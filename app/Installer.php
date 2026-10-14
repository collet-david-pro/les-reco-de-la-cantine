<?php
/**
 * Assistant d'installation (install.php), sur le principe de WordPress :
 *  1. test de l'environnement ;
 *  2. accès à la base (MySQL/MariaDB ou SQLite), avec bouton "Tester la connexion" ;
 *  3. compte administrateur, puis création des tables, écriture de data/config.php et verrouillage.
 * Une fois installé, l'installeur refuse de se relancer.
 */
declare(strict_types=1);

namespace Cantine;

defined('CANTINE') || exit;

use Cantine\Repositories\UserRepository;
use Cantine\Support\Csrf;
use Cantine\Support\Db;
use Cantine\Support\Diagnostics;
use Cantine\Support\Migrator;
use Cantine\Support\View;

final class Installer
{
    public const LOCK_FILE = CANTINE_DATA . '/install.lock';
    public const CONFIG_FILE = CANTINE_DATA . '/config.php';

    public static function run(): void
    {
        App::sendSecurityHeaders();
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        View::$shared = ['siteName' => 'Les reco de la cantine'];

        try {
            echo self::handle();
        } catch (\Throwable $e) {
            echo View::page('install/message', [
                'title' => 'Erreur pendant l\'installation',
                'heading' => 'Erreur pendant l\'installation',
                'message' => $e->getMessage(),
                'kind' => 'error',
            ], 'install/layout');
        }
    }

    public static function isInstalled(): bool
    {
        return is_file(self::CONFIG_FILE) || is_file(self::LOCK_FILE);
    }

    private static function handle(): string
    {
        if (self::isInstalled()) {
            return View::page('install/message', [
                'title' => 'Déjà installé',
                'heading' => 'Le site est déjà installé',
                'message' => 'Pour des raisons de sécurité, l\'installeur ne peut pas être relancé. Vous pouvez supprimer le fichier install.php du serveur.',
                'kind' => 'info',
                'link' => [App::baseUrl() . 'index.php', 'Aller au site'],
            ], 'install/layout');
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_name('cantine_install');
            session_set_cookie_params(['path' => App::cookiePath(), 'httponly' => true, 'samesite' => 'Lax', 'secure' => App::isHttps()]);
            session_start();
        }

        $step = (int) ($_GET['etape'] ?? 1);
        $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        if ($post && !Csrf::check(Csrf::fromRequest())) {
            return self::redirect($step, 'Le formulaire a expiré, recommencez.');
        }

        // On ne peut pas sauter d'étape.
        if ($step >= 2 && empty($_SESSION['install_env_ok'])) {
            $step = 1;
        }
        if ($step >= 3 && empty($_SESSION['install_db'])) {
            $step = 2;
        }

        return match ($step) {
            2 => self::stepDatabase($post),
            3 => self::stepAdmin($post),
            default => self::stepEnvironment(),
        };
    }

    private static function redirect(int $step, string $message = ''): string
    {
        if ($message !== '') {
            $_SESSION['install_flash'] = $message;
        }
        header('Location: ' . App::baseUrl() . 'install.php?etape=' . $step, true, 303);
        return '';
    }

    private static function page(string $template, array $data): string
    {
        $data['flash'] = $_SESSION['install_flash'] ?? null;
        unset($_SESSION['install_flash']);
        return View::page('install/' . $template, $data, 'install/layout');
    }

    // ---- Étape 1 : environnement ----

    private static function stepEnvironment(): string
    {
        $checks = Diagnostics::environment(true);
        $ok = !Diagnostics::hasFailure($checks);
        $_SESSION['install_env_ok'] = $ok;
        return self::page('environment', ['title' => 'Installation — étape 1', 'step' => 1, 'checks' => $checks, 'ok' => $ok]);
    }

    // ---- Étape 2 : base de données ----

    private static function stepDatabase(bool $post): string
    {
        $values = $_SESSION['install_db'] ?? [
            'driver' => extension_loaded('pdo_mysql') ? 'mysql' : 'sqlite',
            'host' => 'localhost', 'port' => '', 'name' => '', 'user' => '', 'password' => '', 'prefix' => '',
        ];
        $results = null;
        $error = null;

        if ($post) {
            $values = self::readDbForm();
            [$results, $ok] = self::testDatabase($values);
            if (($_POST['action'] ?? '') === 'continue' && $ok) {
                $_SESSION['install_db'] = $values;
                return self::redirect(3);
            }
            if (!$ok) {
                $error = 'La connexion ne fonctionne pas encore : corrigez les points en rouge puis réessayez.';
            }
        }

        return self::page('database', [
            'title' => 'Installation — étape 2',
            'step' => 2,
            'values' => $values,
            'results' => $results,
            'error' => $error,
            'hasMysql' => extension_loaded('pdo_mysql'),
            'hasSqlite' => extension_loaded('pdo_sqlite'),
        ]);
    }

    private static function readDbForm(): array
    {
        $get = static fn (string $name): string => is_string($_POST[$name] ?? null) ? trim($_POST[$name]) : '';
        $driver = $get('driver') === 'mysql' ? 'mysql' : 'sqlite';
        $prefix = strtolower($get('prefix'));
        return [
            'driver' => $driver,
            'host' => $get('host') !== '' ? $get('host') : 'localhost',
            'port' => preg_match('/^\d{1,5}$/', $get('port')) ? $get('port') : '',
            'name' => $get('name'),
            'user' => $get('user'),
            // Le mot de passe saisi est conservé tel quel (espaces compris).
            'password' => is_string($_POST['password'] ?? null) ? $_POST['password'] : '',
            'prefix' => $prefix,
        ];
    }

    /**
     * Teste l'accès à la base étape par étape et explique chaque échec.
     * @return array{0: list<array>, 1: bool}
     */
    public static function testDatabase(array $v): array
    {
        $checks = [];
        $add = static function (string $label, string $status, string $detail = '') use (&$checks): void {
            $checks[] = ['label' => $label, 'status' => $status, 'detail' => $detail];
        };

        if ($v['prefix'] !== '' && !preg_match('/^[a-z][a-z0-9_]{0,19}$/', $v['prefix'])) {
            $add('Préfixe des tables', 'fail', 'Lettres minuscules, chiffres et _ uniquement (20 caractères max), en commençant par une lettre. Exemple : cantine_');
            return [$checks, false];
        }

        if ($v['driver'] === 'sqlite') {
            if (!extension_loaded('pdo_sqlite')) {
                $add('Pilote SQLite', 'fail', 'L\'extension pdo_sqlite est absente.');
                return [$checks, false];
            }
            self::prepareDataDir();
            try {
                $db = Db::connect(['driver' => 'sqlite', 'path' => 'cantine.sqlite', 'prefix' => $v['prefix']]);
                $add('Fichier de base data/cantine.sqlite', 'ok');
            } catch (\PDOException $e) {
                $add('Fichier de base data/cantine.sqlite', 'fail', 'Impossible de créer ou d\'ouvrir le fichier : vérifiez les droits d\'écriture sur data/.');
                return [$checks, false];
            }
            $ok = self::testPrivileges($db, $add);
            if ($ok) {
                $ok = self::checkEmpty($db, $add);
            }
            return [$checks, $ok];
        }

        if (!extension_loaded('pdo_mysql')) {
            $add('Pilote MySQL', 'fail', 'L\'extension pdo_mysql est absente : choisissez SQLite ou activez pdo_mysql.');
            return [$checks, false];
        }
        if ($v['name'] === '' || $v['user'] === '') {
            $add('Champs obligatoires', 'fail', 'Le nom de la base et l\'utilisateur sont obligatoires.');
            return [$checks, false];
        }

        // 1. Connexion au serveur, sans choisir de base.
        $options = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5];
        try {
            new \PDO(Db::mysqlDsn($v, false), $v['user'], $v['password'], $options);
            $add('Connexion au serveur ' . $v['host'], 'ok');
        } catch (\PDOException $e) {
            $add('Connexion au serveur ' . $v['host'], 'fail', self::explainMysqlError($e));
            return [$checks, false];
        }

        // 2. Accès à la base.
        try {
            $db = Db::connect(['driver' => 'mysql'] + $v);
            $add('Accès à la base « ' . $v['name'] . ' »', 'ok');
        } catch (\PDOException $e) {
            $add('Accès à la base « ' . $v['name'] . ' »', 'fail', self::explainMysqlError($e));
            return [$checks, false];
        }

        // 3. Encodage.
        try {
            $charset = (string) $db->value("SELECT @@character_set_connection");
            $dbCharset = (string) $db->value("SELECT @@character_set_database");
            $add('Encodage utf8mb4', $charset === 'utf8mb4' ? 'ok' : 'fail',
                $charset === 'utf8mb4'
                    ? ($dbCharset === 'utf8mb4' ? '' : 'La base est en ' . $dbCharset . ', mais les tables seront créées en utf8mb4.')
                    : 'Le serveur ne gère pas utf8mb4 (version de MySQL trop ancienne ?).');
            if ($charset !== 'utf8mb4') {
                return [$checks, false];
            }
        } catch (\PDOException $e) {
            $add('Encodage utf8mb4', 'warn', 'Vérification impossible.');
        }

        // 4. Droits.
        $ok = self::testPrivileges($db, $add);
        if ($ok) {
            $ok = self::checkEmpty($db, $add);
        }
        return [$checks, $ok];
    }

    /** Crée une table temporaire et vérifie CREATE, ALTER, INSERT, SELECT, UPDATE, DELETE, DROP. */
    private static function testPrivileges(Db $db, callable $add): bool
    {
        $table = $db->table('install_test');
        $steps = [
            'CREATE' => $db->driver === 'mysql'
                ? "CREATE TABLE $table (id INT NOT NULL PRIMARY KEY, label VARCHAR(20)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                : "CREATE TABLE $table (id INTEGER NOT NULL PRIMARY KEY, label TEXT)",
            'ALTER' => "ALTER TABLE $table ADD COLUMN extra INT NULL",
            'INSERT' => "INSERT INTO $table (id, label) VALUES (1, 'é🍲')",
            'SELECT' => "SELECT label FROM $table WHERE id = 1",
            'UPDATE' => "UPDATE $table SET label = 'ok' WHERE id = 1",
            'DELETE' => "DELETE FROM $table WHERE id = 1",
            'DROP' => "DROP TABLE $table",
        ];
        try {
            $db->pdo->exec("DROP TABLE IF EXISTS $table");
        } catch (\PDOException) {
        }
        foreach ($steps as $right => $sql) {
            try {
                $stmt = $db->pdo->query($sql);
                if ($right === 'SELECT' && $stmt->fetchColumn() !== 'é🍲') {
                    $add('Droit SELECT et encodage', 'fail', 'Les caractères accentués ou émojis ne sont pas relus correctement.');
                    return false;
                }
            } catch (\PDOException $e) {
                $add('Droit ' . $right, 'fail', 'L\'utilisateur n\'a pas le droit ' . $right . ' sur cette base. Donnez-lui tous les droits sur la base (GRANT ALL PRIVILEGES ON base.* TO utilisateur).');
                try {
                    $db->pdo->exec("DROP TABLE IF EXISTS $table");
                } catch (\PDOException) {
                }
                return false;
            }
        }
        $add('Droits CREATE, ALTER, INSERT, SELECT, UPDATE, DELETE', 'ok');
        return true;
    }

    /** Refuse une base où le site est déjà installé (avec le même préfixe). */
    private static function checkEmpty(Db $db, callable $add): bool
    {
        if (in_array($db->table('users'), $db->tables(), true)) {
            $count = 0;
            try {
                $count = (int) $db->value('SELECT COUNT(*) FROM {users}');
            } catch (\PDOException) {
            }
            if ($count > 0) {
                $add('Base libre', 'fail', 'Cette base contient déjà un site installé avec ce préfixe. Choisissez une base vide ou un autre préfixe de tables. Pour réutiliser ces données, voir la section « Réinstaller » du README.');
                return false;
            }
        }
        $add('Base libre', 'ok');
        return true;
    }

    /** Traduit les codes d'erreur MySQL courants en explications. */
    public static function explainMysqlError(\PDOException $e): string
    {
        $code = preg_match('/\[(\d{4})\]/', $e->getMessage(), $m) ? (int) $m[1] : (int) ($e->errorInfo[1] ?? 0);
        return match ($code) {
            1045 => 'Accès refusé : identifiant ou mot de passe MySQL incorrect.',
            1044 => 'Accès refusé : cet utilisateur n\'a pas le droit d\'utiliser cette base.',
            1049 => 'Base inconnue : vérifiez son nom, ou créez-la d\'abord (depuis le panneau de votre hébergeur).',
            2002 => 'Serveur injoignable : vérifiez l\'hôte et le port. Essayez « localhost » au lieu de « 127.0.0.1 » (ou l\'inverse).',
            2005 => 'Hôte inconnu : vérifiez l\'adresse du serveur MySQL.',
            2006, 2013 => 'Le serveur MySQL a coupé la connexion. Réessayez.',
            1142 => 'Droit manquant pour cet utilisateur sur la base.',
            default => 'Erreur MySQL' . ($code ? ' ' . $code : '') . '. Vérifiez les informations saisies.',
        };
    }

    // ---- Étape 3 : compte administrateur et fin de l'installation ----

    private static function stepAdmin(bool $post): string
    {
        $values = ['display_name' => '', 'username' => 'admin'];
        $errors = [];

        if ($post) {
            $values = [
                'display_name' => trim((string) ($_POST['display_name'] ?? '')),
                'username' => strtolower(trim((string) ($_POST['username'] ?? ''))),
            ];
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['confirm'] ?? '');
            $errors = array_filter([
                'display_name' => UserRepository::validateDisplayName($values['display_name']),
                'username' => UserRepository::validateUsername($values['username']),
                'password' => UserRepository::validatePassword($password, 12),
            ]);
            if (!isset($errors['password']) && $password !== $confirm) {
                $errors['confirm'] = 'Les deux mots de passe ne correspondent pas.';
            }
            if (!$errors) {
                return self::finish($_SESSION['install_db'], $values, $password);
            }
        }

        return self::page('admin', ['title' => 'Installation — étape 3', 'step' => 3, 'values' => $values, 'errors' => $errors]);
    }

    /** Crée data/, les tables, le compte admin, écrit config.php puis install.lock. */
    private static function finish(array $dbValues, array $admin, string $password): string
    {
        self::prepareDataDir();

        $dbConfig = $dbValues['driver'] === 'mysql'
            ? ['driver' => 'mysql', 'host' => $dbValues['host'], 'port' => $dbValues['port'], 'name' => $dbValues['name'],
                'user' => $dbValues['user'], 'password' => $dbValues['password'], 'prefix' => $dbValues['prefix']]
            : ['driver' => 'sqlite', 'path' => 'cantine.sqlite', 'prefix' => $dbValues['prefix']];

        $db = Db::connect($dbConfig);
        (new Migrator($db))->migrate();
        if (!$db->value('SELECT 1 FROM {users} WHERE username = ?', [$admin['username']])) {
            (new UserRepository($db))->create($admin['username'], $admin['display_name'], $password, 'admin');
        }

        $config = self::configContent($dbConfig);
        $written = @file_put_contents(self::CONFIG_FILE, $config) !== false;
        if ($written) {
            @chmod(self::CONFIG_FILE, 0640);
            @file_put_contents(self::LOCK_FILE, 'Installé le ' . gmdate('Y-m-d H:i:s') . " UTC\n");
        }
        unset($_SESSION['install_db'], $_SESSION['install_env_ok']);

        return View::page('install/done', [
            'title' => 'Installation terminée',
            'step' => 4,
            'written' => $written,
            'config' => $config,
            'siteUrl' => App::baseUrl() . 'index.php',
        ], 'install/layout');
    }

    /** Contenu de data/config.php. */
    public static function configContent(array $dbConfig): string
    {
        $config = [
            'db' => $dbConfig,
            'secret' => bin2hex(random_bytes(32)),
            'site_name' => 'Les reco de la cantine',
            'base_url' => '',
            'trust_proxy' => false,
            'timezone' => 'Europe/Paris',
            'session_idle' => 7200,
            'debug' => false,
        ];
        return "<?php\n"
            . "/*\n"
            . " * Configuration de « Les reco de la cantine » (générée par l'installeur le " . gmdate('Y-m-d') . ").\n"
            . " * Ce fichier contient des secrets : ne le partagez pas et ne le mettez jamais sur GitHub.\n"
            . " *\n"
            . " * - base_url     : à remplir seulement si l'adresse du site est mal détectée (ex. derrière un proxy),\n"
            . " *                  par exemple 'https://cantine.example.org/'.\n"
            . " * - trust_proxy  : true si le site est derrière un proxy inverse qui transmet X-Forwarded-Proto / X-Forwarded-For.\n"
            . " * - timezone     : fuseau horaire d'affichage des dates.\n"
            . " * - session_idle : durée d'inactivité (secondes) avant déconnexion, hors « Rester connecté ».\n"
            . " * - debug        : true pour afficher le détail des erreurs (à ne jamais laisser en production).\n"
            . " */\n"
            . "defined('CANTINE') || exit;\n\n"
            . 'return ' . var_export($config, true) . ";\n";
    }

    /** Crée data/ et ses sous-dossiers, protégés du web. */
    public static function prepareDataDir(): void
    {
        foreach ([CANTINE_DATA, CANTINE_DATA . '/logs', CANTINE_DATA . '/sauvegardes', CANTINE_DATA . '/cache'] as $dir) {
            if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
                throw new \RuntimeException('Impossible de créer le dossier ' . $dir . ' : vérifiez les droits d\'écriture.');
            }
        }
        $htaccess = "# Dossier des données : jamais accessible depuis le web.\n"
            . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
        if (!is_file(CANTINE_DATA . '/.htaccess')) {
            @file_put_contents(CANTINE_DATA . '/.htaccess', $htaccess);
        }
        if (!is_file(CANTINE_DATA . '/index.html')) {
            @file_put_contents(CANTINE_DATA . '/index.html', '');
        }
    }
}
