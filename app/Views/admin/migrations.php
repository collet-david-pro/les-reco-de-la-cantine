<?php defined('CANTINE') || exit; ?>
<section class="panel panel-narrow">
    <h1><?= e(t('migrations.title')) ?></h1>
    <?php if ($pending): ?>
        <p><?= e(t('migrations.needed', ['current' => (string) $current, 'latest' => (string) $latest])) ?></p>
        <ul><?php foreach ($pending as $name): ?><li><code><?= e($name) ?></code></li><?php endforeach; ?></ul>
        <p class="alert alert-warning"><?= e(t('migrations.backup_first')) ?></p>
        <div class="button-row">
            <form method="post" action="<?= e(url('admin/sauvegarde/exporter')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="button"><?= e(t('backup.download')) ?></button>
            </form>
            <form method="post" action="<?= e(url('admin/migrations')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="button button-primary"><?= e(t('migrations.run')) ?></button>
            </form>
        </div>
    <?php else: ?>
        <p><?= e(t('migrations.up_to_date', ['current' => (string) $current])) ?></p>
        <p><a class="button" href="<?= e(url('admin')) ?>"><?= e(t('common.back')) ?></a></p>
    <?php endif; ?>
</section>
