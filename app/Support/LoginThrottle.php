<?php
/**
 * Limitation des tentatives de connexion, par identifiant ET par adresse IP.
 * Après 5 échecs en 15 minutes : attente imposée croissante (1, 2, 4, 8 puis 15 minutes max).
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class LoginThrottle
{
    private const WINDOW = 900;       // 15 minutes
    private const FREE_ATTEMPTS = 5;
    private const MAX_WAIT = 900;     // 15 minutes

    public function __construct(private Db $db)
    {
    }

    /** Nombre de secondes à attendre avant de pouvoir réessayer (0 = autorisé). */
    public function secondsToWait(string $identifier, string $ip, ?int $now = null): int
    {
        $now ??= time();
        $since = gmdate('Y-m-d H:i:s', $now - self::WINDOW);
        $wait = 0;
        foreach (['identifier' => mb_strtolower(trim($identifier)), 'ip' => $ip] as $column => $value) {
            $row = $this->db->one(
                "SELECT COUNT(*) AS n, MAX(attempted_at) AS last FROM {login_attempts} WHERE $column = ? AND attempted_at >= ?",
                [$value, $since]
            );
            $failures = (int) ($row['n'] ?? 0);
            if ($failures >= self::FREE_ATTEMPTS && $row['last']) {
                $delay = min(60 * (2 ** ($failures - self::FREE_ATTEMPTS)), self::MAX_WAIT);
                $remaining = strtotime($row['last'] . ' UTC') + $delay - $now;
                $wait = max($wait, $remaining);
            }
        }
        return max(0, $wait);
    }

    public function recordFailure(string $identifier, string $ip): void
    {
        $this->db->insert('login_attempts', [
            'identifier' => mb_substr(mb_strtolower(trim($identifier)), 0, 100),
            'ip' => $ip,
            'attempted_at' => Db::now(),
        ]);
        // Purge des lignes de plus de 24 h, de temps en temps.
        if (random_int(1, 20) === 1) {
            $this->db->run('DELETE FROM {login_attempts} WHERE attempted_at < ?', [Db::ago(86400)]);
        }
    }

    public function clear(string $identifier): void
    {
        $this->db->delete('login_attempts', ['identifier' => mb_strtolower(trim($identifier))]);
    }
}
