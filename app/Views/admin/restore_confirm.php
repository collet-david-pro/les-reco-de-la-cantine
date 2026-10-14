<?php defined('CANTINE') || exit;
$s = $result['summary'];
?>
<div class="page-head"><h1><?= e(t('backup.restore_title')) ?></h1></div>
<?= partial('admin/nav') ?>
<section class="panel">
    <?php if (!$result['ok']): ?>
        <p class="alert alert-error" role="alert"><?= e(t('backup.invalid')) ?></p>
        <ul class="checks">
            <?php foreach ($result['errors'] as $error): ?>
                <li class="check check-fail"><span class="check-symbol" aria-hidden="true">✘</span><span class="check-label"><?= e($error) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <p class="hint"><?= e(t('backup.nothing_changed')) ?></p>
        <p><a class="button" href="<?= e(url('admin/sauvegarde')) ?>"><?= e(t('common.back')) ?></a></p>
    <?php else: ?>
        <p class="alert alert-success"><?= e(t('backup.valid')) ?></p>
        <dl class="facts facts-wide">
            <div><dt><?= e(t('backup.summary_date')) ?></dt><dd><?= e($s['created_at']) ?></dd></div>
            <div><dt><?= e(t('backup.summary_version')) ?></dt><dd><?= e($s['app_version']) ?> (<?= e(t('backup.schema')) ?> <?= (int) $s['schema_version'] ?>)</dd></div>
            <div><dt><?= e(t('backup.summary_users')) ?></dt><dd><?= (int) $s['users'] ?></dd></div>
            <div><dt><?= e(t('backup.summary_media')) ?></dt><dd><?= (int) $s['media'] ?></dd></div>
            <div><dt><?= e(t('backup.summary_ratings')) ?></dt><dd><?= (int) $s['ratings'] ?></dd></div>
            <div><dt><?= e(t('backup.summary_comments')) ?></dt><dd><?= (int) $s['comments'] ?></dd></div>
        </dl>
        <?php if (!$s['has_secrets']): ?><p class="hint"><?= e(t('backup.keys_kept')) ?></p><?php endif; ?>
        <form method="post" action="<?= e(url('admin/restauration/executer')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="field-check">
                <input type="checkbox" id="understand" name="understand" value="1" required>
                <label for="understand"><?= e(t('backup.understand')) ?></label>
            </div>
            <div class="field">
                <label for="password"><?= e(t('backup.admin_password')) ?></label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <p class="hint"><?= e(t('backup.safety_auto')) ?></p>
            <div class="button-row">
                <button type="submit" class="button button-danger"><?= e(t('backup.restore_now')) ?></button>
                <a class="button button-ghost" href="<?= e(url('admin/sauvegarde')) ?>"><?= e(t('common.cancel')) ?></a>
            </div>
        </form>
    <?php endif; ?>
</section>
