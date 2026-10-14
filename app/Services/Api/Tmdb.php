<?php
/**
 * TMDB (films et séries). Clé gratuite, attribution obligatoire (voir page "À propos").
 *
 * La clé peut être une "clé d'API v3" (32 caractères, paramètre api_key)
 * ou un "jeton d'accès en lecture" (JWT commençant par eyJ, en-tête Authorization: Bearer).
 */
declare(strict_types=1);

namespace Cantine\Services\Api;

defined('CANTINE') || exit;

use Cantine\Services\ApiException;
use Cantine\Services\HttpClient;

final class Tmdb implements SourceInterface
{
    private const BASE = 'https://api.themoviedb.org/3';
    public const IMAGE_BASE = 'https://image.tmdb.org/t/p/';

    /** @param string $type 'film' ou 'serie' */
    public function __construct(
        private HttpClient $http,
        private string $apiKey,
        private string $type = 'film',
        private string $region = 'FR',
    ) {
    }

    public function name(): string
    {
        return 'tmdb';
    }

    public function isAvailable(): bool
    {
        return trim($this->apiKey) !== '';
    }

    private function kind(): string
    {
        return $this->type === 'serie' ? 'tv' : 'movie';
    }

    /** Appel authentifié (clé v3 ou jeton Bearer). */
    private function get(string $path, array $params, int $ttl): array
    {
        if (!$this->isAvailable()) {
            throw new ApiException(t('api.no_key'));
        }
        $headers = [];
        $key = trim($this->apiKey);
        if (str_starts_with($key, 'eyJ')) {
            $headers['Authorization'] = 'Bearer ' . $key;
        } else {
            $params['api_key'] = $key;
        }
        return $this->http->getJson(self::BASE . $path . '?' . http_build_query($params), $headers, $ttl);
    }

    public function search(string $query, array $options = []): array
    {
        $data = $this->get('/search/' . $this->kind(), [
            'query' => $query,
            'language' => 'fr-FR',
            'include_adult' => 'false',
            'page' => 1,
        ], HttpClient::TTL_SEARCH);

        $results = [];
        foreach (Helpers::arr($data['results'] ?? null) as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }
            $title = Helpers::str($item['title'] ?? $item['name'] ?? null);
            if ($title === null) {
                continue;
            }
            $original = Helpers::str($item['original_title'] ?? $item['original_name'] ?? null);
            $results[] = [
                'source' => 'tmdb',
                'external_id' => (string) (int) $item['id'],
                'title' => $title,
                'year' => Helpers::year($item['release_date'] ?? $item['first_air_date'] ?? null),
                'image_url' => self::image($item['poster_path'] ?? null, 'w154'),
                'info' => $original !== null && $original !== $title ? $original : Helpers::excerpt(Helpers::str($item['overview'] ?? null, 2000), 90),
            ];
        }
        return array_slice($results, 0, 20);
    }

    public function fetch(string $externalId): array
    {
        if (!ctype_digit($externalId)) {
            throw new ApiException(t('api.not_found'));
        }
        $data = $this->get('/' . $this->kind() . '/' . $externalId, [
            'language' => 'fr-FR',
            'append_to_response' => 'videos,external_ids,watch/providers,credits',
            'include_video_language' => 'fr,en',
        ], HttpClient::TTL_DETAILS);

        $title = Helpers::str($data['title'] ?? $data['name'] ?? null);
        if ($title === null) {
            throw new ApiException(t('api.not_found'));
        }
        $original = Helpers::str($data['original_title'] ?? $data['original_name'] ?? null);
        $imdbId = Helpers::str($data['external_ids']['imdb_id'] ?? $data['imdb_id'] ?? null, 20);

        $extra = ['original_title' => $original !== $title ? $original : null];
        if ($this->type === 'serie') {
            $extra['seasons'] = Helpers::int($data['number_of_seasons'] ?? null);
            $extra['episodes'] = Helpers::int($data['number_of_episodes'] ?? null);
            $extra['networks'] = Helpers::names($data['networks'] ?? null, 'name', 3);
            $extra['creators'] = Helpers::names($data['created_by'] ?? null, 'name', 3);
        } else {
            $extra['runtime'] = Helpers::int($data['runtime'] ?? null) ?: null;
            $directors = [];
            foreach (Helpers::arr($data['credits']['crew'] ?? null) as $person) {
                if (is_array($person) && ($person['job'] ?? '') === 'Director') {
                    $directors[] = Helpers::str($person['name'] ?? null, 100);
                }
            }
            $extra['director'] = array_values(array_unique(array_filter($directors)));
        }
        if ($imdbId !== null && preg_match('/^tt\d+$/', $imdbId)) {
            $extra['imdb_id'] = $imdbId;
        }

        return [
            'source' => 'tmdb',
            'external_id' => $externalId,
            'type' => $this->type,
            'title' => $title,
            'year' => Helpers::year($data['release_date'] ?? $data['first_air_date'] ?? null),
            'description' => Helpers::str($data['overview'] ?? null, 2000),
            'image_url' => self::image($data['poster_path'] ?? null, 'w500'),
            'external_url' => 'https://www.themoviedb.org/' . $this->kind() . '/' . $externalId,
            'trailer_url' => self::trailer(Helpers::arr($data['videos']['results'] ?? null)),
            'isbn' => null,
            'genres' => Helpers::names($data['genres'] ?? null, 'name', 6),
            'extra' => $extra,
            'watch_providers' => self::parseProviders(Helpers::arr($data['watch/providers']['results'] ?? null), $this->region),
        ];
    }

    /** Disponibilités "où regarder" seules (rafraîchissement). */
    public function watchProviders(string $externalId): ?array
    {
        $data = $this->get('/' . $this->kind() . '/' . $externalId . '/watch/providers', [], 0);
        return self::parseProviders(Helpers::arr($data['results'] ?? null), $this->region);
    }

    /** Vérifie la clé (bouton "Tester la clé" de l'admin). */
    public function testKey(): void
    {
        $this->get('/configuration', [], 0);
    }

    public static function image(mixed $path, string $size): ?string
    {
        return is_string($path) && preg_match('~^/[A-Za-z0-9._-]+$~', $path) ? self::IMAGE_BASE . $size . $path : null;
    }

    /** Bande-annonce YouTube : français de préférence, puis anglais. */
    private static function trailer(array $videos): ?string
    {
        $best = null;
        $bestScore = -1;
        foreach ($videos as $video) {
            if (!is_array($video) || ($video['site'] ?? '') !== 'YouTube') {
                continue;
            }
            $key = Helpers::str($video['key'] ?? null, 20);
            if ($key === null || !preg_match('/^[A-Za-z0-9_-]{11}$/', $key)) {
                continue;
            }
            $score = (($video['type'] ?? '') === 'Trailer' ? 10 : (($video['type'] ?? '') === 'Teaser' ? 5 : 0))
                + (($video['iso_639_1'] ?? '') === 'fr' ? 3 : 0)
                + (!empty($video['official']) ? 1 : 0);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $key;
            }
        }
        return $best !== null && $bestScore >= 5 ? 'https://www.youtube.com/watch?v=' . $best : null;
    }

    /**
     * Garde uniquement la région voulue et les champs utiles.
     * @return array{link: ?string, flatrate: list, free: list, rent: list, buy: list}|null
     */
    public static function parseProviders(array $results, string $region): ?array
    {
        $data = $results[$region] ?? null;
        if (!is_array($data)) {
            return ['link' => null, 'flatrate' => [], 'free' => [], 'rent' => [], 'buy' => []];
        }
        $list = static function (mixed $items): array {
            $out = [];
            foreach (Helpers::arr($items) as $item) {
                $name = Helpers::str(is_array($item) ? ($item['provider_name'] ?? null) : null, 100);
                if ($name === null) {
                    continue;
                }
                $out[] = ['name' => $name, 'logo' => self::image($item['logo_path'] ?? null, 'w92')];
            }
            return $out;
        };
        return [
            'link' => Helpers::httpsUrl($data['link'] ?? null),
            'flatrate' => $list($data['flatrate'] ?? null),
            'free' => array_merge($list($data['free'] ?? null), $list($data['ads'] ?? null)),
            'rent' => $list($data['rent'] ?? null),
            'buy' => $list($data['buy'] ?? null),
        ];
    }
}
