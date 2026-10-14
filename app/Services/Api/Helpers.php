<?php
/**
 * Petites fonctions communes aux connecteurs pour lire prudemment les réponses d'API.
 * Une API qui change de format ne doit pas casser la page : on vérifie chaque champ.
 */
declare(strict_types=1);

namespace Cantine\Services\Api;

defined('CANTINE') || exit;

final class Helpers
{
    public static function str(mixed $value, int $max = 255): ?string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    public static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /** Année à partir d'une date "2014-11-05" ou d'un entier. */
    public static function year(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 1000 && $value < 3000 ? $value : null;
        }
        if (is_string($value) && preg_match('/^(\d{4})/', $value, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /** URL https uniquement (les jaquettes et liens venant des API). */
    public static function httpsUrl(mixed $value): ?string
    {
        if (!is_string($value) || !str_starts_with($value, 'https://') || strlen($value) > 500) {
            return null;
        }
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }

    public static function arr(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /** Extrait les valeurs d'un champ dans une liste d'objets : names([{name: 'A'}, ...], 'name'). */
    public static function names(mixed $list, string $field = 'name', int $max = 10): array
    {
        $names = [];
        foreach (self::arr($list) as $item) {
            $name = self::str(is_array($item) ? ($item[$field] ?? null) : null, 100);
            if ($name !== null && !in_array($name, $names, true)) {
                $names[] = $name;
            }
            if (count($names) >= $max) {
                break;
            }
        }
        return $names;
    }

    public static function excerpt(?string $text, int $max = 120): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($text)));
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
    }
}
