# Journal des versions

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/). Numérotation [semver](https://semver.org/lang/fr/).
Chaque version indique si elle contient une **mise à jour de la base** (bouton « Mettre à jour la base » après téléversement).

## [0.1.0] — 2026-10-14

Première version, à valider par le propriétaire avant publication.

**Mise à jour de la base : oui** (création initiale, migration `001_init`).

### Ajouté
- Installeur en 3 étapes (environnement, base MySQL/MariaDB ou SQLite, compte administrateur), verrouillé après usage.
- Comptes créés par l'administrateur, « Rester connecté » 30 jours, changement de mot de passe, avatars à initiales.
- Recommandations : recherche TMDB (films, séries), Open Library (livres), RAWG (jeux), Deezer (albums et morceaux), YouTube (lien collé) ; ajout manuel ; alerte de doublon.
- Ma liste : statuts (À découvrir, En cours, Vu, Pas pour moi), notes de 1 à 5, filtres, tris, recherche, badge « nouveau ».
- Fiche contenu : infos pratiques, bande-annonce, « Où regarder » (JustWatch via TMDB), liens boutiques pour les livres, avis du groupe, commentaires avec spoiler.
- Fil « Quoi de neuf », profils des membres, tirage au sort « Que regarder ce soir ? ».
- Administration : membres, réglages et clés d'API, sauvegarde/restauration JSON portable, diagnostic, mode maintenance, données de démonstration, fusion de doublons.
- Application installable (PWA) avec page hors connexion.
