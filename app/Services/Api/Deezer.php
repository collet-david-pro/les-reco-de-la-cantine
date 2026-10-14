<?php
/**
 * Deezer (musique : albums et morceaux). API publique sans clé.
 * L'identifiant externe est préfixé par le genre d'objet ("album:123", "track:456")
 * pour qu'un album et un morceau ne puissent pas avoir le même identifiant.
 */
declare(strict_types=1);

namespace Cantine\Services\Api;

defined('CANTINE') || exit;

use Cantine\Services\ApiException;
use Cantine\Services\HttpClient;

final class Deezer implements SourceInterface
{
    private const BASE = 'https://api.deezer.com';

    public function __construct(private HttpClient $http)
    {
    }

    public function name(): string
    {
        return 'deezer';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /** Deezer répond parfois 200 avec {"error": {...}}. */
    private function get(string $path, array $params, int $ttl): array
    {
        $url = self::BASE . $path . ($params ? '?' . http_build_query($params) : '');
        $data = $this->http->getJson($url, [], $ttl);
        if (isset($data['error'])) {
            $code = is_array($data['error']) ? (int) ($data['error']['code'] ?? 0) : 0;
            throw new ApiException($code === 800 ? t('api.not_found') : t('api.unreachable'));
        }
        return $data;
    }

    /** $options['kind'] : 'album' (défaut) ou 'track'. */
    public function search(string $query, array $options = []): array
    {
        $kind = ($options['kind'] ?? 'album') === 'track' ? 'track' : 'album';
        $data = $this->get('/search/' . $kind, ['q' => $query, 'limit' => 20], HttpClient::TTL_SEARCH);

        $results = [];
        foreach (Helpers::arr($data['data'] ?? null) as $item) {
            if (!is_array($item) || !isset($item['id'])) {
                continue;
            }
            $title = Helpers::str($item['title'] ?? null);
            if ($title === null) {
                continue;
            }
            $artist = Helpers::str($item['artist']['name'] ?? null, 100);
            if ($kind === 'track') {
                $album = Helpers::str($item['album']['title'] ?? null, 100);
                $info = trim(($artist ?? '') . ($album !== null ? ' — ' . $album : ''), ' —');
                $image = Helpers::httpsUrl($item['album']['cover_medium'] ?? null);
            } else {
                $info = $artist;
                $image = Helpers::httpsUrl($item['cover_medium'] ?? null);
            }
            $results[] = [
                'source' => 'deezer',
                'external_id' => $kind . ':' . (int) $item['id'],
                'title' => $title,
                'year' => null,
                'image_url' => $image,
                'info' => $info !== '' ? $info : null,
            ];
        }
        return $results;
    }

    public function fetch(string $externalId): array
    {
        if (!preg_match('/^(album|track):(\d+)$/', $externalId, $m)) {
            throw new ApiException(t('api.not_found'));
        }
        [, $kind, $id] = $m;
        $data = $this->get('/' . $kind . '/' . $id, [], HttpClient::TTL_DETAILS);
        $title = Helpers::str($data['title'] ?? null);
        if ($title === null) {
            throw new ApiException(t('api.not_found'));
        }

        if ($kind === 'album') {
            $image = Helpers::httpsUrl($data['cover_big'] ?? $data['cover_medium'] ?? null);
            $extra = [
                'artist' => Helpers::str($data['artist']['name'] ?? null, 100),
                'kind' => 'Album',
                'tracks' => Helpers::int($data['nb_tracks'] ?? null),
                'duration' => Helpers::int($data['duration'] ?? null),
            ];
            $genres = Helpers::names($data['genres']['data'] ?? null, 'name', 3);
        } else {
            $image = Helpers::httpsUrl($data['album']['cover_big'] ?? $data['album']['cover_medium'] ?? null);
            $extra = [
                'artist' => Helpers::str($data['artist']['name'] ?? null, 100),
                'album' => Helpers::str($data['album']['title'] ?? null, 200),
                'kind' => 'Morceau',
                'duration' => Helpers::int($data['duration'] ?? null),
            ];
            $genres = [];
        }

        return [
            'source' => 'deezer',
            'external_id' => $externalId,
            'type' => 'musique',
            'title' => $title,
            'year' => Helpers::year($data['release_date'] ?? null),
            'description' => null,
            'image_url' => $image,
            'external_url' => Helpers::httpsUrl($data['link'] ?? null) ?? 'https://www.deezer.com/' . $kind . '/' . $id,
            'trailer_url' => null,
            'isbn' => null,
            'genres' => $genres,
            'extra' => $extra,
        ];
    }
}
