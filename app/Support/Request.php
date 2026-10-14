<?php
/**
 * Lecture des paramètres de la requête, avec conversion de type.
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class Request
{
    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    /** Requête fetch() qui attend du JSON. */
    public static function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    /** Paramètre GET (texte, nettoyé des espaces). */
    public static function query(string $name, string $default = ''): string
    {
        $value = $_GET[$name] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    public static function queryInt(string $name, int $default = 0): int
    {
        $value = $_GET[$name] ?? null;
        return is_string($value) && preg_match('/^-?\d{1,9}$/', $value) ? (int) $value : $default;
    }

    /** Paramètre POST (texte, nettoyé des espaces). */
    public static function post(string $name, string $default = ''): string
    {
        $value = $_POST[$name] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    /** Paramètre POST sans nettoyage (mots de passe). */
    public static function postRaw(string $name): string
    {
        $value = $_POST[$name] ?? '';
        return is_string($value) ? $value : '';
    }

    public static function postInt(string $name, int $default = 0): int
    {
        $value = $_POST[$name] ?? null;
        return is_string($value) && preg_match('/^-?\d{1,9}$/', $value) ? (int) $value : $default;
    }

    public static function postBool(string $name): bool
    {
        return isset($_POST[$name]) && $_POST[$name] !== '' && $_POST[$name] !== '0';
    }
}
