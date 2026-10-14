<?php defined('CANTINE') || exit; ?>
<section class="panel">
    <h1>Compte administrateur</h1>
    <p>Ce sera votre compte : il permet de créer les comptes de vos amis et de régler le site.</p>
    <?php if ($errors): ?><p class="alert alert-error" role="alert">Certains champs sont à corriger.</p><?php endif; ?>
    <form method="post" action="install.php?etape=3" class="form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="display_name">Votre prénom ou pseudo (affiché aux autres)</label>
            <input type="text" id="display_name" name="display_name" value="<?= e($values['display_name']) ?>" required maxlength="50">
            <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'display_name']) ?>
        </div>
        <div class="field">
            <label for="username">Identifiant de connexion</label>
            <input type="text" id="username" name="username" value="<?= e($values['username']) ?>" required maxlength="30" autocapitalize="none" spellcheck="false" autocomplete="username">
            <p class="hint">3 à 30 caractères : lettres, chiffres et tirets.</p>
            <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'username']) ?>
        </div>
        <div class="field">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password">
            <p class="hint">12 caractères minimum pour le compte administrateur. Une courte phrase est idéale.</p>
            <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'password']) ?>
        </div>
        <div class="field">
            <label for="confirm">Confirmer le mot de passe</label>
            <input type="password" id="confirm" name="confirm" required maxlength="72" autocomplete="new-password">
            <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'confirm']) ?>
        </div>
        <button type="submit" class="button button-primary button-large">Installer le site</button>
    </form>
</section>
