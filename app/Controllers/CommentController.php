<?php
/**
 * Commentaires : ajout, modification et suppression par l'auteur ; modération par l'admin.
 */
declare(strict_types=1);

namespace Cantine\Controllers;

defined('CANTINE') || exit;

use Cantine\Repositories\CommentRepository;
use Cantine\Repositories\MediaRepository;
use Cantine\Support\Flash;
use Cantine\Support\HttpException;
use Cantine\Support\Request;
use Cantine\Support\Response;

final class CommentController extends Controller
{
    public function create(): Response
    {
        $media = (new MediaRepository($this->db))->find(Request::postInt('media_id')) ?? throw HttpException::notFound();
        $body = CommentRepository::clean(Request::postRaw('body'));
        if ($body === null) {
            Flash::error(t('comment.empty'));
        } else {
            (new CommentRepository($this->db))->create((int) $media['id'], $this->userId(), $body, Request::postBool('spoiler'));
            Flash::success(t('comment.added'));
        }
        return Response::redirect(url('media', ['id' => $media['id']]) . '#commentaires');
    }

    private function loadOwned(int $id): array
    {
        $comment = (new CommentRepository($this->db))->find($id) ?? throw HttpException::notFound();
        $this->requireOwnerOrAdmin((int) $comment['user_id']);
        return $comment;
    }

    public function editForm(): Response
    {
        $comment = $this->loadOwned(Request::queryInt('id'));
        $media = (new MediaRepository($this->db))->find((int) $comment['media_id']) ?? throw HttpException::notFound();
        return $this->view('pages/comment_edit', ['title' => t('comment.edit_title'), 'comment' => $comment, 'media' => $media]);
    }

    public function update(): Response
    {
        $comment = $this->loadOwned(Request::postInt('id'));
        $body = CommentRepository::clean(Request::postRaw('body'));
        if ($body === null) {
            Flash::error(t('comment.empty'));
            return $this->redirect('commentaire/modifier', ['id' => $comment['id']]);
        }
        (new CommentRepository($this->db))->update((int) $comment['id'], $body, Request::postBool('spoiler'));
        Flash::success(t('comment.updated'));
        return Response::redirect(url('media', ['id' => $comment['media_id']]) . '#commentaire-' . $comment['id']);
    }

    public function delete(): Response
    {
        $comment = $this->loadOwned(Request::postInt('id'));
        (new CommentRepository($this->db))->delete((int) $comment['id']);
        Flash::success(t('comment.deleted'));
        return Response::redirect(url('media', ['id' => $comment['media_id']]) . '#commentaires');
    }
}
