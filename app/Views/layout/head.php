<?php defined('CANTINE') || exit; ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= isset($title) ? e($title) . ' · ' : '' ?><?= e($siteName) ?></title>
<meta name="theme-color" content="#C2410C">
<meta name="referrer" content="strict-origin-when-cross-origin">
<link rel="manifest" href="<?= e(url('manifest')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(asset('img/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
