<?php
/**
 * Tableau de bord de l'administration : maintenance, diagnostic, migrations, données de démonstration.
 */
declare(strict_types=1);

namespace Cantine\Controllers\Admin;

defined('CANTINE') || exit;

use Cantine\App;
use Cantine\Controllers\Controller;
use Cantine\Services\Backup;
use Cantine\Services\Demo;
use Cantine\Services\HttpClient;
use Cantine\Services\UpdateChecker;
use Cantine\Support\Diagnostics;
use Cantine\Support\Flash;
use Cantine\Support\Logger;
use Cantine\Support\Migrator;
use Cantine\Support\Response;

final class AdminController extends Controller
{
    private function updateChecker(): UpdateChecker
    {
        return new UpdateChecker($this->settings, new HttpClient($this->db));
    }

    public function index(): Response
    {
        $demo = new Demo($this->db, $this->settings);
        $updates = null;
        if (App::updatesEnabled()) {
            $checker = $this->updateChecker();
            $checker->checkIfDue(); // au plus une fois par 24 h
            $updates = $checker->status();
        }
        return $this->view('admin/index', [
            'title' => t('admin.title'),
            'counts' => [
                'users' => (int) $this->db->value('SELECT COUNT(*) FROM {users} WHERE is_active = 1'),
                'media' => (int) $this->db->value('SELECT COUNT(*) FROM {media}'),
                'comments' => (int) $this->db->value('SELECT COUNT(*) FROM {comments}'),
            ],
            'maintenance' => $this->settings->bool('maintenance'),
            'demoLoaded' => $demo->isLoaded(),
            'demoActivity' => $demo->isLoaded() ? $demo->realActivityOnDemo() : ['statuses' => 0, 'comments' => 0],
            'demoCredentials' => $_SESSION['_demo_credentials'] ?? null,
            'missingKeys' => $this->settings->get('tmdb_key') === '', // les jeux fonctionnent sans clé (Steam)
            'updates' => $updates,
        ]);
    }

    public function toggleMaintenance(): Response
    {
        $on = !$this->settings->bool('maintenance');
        $this->settings->set('maintenance', $on ? '1' : '0');
        Flash::success($on ? t('admin.maintenance_on') : t('admin.maintenance_off'));
        return $this->redirect('admin');
    }

    /** Bouton "Vérifier maintenant". */
    public function checkUpdates(): Response
    {
        if (!App::updatesEnabled()) {
            return $this->redirect('admin');
        }
        $checker = $this->updateChecker();
        if (!$checker->check()) {
            Flash::error(t('updates.check_failed'));
        } elseif ($checker->available() !== null) {
            Flash::info(t('updates.found', ['version' => $checker->available()['version']]));
        } else {
            Flash::success(t('updates.up_to_date', ['version' => CANTINE_VERSION]));
        }
        return Response::redirect(url('admin') . '#mises-a-jour');
    }

    public function diagnostics(): Response
    {
        $checks = Diagnostics::environment(true);
        $checks[] = Diagnostics::dataProtectionCheck(App::baseUrl());
        return $this->view('admin/diagnostics', [
            'title' => t('admin.diagnostics'),
            'checks' => $checks,
            'info' => Diagnostics::info($this->db),
        ]);
    }

    public function migrationsForm(): Response
    {
        $migrator = new Migrator($this->db);
        return $this->view('admin/migrations', [
            'title' => t('migrations.title'),
            'current' => $migrator->currentVersion(),
            'latest' => Migrator::latestVersion(),
            'pending' => array_map('basename', $migrator->pending()),
        ]);
    }

    public function migrate(): Response
    {
        try {
            $applied = (new Migrator($this->db))->migrate();
        } catch (\Throwable $e) {
            Logger::error('Migration échouée : ' . $e->getMessage());
            Flash::error(t('migrations.failed', ['error' => $e->getMessage()]));
            return $this->redirect('admin/migrations');
        }
        $this->settings->reset();
        Flash::success(t_plural('migrations.done', count($applied)));
        return $this->redirect('admin');
    }

    public function loadDemo(): Response
    {
        try {
            $result = (new Demo($this->db, $this->settings))->load();
            $_SESSION['_demo_credentials'] = $result;
            Flash::success(t('demo.loaded'));
        } catch (\Throwable $e) {
            Flash::error($e->getMessage());
        }
        return $this->redirect('admin');
    }

    public function removeDemo(): Response
    {
        (new Demo($this->db, $this->settings))->remove();
        unset($_SESSION['_demo_credentials']);
        Flash::success(t('demo.removed'));
        return $this->redirect('admin');
    }
}
