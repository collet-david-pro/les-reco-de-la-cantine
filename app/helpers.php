<?php
/**
 * Fonctions utilitaires globales utilisées dans les gabarits et les contrôleurs.
 */
declare(strict_types=1);

defined('CANTINE') || exit;

use Cantine\App;
use Cantine\Support\Csrf;
use Cantine\Support\Lang;
use Cantine\Support\Text;

/** Échappe une valeur pour l'affichage HTML. À utiliser pour TOUTE valeur affichée. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Texte de l'interface (app/lang/fr.php), avec remplacement des {variables}. */
function t(string $key, array $vars = []): string
{
    return Lang::get($key, $vars);
}

/** Texte au singulier ou au pluriel selon $n (en français : 0 et 1 au singulier). {n} est remplacé par le nombre. */
function t_plural(string $key, int $n, array $vars = []): string
{
    return Lang::plural($key, $n, $vars);
}

/** URL d'une page du site : url('liste', ['type' => 'film']) -> /index.php?route=liste&type=film */
function url(string $route = '', array $params = []): string
{
    $query = $route === '' ? $params : ['route' => $route] + $params;
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
    // Les « / » des routes restent lisibles (autorisés dans une query string).
    return App::baseUrl() . 'index.php' . ($query ? '?' . str_replace('%2F', '/', http_build_query($query)) : '');
}

/** URL d'un fichier statique, avec la version du site pour forcer le rafraîchissement du cache. */
function asset(string $path): string
{
    return App::baseUrl() . 'assets/' . $path . '?v=' . rawurlencode(CANTINE_VERSION);
}

/** Champ caché contenant le jeton CSRF, à placer dans chaque formulaire POST. */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Date relative en français ("il y a 2 jours"), puis absolue au-delà d'une semaine. */
function date_relative(?string $utc): string
{
    return Text::relativeDate($utc);
}

/** Date courte JJ/MM/AAAA dans le fuseau du site. */
function date_short(?string $utc): string
{
    return Text::shortDate($utc);
}

/** Nombre décimal à la française (3,5). */
function number_fr(float $n, int $decimals = 1): string
{
    return number_format($n, $decimals, ',', ' ');
}

/** Rend un gabarit partiel : <?= partial('partials/card', ['media' => $m]) ?> */
function partial(string $template, array $data = []): string
{
    return \Cantine\Support\View::render($template, $data);
}
