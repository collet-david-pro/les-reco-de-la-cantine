<?php
/**
 * "Ma liste" : contenus du groupe avec mon statut, filtres, tris, recherche et pagination.
 * Les filtres sont des paramètres GET (URL partageable, bouton retour fonctionnel).
 */
declare(strict_types=1);

namespace Cantine\Controllers;

defined('CANTINE') || exit;

use Cantine\Repositories\MediaRepository;
use Cantine\Support\Request;
use Cantine\Support\Response;
use Cantine\Types;

final class ListController extends Controller
{
    public function index(): Response
    {
        $repo = new MediaRepository($this->db);
        $filters = self::filtersFromQuery();
        $sort = in_array(Request::query('tri'), MediaRepository::SORTS, true) ? Request::query('tri') : 'recent';
        $page = max(1, Request::queryInt('page', 1));

        $result = $repo->listForUser($this->userId(), $filters, $sort, $page);
        $user = $this->auth->user();

        return $this->view('pages/list', [
            'title' => t('nav.list'),
            'filters' => $filters,
            'sort' => $sort,
            'page' => $page,
            'items' => $result['items'],
            'total' => $result['total'],
            'pages' => max(1, (int) ceil($result['total'] / MediaRepository::PER_PAGE)),
            'tabCounts' => $repo->tabCounts($this->userId()),
            'genres' => $repo->usedGenres($filters['type'] ?: null),
            'adders' => $repo->adders(),
            'previousSeenAt' => $user['previous_seen_at'] ?? null,
        ]);
    }

    /** Lit et valide les filtres de l'URL. */
    public static function filtersFromQuery(): array
    {
        $tab = Request::query('vue', 'a_decouvrir');
        $type = Request::query('type');
        return [
            'tab' => in_array($tab, MediaRepository::TABS, true) ? $tab : 'a_decouvrir',
            'type' => Types::exists($type) ? $type : '',
            'added_by' => Request::queryInt('par') ?: '',
            'genre' => Request::queryInt('genre') ?: '',
            'q' => mb_substr(Request::query('q'), 0, 100),
        ];
    }
}
