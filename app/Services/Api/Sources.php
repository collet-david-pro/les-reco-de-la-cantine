<?php
/**
 * Fabrique des connecteurs : donne le bon connecteur pour un type de contenu, avec ses réglages.
 */
declare(strict_types=1);

namespace Cantine\Services\Api;

defined('CANTINE') || exit;

use Cantine\Services\HttpClient;
use Cantine\Support\Settings;
use Cantine\Types;

final class Sources
{
    public function __construct(private Settings $settings, private HttpClient $http)
    {
    }

    /**
     * Connecteur de recherche d'un type : le premier de la liste qui est configuré
     * (ex. jeux : IGDB, sinon RAWG, sinon Steam qui ne demande pas de clé). Null si le type n'en a aucun.
     */
    public function forType(string $type): ?SourceInterface
    {
        $first = null;
        foreach (Types::get($type)['sources'] as $name) {
            $source = $this->get($name, $type);
            if ($source !== null && $source->isAvailable()) {
                return $source;
            }
            $first ??= $source;
        }
        return $first;
    }

    public function get(string $source, string $type): ?SourceInterface
    {
        return match ($source) {
            'tmdb' => new Tmdb($this->http, $this->settings->get('tmdb_key'), $type, $this->region()),
            'openlibrary' => new OpenLibrary($this->http),
            'rawg' => new Rawg($this->http, $this->settings->get('rawg_key')),
            'igdb' => new Igdb($this->http, $this->settings, $this->settings->get('igdb_client_id'), $this->settings->get('igdb_client_secret')),
            'steam' => new Steam($this->http, $this->region()),
            'deezer' => new Deezer($this->http),
            'youtube' => new YouTube($this->http),
            default => null,
        };
    }

    public function region(): string
    {
        $region = strtoupper($this->settings->get('watch_region', 'FR'));
        return preg_match('/^[A-Z]{2}$/', $region) ? $region : 'FR';
    }
}
