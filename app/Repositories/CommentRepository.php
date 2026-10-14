<?php
/**
 * Commentaires sous les contenus (texte brut, 1000 caractères max, mention spoiler facultative).
 */
declare(strict_types=1);

namespace Cantine\Repositories;

defined('CANTINE') || exit;

use Cantine\Support\Db;

final class CommentRepository
{
    public const MAX_LENGTH = 1000;

    public function __construct(private Db $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM {comments} WHERE id = ?', [$id]);
    }

    public function forMedia(int $mediaId): array
    {
        return $this->db->all(
            'SELECT c.*, u.display_name, u.avatar_color FROM {comments} c JOIN {users} u ON u.id = c.user_id
             WHERE c.media_id = ? ORDER BY c.created_at, c.id',
            [$mediaId]
        );
    }

    public function create(int $mediaId, int $userId, string $body, bool $spoiler, bool $isDemo = false, ?string $createdAt = null): int
    {
        return $this->db->insert('comments', [
            'media_id' => $mediaId,
            'user_id' => $userId,
            'body' => $body,
            'is_spoiler' => $spoiler ? 1 : 0,
            'is_demo' => $isDemo ? 1 : 0,
            'created_at' => $createdAt ?? Db::now(),
            'updated_at' => null,
        ]);
    }

    public function update(int $id, string $body, bool $spoiler): void
    {
        $this->db->update('comments', ['body' => $body, 'is_spoiler' => $spoiler ? 1 : 0, 'updated_at' => Db::now()], ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('comments', ['id' => $id]);
    }

    /** Nettoie et valide le texte. Renvoie null s'il est vide. */
    public static function clean(string $body): ?string
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));
        $body = (string) preg_replace("/\n{3,}/", "\n\n", $body);
        if ($body === '') {
            return null;
        }
        return mb_substr($body, 0, self::MAX_LENGTH);
    }
}
