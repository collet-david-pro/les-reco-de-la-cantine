<?php
/**
 * Statut personnel et note de chaque membre (table user_media).
 *
 * Conventions :
 *  - pas de ligne = "À découvrir" ; une ligne peut aussi contenir explicitement 'a_decouvrir' ;
 *  - la note (1 à 5) n'est permise qu'au statut "vu" ; si le membre quitte "vu", la note est conservée
 *    mais masquée et non comptée (elle réapparaît s'il repasse en "vu").
 */
declare(strict_types=1);

namespace Cantine\Repositories;

defined('CANTINE') || exit;

use Cantine\Support\Db;
use Cantine\Types;

final class UserMediaRepository
{
    public function __construct(private Db $db)
    {
    }

    /** @return array{status: string, rating: ?int} */
    public function get(int $userId, int $mediaId): array
    {
        $row = $this->db->one('SELECT status, rating FROM {user_media} WHERE user_id = ? AND media_id = ?', [$userId, $mediaId]);
        return [
            'status' => $row['status'] ?? 'a_decouvrir',
            'rating' => isset($row['rating']) ? (int) $row['rating'] : null,
        ];
    }

    public function setStatus(int $userId, int $mediaId, string $status): void
    {
        if (!Types::isStatus($status)) {
            throw new \InvalidArgumentException('Statut inconnu');
        }
        $this->db->upsert('user_media', ['status' => $status, 'updated_at' => Db::now()], ['user_id' => $userId, 'media_id' => $mediaId]);
    }

    /** Note de 1 à 5, ou null pour l'effacer. Le statut doit être "vu". */
    public function setRating(int $userId, int $mediaId, ?int $rating): bool
    {
        if ($rating !== null && ($rating < 1 || $rating > 5)) {
            return false;
        }
        if ($this->get($userId, $mediaId)['status'] !== 'vu') {
            return false;
        }
        $this->db->update('user_media', ['rating' => $rating, 'updated_at' => Db::now()], ['user_id' => $userId, 'media_id' => $mediaId]);
        return true;
    }

    /** Marque "vu" sans toucher à une note existante (ajout, doublon détecté). */
    public function markSeen(int $userId, int $mediaId): void
    {
        $current = $this->get($userId, $mediaId)['status'];
        if ($current !== 'vu') {
            $this->setStatus($userId, $mediaId, 'vu');
        }
    }
}
