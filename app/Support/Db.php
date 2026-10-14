<?php
/**
 * Accès à la base de données (PDO), commun à MySQL/MariaDB et SQLite.
 *
 * Dans le SQL, les noms de tables s'écrivent entre accolades : SELECT * FROM {media}.
 * Ils sont remplacés par le nom réel avec le préfixe éventuel (ex. cantine_media).
 * Les différences entre moteurs sont isolées ici (random(), insertOrIgnore()...).
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

use PDO;
use PDOStatement;

final class Db
{
    private int $transactionDepth = 0;

    public function __construct(
        public readonly PDO $pdo,
        public readonly string $driver, // 'mysql' ou 'sqlite'
        public readonly string $prefix = '',
    ) {
    }

    /**
     * Ouvre la connexion à partir du bloc 'db' de config.php.
     * @throws \PDOException
     */
    public static function connect(array $config): self
    {
        $driver = $config['driver'] ?? 'sqlite';
        $prefix = (string) ($config['prefix'] ?? '');
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($driver === 'mysql') {
            $dsn = self::mysqlDsn($config, true);
            $options[PDO::ATTR_EMULATE_PREPARES] = false;
            $pdo = new PDO($dsn, (string) ($config['user'] ?? ''), (string) ($config['password'] ?? ''), $options);
            $pdo->exec("SET time_zone = '+00:00'");
        } else {
            $pdo = new PDO('sqlite:' . self::sqlitePath($config), null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            $pdo->exec('PRAGMA journal_mode = WAL');
        }

        return new self($pdo, $driver, $prefix);
    }

    public static function mysqlDsn(array $config, bool $withDatabase): string
    {
        $host = (string) ($config['host'] ?? 'localhost');
        $dsn = 'mysql:host=' . $host;
        if (!empty($config['port'])) {
            $dsn .= ';port=' . (int) $config['port'];
        }
        if ($withDatabase) {
            $dsn .= ';dbname=' . (string) ($config['name'] ?? '');
        }
        return $dsn . ';charset=utf8mb4';
    }

    /** Chemin du fichier SQLite : relatif au dossier data/ sauf s'il est absolu. */
    public static function sqlitePath(array $config): string
    {
        $path = (string) ($config['path'] ?? 'cantine.sqlite');
        if ($path === ':memory:' || str_starts_with($path, '/') || preg_match('~^[A-Za-z]:[\\\\/]~', $path)) {
            return $path;
        }
        return CANTINE_DATA . '/' . $path;
    }

    /** Nom réel d'une table (avec préfixe). */
    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    /** Remplace les {table} par les noms réels. */
    public function sql(string $sql): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', fn (array $m) => $this->prefix . $m[1], $sql);
    }

    /** Exécute une requête préparée. Les entiers sont liés en PARAM_INT (nécessaire pour LIMIT/OFFSET). */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($this->sql($sql));
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            if (is_int($value)) {
                $stmt->bindValue($name, $value, PDO::PARAM_INT);
            } elseif (is_bool($value)) {
                $stmt->bindValue($name, $value ? 1 : 0, PDO::PARAM_INT);
            } elseif ($value === null) {
                $stmt->bindValue($name, null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue($name, (string) $value, PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        return $stmt;
    }

    /** @return list<array<string, mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** Insère une ligne et renvoie son identifiant. */
    public function insert(string $table, array $row): int
    {
        $columns = array_keys($row);
        $sql = 'INSERT INTO {' . $table . '} (' . implode(', ', $columns) . ') VALUES ('
            . implode(', ', array_map(static fn ($c) => ':' . $c, $columns)) . ')';
        $this->run($sql, $row);
        return (int) $this->pdo->lastInsertId();
    }

    /** Met à jour les lignes correspondant à $where (ex. ['id' => 3]). Renvoie le nombre de lignes modifiées. */
    public function update(string $table, array $row, array $where): int
    {
        $set = [];
        $params = [];
        foreach ($row as $column => $value) {
            $set[] = $column . ' = :set_' . $column;
            $params['set_' . $column] = $value;
        }
        $conditions = [];
        foreach ($where as $column => $value) {
            $conditions[] = $column . ' = :where_' . $column;
            $params['where_' . $column] = $value;
        }
        $sql = 'UPDATE {' . $table . '} SET ' . implode(', ', $set) . ' WHERE ' . implode(' AND ', $conditions);
        return $this->run($sql, $params)->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        $conditions = [];
        foreach (array_keys($where) as $column) {
            $conditions[] = $column . ' = :' . $column;
        }
        return $this->run('DELETE FROM {' . $table . '} WHERE ' . implode(' AND ', $conditions), $where)->rowCount();
    }

    /**
     * "Upsert" portable : UPDATE, puis INSERT si la ligne n'existe pas.
     * (On évite ON DUPLICATE KEY / ON CONFLICT, propres à chaque moteur.)
     */
    public function upsert(string $table, array $row, array $key): void
    {
        $this->transaction(function () use ($table, $row, $key): void {
            $exists = $this->value(
                'SELECT 1 FROM {' . $table . '} WHERE ' . implode(' AND ', array_map(static fn ($c) => "$c = :$c", array_keys($key))),
                $key
            );
            if ($exists) {
                $this->update($table, $row, $key);
            } else {
                $this->insert($table, $key + $row);
            }
        });
    }

    /**
     * Exécute $callback dans une transaction (tout ou rien).
     * Les appels imbriqués réutilisent la transaction en cours.
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->transactionDepth > 0) {
            $this->transactionDepth++;
            try {
                return $callback($this);
            } finally {
                $this->transactionDepth--;
            }
        }

        $this->pdo->beginTransaction();
        $this->transactionDepth = 1;
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } finally {
            $this->transactionDepth = 0;
        }
    }

    /** Date courante en UTC au format de la base. */
    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    /** Date UTC décalée : Db::ago(900) = il y a 15 minutes. */
    public static function ago(int $seconds): string
    {
        return gmdate('Y-m-d H:i:s', time() - $seconds);
    }

    /** Vrai si l'exception correspond à une violation de contrainte d'unicité. */
    public static function isUniqueViolation(\PDOException $e): bool
    {
        $info = $e->errorInfo ?? [];
        // MySQL : 1062 ; SQLite : code 19 (SQLITE_CONSTRAINT) avec message "UNIQUE constraint failed"
        return ($info[1] ?? null) === 1062
            || (($info[0] ?? '') === '23000' && str_contains($e->getMessage(), 'UNIQUE'));
    }

    /** Liste des tables existantes (sans préfixe retiré). */
    public function tables(): array
    {
        if ($this->driver === 'mysql') {
            return $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        }
        return $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
    }
}
