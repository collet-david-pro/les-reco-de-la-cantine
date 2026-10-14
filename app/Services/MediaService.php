<?php
/**
 * Enregistrement des contenus venant des API : création, re-synchronisation, "où regarder".
 */
declare(strict_types=1);

namespace Cantine\Services;

defined('CANTINE') || exit;

use Cantine\Repositories\MediaRepository;
use Cantine\Repositories\UserMediaRepository;
use Cantine\Support\Db;

final class MediaService
{
    public function __construct(private Db $db)
    {
    }

    /**
     * Crée un contenu à partir d'une fiche d'API normalisée.
     * Si le contenu existe déjà (contrainte d'unicité), l'utilisateur est simplement marqué "vu".
     * @return array{id: int, created: bool}
     */
    public function createFromApi(array $details, int $userId): array
    {
        $repo = new MediaRepository($this->db);
        $existing = $repo->findByExternal($details['type'], $details['source'], $details['external_id']);
        if ($existing) {
            (new UserMediaRepository($this->db))->markSeen($userId, (int) $existing['id']);
            return ['id' => (int) $existing['id'], 'created' => false];
        }
        try {
            $id = $repo->create($details, $details['genres'] ?? [], $userId);
        } catch (\PDOException $e) {
            if (!Db::isUniqueViolation($e)) {
                throw $e;
            }
            // Ajouté au même moment par quelqu'un d'autre.
            $existing = $repo->findByExternal($details['type'], $details['source'], $details['external_id']);
            (new UserMediaRepository($this->db))->markSeen($userId, (int) $existing['id']);
            return ['id' => (int) $existing['id'], 'created' => false];
        }
        if (array_key_exists('watch_providers', $details) && $details['watch_providers'] !== null) {
            $this->saveWatchProviders($id, $details['watch_providers'], $details['region'] ?? 'FR');
        }
        return ['id' => $id, 'created' => true];
    }

    /** Met à jour un contenu avec une fiche d'API fraîche (bouton "re-synchroniser"). */
    public function applyApiDetails(int $mediaId, array $details): void
    {
        $repo = new MediaRepository($this->db);
        $this->db->transaction(function () use ($repo, $mediaId, $details): void {
            $repo->update($mediaId, [
                'title' => mb_substr($details['title'], 0, 255),
                'year' => $details['year'],
                'description' => $details['description'],
                'image_url' => $details['image_url'],
                'external_url' => $details['external_url'],
                'trailer_url' => $details['trailer_url'],
                'isbn' => $details['isbn'],
                'extra' => $details['extra'] ?? [],
            ]);
            $repo->setGenres($mediaId, $details['genres'] ?? []);
        });
        if (array_key_exists('watch_providers', $details) && $details['watch_providers'] !== null) {
            $this->saveWatchProviders($mediaId, $details['watch_providers'], $details['region'] ?? 'FR');
        }
    }

    public function saveWatchProviders(int $mediaId, array $data, string $region): void
    {
        $this->db->upsert('watch_providers', [
            'region' => $region,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => Db::now(),
        ], ['media_id' => $mediaId]);
    }

    /** @return array{data: array, updated_at: string, region: string}|null */
    public function watchProviders(int $mediaId): ?array
    {
        $row = $this->db->one('SELECT * FROM {watch_providers} WHERE media_id = ?', [$mediaId]);
        if (!$row) {
            return null;
        }
        $data = json_decode((string) $row['data'], true);
        return ['data' => is_array($data) ? $data : [], 'updated_at' => $row['updated_at'], 'region' => $row['region']];
    }
}
