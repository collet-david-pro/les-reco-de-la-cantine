<?php
/**
 * Interface commune des connecteurs d'API (TMDB, Open Library, RAWG, Deezer, YouTube).
 *
 * search() renvoie des résultats normalisés :
 *   ['source', 'external_id', 'title', 'year', 'image_url', 'info']   (info = détail distinctif : auteur, artiste...)
 * fetch() renvoie une fiche complète normalisée :
 *   ['source', 'external_id', 'type', 'title', 'year', 'description', 'image_url', 'external_url',
 *    'trailer_url', 'isbn', 'genres' => [...], 'extra' => [...], 'watch_providers' => ?array]
 */
declare(strict_types=1);

namespace Cantine\Services\Api;

defined('CANTINE') || exit;

interface SourceInterface
{
    /** Code de la source, stocké dans media.source. */
    public function name(): string;

    /** Faux si la source n'est pas utilisable (clé d'API manquante). */
    public function isAvailable(): bool;

    /** @return list<array<string, mixed>> */
    public function search(string $query, array $options = []): array;

    /** @return array<string, mixed> */
    public function fetch(string $externalId): array;
}
