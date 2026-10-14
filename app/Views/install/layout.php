<?php defined('CANTINE') || exit;
/** Mise en page de l'installeur (autonome : ne dépend ni de la base ni de la session du site). */
$base = \Cantine\App::baseUrl();
$v = rawurlencode(CANTINE_VERSION);
$steps = [1 => 'Environnement', 2 => 'Base de données', 3 => 'Administrateur', 4 => 'Terminé'];
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Installation') ?> · Les reco de la cantine</title>
<link rel="icon" href="<?= e($base) ?>assets/img/favicon.svg?v=<?= e($v) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e($base) ?>assets/css/app.css?v=<?= e($v) ?>">
<script src="<?= e($base) ?>assets/js/app.js?v=<?= e($v) ?>" defer></script>
</head>
<body class="bare installer">
<main class="bare-main bare-wide">
    <div class="bare-brand">
        <img src="<?= e($base) ?>assets/img/favicon.svg?v=<?= e($v) ?>" alt="" width="56" height="56">
        <p class="bare-title">Installation de « Les reco de la cantine »</p>
    </div>
    <?php if (isset($step)): ?>
        <ol class="steps" aria-label="Étapes">
            <?php foreach ($steps as $n => $label): ?>
                <li class="<?= $n === $step ? 'is-current' : ($n < $step ? 'is-done' : '') ?>"<?= $n === $step ? ' aria-current="step"' : '' ?>><span><?= $n ?></span> <?= e($label) ?></li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <?php if (!empty($flash)): ?><p class="alert alert-error" role="alert"><?= e($flash) ?></p><?php endif; ?>
    <?= $content ?>
    <p class="bare-footer">Version <?= e(CANTINE_VERSION) ?></p>
</main>
</body>
</html>
