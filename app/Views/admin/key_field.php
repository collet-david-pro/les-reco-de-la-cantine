<?php defined('CANTINE') || exit;
/** Champ de clé d'API : jamais pré-rempli (la clé n'est pas renvoyée au navigateur), case pour la supprimer. */
?>
<div class="field">
    <label for="<?= e($name) ?>"><?= e($label) ?></label>
    <input type="password" id="<?= e($name) ?>" name="<?= e($name) ?>" value="" autocomplete="off" placeholder="<?= e($masked !== '' ? $masked : t('settings.no_key')) ?>">
    <?php if ($help !== '' || $masked !== ''): ?>
        <p class="hint"><?= e($help) ?> <?= e($masked !== '' ? t('settings.key_keep') : '') ?></p>
    <?php endif; ?>
    <?php if ($masked !== ''): ?>
        <div class="field-check">
            <input type="checkbox" id="remove_<?= e($name) ?>" name="remove_<?= e($name) ?>" value="1">
            <label for="remove_<?= e($name) ?>"><?= e(t('settings.key_remove')) ?></label>
        </div>
    <?php endif; ?>
</div>
