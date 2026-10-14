<?php defined('CANTINE') || exit; ?>
<div class="page-head">
    <h1><?= e(t('media.edit_title')) ?></h1>
</div>
<section class="panel">
    <?php if ($errors): ?><p class="alert alert-error" role="alert"><?= e(t('form.has_errors')) ?></p><?php endif; ?>
    <?php if ($media['source'] !== 'manuel'): ?>
        <p class="hint"><?= e(t('media.edit_api_hint')) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('media/modifier')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $media['id'] ?>">
        <?= partial('partials/media_fields', ['type' => $media['type'], 'values' => $values, 'errors' => $errors, 'full' => $media['source'] === 'manuel']) ?>
        <div class="button-row">
            <button type="submit" class="button button-primary"><?= e(t('common.save')) ?></button>
            <a class="button button-ghost" href="<?= e(url('media', ['id' => $media['id']])) ?>"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
