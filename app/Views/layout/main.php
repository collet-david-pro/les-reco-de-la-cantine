<?php defined('CANTINE') || exit;
/** Mise en page des pages connectées : en-tête, bandeaux, messages, contenu, navigation. */
$navItems = [
    ['accueil', 'home', t('nav.home')],
    ['liste', 'list', t('nav.list')],
    ['ajouter', 'plus', t('nav.add')],
    ['hasard', 'dice', t('nav.random')],
    ['membre', 'user', t('nav.profile')],
];
$routeGroup = explode('/', $currentRoute)[0];
?>
<!doctype html>
<html lang="fr">
<head>
<?php require __DIR__ . '/head.php'; ?>
</head>
<body data-base="<?= e(\Cantine\App::baseUrl()) ?>" data-csrf="<?= e(\Cantine\Support\Csrf::token()) ?>">
<a class="skip-link" href="#contenu"><?= e(t('nav.skip')) ?></a>
<header class="topbar">
    <a class="brand" href="<?= e(url('accueil')) ?>">
        <img src="<?= e(asset('img/favicon.svg')) ?>" alt="" width="28" height="28">
        <span><?= e($siteName) ?></span>
    </a>
    <nav class="tabbar" aria-label="<?= e(t('nav.main')) ?>">
        <?php foreach ($navItems as [$route, $icon, $label]):
            $active = $routeGroup === $route || ($route === 'membre' && in_array($routeGroup, ['mon-compte', 'admin'], true));
            ?>
            <a href="<?= e(url($route)) ?>" class="tab<?= $route === 'ajouter' ? ' tab-add' : '' ?><?= $active ? ' is-active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                <?= partial('partials/icon', ['name' => $icon]) ?>
                <span class="tab-label"><?= e($label) ?></span>
                <?php if ($route === 'liste' && $newCount > 0): ?>
                    <span class="tab-badge" title="<?= e(t_plural('nav.new_count', $newCount)) ?>"><?= $newCount > 99 ? '99+' : (int) $newCount ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="topbar-links">
        <?php if ($isAdmin): ?><a href="<?= e(url('admin')) ?>"><?= e(t('nav.admin')) ?></a><?php endif; ?>
        <a href="<?= e(url('mon-compte')) ?>"><?= e(t('nav.account')) ?></a>
        <form method="post" action="<?= e(url('deconnexion')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="link-button"><?= e(t('nav.logout')) ?></button>
        </form>
    </div>
</header>

<?php if ($maintenanceOn && $isAdmin): ?>
    <div class="banner banner-warning" role="status">
        <?= e(t('banner.maintenance')) ?> <a href="<?= e(url('admin')) ?>"><?= e(t('banner.maintenance_link')) ?></a>
    </div>
<?php endif; ?>
<?php if (!empty($updateAvailable) && $currentRoute !== 'admin'): ?>
    <div class="banner banner-update" role="status">
        <?= e(t('updates.banner', ['version' => $updateAvailable['version']])) ?>
        <a href="<?= e(url('admin')) ?>#mises-a-jour"><?= e(t('updates.banner_link')) ?></a>
    </div>
<?php endif; ?>
<?php if ($demoLoaded): ?>
    <div class="banner banner-info" role="status"><?= e(t('banner.demo')) ?></div>
<?php endif; ?>

<main id="contenu" class="page">
    <?= partial('partials/flash') ?>
    <?= $content ?>
</main>

<footer class="footer">
    <a href="<?= e(url('a-propos')) ?>"><?= e(t('footer.about')) ?></a>
    <span aria-hidden="true">·</span>
    <span><?= e(t('footer.tmdb')) ?></span>
</footer>
</body>
</html>
