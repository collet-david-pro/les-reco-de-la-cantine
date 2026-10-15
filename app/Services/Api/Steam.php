<?php
/**
 * Steam (jeux vidéo, surtout PC). API publique de la boutique, sans clé, en français.
 * Source par défaut des jeux quand ni IGDB ni RAWG ne sont configurés.
 * L'identifiant externe est le numéro d'application Steam (appid).
 */
declare(strict_types=1);

namespace Cantine\Services\Api;

defined('CANTINE') || exit;

use Cantine\Services\ApiException;
use Cantine\Services\HttpClient;

final class Steam implements SourceInterface
{
    private const STORE = 'https://store.steampowered.com';
    private const ASSETS = 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/';

    public function __construct(private HttpClient $http, private string $region = 'FR')
    {
    }

    public function name(): string
    {
        return 'steam';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    /** Jaquette verticale (format des cartes du site). */
    public static function verticalCover(int $appId): string
    {
        return self::ASSETS . $appId . '/library_600x900.jpg';
    }

    public function search(string $query, array $options = []): array
    {
        $data = $this->http->getJson(self::STORE . '/api/storesearch/?' . http_build_query([
            'term' => $query, 'l' => 'french', 'cc' => $this->region,
        ]), [], HttpClient::TTL_SEARCH);

        $results = [];
        foreach (Helpers::arr($data['items'] ?? null) as $item) {
            if (!is_array($item) || !isset($item['id']) || !is_numeric($item['id'])) {
                continue;
            }
            $title = Helpers::str($item['name'] ?? null);
            if ($title === null) {
                continue;
            }
            $results[] = [
                'source' => 'steam',
                'external_id' => (string) (int) $item['id'],
                'title' => $title,
                'year' => null,
                'image_url' => self::verticalCover((int) $item['id']),
                'info' => implode(', ', self::platforms(Helpers::arr($item['platforms'] ?? null))) ?: null,
            ];
        }
        return $results;
    }

    public function fetch(string $externalId): array
    {
        if (!ctype_digit($externalId)) {
            throw new ApiException(t('api.not_found'));
        }
        $response = $this->http->getJson(self::STORE . '/api/appdetails?' . http_build_query([
            'appids' => $externalId, 'l' => 'french', 'cc' => $this->region,
        ]), [], HttpClient::TTL_DETAILS);

        $entry = $response[$externalId] ?? null;
        $game = is_array($entry) && !empty($entry['success']) ? Helpers::arr($entry['data'] ?? null) : [];
        $title = Helpers::str($game['name'] ?? null);
        if ($title === null) {
            throw new ApiException(t('api.not_found'));
        }

        // Jaquette verticale si elle existe, sinon la bannière horizontale fournie par l'API.
        $cover = self::verticalCover((int) $externalId);
        $image = $this->http->urlExists($cover) ? $cover : Helpers::httpsUrl($game['header_image'] ?? null);

        $description = Helpers::str($game['short_description'] ?? null, 4000);
        if ($description !== null) {
            $description = Helpers::str(html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 2000);
        }

        return [
            'source' => 'steam',
            'external_id' => $externalId,
            'type' => 'jeu',
            'title' => $title,
            'year' => self::year($game['release_date']['date'] ?? null),
            'description' => $description,
            'image_url' => $image,
            'external_url' => self::STORE . '/app/' . $externalId . '/',
            'trailer_url' => null,
            'isbn' => null,
            'genres' => Helpers::names($game['genres'] ?? null, 'description', 4),
            'extra' => [
                'platforms' => self::platforms(Helpers::arr($game['platforms'] ?? null)),
                'developers' => array_slice(array_values(array_filter(array_map(
                    static fn ($d) => Helpers::str($d, 100),
                    Helpers::arr($game['developers'] ?? null)
                ))), 0, 3),
            ],
        ];
    }

    /** Date Steam localisée ("24 févr. 2017", "Feb 24, 2017"...) : on garde l'année. */
    private static function year(mixed $date): ?int
    {
        return is_string($date) && preg_match('/\b(19|20)\d{2}\b/', $date, $m) ? (int) $m[0] : null;
    }

    private static function platforms(array $platforms): array
    {
        $names = [];
        foreach (['windows' => 'PC (Windows)', 'mac' => 'Mac', 'linux' => 'Linux / Steam Deck'] as $key => $label) {
            if (!empty($platforms[$key])) {
                $names[] = $label;
            }
        }
        return $names;
    }
}
