<?php
/**
 * Routeur pour le serveur de développement intégré de PHP, qui ne lit pas les .htaccess.
 *
 *   php -S localhost:8000 tools/dev-router.php
 *
 * Il refuse l'accès aux dossiers internes (comme le ferait .htaccess) et sert le reste.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Dossiers internes, fichiers cachés et fichiers de développement : interdits.
if (preg_match('~^/(app|migrations|data|tests|tools)(/|$)~', $path)
    || preg_match('~/\.~', $path)
    || preg_match('~\.(md|sh|lock|sqlite|log)$~', $path)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 — Accès interdit\n";
    return true;
}

if ($path === '/' || $path === '') {
    $path = '/index.php';
}

if ($path === '/index.php' || $path === '/install.php') {
    chdir($root);
    $_SERVER['SCRIPT_NAME'] = $path;
    $_SERVER['SCRIPT_FILENAME'] = $root . $path;
    require $root . $path;
    return true;
}

$file = realpath($root . $path);
if ($file !== false && str_starts_with($file, $root . DIRECTORY_SEPARATOR) && is_file($file) && !str_ends_with($file, '.php')) {
    return false; // fichier statique servi par PHP
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "404 — Introuvable\n";
return true;
