<?php
/** Tests des connecteurs d'API avec des réponses enregistrées (aucun appel réseau). */
declare(strict_types=1);

use Cantine\Services\Api\Deezer;
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

    public function __construct(private array $routes)
    {
        parent::__construct(null);
    }

    protected function request(string $url, array $headers): string
    {
        $this->requested[] = ['url' => $url, 'headers' => $headers];
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

    'client HTTP : hôtes autorisés uniquement' => function (): void {
        $http = new HttpClient(null);
        throws(fn () => $http->getJson('https://evil.example/api'), ApiException::class);
        throws(fn () => $http->getJson('http://api.deezer.com/search'), ApiException::class);
        throws(fn () => $http->getJson('file:///etc/passwd'), ApiException::class);
    },
];
