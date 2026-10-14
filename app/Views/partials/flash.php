<?php defined('CANTINE') || exit;
/** Messages "flash" (succès, erreur, information) affichés après une action. */
$messages = \Cantine\Support\Flash::take();
if (!$messages) {
    return;
}
?>
<div class="flashes">
    <?php foreach ($messages as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>">
            <span><?= e($flash['message']) ?></span>
            <button type="button" class="flash-close" aria-label="<?= e(t('common.close')) ?>" hidden>×</button>
        </div>
    <?php endforeach; ?>
</div>
