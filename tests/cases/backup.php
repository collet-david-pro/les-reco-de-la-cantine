<?php
/** Tests de sauvegarde et restauration (aller-retour identique, y compris d'un moteur à l'autre). */
declare(strict_types=1);

use Cantine\Repositories\CommentRepository;
use Cantine\Repositories\MediaRepository;
use Cantine\Repositories\UserMediaRepository;
use Cantine\Repositories\UserRepository;
use Cantine\Services\Backup;
use Cantine\Services\Demo;
use Cantine\Support\Db;
use Cantine\Support\Settings;

/** Contenu de toutes les tables sauvegardées, normalisé pour comparaison. */
function dumpTables(Db $db): array
{
    $out = [];
    foreach (Backup::TABLES as $table => $columns) {
        $order = match ($table) {
            'media_genres' => 'media_id, genre_id', 'user_media' => 'user_id, media_id', 'settings' => 'name', default => 'id',
        };
        $rows = $db->all('SELECT ' . implode(', ', $columns) . ' FROM {' . $table . "} ORDER BY $order");
        foreach ($rows as &$row) {
            foreach ($row as $k => $v) {
                $row[$k] = $v === null ? null : (string) $v;
            }
        }
        if ($table === 'settings') {
            $rows = array_values(array_filter($rows, static fn ($r) => !in_array($r['name'], ['schema_version', 'maintenance'], true)));
        }
        $out[$table] = $rows;
    }
    return $out;
}

function exportToString(Db $db, bool $secrets = false, bool $demo = false): string
{
    $stream = fopen('php://memory', 'w+b');
    (new Backup($db))->export($stream, $secrets, $demo);
    rewind($stream);
    $json = stream_get_contents($stream);
    fclose($stream);
    return $json;
}

/** Base remplie de données variées (accents, émojis, NULL, notes masquées...). */
function seedRich(Db $db): void
{
    $users = new UserRepository($db);
    $admin = $users->create('admin', 'Admin', 'motdepasse-admin', 'admin');
    $a = $users->create('zoe', 'Zoé 🍲', 'motdepasse-zoe');
    $repo = new MediaRepository($db);
    $m = $repo->create(['type' => 'livre', 'source' => 'openlibrary', 'external_id' => 'OL1W', 'title' => 'L\'Écume des jours',
        'year' => 1947, 'isbn' => '9782253140870', 'extra' => ['author' => ['Boris Vian'], 'pages' => 320]], ['Roman'], $a);
    $m2 = $repo->create(['type' => 'film', 'source' => 'manuel', 'title' => 'Sans année « spéciale »'], [], $admin);
    $status = new UserMediaRepository($db);
    $status->setStatus($admin, $m, 'vu');
    $status->setRating($admin, $m, 4);
    $status->setRating($a, $m, 5);
    $status->setStatus($a, $m, 'en_cours'); // note masquée conservée
    $status->setStatus($a, $m2, 'pas_pour_moi');
    (new CommentRepository($db))->create($m, $admin, "Ligne 1\nLigne 2 <b>gras</b> & co", true);
    $settings = new Settings($db);
    $settings->set('site_name', 'Ma cantine');
    $settings->set('tmdb_key', 'cle-secrete-tmdb');
    $settings->set('shop_links', '[{"nom":"A","url":"https://a.example/{isbn}"}]');
}

return [
    'db: sauvegarde puis restauration identique' => function (string $driver): void {
        $source = freshDb($driver);
        seedRich($source);
        $json = exportToString($source, true);
        $result = (new Backup($source))->validate($json);
        check($result['ok'], implode(' / ', $result['errors']));
        same(2, $result['summary']['users']);
        same(2, $result['summary']['ratings']);

        // Restauration sur chaque moteur disponible (MySQL -> SQLite et inversement).
        foreach (array_keys(testDatabases()) as $targetDriver) {
            $target = freshDb($targetDriver);
            (new UserRepository($target))->create('ancien', 'Ancien', 'motdepasse-x', 'admin');
            (new Backup($target))->restore($result['data']);
            same(dumpTables($source), dumpTables($target), "aller-retour $driver -> $targetDriver");
            // Les compteurs reprennent après les identifiants restaurés.
            $newId = (new UserRepository($target))->create('nouveau', 'Nouveau', 'motdepasse-n');
            same(3, $newId);
        }
    },

    'db: clés d\'API et données de démo exclues par défaut' => function (string $driver): void {
        $db = freshDb($driver);
        seedRich($db);
        (new Demo($db, new Settings($db)))->load();
        $data = json_decode(exportToString($db), true);
        $names = array_column($data['tables']['settings'], 'name');
        check(!in_array('tmdb_key', $names, true), 'clé exclue');
        check(!in_array('schema_version', $names, true));
        same(2, count($data['tables']['users']), 'comptes de démo exclus');
        same(0, count(array_filter($data['tables']['media'], static fn ($m) => $m['is_demo'] === 1)));
        $full = json_decode(exportToString($db, true, true), true);
        same(6, count($full['tables']['users']));

        // Restaurer une sauvegarde sans clé conserve la clé actuelle.
        $target = freshDb($driver);
        (new Settings($target))->set('tmdb_key', 'cle-actuelle');
        $result = (new Backup($target))->validate(json_encode($data));
        check($result['ok']);
        (new Backup($target))->restore($result['data']);
        same('cle-actuelle', (new Settings($target))->get('tmdb_key'));
        same('Ma cantine', (new Settings($target))->get('site_name'));
    },

    'db: restauration refusée si le fichier est invalide' => function (string $driver): void {
        $db = freshDb($driver);
        seedRich($db);
        $backup = new Backup($db);
        $data = json_decode(exportToString($db), true);

        check(!$backup->validate('{pas du json')['ok']);
        check(!$backup->validate('{"format":"autre"}')['ok']);

        $tooNew = $data;
        $tooNew['schema_version'] = 999;
        check(!$backup->validate(json_encode($tooNew))['ok'], 'schéma plus récent');

        $broken = $data;
        $broken['tables']['user_media'][0]['media_id'] = 9999;
        check(!$backup->validate(json_encode($broken))['ok'], 'clé étrangère incohérente');

        $badStatus = $data;
        $badStatus['tables']['user_media'][0]['status'] = 'adore';
        check(!$backup->validate(json_encode($badStatus))['ok'], 'statut invalide');

        $noAdmin = $data;
        foreach ($noAdmin['tables']['users'] as &$u) {
            $u['role'] = 'member';
        }
        unset($u);
        check(!$backup->validate(json_encode($noAdmin))['ok'], 'aucun admin');

        $missing = $data;
        unset($missing['tables']['comments']);
        check(!$backup->validate(json_encode($missing))['ok'], 'table manquante');

        same(2, (int) $db->value('SELECT COUNT(*) FROM {users}'), 'rien n\'a changé');
    },
];
