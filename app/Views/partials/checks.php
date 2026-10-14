<?php defined('CANTINE') || exit;
/** Liste de vérifications ✔ / ⚠ / ✘. Paramètre : $checks. */
$symbols = ['ok' => '✔', 'warn' => '⚠', 'fail' => '✘'];
?>
<ul class="checks">
    <?php foreach ($checks as $check): ?>
        <li class="check check-<?= e($check['status']) ?>">
            <span class="check-symbol" aria-hidden="true"><?= $symbols[$check['status']] ?? '?' ?></span>
            <span class="sr-only"><?= e(t('diagnostics.status_' . $check['status'])) ?> :</span>
            <span class="check-label"><?= e($check['label']) ?></span>
            <?php if ($check['detail'] !== ''): ?><span class="check-detail"><?= e($check['detail']) ?></span><?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
