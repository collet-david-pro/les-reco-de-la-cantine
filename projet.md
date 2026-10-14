# Les reco de la cantine

> Nom du site : **Les reco de la cantine** (dossier de travail : Filmo).
> Document de cadrage destiné aux agents IA qui travailleront sur ce projet.
> Statut : **version 0.1.0 développée** (étapes 1 à 7 de la section 11). Reste à faire par le propriétaire : choisir la palette (section 13) et vérifier les points listés en section 13.

<!--
NOTE AUX AGENTS IA
Les blocs de commentaires HTML comme celui-ci ne s'affichent pas dans un rendu Markdown.
Ils contiennent des consignes techniques détaillées pour la construction du projet.
- Le texte visible = ce que veut le propriétaire (périmètre, décisions).
- Les commentaires = comment le construire (conseils d'implémentation, pièges connus, choix par défaut).
En cas de conflit, le texte visible l'emporte. Un "choix par défaut" dans un commentaire peut être
appliqué sans demander ; un point marqué "À VALIDER" doit être soumis au propriétaire avant d'être codé.
Quand une décision est prise, mettre à jour le texte visible ET le commentaire concerné.
-->

## 1. Résumé

Site web **privé pour un groupe d'amis**, distribué ensuite en **projet public open source sur GitHub** (chacun peut l'installer chez soi). Chacun se connecte avec **son propre compte** (identifiant + mot de passe) et voit **sa liste personnelle**.

Chacun peut **recommander** un contenu à tout le groupe. Les autres le voient dans leur liste, le cochent quand ils l'ont vu/lu/joué/écouté, le notent et en discutent.

**Taille visée** : de 2 à 50 amis, voire plus. La vraie limite est le nombre de comptes et la taille de la base : prévoir index SQL, pagination des listes et requêtes efficaces.

Aucune IA sur le site final : l'IA sert uniquement à le concevoir et le développer.

<!--
AGENTS — Esprit du projet
- Public cible : des amis non techniciens, surtout sur téléphone. Chaque écran doit être compréhensible sans aide.
- Le propriétaire n'est pas forcément développeur PHP : le code doit être lisible, commenté là où c'est utile,
  sans magie (pas de méta-programmation, pas d'abstraction "au cas où").
- Volumétrie réaliste : quelques milliers de contenus, quelques dizaines de comptes, quelques dizaines de milliers
  de lignes user_media. Pas besoin de cache applicatif complexe, mais AUCUNE requête N+1 dans les listes
  (charger statuts, notes moyennes et compteurs en une ou deux requêtes agrégées par page).
- "Sans IA" = aucun appel à un service d'IA, aucune recommandation algorithmique opaque. Les tris et
  tirages au sort sont de simples requêtes SQL.
-->

## 2. Types de contenus

Films, séries, livres, jeux vidéo, vidéos YouTube, musiques.
Le modèle de données doit permettre d'ajouter d'autres types plus tard sans refonte.

<!--
AGENTS — Types
- Définir les types dans UN SEUL endroit du code (ex. app/Types.php) : code interne, libellé français,
  verbe de statut, icône, source(s) de recherche, champs d'infos pratiques affichés.
  Codes conseillés : film, serie, livre, jeu, video, musique.
- Les libellés de statut dépendent du type : "Vu" (film, série, vidéo), "Lu" (livre), "Joué" (jeu),
  "Écouté" (musique). En base, le statut reste générique ("vu") ; seul l'affichage change.
- Ajouter un type plus tard = ajouter une entrée dans ce registre (+ éventuellement un connecteur d'API),
  sans migration de schéma : le type est stocké en VARCHAR, pas en ENUM.
-->

## 3. Fonctionnalités

Toutes les fonctionnalités ci-dessous font partie du périmètre. L'ordre de développement conseillé est donné en section 11.

### 3.1 Comptes et administration
- **Connexion** par identifiant + mot de passe. Pas d'inscription publique : **l'administrateur crée les comptes**.
- **Case « Rester connecté »** à la connexion (30 jours sur cet appareil). Sans la case, la session expire après une période d'inactivité.
- **Mot de passe modifiable par l'utilisateur** (ancien mot de passe demandé). **Pas d'e-mail ni de récupération automatique** : mot de passe perdu = contacter l'admin, qui le réinitialise.
- **Avatar simple** : initiales sur une couleur choisie par l'utilisateur (aucun envoi d'image).
- **Espace admin** : créer / désactiver / supprimer des comptes, réinitialiser un mot de passe, configurer les clés d'API et les liens boutiques, modifier ou supprimer n'importe quel contenu ou commentaire, sauvegarder et restaurer la base (section 6), page de diagnostic, **mode maintenance**, **données de démonstration** (ajout et retrait en un clic).
- **Données de démonstration** : depuis le panneau admin, l'admin peut charger un jeu de faux comptes et de contenus d'exemple pour tester ou présenter le site, puis **tout supprimer en un clic** sans toucher aux vraies données.
- **Mode maintenance** : l'admin ferme temporairement le site (pendant une mise à jour par exemple). Les membres voient un message ; l'admin garde l'accès.

<!--
AGENTS — Comptes et admin
- Identifiant : unique, insensible à la casse (stocker en minuscules), lettres/chiffres/tirets, 3 à 30 caractères.
  Séparer "identifiant" (connexion) et "nom affiché" (ce que voient les autres, modifiable par l'utilisateur).
- Mot de passe : 8 caractères minimum, pas de règles de complexité imposées (recommandation NIST),
  12 minimum conseillé pour le compte admin à l'installation.
- Création d'un compte par l'admin : l'admin saisit un mot de passe provisoire OU le site en génère un lisible
  (ex. 3 mots + chiffres) affiché UNE fois. Option conseillée : forcer le changement à la première connexion
  (colonne must_change_password).
- Réinitialisation par l'admin : même mécanisme ; invalider les sessions existantes de ce compte
  (colonne session_version incrémentée, comparée à chaque requête).
- Désactivation (actif = 0) : le compte ne peut plus se connecter, ses recos/notes/commentaires restent visibles.
  C'est l'action à privilégier dans l'interface.
- Suppression définitive (choix par défaut, confirmation obligatoire) : ses statuts et notes sont supprimés,
  ses commentaires supprimés, ses contenus conservés avec ajouté_par = NULL affiché "Ancien membre".
- Garde-fous : impossible de supprimer, désactiver ou rétrograder le DERNIER admin ; un admin ne peut pas
  se supprimer lui-même. Prévoir le rôle admin pour plusieurs comptes (promouvoir un membre).
- Avatar : palette fixe de ~12 couleurs accessibles (contraste AA avec texte blanc), initiales calculées
  depuis le nom affiché (2 lettres max, gérer accents et noms d'un seul mot). Rendu en HTML/CSS, pas d'image.
- Mode maintenance : flag en table settings. Si actif : toute requête d'un non-admin reçoit une page
  "Le site est en maintenance" avec HTTP 503 + en-tête Retry-After. La page de connexion reste accessible
  pour que l'admin puisse se connecter. Bandeau visible pour l'admin tant que le mode est actif.
- "Rester connecté" (VALIDÉ) : case décochée par défaut. Si cochée : cookie cantine_remember de 30 jours
  contenant "selecteur:validateur" (deux valeurs aléatoires) ; en base, table remember_tokens avec le sélecteur
  en clair et le HASH (sha256) du validateur, user_id, expires_at. À chaque reconnexion automatique : vérifier
  avec hash_equals, puis faire tourner le jeton (nouveau validateur). Cookie HttpOnly, SameSite=Lax, Secure si HTTPS.
  Supprimer tous les jetons de l'utilisateur à : déconnexion (option "déconnecter tous mes appareils" dans le
  profil), changement ou réinitialisation de mot de passe, désactivation du compte. Purge des jetons expirés.
  Une reconnexion par jeton ne doit PAS donner accès aux actions sensibles sans mot de passe : le changement de mot
  de passe demande déjà l'ancien, la restauration demande le mot de passe admin (section 6).

- Données de démonstration (VALIDÉ, dans le panneau admin uniquement, pas dans l'installeur) :
  - Bouton "Charger les données de démo" : crée ~4 faux membres (ex. Alice, Bruno, Chloé, Driss, mot de passe
    affiché à l'écran, tous désactivables), ~20 contenus répartis sur tous les types, des statuts, des notes et
    quelques commentaires (dont un spoiler). Données stockées dans un fichier app/demo/demo.json.
  - Les contenus de démo sont des AJOUTS MANUELS avec des titres fictifs ou très connus et SANS image externe
    (images de remplacement locales) : ça marche sans clé d'API ni réseau.
  - Chaque ligne créée est marquée (colonne is_demo sur users, media, comments) ; les user_media de démo sont
    ceux des comptes de démo.
  - Bouton "Supprimer les données de démo" : supprime uniquement ce qui est marqué is_demo (et, par cascade,
    les statuts/notes/commentaires liés), y compris les notes ou commentaires de vrais membres posés sur un
    contenu de démo (avertir avant). Ne touche jamais aux vraies données.
  - Bandeau visible pour tous tant que des données de démo sont présentes ("Données de démonstration chargées").
  - Les données de démo sont exclues de l'export de sauvegarde par défaut (case à cocher pour les inclure).
-->

### 3.2 Recommander
- **Ajouter une recommandation** : choisir le type, rechercher le titre, sélectionner le bon résultat. Titre, année, jaquette, genres, infos pratiques et liens sont récupérés automatiquement.
- **Ajout manuel** (titre + lien libre) quand la recherche ne trouve rien, ou si l'API est indisponible ou sans clé.
- **Alerte de doublon** : si le contenu existe déjà, l'afficher (« déjà recommandé par Paul le 3 mars ») au lieu d'en créer un second ; l'utilisateur est alors simplement marqué « vu » dessus.
- **Auto-coché** : celui qui ajoute un contenu l'a automatiquement « vu ».
- **Modifier / supprimer sa propre recommandation** (l'admin peut tout modifier).
- **Une recommandation est visible par tout le groupe** (pas de destinataires ciblés).

<!--
AGENTS — Flux d'ajout
1. L'utilisateur choisit le type (gros boutons/icônes, pas une liste déroulante sur mobile).
2. Champ de recherche avec anti-rebond côté navigateur (~400 ms, minimum 2 caractères) ; appel AJAX
   vers une route interne (ex. ?route=api/search&type=film&q=...) qui interroge l'API externe côté serveur.
   Pour YouTube : champ "coller le lien" au lieu d'une recherche.
3. Résultats en liste avec miniature, titre, année et une info distinctive (réalisateur, auteur, plateforme,
   artiste) pour départager les homonymes. Au-dessus des résultats : les contenus DÉJÀ présents sur le site
   qui correspondent (alerte de doublon précoce).
4. L'utilisateur choisit un résultat : le navigateur n'envoie QUE (type, source, id_externe) + jeton CSRF.
   Le serveur va lui-même rechercher la fiche détaillée auprès de l'API. Ne jamais faire confiance aux
   titres/URL d'image envoyés par le navigateur.
5. Écran de confirmation (aperçu de la fiche) puis enregistrement dans une transaction :
   insertion media + genres + user_media(statut = vu) de l'ajouteur.
6. Lien "Je ne trouve pas" toujours visible -> formulaire d'ajout manuel.

Doublons
- Sources API : contrainte UNIQUE (type, source, id_externe). En cas de violation, ne pas lever d'erreur :
  rediriger vers la fiche existante avec le message "Déjà recommandé par X le JJ/MM" et marquer l'utilisateur vu
  (sans écraser une note existante).
- Ajout manuel : comparer un titre normalisé (minuscules, sans accents, sans ponctuation ni articles
  "le/la/les/the") + type (+ année si fournie). Si correspondance : afficher "Ce contenu existe peut-être déjà"
  avec un choix "C'est le même" / "Non, c'est autre chose". Stocker le titre normalisé dans une colonne indexée.
- Un même film peut exister en manuel puis via TMDB : l'admin peut fusionner deux contenus (option simple :
  bouton "fusionner dans..." qui reporte statuts/notes/commentaires puis supprime le doublon). Choix par défaut :
  à faire en fin de projet, si le temps le permet.

Modification / suppression
- L'ajouteur peut modifier les champs éditables (titre, année, lien, image pour les ajouts manuels) et
  "re-synchroniser" depuis l'API. Il peut supprimer sa reco UNIQUEMENT si personne d'autre n'a de statut ou de
  commentaire dessus ; sinon, seul l'admin peut supprimer (avec confirmation affichant ce qui sera perdu).
-->

### 3.3 Ma liste
Chaque membre a, pour chaque contenu, un **statut personnel** :

| Statut | Sens |
|---|---|
| À découvrir | Par défaut pour tout contenu recommandé par un autre |
| En cours | Série commencée, livre entamé, jeu en cours |
| Vu | Vu / lu / joué / écouté (une note devient possible) |
| Pas pour moi | Masqué de la liste principale, sans note |

- Les contenus **« Pas pour moi »** restent consultables dans une **vue dédiée**, où l'on peut **changer d'avis** (repasser en « À découvrir », « En cours » ou « Vu »).
- **Note facultative de 1 à 5**, modifiable, jamais obligatoire. **Notes visibles de tous** (qui a mis quoi + moyenne du groupe).
- **Filtres** : type, statut, recommandé par, **genre**.
- **Tri** : date d'ajout, note moyenne, popularité (nombre de « vu »), titre.
- **Recherche** dans les contenus déjà présents sur le site.
- **Badge « nouveau »** sur ce qui a été ajouté depuis la dernière visite du membre, avec compteur dans la navigation.
- **Tirage au sort « Que regarder ce soir ? »** : tire un contenu « À découvrir » au hasard, filtrable par type (et genre).

<!--
AGENTS — Liste et statuts
- Pas de ligne user_media = "À découvrir". Ne pas créer de lignes pour tous les membres à chaque ajout.
  Requête de liste : media LEFT JOIN user_media (sur l'utilisateur courant) ; statut effectif = COALESCE(statut, 'a_decouvrir').
- Codes de statut en base : a_decouvrir, en_cours, vu, pas_pour_moi (VARCHAR + validation PHP, pas d'ENUM).
  Repasser en "À découvrir" = supprimer la ligne ou la mettre à a_decouvrir (choisir une convention et s'y tenir).
- Note : autorisée seulement si statut = vu. Si l'utilisateur quitte "vu", choix par défaut : la note est
  conservée en base mais masquée et non comptée dans la moyenne ; elle réapparaît s'il repasse en "vu".
  (Évite de perdre une note sur un clic malheureux.) Moyennes = AVG sur les lignes statut = vu ET note NON NULL.
- Changement de statut et de note : en AJAX (fetch POST + en-tête X-CSRF-Token), mise à jour optimiste de
  l'interface, et repli sans JavaScript (formulaire classique) pour rester robuste.
- Vues de liste conseillées (onglets) : "À découvrir" (défaut), "En cours", "Vus", "Pas pour moi", "Tout".
  "Pas pour moi" n'apparaît nulle part ailleurs (ni liste par défaut, ni tirage au sort) sauf dans son onglet
  et sur la fiche du contenu.
- Filtres et tris passés en paramètres GET (URL partageable, bouton retour du navigateur fonctionnel).
  Pagination : 24 éléments par page, LIMIT/OFFSET suffit à cette échelle.
- Tri "popularité" = nombre de membres au statut vu ; tri "note moyenne" : les contenus sans note à la fin.
  Index nécessaires : user_media(user_id, statut), user_media(media_id, statut), media(type, date_ajout),
  media(titre_normalise).
- Recherche interne : LIKE sur titre_normalise (préfixe et contient). Attention : la sensibilité à la casse
  et aux accents diffère entre MySQL et SQLite, d'où la colonne normalisée calculée en PHP.
- Badge "nouveau" : stocker sur users deux dates : derniere_visite (mise à jour au plus une fois toutes les
  5 minutes pour limiter les écritures) et visite_precedente (copiée depuis derniere_visite au début de chaque
  nouvelle session). "Nouveau" = date_ajout > visite_precedente ET ajouté par quelqu'un d'autre.
  Le badge disparaît à la session suivante, ce qui est le comportement attendu.
- Tirage au sort : ne pas utiliser ORDER BY RAND()/RANDOM() sur toute la table si elle grossit ;
  à cette échelle c'est acceptable, mais préférer : COUNT des candidats puis OFFSET aléatoire (random_int).
  Afficher le résultat en grand avec boutons "Un autre !" et "Je le commence" (passe en "En cours").
-->

### 3.4 Vie du groupe
- **Fil « Quoi de neuf »** en page d'accueil : dernières recommandations, notes et commentaires du groupe (paginé).
- **Commentaires** sous chaque contenu (texte court, modifiable et supprimable par son auteur, modération par l'admin). Prévoir une mention « spoiler » facultative qui masque le texte jusqu'au clic.
- **Profil d'un membre** : ses recommandations, ses notes, quelques chiffres (nombre de contenus vus par type, note moyenne donnée).

<!--
AGENTS — Groupe
- Fil "Quoi de neuf" : UNION de trois requêtes (nouveaux media, notes récentes via user_media.date_maj,
  commentaires récents), triée par date, LIMIT 30 par page. Regrouper les événements proches
  ("Paul a noté 3 contenus") est un plus, pas une obligation. Ne pas afficher les passages en "pas pour moi"
  (c'est un choix personnel, pas une info à diffuser). Choix par défaut : afficher les passages en "vu" avec note,
  pas les changements de statut sans note.
- Commentaires : texte brut uniquement (pas de HTML, pas de Markdown), 1000 caractères max, sauts de ligne
  conservés (nl2br APRÈS échappement). Liens cliquables possibles via une regex simple sur le texte échappé,
  avec rel="noopener noreferrer nofollow". Affichage "modifié" si date_modif renseignée.
- Spoiler : élément <details><summary>Spoiler — afficher</summary>...</details> : natif, accessible, sans JS.
- Profil : en-tête (avatar, nom, membre depuis), compteurs par type, note moyenne donnée, liste paginée de ses recos.
  Ses notes sont visibles (décision du propriétaire : notes visibles de tous).
-->

### 3.5 Fiche d'un contenu
- Jaquette, titre, année, type, genres, recommandé par, date.
- **Infos pratiques** selon le type : durée (film), nombre de saisons/épisodes (série), nombre de pages et auteur (livre), plateformes (jeu), artiste et album (musique), chaîne (YouTube).
- Liens : fiche de référence (TMDB / IMDb, Open Library, RAWG, Deezer), bande-annonce si disponible.
- **« Où regarder »** (films et séries) : plateformes de streaming en France (section 7).
- **Liens boutiques** (livres) : voir section 7.
- **Lien d'écoute** (musique) : Deezer.
- Statut et note de chaque membre, moyenne, commentaires.

<!--
AGENTS — Fiche
- Ordre sur mobile : jaquette + titre + infos clés, puis MES actions (statut, note) bien visibles,
  puis liens (bande-annonce, où regarder, boutiques, écoute), puis avis du groupe, puis commentaires.
- Bande-annonce : lien externe vers YouTube (pas d'iframe intégrée par défaut : plus simple pour la CSP
  et la vie privée). Si une intégration est souhaitée plus tard, utiliser youtube-nocookie.com.
- Tous les liens externes : target="_blank" rel="noopener noreferrer".
- Images : affichées directement depuis les CDN des APIs (pas de copie locale par défaut), avec
  loading="lazy", dimensions fixes (éviter les sauts de mise en page) et une image de remplacement
  locale par type si l'image manque ou ne charge pas. Les domaines d'images doivent figurer dans la CSP img-src.
- Infos pratiques : stockées en JSON (colonne TEXT) ; leur rendu est piloté par le registre des types (section 2).
-->

### 3.6 Confort
- **Interface responsive**, pensée d'abord pour le téléphone.
- **PWA installable** : manifeste + icônes + service worker minimal, pour ajouter le site à l'écran d'accueil comme une appli. Pas de mode hors ligne complexe (au mieux une page « hors connexion »).
- Thème **clair, moderne et simple**.

<!--
AGENTS — Interface
- Mobile d'abord : navigation par barre fixe en bas d'écran sur mobile (Accueil, Ma liste, + Ajouter,
  Au hasard, Profil), barre latérale ou en-tête sur grand écran. Le bouton "+ Ajouter" est le plus visible.
  Penser aux zones sûres iPhone (env(safe-area-inset-bottom)).
- CSS : un seul fichier, variables CSS (couleurs, espacements, rayons), pas de framework CSS ni de build.
  Police système (system-ui), pas de police externe (vie privée + CSP + rapidité).
- Thème clair : fond blanc cassé, une couleur d'accent chaleureuse (le nom évoque une cantine : orange/
  moutarde ou vert sauge), cartes avec ombre légère, coins arrondis. Contraste WCAG AA minimum pour tout texte.
- Palette (démarche VALIDÉE) : au début de l'étape 2, proposer 2 ou 3 palettes au propriétaire sous forme
  d'une page de démonstration statique (assets/palettes.html ou captures) montrant une carte, un bouton, la barre de
  navigation et les étoiles de note. Le propriétaire choisit ; ne rien figer avant. Toutes les couleurs passent par
  des variables CSS, pour pouvoir changer de palette en modifiant un seul bloc.
- Accessibilité : vrais <button> et <a>, <label> sur chaque champ, focus visible, zones tactiles >= 44 px,
  notes 1 à 5 utilisables au clavier (boutons radio stylés), textes alternatifs sur les jaquettes.
- JavaScript : vanilla, en amélioration progressive ; un fichier principal, pas de JS inline (CSP).
- Grilles de jaquettes : CSS grid auto-fill, 2 colonnes sur petit téléphone, plus sur grand écran.
- Messages "flash" (succès/erreur) après chaque action, en français simple.
- Dates affichées en français relatif ("il y a 2 jours") puis absolu au-delà d'une semaine.

AGENTS — PWA
- manifest.webmanifest : name "Les reco de la cantine" (pris depuis la config), short_name "La cantine",
  start_url et scope RELATIFS (le site peut être dans un sous-dossier), display "standalone",
  theme_color/background_color du thème, icônes 192 et 512 px + icône "maskable" + apple-touch-icon.
- sw.js à la racine du site (pour que son scope couvre tout le site) :
  - met en cache UNIQUEMENT les fichiers statiques (CSS, JS, icônes, page offline.html) ;
  - ne met JAMAIS en cache les pages HTML authentifiées ni les réponses AJAX (données privées, risque d'afficher
    les données d'un autre compte sur un appareil partagé) ;
  - en cas d'échec réseau sur une navigation : afficher offline.html ;
  - versionner le nom du cache avec la version du site pour forcer la mise à jour.
- La PWA nécessite HTTPS (sauf localhost) : l'indiquer dans le README.
-->

## 4. Hébergement et déploiement

Pour les agents, la cible est simplement **un serveur web PHP + MySQL/MariaDB (ou SQLite)**. Le propriétaire héberge sur un serveur personnel (YunoHost), mais le code ne doit contenir **aucune dépendance à YunoHost** : c'est son affaire de déploiement.

- Le propriétaire **téléverse lui-même les fichiers** sur le serveur (pas de CI, pas de build sur le serveur).
- Tout est **testé en local avant déploiement** (`php -S localhost:8000` suffit).
- Les **mises à jour du code restent possibles** : on renvoie les fichiers sans perdre la configuration ni les données.
- Le site doit **s'installer tout seul via un installeur PHP** (principe WordPress).

### Distribution publique (GitHub)
- Le dépôt GitHub est **public**. La distribution se fait par **releases** : chaque version publiée fournit une archive **`.zip` prête à téléverser** (même contenu que le dépôt, sans fichiers de développement), avec numéro de version (`vX.Y.Z`) et notes de version.
- Le dépôt contient : `README.md` (installation, prérequis, mise à jour, sauvegarde/restauration, dépannage), `LICENSE` (MIT), `CHANGELOG.md`, `VERSION`.
- **Aucune donnée personnelle ni secret** dans le dépôt ou dans l'archive (pas de `config.php`, pas de base, pas de clé d'API).
- Le code ne contient rien de spécifique à l'instance du propriétaire : nom du site, textes, clés et liens boutiques sont configurables.

Conséquence : le code doit être **portable** et reposer uniquement sur ce que fournissent presque tous les hébergeurs PHP.

<!--
AGENTS — Arborescence conseillée
Contrainte : beaucoup d'hébergeurs ne permettent pas de choisir la racine web. Le site doit donc fonctionner
quand TOUT le dossier est servi tel quel, tout en protégeant les dossiers internes.

  /
  ├── index.php              contrôleur frontal unique (toutes les pages)
  ├── install.php            installeur (refuse de tourner si déjà installé)
  ├── sw.js                  service worker (doit être à la racine pour son scope)
  ├── manifest.webmanifest
  ├── .htaccess              règles Apache (protection des dossiers internes, en-têtes)
  ├── assets/                css/, js/, img/ (icônes, images de remplacement)
  ├── app/                   code PHP (non accessible depuis le web)
  │   ├── bootstrap.php      chargement config, autoload, session, gestion d'erreurs
  │   ├── routes.php         table des routes
  │   ├── Controllers/  Repositories/  Services/ (dont Services/Api/)  Support/ (Db, Csrf, Auth, Http, View...)
  │   ├── Views/             gabarits PHP (layout + pages + partiels)
  │   ├── lang/fr.php        tous les textes de l'interface
  │   └── Types.php          registre des types de contenus
  ├── migrations/            001_init.php, 002_..., (non accessible depuis le web)
  ├── data/                  NON livré dans l'archive : config.php, base SQLite, cache/, logs/, sauvegardes/
  ├── tests/                 NON livré dans l'archive
  ├── tools/                 scripts de développement (ex. build-release.sh), NON livré
  ├── README.md  LICENSE  CHANGELOG.md  VERSION
  └── projet.md              ce fichier, NON livré dans l'archive

Protection des dossiers internes (défense en profondeur, les trois à la fois) :
1. .htaccess "Require all denied" dans app/, migrations/, data/ (Apache) ;
2. bloc nginx documenté dans le README (location ~ ^/(app|migrations|data|tests|tools)/ { deny all; }) ;
3. chaque fichier PHP de app/ commence par une garde : defined('CANTINE') || exit; (constante définie par index.php).
De plus, le diagnostic tente une requête HTTP vers data/.probe et affiche une alerte rouge si le fichier
est accessible depuis le web.

Routage : sans réécriture d'URL obligatoire. URLs de la forme index.php?route=liste&type=film.
Si mod_rewrite est disponible, des URLs propres peuvent être ajoutées plus tard ; ne pas en dépendre.
URL de base : déduite de SCRIPT_NAME (gère le sous-dossier), surchargeable par une option base_url dans config.php
(utile derrière un proxy). Détection HTTPS : $_SERVER['HTTPS'], puis X-Forwarded-Proto seulement si l'option
trust_proxy est activée dans la config.

Release : script tools/build-release.sh qui produit cantine-vX.Y.Z.zip contenant un dossier racine
"les-reco-de-la-cantine/" sans data/, tests/, tools/, .git*, projet.md. Le dossier data/ est créé par
l'installeur (avec son .htaccess). Ne pas utiliser de GitHub Action obligatoire ; une Action optionnelle
de création de release peut être proposée.
-->

## 5. Stack technique

- **PHP 8.1 minimum** (testé sur 8.1 à 8.4). Ce prérequis est **écrit dans le README** et vérifié par l'installeur.
- Sans framework lourd, **sans étape de build** (ce qui est téléversé est ce qui tourne). Si des bibliothèques sont nécessaires, les inclure dans le dossier du projet (pas de `composer install` requis sur le serveur).
- **Base de données via PDO**, deux pilotes au choix dans l'installeur :
  - **MySQL / MariaDB** : **base de production du propriétaire**, disponible chez presque tous les hébergeurs ;
  - **SQLite** : zéro configuration, pratique pour tester en local et pour d'autres déploiements.
  Le SQL doit rester compatible avec les deux, ou isolé dans une couche d'abstraction. Tester les deux avant chaque livraison.
- **Multi-systèmes** : nginx + PHP-FPM, Apache, hébergement mutualisé, local (macOS). Aucun chemin absolu, URL de base détectée automatiquement (site à la racine ou dans un sous-dossier).
- Front : HTML/CSS/JS simples (pas de framework JS nécessitant un build), responsive.
- **Extensions PHP requises** (liste unique, reprise par l'installeur et le README) : `pdo`, `pdo_mysql` et/ou `pdo_sqlite`, `curl`, `mbstring`, `json`, `openssl`, `session`, `ctype`. Ne pas en ajouter d'autres sans mettre à jour cette liste.
- Interface **en français uniquement** ; regrouper les textes de l'interface dans un fichier pour faciliter une éventuelle traduction future (sans système de traduction pour l'instant).

<!--
AGENTS — Code PHP
- declare(strict_types=1) partout, autoload PSR-4 maison (spl_autoload_register, ~10 lignes), namespace Cantine\.
  N'utiliser QUE des fonctionnalités PHP 8.1 (enums et readonly OK ; pas de typed class constants (8.3),
  pas de readonly classes (8.2), pas de json_validate (8.3)).
- Pas de dépendance externe nécessaire : tout ce qu'il faut (routeur, gabarits, PDO, cURL) tient en peu de code.
  Si une bibliothèque devient vraiment utile, la copier dans app/vendor/ avec sa licence et la mentionner dans le README.
- Gabarits : PHP natif. Fonction e() = htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') utilisée
  SYSTÉMATIQUEMENT pour toute valeur affichée. Aucune concaténation de HTML dans les contrôleurs.
- Textes : app/lang/fr.php renvoie un tableau clé => texte ; helper t('cle', ['nom' => ...]).
  Pluriels gérés simplement (t_plural). Aucun texte d'interface en dur dans les gabarits, sauf exceptions évidentes.
- Erreurs : display_errors désactivé hors mode debug ; journal dans data/logs/app.log (rotation simple par taille) ;
  page d'erreur générique en français. Option debug dans config.php (false par défaut).
- Fuseau horaire : stocker toutes les dates en UTC (format 'Y-m-d H:i:s'), afficher en Europe/Paris
  (option timezone dans la config).

AGENTS — Compatibilité MySQL / SQLite (pièges connus)
- Clés primaires : MySQL "INT UNSIGNED AUTO_INCREMENT PRIMARY KEY" vs SQLite "INTEGER PRIMARY KEY AUTOINCREMENT".
  Les migrations peuvent contenir deux variantes de SQL (par pilote) pour la création de tables ; les requêtes
  de l'application, elles, doivent être communes.
- Pas d'ENUM, pas de types JSON natifs : VARCHAR et TEXT. Booléens : TINYINT(1) / INTEGER, valeurs 0/1.
- MySQL : ENGINE=InnoDB, CHARSET utf8mb4, COLLATE utf8mb4_unicode_ci, connexion avec charset=utf8mb4 dans le DSN.
- SQLite : exécuter "PRAGMA foreign_keys = ON" à chaque connexion ; activer le mode WAL ; busy_timeout ~5000 ms.
- Upsert : éviter ON DUPLICATE KEY (MySQL) et ON CONFLICT (SQLite) dans le code applicatif ; faire
  UPDATE puis INSERT si aucune ligne touchée, dans une transaction (ou isoler l'upsert dans une méthode par pilote).
- Fonctions de date, RAND()/RANDOM(), CONCAT/|| , GROUP_CONCAT diffèrent : calculer en PHP ou isoler dans
  la classe Db (méthodes du type $db->random(), $db->now()).
- LIMIT/OFFSET : passer des entiers liés avec PDO::PARAM_INT (ou cast + interpolation contrôlée).
- Préfixe de tables facultatif : toutes les requêtes passent par un helper de nom de table ({prefix}media).
- PDO : ERRMODE_EXCEPTION, FETCH_ASSOC par défaut, EMULATE_PREPARES = false pour MySQL.
- Tests : la suite de tests doit pouvoir tourner contre SQLite (rapide, par défaut) et contre MySQL
  (variable d'environnement), avant chaque release.

AGENTS — Tests et qualité
- Pas de PHPUnit obligatoire sur le serveur. En développement, au choix : PHPUnit (via composer en dev uniquement,
  vendor/ hors archive) ou un mini lanceur de tests maison dans tests/. Privilégier la simplicité.
- À tester en priorité : migrations (installation fraîche sur les deux pilotes), authentification et limitation
  des tentatives, CSRF, droits (membre / auteur / admin), statuts et notes, export puis restauration
  (aller-retour identique), normalisation des titres, connecteurs d'API (avec réponses JSON enregistrées en fixtures,
  jamais d'appel réseau réel dans les tests).
- php -l sur tous les fichiers avant chaque release ; tester au moins PHP 8.1 et la dernière version stable.
-->

## 6. Installeur, mises à jour, sauvegardes

### Installeur (`install.php`)
Assistant en étapes, avec un bouton « Retester » à chaque étape bloquante et des messages d'erreur clairs en français (pas de trace PHP brute).

1. **Test de l'environnement** (résultats en liste ✔ / ✘ / ⚠) :
   - version de PHP (minimum 8.1) ;
   - extensions requises (section 5) ;
   - `password_hash` avec Argon2id ou bcrypt, `random_bytes`, sessions utilisables ;
   - droits d'écriture sur le dossier de configuration (et de la base si SQLite) ;
   - **sortie réseau vers les APIs** (appel HTTPS simple) : avertissement non bloquant ;
   - HTTPS détecté : avertissement si absent.
   L'installation est bloquée tant qu'un point obligatoire est ✘ ; les ⚠ n'empêchent pas de continuer.
2. **Accès à la base** : **MySQL / MariaDB** (hôte, port, nom de la base, utilisateur, mot de passe, préfixe de tables facultatif) ou **SQLite**.
   Bouton **« Tester la connexion »** qui vérifie séparément : connexion au serveur, accès à la base, droits (`CREATE`, `ALTER`, `INSERT`, `SELECT`, `UPDATE`, `DELETE`), encodage `utf8mb4`. Chaque échec est expliqué (mauvais mot de passe, base introuvable, droits insuffisants…).
3. Création des tables (via le système de migrations).
4. Création du **compte administrateur** (identifiant + mot de passe fort).
5. Écriture de `config.php` (secret de session généré aléatoirement).
6. **Verrouillage** (`install.lock` ou équivalent) : une fois installé, `install.php` refuse de se relancer et invite à le supprimer.

La page de test de l'environnement reste accessible **à l'admin après installation** (diagnostic).

<!--
AGENTS — Installeur
- install.php est AUTONOME pour l'étape 1 : il ne doit charger aucun fichier qui utiliserait une extension
  peut-être absente, ni une syntaxe PHP > 7.0 avant d'avoir vérifié la version (sinon page blanche au lieu
  d'un message clair). Commencer par un test de version écrit en syntaxe ancienne, puis inclure le reste.
- Mettre le code des vérifications dans une classe partagée (ex. app/Support/Diagnostics.php) utilisée
  par l'installeur ET la page de diagnostic admin.
- Si data/config.php existe déjà ou install.lock est présent : afficher "Le site est déjà installé" et rien d'autre.
- Test des droits MySQL : créer une table temporaire nommée {prefix}install_test, faire ALTER/INSERT/SELECT/
  UPDATE/DELETE puis DROP ; signaler précisément l'étape qui échoue. Traduire les codes d'erreur courants :
  1045 (accès refusé : identifiant ou mot de passe), 1049 (base inconnue), 2002 (serveur injoignable : hôte/port/
  socket ; suggérer "localhost" vs "127.0.0.1"), 1142 (droit manquant).
- Test réseau : requête HTTPS courte (timeout 5 s) vers api.themoviedb.org et openlibrary.org ; ⚠ si échec.
- SQLite : fichier data/cantine.sqlite ; vérifier que data/ est inscriptible et NON accessible depuis le web.
- Écriture de config.php : var_export d'un tableau, permissions 0640 si possible ; afficher le contenu à copier
  à la main si l'écriture échoue (certains hébergements).
- Secret applicatif : bin2hex(random_bytes(32)).
- L'installeur protège ses propres formulaires par un jeton CSRF stocké en session.
- Fin d'installation : écrire install.lock, afficher les prochaines étapes (supprimer install.php, saisir
  la clé TMDB dans l'admin, créer les comptes des amis), bouton "Aller au site".
- Pas de données de démonstration dans l'installeur : elles se chargent depuis le panneau admin (section 3.1).
  L'écran de fin d'installation peut simplement mentionner cette possibilité.
-->

### Mises à jour
- Procédure : activer le mode maintenance, téléverser le nouveau code, appliquer les migrations, désactiver le mode maintenance (documentée dans le README).
- **Migrations versionnées** (version du schéma stockée en base), appliquées automatiquement ou via un bouton admin.
- La mise à jour **ne doit jamais écraser** `config.php`, la base SQLite ni les données : ces éléments sont hors du code livré.

<!--
AGENTS — Migrations
- Fichiers migrations/NNN_description.php renvoyant un tableau ['mysql' => [sql...], 'sqlite' => [sql...]]
  ou une fonction function (PDO $pdo, string $driver): void pour les cas complexes.
- Version courante stockée dans settings (schema_version). Au démarrage, si VERSION du code > version en base :
  les membres voient la page de maintenance, l'admin voit "Mise à jour de la base nécessaire" + bouton.
  Choix par défaut : bouton explicite plutôt qu'application silencieuse (le propriétaire garde la main,
  et propose de faire une sauvegarde juste avant).
- Ne jamais modifier une migration déjà publiée : toujours en ajouter une nouvelle.
- MySQL n'annule pas les CREATE/ALTER dans une transaction (commit implicite) : appliquer les migrations une par une,
  mettre à jour schema_version après CHAQUE migration réussie, et écrire des migrations idempotentes quand c'est possible.
- Mettre à jour CHANGELOG.md et VERSION (semver) à chaque release ; indiquer dans le CHANGELOG si une migration est incluse.
-->

### Sauvegarde et restauration (espace admin)
- **Export** : bouton qui télécharge un fichier **JSON** contenant toute la base (comptes, contenus, statuts, notes, commentaires, réglages). Nom daté, par exemple `cantine-sauvegarde-2026-10-14.json`. En-tête : version du site, version du schéma, date.
- **Portable** : restaurable quel que soit le moteur (MySQL ↔ SQLite) et d'une version du site à une autre.
- **Page de restauration** (admin uniquement) : envoi du fichier, **validation complète avant tout changement** (format, version compatible, cohérence), résumé de ce qui va être restauré, **confirmation explicite**, exécution dans une **transaction** (tout ou rien). Les migrations manquantes sont appliquées ensuite.
- Respecter `upload_max_filesize` / `post_max_size`, et indiquer clairement quand le fichier dépasse la limite du serveur.
- La sauvegarde contient les **mots de passe hachés** : avertissement au téléchargement, jamais stockée dans un dossier accessible depuis le web.
- Les clés d'API sont exclues de l'export par défaut (case à cocher pour les inclure).

<!--
AGENTS — Format de sauvegarde
{
  "format": "cantine-backup",
  "format_version": 1,
  "app_version": "1.2.0",
  "schema_version": 7,
  "created_at": "2026-10-14T18:30:00Z",
  "tables": { "users": [ {...}, ... ], "media": [...], "genres": [...], "media_genres": [...],
              "user_media": [...], "comments": [...], "settings": [...] }
}
- Exclure les tables techniques (login_attempts, api_cache, watch_providers) : elles se reconstruisent seules.
- Conserver les identifiants (id) tels quels pour préserver les relations.
- Export en flux (écrire table par table, ligne par ligne, avec json_encode par ligne) pour ne pas saturer
  la mémoire ; en-têtes Content-Disposition: attachment et Cache-Control: no-store.
  Option : proposer aussi une version .json.gz si zlib est présent (ne pas en dépendre).

AGENTS — Restauration
- Étapes : envoi -> validation (JSON valide, format = cantine-backup, schema_version <= version du code,
  colonnes attendues, clés étrangères cohérentes, valeurs de statut/note valides) -> écran de résumé
  (nombre de comptes, contenus, notes, commentaires, date de la sauvegarde) -> case "Je comprends que les données
  actuelles seront remplacées" + saisie du mot de passe admin -> exécution.
- Stratégie : (1) faire automatiquement une sauvegarde de sécurité de l'état actuel dans data/sauvegardes/ ;
  (2) dans une transaction : DELETE (pas TRUNCATE, qui provoque un commit implicite sous MySQL) de toutes les
  tables dans l'ordre inverse des dépendances, puis INSERT dans l'ordre des dépendances, avec requêtes préparées
  réutilisées ; (3) remettre à niveau les compteurs auto-incrément (MySQL : ALTER TABLE ... AUTO_INCREMENT ;
  SQLite : table sqlite_sequence) ; (4) si schema_version de la sauvegarde < version actuelle : les données doivent
  être transformées par des fonctions d'adaptation par version (prévoir le mécanisme dès la v1, même vide) ;
  (5) en cas d'erreur : rollback et message clair ; la sauvegarde de sécurité permet de revenir en arrière.
- Après restauration, la session de l'admin peut ne plus correspondre (mot de passe ou id différents) :
  le déconnecter proprement et l'inviter à se reconnecter.
- Limites de taille : lire upload_max_filesize et post_max_size, les afficher sur la page, et détecter le cas
  où PHP vide $_POST/$_FILES (dépassement de post_max_size) pour afficher un message compréhensible.
- Les sauvegardes de sécurité dans data/sauvegardes/ : garder les 5 dernières, listées dans l'admin
  avec bouton de téléchargement.
-->

### Clés d'API
Saisies dans l'espace admin, stockées côté serveur uniquement, jamais envoyées au navigateur.

<!--
AGENTS — Clés d'API
- Stockées dans la table settings. Dans l'admin, afficher seulement les 4 derniers caractères, avec bouton
  "Tester la clé" (appel réel et résultat ✔/✘).
- Chaque source sans clé est simplement désactivée dans l'écran d'ajout (avec message "recherche indisponible,
  ajout manuel possible"), sans erreur.
- Le README explique pas à pas comment obtenir les clés TMDB et RAWG (gratuites).
-->

## 7. Données externes (APIs, sans IA)

Appels faits **par le serveur PHP** (cURL), jamais depuis le navigateur.

| Type | API | Clé | Remarques |
|---|---|---|---|
| Films, séries | **TMDB** | Clé gratuite | Usage non commercial gratuit, **attribution TMDB obligatoire**. Affiche, synopsis, genres, durée, saisons, bande-annonce, ID IMDb, **« où regarder »**. |
| Livres | **Open Library** | Aucune | Couverture, auteur, pages, sujets, **ISBN**. Pas de Google Books. |
| Jeux vidéo | **RAWG** (alt. IGDB) | Clé gratuite | Jaquette, genres, plateformes, année. |
| Musique | **Deezer** API publique | Aucune | **Source principale** : recherche d'albums et de morceaux, pochettes, **lien d'écoute** Deezer. |
| YouTube | **oEmbed** YouTube | Aucune | L'utilisateur colle l'URL ; titre, chaîne, miniature. |

<!--
AGENTS — Architecture des connecteurs
- Une interface commune (ex. SourceInterface) : search(string $q): array de résultats normalisés
  (source, id_externe, titre, année, image, info_distinctive) ; fetch(string $id): fiche normalisée complète
  (titre, année, image, lien, isbn, genres[], infos pratiques[], liens[]).
- Un client HTTP unique (Services/Http.php) utilisé par tous les connecteurs :
  - HTTPS uniquement (CURLOPT_PROTOCOLS = HTTPS), liste blanche des hôtes autorisés ;
  - CURLOPT_FOLLOWLOCATION désactivé (ou redirections vérifiées contre la liste blanche) ;
  - timeout de connexion 3 s, timeout total 8 s ;
  - User-Agent : "LesRecoDeLaCantine/<version> (+<url du dépôt GitHub>)" ;
  - réponses mises en cache (table api_cache : clé = hash de l'URL, corps, expiration) : 24 h pour les recherches,
    7 jours pour les fiches ; purge des entrées expirées de temps en temps (à 1 % des requêtes, par exemple).
  - jamais de clé d'API dans les journaux ni dans les messages d'erreur.
- Toujours vérifier la structure des réponses (champs absents, null) : une API qui change ne doit pas casser la page.

AGENTS — TMDB (films, séries)
- API v3, auth par paramètre api_key ou jeton "Read Access Token" (en-tête Authorization: Bearer) : accepter les deux
  dans l'admin (détecter le format).
- Recherche : /search/movie et /search/tv avec language=fr-FR (ou /search/multi filtré sur movie/tv).
- Fiche : /movie/{id} ou /tv/{id} avec language=fr-FR et append_to_response=videos,external_ids,watch/providers
  (un seul appel). Bande-annonce : videos.results où site = YouTube et type = Trailer, préférer fr puis en
  (deuxième appel avec include_video_language=fr,en si besoin).
- Images : https://image.tmdb.org/t/p/w342{poster_path} (liste) et w500 (fiche).
- Attribution OBLIGATOIRE : logo TMDB + phrase "Ce produit utilise l'API TMDB mais n'est ni approuvé ni certifié
  par TMDB." dans une page "À propos" et/ou le pied de page.
- Durée (runtime), nombre de saisons/épisodes, genres (déjà en français avec fr-FR), lien IMDb via external_ids.imdb_id.

AGENTS — Open Library (livres)
- Recherche : https://openlibrary.org/search.json?q=...&fields=key,title,author_name,first_publish_year,isbn,
  cover_i,number_of_pages_median,subject&limit=20 (limiter les champs : réponse beaucoup plus légère).
  Ajouter language=fre peut aider mais réduit les résultats : À tester.
- Couverture : https://covers.openlibrary.org/b/id/{cover_i}-M.jpg (ou /b/isbn/{isbn}-M.jpg).
- Fiche : https://openlibrary.org{key} (lien de référence).
- ISBN : prendre de préférence un ISBN-13 ; plusieurs éditions existent, garder le premier pertinent.
- Les "subjects" sont nombreux et en vrac : garder au maximum 3 genres, ou ne pas en tirer de genre si c'est peu fiable.
- Pas de Google Books (décision du propriétaire) : si Open Library ne trouve pas un livre, l'ajout manuel prend le relais.

AGENTS — RAWG (jeux)
- https://api.rawg.io/api/games?key=...&search=...&page_size=20 ; fiche /games/{id}.
- Champs : name, released, background_image, platforms[].platform.name, genres[].name (en anglais : prévoir une
  petite table de traduction des genres courants : Action, Adventure, RPG, Shooter, Puzzle, Strategy...).
- Attribution demandée par RAWG : lien vers rawg.io sur les fiches de jeux.

AGENTS — Musique
- VALIDÉ : Deezer est la source de recherche principale (pas de clé, pochettes incluses, liens d'écoute directs).
  - Recherche : https://api.deezer.com/search/album?q=... et https://api.deezer.com/search/track?q=...
  - Fiche : /album/{id} (artiste, année via release_date, nombre de titres, durée, genres) ou /track/{id}
    (artiste, album, durée).
  - Pochettes : champs cover_medium / cover_big (album) ; lien d'écoute : champ "link".
  - Source en base : "deezer" ; id_externe préfixé par le genre d'objet ("album:123", "track:456") pour éviter
    qu'un album et un morceau aient le même id.
  - Deezer limite le débit (environ 50 requêtes / 5 s) : l'anti-rebond navigateur + le cache suffisent.
  - Vérifier le domaine réel des pochettes renvoyées pour la CSP img-src.
- MusicBrainz n'est plus nécessaire. Ne pas l'implémenter, sauf demande ultérieure.
- Laisser choisir "Album" ou "Morceau" dans l'écran d'ajout musique.

AGENTS — YouTube
- L'utilisateur colle une URL. Accepter : youtube.com/watch?v=ID, youtu.be/ID, youtube.com/shorts/ID,
  m.youtube.com, music.youtube.com. Extraire l'ID (11 caractères [A-Za-z0-9_-]) et RECONSTRUIRE l'URL canonique
  https://www.youtube.com/watch?v=ID avant tout appel (protection SSRF : on n'appelle jamais l'URL saisie).
- oEmbed : https://www.youtube.com/oembed?url=<URL canonique>&format=json -> title, author_name, thumbnail_url.
- id_externe = ID de la vidéo (détection de doublon fiable).
- Playlists : hors périmètre par défaut.
-->

### « Où regarder » (films, séries)
- Via l'endpoint *watch providers* de TMDB, **région FR**. Données fournies par **JustWatch** : **attribution JustWatch obligatoire** à côté de l'information.
- Ces disponibilités changent : les mettre en cache avec une date et les rafraîchir à la consultation si elles ont plus de quelques jours.

<!--
AGENTS — Où regarder
- Réponse TMDB : results.FR.{flatrate, rent, buy, ads, free, link}. Afficher en priorité "Inclus dans l'abonnement"
  (flatrate), puis "Gratuit" (free/ads), puis "Location" et "Achat" repliés par défaut.
- Logos : https://image.tmdb.org/t/p/w92{logo_path}. TMDB ne fournit pas de lien direct vers chaque plateforme :
  le lien commun est results.FR.link (page TMDB qui redirige vers JustWatch). Ne pas tenter de construire des
  liens profonds vers Netflix & co.
- Mention sous le bloc : "Disponibilités fournies par JustWatch" avec lien vers justwatch.com.
- Cache dans watch_providers : rafraîchir si date_maj > 3 jours, au moment de l'affichage de la fiche ; si l'appel
  échoue, afficher les dernières données connues avec leur date ("au 12/10").
- Région FR par défaut, configurable dans l'admin (projet public).
-->

### Liens boutiques (livres)
- Liens de **recherche** construits à partir de l'ISBN (ou du titre + auteur à défaut), **sans API ni compte affilié**.
- Liste **configurable par l'admin** sous forme de modèles d'URL avec `{isbn}` / `{titre}`. Valeurs par défaut proposées : **Amazon**, **Fnac**, et une librairie indépendante (**Place des Libraires** ou **leslibraires.fr**).
- Pas de lien d'affiliation par défaut (projet public) ; l'admin peut en ajouter un s'il le souhaite.

<!--
AGENTS — Liens boutiques
- Stockage : settings, clé shop_links = JSON [{ "nom": "Amazon", "url": "https://www.amazon.fr/s?k={isbn}" }, ...].
- Remplacement : rawurlencode() sur chaque valeur injectée ; {isbn} si disponible, sinon {titre} = "titre auteur".
  Si le modèle n'utilise que {isbn} et que le livre n'en a pas, masquer ce lien.
- Validation dans l'admin : URL https:// uniquement, au moins un marqueur {isbn} ou {titre}, nom de 30 caractères max.
- Modèles par défaut à VÉRIFIER manuellement avant la release (les URLs de recherche des sites changent) :
  Amazon "https://www.amazon.fr/s?k={isbn}", Fnac "https://www.fnac.com/SearchResult/ResultList.aspx?Search={isbn}",
  librairie indépendante : vérifier le format actuel de placedeslibraires.fr ou leslibraires.fr.
- Lien d'affiliation : l'admin modifie simplement le modèle d'URL (ex. ajout de &tag=xxx) ; rien à coder de plus.
-->

### Règles générales
- **IMDb n'a pas d'API gratuite officielle** : ne pas scraper. Lien IMDb construit à partir de l'ID fourni par TMDB : `https://www.imdb.com/title/<id>`.
- Respecter conditions d'utilisation et quotas ; mettre en cache les réponses.
- Stocker en base une **copie locale** des infos utiles : le site ne dépend plus des APIs après l'ajout (sauf « où regarder », rafraîchi).
- Le site reste utilisable (ajout manuel) si une API est en panne ou sans clé.

## 8. Modèle de données (esquisse)

- `users` : id, identifiant (unique), nom affiché, mot de passe haché, rôle (admin / membre), couleur d'avatar, actif, date de création, **dernière visite**, démo (oui/non)
- `remember_tokens` : jetons « rester connecté » (sélecteur, validateur haché, user_id, expiration)
- `media` : id, type, source (tmdb, openlibrary, rawg, deezer, youtube, manuel), démo (oui/non), id_externe, titre, année, image, lien, isbn, **infos pratiques** (champ JSON : durée, saisons, pages, auteur, plateformes…), ajouté_par, date_ajout, date_modif. Unique sur (source, id_externe).
- `genres` + `media_genres` : genres normalisés pour le filtre.
- `user_media` : user_id, media_id, **statut** (a_decouvrir / en_cours / vu / pas_pour_moi), note (1 à 5 ou NULL, seulement si vu), date_maj. L'absence de ligne vaut « À découvrir ».
- `comments` : id, media_id, user_id, texte, spoiler (booléen), date, date_modif
- `watch_providers` : media_id, données (JSON), date_maj
- `settings` : clé, valeur (clés d'API, liens boutiques, mode maintenance, version du schéma…)
- `login_attempts` : pour la limitation des tentatives de connexion

Règles :
- Ajout par X : statut de X = vu, sans note.
- Contenu déjà existant : alerte de doublon, l'ajouteur est marqué « vu ».
- « Nouveau » = contenu ajouté après la dernière visite du membre.
- Le fil « Quoi de neuf » se construit à partir des dates des contenus, notes et commentaires (pas besoin de table dédiée sauf si nécessaire pour les performances).

<!--
AGENTS — Précisions sur le schéma (à transcrire dans migrations/001_init.php)
- Noms de colonnes en anglais ou en français sans accents, au choix, mais COHÉRENTS dans tout le projet
  (conseil : anglais snake_case pour le code, français pour l'interface).
- users : + display_name, must_change_password, session_version (INT, incrémenté pour invalider les sessions),
  last_seen_at, previous_seen_at (badge "nouveau"), created_at, updated_at.
- media : + normalized_title (indexé, détection de doublons et recherche), year (INT NULL), description courte
  facultative, image_url, external_url, trailer_url NULL, extra (TEXT JSON des infos pratiques), added_by NULL
  (FK users ON DELETE SET NULL), created_at, updated_at.
  Contrainte unique : (type, source, external_id), external_id NULL pour les ajouts manuels (NULL n'entre pas en
  conflit dans un index unique, sur MySQL comme sur SQLite).
- genres : id, name (unique, en français). media_genres : (media_id, genre_id) clé primaire composite.
- user_media : clé primaire composite (user_id, media_id), status, rating NULL (TINYINT 1..5, vérifié en PHP),
  updated_at ; FK vers users et media en ON DELETE CASCADE.
- comments : FK media ON DELETE CASCADE ; FK users ON DELETE CASCADE (choix par défaut, cf. suppression de compte).
- api_cache : cache_key (CHAR 64, sha256) clé primaire, body (MEDIUMTEXT / TEXT), expires_at.
- login_attempts : id, identifier (identifiant tenté), ip, attempted_at ; index sur (identifier, attempted_at)
  et (ip, attempted_at) ; purge des lignes de plus de 24 h.
- remember_tokens : id, selector (unique), validator_hash (CHAR 64), user_id (FK ON DELETE CASCADE), expires_at, created_at.
- is_demo (booléen, défaut 0) sur users, media et comments.
- Index listés en section 3.3. Toutes les dates en UTC.
-->

## 9. Sécurité

Les données ne sont pas très sensibles, mais on garantit un niveau de sécurité sérieux :
- Mots de passe avec `password_hash()` (Argon2id ou bcrypt), `password_verify()`, `password_needs_rehash()`.
- Sessions : cookie `HttpOnly`, `Secure`, `SameSite=Lax`, régénération de l'identifiant à la connexion, expiration.
- **Protection CSRF** sur tous les formulaires et actions POST.
- **Requêtes préparées PDO** partout.
- **Échappement systématique** des sorties HTML (y compris commentaires) ; en-têtes de sécurité (CSP, X-Content-Type-Options, Referrer-Policy).
- **Limitation des tentatives** de connexion (par IP et par compte) avec délai croissant.
- Contrôle d'accès côté serveur sur chaque page et action : membre connecté pour le contenu, auteur ou admin pour modifier/supprimer, admin pour l'administration.
- Fichiers sensibles (`config.php`, base SQLite, dossier des données) **non accessibles depuis le web** : hors de la racine publique si possible, sinon protégés (`.htaccess` fourni pour Apache, règle nginx documentée dans le README).
- HTTPS recommandé (avertissement de l'installeur si absent).
- Aucun secret dans git ; `config.php` dans `.gitignore`.
- Réinitialisation de mot de passe par l'admin uniquement ; changement par l'utilisateur avec saisie de l'ancien.
- Projet public : rien de secret dans le code ; la sécurité repose sur la configuration et les mots de passe de l'instance.
- Appels sortants limités aux domaines attendus (éviter le SSRF, notamment pour l'URL YouTube saisie).
- Modèles d'URL des liens boutiques : n'accepter que `https://`.

<!--
AGENTS — Mise en œuvre de la sécurité
- Mots de passe : PASSWORD_ARGON2ID si défini, sinon PASSWORD_DEFAULT (bcrypt). Attention : bcrypt tronque
  à 72 octets, limiter la longueur saisie à 72 caractères ou pré-hacher (choix par défaut : limiter à 72).
  Rehash transparent à la connexion si password_needs_rehash().
- Session : nom de cookie propre (ex. cantine_sid), path = chemin de base du site, httponly, samesite Lax,
  secure SEULEMENT si la requête est en HTTPS (sinon la connexion est impossible en local sur http://localhost).
  session.use_strict_mode = 1. session_regenerate_id(true) à la connexion et au changement de rôle.
  Expiration d'inactivité : 2 h par défaut (configurable). Vérifier session_version à chaque requête.
- CSRF : jeton par session (bin2hex(random_bytes(32))), champ caché _csrf dans chaque formulaire POST, en-tête
  X-CSRF-Token pour les fetch, comparaison avec hash_equals. Toute action qui modifie quelque chose = POST,
  jamais GET (y compris "déconnexion" et "supprimer").
- Limitation des tentatives : après 5 échecs en 15 min sur un même identifiant OU une même IP : attente imposée
  croissante (1, 2, 4... jusqu'à 15 min). Message d'erreur identique que l'identifiant existe ou non
  ("Identifiant ou mot de passe incorrect"). Exécuter password_verify même si le compte n'existe pas
  (hash factice) pour ne pas révéler les comptes par le temps de réponse.
- En-têtes envoyés par PHP (pas seulement par .htaccess, pour nginx) :
  Content-Security-Policy: default-src 'self'; img-src 'self' data: https://image.tmdb.org https://covers.openlibrary.org
    https://*.archive.org https://media.rawg.io https://e-cdns-images.dzcdn.net
    https://cdn-images.dzcdn.net https://i.ytimg.com; script-src 'self'; style-src 'self'; object-src 'none';
    base-uri 'self'; form-action 'self'; frame-ancestors 'none'
  (liste des domaines d'images à vérifier selon les URLs réellement renvoyées par les APIs ;
   aucun script ni style inline, donc pas de 'unsafe-inline').
  X-Content-Type-Options: nosniff ; Referrer-Policy: strict-origin-when-cross-origin ; X-Frame-Options: DENY ;
  Permissions-Policy: camera=(), microphone=(), geolocation=().
- Contrôle d'accès : un middleware par route (public / membre / auteur-ou-admin / admin) déclaré dans routes.php ;
  ne jamais se fier à un champ caché (user_id, role) envoyé par le formulaire.
- Envoi de fichier (restauration) : vérifier la taille, lire le contenu avec json_decode (JSON_THROW_ON_ERROR),
  ne jamais enregistrer le fichier envoyé dans un dossier public, ne jamais l'inclure ni l'exécuter.
- Journalisation : erreurs et échecs de connexion dans data/logs/ (sans mot de passe ni clé d'API).
- Dépendances externes : aucune par défaut, donc pas de surveillance de vulnérabilités à mettre en place.
-->

## 10. Décisions en suspens

Aucune. Le nom, la taille, l'hébergement, la stack, la sécurité, le thème (clair), la licence (MIT), la langue (français), la sauvegarde (JSON) et le périmètre fonctionnel sont décidés.

Écartés volontairement (ne pas développer sans nouvelle demande) : mot d'accompagnement de l'ajouteur, statistiques et classements du groupe, export CSV personnel, rappel de sauvegarde, journal d'activité admin.

<!--
AGENTS — Points tranchés par le propriétaire (anciennement "À VALIDER") :
- "rester connecté" : OUI (section 3.1) ;
- palette : l'agent propose 2 ou 3 palettes, le propriétaire choisit (section 3.6) ;
- Deezer : source principale pour la musique, MusicBrainz abandonné (section 7) ;
- Google Books : NON (section 7) ;
- données de démonstration : OUI, depuis le panneau admin uniquement (section 3.1).
Il ne reste aucun point "À VALIDER". Tout nouveau point de ce type doit être ajouté ici.
Ne pas réintroduire les fonctionnalités écartées ci-dessus, même partiellement (ex. pas de page de stats
"cachée" ni de champ "pourquoi je recommande").
-->

## 11. Ordre de développement conseillé

1. **Socle** : structure du projet, configuration, accès base (MySQL + SQLite), migrations, installeur avec tests d'environnement et de base, connexion, sessions, sécurité de base.
2. **Cœur** : comptes et admin, ajout manuel, liste personnelle avec statuts, notes, fiche contenu.
3. **APIs** : recherche TMDB, Open Library, RAWG, Deezer, YouTube ; alerte de doublon ; genres et infos pratiques.
4. **Groupe** : fil « Quoi de neuf », badge « nouveau », commentaires, profils, avatars.
5. **Extras** : « où regarder », liens boutiques, tri/recherche/filtres avancés, tirage au sort, vue « Pas pour moi ».
6. **Exploitation** : sauvegarde / restauration, mode maintenance, page de diagnostic, données de démonstration, PWA.
7. **Publication** : README, LICENSE, CHANGELOG, archive de release.

Chaque étape doit être testable en local avant de passer à la suivante.

<!--
AGENTS — Organisation du travail
- Une étape = une ou plusieurs sessions de travail, terminées par : tests passants sur SQLite (et MySQL si disponible),
  vérification manuelle dans le navigateur (mobile et ordinateur), et un court compte rendu au propriétaire
  (ce qui marche, comment le tester, ce qui reste).
- Critères de fin d'étape :
  1. Socle : installation fraîche réussie sur SQLite ET MySQL via install.php ; connexion/déconnexion ;
     diagnostic affiché ; dossiers internes inaccessibles depuis le web (vérifié).
  2. Cœur : un admin crée deux comptes ; A ajoute manuellement un film ; B le voit "À découvrir", le passe
     "Vu" et le note ; A voit la note de B et la moyenne.
  3. APIs : ajout d'un contenu de chaque type par recherche ; doublon détecté ; site fonctionnel sans aucune clé.
  4. Groupe : fil, badge, commentaires avec spoiler, profil.
  5. Extras : tous les filtres/tris combinables ; tirage au sort ; "Pas pour moi" réversible.
  6. Exploitation : export -> installation neuve sur l'autre moteur -> restauration -> données identiques ;
     mode maintenance ; PWA installable sur Android et iOS.
  7. Publication : l'archive .zip, téléversée sur un serveur vierge, s'installe en suivant uniquement le README.
- Pendant le développement local : php -S localhost:8000 depuis la racine du projet. Le serveur intégré de PHP
  ne lit pas .htaccess : la protection des dossiers repose alors sur la garde PHP (defined('CANTINE')),
  d'où l'importance de cette garde. Prévoir un petit script router pour php -S qui refuse aussi app/, data/, etc.
- Commits git petits et fréquents, messages en français, un commit par fonctionnalité cohérente.
-->

## 12. Consignes pour les agents IA

- Lire ce fichier en premier, **y compris les commentaires `<!-- AGENTS -->`** ; le mettre à jour quand une décision est prise.
- Projet entre amis : la solution la plus simple qui répond au besoin, pas de sur-ingénierie.
- Aucune IA dans le produit final.
- Pas de framework imposant un build ou `composer` sur le serveur ; le dossier doit être téléversable tel quel.
- Tout doit pouvoir être testé en local (`php -S`) avant déploiement ; marche à suivre dans le `README`.
- Ne jamais committer de secret.
- Demander avant toute décision d'architecture non tranchée ici, et avant de coder un point marqué « À VALIDER ».
- Communiquer en français ; l'interface du site est en français.

<!--
AGENTS — Rappels de méthode
- Avant de coder une fonctionnalité : relire sa section ET son commentaire ; vérifier qu'elle ne fait pas
  partie des fonctionnalités écartées (section 10).
- Ne pas inventer de comportement non décrit : s'il manque une règle, choisir l'option la plus simple et
  la plus réversible, la noter dans ce fichier, et la signaler au propriétaire dans le compte rendu.
- Vérifier les endpoints et conditions des APIs dans leur documentation officielle au moment du développement :
  les indications ci-dessus datent d'octobre 2026 et peuvent avoir changé.
- Ne pas supprimer ni réécrire les commentaires d'un autre agent sans raison : les compléter.
-->

## 13. Journal de réalisation (v0.1.0, 14/10/2026)

Toutes les étapes de la section 11 sont codées et testées en local (PHP 8.5, SQLite 3.51, MySQL 9.6) :
52 tests automatisés (`php tests/run.php`, SQLite + MySQL), installation complète via `install.php` sur les deux moteurs,
scénario de fin d'étape 2 rejoué, sauvegarde MySQL restaurée sous SQLite par l'interface, archive de release installée sur un dossier vierge.

**À faire par le propriétaire**
- **Choisir la palette** : ouvrir `assets/palettes.html` (3 propositions : Terracotta, Vert sauge, Encre et moutarde). Terracotta est appliquée
  provisoirement ; changer = remplacer le bloc `:root` en tête de `assets/css/app.css` (et `theme_color` dans `PageController::manifest()` + `layout/head.php`).
- **Adresse du dépôt GitHub** : renseigner la constante `CANTINE_REPO_URL` dans `app/bootstrap.php` (User-Agent des API, page « À propos »).
- **Liens boutiques par défaut** : Place des Libraires vérifié (réponse 200) ; Amazon et Fnac bloquent les robots, à vérifier dans un navigateur.
- Tester la PWA sur un vrai téléphone Android et iOS (nécessite HTTPS), et les clés TMDB/RAWG réelles (les connecteurs sont testés sur des réponses enregistrées).

**Choix faits en l'absence de règle (simples et réversibles)**
- CSP `img-src 'self' data: https:` au lieu d'une liste de domaines : les jaquettes d'ajout manuel peuvent venir de n'importe quel site https.
  Pour revenir à une liste blanche, modifier `App::sendSecurityHeaders()`.
- `user_media` : repasser en « À découvrir » met la ligne à `a_decouvrir` (elle n'est pas supprimée, pour conserver une note masquée).
- Fil « Quoi de neuf » : trois requêtes fusionnées en PHP plutôt qu'un UNION (portabilité) ; les notes consécutives d'un même membre sont regroupées.
- Badge « nouveau » : rien n'est marqué nouveau lors de la toute première connexion d'un membre.
- Manifeste PWA servi par `index.php?route=manifest` (et non un fichier statique) pour reprendre le nom du site configuré.
- Restauration : `schema_version` et le mode maintenance restent ceux de l'instance ; les clés d'API actuelles sont conservées si la sauvegarde n'en contient pas.
- Fusion de doublons (admin) implémentée : bouton « Fusionner… » sur la fiche.
- Les messages de l'installeur et du diagnostic sont écrits en dur (en français) : l'installeur doit fonctionner avant tout chargement.
- Données de démo : mot de passe commun `demo-cantine-2026`, affiché dans l'administration.
- `assets/palettes.html` est exclu de l'archive de release (page de travail).
