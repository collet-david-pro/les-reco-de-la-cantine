<?php defined('CANTINE') || exit; ?>
<section class="panel panel-narrow">
    <h1><?= e(t('users.delete_title')) ?></h1>
    <?php if ($blocked): ?>
        <p class="alert alert-info"><?= e(t('users.cannot_delete')) ?></p>
        <p><a class="button" href="<?= e(url('admin/membres')) ?>"><?= e(t('common.back')) ?></a></p>
    <?php else: ?>
        <p><?= e(t('users.delete_question', ['name' => $user['display_name']])) ?></p>
        <ul>
            <li><?= e(t('users.delete_keeps', ['n' => t_plural('media.n_recos', $counts['media'])])) ?></li>
            <li><?= e(t('users.delete_loses', ['statuses' => t_plural('media.n_statuses', $counts['statuses']), 'comments' => t_plural('media.n_comments', $counts['comments'])])) ?></li>
        </ul>
        <p class="alert alert-warning"><?= e(t('users.delete_prefer_deactivate')) ?></p>
        <form method="post" action="<?= e(url('admin/membres/supprimer')) ?>" class="form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
            <div class="field">
                <label for="confirm_username"><?= e(t('users.delete_type_username', ['username' => $user['username']])) ?></label>
                <input type="text" id="confirm_username" name="confirm_username" required autocomplete="off" autocapitalize="none">
            </div>
            <div class="button-row">
                <button type="submit" class="button button-danger"><?= e(t('users.delete_confirm')) ?></button>
                <a class="button button-ghost" href="<?= e(url('admin/membres/modifier', ['id' => $user['id']])) ?>"><?= e(t('common.cancel')) ?></a>
            </div>
        </form>
    <?php endif; ?>
</section>
