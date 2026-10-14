<?php
/**
 * Gestion des comptes par l'admin : création, désactivation, rôle, réinitialisation du mot de passe, suppression.
 * Garde-fous : impossible de supprimer, désactiver ou rétrograder le dernier admin, ni de se supprimer soi-même.
 */
declare(strict_types=1);

namespace Cantine\Controllers\Admin;

defined('CANTINE') || exit;

use Cantine\Controllers\Controller;
use Cantine\Repositories\UserRepository;
use Cantine\Support\Flash;
use Cantine\Support\HttpException;
use Cantine\Support\Request;
use Cantine\Support\Response;
use Cantine\Support\Text;

final class UsersController extends Controller
{
    private function users(): UserRepository
    {
        return new UserRepository($this->db);
    }

    private function load(int $id): array
    {
        return $this->users()->find($id) ?? throw HttpException::notFound();
    }

    public function index(): Response
    {
        $created = $_SESSION['_created_password'] ?? null;
        unset($_SESSION['_created_password']);
        return $this->view('admin/users', [
            'title' => t('users.title'),
            'users' => $this->users()->allWithCounts(),
            'created' => is_array($created) ? $created : null,
        ]);
    }

    public function createForm(): Response
    {
        return $this->view('admin/user_create', ['title' => t('users.create_title'), 'values' => [], 'errors' => []]);
    }

    public function create(): Response
    {
        $username = mb_strtolower(Request::post('username'));
        $displayName = Request::post('display_name');
        $generate = Request::post('password_mode', 'generate') === 'generate';
        $password = $generate ? Text::readablePassword() : Request::postRaw('password');
        $role = Request::post('role') === 'admin' ? 'admin' : 'member';

        $errors = array_filter([
            'username' => UserRepository::validateUsername($username),
            'display_name' => UserRepository::validateDisplayName($displayName),
            'password' => $generate ? null : UserRepository::validatePassword($password),
        ]);
        if (!isset($errors['username']) && $this->users()->findByUsername($username)) {
            $errors['username'] = t('users.username_taken');
        }
        if ($errors) {
            return $this->view('admin/user_create', ['title' => t('users.create_title'), 'values' => $_POST, 'errors' => $errors], 422);
        }

        $this->users()->create($username, $displayName, $password, $role, Request::postBool('must_change'));
        // Le mot de passe est affiché UNE seule fois.
        $_SESSION['_created_password'] = ['username' => $username, 'password' => $password, 'reset' => false];
        Flash::success(t('users.created', ['name' => $displayName]));
        return $this->redirect('admin/membres');
    }

    public function editForm(): Response
    {
        $user = $this->load(Request::queryInt('id'));
        return $this->view('admin/user_edit', [
            'title' => t('users.edit_title'),
            'user' => $user,
            'isLastAdmin' => $this->users()->isLastAdmin((int) $user['id']),
            'isSelf' => (int) $user['id'] === $this->userId(),
        ]);
    }

    public function update(): Response
    {
        $user = $this->load(Request::postInt('id'));
        $displayName = Request::post('display_name');
        $role = Request::post('role') === 'admin' ? 'admin' : 'member';
        $error = UserRepository::validateDisplayName($displayName);
        if ($error !== null) {
            Flash::error($error);
            return $this->redirect('admin/membres/modifier', ['id' => $user['id']]);
        }
        if ($role !== 'admin' && $this->users()->isLastAdmin((int) $user['id'])) {
            Flash::error(t('users.last_admin'));
            return $this->redirect('admin/membres/modifier', ['id' => $user['id']]);
        }
        $this->users()->update((int) $user['id'], ['display_name' => $displayName, 'role' => $role]);
        if ($role !== $user['role']) {
            // Changement de rôle : les sessions de ce compte sont renouvelées.
            $this->auth->invalidateSessions((int) $user['id']);
            if ((int) $user['id'] === $this->userId()) {
                $this->auth->refreshCurrentSession();
            }
        }
        Flash::success(t('users.updated'));
        return $this->redirect('admin/membres');
    }

    public function toggleActive(): Response
    {
        $user = $this->load(Request::postInt('id'));
        $activate = (int) $user['is_active'] === 0;
        if (!$activate && ((int) $user['id'] === $this->userId() || $this->users()->isLastAdmin((int) $user['id']))) {
            Flash::error(t('users.cannot_deactivate'));
            return $this->redirect('admin/membres');
        }
        $this->users()->update((int) $user['id'], ['is_active' => $activate ? 1 : 0]);
        if (!$activate) {
            $this->auth->invalidateSessions((int) $user['id']);
        }
        Flash::success($activate ? t('users.activated', ['name' => $user['display_name']]) : t('users.deactivated', ['name' => $user['display_name']]));
        return $this->redirect('admin/membres');
    }

    public function resetPassword(): Response
    {
        $user = $this->load(Request::postInt('id'));
        $password = Text::readablePassword();
        $this->users()->setPassword((int) $user['id'], $password, true);
        $this->auth->invalidateSessions((int) $user['id']);
        if ((int) $user['id'] === $this->userId()) {
            $this->auth->refreshCurrentSession();
        }
        $_SESSION['_created_password'] = ['username' => $user['username'], 'password' => $password, 'reset' => true];
        Flash::success(t('users.password_reset', ['name' => $user['display_name']]));
        return $this->redirect('admin/membres');
    }

    public function deleteConfirm(): Response
    {
        $user = $this->load(Request::queryInt('id'));
        $counts = [
            'media' => (int) $this->db->value('SELECT COUNT(*) FROM {media} WHERE added_by = ?', [$user['id']]),
            'statuses' => (int) $this->db->value('SELECT COUNT(*) FROM {user_media} WHERE user_id = ?', [$user['id']]),
            'comments' => (int) $this->db->value('SELECT COUNT(*) FROM {comments} WHERE user_id = ?', [$user['id']]),
        ];
        return $this->view('admin/user_delete', [
            'title' => t('users.delete_title'),
            'user' => $user,
            'counts' => $counts,
            'blocked' => (int) $user['id'] === $this->userId() || $this->users()->isLastAdmin((int) $user['id']),
        ]);
    }

    public function delete(): Response
    {
        $user = $this->load(Request::postInt('id'));
        if ((int) $user['id'] === $this->userId() || $this->users()->isLastAdmin((int) $user['id'])) {
            Flash::error(t('users.cannot_delete'));
            return $this->redirect('admin/membres');
        }
        if (mb_strtolower(Request::post('confirm_username')) !== $user['username']) {
            Flash::error(t('users.delete_confirm_mismatch'));
            return $this->redirect('admin/membres/supprimer', ['id' => $user['id']]);
        }
        $this->users()->delete((int) $user['id']);
        Flash::success(t('users.deleted', ['name' => $user['display_name']]));
        return $this->redirect('admin/membres');
    }
}
