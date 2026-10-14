<?php defined('CANTINE') || exit; ?>
<div class="page-head">
    <h1><?= e(t('home.title')) ?></h1>
    <a class="button button-primary" href="<?= e(url('ajouter')) ?>"><?= partial('partials/icon', ['name' => 'plus']) ?> <?= e(t('home.add')) ?></a>
</div>

<?php if ($newCount > 0): ?>
    <p class="callout">
        <?= e(t_plural('home.new_since', $newCount)) ?>
        <a href="<?= e(url('liste', ['vue' => 'a_decouvrir'])) ?>"><?= e(t('home.see_list')) ?></a>
    </p>
<?php endif; ?>

<?php if (!$events): ?>
    <section class="panel empty">
        <p class="empty-icon" aria-hidden="true">🍲</p>
        <h2><?= e(t('home.empty_title')) ?></h2>
        <p><?= e(t('home.empty_text')) ?></p>
        <p><a class="button button-primary" href="<?= e(url('ajouter')) ?>"><?= e(t('home.empty_cta')) ?></a></p>
        <?php if ($isAdmin): ?><p class="hint"><?= e(t('home.empty_admin')) ?> <a href="<?= e(url('admin')) ?>"><?= e(t('nav.admin')) ?></a></p><?php endif; ?>
    </section>
<?php else: ?>
    <ol class="feed">
        <?php foreach ($events as $event): ?>
            <?= partial('partials/feed_event', ['event' => $event]) ?>
        <?php endforeach; ?>
    </ol>
    <nav class="pagination" aria-label="<?= e(t('pagination.label')) ?>">
        <?php if ($page > 1): ?><a class="button button-ghost" href="<?= e(url('accueil', ['page' => $page - 1])) ?>">← <?= e(t('feed.newer')) ?></a><?php endif; ?>
        <?php if ($hasMore): ?><a class="button button-ghost" href="<?= e(url('accueil', ['page' => $page + 1])) ?>"><?= e(t('feed.older')) ?> →</a><?php endif; ?>
    </nav>
<?php endif; ?>
