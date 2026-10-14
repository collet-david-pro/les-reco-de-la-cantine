<?php
/**
 * Migrations versionnées.
 *
 * Chaque fichier migrations/NNN_description.php renvoie :
 *  - soit ['mysql' => [sql, ...], 'sqlite' => [sql, ...]] ;
 *  - soit une fonction function (Db $db): void pour les cas complexes.
 * La version du schéma (numéro de la dernière migration appliquée) est stockée dans settings.schema_version.
 * MySQL valide implicitement les CREATE/ALTER : on applique donc les migrations une par une
 * et on enregistre la version après CHAQUE migration réussie.
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class Migrator
{
    public function __construct(private Db $db)
    {
    }

    /** @return array<int, string> numéro => chemin du fichier, trié */
    public static function files(): array
    {
        $files = [];
        foreach (glob(CANTINE_ROOT . '/migrations/[0-9][0-9][0-9]_*.php') ?: [] as $file) {
            $files[(int) substr(basename($file), 0, 3)] = $file;
        }
        ksort($files);
        return $files;
    }

    /** Version attendue par le code = numéro de la dernière migration livrée. */
    public static function latestVersion(): int
    {
        $files = self::files();
        return $files ? (int) array_key_last($files) : 0;
    }

    /** Version actuelle de la base (0 si rien n'est installé). */
    public function currentVersion(): int
    {
        try {
            return (int) $this->db->value("SELECT value FROM {settings} WHERE name = 'schema_version'");
        } catch (\PDOException) {
            return 0; // table settings absente
        }
    }

    public function pending(): array
    {
        $current = $this->currentVersion();
        return array_filter(self::files(), static fn (int $n) => $n > $current, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Applique les migrations en attente.
     * @return list<string> noms des migrations appliquées
     */
    public function migrate(): array
    {
        $applied = [];
        foreach ($this->pending() as $number => $file) {
            $migration = require $file;
            if (is_callable($migration)) {
                $migration($this->db);
            } else {
                foreach ($migration[$this->db->driver] ?? [] as $sql) {
                    $this->db->pdo->exec($this->db->sql($sql));
                }
            }
            $this->db->upsert('settings', ['value' => (string) $number], ['name' => 'schema_version']);
            $applied[] = basename($file, '.php');
        }
        return $applied;
    }
}
