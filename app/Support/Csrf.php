<?php
/**
 * Protection CSRF : un jeton par session, envoyé dans le champ _csrf des formulaires
 * ou dans l'en-tête X-CSRF-Token des requêtes fetch().
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function check(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals(self::token(), $token);
    }

    /** Jeton envoyé par la requête courante (formulaire ou en-tête). */
    public static function fromRequest(): ?string
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return is_string($token) ? $token : null;
    }
}
