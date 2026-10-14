<?php
/**
 * RAWG (jeux vidéo). Clé gratuite ; RAWG demande un lien vers rawg.io sur les fiches.
 */
declare(strict_types=1);

namespace Cantine\Services\Api;

defined('CANTINE') || exit;

use Cantine\Services\ApiException;
use Cantine\Services\HttpClient;

final class Rawg implements SourceInterface
{
    private const BASE = 'https://api.rawg.io/api';

    /** Traduction des genres RAWG (en anglais) les plus courants. */
    private const GENRES = [
        'Action' => 'Action', 'Adventure' => 'Aventure', 'RPG' => 'RPG', 'Shooter' => 'Tir',
        'Puzzle' => 'Réflexion', 'Strategy' => 'Stratégie', 'Simulation' => 'Simulation',
        'Platformer' => 'Plateforme', 'Racing' => 'Course', 'Sports' => 'Sport', 'Fighting' => 'Combat',
        'Family' => 'Famille', 'Board Games' => 'Jeu de société', 'Educational' => 'Éducatif',
        'Card' => 'Cartes', 'Casual' => 'Casual', 'Arcade' => 'Arcade', 'Indie' => 'Indépendant',
        'Massively Multiplayer' => 'Multijoueur massif',
    ];

    public function __construct(private HttpClient $http, private string $apiKey)
    {
    }

    public function name(): string
    {
        return 'rawg';
    }

    public function isAvailable(): bool
    {
        return trim($this->apiKey) !== '';
    }

    private function get(string $path, array $params, int $ttl): array
    {
        if (!$this->isAvailable()) {
            throw new ApiException(t('api.no_key'));
        }
        $params['key'] = trim($this->apiKey);
        return $this->http->getJson(self::BASE . $path . '?' . http_build_query($params), [], $ttl);
    }

    public function search(string $query, array $options = []): array
    {
        $data = $this->get('/games', ['search' => $query, 'page_size' => 20], HttpClient::TTL_SEARCH);
        $results = [];
        foreach (Helpers::arr($data['results'] ?? null) as $game) {
            if (!is_array($game) || !isset($game['id'])) {
                continue;
            }
            $title = Helpers::str($game['name'] ?? null);
            if ($title === null) {
                continue;
            }
            $results[] = [
                'source' => 'rawg',
                'external_id' => (string) (int) $game['id'],
                'title' => $title,
                'year' => Helpers::year($game['released'] ?? null),
                'image_url' => Helpers::httpsUrl($game['background_image'] ?? null),
                'info' => implode(', ', array_slice(self::platforms($game['platforms'] ?? null), 0, 4)) ?: null,
            ];
        }
        return $results;
    }

    public function fetch(string $externalId): array
    {
        if (!ctype_digit($externalId)) {
            throw new ApiException(t('api.not_found'));
        }
        $game = $this->get('/games/' . $externalId, [], HttpClient::TTL_DETAILS);
        $title = Helpers::str($game['name'] ?? null);
        if ($title === null) {
            throw new ApiException(t('api.not_found'));
        }
        $slug = Helpers::str($game['slug'] ?? null, 200);
        $genres = array_map(static fn (string $g) => self::GENRES[$g] ?? $g, Helpers::names($game['genres'] ?? null, 'name', 4));

        return [
            'source' => 'rawg',
            'external_id' => $externalId,
            'type' => 'jeu',
            'title' => $title,
            'year' => Helpers::year($game['released'] ?? null),
            'description' => Helpers::str($game['description_raw'] ?? null, 2000),
            'image_url' => Helpers::httpsUrl($game['background_image'] ?? null),
            'external_url' => 'https://rawg.io/games/' . ($slug !== null && preg_match('/^[a-z0-9-]+$/', $slug) ? $slug : $externalId),
            'trailer_url' => null,
            'isbn' => null,
            'genres' => $genres,
            'extra' => [
                'platforms' => self::platforms($game['platforms'] ?? null),
                'developers' => Helpers::names($game['developers'] ?? null, 'name', 3),
            ],
        ];
    }

    public function testKey(): void
    {
        $this->get('/games', ['page_size' => 1], 0);
    }

    private static function platforms(mixed $platforms): array
    {
        $names = [];
        foreach (Helpers::arr($platforms) as $item) {
            $name = Helpers::str(is_array($item) ? ($item['platform']['name'] ?? null) : null, 50);
            if ($name !== null) {
                $names[] = $name;
            }
        }
        return array_slice(array_values(array_unique($names)), 0, 10);
    }
}
