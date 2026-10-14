<?php
/**
 * Mini lanceur de tests, sans dépendance.
 *
 *   php tests/run.php                 tests sur SQLite (base temporaire)
 *   php tests/run.php filtre          seulement les tests dont le nom contient "filtre"
 *
 * Pour tester aussi MySQL/MariaDB (base dédiée, elle sera vidée des tables préfixées "t_") :
 *   CANTINE_TEST_MYSQL="host=127.0.0.1;port=3306;name=cantine_test;user=root;password=" php tests/run.php
 *
 * Aucun appel réseau : les connecteurs d'API sont testés avec des réponses enregistrées (tests/fixtures).
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit;
}

define('CANTINE', true);
require dirname(__DIR__) . '/app/bootstrap.php';

use Cantine\Support\Db;
use Cantine\Support\Migrator;

final class TestFailure extends RuntimeException
{
}

/** Petites assertions. */
function check(bool $condition, string $message = 'condition fausse'): void
{
    if (!$condition) {
        throw new TestFailure($message);
    }
}

function same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new TestFailure(($message !== '' ? $message . ' : ' : '') . 'attendu ' . var_export($expected, true) . ', obtenu ' . var_export($actual, true));
    }
}

function throws(callable $fn, string $class = Throwable::class): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return;
        }
        throw new TestFailure('exception ' . get_class($e) . ' au lieu de ' . $class . ' : ' . $e->getMessage());
    }
    throw new TestFailure('aucune exception levée (attendu ' . $class . ')');
}

/** Bases de test disponibles : SQLite toujours, MySQL si configuré. */
function testDatabases(): array
{
    $dbs = ['sqlite' => null];
    $env = getenv('CANTINE_TEST_MYSQL');
    if (is_string($env) && $env !== '') {
        $config = ['driver' => 'mysql', 'prefix' => 't_'];
        foreach (explode(';', $env) as $pair) {
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $config[trim($k)] = $v;
        }
        $dbs['mysql'] = $config;
    }
    return $dbs;
}

/** Base neuve et migrée pour un test. */
function freshDb(string $driver = 'sqlite'): Db
{
    if ($driver === 'mysql') {
        $config = testDatabases()['mysql'];
        $db = Db::connect($config);
        $db->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($db->tables() as $table) {
            if (str_starts_with($table, 't_')) {
                $db->pdo->exec('DROP TABLE `' . $table . '`');
            }
        }
        $db->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } else {
        $file = sys_get_temp_dir() . '/cantine-test-' . bin2hex(random_bytes(6)) . '.sqlite';
        register_shutdown_function(static function () use ($file): void {
            foreach ([$file, $file . '-wal', $file . '-shm'] as $f) {
                @unlink($f);
            }
        });
        $db = Db::connect(['driver' => 'sqlite', 'path' => $file, 'prefix' => '']);
    }
    (new Migrator($db))->migrate();
    return $db;
}

// ---- Chargement et exécution des tests ----

$tests = [];
foreach (glob(__DIR__ . '/cases/*.php') as $file) {
    $tests += require $file;
}

$filter = $argv[1] ?? '';
$passed = 0;
$failed = [];
$start = microtime(true);

foreach ($tests as $name => $test) {
    if ($filter !== '' && !str_contains($name, $filter)) {
        continue;
    }
    // Les tests "db:" sont exécutés sur chaque moteur disponible.
    $drivers = str_starts_with($name, 'db: ') ? array_keys(testDatabases()) : [null];
    foreach ($drivers as $driver) {
        $label = $name . ($driver ? " [$driver]" : '');
        $_SESSION = [];
        try {
            $driver ? $test($driver) : $test();
            $passed++;
            echo "\033[32m✔\033[0m $label\n";
        } catch (Throwable $e) {
            $failed[] = $label;
            echo "\033[31m✘ $label\033[0m\n    " . get_class($e) . ' : ' . $e->getMessage() . "\n";
            if (!$e instanceof TestFailure) {
                echo '    ' . $e->getFile() . ':' . $e->getLine() . "\n";
            }
        }
    }
}

printf("\n%d réussi(s), %d échec(s) en %.2f s%s\n", $passed, count($failed), microtime(true) - $start,
    isset(testDatabases()['mysql']) ? ' (SQLite + MySQL)' : ' (SQLite seulement)');
exit($failed ? 1 : 0);
