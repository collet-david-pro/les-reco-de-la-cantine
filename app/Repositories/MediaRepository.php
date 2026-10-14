<?php
/**
 * Requêtes sur les contenus (table media) et leurs genres.
 *
 * Rappel : l'absence de ligne user_media pour un membre vaut "À découvrir".
 * Statut effectif = COALESCE(um.status, 'a_decouvrir').
 */
declare(strict_types=1);

namespace Cantine\Repositories;

defined('CANTINE') || exit;

use Cantine\Support\Db;
use Cantine\Support\Text;

final class MediaRepository
{
    public const PER_PAGE = 24;

    public const SORTS = ['recent', 'note', 'populaire', 'titre'];
    public const TABS = ['a_decouvrir', 'en_cours', 'vu', 'pas_pour_moi', 'tout'];

    /** Agrégat des avis du groupe par contenu (nombre de "vu", note moyenne). */
    private const STATS_JOIN = "LEFT JOIN (
            SELECT media_id, COUNT(*) AS seen_count, AVG(rating) AS avg_rating, COUNT(rating) AS rating_count
            FROM {user_media} WHERE status = 'vu' GROUP BY media_id
        ) s ON s.media_id = m.id";

    public function __construct(private Db $db)
    {
    }

    public function find(int $id): ?array
    {
        $media = $this->db->one(
            'SELECT m.*, u.display_name AS added_by_name, u.avatar_color AS added_by_color
             FROM {media} m LEFT JOIN {users} u ON u.id = m.added_by WHERE m.id = ?',
            [$id]
        );
        if ($media) {
            $media['extra'] = self::decodeExtra($media['extra']);
        }
        return $media;
    }

    public function findByExternal(string $type, string $source, string $externalId): ?array
    {
        return $this->db->one(
            'SELECT m.*, u.display_name AS added_by_name FROM {media} m LEFT JOIN {users} u ON u.id = m.added_by
             WHERE m.type = ? AND m.source = ? AND m.external_id = ?',
            [$type, $source, $externalId]
        );
    }

    /** Contenus de même type au titre normalisé identique (détection de doublons pour l'ajout manuel). */
    public function findSimilar(string $type, string $title, ?int $year): array
    {
        $rows = $this->db->all(
            'SELECT m.*, u.display_name AS added_by_name FROM {media} m LEFT JOIN {users} u ON u.id = m.added_by
             WHERE m.type = ? AND m.normalized_title = ? ORDER BY m.created_at',
            [$type, Text::normalize($title)]
        );
        if ($year !== null) {
            // Une année différente indique un autre contenu (remake...) ; une année absente reste un doublon possible.
            $rows = array_values(array_filter($rows, static fn ($r) => $r['year'] === null || (int) $r['year'] === $year));
        }
        return $rows;
    }

    /** Recherche dans les contenus déjà présents (titre normalisé, préfixe ou contenu). */
    public function searchLocal(string $query, ?string $type = null, int $limit = 5): array
    {
        $normalized = Text::normalize($query);
        if ($normalized === '') {
            return [];
        }
        $params = ['contains' => '%' . $normalized . '%', 'prefix' => $normalized . '%', 'limit' => $limit];
        $typeSql = '';
        if ($type !== null) {
            $typeSql = ' AND m.type = :type';
            $params['type'] = $type;
        }
        return $this->db->all(
            "SELECT m.id, m.type, m.title, m.year, m.image_url, m.created_at, u.display_name AS added_by_name
             FROM {media} m LEFT JOIN {users} u ON u.id = m.added_by
             WHERE m.normalized_title LIKE :contains $typeSql
             ORDER BY CASE WHEN m.normalized_title LIKE :prefix THEN 0 ELSE 1 END, m.normalized_title
             LIMIT :limit",
            $params
        );
    }

    /**
     * Crée un contenu, ses genres, et marque l'ajouteur "vu" (le tout dans une transaction).
     * $data : type, source, external_id, title, year, description, image_url, external_url, trailer_url, isbn, extra (array)
     */
    public function create(array $data, array $genres, ?int $userId, bool $isDemo = false): int
    {
        return $this->db->transaction(function () use ($data, $genres, $userId, $isDemo): int {
            $now = Db::now();
            $id = $this->db->insert('media', [
                'type' => $data['type'],
                'source' => $data['source'],
                'external_id' => $data['external_id'] ?? null,
                'title' => mb_substr($data['title'], 0, 255),
                'normalized_title' => Text::normalize($data['title']),
                'year' => $data['year'] ?? null,
                'description' => $data['description'] ?? null,
                'image_url' => $data['image_url'] ?? null,
                'external_url' => $data['external_url'] ?? null,
                'trailer_url' => $data['trailer_url'] ?? null,
                'isbn' => $data['isbn'] ?? null,
                'extra' => self::encodeExtra($data['extra'] ?? []),
                'added_by' => $userId,
                'is_demo' => $isDemo ? 1 : 0,
                'created_at' => $data['created_at'] ?? $now,
                'updated_at' => $now,
            ]);
            $this->setGenres($id, $genres);
            if ($userId !== null) {
                (new UserMediaRepository($this->db))->markSeen($userId, $id);
            }
            return $id;
        });
    }

    public function update(int $id, array $data): void
    {
        if (isset($data['title'])) {
            $data['normalized_title'] = Text::normalize($data['title']);
        }
        if (array_key_exists('extra', $data)) {
            $data['extra'] = self::encodeExtra($data['extra']);
        }
        $data['updated_at'] = Db::now();
        $this->db->update('media', $data, ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('media', ['id' => $id]);
    }

    // ---- Genres ----

    /** Remplace les genres d'un contenu (créés au besoin, au maximum 6). */
    public function setGenres(int $mediaId, array $names): void
    {
        $this->db->transaction(function () use ($mediaId, $names): void {
            $this->db->delete('media_genres', ['media_id' => $mediaId]);
            $seen = [];
            foreach ($names as $name) {
                $name = trim(mb_substr((string) $name, 0, 100));
                $key = mb_strtolower($name);
                if ($name === '' || isset($seen[$key]) || count($seen) >= 6) {
                    continue;
                }
                $seen[$key] = true;
                $genreId = $this->db->value('SELECT id FROM {genres} WHERE name = ?', [$name]);
                if (!$genreId) {
                    $genreId = $this->db->insert('genres', ['name' => $name]);
                }
                $this->db->insert('media_genres', ['media_id' => $mediaId, 'genre_id' => (int) $genreId]);
            }
        });
    }

    public function genres(int $mediaId): array
    {
        return $this->db->all(
            'SELECT g.id, g.name FROM {genres} g JOIN {media_genres} mg ON mg.genre_id = g.id WHERE mg.media_id = ? ORDER BY g.name',
            [$mediaId]
        );
    }

    /** Genres utilisés au moins une fois (pour le filtre), éventuellement limités à un type. */
    public function usedGenres(?string $type = null): array
    {
        if ($type !== null) {
            return $this->db->all(
                'SELECT DISTINCT g.id, g.name FROM {genres} g
                 JOIN {media_genres} mg ON mg.genre_id = g.id JOIN {media} m ON m.id = mg.media_id
                 WHERE m.type = ? ORDER BY g.name',
                [$type]
            );
        }
        return $this->db->all(
            'SELECT DISTINCT g.id, g.name FROM {genres} g JOIN {media_genres} mg ON mg.genre_id = g.id ORDER BY g.name'
        );
    }

    /** Supprime les genres qui ne sont plus utilisés. */
    public function purgeUnusedGenres(): void
    {
        $this->db->run('DELETE FROM {genres} WHERE id NOT IN (SELECT genre_id FROM {media_genres})');
    }

    /** Membres ayant recommandé au moins un contenu (pour le filtre "recommandé par"). */
    public function adders(): array
    {
        return $this->db->all(
            'SELECT DISTINCT u.id, u.display_name FROM {users} u JOIN {media} m ON m.added_by = u.id ORDER BY u.display_name'
        );
    }

    // ---- Listes ----

    /**
     * Liste paginée pour un membre, avec son statut, la moyenne et la popularité, en une seule requête.
     * $filters : tab, type, added_by, genre, q ; $sort : voir SORTS.
     * @return array{items: list<array>, total: int}
     */
    public function listForUser(int $userId, array $filters, string $sort, int $page, int $perPage = self::PER_PAGE): array
    {
        [$where, $params] = $this->buildFilters($userId, $filters);

        $total = (int) $this->db->value(
            "SELECT COUNT(*) FROM {media} m
             LEFT JOIN {user_media} um ON um.media_id = m.id AND um.user_id = :me
             WHERE $where",
            $params
        );

        $order = match ($sort) {
            'note' => 's.avg_rating IS NULL, s.avg_rating DESC, s.rating_count DESC, m.created_at DESC',
            'populaire' => 'COALESCE(s.seen_count, 0) DESC, m.created_at DESC',
            'titre' => 'm.normalized_title ASC, m.id ASC',
            default => 'm.created_at DESC, m.id DESC',
        };

        $params['limit'] = $perPage;
        $params['offset'] = max(0, ($page - 1) * $perPage);
        $items = $this->db->all(
            "SELECT m.id, m.type, m.title, m.year, m.image_url, m.created_at, m.added_by,
                    u.display_name AS added_by_name,
                    COALESCE(um.status, 'a_decouvrir') AS my_status, um.rating AS my_rating,
                    COALESCE(s.seen_count, 0) AS seen_count, s.avg_rating, COALESCE(s.rating_count, 0) AS rating_count
             FROM {media} m
             LEFT JOIN {users} u ON u.id = m.added_by
             LEFT JOIN {user_media} um ON um.media_id = m.id AND um.user_id = :me
             " . self::STATS_JOIN . "
             WHERE $where
             ORDER BY $order
             LIMIT :limit OFFSET :offset",
            $params
        );

        return ['items' => $items, 'total' => $total];
    }

    /** Conditions SQL communes à la liste et au comptage. */
    private function buildFilters(int $userId, array $filters): array
    {
        $where = [];
        $params = ['me' => $userId];

        $tab = $filters['tab'] ?? 'a_decouvrir';
        if ($tab === 'tout') {
            // "Pas pour moi" n'apparaît que dans son propre onglet.
            $where[] = "COALESCE(um.status, 'a_decouvrir') <> 'pas_pour_moi'";
        } else {
            $where[] = "COALESCE(um.status, 'a_decouvrir') = :status";
            $params['status'] = $tab;
        }
        if (!empty($filters['type'])) {
            $where[] = 'm.type = :type';
            $params['type'] = $filters['type'];
        }
        if (!empty($filters['added_by'])) {
            $where[] = 'm.added_by = :added_by';
            $params['added_by'] = (int) $filters['added_by'];
        }
        if (!empty($filters['genre'])) {
            $where[] = 'EXISTS (SELECT 1 FROM {media_genres} mg WHERE mg.media_id = m.id AND mg.genre_id = :genre)';
            $params['genre'] = (int) $filters['genre'];
        }
        if (!empty($filters['q'])) {
            $normalized = Text::normalize((string) $filters['q']);
            if ($normalized !== '') {
                $where[] = 'm.normalized_title LIKE :q';
                $params['q'] = '%' . $normalized . '%';
            }
        }
        return [implode(' AND ', $where), $params];
    }

    /** Nombre de contenus "à découvrir" par onglet, pour les compteurs des onglets. */
    public function tabCounts(int $userId): array
    {
        $rows = $this->db->all(
            "SELECT COALESCE(um.status, 'a_decouvrir') AS status, COUNT(*) AS n
             FROM {media} m LEFT JOIN {user_media} um ON um.media_id = m.id AND um.user_id = ?
             GROUP BY COALESCE(um.status, 'a_decouvrir')",
            [$userId]
        );
        $counts = array_fill_keys(self::TABS, 0);
        foreach ($rows as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }
        $counts['tout'] = array_sum($counts) - $counts['pas_pour_moi'];
        return $counts;
    }

    /** Recommandations ajoutées depuis la visite précédente, par d'autres membres. */
    public function countNew(int $userId, ?string $previousSeenAt): int
    {
        if ($previousSeenAt === null) {
            return 0;
        }
        return (int) $this->db->value(
            'SELECT COUNT(*) FROM {media} WHERE created_at > ? AND (added_by IS NULL OR added_by <> ?)',
            [$previousSeenAt, $userId]
        );
    }

    /** Recommandations d'un membre (page de profil). */
    public function addedBy(int $userId, int $viewerId, int $page, int $perPage = self::PER_PAGE): array
    {
        $total = (int) $this->db->value('SELECT COUNT(*) FROM {media} WHERE added_by = ?', [$userId]);
        $items = $this->db->all(
            "SELECT m.id, m.type, m.title, m.year, m.image_url, m.created_at, m.added_by,
                    COALESCE(um.status, 'a_decouvrir') AS my_status, um.rating AS my_rating,
                    COALESCE(s.seen_count, 0) AS seen_count, s.avg_rating, COALESCE(s.rating_count, 0) AS rating_count
             FROM {media} m
             LEFT JOIN {user_media} um ON um.media_id = m.id AND um.user_id = :viewer
             " . self::STATS_JOIN . "
             WHERE m.added_by = :author
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT :limit OFFSET :offset",
            ['viewer' => $viewerId, 'author' => $userId, 'limit' => $perPage, 'offset' => max(0, ($page - 1) * $perPage)]
        );
        return ['items' => $items, 'total' => $total];
    }

    // ---- Tirage au sort ----

    /** Tire au hasard un contenu "À découvrir" (COUNT puis OFFSET aléatoire, sans ORDER BY RAND()). */
    public function randomToDiscover(int $userId, ?string $type, ?int $genre, ?int $excludeId = null): ?array
    {
        $filters = ['tab' => 'a_decouvrir', 'type' => $type, 'genre' => $genre];
        [$where, $params] = $this->buildFilters($userId, $filters);
        if ($excludeId !== null) {
            $where .= ' AND m.id <> :exclude';
            $params['exclude'] = $excludeId;
        }
        $count = (int) $this->db->value(
            "SELECT COUNT(*) FROM {media} m LEFT JOIN {user_media} um ON um.media_id = m.id AND um.user_id = :me WHERE $where",
            $params
        );
        if ($count === 0) {
            return null;
        }
        $params['offset'] = random_int(0, $count - 1);
        $id = (int) $this->db->value(
            "SELECT m.id FROM {media} m LEFT JOIN {user_media} um ON um.media_id = m.id AND um.user_id = :me
             WHERE $where ORDER BY m.id LIMIT 1 OFFSET :offset",
            $params
        );
        return $this->find($id);
    }

    // ---- Fiche ----

    /** Statut et note de chaque membre ayant un avis sur ce contenu. */
    public function memberOpinions(int $mediaId): array
    {
        return $this->db->all(
            "SELECT u.id, u.display_name, u.avatar_color, um.status, um.rating, um.updated_at
             FROM {user_media} um JOIN {users} u ON u.id = um.user_id
             WHERE um.media_id = ? AND um.status <> 'a_decouvrir'
             ORDER BY CASE um.status WHEN 'vu' THEN 0 WHEN 'en_cours' THEN 1 ELSE 2 END, um.rating DESC, u.display_name",
            [$mediaId]
        );
    }

    public function stats(int $mediaId): array
    {
        $row = $this->db->one(
            "SELECT COUNT(*) AS seen_count, AVG(rating) AS avg_rating, COUNT(rating) AS rating_count
             FROM {user_media} WHERE media_id = ? AND status = 'vu'",
            [$mediaId]
        );
        return [
            'seen_count' => (int) ($row['seen_count'] ?? 0),
            'avg_rating' => $row['avg_rating'] !== null ? (float) $row['avg_rating'] : null,
            'rating_count' => (int) ($row['rating_count'] ?? 0),
        ];
    }

    /** Ce que la suppression ferait perdre aux AUTRES membres (statuts et commentaires). */
    public function othersActivity(int $mediaId, int $exceptUserId): array
    {
        return [
            'statuses' => (int) $this->db->value(
                "SELECT COUNT(*) FROM {user_media} WHERE media_id = ? AND user_id <> ? AND status <> 'a_decouvrir'",
                [$mediaId, $exceptUserId]
            ),
            'comments' => (int) $this->db->value(
                'SELECT COUNT(*) FROM {comments} WHERE media_id = ? AND user_id <> ?',
                [$mediaId, $exceptUserId]
            ),
        ];
    }

    /**
     * Fusionne le contenu $sourceId dans $targetId : statuts, notes, commentaires et genres sont reportés,
     * puis le doublon est supprimé. En cas de conflit de statut, celui de la cible est conservé.
     */
    public function merge(int $sourceId, int $targetId): void
    {
        $this->db->transaction(function () use ($sourceId, $targetId): void {
            $this->db->run(
                'UPDATE {user_media} SET media_id = :target WHERE media_id = :source
                 AND user_id NOT IN (SELECT user_id FROM (SELECT user_id FROM {user_media} WHERE media_id = :target2) t)',
                ['target' => $targetId, 'source' => $sourceId, 'target2' => $targetId]
            );
            $this->db->run('UPDATE {comments} SET media_id = ? WHERE media_id = ?', [$targetId, $sourceId]);
            $this->db->run(
                'INSERT INTO {media_genres} (media_id, genre_id)
                 SELECT :target, genre_id FROM {media_genres} WHERE media_id = :source
                 AND genre_id NOT IN (SELECT genre_id FROM (SELECT genre_id FROM {media_genres} WHERE media_id = :target2) t)',
                ['target' => $targetId, 'source' => $sourceId, 'target2' => $targetId]
            );
            $this->delete($sourceId);
        });
    }

    // ---- Infos pratiques (JSON) ----

    public static function decodeExtra(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    public static function encodeExtra(array $extra): ?string
    {
        $extra = array_filter($extra, static fn ($v) => $v !== null && $v !== '' && $v !== []);
        return $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }
}
