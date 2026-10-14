<?php
/**
 * Classe de base des contrôleurs : raccourcis pour afficher une page, rediriger, vérifier les droits.
 */
declare(strict_types=1);

namespace Cantine\Controllers;

defined('CANTINE') || exit;

use Cantine\App;
use Cantine\Support\Auth;
use Cantine\Support\Db;
use Cantine\Support\HttpException;
use Cantine\Support\Response;
use Cantine\Support\Settings;
use Cantine\Support\View;

abstract class Controller
{
    protected Db $db;
    protected Settings $settings;
    protected Auth $auth;

    public function __construct()
    {
        $this->db = App::db();
        $this->settings = App::settings();
        $this->auth = App::auth();
    }

    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html(View::page($template, $data), $status);
    }

    protected function redirect(string $route = '', array $params = []): Response
    {
        return Response::redirect(url($route, $params));
    }

    /** Redirige vers la page précédente si elle appartient au site, sinon vers $fallback. */
    protected function back(string $fallback = '', array $params = []): Response
    {
        $target = $_POST['_back'] ?? '';
        if (is_string($target) && $target !== '' && str_starts_with($target, App::baseUrl() . 'index.php')) {
            return Response::redirect($target);
        }
        return $this->redirect($fallback, $params);
    }

    protected function userId(): int
    {
        return (int) $this->auth->id();
    }

    protected function isAdmin(): bool
    {
        return $this->auth->isAdmin();
    }

    /** Autorise uniquement l'auteur de la ressource ou un admin. */
    protected function requireOwnerOrAdmin(?int $ownerId): void
    {
        if (!$this->isAdmin() && ($ownerId === null || $ownerId !== $this->userId())) {
            throw HttpException::forbidden();
        }
    }
}
