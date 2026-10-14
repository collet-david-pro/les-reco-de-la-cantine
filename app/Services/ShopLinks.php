<?php
/**
 * Liens de recherche vers des boutiques pour les livres, construits depuis des modèles d'URL
 * ({isbn} ou {titre}), sans API ni compte affilié. Modèles configurables dans l'admin.
 */
declare(strict_types=1);

namespace Cantine\Services;

defined('CANTINE') || exit;

final class ShopLinks
{
    /** Modèles par défaut (à vérifier avant chaque release : les URLs de recherche des sites changent). */
    public const DEFAULTS = [
        ['nom' => 'Amazon', 'url' => 'https://www.amazon.fr/s?k={isbn}'],
        ['nom' => 'Fnac', 'url' => 'https://www.fnac.com/SearchResult/ResultList.aspx?Search={isbn}'],
        ['nom' => 'Place des Libraires', 'url' => 'https://www.placedeslibraires.fr/listeliv.php?base=allbooks&mots_recherche={isbn}'],
    ];

    /**
     * Liens pour un livre : {isbn} si disponible, sinon {titre} = "titre auteur".
     * Un modèle qui n'utilise que {isbn} est masqué pour un livre sans ISBN.
     * @return list<array{nom: string, url: string}>
     */
    public static function build(array $templates, ?string $isbn, string $title, ?string $author): array
    {
        $links = [];
        $titleQuery = trim($title . ' ' . ($author ?? ''));
        foreach ($templates as $template) {
            $url = (string) ($template['url'] ?? '');
            $name = (string) ($template['nom'] ?? '');
            if ($url === '' || $name === '') {
                continue;
            }
            if (str_contains($url, '{isbn}')) {
                if ($isbn !== null && $isbn !== '') {
                    $url = str_replace('{isbn}', rawurlencode($isbn), $url);
                } elseif (str_contains($url, '{titre}')) {
                    $url = str_replace('{isbn}', '', $url);
                } else {
                    // Pas d'ISBN : on se rabat sur le titre dans le même paramètre.
                    $url = str_replace('{isbn}', rawurlencode($titleQuery), $url);
                }
            }
            $url = str_replace('{titre}', rawurlencode($titleQuery), $url);
            $links[] = ['nom' => $name, 'url' => $url];
        }
        return $links;
    }

    /**
     * Valide la liste saisie dans l'admin (une ligne par boutique : "Nom | URL").
     * @return array{0: list<array{nom: string, url: string}>, 1: list<string>} [liens valides, erreurs]
     */
    public static function parse(string $text): array
    {
        $links = [];
        $errors = [];
        foreach (preg_split('/\R/', $text) ?: [] as $number => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            if (count($parts) !== 2) {
                $errors[] = t('settings.shop_line_format', ['line' => (string) ($number + 1)]);
                continue;
            }
            [$name, $url] = $parts;
            if ($name === '' || mb_strlen($name) > 30) {
                $errors[] = t('settings.shop_name_invalid', ['line' => (string) ($number + 1)]);
            } elseif (!str_starts_with($url, 'https://') || !filter_var(str_replace(['{isbn}', '{titre}'], 'x', $url), FILTER_VALIDATE_URL)) {
                $errors[] = t('settings.shop_url_https', ['line' => (string) ($number + 1)]);
            } elseif (!str_contains($url, '{isbn}') && !str_contains($url, '{titre}')) {
                $errors[] = t('settings.shop_url_marker', ['line' => (string) ($number + 1)]);
            } else {
                $links[] = ['nom' => $name, 'url' => $url];
            }
        }
        return [$links, $errors];
    }
}
