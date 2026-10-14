<?php
/**
 * Tirage au sort "Que regarder ce soir ?" parmi mes contenus "À découvrir".
 */
declare(strict_types=1);

namespace Cantine\Controllers;

defined('CANTINE') || exit;

use Cantine\Repositories\MediaRepository;
use Cantine\Repositories\UserMediaRepository;
use Cantine\Support\Flash;
use Cantine\Support\HttpException;
use Cantine\Support\Request;
use Cantine\Support\Response;
use Cantine\Types;

final class RandomController extends Controller
{
    public function index(): Response
    {
        $repo = new MediaRepository($this->db);
        $type = Types::exists(Request::query('type')) ? Request::query('type') : null;
        $genre = Request::queryInt('genre') ?: null;
        $draw = Request::query('tirer') === '1';

        $media = null;
        if ($draw) {
            $media = $repo->randomToDiscover($this->userId(), $type, $genre, Request::queryInt('sauf') ?: null)
                ?? $repo->randomToDiscover($this->userId(), $type, $genre);
        }

        return $this->view('pages/random', [
            'title' => t('random.title'),
            'type' => $type,
            'genre' => $genre,
            'draw' => $draw,
            'media' => $media,
            'genres' => $repo->usedGenres($type),
        ]);
    }

    /** "Je le commence" : passe le contenu tiré en "En cours". */
    public function start(): Response
    {
        $id = Request::postInt('media_id');
        $media = (new MediaRepository($this->db))->find($id) ?? throw HttpException::notFound();
        (new UserMediaRepository($this->db))->setStatus($this->userId(), $id, 'en_cours');
        Flash::success(t('random.started', ['title' => $media['title']]));
        return $this->redirect('media', ['id' => $id]);
    }
}
