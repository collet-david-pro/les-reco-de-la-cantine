<?php
/**
 * IGDB (jeux vidéo, toutes plateformes y compris consoles). Facultatif : nécessite une "application" Twitch
 * (identifiant client + secret, gratuits). Prioritaire sur RAWG et Steam quand il est configuré.
 *
 * Authentification : le site demande lui-même un jeton d'accès à Twitch (valable environ 2 mois),
 * le garde dans les réglages et le renouvelle quand il expire ou est refusé.
 * Les requêtes IGDB sont des POST écrits dans le langage "Apicalypse".
 */
declare(strict_types=1);

namespace Cantine\Services\Api;

defined('CANTINE') || exit;

use Cantine\Services\ApiException;
use Cantine\Services\HttpClient;
use Cantine\Support\Settings;

final class Igdb implements SourceInterface
{
    private const API = 'https://api.igdb.com/v4/games';
    private const TOKEN_URL = 'https://id.twitch.tv/oauth2/token';
    private const IMAGES = 'https://images.igdb.com/igdb/image/upload/';
    private const FIELDS = 'name,first_release_date,cover.image_id,platforms.name,platforms.abbreviation,genres.name,'
        . 'involved_companies.company.name,involved_companies.developer,summary,url,slug';

    /** Traduction des genres IGDB (en anglais). */
    private const GENRES = [
        'Adventure' => 'Aventure', 'Role-playing (RPG)' => 'RPG', 'Shooter' => 'Tir', 'Platform' => 'Plateforme',
        'Puzzle' => 'Réflexion', 'Racing' => 'Course', 'Real Time Strategy (RTS)' => 'Stratégie', 'Strategy' => 'Stratégie',
        'Turn-based strategy (TBS)' => 'Stratégie', 'Tactical' => 'Tactique', 'Simulator' => 'Simulation', 'Sport' => 'Sport',
        'Fighting' => 'Combat', "Hack and slash/Beat 'em up" => 'Beat them all', 'Indie' => 'Indépendant', 'Arcade' => 'Arcade',
        'Visual Novel' => 'Visual novel', 'Card & Board Game' => 'Jeu de société', 'Point-and-click' => 'Point & click',
        'Music' => 'Musique', 'Quiz/Trivia' => 'Quiz', 'Pinball' => 'Flipper', 'MOBA' => 'MOBA',
    ];

    public function __construct(
        private HttpClient $http,
        private Settings $settings,
        private string $clientId,
        private string $clientSecret,
    ) {
    }

    public function name(): string
    {
        return 'igdb';
    }

    public function isAvailable(): bool
    {
        return trim($this->clientId) !== '' && trim($this->clientSecret) !== '';
    }

    // ---- Jeton d'accès Twitch ----

    private function token(bool $renew = false): string
    {
        $token = $this->settings->get('igdb_token');
        $expires = (int) $this->settings->get('igdb_token_expires', '0');
        if (!$renew && $token !== '' && $expires > time() + 3600) {
            return $token;
        }
        try {
            $data = $this->http->postJson(self::TOKEN_URL . '?' . http_build_query([
                'client_id' => trim($this->clientId),
                'client_secret' => trim($this->clientSecret),
                'grant_type' => 'client_credentials',
            ]), '', [], 0);
        } catch (ApiException $e) {
            throw new ApiException(in_array($e->getCode(), [400, 401, 403], true) ? t('api.bad_key') : $e->getMessage(), $e->getCode());
        }
        $token = Helpers::str($data['access_token'] ?? null, 200);
        if ($token === null || !preg_match('/^[A-Za-z0-9]+$/', $token)) {
            throw new ApiException(t('api.bad_key'));
        }
        $this->settings->set('igdb_token', $token);
        $this->settings->set('igdb_token_expires', (string) (time() + max(3600, (int) ($data['expires_in'] ?? 0))));
        return $token;
    }

    /** Requête IGDB ; en cas de jeton refusé, on en redemande un et on réessaie une fois. */
    private function query(string $body, int $ttl): array
    {
        if (!$this->isAvailable()) {
            throw new ApiException(t('api.no_key'));
        }
        $headers = static fn (string $token, string $id): array => ['Client-ID' => trim($id), 'Authorization' => 'Bearer ' . $token];
        try {
            return $this->http->postJson(self::API, $body, $headers($this->token(), $this->clientId), $ttl);
        } catch (ApiException $e) {
            if ($e->getCode() !== 401) {
                throw $e;
            }
            return $this->http->postJson(self::API, $body, $headers($this->token(true), $this->clientId), $ttl);
        }
    }

    /** Échappe une chaîne pour Apicalypse ("..."). */
    private static function quote(string $text): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], mb_substr($text, 0, 200)) . '"';
    }

    // ---- Recherche et fiche ----

    public function search(string $query, array $options = []): array
    {
        $data = $this->query('search ' . self::quote($query) . '; fields ' . self::FIELDS . '; limit 20;', HttpClient::TTL_SEARCH);
        $results = [];
        foreach ($data as $game) {
            $parsed = is_array($game) ? self::parse($game) : null;
            if ($parsed === null) {
                continue;
            }
            $results[] = [
                'source' => 'igdb',
                'external_id' => $parsed['external_id'],
                'title' => $parsed['title'],
                'year' => $parsed['year'],
                'image_url' => $parsed['cover_id'] !== null ? self::IMAGES . 't_cover_small/' . $parsed['cover_id'] . '.jpg' : null,
                'info' => implode(', ', array_slice($parsed['platforms_short'], 0, 5)) ?: null,
            ];
        }
        return $results;
    }

    public function fetch(string $externalId): array
    {
        if (!ctype_digit($externalId)) {
            throw new ApiException(t('api.not_found'));
        }
        $data = $this->query('fields ' . self::FIELDS . '; where id = ' . (int) $externalId . ';', HttpClient::TTL_DETAILS);
        $game = isset($data[0]) && is_array($data[0]) ? self::parse($data[0]) : null;
        if ($game === null) {
            throw new ApiException(t('api.not_found'));
        }
        return [
            'source' => 'igdb',
            'external_id' => $externalId,
            'type' => 'jeu',
            'title' => $game['title'],
            'year' => $game['year'],
            'description' => $game['summary'],
            'image_url' => $game['cover_id'] !== null ? self::IMAGES . 't_cover_big/' . $game['cover_id'] . '.jpg' : null,
            'external_url' => $game['url'],
            'trailer_url' => null,
            'isbn' => null,
            'genres' => $game['genres'],
            'extra' => [
                'platforms' => $game['platforms'],
                'developers' => $game['developers'],
            ],
        ];
    }

    /** Vérifie les identifiants (bouton "Tester" de l'admin) : obtient un jeton neuf puis fait une petite requête. */
    public function testKey(): void
    {
        $this->token(true);
        $this->query('fields name; limit 1;', 0);
    }

    /** Lit un jeu IGDB en vérifiant chaque champ. */
    private static function parse(array $game): ?array
    {
        $title = Helpers::str($game['name'] ?? null);
        if ($title === null || !isset($game['id']) || !is_numeric($game['id'])) {
            return null;
        }
        $coverId = Helpers::str($game['cover']['image_id'] ?? null, 50);
        $developers = [];
        foreach (Helpers::arr($game['involved_companies'] ?? null) as $company) {
            if (is_array($company) && !empty($company['developer'])) {
                $name = Helpers::str($company['company']['name'] ?? null, 100);
                if ($name !== null) {
                    $developers[] = $name;
                }
            }
        }
        $platformsShort = [];
        foreach (Helpers::arr($game['platforms'] ?? null) as $platform) {
            $short = is_array($platform) ? Helpers::str($platform['abbreviation'] ?? $platform['name'] ?? null, 50) : null;
            if ($short !== null) {
                $platformsShort[] = $short;
            }
        }
        $slug = Helpers::str($game['slug'] ?? null, 200);
        $timestamp = Helpers::int($game['first_release_date'] ?? null);

        return [
            'external_id' => (string) (int) $game['id'],
            'title' => $title,
            'year' => $timestamp !== null ? (int) gmdate('Y', $timestamp) : null,
            'summary' => Helpers::str($game['summary'] ?? null, 2000),
            'cover_id' => $coverId !== null && preg_match('/^[A-Za-z0-9_]+$/', $coverId) ? $coverId : null,
            'url' => Helpers::httpsUrl($game['url'] ?? null)
                ?? ($slug !== null && preg_match('/^[a-z0-9-]+$/', $slug) ? 'https://www.igdb.com/games/' . $slug : null),
            'genres' => array_map(static fn (string $g) => self::GENRES[$g] ?? $g, Helpers::names($game['genres'] ?? null, 'name', 4)),
            'platforms' => Helpers::names($game['platforms'] ?? null, 'name', 12),
            'platforms_short' => $platformsShort,
            'developers' => array_slice(array_values(array_unique($developers)), 0, 3),
        ];
    }
}
