<?php defined('CANTINE') || exit; ?>
<section class="panel">
    <h1><?= e(t('merge.title')) ?></h1>
    <p><?= e(t('merge.intro', ['title' => $media['title']])) ?></p>
    <form method="get" action="<?= e(url()) ?>" class="form form-inline">
        <input type="hidden" name="route" value="media/fusionner">
        <input type="hidden" name="id" value="<?= (int) $media['id'] ?>">
        <label for="merge-q" class="sr-only"><?= e(t('list.search')) ?></label>
        <input type="search" id="merge-q" name="q" value="<?= e($query) ?>">
        <button type="submit" class="button"><?= e(t('list.search')) ?></button>
    </form>
    <?php if (!$candidates): ?>
        <p class="muted"><?= e(t('merge.none')) ?></p>
    <?php else: ?>
        <ul class="result-list">
            <?php foreach ($candidates as $c): ?>
                <li class="result">
                    <?= partial('partials/cover', ['media' => $c, 'size' => 'thumb']) ?>
                    <div class="result-text">
                        <a href="<?= e(url('media', ['id' => $c['id']])) ?>" class="result-title"><?= e($c['title']) ?></a>
                        <span class="muted"><?= e((string) $c['year']) ?> · <?= e($c['added_by_name'] ?? t('user.former')) ?></span>
                    </div>
                    <form method="post" action="<?= e(url('media/fusionner')) ?>" data-confirm="<?= e(t('merge.confirm', ['source' => $media['title'], 'target' => $c['title']])) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $media['id'] ?>">
                        <input type="hidden" name="target_id" value="<?= (int) $c['id'] ?>">
                        <button type="submit" class="button button-small"><?= e(t('merge.into')) ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p class="hint"><?= e(t('merge.explain')) ?></p>
    <p><a class="button button-ghost" href="<?= e(url('media', ['id' => $media['id']])) ?>"><?= e(t('common.cancel')) ?></a></p>
</section>
