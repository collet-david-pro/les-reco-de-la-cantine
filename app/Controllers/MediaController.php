<?php
/**
 * Fiche d'un contenu : affichage, modification, re-synchronisation, suppression, fusion (admin).
 */
declare(strict_types=1);

namespace Cantine\Controllers;

defined('CANTINE') || exit;

use Cantine\Repositories\CommentRepository;
use Cantine\Repositories\MediaRepository;
use Cantine\Repositories\UserMediaRepository;
use Cantine\Services\Api\Sources;
use Cantine\Services\Api\Tmdb;
use Cantine\Services\ApiException;
use Cantine\Services\HttpClient;
use Cantine\Services\MediaService;
use Cantine\Services\ShopLinks;
use Cantine\Support\Flash;
use Cantine\Support\HttpException;
use Cantine\Support\Logger;
use Cantine\Support\Request;
use Cantine\Support\Response;
use Cantine\Types;

final class MediaController extends Controller
{
    /** Les disponibilités "où regarder" sont rafraîchies si elles ont plus de 3 jours. */
    private const PROVIDERS_MAX_AGE = 3 * 86400;

    private function load(int $id): array
    {
        return (new MediaRepository($this->db))->find($id) ?? throw HttpException::notFound();
    }

    public function show(): Response
    {
        $media = $this->load(Request::queryInt('id'));
        $repo = new MediaRepository($this->db);
        $id = (int) $media['id'];

        $user = $this->auth->user();
        $isNew = $user['previous_seen_at'] !== null && $media['created_at'] > $user['previous_seen_at']
            && (int) $media['added_by'] !== $this->userId();

        return $this->view('pages/media', [
            'title' => $media['title'],
            'media' => $media,
            'genres' => $repo->genres($id),
            'stats' => $repo->stats($id),
            'opinions' => $repo->memberOpinions($id),
            'mine' => (new UserMediaRepository($this->db))->get($this->userId(), $id),
            'comments' => (new CommentRepository($this->db))->forMedia($id),
            'providers' => in_array($media['type'], ['film', 'serie'], true) ? $this->providers($media) : null,
            'shopLinks' => $media['type'] === 'livre' ? $this->shopLinks($media) : [],
            'canEdit' => $this->canEdit($media),
            'isNew' => $isNew,
        ]);
    }

    /** "Où regarder" : données en cache, rafraîchies à la consultation si trop anciennes. */
    private function providers(array $media): ?array
    {
        $service = new MediaService($this->db);
        $cached = $service->watchProviders((int) $media['id']);
        if ($media['source'] !== 'tmdb' || !ctype_digit((string) $media['external_id'])) {
            return $cached;
        }
        $sources = new Sources($this->settings, new HttpClient($this->db));
        $region = $sources->region();
        $stale = $cached === null || $cached['region'] !== $region
            || strtotime($cached['updated_at'] . ' UTC') < time() - self::PROVIDERS_MAX_AGE;
        if (!$stale) {
            return $cached;
        }
        $tmdb = $sources->get('tmdb', $media['type']);
        if (!$tmdb instanceof Tmdb || !$tmdb->isAvailable()) {
            return $cached;
        }
        try {
            $data = $tmdb->watchProviders((string) $media['external_id']);
            if ($data !== null) {
                $service->saveWatchProviders((int) $media['id'], $data, $region);
                return $service->watchProviders((int) $media['id']);
            }
        } catch (ApiException $e) {
            Logger::warning('Rafraîchissement "où regarder" impossible : ' . $e->getMessage());
        }
        return $cached; // dernières données connues, affichées avec leur date
    }

    private function shopLinks(array $media): array
    {
        $templates = $this->settings->json('shop_links', ShopLinks::DEFAULTS);
        $author = $media['extra']['author'] ?? null;
        if (is_array($author)) {
            $author = $author[0] ?? null;
        }
        return ShopLinks::build($templates, $media['isbn'] ?: null, $media['title'], is_string($author) ? $author : null);
    }

    private function canEdit(array $media): bool
    {
        return $this->isAdmin() || ($media['added_by'] !== null && (int) $media['added_by'] === $this->userId());
    }

    // ---- Modification ----

    public function editForm(): Response
    {
        $media = $this->load(Request::queryInt('id'));
        $this->requireOwnerOrAdmin($media['added_by'] !== null ? (int) $media['added_by'] : null);
        $genres = array_column((new MediaRepository($this->db))->genres((int) $media['id']), 'name');
        return $this->view('pages/media_edit', [
            'title' => t('media.edit_title'),
            'media' => $media,
            'values' => $media + ['genres_text' => implode(', ', $genres)],
            'errors' => [],
        ]);
    }

    public function update(): Response
    {
        $media = $this->load(Request::postInt('id'));
        $this->requireOwnerOrAdmin($media['added_by'] !== null ? (int) $media['added_by'] : null);
        $isManual = $media['source'] === 'manuel';

        [$data, $errors] = AddController::validateManualFields($media['type'], $isManual);
        if ($errors) {
            return $this->view('pages/media_edit', [
                'title' => t('media.edit_title'),
                'media' => $media,
                'values' => array_merge($media, $_POST, ['extra' => $data['extra'] ?? $media['extra']]),
                'errors' => $errors,
            ], 422);
        }

        $repo = new MediaRepository($this->db);
        $update = [
            'title' => $data['title'],
            'year' => $data['year'],
            'description' => $data['description'],
        ];
        if ($isManual) {
            $update['external_url'] = $data['external_url'];
            $update['image_url'] = $data['image_url'];
            $update['isbn'] = $data['isbn'];
            $update['extra'] = $data['extra'];
        }
        $this->db->transaction(function () use ($repo, $media, $update, $data, $isManual): void {
            $repo->update((int) $media['id'], $update);
            if ($isManual) {
                $repo->setGenres((int) $media['id'], $data['genres']);
            }
        });
        Flash::success(t('media.updated'));
        return $this->redirect('media', ['id' => $media['id']]);
    }

    /** Recharge la fiche depuis l'API (titre, jaquette, infos pratiques...). */
    public function resync(): Response
    {
        $media = $this->load(Request::postInt('id'));
        $this->requireOwnerOrAdmin($media['added_by'] !== null ? (int) $media['added_by'] : null);
        $http = new HttpClient($this->db);
        $http->refresh = true;
        $source = (new Sources($this->settings, $http))->get($media['source'], $media['type']);
        if ($source === null || !$source->isAvailable()) {
            Flash::error(t('media.resync_unavailable'));
            return $this->redirect('media', ['id' => $media['id']]);
        }
        try {
            $details = $source->fetch((string) $media['external_id']);
            $details['region'] = (new Sources($this->settings, $http))->region();
            (new MediaService($this->db))->applyApiDetails((int) $media['id'], $details);
            Flash::success(t('media.resynced'));
        } catch (ApiException $e) {
            Flash::error($e->getMessage());
        }
        return $this->redirect('media', ['id' => $media['id']]);
    }

    // ---- Suppression ----

    public function deleteConfirm(): Response
    {
        $media = $this->load(Request::queryInt('id'));
        $this->requireOwnerOrAdmin($media['added_by'] !== null ? (int) $media['added_by'] : null);
        $others = (new MediaRepository($this->db))->othersActivity((int) $media['id'], $this->userId());
        return $this->view('pages/media_delete', [
            'title' => t('media.delete_title'),
            'media' => $media,
            'others' => $others,
            'allowed' => $this->isAdmin() || ($others['statuses'] === 0 && $others['comments'] === 0),
        ]);
    }

    /**
     * L'ajouteur ne peut supprimer que si personne d'autre n'a de statut ni de commentaire dessus.
     * Sinon seul l'admin peut supprimer.
     */
    public function delete(): Response
    {
        $media = $this->load(Request::postInt('id'));
        $this->requireOwnerOrAdmin($media['added_by'] !== null ? (int) $media['added_by'] : null);
        $repo = new MediaRepository($this->db);
        $others = $repo->othersActivity((int) $media['id'], $this->userId());
        if (!$this->isAdmin() && ($others['statuses'] > 0 || $others['comments'] > 0)) {
            Flash::error(t('media.delete_blocked'));
            return $this->redirect('media', ['id' => $media['id']]);
        }
        $repo->delete((int) $media['id']);
        $repo->purgeUnusedGenres();
        Flash::success(t('media.deleted', ['title' => $media['title']]));
        return $this->redirect('liste');
    }

    // ---- Fusion de doublons (admin) ----

    public function mergeForm(): Response
    {
        $media = $this->load(Request::queryInt('id'));
        $query = Request::query('q', $media['title']);
        $candidates = array_values(array_filter(
            (new MediaRepository($this->db))->searchLocal($query, $media['type'], 20),
            static fn ($m) => (int) $m['id'] !== (int) $media['id']
        ));
        return $this->view('pages/media_merge', [
            'title' => t('merge.title'),
            'media' => $media,
            'query' => $query,
            'candidates' => $candidates,
        ]);
    }

    public function merge(): Response
    {
        $source = $this->load(Request::postInt('id'));
        $target = $this->load(Request::postInt('target_id'));
        if ((int) $source['id'] === (int) $target['id'] || $source['type'] !== $target['type']) {
            Flash::error(t('merge.invalid'));
            return $this->redirect('media', ['id' => $source['id']]);
        }
        (new MediaRepository($this->db))->merge((int) $source['id'], (int) $target['id']);
        Flash::success(t('merge.done', ['source' => $source['title'], 'target' => $target['title']]));
        return $this->redirect('media', ['id' => $target['id']]);
    }

    /** Libellés des statuts d'un type, pour les boutons de la fiche. */
    public static function statusChoices(string $type): array
    {
        $choices = [];
        foreach (Types::STATUSES as $status) {
            $choices[$status] = Types::statusLabel($status, $type);
        }
        return $choices;
    }
}
