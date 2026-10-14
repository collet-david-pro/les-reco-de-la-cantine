<?php
/**
 * Profil d'un membre : ses recommandations, ses notes et quelques chiffres.
 */
declare(strict_types=1);

namespace Cantine\Controllers;

defined('CANTINE') || exit;

use Cantine\Repositories\MediaRepository;
use Cantine\Repositories\UserRepository;
use Cantine\Support\HttpException;
use Cantine\Support\Request;
use Cantine\Support\Response;

final class ProfileController extends Controller
{
    public function show(): Response
    {
        $id = Request::queryInt('id') ?: $this->userId();
        $users = new UserRepository($this->db);
        $member = $users->find($id) ?? throw HttpException::notFound();
        $page = max(1, Request::queryInt('page', 1));
        $added = (new MediaRepository($this->db))->addedBy($id, $this->userId(), $page);

        return $this->view('pages/profile', [
            'title' => $member['display_name'],
            'member' => $member,
            'stats' => $users->profileStats($id),
            'ratings' => $users->recentRatings($id),
            'items' => $added['items'],
            'total' => $added['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($added['total'] / MediaRepository::PER_PAGE)),
            'isMe' => $id === $this->userId(),
        ]);
    }
}
