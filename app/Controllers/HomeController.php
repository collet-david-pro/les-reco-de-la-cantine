<?php
/**
 * Page d'accueil : fil "Quoi de neuf".
 */
declare(strict_types=1);

namespace Cantine\Controllers;

defined('CANTINE') || exit;

use Cantine\Repositories\FeedRepository;
use Cantine\Support\Request;
use Cantine\Support\Response;

final class HomeController extends Controller
{
    public function index(): Response
    {
        $page = max(1, Request::queryInt('page', 1));
        $feed = (new FeedRepository($this->db))->page($page);
        return $this->view('pages/home', [
            'title' => t('nav.home'),
            'events' => $feed['events'],
            'hasMore' => $feed['has_more'],
            'page' => $page,
        ]);
    }
}
