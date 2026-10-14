<?php
/**
 * Petites fonctions de traitement de texte : normalisation des titres, initiales, dates, commentaires.
 */
declare(strict_types=1);

namespace Cantine\Support;

defined('CANTINE') || exit;

final class Text
{
    /** Fuseau d'affichage (option timezone de config.php). */
    public static string $timezone = 'Europe/Paris';

    private const ARTICLES = ['le', 'la', 'les', 'l', 'un', 'une', 'des', 'the', 'a', 'an'];

    /**
     * Titre normalisé pour la détection de doublons et la recherche :
     * minuscules, sans accents, sans ponctuation, sans article en tête, espaces simples.
     * "L'Été meurtrier !" -> "ete meurtrier"
     */
    public static function normalize(string $title): string
    {
        $text = mb_strtolower(trim($title), 'UTF-8');
        $text = self::removeAccents($text);
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);
        $words = array_values(array_filter(explode(' ', $text), static fn ($w) => $w !== ''));
        if (count($words) > 1 && in_array($words[0], self::ARTICLES, true)) {
            array_shift($words);
        }
        return mb_substr(implode(' ', $words), 0, 190);
    }

    public static function removeAccents(string $text): string
    {
        static $map = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
            'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i',
            'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ö' => 'o', 'ø' => 'o', 'œ' => 'oe', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss', 'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ä' => 'a',
            'Ç' => 'c', 'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e', 'Î' => 'i', 'Ï' => 'i',
            'Ô' => 'o', 'Ö' => 'o', 'Œ' => 'oe', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
        ];
        return strtr($text, $map);
    }

    /** Initiales (2 lettres max) à partir du nom affiché : "Jean-Paul Dupont" -> "JD", "Chloé" -> "CH". */
    public static function initials(string $name): string
    {
        $words = preg_split('/[\s\-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$words) {
            return '?';
        }
        if (count($words) === 1) {
            return mb_strtoupper(mb_substr($words[0], 0, 2));
        }
        return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[count($words) - 1], 0, 1));
    }

    public static function toLocal(string $utc): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($utc, new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone(self::$timezone));
    }

    public static function shortDate(?string $utc): string
    {
        return $utc ? self::toLocal($utc)->format('d/m/Y') : '';
    }

    /** "à l'instant", "il y a 5 min", "il y a 3 h", "hier", "il y a 4 jours", puis "le 12/10/2026". */
    public static function relativeDate(?string $utc, ?int $now = null): string
    {
        if (!$utc) {
            return '';
        }
        $now ??= time();
        $time = strtotime($utc . ' UTC');
        $diff = $now - $time;
        if ($diff < 60) {
            return Lang::get('date.now');
        }
        if ($diff < 3600) {
            return Lang::plural('date.minutes', intdiv($diff, 60));
        }
        if ($diff < 86400) {
            return Lang::plural('date.hours', intdiv($diff, 3600));
        }
        $days = intdiv($diff, 86400);
        if ($days === 1) {
            return Lang::get('date.yesterday');
        }
        if ($days < 7) {
            return Lang::plural('date.days', $days);
        }
        return Lang::get('date.on', ['date' => self::shortDate($utc)]);
    }

    /**
     * Commentaire en texte brut -> HTML sûr : échappement, liens cliquables, sauts de ligne.
     * L'échappement est fait AVANT toute transformation.
     */
    public static function commentHtml(string $text): string
    {
        $html = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = (string) preg_replace_callback(
            '~\bhttps?://[^\s<>"\']+~i',
            static function (array $m): string {
                $url = rtrim($m[0], '.,;:!?)');
                $rest = substr($m[0], strlen($url));
                return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer nofollow">' . $url . '</a>' . $rest;
            },
            $html
        );
        return nl2br($html, false);
    }

    /** Mot de passe lisible généré : trois mots et deux chiffres, ex. "pomme-radis-tulipe-42". */
    public static function readablePassword(): string
    {
        $words = ['pomme', 'radis', 'tulipe', 'cerise', 'nuage', 'piano', 'canard', 'olive', 'fusee', 'citron',
            'loutre', 'violon', 'tomate', 'banjo', 'cactus', 'gaufre', 'hibou', 'menthe', 'navet', 'poivre',
            'renard', 'sapin', 'tigre', 'velo', 'zebre', 'brioche', 'comete', 'dauphin', 'etoile', 'fraise'];
        $picked = [];
        for ($i = 0; $i < 3; $i++) {
            $picked[] = $words[random_int(0, count($words) - 1)];
        }
        return implode('-', $picked) . '-' . random_int(10, 99);
    }

    /** Durée en minutes -> "1 h 52" ou "45 min". */
    public static function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        return intdiv($minutes, 60) . ' h ' . str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    /** Durée en secondes -> "3 min 05" ou "1 h 12". */
    public static function durationSeconds(int $seconds): string
    {
        if ($seconds >= 3600) {
            return self::duration(intdiv($seconds, 60));
        }
        return intdiv($seconds, 60) . ' min ' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }
}
