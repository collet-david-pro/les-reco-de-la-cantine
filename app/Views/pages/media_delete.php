<?php defined('CANTINE') || exit; ?>
<section class="panel panel-narrow">
    <h1><?= e(t('media.delete_title')) ?></h1>
    <p><?= e(t('media.delete_question', ['title' => $media['title']])) ?></p>
    <?php if ($others['statuses'] > 0 || $others['comments'] > 0): ?>
        <p class="alert alert-warning">
            <?= e(t('media.delete_will_lose', [
                'statuses' => t_plural('media.n_statuses', $others['statuses']),
                'comments' => t_plural('media.n_comments', $others['comments']),
            ])) ?>
        </p>
    <?php endif; ?>
    <?php if ($allowed): ?>
        <form method="post" action="<?= e(url('media/supprimer')) ?>" class="button-row">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $media['id'] ?>">
            <button type="submit" class="button button-danger"><?= e(t('media.delete_confirm')) ?></button>
            <a class="button button-ghost" href="<?= e(url('media', ['id' => $media['id']])) ?>"><?= e(t('common.cancel')) ?></a>
        </form>
    <?php else: ?>
        <p class="alert alert-info"><?= e(t('media.delete_blocked')) ?></p>
        <p><a class="button" href="<?= e(url('media', ['id' => $media['id']])) ?>"><?= e(t('common.back')) ?></a></p>
    <?php endif; ?>
</section>
