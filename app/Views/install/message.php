<?php defined('CANTINE') || exit; ?>
<section class="panel">
    <h1><?= e($heading) ?></h1>
    <p class="alert alert-<?= e($kind) ?>"><?= e($message) ?></p>
    <?php if (!empty($link)): ?><p><a class="button button-primary" href="<?= e($link[0]) ?>"><?= e($link[1]) ?></a></p><?php endif; ?>
</section>
