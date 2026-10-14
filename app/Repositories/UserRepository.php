<?php
/**
 * Comptes des membres (table users).
 */
declare(strict_types=1);

namespace Cantine\Repositories;

defined('CANTINE') || exit;

use Cantine\Support\Auth;
use Cantine\Support\Db;

final class UserRepository
{
    /** Palette d'avatars : contraste AA avec du texte blanc. */
    public const AVATAR_COLORS = [
        '#B91C1C', '#C2410C', '#A16207', '#4D7C0F', '#15803D', '#047857',
        '#0F766E', '#0E7490', '#1D4ED8', '#4338CA', '#7E22CE', '#BE185D',
    ];

    public function __construct(private Db $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM {users} WHERE id = ?', [$id]);
    }

    public function findByUsername(string $username): ?array
    {
        return $this->db->one('SELECT * FROM {users} WHERE username = ?', [mb_strtolower(trim($username))]);
    }

    /** Tous les comptes avec quelques compteurs (page d'administration). */
    public function allWithCounts(): array
    {
        return $this->db->all(
            "SELECT u.*,
                (SELECT COUNT(*) FROM {media} m WHERE m.added_by = u.id) AS media_count,
                (SELECT COUNT(*) FROM {user_media} um WHERE um.user_id = u.id AND um.status = 'vu') AS seen_count
             FROM {users} u ORDER BY u.is_active DESC, u.display_name"
        );
    }

    public function activeMembers(): array
    {
        return $this->db->all('SELECT id, display_name, avatar_color FROM {users} WHERE is_active = 1 ORDER BY display_name');
    }

    public function create(string $username, string $displayName, string $password, string $role = 'member', bool $mustChange = false, bool $isDemo = false): int
    {
        $now = Db::now();
        return $this->db->insert('users', [
            'username' => mb_strtolower(trim($username)),
            'display_name' => trim($displayName),
            'password_hash' => Auth::hashPassword($password),
            'role' => $role === 'admin' ? 'admin' : 'member',
            'avatar_color' => self::AVATAR_COLORS[random_int(0, count(self::AVATAR_COLORS) - 1)],
            'is_active' => 1,
            'must_change_password' => $mustChange ? 1 : 0,
            'session_version' => 1,
            'is_demo' => $isDemo ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function update(int $id, array $data): void
    {
        $data['updated_at'] = Db::now();
        $this->db->update('users', $data, ['id' => $id]);
    }

    public function setPassword(int $id, string $password, bool $mustChange): void
    {
        $this->update($id, ['password_hash' => Auth::hashPassword($password), 'must_change_password' => $mustChange ? 1 : 0]);
    }

    /**
     * Suppression définitive : statuts, notes et commentaires supprimés (cascade),
     * recommandations conservées et affichées "Ancien membre" (added_by = NULL).
     */
    public function delete(int $id): void
    {
        $this->db->delete('users', ['id' => $id]);
    }

    public function countActiveAdmins(): int
    {
        return (int) $this->db->value("SELECT COUNT(*) FROM {users} WHERE role = 'admin' AND is_active = 1");
    }

    /** Vrai si $id est le dernier administrateur actif (à protéger). */
    public function isLastAdmin(int $id): bool
    {
        $user = $this->find($id);
        return $user !== null && $user['role'] === 'admin' && (int) $user['is_active'] === 1 && $this->countActiveAdmins() <= 1;
    }

    /** Chiffres de la page de profil. */
    public function profileStats(int $id): array
    {
        $byType = $this->db->all(
            "SELECT m.type, COUNT(*) AS n FROM {user_media} um JOIN {media} m ON m.id = um.media_id
             WHERE um.user_id = ? AND um.status = 'vu' GROUP BY m.type",
            [$id]
        );
        $ratings = $this->db->one(
            "SELECT AVG(rating) AS avg_rating, COUNT(rating) AS n FROM {user_media} WHERE user_id = ? AND status = 'vu' AND rating IS NOT NULL",
            [$id]
        );
        return [
            'seen_by_type' => array_column($byType, 'n', 'type'),
            'avg_rating' => $ratings['avg_rating'] !== null ? (float) $ratings['avg_rating'] : null,
            'rating_count' => (int) $ratings['n'],
            'added_count' => (int) $this->db->value('SELECT COUNT(*) FROM {media} WHERE added_by = ?', [$id]),
        ];
    }

    /** Dernières notes données par un membre. */
    public function recentRatings(int $id, int $limit = 12): array
    {
        return $this->db->all(
            "SELECT m.id, m.type, m.title, m.image_url, um.rating, um.updated_at
             FROM {user_media} um JOIN {media} m ON m.id = um.media_id
             WHERE um.user_id = ? AND um.status = 'vu' AND um.rating IS NOT NULL
             ORDER BY um.updated_at DESC LIMIT ?",
            [$id, $limit]
        );
    }

    // ---- Validation ----

    /** Identifiant : 3 à 30 caractères, lettres, chiffres et tirets. Renvoie un message d'erreur ou null. */
    public static function validateUsername(string $username): ?string
    {
        if (!preg_match('/^[a-z0-9-]{3,30}$/', mb_strtolower(trim($username)))) {
            return t('users.username_invalid');
        }
        return null;
    }

    public static function validatePassword(string $password, int $min = 8): ?string
    {
        if (mb_strlen($password) < $min) {
            return t('users.password_too_short', ['min' => $min]);
        }
        if (strlen($password) > 72) {
            return t('users.password_too_long'); // bcrypt ne tient compte que des 72 premiers octets
        }
        return null;
    }

    public static function validateDisplayName(string $name): ?string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 50) {
            return t('users.display_name_invalid');
        }
        return null;
    }
}
