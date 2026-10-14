<?php
/**
 * Ajout d'une recommandation.
 *
 * 1. choix du type ; 2. recherche (AJAX avec repli sans JavaScript) ;
 * 3. choix d'un résultat : le navigateur n'envoie que (type, source, id externe) ;
 * 4. aperçu : le serveur va chercher lui-même la fiche auprès de l'API ; 5. enregistrement.
 * "Je ne trouve pas" mène toujours à l'ajout manuel.
 */
declare(strict_types=1);

namespace Cantine\Controllers;

defined('CANTINE') || exit;

use Cantine\Repositories\MediaRepository;
use Cantine\Repositories\UserMediaRepository;
use Cantine\Services\Api\SourceInterface;
use Cantine\Services\Api\Sources;
use Cantine\Services\ApiException;
use Cantine\Services\HttpClient;
use Cantine\Services\MediaService;
use Cantine\Support\Flash;
use Cantine\Support\HttpException;
use Cantine\Support\Request;
use Cantine\Support\Response;
use Cantine\Support\View;
use Cantine\Types;

final class AddController extends Controller
{
    private function sources(): Sources
    {
        return new Sources($this->settings, new HttpClient($this->db));
    }

    private function requireType(string $type): string
    {
        if (!Types::exists($type)) {
            throw HttpException::notFound();
        }
        return $type;
    }

    public function chooseType(): Response
    {
        $available = [];
        foreach (array_keys(Types::ALL) as $type) {
            $source = $this->sources()->forType($type);
            $available[$type] = $source !== null && $source->isAvailable();
        }
        return $this->view('pages/add_type', ['title' => t('add.title'), 'available' => $available]);
    }

    // ---- Recherche ----

    public function search(): Response
    {
        $type = $this->requireType(Request::query('type'));
        $query = mb_substr(Request::query('q'), 0, 200);
        $kind = Request::query('kind') === 'track' ? 'track' : 'album';
        $source = $this->sources()->forType($type);

        return $this->view('pages/add_search', [
            'title' => t('add.title'),
            'type' => $type,
            'query' => $query,
            'kind' => $kind,
            'available' => $source !== null && $source->isAvailable(),
            'results' => $query !== '' ? $this->runSearch($type, $query, $kind, $source) : null,
        ]);
    }

    /** Résultats seuls (fragment HTML), pour la recherche en direct en JavaScript. */
    public function searchFragment(): Response
    {
        $type = $this->requireType(Request::query('type'));
        $query = mb_substr(Request::query('q'), 0, 200);
        $kind = Request::query('kind') === 'track' ? 'track' : 'album';
        $results = mb_strlen($query) >= 2 ? $this->runSearch($type, $query, $kind, $this->sources()->forType($type)) : null;
        $response = Response::html(View::render('partials/search_results', ['type' => $type, 'query' => $query, 'results' => $results]));
        $response->headers['Cache-Control'] = 'no-store';
        return $response;
    }

    /**
     * Recherche : contenus déjà présents sur le site (alerte de doublon précoce) + résultats de l'API.
     * @return array{local: list<array>, remote: list<array>, error: ?string}
     */
    private function runSearch(string $type, string $query, string $kind, ?SourceInterface $source): array
    {
        $repo = new MediaRepository($this->db);
        $result = ['local' => $type === 'video' ? [] : $repo->searchLocal($query, $type, 5), 'remote' => [], 'error' => null];
        if ($source === null || !$source->isAvailable()) {
            $result['error'] = t('add.search_unavailable');
            return $result;
        }
        try {
            $remote = $source->search($query, ['kind' => $kind]);
        } catch (ApiException $e) {
            $result['error'] = $e->getMessage();
            return $result;
        }
        // Signale les résultats déjà recommandés.
        foreach ($remote as &$item) {
            $existing = $repo->findByExternal($type, $item['source'], $item['external_id']);
            $item['existing'] = $existing;
            if ($existing && $type === 'video') {
                $result['local'][] = $existing;
            }
        }
        unset($item);
        $result['remote'] = $remote;
        return $result;
    }

    // ---- Choix d'un résultat, aperçu, enregistrement ----

    /** Lit et vérifie (type, source, id externe) envoyés par le navigateur. */
    private function readSelection(array $input): array
    {
        $type = $this->requireType((string) ($input['type'] ?? ''));
        $sourceName = (string) ($input['source'] ?? '');
        $externalId = trim((string) ($input['id'] ?? ''));
        if (!in_array($sourceName, Types::get($type)['sources'], true) || $externalId === '' || strlen($externalId) > 100
            || !preg_match('/^[A-Za-z0-9:_-]+$/', $externalId)) {
            throw HttpException::notFound();
        }
        return [$type, $sourceName, $externalId];
    }

    public function select(): Response
    {
        [$type, $sourceName, $externalId] = $this->readSelection($_POST);
        $existing = (new MediaRepository($this->db))->findByExternal($type, $sourceName, $externalId);
        if ($existing) {
            return $this->alreadyThere($existing);
        }
        return $this->redirect('ajouter/apercu', ['type' => $type, 'source' => $sourceName, 'id' => $externalId]);
    }

    /** Doublon : on ne crée rien, on marque "vu" et on renvoie vers la fiche existante. */
    private function alreadyThere(array $existing): Response
    {
        (new UserMediaRepository($this->db))->markSeen($this->userId(), (int) $existing['id']);
        Flash::info(t('add.already', [
            'name' => $existing['added_by_name'] ?? t('user.former'),
            'date' => date_short($existing['created_at']),
        ]));
        return $this->redirect('media', ['id' => $existing['id']]);
    }

    public function preview(): Response
    {
        [$type, $sourceName, $externalId] = $this->readSelection($_GET);
        $existing = (new MediaRepository($this->db))->findByExternal($type, $sourceName, $externalId);
        if ($existing) {
            return $this->redirect('media', ['id' => $existing['id']]);
        }
        try {
            $details = $this->fetchDetails($type, $sourceName, $externalId);
        } catch (ApiException $e) {
            Flash::error($e->getMessage());
            return $this->redirect('ajouter/recherche', ['type' => $type]);
        }
        return $this->view('pages/add_preview', [
            'title' => t('add.preview_title'),
            'type' => $type,
            'details' => $details,
        ]);
    }

    public function save(): Response
    {
        [$type, $sourceName, $externalId] = $this->readSelection($_POST);
        try {
            $details = $this->fetchDetails($type, $sourceName, $externalId);
        } catch (ApiException $e) {
            Flash::error($e->getMessage());
            return $this->redirect('ajouter/recherche', ['type' => $type]);
        }
        $details['region'] = $this->sources()->region();
        $result = (new MediaService($this->db))->createFromApi($details, $this->userId());
        if (!$result['created']) {
            $existing = (new MediaRepository($this->db))->find($result['id']);
            return $this->alreadyThere($existing);
        }
        Flash::success(t('add.saved', ['title' => $details['title']]));
        return $this->redirect('media', ['id' => $result['id']]);
    }

    /** La fiche est toujours relue côté serveur : on ne fait jamais confiance aux titres/images du navigateur. */
    private function fetchDetails(string $type, string $sourceName, string $externalId): array
    {
        $source = $this->sources()->get($sourceName, $type);
        if ($source === null || !$source->isAvailable()) {
            throw new ApiException(t('add.search_unavailable'));
        }
        $details = $source->fetch($externalId);
        $details['type'] = $type;
        return $details;
    }

    // ---- Ajout manuel ----

    public function manualForm(): Response
    {
        $type = $this->requireType(Request::query('type'));
        return $this->view('pages/add_manual', [
            'title' => t('add.manual_title'),
            'type' => $type,
            'values' => ['title' => Request::query('q'), 'extra' => []],
            'errors' => [],
        ]);
    }

    public function manualSave(): Response
    {
        $type = $this->requireType(Request::post('type'));
        $repo = new MediaRepository($this->db);

        // "C'est le même" : on rejoint le contenu existant.
        $sameAs = Request::postInt('same_as');
        if ($sameAs > 0) {
            $existing = $repo->find($sameAs) ?? throw HttpException::notFound();
            return $this->alreadyThere($existing);
        }

        [$data, $errors] = self::validateManualFields($type, true);
        if ($errors) {
            return $this->view('pages/add_manual', [
                'title' => t('add.manual_title'),
                'type' => $type,
                'values' => array_merge($_POST, ['extra' => $data['extra']]),
                'errors' => $errors,
            ], 422);
        }

        if (!Request::postBool('confirm_distinct')) {
            $similar = $repo->findSimilar($type, $data['title'], $data['year']);
            if ($similar) {
                return $this->view('pages/add_duplicate', [
                    'title' => t('add.duplicate_title'),
                    'type' => $type,
                    'similar' => $similar,
                    'post' => $_POST,
                ]);
            }
        }

        $id = $repo->create($data + ['type' => $type, 'source' => 'manuel', 'external_id' => null], $data['genres'], $this->userId());
        Flash::success(t('add.saved', ['title' => $data['title']]));
        return $this->redirect('media', ['id' => $id]);
    }

    /**
     * Valide les champs d'un ajout manuel (ou de la modification d'un contenu).
     * Si $full est faux (contenu venant d'une API), seuls titre, année et description sont lus.
     * @return array{0: array, 1: array<string, string>} [données, erreurs par champ]
     */
    public static function validateManualFields(string $type, bool $full): array
    {
        $errors = [];
        $title = mb_substr(Request::post('title'), 0, 255);
        if ($title === '') {
            $errors['title'] = t('add.error_title');
        }
        $yearText = Request::post('year');
        $year = null;
        if ($yearText !== '') {
            if (!preg_match('/^\d{4}$/', $yearText) || (int) $yearText < 1000 || (int) $yearText > 2100) {
                $errors['year'] = t('add.error_year');
            } else {
                $year = (int) $yearText;
            }
        }
        $description = mb_substr(Request::post('description'), 0, 2000);
        $data = ['title' => $title, 'year' => $year, 'description' => $description !== '' ? $description : null];
        if (!$full) {
            return [$data, $errors];
        }

        $link = Request::post('external_url');
        if ($link !== '' && (!preg_match('~^https?://~i', $link) || !filter_var($link, FILTER_VALIDATE_URL) || strlen($link) > 500)) {
            $errors['external_url'] = t('add.error_link');
        }
        $image = Request::post('image_url');
        if ($image !== '' && (!str_starts_with($image, 'https://') || !filter_var($image, FILTER_VALIDATE_URL) || strlen($image) > 500)) {
            $errors['image_url'] = t('add.error_image');
        }
        $isbn = strtoupper((string) preg_replace('/[\s-]/', '', Request::post('isbn')));
        if ($isbn !== '' && !preg_match('/^(\d{9}[\dX]|97[89]\d{10})$/', $isbn)) {
            $errors['isbn'] = t('add.error_isbn');
        }

        $genres = array_slice(array_values(array_filter(array_map(
            static fn ($g) => mb_substr(trim($g), 0, 50),
            explode(',', Request::post('genres'))
        ))), 0, 6);
        $genres = array_map(static fn ($g) => mb_strtoupper(mb_substr($g, 0, 1)) . mb_substr($g, 1), $genres);

        $extra = [];
        $postedExtra = is_array($_POST['extra'] ?? null) ? $_POST['extra'] : [];
        foreach (Types::get($type)['extra'] as $key => [$label, $format]) {
            $value = is_string($postedExtra[$key] ?? null) ? trim($postedExtra[$key]) : '';
            if ($value === '') {
                continue;
            }
            if (in_array($format, ['number', 'minutes', 'seconds'], true)) {
                if (!ctype_digit($value) || (int) $value > 1000000) {
                    $errors['extra_' . $key] = t('add.error_number', ['field' => $label]);
                    continue;
                }
                $extra[$key] = (int) $value;
            } else {
                $extra[$key] = mb_substr($value, 0, 200);
            }
        }

        return [$data + [
            'external_url' => $link !== '' ? $link : null,
            'image_url' => $image !== '' ? $image : null,
            'isbn' => $isbn !== '' ? $isbn : null,
            'genres' => $genres,
            'extra' => $extra,
        ], $errors];
    }
}
