# Les reco de la cantine

Site **privé** de recommandations entre amis : films, séries, livres, jeux vidéo, vidéos YouTube et musiques.
Chacun a son compte, recommande des contenus au groupe, coche ce qu'il a vu, lu, joué ou écouté, note de 1 à 5 et en discute.

- Interface en français, pensée d'abord pour le téléphone, installable comme une application (PWA).
- Fiches remplies automatiquement grâce à TMDB, Open Library, RAWG, Deezer et YouTube, avec ajout manuel toujours possible.
- « Où regarder » (plateformes de streaming), liens vers des librairies, fil « Quoi de neuf », tirage au sort « Que regarder ce soir ? ».
- **Aucune intelligence artificielle** et aucune recommandation algorithmique : seulement les conseils des copains.
- PHP sans framework ni étape de compilation : vous téléversez les fichiers, l'installeur fait le reste.

Licence MIT.

---

## Sommaire

1. [Prérequis](#prérequis)
2. [Installation](#installation)
3. [Premiers pas](#premiers-pas)
4. [Clés d'API (TMDB, RAWG)](#clés-dapi-tmdb-rawg)
5. [Mise à jour](#mise-à-jour)
6. [Sauvegarde et restauration](#sauvegarde-et-restauration)
7. [Configuration du serveur web (Apache, nginx)](#configuration-du-serveur-web)
8. [Tester en local](#tester-en-local)
9. [Dépannage](#dépannage)
10. [Pour les développeurs](#pour-les-développeurs)

---

## Prérequis

- **PHP 8.1 ou plus récent** (testé de 8.1 à 8.5).
- Extensions PHP : `pdo`, `pdo_mysql` et/ou `pdo_sqlite`, `curl`, `mbstring`, `json`, `openssl`, `session`, `ctype`.
  Elles sont présentes chez presque tous les hébergeurs ; l'installeur les vérifie pour vous.
- Une base de données au choix :
  - **MySQL 5.7+ ou MariaDB 10.3+** (conseillé en ligne) : créez une base vide depuis le panneau de votre hébergeur ;
  - **SQLite** : rien à préparer, un simple fichier dans `data/` (idéal pour tester ou pour un petit groupe).
- **HTTPS** fortement conseillé (obligatoire pour installer le site comme une application sur téléphone).
- Le serveur doit pouvoir faire des requêtes HTTPS sortantes vers les API (sinon seul l'ajout manuel fonctionne).

## Installation

1. Téléchargez la dernière archive `cantine-vX.Y.Z.zip` depuis la page **Releases** du dépôt GitHub.
2. Décompressez-la et téléversez le contenu du dossier `les-reco-de-la-cantine/` sur votre hébergement
   (à la racine du domaine ou dans un sous-dossier, les deux fonctionnent).
3. Ouvrez `https://votre-site/install.php` dans le navigateur et suivez les étapes :
   1. **vérification du serveur** (✔ / ⚠ / ✘ ; les ⚠ n'empêchent pas de continuer) ;
   2. **base de données** : MySQL/MariaDB ou SQLite, avec un bouton « Tester la connexion » qui explique chaque problème ;
   3. **compte administrateur** (mot de passe de 12 caractères minimum).
4. L'installeur crée les tables, le dossier `data/` (protégé du web) et le fichier `data/config.php`, puis se verrouille.
5. Supprimez `install.php` du serveur (conseillé, même s'il refuse de se relancer).

> Si l'hébergement interdit l'écriture de `data/config.php`, l'installeur affiche son contenu : créez le fichier à la main par FTP.

## Premiers pas

- **Administration › Réglages** : saisissez les clés TMDB et RAWG (voir ci-dessous), le nom du site, le pays pour « Où regarder » et les liens boutiques.
- **Administration › Membres** : créez les comptes de vos amis. Il n'y a pas d'inscription publique.
  Un mot de passe provisoire facile à dicter peut être généré ; il est affiché une seule fois et la personne devra le changer à sa première connexion.
- **Mot de passe oublié ?** Il n'y a pas d'envoi d'e-mail : l'administrateur réinitialise le mot de passe depuis la fiche du membre.
- **Données de démonstration** : depuis **Administration**, chargez de faux membres et une vingtaine de contenus pour découvrir le site, puis supprimez-les en un clic.
  Les vraies données ne sont jamais touchées.

## Clés d'API (TMDB, RAWG)

Les livres (Open Library), la musique (Deezer) et YouTube fonctionnent **sans clé**. Pour les films, séries et jeux, il faut deux clés gratuites.
Sans clé, la recherche automatique est simplement désactivée pour ce type et l'ajout manuel reste disponible.

**TMDB (films et séries)**
1. Créez un compte sur [themoviedb.org](https://www.themoviedb.org/signup).
2. Allez dans *Paramètres › API* et demandez une clé (usage « personnel / non commercial »).
3. Copiez la **clé d'API** (32 caractères) **ou** le **jeton d'accès en lecture** (long texte commençant par `eyJ`) : les deux sont acceptés.
4. Collez-la dans *Administration › Réglages*, enregistrez puis cliquez sur « Tester la clé TMDB ».

TMDB impose une attribution : elle figure dans le pied de page et la page « À propos ». Les disponibilités « Où regarder » sont fournies par JustWatch (attribution affichée à côté).

**RAWG (jeux vidéo)**
1. Créez un compte sur [rawg.io](https://rawg.io/signup), puis rendez-vous sur [rawg.io/apidocs](https://rawg.io/apidocs).
2. Obtenez la clé gratuite et collez-la dans *Administration › Réglages*.

Les clés restent sur le serveur : elles ne sont jamais envoyées aux navigateurs, n'apparaissent pas dans les journaux et sont exclues des sauvegardes par défaut.

## Mise à jour

Vos données et votre configuration sont dans `data/`, qui n'est **jamais** dans l'archive : une mise à jour ne les écrase pas.

1. **Administration › Sauvegarde** : téléchargez une sauvegarde (par prudence).
2. **Administration** : activez le **mode maintenance** (les membres voient une page d'attente, vous gardez l'accès).
3. Téléversez les fichiers de la nouvelle version **par-dessus** les anciens (sans toucher à `data/`). Ne renvoyez pas `install.php` (ou supprimez-le ensuite).
4. Si la nouvelle version modifie la base, le site affiche à l'administrateur « Mise à jour de la base nécessaire » : cliquez sur **Mettre à jour la base**.
5. Désactivez le mode maintenance.

Le fichier `CHANGELOG.md` indique pour chaque version si une mise à jour de la base est incluse.

## Sauvegarde et restauration

**Administration › Sauvegarde**

- **Télécharger une sauvegarde** : un fichier JSON daté (`cantine-sauvegarde-AAAA-MM-JJ.json`) contenant comptes, contenus, statuts, notes, commentaires et réglages.
  Il contient les mots de passe **chiffrés** des membres : gardez-le en lieu sûr.
  Les clés d'API et les données de démonstration en sont exclues, sauf si vous cochez les cases correspondantes.
- **Restaurer** : le fichier est entièrement vérifié avant tout changement, un résumé est affiché, puis la restauration demande une confirmation
  et votre mot de passe. Elle se fait en une seule fois (tout ou rien). Une **sauvegarde de sécurité** de l'état actuel est créée juste avant
  (les 5 dernières sont gardées et téléchargeables depuis la même page).
- La sauvegarde est **portable** : une sauvegarde faite sous MySQL se restaure sous SQLite et inversement, et une sauvegarde
  d'une version plus ancienne du site se restaure sur une version plus récente.
- Si l'envoi échoue parce que le fichier est trop gros, augmentez `upload_max_filesize` et `post_max_size` (la page affiche les limites actuelles).

## Configuration du serveur web

Le site fonctionne même si **tout le dossier** est servi tel quel (cas de la plupart des hébergements mutualisés).
Les dossiers internes (`app/`, `migrations/`, `data/`) sont protégés de trois façons : fichiers `.htaccess`, règle nginx ci-dessous,
et une garde PHP en tête de chaque fichier. La page **Administration › Diagnostic** vérifie que `data/` n'est pas lisible depuis le web
et affiche une alerte rouge dans le cas contraire.

**Apache** : rien à faire, les fichiers `.htaccess` fournis s'en chargent (`AllowOverride All` doit être autorisé, ce qui est le cas par défaut chez la plupart des hébergeurs).

**nginx + PHP-FPM** : nginx ignore les `.htaccess`, ajoutez ces règles à votre bloc `server` :

```nginx
# Dossiers internes et fichiers sensibles : interdits
location ~ ^/(app|migrations|data|tests|tools)/ { deny all; return 404; }
location ~ /\.                                  { deny all; return 404; }
location ~ \.(md|sh|lock|sqlite|log)$           { deny all; return 404; }

index index.php;
location / { try_files $uri $uri/ /index.php?$query_string; }

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # à adapter
}

location = /sw.js { add_header Cache-Control "no-cache"; }
```

Si le site est dans un sous-dossier, préfixez les chemins (`^/cantine/(app|migrations|data|tests|tools)/`).

**Derrière un proxy inverse** (HTTPS terminé par le proxy) : dans `data/config.php`, passez `trust_proxy` à `true`
et, si l'adresse du site est mal détectée, renseignez `base_url` (ex. `'https://cantine.example.org/'`).

**Options de `data/config.php`** : `timezone` (fuseau d'affichage, `Europe/Paris` par défaut), `session_idle` (déconnexion après inactivité, en secondes ;
la case « Rester connecté » garde la session 30 jours), `debug` (affiche le détail des erreurs — à ne jamais laisser activé en ligne).

## Tester en local

Il suffit de PHP (aucun serveur web nécessaire) :

```bash
php -S localhost:8000 tools/dev-router.php
```

puis ouvrez <http://localhost:8000/>. L'installeur se lance ; choisissez SQLite pour aller au plus vite.
Le petit routeur `tools/dev-router.php` remplace les `.htaccess` (que le serveur intégré de PHP ignore) et bloque les dossiers internes.

Pour repartir de zéro en local : arrêtez le serveur et supprimez le dossier `data/`.

## Dépannage

| Symptôme | Solution |
|---|---|
| Page blanche ou « Erreur inattendue » | Consultez `data/logs/app.log`. En local, passez `'debug' => true` dans `data/config.php` pour voir le détail. |
| « La base de données est injoignable » | Vérifiez les informations de connexion dans `data/config.php` et que le serveur MySQL fonctionne. |
| Impossible de rester connecté en local | Normal si vous utilisez `https://` sans certificat : utilisez `http://localhost`. |
| « Le formulaire a expiré » | La page est restée ouverte trop longtemps : rechargez-la. |
| La recherche de films ne fonctionne pas | Clé TMDB absente ou refusée : *Réglages › Tester la clé TMDB*. Vérifiez aussi le réseau sortant dans *Diagnostic*. |
| Alerte rouge « data/ lisible depuis le web » | Sous Apache, vérifiez que les `.htaccess` sont pris en compte ; sous nginx, ajoutez les règles ci-dessus. |
| L'application ne s'installe pas sur le téléphone | Le site doit être en HTTPS. |
| Trop de tentatives de connexion | Après 5 échecs en 15 minutes, l'attente augmente (jusqu'à 15 min). Patientez, ou réinitialisez le mot de passe depuis l'admin. |
| Mot de passe administrateur perdu | Un autre administrateur peut le réinitialiser. S'il n'y en a pas : restaurez une sauvegarde, ou (avancé) remplacez `password_hash` du compte en base par le résultat de `php -r "echo password_hash('nouveau-mot-de-passe', PASSWORD_DEFAULT);"`. |

**Réinstaller sur une base existante** (par exemple après la perte de `data/config.php`) : l'installeur refuse une base qui contient déjà un site,
pour ne rien écraser. Recréez simplement `data/config.php` (modèle ci-dessous) avec les bons paramètres, et le site reprend avec ses données.

```php
<?php
defined('CANTINE') || exit;
return [
    'db' => ['driver' => 'mysql', 'host' => 'localhost', 'port' => '', 'name' => 'ma_base', 'user' => 'mon_utilisateur', 'password' => '...', 'prefix' => ''],
    // ou : 'db' => ['driver' => 'sqlite', 'path' => 'cantine.sqlite', 'prefix' => ''],
    'secret' => 'une-longue-chaine-aleatoire',
    'site_name' => 'Les reco de la cantine',
    'base_url' => '',
    'trust_proxy' => false,
    'timezone' => 'Europe/Paris',
    'session_idle' => 7200,
    'debug' => false,
];
```

## Pour les développeurs

- `projet.md` : document de cadrage (périmètre, décisions, consignes techniques). À lire en premier.
- Structure : `index.php` (contrôleur frontal), `install.php`, `app/` (code : `Controllers/`, `Repositories/`, `Services/`, `Support/`, `Views/`, `lang/fr.php`, `Types.php`), `migrations/`, `assets/`.
- Ajouter un type de contenu : une entrée dans `app/Types.php` (+ un connecteur dans `app/Services/Api/` si besoin). Aucune migration.
- Textes de l'interface : `app/lang/fr.php`.
- Palette de couleurs : un seul bloc de variables en tête de `assets/css/app.css` ; trois propositions sont visibles dans `assets/palettes.html`.
- Base de données : nouvelle migration = nouveau fichier `migrations/NNN_description.php` (ne jamais modifier une migration publiée),
  avec si besoin une fonction d'adaptation des sauvegardes dans `app/Services/Backup.php` (`upgrade()`).

**Tests** (aucun outil à installer, aucun appel réseau) :

```bash
php tests/run.php                       # sur SQLite
CANTINE_TEST_MYSQL="host=127.0.0.1;name=cantine_test;user=root;password=" php tests/run.php   # SQLite + MySQL
```

La base MySQL de test doit exister ; ses tables préfixées `t_` sont recréées à chaque test.

**Publier une version** : mettez à jour `VERSION` et `CHANGELOG.md`, lancez les tests sur les deux moteurs, puis :

```bash
tools/build-release.sh        # produit dist/cantine-vX.Y.Z.zip
```

L'archive contient un dossier `les-reco-de-la-cantine/` sans `data/`, `tests/`, `tools/`, fichiers git ni `projet.md`.
Joignez-la à une *release* GitHub `vX.Y.Z` avec les notes du CHANGELOG.

Icônes de l'application : `php tools/make-icons.php` (nécessite l'extension GD, en développement seulement).

## Crédits

- Données films et séries : [TMDB](https://www.themoviedb.org/). Ce produit utilise l'API TMDB mais n'est ni approuvé ni certifié par TMDB.
- Disponibilités « Où regarder » : [JustWatch](https://www.justwatch.com/), via TMDB.
- Livres : [Open Library](https://openlibrary.org/). Jeux vidéo : [RAWG](https://rawg.io/). Musique : [Deezer](https://www.deezer.com/). Vidéos : YouTube (oEmbed).
