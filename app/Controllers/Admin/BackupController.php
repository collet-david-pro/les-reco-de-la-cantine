<?php
/**
 * Sauvegarde (export JSON) et restauration (admin).
 * Restauration : envoi -> validation complète -> résumé -> confirmation + mot de passe admin -> exécution.
 * Le fichier envoyé est conservé temporairement dans data/sauvegardes/ (jamais dans un dossier public).
 */
declare(strict_types=1);

namespace Cantine\Controllers\Admin;

defined('CANTINE') || exit;

use Cantine\Controllers\Controller;
use Cantine\Services\Backup;
use Cantine\Support\Flash;
use Cantine\Support\HttpException;
use Cantine\Support\Logger;
use Cantine\Support\Request;
use Cantine\Support\Response;

final class BackupController extends Controller
{
    public function index(): Response
    {
        return $this->view('admin/backup', [
            'title' => t('backup.title'),
            'safetyFiles' => Backup::safetyFiles(),
            'uploadMax' => ini_get('upload_max_filesize'),
            'postMax' => ini_get('post_max_size'),
        ]);
    }

    public function export(): Response
    {
        $backup = new Backup($this->db);
        $secrets = Request::postBool('include_keys');
        $demo = Request::postBool('include_demo');
        return Response::download(Backup::filename(), 'application/json; charset=utf-8', static function () use ($backup, $secrets, $demo): void {
            $out = fopen('php://output', 'wb');
            $backup->export($out, $secrets, $demo);
            fclose($out);
        });
    }

    /** Téléchargement d'une sauvegarde de sécurité (data/sauvegardes/). */
    public function downloadSafety(): Response
    {
        $name = Request::query('fichier');
        if (!preg_match('/^securite-[0-9-]+\.json$/', $name) || !is_file(Backup::safetyDir() . '/' . $name)) {
            throw HttpException::notFound();
        }
        $path = Backup::safetyDir() . '/' . $name;
        return Response::download($name, 'application/json; charset=utf-8', static function () use ($path): void {
            readfile($path);
        });
    }

    public function upload(): Response
    {
        $file = $_FILES['backup'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $code = is_array($file) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
            Flash::error(match ($code) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => t('backup.too_large', ['max' => (string) ini_get('upload_max_filesize')]),
                UPLOAD_ERR_NO_FILE => t('backup.no_file'),
                default => t('backup.upload_failed'),
            });
            return $this->redirect('admin/sauvegarde');
        }
        // Lecture du contenu seulement : le fichier envoyé n'est jamais inclus ni exécuté.
        $json = (string) file_get_contents($file['tmp_name']);
        $result = (new Backup($this->db))->validate($json);
        if (!$result['ok']) {
            return $this->view('admin/restore_confirm', ['title' => t('backup.restore_title'), 'result' => $result, 'token' => null]);
        }

        $dir = Backup::safetyDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        // Un seul envoi en attente à la fois.
        foreach (glob($dir . '/envoi-*.json') ?: [] as $old) {
            @unlink($old);
        }
        $token = bin2hex(random_bytes(16));
        file_put_contents($dir . '/envoi-' . $token . '.json', $json);
        $_SESSION['_restore_token'] = $token;
        return $this->redirect('admin/restauration/confirmer');
    }

    private function pendingFile(): ?string
    {
        $token = $_SESSION['_restore_token'] ?? '';
        if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $path = Backup::safetyDir() . '/envoi-' . $token . '.json';
        return is_file($path) ? $path : null;
    }

    public function confirmForm(): Response
    {
        $path = $this->pendingFile();
        if ($path === null) {
            Flash::error(t('backup.no_pending'));
            return $this->redirect('admin/sauvegarde');
        }
        $result = (new Backup($this->db))->validate((string) file_get_contents($path));
        return $this->view('admin/restore_confirm', ['title' => t('backup.restore_title'), 'result' => $result, 'token' => $_SESSION['_restore_token']]);
    }

    public function restore(): Response
    {
        $path = $this->pendingFile();
        if ($path === null) {
            Flash::error(t('backup.no_pending'));
            return $this->redirect('admin/sauvegarde');
        }
        if (!Request::postBool('understand')) {
            Flash::error(t('backup.must_confirm'));
            return $this->redirect('admin/restauration/confirmer');
        }
        $admin = $this->auth->user();
        if (!password_verify(Request::postRaw('password'), $admin['password_hash'])) {
            Flash::error(t('account.wrong_password'));
            return $this->redirect('admin/restauration/confirmer');
        }

        $backup = new Backup($this->db);
        $result = $backup->validate((string) file_get_contents($path));
        if (!$result['ok']) {
            return $this->view('admin/restore_confirm', ['title' => t('backup.restore_title'), 'result' => $result, 'token' => null]);
        }

        try {
            $safety = $backup->writeSafetyCopy();
            $backup->restore($result['data']);
        } catch (\Throwable $e) {
            Logger::error('Restauration échouée : ' . $e->getMessage());
            Flash::error(t('backup.restore_failed', ['error' => $e->getMessage()]));
            return $this->redirect('admin/sauvegarde');
        }
        @unlink($path);
        unset($_SESSION['_restore_token']);
        Logger::info('Restauration effectuée', ['safety_copy' => $safety]);

        // Les comptes ont pu changer : on déconnecte l'admin proprement.
        $this->auth->logout();
        $this->auth->startSession();
        Flash::success(t('backup.restored', ['file' => $safety]));
        return $this->redirect('connexion');
    }
}
