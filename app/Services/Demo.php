<?php
/**
 * Données de démonstration (panneau admin) : faux membres, contenus d'exemple, statuts, notes, commentaires.
 * Tout ce qui est créé est marqué is_demo et peut être supprimé en un clic sans toucher aux vraies données.
 * Les contenus de démo sont des ajouts manuels sans image externe : aucune clé d'API ni réseau nécessaire.
 */
declare(strict_types=1);

namespace Cantine\Services;

defined('CANTINE') || exit;

use Cantine\Repositories\CommentRepository;
use Cantine\Repositories\MediaRepository;
use Cantine\Repositories\UserMediaRepository;
use Cantine\Repositories\UserRepository;
use Cantine\Support\Db;
use Cantine\Support\Settings;

final class Demo
{
    public function __construct(private Db $db, private Settings $settings)
    {
    }

    public function isLoaded(): bool
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM {users} WHERE is_demo = 1')
            + (int) $this->db->value('SELECT COUNT(*) FROM {media} WHERE is_demo = 1') > 0;
    }

    /**
     * Charge les données de app/demo/demo.json.
     * @return array{password: string, users: list<string>} identifiants créés et mot de passe commun
     */
    public function load(): array
    {
        if ($this->isLoaded()) {
            throw new \RuntimeException(t('demo.already_loaded'));
        }
        $data = json_decode((string) file_get_contents(CANTINE_APP . '/demo/demo.json'), true, 32, JSON_THROW_ON_ERROR);
        $password = (string) $data['password'];
        $usersRepo = new UserRepository($this->db);
        $mediaRepo = new MediaRepository($this->db);
        $statusRepo = new UserMediaRepository($this->db);
        $comments = new CommentRepository($this->db);

        $created = [];
        $this->db->transaction(function () use ($data, $password, $usersRepo, $mediaRepo, $statusRepo, $comments, &$created): void {
            $userIds = [];
            foreach ($data['users'] as $key => $user) {
                $username = $this->freeUsername((string) $user['username']);
                $userIds[$key] = $usersRepo->create($username, (string) $user['display_name'], $password, 'member', false, true);
                $usersRepo->update($userIds[$key], ['avatar_color' => (string) $user['color']]);
                $created[] = $username;
            }

            foreach ($data['media'] as $i => $item) {
                $addedBy = $userIds[$item['by']] ?? null;
                // Dates étalées dans le passé pour un fil d'actualité réaliste.
                $createdAt = Db::ago((count($data['media']) - $i) * 7200 + 3600);
                $id = $mediaRepo->create([
                    'type' => $item['type'],
                    'source' => 'manuel',
                    'external_id' => null,
                    'title' => $item['title'],
                    'year' => $item['year'] ?? null,
                    'description' => $item['description'] ?? null,
                    'extra' => $item['extra'] ?? [],
                    'created_at' => $createdAt,
                ], $item['genres'] ?? [], $addedBy, true);

                foreach ($item['opinions'] ?? [] as $who => $opinion) {
                    if (!isset($userIds[$who])) {
                        continue;
                    }
                    [$status, $rating] = array_pad((array) $opinion, 2, null);
                    $statusRepo->setStatus($userIds[$who], $id, (string) $status);
                    if ($status === 'vu' && $rating !== null) {
                        $statusRepo->setRating($userIds[$who], $id, (int) $rating);
                    }
                }
                // Notes datées juste après l'ajout, plutôt qu'"à l'instant".
                $this->db->run('UPDATE {user_media} SET updated_at = ? WHERE media_id = ?', [Db::ago((count($data['media']) - $i) * 7200 - 1800), $id]);
                foreach ($item['comments'] ?? [] as $j => $comment) {
                    if (isset($userIds[$comment['by']])) {
                        $comments->create($id, $userIds[$comment['by']], (string) $comment['text'], !empty($comment['spoiler']), true,
                            Db::ago((count($data['media']) - $i) * 7200 - ($j + 1) * 600));
                    }
                }
            }
        });
        $this->settings->set('demo_loaded', '1');
        return ['password' => $password, 'users' => $created];
    }

    /** Nombre de vraies notes/commentaires posés sur des contenus de démo (à signaler avant suppression). */
    public function realActivityOnDemo(): array
    {
        return [
            'statuses' => (int) $this->db->value(
                "SELECT COUNT(*) FROM {user_media} um JOIN {media} m ON m.id = um.media_id JOIN {users} u ON u.id = um.user_id
                 WHERE m.is_demo = 1 AND u.is_demo = 0 AND um.status <> 'a_decouvrir'"
            ),
            'comments' => (int) $this->db->value(
                'SELECT COUNT(*) FROM {comments} c JOIN {media} m ON m.id = c.media_id WHERE m.is_demo = 1 AND c.is_demo = 0'
            ),
        ];
    }

    /** Supprime tout ce qui est marqué is_demo (les statuts, notes et commentaires liés suivent par cascade). */
    public function remove(): void
    {
        $this->db->transaction(function (): void {
            $this->db->run('DELETE FROM {comments} WHERE is_demo = 1');
            $this->db->run('DELETE FROM {media} WHERE is_demo = 1');
            $this->db->run('DELETE FROM {users} WHERE is_demo = 1');
        });
        (new MediaRepository($this->db))->purgeUnusedGenres();
        $this->settings->remove('demo_loaded');
    }

    /** Évite un conflit avec un vrai compte qui porterait le même identifiant. */
    private function freeUsername(string $base): string
    {
        $username = $base;
        $n = 2;
        while ($this->db->value('SELECT 1 FROM {users} WHERE username = ?', [$username])) {
            $username = $base . '-' . $n++;
        }
        return $username;
    }
}
