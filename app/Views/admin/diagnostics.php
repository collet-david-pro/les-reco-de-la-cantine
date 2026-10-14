<?php defined('CANTINE') || exit; ?>
<div class="page-head"><h1><?= e(t('admin.diagnostics')) ?></h1></div>
<?= partial('admin/nav') ?>
<section class="panel">
    <h2 class="section-title"><?= e(t('diagnostics.checks')) ?></h2>
    <?= partial('partials/checks', ['checks' => $checks]) ?>
</section>
<section class="panel">
    <h2 class="section-title"><?= e(t('diagnostics.info')) ?></h2>
    <dl class="facts facts-wide">
        <?php foreach ($info as $label => $value): ?>
            <div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div>
        <?php endforeach; ?>
    </dl>
</section>
