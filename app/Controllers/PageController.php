<?php
/**
 * Pages simples : "À propos" (attributions obligatoires) et manifeste PWA.
 */
declare(strict_types=1);

namespace Cantine\Controllers;

defined('CANTINE') || exit;

use Cantine\App;
use Cantine\Support\Response;
use Cantine\Support\View;

final class PageController extends Controller
{
    public function about(): Response
    {
        $layout = $this->auth->user() ? 'layout/main' : 'layout/bare';
        return Response::html(View::page('pages/about', ['title' => t('about.title'), 'version' => CANTINE_VERSION], $layout));
    }

    /** manifest.webmanifest généré à partir du nom du site (URLs relatives : le site peut être dans un sous-dossier). */
    public function manifest(): Response
    {
        $name = App::siteName();
        $base = App::baseUrl();
        $manifest = [
            'name' => $name,
            'short_name' => mb_strlen($name) > 12 ? 'La cantine' : $name,
            'description' => t('pwa.description'),
            'lang' => 'fr',
            'start_url' => $base . 'index.php?route=accueil',
            'scope' => $base,
            'display' => 'standalone',
            'background_color' => '#FAF7F2',
            'theme_color' => '#C2410C',
            'icons' => [
                ['src' => $base . 'assets/img/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => $base . 'assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => $base . 'assets/img/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ];
        return new Response(
            (string) json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            200,
            ['Content-Type' => 'application/manifest+json; charset=utf-8', 'Cache-Control' => 'public, max-age=86400']
        );
    }
}
