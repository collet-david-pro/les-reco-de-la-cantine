<?php
/**
 * Mon compte : nom affiché, couleur d'avatar, mot de passe, déconnexion de tous les appareils.
 */
declare(strict_types=1);

namespace Cantine\Controllers;

defined('CANTINE') || exit;

use Cantine\Repositories\UserRepository;
use Cantine\Support\Auth;
use Cantine\Support\Flash;
use Cantine\Support\Request;
use Cantine\Support\Response;

final class AccountController extends Controller
{
    public function index(): Response
    {
        return $this->view('pages/account', [
            'title' => t('account.title'),
            'colors' => UserRepository::AVATAR_COLORS,
            'errors' => [],
        ]);
    }

    public function updateProfile(): Response
    {
        $name = mb_substr(Request::post('display_name'), 0, 60);
        $color = Request::post('avatar_color');
        $error = UserRepository::validateDisplayName($name);
        if ($error !== null) {
            Flash::error($error);
            return $this->redirect('mon-compte');
        }
        $data = ['display_name' => $name];
        if (in_array($color, UserRepository::AVATAR_COLORS, true)) {
            $data['avatar_color'] = $color;
        }
        (new UserRepository($this->db))->update($this->userId(), $data);
        Flash::success(t('account.profile_saved'));
        return $this->redirect('mon-compte');
    }

    public function changePassword(): Response
    {
        $user = $this->auth->user();
        $current = Request::postRaw('current_password');
        $new = Request::postRaw('new_password');
        $confirm = Request::postRaw('confirm_password');

        $errors = [];
        if (!password_verify($current, $user['password_hash'])) {
            $errors['current_password'] = t('account.wrong_password');
        }
        $minLength = $user['role'] === 'admin' ? 12 : 8;
        $error = UserRepository::validatePassword($new, $minLength);
        if ($error !== null) {
            $errors['new_password'] = $error;
        } elseif ($new !== $confirm) {
            $errors['confirm_password'] = t('account.password_mismatch');
        } elseif ($new === $current) {
            $errors['new_password'] = t('account.password_same');
        }
        if ($errors) {
            return $this->view('pages/account', [
                'title' => t('account.title'),
                'colors' => UserRepository::AVATAR_COLORS,
                'errors' => $errors,
            ], 422);
        }

        (new UserRepository($this->db))->setPassword((int) $user['id'], $new, false);
        // Les autres appareils sont déconnectés ; la session courante reste ouverte.
        $this->auth->invalidateSessions((int) $user['id']);
        $this->auth->refreshCurrentSession();
        Flash::success(t('account.password_changed'));
        return $this->redirect('accueil');
    }

    public function logoutEverywhere(): Response
    {
        $this->auth->logout(true);
        $this->auth->startSession();
        Flash::success(t('account.logged_out_everywhere'));
        return $this->redirect('connexion');
    }
}
