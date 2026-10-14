<?php
/**
 * Fil "Quoi de neuf" : nouvelles recommandations, notes et commentaires du groupe, triés par date.
 *
 * Trois requêtes simples (une par type d'événement) fusionnées en PHP : portable MySQL/SQLite
 * et suffisant à l'échelle d'un groupe d'amis. Les passages en "pas pour moi" ne sont jamais diffusés.
 */
declare(strict_types=1);

namespace Cantine\Repositories;

defined('CANTINE') || exit;

use Cantine\Support\Db;

final class FeedRepository
{
    public const PER_PAGE = 30;

    public function __construct(private Db $db)
    {
    }

    /** @return array{events: list<array>, has_more: bool} */
    public function page(int $page): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * self::PER_PAGE;
        $limit = $offset + self::PER_PAGE + 1; // chaque source doit fournir assez de lignes pour cette page

        $media = $this->db->all(
            "SELECT 'media' AS kind, m.id AS media_id, m.type, m.title, m.image_url, m.created_at AS at,
                    u.id AS user_id, u.display_name, u.avatar_color
             FROM {media} m LEFT JOIN {users} u ON u.id = m.added_by
             ORDER BY m.created_at DESC, m.id DESC LIMIT :limit",
            ['limit' => $limit]
        );
        $ratings = $this->db->all(
            "SELECT 'rating' AS kind, m.id AS media_id, m.type, m.title, m.image_url, um.updated_at AS at, um.rating,
                    u.id AS user_id, u.display_name, u.avatar_color
             FROM {user_media} um JOIN {media} m ON m.id = um.media_id JOIN {users} u ON u.id = um.user_id
             WHERE um.status = 'vu' AND um.rating IS NOT NULL
             ORDER BY um.updated_at DESC LIMIT :limit",
            ['limit' => $limit]
        );
        $comments = $this->db->all(
            "SELECT 'comment' AS kind, m.id AS media_id, m.type, m.title, m.image_url, c.created_at AS at,
                    c.id AS comment_id, c.body, c.is_spoiler, u.id AS user_id, u.display_name, u.avatar_color
             FROM {comments} c JOIN {media} m ON m.id = c.media_id JOIN {users} u ON u.id = c.user_id
             ORDER BY c.created_at DESC, c.id DESC LIMIT :limit",
            ['limit' => $limit]
        );

        $all = array_merge($media, $ratings, $comments);
        usort($all, static fn (array $a, array $b) => strcmp((string) $b['at'], (string) $a['at']));

        $events = array_slice($all, $offset, self::PER_PAGE);
        return [
            'events' => self::groupRatings($events),
            'has_more' => count($all) > $offset + self::PER_PAGE,
        ];
    }

    /** Regroupe les notes consécutives d'un même membre : "Paul a noté 3 contenus". */
    public static function groupRatings(array $events): array
    {
        $grouped = [];
        foreach ($events as $event) {
            $lastIndex = count($grouped) - 1;
            if ($event['kind'] === 'rating' && $lastIndex >= 0
                && $grouped[$lastIndex]['kind'] === 'rating'
                && $grouped[$lastIndex]['user_id'] === $event['user_id']) {
                $grouped[$lastIndex]['items'][] = $event;
                continue;
            }
            if ($event['kind'] === 'rating') {
                $event['items'] = [$event];
            }
            $grouped[] = $event;
        }
        return $grouped;
    }
}
