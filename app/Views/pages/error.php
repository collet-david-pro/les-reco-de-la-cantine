<?php defined('CANTINE') || exit; ?>
<section class="panel panel-narrow empty">
    <p class="empty-icon" aria-hidden="true"><?= $status === 404 ? '🍽️' : ($status === 403 ? '🔒' : '🫠') ?></p>
    <h1><?= e(t('error.title_' . ($status === 404 ? '404' : ($status === 403 ? '403' : 'other')))) ?></h1>
    <p><?= e($message) ?></p>
    <p><a class="button" href="<?= e(url('')) ?>"><?= e(t('error.back_home')) ?></a></p>
</section>
