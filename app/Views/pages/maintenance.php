<?php defined('CANTINE') || exit; ?>
<section class="panel panel-narrow empty">
    <p class="empty-icon" aria-hidden="true">🧑‍🍳</p>
    <h1><?= e(t('maintenance.title')) ?></h1>
    <p><?= e(t('maintenance.text')) ?></p>
    <p class="hint"><a href="<?= e(url('connexion')) ?>"><?= e(t('maintenance.admin_login')) ?></a></p>
</section>
