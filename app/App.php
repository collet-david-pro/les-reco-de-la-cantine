<?php
/**
 * Déroulement d'une requête : configuration, base, session, maintenance, droits, contrôleur, réponse.
 * Donne aussi accès aux objets partagés (App::db(), App::auth()...).
 */
declare(strict_types=1);

namespace Cantine;

defined('CANTINE') || exit;

use Cantine\Repositories\MediaRepository;
use Cantine\Support\Auth;
use Cantine\Support\Csrf;
use Cantine\Support\Db;
use Cantine\Support\Flash;
use Cantine\Support\HttpException;
use Cantine\Support\Logger;
use Cantine\Support\Migrator;
use Cantine\Support\Request;
use Cantine\Support\Response;
use Cantine\Support\Settings;
use Cantine\Support\Text;
use Cantine\Support\View;

final class App
{
    private static array $config = [];
    private static ?Db $db = null;
    private static ?Settings $settings = null;
    private static ?Auth $auth = null;
    private static ?string $baseUrl = null;

    /** Routes accessibles pendant la maintenance ou une mise à jour de la base. */
    private const ALWAYS_OPEN = ['connexion', 'deconnexion', 'manifest', 'a-propos'];

    public static function run(): void
    {
        try {
            $response = self::handle();
        } catch (HttpException $e) {
            $response = self::errorResponse($e->status, $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            $message = !empty(self::$config['debug'])
                ? get_class($e) . ' : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')'
                : t('error.generic');
            $response = self::errorResponse(500, $message);
        }
        self::sendSecurityHeaders();
        $response->send();
    }

    private static function handle(): Response
    {
        $configFile = CANTINE_DATA . '/config.php';
        if (!is_file($configFile)) {
            // Pas encore installé : direction l'installeur.
            return Response::redirect(self::baseUrl() . 'install.php');
        }
        self::$config = require $configFile;
        Text::$timezone = (string) (self::$config['timezone'] ?? 'Europe/Paris');

        try {
            self::$db = Db::connect(self::$config['db'] ?? []);
        } catch (\PDOException $e) {
            Logger::error('Connexion à la base impossible : ' . $e->getMessage());
            return self::errorResponse(503, t('error.database'));
        }
        self::$settings = new Settings(self::$db);
        self::$auth = new Auth(
            self::$db,
            self::cookiePath(),
            self::isHttps(),
            (int) (self::$config['session_idle'] ?? 7200)
        );

        $route = Request::query('route');
        $method = Request::isPost() ? 'POST' : 'GET';

        if ($route === 'manifest') {
            return (new Controllers\PageController())->manifest();
        }

        self::$auth->startSession();
        $user = self::$auth->user();
        $isAdmin = self::$auth->isAdmin();

        if ($route === '') {
            return Response::redirect(url($user ? 'accueil' : 'connexion'));
        }

        $routes = require CANTINE_APP . '/routes.php';
        $definition = $routes[$method . ' ' . $route] ?? null;
        if ($definition === null) {
            throw HttpException::notFound();
        }
        [$controllerClass, $action, $access] = $definition;

        // Base à mettre à jour (nouveau code téléversé) : les membres voient la maintenance, l'admin le bouton.
        $migrator = new Migrator(self::$db);
        $migrationNeeded = $migrator->currentVersion() < Migrator::latestVersion();
        $open = in_array($route, self::ALWAYS_OPEN, true);
        if ($migrationNeeded && !$open) {
            if (!$isAdmin) {
                return self::maintenanceResponse();
            }
            if (!in_array($route, ['admin/migrations', 'admin/sauvegarde', 'admin/sauvegarde/exporter'], true)) {
                return Response::redirect(url('admin/migrations'));
            }
        }

        // Mode maintenance : seul l'admin passe.
        if (!$migrationNeeded && self::$settings->bool('maintenance') && !$isAdmin && !$open) {
            return self::maintenanceResponse();
        }

        // Contrôle d'accès.
        if ($access !== 'public' && $user === null) {
            if (Request::wantsJson()) {
                return Response::json(['ok' => false, 'error' => t('auth.session_expired')], 401);
            }
            if ($method === 'GET') {
                $_SESSION['_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
            }
            Flash::info(t('auth.please_login'));
            return Response::redirect(url('connexion'));
        }
        if ($access === 'admin' && !$isAdmin) {
            throw HttpException::forbidden();
        }
        if ($access === 'member' && $user !== null && (int) $user['must_change_password'] === 1) {
            Flash::info(t('account.must_change'));
            return Response::redirect(url('mon-compte'));
        }

        // Protection CSRF de toutes les requêtes POST.
        if ($method === 'POST') {
            if (empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
                // PHP vide $_POST quand post_max_size est dépassé.
                Flash::error(t('error.post_too_large', ['max' => ini_get('post_max_size')]));
                return Response::redirect(url($user ? 'accueil' : 'connexion'));
            }
            if (!Csrf::check(Csrf::fromRequest())) {
                if (Request::wantsJson()) {
                    return Response::json(['ok' => false, 'error' => t('error.csrf')], 419);
                }
                Flash::error(t('error.csrf'));
                return Response::redirect(url($user ? 'accueil' : 'connexion'));
            }
        }

        self::shareViewData($route, $migrationNeeded);

        $controller = new $controllerClass();
        return $controller->$action();
    }

    /** Variables disponibles dans tous les gabarits. */
    private static function shareViewData(string $route, bool $migrationNeeded = false): void
    {
        $user = self::$auth?->user();
        $newCount = 0;
        if ($user !== null && self::$db !== null && !$migrationNeeded) {
            $newCount = (new MediaRepository(self::$db))->countNew((int) $user['id'], $user['previous_seen_at']);
        }
        View::$shared = [
            'currentUser' => $user,
            'isAdmin' => $user !== null && $user['role'] === 'admin',
            'siteName' => self::siteName(),
            'currentRoute' => $route,
            'newCount' => $newCount,
            'maintenanceOn' => self::$settings !== null && self::$settings->bool('maintenance'),
            'demoLoaded' => self::$settings !== null && self::$settings->bool('demo_loaded'),
            // Nouvelle version (admins seulement) : lecture des réglages, aucun appel réseau ici.
            'updateAvailable' => $user !== null && $user['role'] === 'admin' && self::$settings !== null && self::updatesEnabled()
                ? (new Services\UpdateChecker(self::$settings, new Services\HttpClient()))->available()
                : null,
        ];
    }

    private static function maintenanceResponse(): Response
    {
        self::shareViewData('maintenance');
        $response = Response::html(View::page('pages/maintenance', [], 'layout/bare'), 503);
        $response->headers['Retry-After'] = '600';
        return $response;
    }

    private static function errorResponse(int $status, string $message): Response
    {
        if (Request::wantsJson()) {
            return Response::json(['ok' => false, 'error' => $message], $status);
        }
        try {
            if (empty(View::$shared)) {
                self::shareViewData('erreur');
            }
            $layout = self::$auth?->user() ? 'layout/main' : 'layout/bare';
            return Response::html(View::page('pages/error', ['status' => $status, 'message' => $message], $layout), $status);
        } catch (\Throwable $e) {
            Logger::error('Erreur pendant l\'affichage de la page d\'erreur : ' . $e->getMessage());
            return Response::html('<!doctype html><meta charset="utf-8"><title>Erreur</title><p>' . e($message) . '</p>', $status);
        }
    }

    /** En-têtes de sécurité envoyés par PHP (valables aussi sous nginx, qui ignore .htaccess). */
    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        // img-src https: : les jaquettes viennent des CDN des APIs et des liens d'image saisis à la main.
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; script-src 'self'; "
            . "style-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; "
            . "manifest-src 'self'; worker-src 'self'");
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: DENY');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }

    // ---- Accès aux objets partagés ----

    public static function db(): Db
    {
        return self::$db ?? throw new \LogicException('Base non initialisée');
    }

    public static function settings(): Settings
    {
        return self::$settings ?? throw new \LogicException('Réglages non initialisés');
    }

    public static function auth(): Auth
    {
        return self::$auth ?? throw new \LogicException('Session non initialisée');
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        return self::$config[$key] ?? $default;
    }

    /** Utilisé par les tests et l'installeur. */
    public static function boot(array $config, Db $db, ?Auth $auth = null): void
    {
        self::$config = $config;
        self::$db = $db;
        self::$settings = new Settings($db);
        self::$auth = $auth;
    }

    /** Notification de nouvelle version : activée sauf 'check_updates' => false dans config.php. */
    public static function updatesEnabled(): bool
    {
        return (self::$config['check_updates'] ?? true) !== false;
    }

    public static function siteName(): string
    {
        $name = self::$settings?->get('site_name', '') ?? '';
        return $name !== '' ? $name : (string) (self::$config['site_name'] ?? 'Les reco de la cantine');
    }

    // ---- URL de base et HTTPS ----

    /** URL de base du site, avec "/" final (gère l'installation dans un sous-dossier). */
    public static function baseUrl(): string
    {
        if (self::$baseUrl !== null) {
            return self::$baseUrl;
        }
        $configured = (string) (self::$config['base_url'] ?? '');
        if ($configured !== '') {
            return self::$baseUrl = rtrim($configured, '/') . '/';
        }
        $dir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
        return self::$baseUrl = rtrim($dir, '/') . '/';
    }

    /** Chemin des cookies = chemin de base du site (sans le domaine). */
    public static function cookiePath(): string
    {
        $path = (string) parse_url(self::baseUrl(), PHP_URL_PATH);
        return $path !== '' ? $path : '/';
    }

    public static function isHttps(): bool
    {
        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && strtolower((string) $https) !== 'off') {
            return true;
        }
        if (!empty(self::$config['trust_proxy'])) {
            return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        }
        return false;
    }

    /** Adresse IP du visiteur (X-Forwarded-For seulement si trust_proxy est activé). */
    public static function clientIp(): string
    {
        if (!empty(self::$config['trust_proxy']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $first = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
