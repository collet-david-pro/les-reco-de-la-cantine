<?php
/**
 * Sessions, connexion, "rester connecté" et utilisateur courant.
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class Auth
{
    public const REMEMBER_COOKIE = 'cantine_remember';
    public const REMEMBER_DAYS = 30;

    private ?array $user = null;
    private bool $loaded = false;

    public function __construct(
        private Db $db,
        private string $cookiePath,
        private bool $https,
        private int $idleTimeout,
    ) {
    }

    /** Démarre la session PHP avec des paramètres sûrs. */
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string) max($this->idleTimeout, 1440));
        session_name('cantine_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $this->cookiePath,
            'secure' => $this->https, // seulement en HTTPS, sinon impossible de se connecter sur http://localhost
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Expiration après une période d'inactivité.
        $last = $_SESSION['_last_activity'] ?? null;
        if (is_int($last) && time() - $last > $this->idleTimeout) {
            $this->destroySession();
            session_start();
        }
        $_SESSION['_last_activity'] = time();
    }

    /** Utilisateur connecté (ligne de la table users) ou null. */
    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;

        $id = $_SESSION['user_id'] ?? null;
        if (is_int($id)) {
            $user = $this->db->one('SELECT * FROM {users} WHERE id = ?', [$id]);
            if ($user && (int) $user['is_active'] === 1 && (int) $user['session_version'] === ($_SESSION['session_version'] ?? null)) {
                $this->user = $user;
            } else {
                // Compte désactivé, supprimé ou mot de passe réinitialisé : on coupe la session.
                unset($_SESSION['user_id'], $_SESSION['session_version']);
            }
        }

        if ($this->user === null) {
            $this->user = $this->loginFromRememberCookie();
        }

        if ($this->user !== null) {
            $this->touchLastSeen();
        }
        return $this->user;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user ? (int) $user['id'] : null;
    }

    public function isAdmin(): bool
    {
        return ($this->user()['role'] ?? '') === 'admin';
    }

    /** Ouvre la session pour $user (après vérification du mot de passe). */
    public function login(array $user, bool $remember): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['session_version'] = (int) $user['session_version'];
        unset($_SESSION['_visit_started']);
        $this->user = $user;
        $this->loaded = true;
        if ($remember) {
            $this->issueRememberToken((int) $user['id']);
        }
        $this->touchLastSeen();
    }

    public function logout(bool $allDevices = false): void
    {
        $id = $this->id();
        if ($id !== null) {
            if ($allDevices) {
                $this->invalidateSessions($id);
            } else {
                $this->forgetCurrentRememberToken();
            }
        }
        $this->clearRememberCookie();
        $this->destroySession();
        $this->user = null;
    }

    /**
     * Invalide toutes les sessions et jetons "rester connecté" d'un utilisateur
     * (réinitialisation ou changement de mot de passe, désactivation, "déconnecter tous mes appareils").
     */
    public function invalidateSessions(int $userId): void
    {
        $this->db->run('UPDATE {users} SET session_version = session_version + 1 WHERE id = ?', [$userId]);
        $this->db->delete('remember_tokens', ['user_id' => $userId]);
    }

    /** Après un changement de son propre mot de passe : garder la session courante valide. */
    public function refreshCurrentSession(): void
    {
        $id = $this->id();
        if ($id === null) {
            return;
        }
        $version = (int) $this->db->value('SELECT session_version FROM {users} WHERE id = ?', [$id]);
        session_regenerate_id(true);
        $_SESSION['session_version'] = $version;
        $this->loaded = false;
        $this->user = null;
    }

    private function destroySession(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        setcookie('cantine_sid', '', ['expires' => time() - 3600, 'path' => $this->cookiePath, 'secure' => $this->https, 'httponly' => true, 'samesite' => 'Lax']);
    }

    /**
     * Badge "nouveau" : au début de chaque nouvelle session, la dernière visite devient la "visite précédente".
     * Ensuite, last_seen_at n'est mis à jour qu'au plus une fois toutes les 5 minutes.
     */
    private function touchLastSeen(): void
    {
        $id = (int) $this->user['id'];
        $now = Db::now();
        if (empty($_SESSION['_visit_started'])) {
            $_SESSION['_visit_started'] = true;
            $previous = $this->user['last_seen_at'] ?? null;
            $this->db->update('users', ['previous_seen_at' => $previous, 'last_seen_at' => $now], ['id' => $id]);
            $this->user['previous_seen_at'] = $previous;
            $this->user['last_seen_at'] = $now;
            return;
        }
        $last = $this->user['last_seen_at'] ?? null;
        if (!$last || strtotime($last . ' UTC') < time() - 300) {
            $this->db->update('users', ['last_seen_at' => $now], ['id' => $id]);
            $this->user['last_seen_at'] = $now;
        }
    }

    // ---- "Rester connecté" : cookie "sélecteur:validateur", seul le hash du validateur est en base ----

    private function issueRememberToken(int $userId): void
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + self::REMEMBER_DAYS * 86400;
        $this->db->insert('remember_tokens', [
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'user_id' => $userId,
            'expires_at' => gmdate('Y-m-d H:i:s', $expires),
            'created_at' => Db::now(),
        ]);
        $this->setRememberCookie($selector . ':' . $validator, $expires);
    }

    private function loginFromRememberCookie(): ?array
    {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? '';
        if (!is_string($cookie) || !preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $m)) {
            return null;
        }
        [, $selector, $validator] = $m;

        // Purge occasionnelle des jetons expirés.
        if (random_int(1, 50) === 1) {
            $this->db->run('DELETE FROM {remember_tokens} WHERE expires_at < ?', [Db::now()]);
        }

        $token = $this->db->one('SELECT * FROM {remember_tokens} WHERE selector = ?', [$selector]);
        if (!$token || $token['expires_at'] < Db::now() || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            if ($token) {
                // Validateur incorrect : jeton possiblement volé, on le supprime.
                $this->db->delete('remember_tokens', ['id' => $token['id']]);
            }
            $this->clearRememberCookie();
            return null;
        }

        $user = $this->db->one('SELECT * FROM {users} WHERE id = ? AND is_active = 1', [$token['user_id']]);
        if (!$user) {
            $this->db->delete('remember_tokens', ['id' => $token['id']]);
            $this->clearRememberCookie();
            return null;
        }

        // Rotation : nouveau validateur à chaque reconnexion automatique.
        $newValidator = bin2hex(random_bytes(32));
        $expires = time() + self::REMEMBER_DAYS * 86400;
        $this->db->update('remember_tokens', [
            'validator_hash' => hash('sha256', $newValidator),
            'expires_at' => gmdate('Y-m-d H:i:s', $expires),
        ], ['id' => $token['id']]);
        $this->setRememberCookie($selector . ':' . $newValidator, $expires);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['session_version'] = (int) $user['session_version'];
        unset($_SESSION['_visit_started']);
        return $user;
    }

    private function forgetCurrentRememberToken(): void
    {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? '';
        if (is_string($cookie) && preg_match('/^([a-f0-9]{24}):/', $cookie, $m)) {
            $this->db->delete('remember_tokens', ['selector' => $m[1]]);
        }
    }

    private function setRememberCookie(string $value, int $expires): void
    {
        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires' => $expires,
            'path' => $this->cookiePath,
            'secure' => $this->https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::REMEMBER_COOKIE] = $value;
    }

    private function clearRememberCookie(): void
    {
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            $this->setRememberCookie('', time() - 3600);
            unset($_COOKIE[self::REMEMBER_COOKIE]);
        }
    }

    // ---- Mots de passe ----

    public static function hashPassword(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        return password_hash($password, $algo);
    }

    public static function needsRehash(string $hash): bool
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        return password_needs_rehash($hash, $algo);
    }

    /**
     * Vérifie identifiant + mot de passe. Renvoie l'utilisateur ou null.
     * password_verify est exécuté même si le compte n'existe pas (pas d'indice par le temps de réponse).
     */
    public function attempt(string $username, string $password): ?array
    {
        $user = $this->db->one('SELECT * FROM {users} WHERE username = ?', [mb_strtolower(trim($username))]);
        $hash = $user['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuJ0tP7m0R2oKkFmH2c7mI3lGx6r7q9Ka';
        $valid = password_verify($password, $hash);
        if (!$user || !$valid || (int) $user['is_active'] !== 1) {
            return null;
        }
        if (self::needsRehash($user['password_hash'])) {
            $this->db->update('users', ['password_hash' => self::hashPassword($password)], ['id' => $user['id']]);
        }
        return $user;
    }
}
