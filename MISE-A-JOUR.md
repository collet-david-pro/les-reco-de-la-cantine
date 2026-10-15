## 🔄 Mettre à jour son site (5 minutes)

> Vos données et votre configuration sont dans le dossier `data/`, qui ne fait **jamais** partie de l'archive : une mise à jour ne peut pas les effacer.

**1. 💾 Faites une sauvegarde.**
Sur votre site : *Administration › Sauvegarde › Télécharger une sauvegarde*. Gardez le fichier `.json` au chaud, c'est votre filet de sécurité.

**2. 🚧 Fermez la cuisine.**
*Administration › Mode maintenance › Activer la maintenance*. Vos amis voient une page d'attente, vous gardez l'accès.

**3. 📦 Téléchargez la nouvelle version.**
En bas de la page de la release, dans *Assets*, prenez **`cantine-vX.Y.Z.zip`** (et non « Source code »), puis décompressez-le.

**4. ⬆️ Envoyez les fichiers par-dessus les anciens.**
Avec FileZilla (ou votre outil FTP/SFTP habituel), envoyez **le contenu** du dossier `les-reco-de-la-cantine/` dans le dossier de votre site.
Quand le logiciel demande quoi faire des fichiers existants : **Écraser**.
- Ne touchez pas au dossier `data/` de votre serveur (il n'est pas dans l'archive, donc rien ne le remplace).
- Supprimez ensuite le fichier `install.php` du serveur.

**5. 🗄️ Mettez la base à jour si on vous le demande.**
Rechargez le site. Si la nouvelle version modifie la base de données, la page *« Mise à jour de la base nécessaire »* s'affiche : cliquez sur **Mettre à jour la base**.
Les notes de version indiquent si c'est le cas (« Mise à jour de la base : oui/non »).

**6. 🍽️ Rouvrez la cantine.**
*Administration › Rouvrir le site*. Le numéro de version installée est indiqué dans *Administration › Mises à jour*.

**Vous avez sauté des versions ?** Aucun souci : installez directement la dernière. Les mises à jour de la base des versions intermédiaires s'appliquent toutes, dans l'ordre, au moment du clic sur « Mettre à jour la base ».

**Un problème ?** Remettez les fichiers de l'ancienne version (toujours disponibles dans les releases précédentes), puis restaurez votre sauvegarde depuis *Administration › Sauvegarde*. Le détail des erreurs est dans `data/logs/app.log`.

> 🔔 Le site vous prévient tout seul : quand une nouvelle version sort, un bandeau apparaît pour les administrateurs (vérification une fois par jour auprès de GitHub, désactivable avec `'check_updates' => false` dans `data/config.php`).
