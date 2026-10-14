<?php defined('CANTINE') || exit;
/** Pagination. Paramètres : $page, $pages, $route, $params (paramètres GET à conserver). */
if ($pages <= 1) {
    return;
}
?>
<nav class="pagination" aria-label="<?= e(t('pagination.label')) ?>">
    <?php if ($page > 1): ?>
        <a class="button button-ghost" href="<?= e(url($route, ['page' => $page - 1] + $params)) ?>" rel="prev">← <?= e(t('pagination.prev')) ?></a>
    <?php endif; ?>
    <span class="pagination-info"><?= e(t('pagination.info', ['page' => (string) $page, 'pages' => (string) $pages])) ?></span>
    <?php if ($page < $pages): ?>
        <a class="button button-ghost" href="<?= e(url($route, ['page' => $page + 1] + $params)) ?>" rel="next"><?= e(t('pagination.next')) ?> →</a>
    <?php endif; ?>
</nav>
