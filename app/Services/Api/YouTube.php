<?php
/**
 * YouTube (vidéos) via oEmbed, sans clé.
 * L'utilisateur colle un lien ; on en extrait l'identifiant puis on RECONSTRUIT l'URL canonique :
 * l'URL saisie n'est jamais appelée telle quelle (protection SSRF).
 */
declare(strict_types=1);

namespace Cantine\Services\Api;

defined('CANTINE') || exit;

use Cantine\Services\ApiException;
use Cantine\Services\HttpClient;

final class YouTube implements SourceInterface
{
    public function __construct(private HttpClient $http)
    {
    }

    public function name(): string
    {
        return 'youtube';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /**
     * Extrait l'identifiant (11 caractères) d'un lien YouTube.
     * Formats acceptés : youtube.com/watch?v=ID, youtu.be/ID, youtube.com/shorts/ID, m.youtube.com, music.youtube.com.
     */
    public static function extractId(string $input): ?string
    {
        $input = trim($input);
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }
        if (!preg_match('~^https?://~i', $input)) {
            $input = 'https://' . $input;
        }
        $parts = parse_url($input);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';
        $id = null;
        if ($host === 'youtu.be') {
            $id = explode('/', ltrim($path, '/'))[0] ?? null;
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            if ($path === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $id = is_string($query['v'] ?? null) ? $query['v'] : null;
            } elseif (preg_match('~^/(shorts|embed|live|v)/([^/?#]+)~', $path, $m)) {
                $id = $m[2];
            }
        }
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }

    public static function canonicalUrl(string $id): string
    {
        return 'https://www.youtube.com/watch?v=' . $id;
    }

    /** La "recherche" YouTube consiste à lire le lien collé : un seul résultat possible. */
    public function search(string $query, array $options = []): array
    {
        $id = self::extractId($query);
        if ($id === null) {
            throw new ApiException(t('add.youtube_invalid'));
        }
        $video = $this->fetch($id);
        return [[
            'source' => 'youtube',
            'external_id' => $id,
            'title' => $video['title'],
            'year' => null,
            'image_url' => $video['image_url'],
            'info' => $video['extra']['channel'] ?? null,
        ]];
    }

    public function fetch(string $externalId): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $externalId)) {
            throw new ApiException(t('add.youtube_invalid'));
        }
        $url = self::canonicalUrl($externalId);
        $data = $this->http->getJson('https://www.youtube.com/oembed?' . http_build_query(['url' => $url, 'format' => 'json']), [], HttpClient::TTL_DETAILS);
        $title = Helpers::str($data['title'] ?? null);
        if ($title === null) {
            throw new ApiException(t('api.not_found'));
        }
        return [
            'source' => 'youtube',
            'external_id' => $externalId,
            'type' => 'video',
            'title' => $title,
            'year' => null,
            'description' => null,
            'image_url' => 'https://i.ytimg.com/vi/' . $externalId . '/hqdefault.jpg',
            'external_url' => $url,
            'trailer_url' => null,
            'isbn' => null,
            'genres' => [],
            'extra' => ['channel' => Helpers::str($data['author_name'] ?? null, 100)],
        ];
    }
}
