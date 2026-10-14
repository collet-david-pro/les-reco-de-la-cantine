<?php
/**
 * Rendu des gabarits PHP natifs (app/Views).
 * Dans un gabarit, les variables passées sont disponibles directement ($media, $user...).
 * Toute valeur affichée doit passer par e().
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class View
{
    /** Variables partagées par tous les gabarits (utilisateur courant, nom du site...). */
    public static array $shared = [];

    /** Rend une page complète : le gabarit $template inséré dans le layout. */
    public static function page(string $template, array $data = [], string $layout = 'layout/main'): string
    {
        $content = self::render($template, $data);
        return self::render($layout, $data + ['content' => $content]);
    }

    /** Rend un gabarit et renvoie le HTML. */
    public static function render(string $template, array $data = []): string
    {
        $file = CANTINE_APP . '/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Gabarit introuvable : ' . $template);
        }
        $data += self::$shared;
        ob_start();
        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}

