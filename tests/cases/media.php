<?php
/** Tests des contenus, statuts, notes, listes, fil, droits, fusion et données de démo. */
declare(strict_types=1);

use Cantine\Repositories\CommentRepository;
use Cantine\Repositories\FeedRepository;
use Cantine\Repositories\MediaRepository;
use Cantine\Repositories\UserMediaRepository;
use Cantine\Repositories\UserRepository;
use Cantine\Services\Demo;
use Cantine\Support\Db;
use Cantine\Support\Migrator;
use Cantine\Support\Settings;

/** Deux membres et un admin. */
function seedUsers(Db $db): array
{
    $users = new UserRepository($db);
    return [
        'admin' => $users->create('admin', 'Admin', 'motdepasse-admin', 'admin'),
        'a' => $users->create('alice', 'Alice', 'motdepasse-a'),
        'b' => $users->create('bruno', 'Bruno', 'motdepasse-b'),
    ];
}

function addManual(Db $db, int $userId, string $title, string $type = 'film', array $genres = []): int
{
    return (new MediaRepository($db))->create(['type' => $type, 'source' => 'manuel', 'title' => $title], $genres, $userId);
}

return [
    'db: migrations sur une base neuve' => function (string $driver): void {
        $db = freshDb($driver);
        $migrator = new Migrator($db);
        same(Migrator::latestVersion(), $migrator->currentVersion());
        same([], $migrator->migrate(), 'relancer ne fait rien');
        foreach (['users', 'remember_tokens', 'media', 'genres', 'media_genres', 'user_media', 'comments', 'watch_providers', 'settings', 'login_attempts', 'api_cache'] as $table) {
            check(in_array($db->table($table), $db->tables(), true), "table $table absente");
        }
    },

    'db: critère de fin d\'étape 2 (ajout, statut, note, moyenne)' => function (string $driver): void {
        $db = freshDb($driver);
        $u = seedUsers($db);
        $repo = new MediaRepository($db);
        $status = new UserMediaRepository($db);

        $id = addManual($db, $u['a'], 'Le Film de test');
        same('vu', $status->get($u['a'], $id)['status'], 'l\'ajouteur est automatiquement "vu"');
        same(null, $status->get($u['a'], $id)['rating'], 'sans note');
        same('a_decouvrir', $status->get($u['b'], $id)['status'], 'les autres : à découvrir');

        $list = $repo->listForUser($u['b'], ['tab' => 'a_decouvrir'], 'recent', 1);
        same(1, $list['total']);

        check(!$status->setRating($u['b'], $id, 4), 'pas de note sans "vu"');
        $status->setStatus($u['b'], $id, 'vu');
        check($status->setRating($u['b'], $id, 4));
        check(!$status->setRating($u['b'], $id, 6), 'note de 1 à 5');
        $status->setRating($u['a'], $id, 3);

        $stats = $repo->stats($id);
        same(2, $stats['seen_count']);
        same(3.5, $stats['avg_rating']);
        same(2, count($repo->memberOpinions($id)));
    },

    'db: note masquée puis restaurée en quittant "vu"' => function (string $driver): void {
        $db = freshDb($driver);
        $u = seedUsers($db);
        $status = new UserMediaRepository($db);
        $id = addManual($db, $u['a'], 'Note conservée');
        $status->setRating($u['a'], $id, 5);
        $status->setStatus($u['a'], $id, 'en_cours');
        same(null, (new MediaRepository($db))->stats($id)['avg_rating'], 'non comptée hors "vu"');
        $status->setStatus($u['a'], $id, 'vu');
        same(5, $status->get($u['a'], $id)['rating'], 'réapparaît');
    },

    'db: onglets, filtres, tris et recherche' => function (string $driver): void {
        $db = freshDb($driver);
        $u = seedUsers($db);
        $repo = new MediaRepository($db);
        $status = new UserMediaRepository($db);
        $m1 = addManual($db, $u['a'], 'Alpha', 'film', ['Comédie']);
        $m2 = addManual($db, $u['a'], 'Bêta', 'livre', ['Roman']);
        $m3 = addManual($db, $u['admin'], 'Gamma', 'film', ['Comédie', 'Drame']);
        $status->setStatus($u['b'], $m2, 'pas_pour_moi');
        $status->setStatus($u['b'], $m3, 'vu');
        $status->setRating($u['b'], $m3, 2);
        $status->setRating($u['a'], $m1, 5);
        $status->setStatus($u['b'], $m1, 'en_cours');

        $ids = static fn (array $r) => array_map(static fn ($i) => (int) $i['id'], $r['items']);
        same([], $ids($repo->listForUser($u['b'], ['tab' => 'a_decouvrir'], 'recent', 1)));
        same([$m1], $ids($repo->listForUser($u['b'], ['tab' => 'en_cours'], 'recent', 1)));
        same([$m2], $ids($repo->listForUser($u['b'], ['tab' => 'pas_pour_moi'], 'recent', 1)));
        $all = $ids($repo->listForUser($u['b'], ['tab' => 'tout'], 'titre', 1));
        same([$m1, $m3], $all, '"Pas pour moi" exclu de "Tout"');

        same([$m1, $m3], $ids($repo->listForUser($u['b'], ['tab' => 'tout', 'type' => 'film'], 'titre', 1)));
        $comedy = (int) $db->value("SELECT id FROM {genres} WHERE name = 'Comédie'");
        same(2, $repo->listForUser($u['a'], ['tab' => 'tout', 'genre' => $comedy], 'recent', 1)['total']);
        same([$m3], $ids($repo->listForUser($u['a'], ['tab' => 'tout', 'added_by' => $u['admin']], 'recent', 1)));
        same([$m2], $ids($repo->listForUser($u['a'], ['tab' => 'tout', 'q' => 'beta'], 'recent', 1)), 'recherche sans accent');
        same([$m1, $m3, $m2], $ids($repo->listForUser($u['a'], ['tab' => 'tout'], 'note', 1)), 'mieux notés, sans note à la fin');
        same($m3, $ids($repo->listForUser($u['admin'], ['tab' => 'tout'], 'populaire', 1))[0], 'plus vus en premier');

        $counts = $repo->tabCounts($u['b']);
        same(1, $counts['pas_pour_moi']);
        same(2, $counts['tout']);

        // Pagination
        same(1, count($repo->listForUser($u['a'], ['tab' => 'tout'], 'titre', 2, 2)['items']));
    },

    'db: badge "nouveau" et tirage au sort' => function (string $driver): void {
        $db = freshDb($driver);
        $u = seedUsers($db);
        $repo = new MediaRepository($db);
        $m1 = addManual($db, $u['a'], 'Ancien');
        $db->update('media', ['created_at' => '2020-01-01 00:00:00'], ['id' => $m1]);
        addManual($db, $u['a'], 'Récent');
        addManual($db, $u['b'], 'Le mien');
        same(1, $repo->countNew($u['b'], '2025-01-01 00:00:00'), 'seulement ceux des autres, après la visite');
        same(0, $repo->countNew($u['b'], null));

        $pick = $repo->randomToDiscover($u['b'], null, null);
        check($pick !== null && in_array($pick['title'], ['Ancien', 'Récent'], true), 'tirage parmi "à découvrir"');
        (new UserMediaRepository($db))->setStatus($u['b'], $m1, 'pas_pour_moi');
        for ($i = 0; $i < 5; $i++) {
            same('Récent', $repo->randomToDiscover($u['b'], 'film', null)['title'], '"Pas pour moi" jamais tiré');
        }
        same(null, $repo->randomToDiscover($u['b'], 'livre', null));
    },

    'db: doublons (API et ajout manuel)' => function (string $driver): void {
        $db = freshDb($driver);
        $u = seedUsers($db);
        $repo = new MediaRepository($db);
        $repo->create(['type' => 'film', 'source' => 'tmdb', 'external_id' => '603', 'title' => 'Matrix', 'year' => 1999], [], $u['a']);
        throws(fn () => $repo->create(['type' => 'film', 'source' => 'tmdb', 'external_id' => '603', 'title' => 'Matrix'], [], $u['b']), PDOException::class);
        // Même identifiant, autre type : autorisé (film et série TMDB ont des numérotations distinctes).
        $repo->create(['type' => 'serie', 'source' => 'tmdb', 'external_id' => '603', 'title' => 'Autre'], [], $u['b']);
        // Plusieurs ajouts manuels (external_id NULL) ne se gênent pas.
        addManual($db, $u['a'], 'Sans id 1');
        addManual($db, $u['a'], 'Sans id 2');

        same(1, count($repo->findSimilar('film', 'the matrix', null)), 'titre normalisé');
        same(1, count($repo->findSimilar('film', 'Matrix', 1999)));
        same(0, count($repo->findSimilar('film', 'Matrix', 2021)), 'année différente');
        same(0, count($repo->findSimilar('livre', 'Matrix', null)), 'type différent');
    },

    'db: droits et suppression de compte' => function (string $driver): void {
        $db = freshDb($driver);
        $u = seedUsers($db);
        $users = new UserRepository($db);
        $repo = new MediaRepository($db);
        check($users->isLastAdmin($u['admin']));
        check(!$users->isLastAdmin($u['a']));
        $users->update($u['a'], ['role' => 'admin']);
        check(!$users->isLastAdmin($u['admin']), 'deux admins');

        $id = addManual($db, $u['b'], 'Reco de Bruno');
        same(['statuses' => 0, 'comments' => 0], $repo->othersActivity($id, $u['b']));
        (new UserMediaRepository($db))->setStatus($u['a'], $id, 'vu');
        (new CommentRepository($db))->create($id, $u['a'], 'Super', false);
        same(['statuses' => 1, 'comments' => 1], $repo->othersActivity($id, $u['b']));

        (new CommentRepository($db))->create($id, $u['b'], 'Merci', false);
        $users->delete($u['b']);
        $media = $repo->find($id);
        check($media !== null, 'la recommandation est conservée');
        same(null, $media['added_by'], '"Ancien membre"');
        same(1, (int) $db->value('SELECT COUNT(*) FROM {comments}'), 'ses commentaires sont supprimés');
    },

    'db: fil "Quoi de neuf"' => function (string $driver): void {
        $db = freshDb($driver);
        $u = seedUsers($db);
        $status = new UserMediaRepository($db);
        $m1 = addManual($db, $u['a'], 'Un');
        $m2 = addManual($db, $u['a'], 'Deux');
        $status->setStatus($u['b'], $m1, 'vu');
        $status->setRating($u['b'], $m1, 4);
        $status->setStatus($u['b'], $m2, 'vu');
        $status->setRating($u['b'], $m2, 2);
        $status->setStatus($u['admin'], $m1, 'pas_pour_moi');
        (new CommentRepository($db))->create($m1, $u['admin'], 'Bof', true);
        $feed = (new FeedRepository($db))->page(1);
        $kinds = array_count_values(array_column($feed['events'], 'kind'));
        same(2, $kinds['media']);
        same(1, $kinds['comment']);
        same(1, $kinds['rating'], 'deux notes consécutives regroupées');
        $rating = array_values(array_filter($feed['events'], static fn ($e) => $e['kind'] === 'rating'))[0];
        same(2, count($rating['items']));
        check(!$feed['has_more']);
    },

    'db: fusion de doublons' => function (string $driver): void {
        $db = freshDb($driver);
        $u = seedUsers($db);
        $repo = new MediaRepository($db);
        $status = new UserMediaRepository($db);
        $manual = addManual($db, $u['a'], 'Matrix', 'film', ['Action']);
        $api = $repo->create(['type' => 'film', 'source' => 'tmdb', 'external_id' => '603', 'title' => 'Matrix'], ['Science-fiction'], $u['b']);
        $status->setRating($u['a'], $manual, 5);
        (new CommentRepository($db))->create($manual, $u['admin'], 'Culte', false);
        $repo->merge($manual, $api);
        same(null, $repo->find($manual));
        same(5, $status->get($u['a'], $api)['rating'], 'note reportée');
        same(1, (int) $db->value('SELECT COUNT(*) FROM {comments} WHERE media_id = ?', [$api]));
        same(['Action', 'Science-fiction'], array_column($repo->genres($api), 'name'));
    },

    'db: données de démonstration (ajout et retrait)' => function (string $driver): void {
        $db = freshDb($driver);
        $u = seedUsers($db);
        $real = addManual($db, $u['a'], 'Vrai contenu');
        $settings = new Settings($db);
        $demo = new Demo($db, $settings);
        $result = $demo->load();
        same(4, count($result['users']));
        check($demo->isLoaded());
        check((int) $db->value('SELECT COUNT(*) FROM {media} WHERE is_demo = 1') >= 20);
        throws(fn () => $demo->load(), RuntimeException::class);

        $demoMedia = (int) $db->value('SELECT id FROM {media} WHERE is_demo = 1 ORDER BY id LIMIT 1');
        (new UserMediaRepository($db))->setStatus($u['b'], $demoMedia, 'vu');
        same(1, $demo->realActivityOnDemo()['statuses']);

        $demo->remove();
        check(!$demo->isLoaded());
        same(3, (int) $db->value('SELECT COUNT(*) FROM {users}'), 'vrais comptes intacts');
        same(1, (int) $db->value('SELECT COUNT(*) FROM {media}'));
        check((new MediaRepository($db))->find($real) !== null);
        same('', $settings->get('demo_loaded'));
    },
];
