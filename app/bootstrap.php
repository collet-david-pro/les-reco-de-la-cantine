<?php
/**
 * Chargement commun : constantes, autoload, fonctions utilitaires, gestion des erreurs.
 * Inclus par index.php et install.php (qui définissent la constante CANTINE).
 */
declare(strict_types=1);

defined('CANTINE') || exit;

define('CANTINE_ROOT', dirname(__DIR__));
define('CANTINE_APP', __DIR__);
define('CANTINE_DATA', CANTINE_ROOT . '/data');
define('CANTINE_VERSION', trim((string) @file_get_contents(CANTINE_ROOT . '/VERSION')) ?: '0.0.0');
define('CANTINE_REPO_URL', 'https://github.com/collet-david-pro/les-reco-de-la-cantine');

// Autoload PSR-4 maison : Cantine\Support\Db -> app/Support/Db.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'Cantine\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = CANTINE_APP . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require CANTINE_APP . '/helpers.php';

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8'); // l'installeur doit pouvoir signaler l'absence de mbstring
}
date_default_timezone_set('UTC'); // toutes les dates sont stockées en UTC

// Les erreurs PHP deviennent des exceptions ; l'affichage est géré par App (page d'erreur ou détail en mode debug).
ini_set('display_errors', '0');
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
