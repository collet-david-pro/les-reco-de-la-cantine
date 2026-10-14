<?php
/**
 * Textes de l'interface, regroupés dans app/lang/fr.php.
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class Lang
{
    private static ?array $texts = null;

    public static function all(): array
    {
        return self::$texts ??= require CANTINE_APP . '/lang/fr.php';
    }

    public static function get(string $key, array $vars = []): string
    {
        $text = self::all()[$key] ?? $key;
        if (is_array($text)) {
            $text = $text['one'] ?? $key;
        }
        return self::replace((string) $text, $vars);
    }

    /** En français, 0 et 1 sont au singulier. */
    public static function plural(string $key, int $n, array $vars = []): string
    {
        $text = self::all()[$key] ?? $key;
        if (is_array($text)) {
            $text = abs($n) <= 1 ? ($text['one'] ?? $key) : ($text['many'] ?? $key);
        }
        return self::replace((string) $text, $vars + ['n' => (string) $n]);
    }

    private static function replace(string $text, array $vars): string
    {
        foreach ($vars as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }
}
