<?php
/**
 * Vérifications de l'environnement, partagées par l'installeur et la page de diagnostic de l'admin.
 * Chaque vérification renvoie ['label' => ..., 'status' => 'ok'|'warn'|'fail', 'detail' => ...].
 * "fail" bloque l'installation, "warn" non.
 *
 * Ce fichier n'utilise que des fonctions toujours présentes : il doit fonctionner même si
 * une extension manque, pour pouvoir le signaler clairement.
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class Diagnostics
{
    public const MIN_PHP = '8.1.0';

    /** Liste unique des extensions requises (reprise dans le README). */
    public const REQUIRED_EXTENSIONS = ['pdo', 'curl', 'mbstring', 'json', 'openssl', 'session', 'ctype'];

    /** Fichier témoin pour vérifier que data/ n'est pas accessible depuis le web. */
    public const PROBE_FILE = '.probe';
    public const PROBE_CONTENT = 'cantine-probe';

    private static function check(string $label, string $status, string $detail = ''): array
    {
        return ['label' => $label, 'status' => $status, 'detail' => $detail];
    }

    /** Vérifications de l'étape 1 de l'installation. */
    public static function environment(bool $withNetwork = true): array
    {
        $checks = [];

        $checks[] = version_compare(PHP_VERSION, self::MIN_PHP, '>=')
            ? self::check('PHP ' . self::MIN_PHP . ' ou plus récent', 'ok', 'Version ' . PHP_VERSION)
            : self::check('PHP ' . self::MIN_PHP . ' ou plus récent', 'fail', 'Version actuelle : ' . PHP_VERSION . '. Demandez à votre hébergeur une version plus récente.');

        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $checks[] = extension_loaded($ext)
                ? self::check('Extension ' . $ext, 'ok')
                : self::check('Extension ' . $ext, 'fail', 'Extension PHP manquante : activez-la dans la configuration de PHP ou chez votre hébergeur.');
        }

        $mysql = extension_loaded('pdo_mysql');
        $sqlite = extension_loaded('pdo_sqlite');
        if ($mysql && $sqlite) {
            $checks[] = self::check('Pilotes de base de données', 'ok', 'MySQL/MariaDB et SQLite disponibles');
        } elseif ($mysql || $sqlite) {
            $checks[] = self::check('Pilotes de base de données', 'ok', ($mysql ? 'MySQL/MariaDB' : 'SQLite') . ' disponible (l\'autre pilote est absent)');
        } else {
            $checks[] = self::check('Pilotes de base de données', 'fail', 'Ni pdo_mysql ni pdo_sqlite : au moins un des deux est nécessaire.');
        }

        $argon = defined('PASSWORD_ARGON2ID');
        $checks[] = function_exists('password_hash')
            ? self::check('Hachage des mots de passe', 'ok', $argon ? 'Argon2id' : 'bcrypt')
            : self::check('Hachage des mots de passe', 'fail', 'password_hash indisponible');

        try {
            random_bytes(16);
            $checks[] = self::check('Générateur aléatoire sûr', 'ok');
        } catch (\Throwable) {
            $checks[] = self::check('Générateur aléatoire sûr', 'fail', 'random_bytes ne fonctionne pas sur ce serveur.');
        }

        $sessionPath = (string) ini_get('session.save_path');
        $sessionDir = $sessionPath !== '' ? (string) preg_replace('/^\d+;/', '', $sessionPath) : sys_get_temp_dir();
        $checks[] = extension_loaded('session') && (is_dir($sessionDir) && is_writable($sessionDir))
            ? self::check('Sessions PHP', 'ok')
            : self::check('Sessions PHP', 'warn', 'Le dossier des sessions (' . $sessionDir . ') ne semble pas inscriptible : la connexion pourrait échouer.');

        $checks[] = self::dataDirCheck();

        if ($withNetwork) {
            $checks[] = self::networkCheck();
        }

        $checks[] = self::httpsCheck();
        return $checks;
    }

    public static function dataDirCheck(): array
    {
        $dir = CANTINE_DATA;
        if (!is_dir($dir)) {
            $parent = dirname($dir);
            return is_writable($parent)
                ? self::check('Dossier data/ inscriptible', 'ok', 'Il sera créé à l\'installation.')
                : self::check('Dossier data/ inscriptible', 'fail', 'Impossible de créer ' . $dir . ' : donnez les droits d\'écriture sur le dossier du site, ou créez data/ vous-même avec les droits d\'écriture.');
        }
        return is_writable($dir)
            ? self::check('Dossier data/ inscriptible', 'ok')
            : self::check('Dossier data/ inscriptible', 'fail', 'Le dossier ' . $dir . ' existe mais n\'est pas inscriptible par PHP.');
    }

    public static function networkCheck(): array
    {
        if (!function_exists('curl_init')) {
            return self::check('Accès aux API (réseau sortant)', 'warn', 'cURL absent : la recherche automatique ne fonctionnera pas.');
        }
        $failed = [];
        foreach (['https://api.themoviedb.org/3/configuration', 'https://openlibrary.org/search.json?q=test&limit=1&fields=key'] as $url) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 5, CURLOPT_FOLLOWLOCATION => false,
            ]);
            curl_exec($curl);
            $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($code === 0) {
                $failed[] = (string) parse_url($url, PHP_URL_HOST);
            }
        }
        return $failed
            ? self::check('Accès aux API (réseau sortant)', 'warn', 'Injoignable : ' . implode(', ', $failed) . '. L\'ajout manuel restera possible.')
            : self::check('Accès aux API (réseau sortant)', 'ok');
    }

    public static function httpsCheck(): array
    {
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $local = in_array((string) ($_SERVER['SERVER_NAME'] ?? ''), ['localhost', '127.0.0.1', '::1'], true);
        if ($https) {
            return self::check('HTTPS', 'ok');
        }
        return self::check('HTTPS', 'warn', $local
            ? 'Pas de HTTPS (normal en local). En ligne, HTTPS est fortement recommandé et nécessaire pour l\'application installable.'
            : 'Le site n\'est pas servi en HTTPS : les mots de passe circulent en clair. Activez un certificat (Let\'s Encrypt).');
    }

    /**
     * Tente de lire data/.probe depuis le web : si c'est possible, les données sont exposées.
     * Renvoie 'fail' (rouge) si le fichier est accessible.
     */
    public static function dataProtectionCheck(string $baseUrl): array
    {
        $label = 'Dossier data/ protégé du web';
        if (!is_dir(CANTINE_DATA)) {
            return self::check($label, 'warn', 'Dossier data/ absent.');
        }
        @file_put_contents(CANTINE_DATA . '/' . self::PROBE_FILE, self::PROBE_CONTENT);
        if (!function_exists('curl_init') || empty($_SERVER['HTTP_HOST'])) {
            return self::check($label, 'warn', 'Vérification impossible (cURL absent).');
        }
        $scheme = self::httpsCheck()['status'] === 'ok' ? 'https' : 'http';
        $url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $baseUrl . 'data/' . self::PROBE_FILE;
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $body = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($code === 0) {
            return self::check($label, 'warn', 'Le serveur n\'a pas pu s\'appeler lui-même pour vérifier. Vérifiez à la main que ' . $url . ' renvoie une erreur 403 ou 404.');
        }
        if ($code === 200 && is_string($body) && str_contains($body, self::PROBE_CONTENT)) {
            return self::check($label, 'fail', 'DANGER : ' . $url . ' est lisible depuis le web. Votre configuration et votre base sont exposées. Sous Apache, vérifiez que les fichiers .htaccess sont pris en compte ; sous nginx, ajoutez la règle indiquée dans le README.');
        }
        return self::check($label, 'ok', 'Réponse ' . $code . ' pour data/' . self::PROBE_FILE);
    }

    /** Informations complémentaires pour l'admin. */
    public static function info(Db $db): array
    {
        $dbVersion = '';
        try {
            $dbVersion = (string) $db->value($db->driver === 'mysql' ? 'SELECT VERSION()' : 'SELECT sqlite_version()');
        } catch (\Throwable) {
        }
        return [
            'Version du site' => CANTINE_VERSION,
            'Version du schéma' => (new Migrator($db))->currentVersion() . ' / ' . Migrator::latestVersion(),
            'PHP' => PHP_VERSION . ' (' . PHP_SAPI . ')',
            'Base de données' => ($db->driver === 'mysql' ? 'MySQL/MariaDB ' : 'SQLite ') . $dbVersion,
            'Préfixe des tables' => $db->prefix !== '' ? $db->prefix : '(aucun)',
            'Taille max. d\'envoi' => ini_get('upload_max_filesize') . ' (fichier) / ' . ini_get('post_max_size') . ' (requête)',
            'Limite mémoire' => (string) ini_get('memory_limit'),
            'Fuseau d\'affichage' => Text::$timezone,
            'Serveur web' => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'inconnu'),
        ];
    }

    /** Vrai si une vérification bloque l'installation. */
    public static function hasFailure(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                return true;
            }
        }
        return false;
    }
}
