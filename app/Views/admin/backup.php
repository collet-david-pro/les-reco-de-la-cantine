<?php defined('CANTINE') || exit; ?>
<div class="page-head"><h1><?= e(t('backup.title')) ?></h1></div>
<?= partial('admin/nav') ?>

<section class="panel">
    <h2 class="section-title"><?= e(t('backup.export_title')) ?></h2>
    <p><?= e(t('backup.export_text')) ?></p>
    <p class="alert alert-warning"><?= e(t('backup.contains_hashes')) ?></p>
    <form method="post" action="<?= e(url('admin/sauvegarde/exporter')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="field-check">
            <input type="checkbox" id="include_keys" name="include_keys" value="1">
            <label for="include_keys"><?= e(t('backup.include_keys')) ?></label>
        </div>
        <div class="field-check">
            <input type="checkbox" id="include_demo" name="include_demo" value="1">
            <label for="include_demo"><?= e(t('backup.include_demo')) ?></label>
        </div>
        <button type="submit" class="button button-primary"><?= e(t('backup.download')) ?></button>
    </form>
</section>

<section class="panel">
    <h2 class="section-title"><?= e(t('backup.restore_title')) ?></h2>
    <p><?= e(t('backup.restore_text')) ?></p>
    <p class="hint"><?= e(t('backup.limits', ['upload' => (string) $uploadMax, 'post' => (string) $postMax])) ?></p>
    <form method="post" action="<?= e(url('admin/restauration/envoyer')) ?>" enctype="multipart/form-data" class="form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="backup"><?= e(t('backup.file')) ?></label>
            <input type="file" id="backup" name="backup" accept=".json,application/json" required>
        </div>
        <button type="submit" class="button"><?= e(t('backup.check')) ?></button>
    </form>
</section>

<section class="panel">
    <h2 class="section-title"><?= e(t('backup.safety_title')) ?></h2>
    <p class="hint"><?= e(t('backup.safety_text')) ?></p>
    <?php if (!$safetyFiles): ?>
        <p class="muted"><?= e(t('backup.safety_none')) ?></p>
    <?php else: ?>
        <ul class="file-list">
            <?php foreach ($safetyFiles as $file): ?>
                <li>
                    <a href="<?= e(url('admin/sauvegarde/telecharger', ['fichier' => $file['name']])) ?>"><?= e($file['name']) ?></a>
                    <span class="muted"><?= e(number_fr($file['size'] / 1024, 0)) ?> Ko</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
