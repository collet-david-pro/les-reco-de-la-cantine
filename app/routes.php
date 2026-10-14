<?php
/**
 * Table des routes : 'MÉTHODE route' => [Contrôleur, méthode, accès].
 *
 * Accès :
 *  - public : sans connexion ;
 *  - auth   : connecté (y compris si le changement de mot de passe est imposé) ;
 *  - member : connecté, mot de passe à jour ;
 *  - admin  : administrateur.
 * Les droits "auteur ou admin" sont vérifiés dans le contrôleur (ils dépendent du contenu).
 * Toute action qui modifie quelque chose est en POST (protégée par le jeton CSRF).
 */
declare(strict_types=1);

defined('CANTINE') || exit;

use Cantine\Controllers\AccountController;
use Cantine\Controllers\AddController;
use Cantine\Controllers\Admin\AdminController;
use Cantine\Controllers\Admin\BackupController;
use Cantine\Controllers\Admin\SettingsController;
use Cantine\Controllers\Admin\UsersController;
use Cantine\Controllers\AuthController;
use Cantine\Controllers\CommentController;
use Cantine\Controllers\HomeController;
use Cantine\Controllers\ListController;
use Cantine\Controllers\MediaController;
use Cantine\Controllers\PageController;
use Cantine\Controllers\ProfileController;
use Cantine\Controllers\RandomController;
use Cantine\Controllers\StatusController;

return [
    // Connexion
    'GET connexion' => [AuthController::class, 'loginForm', 'public'],
    'POST connexion' => [AuthController::class, 'login', 'public'],
    'POST deconnexion' => [AuthController::class, 'logout', 'auth'],

    // Pages principales
    'GET accueil' => [HomeController::class, 'index', 'member'],
    'GET liste' => [ListController::class, 'index', 'member'],
    'GET hasard' => [RandomController::class, 'index', 'member'],
    'POST hasard/commencer' => [RandomController::class, 'start', 'member'],
    'GET membre' => [ProfileController::class, 'show', 'member'],
    'GET a-propos' => [PageController::class, 'about', 'public'],
    'GET manifest' => [PageController::class, 'manifest', 'public'],

    // Fiche d'un contenu
    'GET media' => [MediaController::class, 'show', 'member'],
    'GET media/modifier' => [MediaController::class, 'editForm', 'member'],
    'POST media/modifier' => [MediaController::class, 'update', 'member'],
    'POST media/resynchroniser' => [MediaController::class, 'resync', 'member'],
    'GET media/supprimer' => [MediaController::class, 'deleteConfirm', 'member'],
    'POST media/supprimer' => [MediaController::class, 'delete', 'member'],
    'GET media/fusionner' => [MediaController::class, 'mergeForm', 'admin'],
    'POST media/fusionner' => [MediaController::class, 'merge', 'admin'],
    'POST media/statut' => [StatusController::class, 'status', 'member'],
    'POST media/note' => [StatusController::class, 'rating', 'member'],

    // Ajout d'une recommandation
    'GET ajouter' => [AddController::class, 'chooseType', 'member'],
    'GET ajouter/recherche' => [AddController::class, 'search', 'member'],
    'GET api/recherche' => [AddController::class, 'searchFragment', 'member'],
    'POST ajouter/choisir' => [AddController::class, 'select', 'member'],
    'GET ajouter/apercu' => [AddController::class, 'preview', 'member'],
    'POST ajouter/enregistrer' => [AddController::class, 'save', 'member'],
    'GET ajouter/manuel' => [AddController::class, 'manualForm', 'member'],
    'POST ajouter/manuel' => [AddController::class, 'manualSave', 'member'],

    // Commentaires
    'POST commentaire' => [CommentController::class, 'create', 'member'],
    'GET commentaire/modifier' => [CommentController::class, 'editForm', 'member'],
    'POST commentaire/modifier' => [CommentController::class, 'update', 'member'],
    'POST commentaire/supprimer' => [CommentController::class, 'delete', 'member'],

    // Mon compte
    'GET mon-compte' => [AccountController::class, 'index', 'auth'],
    'POST mon-compte/profil' => [AccountController::class, 'updateProfile', 'member'],
    'POST mon-compte/mot-de-passe' => [AccountController::class, 'changePassword', 'auth'],
    'POST mon-compte/deconnexion-partout' => [AccountController::class, 'logoutEverywhere', 'auth'],

    // Administration
    'GET admin' => [AdminController::class, 'index', 'admin'],
    'POST admin/maintenance' => [AdminController::class, 'toggleMaintenance', 'admin'],
    'GET admin/diagnostic' => [AdminController::class, 'diagnostics', 'admin'],
    'GET admin/migrations' => [AdminController::class, 'migrationsForm', 'admin'],
    'POST admin/migrations' => [AdminController::class, 'migrate', 'admin'],
    'POST admin/demo/charger' => [AdminController::class, 'loadDemo', 'admin'],
    'POST admin/demo/supprimer' => [AdminController::class, 'removeDemo', 'admin'],

    'GET admin/membres' => [UsersController::class, 'index', 'admin'],
    'GET admin/membres/nouveau' => [UsersController::class, 'createForm', 'admin'],
    'POST admin/membres/nouveau' => [UsersController::class, 'create', 'admin'],
    'GET admin/membres/modifier' => [UsersController::class, 'editForm', 'admin'],
    'POST admin/membres/modifier' => [UsersController::class, 'update', 'admin'],
    'POST admin/membres/activer' => [UsersController::class, 'toggleActive', 'admin'],
    'POST admin/membres/mot-de-passe' => [UsersController::class, 'resetPassword', 'admin'],
    'GET admin/membres/supprimer' => [UsersController::class, 'deleteConfirm', 'admin'],
    'POST admin/membres/supprimer' => [UsersController::class, 'delete', 'admin'],

    'GET admin/reglages' => [SettingsController::class, 'index', 'admin'],
    'POST admin/reglages' => [SettingsController::class, 'save', 'admin'],
    'POST admin/reglages/tester' => [SettingsController::class, 'testKey', 'admin'],

    'GET admin/sauvegarde' => [BackupController::class, 'index', 'admin'],
    'POST admin/sauvegarde/exporter' => [BackupController::class, 'export', 'admin'],
    'GET admin/sauvegarde/telecharger' => [BackupController::class, 'downloadSafety', 'admin'],
    'POST admin/restauration/envoyer' => [BackupController::class, 'upload', 'admin'],
    'GET admin/restauration/confirmer' => [BackupController::class, 'confirmForm', 'admin'],
    'POST admin/restauration/executer' => [BackupController::class, 'restore', 'admin'],
];
