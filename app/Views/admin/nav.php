<?php defined('CANTINE') || exit;
/** Sous-navigation de l'administration. */
$items = [
    'admin' => t('admin.dashboard'),
    'admin/membres' => t('users.title'),
    'admin/reglages' => t('settings.title'),
    'admin/sauvegarde' => t('backup.title'),
    'admin/diagnostic' => t('admin.diagnostics'),
];
?>
<nav class="tabs admin-tabs" aria-label="<?= e(t('admin.title')) ?>">
    <?php foreach ($items as $route => $label):
        $active = $currentRoute === $route || ($route !== 'admin' && str_starts_with($currentRoute, $route)); ?>
        <a href="<?= e(url($route)) ?>" class="tabs-item<?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
