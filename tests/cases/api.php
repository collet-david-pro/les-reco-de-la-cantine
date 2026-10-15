<?php
/** Tests des connecteurs d'API avec des réponses enregistrées (aucun appel réseau). */
declare(strict_types=1);

use Cantine\Services\Api\Deezer;
use Cantine\Services\Api\Igdb;
use Cantine\Services\Api\Sources;
use Cantine\Services\Api\Steam;
use Cantine\Support\Settings;
use Cantine\Services\Api\OpenLibrary;
use Cantine\Services\Api\Rawg;
use Cantine\Services\Api\Tmdb;
use Cantine\Services\Api\YouTube;
use Cantine\Services\ApiException;
use Cantine\Services\HttpClient;

/** Client HTTP de test : associe des motifs d'URL à des fichiers de tests/fixtures. */
final class FixtureHttp extends HttpClient
{
    public array $requested = [];
    public bool $imagesExist = true;

    public function urlExists(string $url): bool
    {
        return $this->imagesExist;
    }

    public function __construct(private array $routes)
    {
        parent::__construct(null);
    }

    protected function request(string $url, array $headers, ?string $postBody = null): string
    {
        $this->requested[] = ['url' => $url, 'headers' => $headers, 'body' => $postBody];
        foreach ($this->routes as $pattern => $file) {
            if (preg_match($pattern, $url)) {
                return (string) file_get_contents(dirname(__DIR__) . '/fixtures/' . $file);
            }
        }
        throw new ApiException('Pas de fixture pour ' . $url);
    }
}

return [
    'TMDB : recherche et fiche film' => function (): void {
        $http = new FixtureHttp(['~/search/movie~' => 'tmdb-search-movie.json', '~/movie/603\?~' => 'tmdb-movie.json']);
        $tmdb = new Tmdb($http, 'abcdef0123456789abcdef0123456789', 'film', 'FR');
        $results = $tmdb->search('matrix');
        same(2, count($results), 'résultats invalides ignorés');
        same('603', $results[0]['external_id']);
        same(1999, $results[0]['year']);
        same('The Matrix', $results[0]['info']);
        same('https://image.tmdb.org/t/p/w154/dXNAPwY7VrqMAo51EKhhCJfaGb5.jpg', $results[0]['image_url']);
        same(null, $results[1]['image_url']);
        check(str_contains($http->requested[0]['url'], 'api_key='), 'clé v3 en paramètre');
        check(str_contains($http->requested[0]['url'], 'language=fr-FR'));

        $film = $tmdb->fetch('603');
        same('Matrix', $film['title']);
        same(136, $film['extra']['runtime']);
        same(['Lana Wachowski', 'Lilly Wachowski'], $film['extra']['director']);
        same('tt0133093', $film['extra']['imdb_id']);
        same(['Action', 'Science-Fiction'], $film['genres']);
        same('https://www.youtube.com/watch?v=m8e-FF8MsqU', $film['trailer_url'], 'bande-annonce en français de préférence');
        same('Netflix', $film['watch_providers']['flatrate'][0]['name']);
        same(1, count($film['watch_providers']['rent']));
        throws(fn () => $tmdb->fetch('../etc'), ApiException::class);
    },

    'TMDB : série, jeton Bearer et clé absente' => function (): void {
        $http = new FixtureHttp(['~/tv/1396\?~' => 'tmdb-tv.json']);
        $tmdb = new Tmdb($http, 'eyJhbGciOiJIUzI1NiJ9.test.signature', 'serie', 'FR');
        $serie = $tmdb->fetch('1396');
        same(5, $serie['extra']['seasons']);
        same(62, $serie['extra']['episodes']);
        same(['AMC'], $serie['extra']['networks']);
        same(null, $serie['trailer_url']);
        same(['link' => null, 'flatrate' => [], 'free' => [], 'rent' => [], 'buy' => []], $serie['watch_providers']);
        check(str_starts_with($http->requested[0]['headers']['Authorization'] ?? '', 'Bearer '), 'jeton en en-tête');
        check(!str_contains($http->requested[0]['url'], 'api_key'));

        $noKey = new Tmdb(new FixtureHttp([]), '', 'film');
        check(!$noKey->isAvailable());
        throws(fn () => $noKey->search('x'), ApiException::class);
    },

    'Open Library : recherche et fiche livre' => function (): void {
        $http = new FixtureHttp([
            '~search\.json\?q=key~' => 'openlibrary-fetch.json',
            '~search\.json~' => 'openlibrary-search.json',
            '~/works/OL45804W\.json~' => 'openlibrary-work.json',
        ]);
        $ol = new OpenLibrary($http);
        $results = $ol->search('charlie chocolaterie');
        check(count($results) >= 3);
        check((bool) preg_match('/^OL\d+W$/', $results[0]['external_id']));
        $book = $ol->fetch('OL45804W');
        same('livre', $book['type']);
        check($book['title'] !== '');
        check($book['isbn'] === null || (bool) preg_match('/^(97[89]\d{10}|\d{9}[\dX])$/', $book['isbn']), 'ISBN valide');
        same('https://openlibrary.org/works/OL45804W', $book['external_url']);
        check(count($book['genres']) <= 3, '3 genres maximum');
        throws(fn () => $ol->fetch('OL1M'), ApiException::class);
    },

    'RAWG : recherche et fiche jeu' => function (): void {
        $http = new FixtureHttp(['~/games/3328\?~' => 'rawg-game.json', '~/games\?~' => 'rawg-search.json']);
        $rawg = new Rawg($http, 'cle-rawg');
        $results = $rawg->search('witcher');
        same('3328', $results[0]['external_id']);
        same('PC, PlayStation 4, Nintendo Switch', $results[0]['info']);
        same(null, $results[1]['image_url'], 'image non https refusée');
        $game = $rawg->fetch('3328');
        same(['Action', 'RPG'], $game['genres']);
        same('https://rawg.io/games/the-witcher-3-wild-hunt', $game['external_url']);
        same(['PC', 'Xbox One'], $game['extra']['platforms']);
    },

    'Deezer : albums et morceaux' => function (): void {
        $http = new FixtureHttp([
            '~/search/album~' => 'deezer-search-album.json',
            '~/search/track~' => 'deezer-search-track.json',
            '~/album/6575789$~' => 'deezer-album.json',
        ]);
        $deezer = new Deezer($http);
        $albums = $deezer->search('daft punk');
        check(str_starts_with($albums[0]['external_id'], 'album:'));
        $tracks = $deezer->search('get lucky', ['kind' => 'track']);
        check(str_starts_with($tracks[0]['external_id'], 'track:'));
        $album = $deezer->fetch('album:6575789');
        same('Random Access Memories', $album['title']);
        same(2013, $album['year']);
        same('Daft Punk', $album['extra']['artist']);
        same('https://www.deezer.com/album/6575789', $album['external_url']);
        throws(fn () => $deezer->fetch('6575789'), ApiException::class);
    },

    'YouTube : oEmbed sur l\'URL reconstruite' => function (): void {
        $http = new FixtureHttp(['~youtube\.com/oembed~' => 'youtube-oembed.json']);
        $yt = new YouTube($http);
        $results = $yt->search('https://youtu.be/dQw4w9WgXcQ?si=tracking');
        same('dQw4w9WgXcQ', $results[0]['external_id']);
        same('Rick Astley', $results[0]['info']);
        same('https://www.youtube.com/oembed?url=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3DdQw4w9WgXcQ&format=json', $http->requested[0]['url'], 'l\'URL saisie n\'est jamais appelée');
        throws(fn () => $yt->search('https://evil.example/x'), ApiException::class);
    },

    'Steam : recherche et fiche jeu, sans clé' => function (): void {
        $http = new FixtureHttp(['~/api/storesearch/~' => 'steam-search.json', '~/api/appdetails\\?appids=367520~' => 'steam-app.json']);
        $steam = new Steam($http, 'FR');
        check($steam->isAvailable(), 'toujours disponible');
        $results = $steam->search('hollow knight');
        same('367520', $results[0]['external_id']);
        same('Hollow Knight', $results[0]['title']);
        same('https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/367520/library_600x900.jpg', $results[0]['image_url']);
        check(str_contains($http->requested[0]['url'], 'l=french'));

        $game = $steam->fetch('367520');
        same('Hollow Knight', $game['title']);
        same(2017, $game['year']);
        same('https://store.steampowered.com/app/367520/', $game['external_url']);
        same(['Team Cherry'], $game['extra']['developers']);
        check(in_array('PC (Windows)', $game['extra']['platforms'], true));
        check(in_array('Action', $game['genres'], true), 'genres en français');
        check(!str_contains((string) $game['description'], '<'), 'résumé sans HTML');
        same(Steam::verticalCover(367520), $game['image_url'], 'jaquette verticale');

        $http->imagesExist = false;
        $fallback = (new Steam($http, 'FR'))->fetch('367520');
        check(str_contains((string) $fallback['image_url'], 'header.jpg'), 'repli sur la bannière');
        throws(fn () => $steam->fetch('abc'), ApiException::class);
    },

    'db: IGDB : jeton Twitch, recherche, fiche et renouvellement' => function (string $driver): void {
        $settings = new Settings(freshDb($driver));
        $http = new FixtureHttp(['~id\\.twitch\\.tv/oauth2/token~' => 'igdb-token.json', '~api\\.igdb\\.com/v4/games~' => 'igdb-search.json']);
        $igdb = new Igdb($http, $settings, 'client123', 'secret456');
        check($igdb->isAvailable());
        check(!(new Igdb($http, $settings, 'client123', ''))->isAvailable(), 'secret obligatoire');

        $results = $igdb->search('zelda "botw"');
        same(2, count($results), 'jeu sans nom ignoré');
        same('1942', $results[0]['external_id']);
        same(2017, $results[0]['year']);
        same('Switch, WiiU', $results[0]['info']);
        same('https://images.igdb.com/igdb/image/upload/t_cover_small/co3p2d.jpg', $results[0]['image_url']);
        same(null, $results[1]['image_url'], 'identifiant d\'image suspect refusé');
        same('abcdef0123456789tokenigdb', $settings->get('igdb_token'), 'jeton gardé');
        $search = $http->requested[1];
        same('Bearer abcdef0123456789tokenigdb', $search['headers']['Authorization']);
        same('client123', $search['headers']['Client-ID']);
        check(str_contains((string) $search['body'], 'search "zelda \\"botw\\""'), 'requête échappée');

        $game = $igdb->fetch('1942');
        same(['RPG', 'Aventure'], $game['genres'], 'genres traduits');
        same(['Nintendo EPD'], $game['extra']['developers'], 'seulement les développeurs');
        same('https://images.igdb.com/igdb/image/upload/t_cover_big/co3p2d.jpg', $game['image_url']);
        $tokenCalls = static fn () => count(array_filter($http->requested, static fn ($r) => str_contains($r['url'], 'twitch')));
        same(1, $tokenCalls(), 'un seul jeton demandé tant qu\'il est valable');
        $settings->set('igdb_token_expires', '0');
        $igdb->search('mario');
        same(2, $tokenCalls(), 'jeton expiré : renouvelé automatiquement');
        throws(fn () => $igdb->fetch('1 or 1'), ApiException::class);
    },

    'db: jeux : IGDB, sinon RAWG, sinon Steam' => function (string $driver): void {
        $settings = new Settings(freshDb($driver));
        $sources = new Sources($settings, new FixtureHttp([]));
        same('steam', $sources->forType('jeu')->name(), 'sans clé : Steam');
        $settings->set('rawg_key', 'cle-rawg');
        same('rawg', $sources->forType('jeu')->name());
        $settings->set('igdb_client_id', 'id');
        same('rawg', $sources->forType('jeu')->name(), 'IGDB incomplet');
        $settings->set('igdb_client_secret', 'secret');
        same('igdb', $sources->forType('jeu')->name());
        same('tmdb', $sources->forType('film')->name(), 'films : TMDB même sans clé (désactivé)');
        check(!$sources->forType('film')->isAvailable());
    },

    'client HTTP : hôtes autorisés uniquement' => function (): void {
        $http = new HttpClient(null);
        throws(fn () => $http->getJson('https://evil.example/api'), ApiException::class);
        throws(fn () => $http->getJson('http://api.deezer.com/search'), ApiException::class);
        throws(fn () => $http->getJson('file:///etc/passwd'), ApiException::class);
    },
];
