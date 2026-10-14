<?php defined('CANTINE') || exit;
/** Mise en page minimale (connexion, maintenance, erreurs hors connexion). */
?>
<!doctype html>
<html lang="fr">
<head>
<?php require __DIR__ . '/head.php'; ?>
</head>
<body class="bare" data-base="<?= e(\Cantine\App::baseUrl()) ?>">
<main id="contenu" class="bare-main">
    <div class="bare-brand">
        <img src="<?= e(asset('img/favicon.svg')) ?>" alt="" width="56" height="56">
        <p class="bare-title"><?= e($siteName) ?></p>
    </div>
    <?= partial('partials/flash') ?>
    <?= $content ?>
</main>
</body>
</html>
