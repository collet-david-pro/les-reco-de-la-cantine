<?php defined('CANTINE') || exit;
$active = (int) $user['is_active'] === 1;
?>
<div class="page-head"><h1><?= e($user['display_name']) ?> <span class="muted">@<?= e($user['username']) ?></span></h1></div>
<?= partial('admin/nav') ?>

<section class="panel">
    <h2 class="section-title"><?= e(t('users.edit_title')) ?></h2>
    <form method="post" action="<?= e(url('admin/membres/modifier')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
        <div class="field">
            <label for="display_name"><?= e(t('account.display_name')) ?></label>
            <input type="text" id="display_name" name="display_name" value="<?= e($user['display_name']) ?>" required maxlength="50">
        </div>
        <div class="field field-check">
            <input type="checkbox" id="role" name="role" value="admin"<?= $user['role'] === 'admin' ? ' checked' : '' ?><?= $isLastAdmin ? ' disabled' : '' ?>>
            <label for="role"><?= e(t('users.is_admin')) ?></label>
            <?php if ($isLastAdmin): ?><input type="hidden" name="role" value="admin"><p class="hint"><?= e(t('users.last_admin')) ?></p><?php endif; ?>
        </div>
        <button type="submit" class="button button-primary"><?= e(t('common.save')) ?></button>
    </form>
</section>

<section class="panel">
    <h2 class="section-title"><?= e(t('users.password_section')) ?></h2>
    <p class="hint"><?= e(t('users.reset_explain')) ?></p>
    <form method="post" action="<?= e(url('admin/membres/mot-de-passe')) ?>" data-confirm="<?= e(t('users.reset_confirm', ['name' => $user['display_name']])) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
        <button type="submit" class="button"><?= e(t('users.reset_password')) ?></button>
    </form>
</section>

<section class="panel">
    <h2 class="section-title"><?= e(t('users.access_section')) ?></h2>
    <?php if (!$isSelf && !($isLastAdmin && $active)): ?>
        <p class="hint"><?= e($active ? t('users.deactivate_explain') : t('users.activate_explain')) ?></p>
        <form method="post" action="<?= e(url('admin/membres/activer')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
            <button type="submit" class="button"><?= e($active ? t('users.deactivate') : t('users.activate')) ?></button>
        </form>
        <p class="hint"><?= e(t('users.delete_explain')) ?></p>
        <a class="button button-danger" href="<?= e(url('admin/membres/supprimer', ['id' => $user['id']])) ?>"><?= e(t('users.delete')) ?></a>
    <?php else: ?>
        <p class="muted"><?= e($isSelf ? t('users.self_protected') : t('users.last_admin')) ?></p>
    <?php endif; ?>
</section>
