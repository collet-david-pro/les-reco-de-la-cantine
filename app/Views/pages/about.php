<?php defined('CANTINE') || exit; ?>
<section class="panel prose">
    <h1><?= e(t('about.title')) ?></h1>
    <p><?= e(t('about.intro', ['site' => $siteName])) ?></p>
    <p><?= e(t('about.no_ai')) ?></p>

    <h2><?= e(t('about.sources')) ?></h2>
    <ul class="attributions">
        <li>
            <a href="https://www.themoviedb.org/" target="_blank" rel="noopener noreferrer"><img src="<?= e(asset('img/tmdb.svg')) ?>" alt="TMDB" width="120" height="16" class="tmdb-logo"></a>
            <p><?= e(t('about.tmdb')) ?></p>
        </li>
        <li><p><?= e(t('about.justwatch')) ?> <a href="https://www.justwatch.com/" target="_blank" rel="noopener noreferrer">JustWatch</a>.</p></li>
        <li><p><?= e(t('about.openlibrary')) ?> <a href="https://openlibrary.org/" target="_blank" rel="noopener noreferrer">Open Library</a>.</p></li>
        <li><p><?= e(t('about.rawg')) ?> <a href="https://rawg.io/" target="_blank" rel="noopener noreferrer">RAWG</a>.</p></li>
        <li><p><?= e(t('about.deezer')) ?> <a href="https://www.deezer.com/" target="_blank" rel="noopener noreferrer">Deezer</a>.</p></li>
        <li><p><?= e(t('about.youtube')) ?></p></li>
    </ul>

    <h2><?= e(t('about.software')) ?></h2>
    <p><?= e(t('about.license', ['version' => $version])) ?> <a href="<?= e(CANTINE_REPO_URL) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('about.source_code')) ?></a></p>
</section>
