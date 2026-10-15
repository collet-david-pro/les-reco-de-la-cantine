<?php
/**
 * Registre des types de contenus : le SEUL endroit où ils sont définis.
 *
 * Ajouter un type = ajouter une entrée ici (et éventuellement un connecteur dans Services/Api/).
 * Aucune migration nécessaire : le type est stocké en texte dans media.type.
 *
 * Pour chaque type :
 *  - label / plural : libellés affichés ;
 *  - seen : libellé du statut "vu" pour ce type (Vu, Lu, Joué, Écouté) ;
 *  - icon : émoji affiché sur les boutons et les cartes ;
 *  - sources : connecteurs de recherche, par ordre de priorité : le premier configuré est utilisé (voir Services/Api/Sources.php) ;
 *  - extra : infos pratiques affichées sur la fiche (clé du champ JSON media.extra => [libellé, format]).
 */
declare(strict_types=1);

namespace Cantine;

defined('CANTINE') || exit;

final class Types
{
    public const ALL = [
        'film' => [
            'label' => 'Film',
            'plural' => 'Films',
            'seen' => 'Vu',
            'icon' => '🎬',
            'sources' => ['tmdb'],
            'extra' => [
                'director' => ['Réalisation', 'text'],
                'runtime' => ['Durée', 'minutes'],
                'original_title' => ['Titre original', 'text'],
            ],
        ],
        'serie' => [
            'label' => 'Série',
            'plural' => 'Séries',
            'seen' => 'Vue',
            'icon' => '📺',
            'sources' => ['tmdb'],
            'extra' => [
                'creators' => ['Création', 'text'],
                'seasons' => ['Saisons', 'number'],
                'episodes' => ['Épisodes', 'number'],
                'networks' => ['Diffusion', 'text'],
                'original_title' => ['Titre original', 'text'],
            ],
        ],
        'livre' => [
            'label' => 'Livre',
            'plural' => 'Livres',
            'seen' => 'Lu',
            'icon' => '📚',
            'sources' => ['openlibrary'],
            'extra' => [
                'author' => ['Auteur', 'text'],
                'pages' => ['Pages', 'number'],
            ],
        ],
        'jeu' => [
            'label' => 'Jeu vidéo',
            'plural' => 'Jeux vidéo',
            'seen' => 'Joué',
            'icon' => '🎮',
            'sources' => ['igdb', 'rawg', 'steam'], // IGDB et RAWG si configurés, sinon Steam (sans clé)
            'extra' => [
                'platforms' => ['Plateformes', 'text'],
                'developers' => ['Studio', 'text'],
            ],
        ],
        'video' => [
            'label' => 'Vidéo YouTube',
            'plural' => 'Vidéos YouTube',
            'seen' => 'Vue',
            'icon' => '▶️',
            'sources' => ['youtube'],
            'extra' => [
                'channel' => ['Chaîne', 'text'],
            ],
        ],
        'musique' => [
            'label' => 'Musique',
            'plural' => 'Musiques',
            'seen' => 'Écouté',
            'icon' => '🎵',
            'sources' => ['deezer'],
            'extra' => [
                'artist' => ['Artiste', 'text'],
                'album' => ['Album', 'text'],
                'kind' => ['Format', 'text'],
                'tracks' => ['Titres', 'number'],
                'duration' => ['Durée', 'seconds'],
            ],
        ],
    ];

    /** Statuts personnels, dans l'ordre d'affichage. "vu" est libellé selon le type. */
    public const STATUSES = ['a_decouvrir', 'en_cours', 'vu', 'pas_pour_moi'];

    public static function exists(string $type): bool
    {
        return isset(self::ALL[$type]);
    }

    public static function get(string $type): array
    {
        return self::ALL[$type] ?? [
            'label' => ucfirst($type), 'plural' => ucfirst($type), 'seen' => 'Vu', 'icon' => '⭐', 'sources' => [], 'extra' => [],
        ];
    }

    public static function label(string $type): string
    {
        return self::get($type)['label'];
    }

    public static function icon(string $type): string
    {
        return self::get($type)['icon'];
    }

    /** Libellé d'un statut pour un type donné ("Lu" pour un livre). Sans type : libellé générique. */
    public static function statusLabel(string $status, ?string $type = null): string
    {
        if ($status === 'vu' && $type !== null) {
            return self::get($type)['seen'];
        }
        return t('status.' . $status);
    }

    public static function isStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    /** Image de remplacement locale si la jaquette manque. */
    public static function placeholder(string $type): string
    {
        $file = self::exists($type) ? $type : 'autre';
        return \Cantine\App::baseUrl() . 'assets/img/placeholder-' . $file . '.svg';
    }

    /**
     * Infos pratiques mises en forme pour la fiche : liste de [libellé, valeur affichable].
     * @return list<array{0: string, 1: string}>
     */
    public static function formatExtra(string $type, array $extra): array
    {
        $lines = [];
        foreach (self::get($type)['extra'] as $key => [$label, $format]) {
            $value = $extra[$key] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }
            $value = match ($format) {
                'minutes' => \Cantine\Support\Text::duration((int) $value),
                'seconds' => \Cantine\Support\Text::durationSeconds((int) $value),
                default => (string) $value,
            };
            $lines[] = [$label, $value];
        }
        return $lines;
    }
}
