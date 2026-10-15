<?php
/**
 * Sauvegarde et restauration de toute la base au format JSON portable (MySQL <-> SQLite).
 *
 * Format :
 * {
 *   "format": "cantine-backup", "format_version": 1, "app_version": "1.2.0", "schema_version": 1,
 *   "created_at": "2026-10-14T18:30:00Z",
 *   "tables": { "users": [...], "media": [...], "genres": [...], "media_genres": [...],
 *               "user_media": [...], "comments": [...], "settings": [...] }
 * }
 * Les tables techniques (login_attempts, api_cache, watch_providers, remember_tokens) ne sont pas
 * sauvegardées : elles se reconstruisent seules.
 */
declare(strict_types=1);

namespace Cantine\Services;

defined('CANTINE') || exit;

use Cantine\Support\Db;
use Cantine\Support\Migrator;
use Cantine\Types;

final class Backup
{
    public const FORMAT = 'cantine-backup';
    public const FORMAT_VERSION = 1;

    /** Tables sauvegardées, dans l'ordre des dépendances (ordre d'insertion). */
    public const TABLES = [
        'users' => ['id', 'username', 'display_name', 'password_hash', 'role', 'avatar_color', 'is_active',
            'must_change_password', 'session_version', 'last_seen_at', 'previous_seen_at', 'is_demo', 'created_at', 'updated_at'],
        'media' => ['id', 'type', 'source', 'external_id', 'title', 'normalized_title', 'year', 'description', 'image_url',
            'external_url', 'trailer_url', 'isbn', 'extra', 'added_by', 'is_demo', 'created_at', 'updated_at'],
        'genres' => ['id', 'name'],
        'media_genres' => ['media_id', 'genre_id'],
        'user_media' => ['user_id', 'media_id', 'status', 'rating', 'updated_at'],
        'comments' => ['id', 'media_id', 'user_id', 'body', 'is_spoiler', 'is_demo', 'created_at', 'updated_at'],
        'settings' => ['name', 'value'],
    ];

    /** Réglages jamais restaurés depuis une sauvegarde (propres à l'instance en cours). */
    private const LOCAL_SETTINGS = ['schema_version', 'maintenance', 'update_latest', 'update_checked_at', 'update_error'];

    /** Réglages secrets, exclus de l'export par défaut. */
    public const SECRET_SETTINGS = ['tmdb_key', 'rawg_key'];

    /** Tables techniques vidées lors d'une restauration. */
    private const TECHNICAL_TABLES = ['watch_providers', 'remember_tokens', 'login_attempts', 'api_cache'];

    public function __construct(private Db $db)
    {
    }

    public static function filename(): string
    {
        return 'cantine-sauvegarde-' . gmdate('Y-m-d') . '.json';
    }

    /**
     * Écrit la sauvegarde dans $out (flux), table par table et ligne par ligne, sans tout charger en mémoire.
     * @param resource $out
     */
    public function export($out, bool $includeSecrets = false, bool $includeDemo = false): void
    {
        $header = [
            'format' => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'app_version' => CANTINE_VERSION,
            'schema_version' => (new Migrator($this->db))->currentVersion(),
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        $json = json_encode($header, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        fwrite($out, substr($json, 0, -1) . ",\n\"tables\": {\n");

        $firstTable = true;
        foreach (self::TABLES as $table => $columns) {
            fwrite($out, ($firstTable ? '' : ",\n") . json_encode($table) . ": [\n");
            $firstTable = false;
            $firstRow = true;
            $stmt = $this->db->run($this->exportQuery($table, $columns, $includeDemo));
            while ($row = $stmt->fetch()) {
                if ($table === 'settings' && (in_array($row['name'], self::LOCAL_SETTINGS, true)
                    || (!$includeSecrets && in_array($row['name'], self::SECRET_SETTINGS, true))
                    || (!$includeDemo && $row['name'] === 'demo_loaded'))) {
                    continue;
                }
                fwrite($out, ($firstRow ? '' : ",\n") . json_encode(self::castRow($table, $row), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $firstRow = false;
            }
            fwrite($out, "\n]");
        }
        fwrite($out, "\n}\n}\n");
    }

    /** Requête d'export ; sans les données de démo, on exclut tout ce qui est marqué is_demo et ce qui en dépend. */
    private function exportQuery(string $table, array $columns, bool $includeDemo): string
    {
        $cols = implode(', ', array_map(static fn ($c) => 't.' . $c, $columns));
        $sql = "SELECT $cols FROM {" . $table . '} t';
        if ($includeDemo) {
            return $sql . self::orderBy($table);
        }
        $where = match ($table) {
            'users', 'comments' => 't.is_demo = 0',
            'media' => 't.is_demo = 0',
            'media_genres' => 't.media_id IN (SELECT id FROM {media} WHERE is_demo = 0)',
            'user_media' => 't.media_id IN (SELECT id FROM {media} WHERE is_demo = 0) AND t.user_id IN (SELECT id FROM {users} WHERE is_demo = 0)',
            default => '',
        };
        if ($table === 'comments') {
            $where .= ' AND t.media_id IN (SELECT id FROM {media} WHERE is_demo = 0) AND t.user_id IN (SELECT id FROM {users} WHERE is_demo = 0)';
        }
        if ($table === 'media') {
            // Un contenu réel ajouté par un compte de démo : il est gardé, sans auteur.
            $cols = str_replace('t.added_by', 'CASE WHEN t.added_by IN (SELECT id FROM {users} WHERE is_demo = 1) THEN NULL ELSE t.added_by END AS added_by', $cols);
            $sql = "SELECT $cols FROM {media} t";
        }
        return $sql . ($where !== '' ? ' WHERE ' . $where : '') . self::orderBy($table);
    }

    private static function orderBy(string $table): string
    {
        return match ($table) {
            'media_genres' => ' ORDER BY t.media_id, t.genre_id',
            'user_media' => ' ORDER BY t.user_id, t.media_id',
            'settings' => ' ORDER BY t.name',
            default => ' ORDER BY t.id',
        };
    }

    /** Types homogènes quel que soit le moteur (MySQL renvoie parfois des chaînes). */
    private static function castRow(string $table, array $row): array
    {
        $ints = ['id', 'is_active', 'must_change_password', 'session_version', 'is_demo', 'year', 'added_by',
            'media_id', 'genre_id', 'user_id', 'rating', 'is_spoiler'];
        foreach ($row as $key => $value) {
            if ($value !== null && in_array($key, $ints, true)) {
                $row[$key] = (int) $value;
            }
        }
        return $row;
    }

    // ---- Restauration ----

    /**
     * Valide complètement une sauvegarde AVANT tout changement.
     * @return array{ok: bool, errors: list<string>, summary: array, data: ?array}
     */
    public function validate(string $json): array
    {
        $errors = [];
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['ok' => false, 'errors' => [t('backup.invalid_json')], 'summary' => [], 'data' => null];
        }
        if (!is_array($data) || ($data['format'] ?? null) !== self::FORMAT) {
            return ['ok' => false, 'errors' => [t('backup.invalid_format')], 'summary' => [], 'data' => null];
        }
        if ((int) ($data['format_version'] ?? 0) > self::FORMAT_VERSION) {
            $errors[] = t('backup.too_new_format');
        }
        $schema = (int) ($data['schema_version'] ?? 0);
        if ($schema < 1 || $schema > Migrator::latestVersion()) {
            $errors[] = t('backup.incompatible_schema', ['backup' => (string) $schema, 'site' => (string) Migrator::latestVersion()]);
        }
        $tables = $data['tables'] ?? null;
        if (!is_array($tables)) {
            $errors[] = t('backup.invalid_format');
            return ['ok' => false, 'errors' => $errors, 'summary' => [], 'data' => null];
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'summary' => [], 'data' => null];
        }

        // Adaptation des anciennes versions du schéma vers la version actuelle.
        $tables = self::upgrade($tables, $schema);

        foreach (self::TABLES as $table => $columns) {
            if (!isset($tables[$table]) || !is_array($tables[$table])) {
                $errors[] = t('backup.missing_table', ['table' => $table]);
                continue;
            }
            foreach ($tables[$table] as $i => $row) {
                if (!is_array($row) || array_diff($columns, array_keys($row))) {
                    $errors[] = t('backup.bad_row', ['table' => $table, 'n' => (string) ($i + 1)]);
                    break;
                }
            }
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'summary' => [], 'data' => null];
        }

        // Cohérence : clés étrangères, valeurs de statut et de note.
        $userIds = array_flip(array_map(static fn ($r) => (int) $r['id'], $tables['users']));
        $mediaIds = array_flip(array_map(static fn ($r) => (int) $r['id'], $tables['media']));
        $genreIds = array_flip(array_map(static fn ($r) => (int) $r['id'], $tables['genres']));
        $admins = 0;
        foreach ($tables['users'] as $u) {
            if (!is_string($u['username']) || !is_string($u['password_hash']) || $u['password_hash'] === '') {
                $errors[] = t('backup.bad_user');
                break;
            }
            if ($u['role'] === 'admin' && (int) $u['is_active'] === 1) {
                $admins++;
            }
        }
        if ($admins === 0) {
            $errors[] = t('backup.no_admin');
        }
        foreach ($tables['media'] as $m) {
            if ($m['added_by'] !== null && !isset($userIds[(int) $m['added_by']])) {
                $errors[] = t('backup.bad_reference', ['table' => 'media']);
                break;
            }
        }
        foreach ($tables['media_genres'] as $mg) {
            if (!isset($mediaIds[(int) $mg['media_id']]) || !isset($genreIds[(int) $mg['genre_id']])) {
                $errors[] = t('backup.bad_reference', ['table' => 'media_genres']);
                break;
            }
        }
        foreach ($tables['user_media'] as $um) {
            if (!isset($mediaIds[(int) $um['media_id']]) || !isset($userIds[(int) $um['user_id']])) {
                $errors[] = t('backup.bad_reference', ['table' => 'user_media']);
                break;
            }
            if (!Types::isStatus((string) $um['status']) || ($um['rating'] !== null && ((int) $um['rating'] < 1 || (int) $um['rating'] > 5))) {
                $errors[] = t('backup.bad_status');
                break;
            }
        }
        foreach ($tables['comments'] as $c) {
            if (!isset($mediaIds[(int) $c['media_id']]) || !isset($userIds[(int) $c['user_id']])) {
                $errors[] = t('backup.bad_reference', ['table' => 'comments']);
                break;
            }
        }

        $summary = [
            'created_at' => (string) ($data['created_at'] ?? ''),
            'app_version' => (string) ($data['app_version'] ?? '?'),
            'schema_version' => $schema,
            'users' => count($tables['users']),
            'media' => count($tables['media']),
            'ratings' => count(array_filter($tables['user_media'], static fn ($r) => $r['rating'] !== null)),
            'statuses' => count($tables['user_media']),
            'comments' => count($tables['comments']),
            'has_secrets' => (bool) array_filter($tables['settings'], static fn ($r) => in_array($r['name'], self::SECRET_SETTINGS, true)),
        ];
        $data['tables'] = $tables;
        return ['ok' => !$errors, 'errors' => $errors, 'summary' => $summary, 'data' => $errors ? null : $data];
    }

    /**
     * Fonctions d'adaptation par version : transforment les données d'un ancien schéma vers le suivant.
     * Exemple pour une future migration 002 : 1 => fn (array $tables) => ... (ajout d'une colonne, etc.).
     */
    private static function upgrade(array $tables, int $fromVersion): array
    {
        $adapters = [
            // 1 => static function (array $tables): array { ...; return $tables; },
        ];
        for ($v = $fromVersion; $v < Migrator::latestVersion(); $v++) {
            if (isset($adapters[$v])) {
                $tables = $adapters[$v]($tables);
            }
        }
        return $tables;
    }

    /**
     * Remplace toutes les données par celles de la sauvegarde validée, dans une transaction (tout ou rien).
     * Les clés d'API et le mode maintenance actuels sont conservés si la sauvegarde ne les contient pas.
     */
    public function restore(array $data): void
    {
        $tables = $data['tables'];
        $keep = [];
        foreach ($this->db->all('SELECT name, value FROM {settings}') as $row) {
            if (in_array($row['name'], self::LOCAL_SETTINGS, true) || in_array($row['name'], self::SECRET_SETTINGS, true)) {
                $keep[$row['name']] = $row['value'];
            }
        }

        $this->db->transaction(function () use ($tables, $keep): void {
            // DELETE (et non TRUNCATE, qui provoque un commit implicite sous MySQL), dans l'ordre inverse des dépendances.
            foreach (array_merge(self::TECHNICAL_TABLES, array_reverse(array_keys(self::TABLES))) as $table) {
                $this->db->run('DELETE FROM {' . $table . '}');
            }
            foreach (self::TABLES as $table => $columns) {
                $placeholders = implode(', ', array_map(static fn ($c) => ':' . $c, $columns));
                $stmt = $this->db->pdo->prepare($this->db->sql('INSERT INTO {' . $table . '} (' . implode(', ', $columns) . ") VALUES ($placeholders)"));
                foreach ($tables[$table] as $row) {
                    if ($table === 'settings' && isset($keep[$row['name']]) && in_array($row['name'], self::LOCAL_SETTINGS, true)) {
                        continue;
                    }
                    foreach ($columns as $column) {
                        $value = $row[$column];
                        $stmt->bindValue(':' . $column, is_bool($value) ? (int) $value : $value, match (true) {
                            $value === null => \PDO::PARAM_NULL,
                            is_int($value), is_bool($value) => \PDO::PARAM_INT,
                            default => \PDO::PARAM_STR,
                        });
                    }
                    $stmt->execute();
                }
            }
            $restoredNames = array_column($tables['settings'], 'name');
            foreach ($keep as $name => $value) {
                if (in_array($name, self::LOCAL_SETTINGS, true) || !in_array($name, $restoredNames, true)) {
                    $this->db->upsert('settings', ['value' => $value], ['name' => $name]);
                }
            }
        });

        // Remise à niveau des compteurs auto-incrément (après le commit : ALTER TABLE valide implicitement sous MySQL).
        if ($this->db->driver === 'mysql') {
            foreach (['users', 'media', 'genres', 'comments'] as $table) {
                $next = (int) $this->db->value('SELECT COALESCE(MAX(id), 0) + 1 FROM {' . $table . '}');
                $this->db->pdo->exec('ALTER TABLE ' . $this->db->table($table) . ' AUTO_INCREMENT = ' . $next);
            }
        }
        // SQLite : sqlite_sequence est mise à jour automatiquement lors de l'insertion d'identifiants explicites.
    }

    // ---- Sauvegardes de sécurité dans data/sauvegardes/ ----

    public static function safetyDir(): string
    {
        return CANTINE_DATA . '/sauvegardes';
    }

    /** Sauvegarde automatique de l'état actuel avant une restauration. Garde les 5 dernières. */
    public function writeSafetyCopy(): string
    {
        $dir = self::safetyDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $file = $dir . '/securite-' . gmdate('Y-m-d-His') . '.json';
        $out = fopen($file, 'wb');
        if ($out === false) {
            throw new \RuntimeException(t('backup.safety_failed'));
        }
        try {
            $this->export($out, true, true);
        } finally {
            fclose($out);
        }
        @chmod($file, 0640);
        $files = self::safetyFiles();
        foreach (array_slice($files, 5) as $old) {
            @unlink($dir . '/' . $old['name']);
        }
        return basename($file);
    }

    /** @return list<array{name: string, size: int, date: string}> du plus récent au plus ancien */
    public static function safetyFiles(): array
    {
        $files = [];
        foreach (glob(self::safetyDir() . '/securite-*.json') ?: [] as $path) {
            $files[] = ['name' => basename($path), 'size' => (int) filesize($path), 'date' => gmdate('Y-m-d H:i:s', (int) filemtime($path))];
        }
        usort($files, static fn ($a, $b) => strcmp($b['name'], $a['name']));
        return $files;
    }
}
