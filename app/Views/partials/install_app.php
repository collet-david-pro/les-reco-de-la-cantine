<?php defined('CANTINE') || exit;
/**
 * Bouton "Installer l'appli" (PWA), affiché uniquement sur mobile et si le site n'est pas déjà installé.
 * Tout est masqué par défaut : c'est le JavaScript qui décide de l'afficher.
 *  - Android / Chrome : la vraie fenêtre d'installation du navigateur (événement beforeinstallprompt) ;
 *  - iPhone / iPad : pas d'installation automatique, on explique les 3 gestes dans Safari ;
 *  - autres navigateurs : instructions génériques.
 * Paramètre : $dismissible (vrai sur l'accueil : bouton "Plus tard", masqué 30 jours).
 */
$dismissible ??= false;
?>
<section class="install-app" data-install-app data-done="<?= e(t('install_app.done')) ?>"<?= $dismissible ? ' data-dismissible' : '' ?> hidden aria-labelledby="install-title">
    <img src="<?= e(\Cantine\App::baseUrl()) ?>assets/img/icon-192.png" alt="" width="48" height="48" class="install-icon">
    <div class="install-text">
        <h2 id="install-title"><?= e(t('install_app.title')) ?></h2>
        <p><?= e(t('install_app.text')) ?></p>
    </div>
    <div class="install-actions">
        <button type="button" class="button button-primary" data-install-button><?= e(t('install_app.button')) ?></button>
        <?php if ($dismissible): ?>
            <button type="button" class="link-button" data-install-dismiss><?= e(t('install_app.later')) ?></button>
        <?php endif; ?>
    </div>
</section>

<dialog class="install-dialog" data-install-dialog aria-labelledby="install-dialog-title">
    <h2 id="install-dialog-title"><?= e(t('install_app.dialog_title')) ?></h2>
    <div data-install-ios hidden>
        <ol class="install-steps">
            <li><?= e(t('install_app.ios_step1')) ?> <span class="ios-share" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M8 7l4-4 4 4"/><path d="M7 10H5v11h14V10h-2"/></svg></span></li>
            <li><?= e(t('install_app.ios_step2')) ?></li>
            <li><?= e(t('install_app.ios_step3')) ?></li>
        </ol>
        <p class="hint"><?= e(t('install_app.ios_safari')) ?></p>
    </div>
    <div data-install-other hidden>
        <ol class="install-steps">
            <li><?= e(t('install_app.other_step1')) ?></li>
            <li><?= e(t('install_app.other_step2')) ?></li>
        </ol>
        <p class="hint"><?= e(t('install_app.other_hint')) ?></p>
    </div>
    <form method="dialog">
        <button type="submit" class="button button-primary button-block"><?= e(t('install_app.ok')) ?></button>
    </form>
</dialog>
