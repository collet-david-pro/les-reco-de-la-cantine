<?php
/**
 * Open Library (livres). Aucune clé nécessaire.
 * L'identifiant externe est celui de l'œuvre ("OL45804W").
 */
declare(strict_types=1);

namespace Cantine\Services\Api;

defined('CANTINE') || exit;

use Cantine\Services\ApiException;
use Cantine\Services\HttpClient;

final class OpenLibrary implements SourceInterface
{
    private const FIELDS = 'key,title,author_name,first_publish_year,isbn,cover_i,number_of_pages_median,subject';

    public function __construct(private HttpClient $http)
    {
    }

    public function name(): string
    {
        return 'openlibrary';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function search(string $query, array $options = []): array
    {
        $data = $this->http->getJson('https://openlibrary.org/search.json?' . http_build_query([
            'q' => $query,
            'fields' => self::FIELDS,
            'limit' => 20,
        ]), [], HttpClient::TTL_SEARCH);

        $results = [];
        foreach (Helpers::arr($data['docs'] ?? null) as $doc) {
            $book = is_array($doc) ? self::parseDoc($doc) : null;
            if ($book === null) {
                continue;
            }
            $results[] = [
                'source' => 'openlibrary',
                'external_id' => $book['id'],
                'title' => $book['title'],
                'year' => $book['year'],
                'image_url' => $book['cover'] !== null ? 'https://covers.openlibrary.org/b/id/' . $book['cover'] . '-M.jpg' : null,
                'info' => $book['authors'] ? implode(', ', $book['authors']) : null,
            ];
        }
        return $results;
    }

    public function fetch(string $externalId): array
    {
        if (!preg_match('/^OL\d+W$/', $externalId)) {
            throw new ApiException(t('api.not_found'));
        }
        $data = $this->http->getJson('https://openlibrary.org/search.json?' . http_build_query([
            'q' => 'key:"/works/' . $externalId . '"',
            'fields' => self::FIELDS,
            'limit' => 1,
        ]), [], HttpClient::TTL_DETAILS);

        $doc = Helpers::arr($data['docs'] ?? null)[0] ?? null;
        $book = is_array($doc) ? self::parseDoc($doc) : null;
        if ($book === null || $book['id'] !== $externalId) {
            throw new ApiException(t('api.not_found'));
        }

        // Résumé : présent seulement sur la fiche de l'œuvre (appel facultatif).
        $description = null;
        try {
            $work = $this->http->getJson('https://openlibrary.org/works/' . $externalId . '.json', [], HttpClient::TTL_DETAILS);
            $desc = $work['description'] ?? null;
            $description = Helpers::str(is_array($desc) ? ($desc['value'] ?? null) : $desc, 2000);
        } catch (ApiException) {
            // pas grave : la fiche reste utilisable sans résumé
        }

        return [
            'source' => 'openlibrary',
            'external_id' => $externalId,
            'type' => 'livre',
            'title' => $book['title'],
            'year' => $book['year'],
            'description' => $description,
            'image_url' => $book['cover'] !== null ? 'https://covers.openlibrary.org/b/id/' . $book['cover'] . '-L.jpg' : null,
            'external_url' => 'https://openlibrary.org/works/' . $externalId,
            'trailer_url' => null,
            'isbn' => $book['isbn'],
            'genres' => $book['subjects'],
            'extra' => [
                'author' => $book['authors'],
                'pages' => $book['pages'],
            ],
        ];
    }

    /** Lit un document de résultat de recherche Open Library. */
    private static function parseDoc(array $doc): ?array
    {
        $key = Helpers::str($doc['key'] ?? null);
        $title = Helpers::str($doc['title'] ?? null);
        if ($key === null || $title === null || !preg_match('~^/works/(OL\d+W)$~', $key, $m)) {
            return null;
        }
        return [
            'id' => $m[1],
            'title' => $title,
            'year' => Helpers::year($doc['first_publish_year'] ?? null),
            'authors' => array_slice(array_values(array_filter(array_map(
                static fn ($a) => Helpers::str($a, 100),
                Helpers::arr($doc['author_name'] ?? null)
            ))), 0, 3),
            'cover' => Helpers::int($doc['cover_i'] ?? null),
            'pages' => Helpers::int($doc['number_of_pages_median'] ?? null),
            'isbn' => self::pickIsbn(Helpers::arr($doc['isbn'] ?? null)),
            'subjects' => self::pickSubjects(Helpers::arr($doc['subject'] ?? null)),
        ];
    }

    /** ISBN-13 de préférence (978/979), sinon le premier ISBN-10 valide. */
    private static function pickIsbn(array $isbns): ?string
    {
        $isbn10 = null;
        foreach ($isbns as $isbn) {
            $isbn = is_string($isbn) ? preg_replace('/[^0-9X]/', '', strtoupper($isbn)) : '';
            if (preg_match('/^97[89]\d{10}$/', (string) $isbn)) {
                return $isbn;
            }
            if ($isbn10 === null && preg_match('/^\d{9}[\dX]$/', (string) $isbn)) {
                $isbn10 = $isbn;
            }
        }
        return $isbn10;
    }

    /**
     * Les "subjects" d'Open Library sont nombreux et en vrac : on ne garde que des genres courants,
     * traduits en français, 3 au maximum.
     */
    private static function pickSubjects(array $subjects): array
    {
        static $map = [
            'fiction' => 'Roman', 'novel' => 'Roman', 'romans' => 'Roman',
            'science fiction' => 'Science-fiction', 'science-fiction' => 'Science-fiction',
            'fantasy' => 'Fantasy', 'fantastique' => 'Fantastique',
            'mystery' => 'Policier', 'detective' => 'Policier', 'crime' => 'Policier', 'roman policier' => 'Policier',
            'thriller' => 'Thriller', 'suspense' => 'Thriller',
            'horror' => 'Horreur', 'romance' => 'Romance', 'love stories' => 'Romance',
            'history' => 'Histoire', 'historical fiction' => 'Roman historique',
            'biography' => 'Biographie', 'autobiography' => 'Biographie',
            'comics' => 'Bande dessinée', 'bandes dessinées' => 'Bande dessinée', 'graphic novels' => 'Bande dessinée',
            'manga' => 'Manga', 'poetry' => 'Poésie', 'poésie' => 'Poésie',
            'philosophy' => 'Philosophie', 'essays' => 'Essai', 'humor' => 'Humour', 'humour' => 'Humour',
            'juvenile fiction' => 'Jeunesse', "children's fiction" => 'Jeunesse', 'young adult fiction' => 'Jeunesse',
            'adventure' => 'Aventure', 'adventure stories' => 'Aventure', 'dystopias' => 'Dystopie',
            'cooking' => 'Cuisine', 'travel' => 'Voyage', 'science' => 'Sciences', 'psychology' => 'Psychologie',
        ];
        $genres = [];
        foreach ($subjects as $subject) {
            $key = is_string($subject) ? mb_strtolower(trim($subject)) : '';
            if (isset($map[$key]) && !in_array($map[$key], $genres, true)) {
                $genres[] = $map[$key];
            }
            if (count($genres) >= 3) {
                break;
            }
        }
        return $genres;
    }
}
