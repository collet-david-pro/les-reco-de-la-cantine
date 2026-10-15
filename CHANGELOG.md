# Journal des versions

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/). Numérotation [semver](https://semver.org/lang/fr/).
Chaque version indique si elle contient une **mise à jour de la base** (bouton « Mettre à jour la base » après téléversement).

## [0.2.0] — 2026-10-15

**Mise à jour de la base : non** (simple remplacement des fichiers).

### Ajouté
- 🔔 **Notification de nouvelle version** : quand une nouvelle release est publiée sur GitHub, les administrateurs voient un bandeau
  et, dans *Administration › Mises à jour*, les nouveautés, la marche à suivre et le lien de téléchargement. Vérification au plus une fois
  par jour, bouton « Vérifier maintenant », désactivable avec `'check_updates' => false` dans `data/config.php`. Rien ne s'installe tout seul.
- 📖 Tutoriel de mise à jour dans chaque release et dans le fichier `MISE-A-JOUR.md` de l'archive.

### Corrigé
- Sur mobile, les résultats de recherche ne débordent plus de l'écran (le bouton « Choisir » était masqué).
- Sur la fiche d'un contenu, « Recommandé par … le … » ne se découpe plus en colonnes.

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
