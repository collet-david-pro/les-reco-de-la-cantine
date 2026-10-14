<?php
/**
 * Messages "flash" affichés une seule fois après une action (succès, erreur).
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class Flash
{
    public static function success(string $message): void
    {
        $_SESSION['_flash'][] = ['type' => 'success', 'message' => $message];
    }

    public static function error(string $message): void
    {
        $_SESSION['_flash'][] = ['type' => 'error', 'message' => $message];
    }

    public static function info(string $message): void
    {
        $_SESSION['_flash'][] = ['type' => 'info', 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    public static function take(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($messages) ? $messages : [];
    }
}
