<?php
/**
 * Connexion et déconnexion.
 */
declare(strict_types=1);

namespace Cantine\Controllers;

defined('CANTINE') || exit;

use Cantine\App;
use Cantine\Support\Flash;
use Cantine\Support\Logger;
use Cantine\Support\LoginThrottle;
use Cantine\Support\Request;
use Cantine\Support\Response;
use Cantine\Support\View;

final class AuthController extends Controller
{
    public function loginForm(): Response
    {
        if ($this->auth->user() !== null) {
            return $this->redirect('accueil');
        }
        return Response::html(View::page('auth/login', ['username' => ''], 'layout/bare'));
    }

    public function login(): Response
    {
        $username = mb_substr(Request::post('username'), 0, 100);
        $password = mb_substr(Request::postRaw('password'), 0, 200);
        $remember = Request::postBool('remember');
        $ip = App::clientIp();
        $throttle = new LoginThrottle($this->db);

        $wait = $throttle->secondsToWait($username, $ip);
        if ($wait > 0) {
            $error = t('auth.too_many', ['minutes' => (string) max(1, (int) ceil($wait / 60))]);
            return Response::html(View::page('auth/login', ['username' => $username, 'error' => $error], 'layout/bare'), 429);
        }

        $user = $this->auth->attempt($username, $password);
        if ($user === null) {
            $throttle->recordFailure($username, $ip);
            Logger::info('Échec de connexion', ['username' => mb_substr($username, 0, 30), 'ip' => $ip]);
            // Même message que le compte existe ou non.
            return Response::html(View::page('auth/login', ['username' => $username, 'error' => t('auth.invalid')], 'layout/bare'), 401);
        }

        $throttle->clear($username);
        $this->auth->login($user, $remember);
        Flash::success(t('auth.welcome', ['name' => $user['display_name']]));

        if ((int) $user['must_change_password'] === 1) {
            return $this->redirect('mon-compte');
        }
        $after = $_SESSION['_after_login'] ?? '';
        unset($_SESSION['_after_login']);
        if (is_string($after) && str_starts_with($after, App::baseUrl() . 'index.php')) {
            return Response::redirect($after);
        }
        return $this->redirect('accueil');
    }

    public function logout(): Response
    {
        $this->auth->logout();
        $this->auth->startSession();
        Flash::success(t('auth.logged_out'));
        return $this->redirect('connexion');
    }
}
