<?php defined('CANTINE') || exit; ?>
<div class="page-head"><h1><?= e(t('admin.title')) ?></h1></div>
<?= partial('admin/nav') ?>

<?php if ($missingKeys): ?>
    <p class="callout"><?= e(t('admin.keys_missing')) ?> <a href="<?= e(url('admin/reglages')) ?>"><?= e(t('settings.title')) ?></a></p>
<?php endif; ?>

<ul class="stat-grid">
    <li><strong><?= (int) $counts['users'] ?></strong><span><?= e(t_plural('admin.active_members', $counts['users'])) ?></span></li>
    <li><strong><?= (int) $counts['media'] ?></strong><span><?= e(t_plural('profile.recos', $counts['media'])) ?></span></li>
    <li><strong><?= (int) $counts['comments'] ?></strong><span><?= e(t_plural('admin.comments', $counts['comments'])) ?></span></li>
</ul>

<section class="panel">
    <h2 class="section-title"><?= e(t('admin.maintenance')) ?></h2>
    <p><?= e($maintenance ? t('admin.maintenance_is_on') : t('admin.maintenance_is_off')) ?></p>
    <p class="hint"><?= e(t('admin.maintenance_hint')) ?></p>
    <form method="post" action="<?= e(url('admin/maintenance')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="button<?= $maintenance ? ' button-primary' : '' ?>"><?= e($maintenance ? t('admin.maintenance_disable') : t('admin.maintenance_enable')) ?></button>
    </form>
</section>

<section class="panel">
    <h2 class="section-title"><?= e(t('demo.title')) ?></h2>
    <p class="hint"><?= e(t('demo.explain')) ?></p>
    <?php if ($demoLoaded): ?>
        <?php if (is_array($demoCredentials)): ?>
            <p class="alert alert-info"><?= e(t('demo.credentials', ['users' => implode(', ', $demoCredentials['users']), 'password' => $demoCredentials['password']])) ?></p>
        <?php endif; ?>
        <?php if ($demoActivity['statuses'] > 0 || $demoActivity['comments'] > 0): ?>
            <p class="alert alert-warning"><?= e(t('demo.real_activity', [
                'statuses' => t_plural('media.n_statuses', $demoActivity['statuses']),
                'comments' => t_plural('media.n_comments', $demoActivity['comments']),
            ])) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= e(url('admin/demo/supprimer')) ?>" data-confirm="<?= e(t('demo.remove_confirm')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button button-danger"><?= e(t('demo.remove')) ?></button>
        </form>
    <?php else: ?>
        <form method="post" action="<?= e(url('admin/demo/charger')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="button"><?= e(t('demo.load')) ?></button>
        </form>
    <?php endif; ?>
</section>
