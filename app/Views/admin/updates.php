<?php defined('CANTINE') || exit;
/**
 * Encart "Mises à jour" du tableau de bord : version installée, nouvelle version éventuelle et liens.
 * Paramètre : $updates (null si la vérification est désactivée dans config.php).
 */
use Cantine\Services\UpdateChecker;

$available = $updates['available'] ?? null;
?>
<section class="panel<?= $available ? ' panel-update' : '' ?>" id="mises-a-jour" aria-labelledby="titre-maj">
    <h2 id="titre-maj" class="section-title"><?= e(t('updates.title')) ?></h2>
    <p><?= e(t('updates.installed', ['version' => CANTINE_VERSION])) ?></p>

    <?php if ($updates === null): ?>
        <p class="hint"><?= e(t('updates.disabled')) ?></p>
    <?php else: ?>
        <?php if ($available): ?>
            <p class="update-headline">
                <span aria-hidden="true">🎉</span>
                <strong><?= e(t('updates.available', ['version' => $available['version']])) ?></strong>
                <?php if (!empty($available['published_at'])): ?><span class="muted"><?= e(t('updates.published', ['date' => date_short($available['published_at'])])) ?></span><?php endif; ?>
            </p>
            <?php $notes = UpdateChecker::plainNotes((string) ($available['notes'] ?? '')); ?>
            <?php if ($notes !== ''): ?>
                <details class="update-notes">
                    <summary><?= e(t('updates.whats_new')) ?></summary>
                    <div class="update-notes-text"><?= nl2br(e($notes), false) ?></div>
                </details>
            <?php endif; ?>
            <ol class="update-steps">
                <li><?= e(t('updates.step_backup')) ?></li>
                <li><?= e(t('updates.step_maintenance')) ?></li>
                <li><?= e(t('updates.step_upload')) ?></li>
                <li><?= e(t('updates.step_migrate')) ?></li>
                <li><?= e(t('updates.step_reopen')) ?></li>
            </ol>
            <div class="button-row">
                <a class="button button-primary" href="<?= e($available['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('updates.see_release')) ?> <?= partial('partials/icon', ['name' => 'external']) ?></a>
                <?php if (!empty($available['zip_url'])): ?>
                    <a class="button" href="<?= e($available['zip_url']) ?>" rel="noopener noreferrer"><?= e(t('updates.download_zip')) ?></a>
                <?php endif; ?>
            </div>
        <?php elseif (!empty($updates['error'])): ?>
            <p class="alert alert-warning"><?= e(t('updates.error', ['error' => $updates['error']])) ?></p>
        <?php else: ?>
            <p class="alert alert-success"><?= e(t('updates.none')) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e(url('admin/mises-a-jour/verifier')) ?>" class="update-check">
            <?= csrf_field() ?>
            <span class="hint"><?= e($updates['checked_at'] ? t('updates.checked', ['date' => date_relative($updates['checked_at'])]) : t('updates.never_checked')) ?></span>
            <button type="submit" class="link-button"><?= e(t('updates.check_now')) ?></button>
        </form>
    <?php endif; ?>
</section>
