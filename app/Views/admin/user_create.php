<?php defined('CANTINE') || exit;
$mode = $values['password_mode'] ?? 'generate';
?>
<div class="page-head"><h1><?= e(t('users.create_title')) ?></h1></div>
<?= partial('admin/nav') ?>
<section class="panel">
    <?php if ($errors): ?><p class="alert alert-error" role="alert"><?= e(t('form.has_errors')) ?></p><?php endif; ?>
    <form method="post" action="<?= e(url('admin/membres/nouveau')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="display_name"><?= e(t('account.display_name')) ?></label>
            <input type="text" id="display_name" name="display_name" value="<?= e($values['display_name'] ?? '') ?>" required maxlength="50">
            <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'display_name']) ?>
        </div>
        <div class="field">
            <label for="username"><?= e(t('auth.username')) ?></label>
            <input type="text" id="username" name="username" value="<?= e($values['username'] ?? '') ?>" required maxlength="30" pattern="[A-Za-z0-9\-]{3,30}" autocapitalize="none" spellcheck="false">
            <p class="hint"><?= e(t('users.username_rule')) ?></p>
            <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'username']) ?>
        </div>
        <fieldset class="field">
            <legend><?= e(t('auth.password')) ?></legend>
            <div class="field-check">
                <input type="radio" id="pm-generate" name="password_mode" value="generate"<?= $mode === 'generate' ? ' checked' : '' ?>>
                <label for="pm-generate"><?= e(t('users.password_generate')) ?></label>
            </div>
            <div class="field-check">
                <input type="radio" id="pm-manual" name="password_mode" value="manual"<?= $mode === 'manual' ? ' checked' : '' ?>>
                <label for="pm-manual"><?= e(t('users.password_manual')) ?></label>
            </div>
            <label for="password" class="sr-only"><?= e(t('users.password_manual')) ?></label>
            <input type="text" id="password" name="password" maxlength="72" autocomplete="off" placeholder="<?= e(t('users.password_placeholder')) ?>">
            <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'password']) ?>
        </fieldset>
        <div class="field field-check">
            <input type="checkbox" id="must_change" name="must_change" value="1"<?= !$values || !empty($values['must_change']) ? ' checked' : '' ?>>
            <label for="must_change"><?= e(t('users.must_change')) ?></label>
        </div>
        <div class="field field-check">
            <input type="checkbox" id="role" name="role" value="admin"<?= ($values['role'] ?? '') === 'admin' ? ' checked' : '' ?>>
            <label for="role"><?= e(t('users.make_admin')) ?></label>
        </div>
        <div class="button-row">
            <button type="submit" class="button button-primary"><?= e(t('users.create')) ?></button>
            <a class="button button-ghost" href="<?= e(url('admin/membres')) ?>"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
