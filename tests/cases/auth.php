<?php
/** Tests d'authentification, limitation des tentatives et CSRF. */
declare(strict_types=1);

use Cantine\Repositories\UserRepository;
use Cantine\Support\Auth;
use Cantine\Support\Csrf;
use Cantine\Support\LoginThrottle;

return [
    'db: connexion par identifiant et mot de passe' => function (string $driver): void {
        $db = freshDb($driver);
        $users = new UserRepository($db);
        $id = $users->create('Paul', 'Paul', 'un mot de passe solide');
        $auth = new Auth($db, '/', false, 7200);
        check($auth->attempt('paul', 'un mot de passe solide') !== null, 'identifiant en minuscules');
        check($auth->attempt('PAUL ', 'un mot de passe solide') !== null, 'insensible à la casse');
        same(null, $auth->attempt('paul', 'mauvais'));
        same(null, $auth->attempt('inconnu', 'un mot de passe solide'));
        $users->update($id, ['is_active' => 0]);
        same(null, $auth->attempt('paul', 'un mot de passe solide'), 'compte désactivé refusé');
    },

    'db: invalidation des sessions' => function (string $driver): void {
        $db = freshDb($driver);
        $id = (new UserRepository($db))->create('lea', 'Léa', 'motdepasse-123');
        $db->insert('remember_tokens', ['selector' => str_repeat('a', 24), 'validator_hash' => str_repeat('b', 64), 'user_id' => $id,
            'expires_at' => '2099-01-01 00:00:00', 'created_at' => '2026-01-01 00:00:00']);
        (new Auth($db, '/', false, 7200))->invalidateSessions($id);
        same(2, (int) $db->value('SELECT session_version FROM {users} WHERE id = ?', [$id]));
        same(0, (int) $db->value('SELECT COUNT(*) FROM {remember_tokens}'));
    },

    'db: limitation des tentatives de connexion' => function (string $driver): void {
        $db = freshDb($driver);
        $throttle = new LoginThrottle($db);
        same(0, $throttle->secondsToWait('paul', '1.2.3.4'));
        for ($i = 0; $i < 5; $i++) {
            $throttle->recordFailure('paul', '1.2.3.4');
        }
        check($throttle->secondsToWait('paul', '9.9.9.9') > 0, 'bloqué par identifiant');
        check($throttle->secondsToWait('autre', '1.2.3.4') > 0, 'bloqué par IP');
        same(0, $throttle->secondsToWait('autre', '9.9.9.9'));
        check($throttle->secondsToWait('paul', '1.2.3.4', time() + 61) === 0, 'attente de 1 min après 5 échecs');
        $throttle->recordFailure('paul', '1.2.3.4');
        check($throttle->secondsToWait('paul', '5.5.5.5') > 60, 'attente croissante');
        $throttle->clear('paul');
        same(0, $throttle->secondsToWait('paul', '5.5.5.5'));
    },

    'mots de passe : longueur et hachage' => function (): void {
        check(UserRepository::validatePassword('court') !== null);
        check(UserRepository::validatePassword('assez long') === null);
        check(UserRepository::validatePassword('onze caract', 12) !== null, 'admin : 12 minimum');
        check(UserRepository::validatePassword(str_repeat('x', 73)) !== null, '72 octets maximum (bcrypt)');
        check(UserRepository::validateUsername('ab') !== null);
        check(UserRepository::validateUsername('jean-paul') === null);
        check(UserRepository::validateUsername('jean paul') !== null);
        $hash = Auth::hashPassword('secret-de-test');
        check(password_verify('secret-de-test', $hash));
        check(!Auth::needsRehash($hash));
    },

    'jeton CSRF' => function (): void {
        $_SESSION = [];
        $token = Csrf::token();
        same(64, strlen($token));
        same($token, Csrf::token(), 'stable pendant la session');
        check(Csrf::check($token));
        check(!Csrf::check('faux'));
        check(!Csrf::check(''));
        check(!Csrf::check(null));
    },
];
