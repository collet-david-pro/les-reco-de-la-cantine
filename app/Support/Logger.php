<?php
/**
 * Journal simple dans data/logs/app.log, avec rotation par taille (1 Mo, 3 fichiers).
 * Ne jamais y écrire de mot de passe ni de clé d'API.
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class Logger
{
    private const MAX_SIZE = 1_000_000;
    private const KEEP = 3;

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $dir = CANTINE_DATA . '/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            return;
        }
        $file = $dir . '/app.log';
        if (is_file($file) && filesize($file) > self::MAX_SIZE) {
            for ($i = self::KEEP - 1; $i >= 1; $i--) {
                if (is_file("$file.$i")) {
                    @rename("$file.$i", "$file." . ($i + 1));
                }
            }
            @rename($file, "$file.1");
        }
        $line = gmdate('Y-m-d H:i:s') . " [$level] " . $message;
        if ($context) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
