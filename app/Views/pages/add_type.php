<?php defined('CANTINE') || exit;
use Cantine\Types;
?>
<div class="page-head">
    <h1><?= e(t('add.title')) ?></h1>
</div>
<p class="lead"><?= e(t('add.choose_type')) ?></p>
<ul class="type-grid">
    <?php foreach (Types::ALL as $code => $def): ?>
        <li>
            <a class="type-button" href="<?= e(url('ajouter/recherche', ['type' => $code])) ?>">
                <span class="type-icon" aria-hidden="true"><?= $def['icon'] ?></span>
                <span class="type-label"><?= e($def['label']) ?></span>
                <?php if (!$available[$code]): ?><span class="type-note"><?= e(t('add.manual_only')) ?></span><?php endif; ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>
