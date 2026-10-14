<?php defined('CANTINE') || exit; ?>
<div class="page-head">
    <h1><?= e(t('users.title')) ?></h1>
    <a class="button button-primary" href="<?= e(url('admin/membres/nouveau')) ?>"><?= partial('partials/icon', ['name' => 'plus']) ?> <?= e(t('users.create')) ?></a>
</div>
<?= partial('admin/nav') ?>

<?php if ($created): ?>
    <section class="alert alert-success password-reveal" role="status">
        <p><strong><?= e($created['reset'] ? t('users.reset_done') : t('users.created_done')) ?></strong></p>
        <p><?= e(t('users.give_credentials')) ?></p>
        <dl class="facts">
            <div><dt><?= e(t('auth.username')) ?></dt><dd><code><?= e($created['username']) ?></code></dd></div>
            <div><dt><?= e(t('auth.password')) ?></dt><dd><code data-copy><?= e($created['password']) ?></code></dd></div>
        </dl>
        <p class="hint"><?= e(t('users.shown_once')) ?></p>
    </section>
<?php endif; ?>

<ul class="user-list">
    <?php foreach ($users as $u): ?>
        <li class="user-row<?= (int) $u['is_active'] === 0 ? ' is-inactive' : '' ?>">
            <?= partial('partials/avatar', ['name' => $u['display_name'], 'color' => $u['avatar_color'], 'size' => 'md']) ?>
            <div class="user-info">
                <p>
                    <strong><?= e($u['display_name']) ?></strong>
                    <span class="muted">@<?= e($u['username']) ?></span>
                    <?php if ($u['role'] === 'admin'): ?><span class="badge"><?= e(t('users.role_admin')) ?></span><?php endif; ?>
                    <?php if ((int) $u['is_active'] === 0): ?><span class="badge badge-muted"><?= e(t('users.inactive')) ?></span><?php endif; ?>
                    <?php if ((int) $u['is_demo'] === 1): ?><span class="badge badge-muted"><?= e(t('users.demo')) ?></span><?php endif; ?>
                </p>
                <p class="muted small">
                    <?= e(t_plural('media.n_recos', (int) $u['media_count'])) ?> · <?= e(t('users.seen_n', ['n' => (string) $u['seen_count']])) ?> ·
                    <?= e($u['last_seen_at'] ? t('users.last_seen', ['date' => date_relative($u['last_seen_at'])]) : t('users.never_seen')) ?>
                </p>
            </div>
            <div class="user-actions">
                <a class="button button-small" href="<?= e(url('admin/membres/modifier', ['id' => $u['id']])) ?>"><?= e(t('common.manage')) ?></a>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
