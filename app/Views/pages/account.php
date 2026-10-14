<?php defined('CANTINE') || exit;
/** Mon compte : profil (nom, couleur), mot de passe, déconnexion de tous les appareils. */
$mustChange = (int) $currentUser['must_change_password'] === 1;
$minLength = $currentUser['role'] === 'admin' ? 12 : 8;
?>
<div class="page-head">
    <h1><?= e(t('account.title')) ?></h1>
</div>

<?php if ($mustChange): ?>
    <p class="alert alert-warning" role="alert"><?= e(t('account.must_change')) ?></p>
<?php endif; ?>

<section class="panel" aria-labelledby="mdp">
    <h2 id="mdp" class="section-title"><?= e(t('account.password_title')) ?></h2>
    <form method="post" action="<?= e(url('mon-compte/mot-de-passe')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="text" name="username" value="<?= e($currentUser['username']) ?>" autocomplete="username" hidden>
        <div class="field">
            <label for="current_password"><?= e(t('account.current_password')) ?></label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>
            <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'current_password']) ?>
        </div>
        <div class="field">
            <label for="new_password"><?= e(t('account.new_password')) ?></label>
            <input type="password" id="new_password" name="new_password" autocomplete="new-password" required minlength="<?= $minLength ?>" maxlength="72">
            <p class="hint"><?= e(t('account.password_rule', ['min' => (string) $minLength])) ?></p>
            <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'new_password']) ?>
        </div>
        <div class="field">
            <label for="confirm_password"><?= e(t('account.confirm_password')) ?></label>
            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required maxlength="72">
            <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'confirm_password']) ?>
        </div>
        <button type="submit" class="button button-primary"><?= e(t('account.change_password')) ?></button>
    </form>
</section>

<?php if (!$mustChange): ?>
<section class="panel" aria-labelledby="profil">
    <h2 id="profil" class="section-title"><?= e(t('account.profile_title')) ?></h2>
    <form method="post" action="<?= e(url('mon-compte/profil')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="display_name"><?= e(t('account.display_name')) ?></label>
            <input type="text" id="display_name" name="display_name" value="<?= e($currentUser['display_name']) ?>" required maxlength="50">
            <p class="hint"><?= e(t('account.username_is', ['username' => $currentUser['username']])) ?></p>
        </div>
        <fieldset class="color-picker">
            <legend><?= e(t('account.avatar_color')) ?></legend>
            <div class="color-choices">
                <?php foreach ($colors as $i => $color): ?>
                    <input type="radio" id="color-<?= $i ?>" name="avatar_color" value="<?= e($color) ?>"<?= strcasecmp($currentUser['avatar_color'], $color) === 0 ? ' checked' : '' ?>>
                    <label for="color-<?= $i ?>"><?= partial('partials/avatar', ['name' => $currentUser['display_name'], 'color' => $color, 'size' => 'md']) ?><span class="sr-only"><?= e(t('account.color_n', ['n' => (string) ($i + 1)])) ?></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <button type="submit" class="button button-primary"><?= e(t('common.save')) ?></button>
    </form>
</section>
<?php endif; ?>

<section class="panel" aria-labelledby="appareils">
    <h2 id="appareils" class="section-title"><?= e(t('account.devices_title')) ?></h2>
    <p class="hint"><?= e(t('account.devices_text')) ?></p>
    <div class="button-row">
        <form method="post" action="<?= e(url('mon-compte/deconnexion-partout')) ?>" class="inline-form" data-confirm="<?= e(t('account.logout_all_confirm')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button"><?= e(t('account.logout_all')) ?></button>
        </form>
        <form method="post" action="<?= e(url('deconnexion')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="button button-ghost"><?= e(t('nav.logout')) ?></button>
        </form>
    </div>
</section>
