<?php defined('CANTINE') || exit;
/**
 * Fiche d'un contenu. Ordre sur mobile : jaquette + titre + infos clés, MES actions, liens, avis du groupe, commentaires.
 */
use Cantine\Types;

$type = $media['type'];
$extraLines = Types::formatExtra($type, $media['extra']);
$imdb = $media['extra']['imdb_id'] ?? null;
$sourceLabels = ['tmdb' => 'TMDB', 'openlibrary' => 'Open Library', 'rawg' => 'RAWG', 'deezer' => 'Deezer', 'youtube' => 'YouTube'];
?>
<article class="media-page">
    <header class="media-hero">
        <?= partial('partials/cover', ['media' => $media, 'size' => 'large']) ?>
        <div class="media-hero-text">
            <p class="media-kicker"><span aria-hidden="true"><?= Types::icon($type) ?></span> <?= e(Types::label($type)) ?><?= $media['year'] ? ' · ' . e($media['year']) : '' ?>
                <?php if ($isNew): ?><span class="badge badge-new"><?= e(t('common.new')) ?></span><?php endif; ?>
            </p>
            <h1 class="media-title"><?= e($media['title']) ?></h1>
            <?php if ($genres): ?>
                <ul class="chips" aria-label="<?= e(t('media.genres')) ?>">
                    <?php foreach ($genres as $genre): ?>
                        <li><a class="chip" href="<?= e(url('liste', ['vue' => 'tout', 'genre' => $genre['id']])) ?>"><?= e($genre['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="media-avg" data-avg-block<?= $stats['avg_rating'] === null ? ' hidden' : '' ?>>
                <span class="media-avg-star" aria-hidden="true">★</span>
                <span><strong data-avg><?= $stats['avg_rating'] !== null ? e(number_fr($stats['avg_rating'])) : '' ?></strong>/5</span>
                <span class="muted"><?= e(t('media.group_average')) ?> · <span data-rating-count><?= e(t_plural('media.rating_count', $stats['rating_count'])) ?></span></span>
            </p>
            <p class="media-by">
                <?= partial('partials/avatar', ['name' => $media['added_by_name'], 'color' => $media['added_by_color']]) ?>
                <span>
                <?php if ($media['added_by'] !== null): ?>
                    <?= t('media.recommended_by', [
                        'who' => '<a href="' . e(url('membre', ['id' => $media['added_by']])) . '">' . e($media['added_by_name']) . '</a>',
                        'date' => e(date_short($media['created_at'])),
                    ]) ?>
                <?php else: ?>
                    <?= e(t('media.recommended_by_former', ['date' => date_short($media['created_at'])])) ?>
                <?php endif; ?>
                </span>
            </p>
            <?php if ($extraLines): ?>
                <dl class="facts">
                    <?php foreach ($extraLines as [$label, $value]): ?>
                        <div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div>
                    <?php endforeach; ?>
                    <?php if ($media['isbn']): ?><div><dt><?= e(t('media.isbn')) ?></dt><dd><?= e($media['isbn']) ?></dd></div><?php endif; ?>
                </dl>
            <?php endif; ?>
        </div>
    </header>

    <?= partial('partials/my_actions', ['media' => $media, 'mine' => $mine]) ?>

    <?php if ($media['description']): ?>
        <section class="panel">
            <h2 class="section-title"><?= e(t('media.summary')) ?></h2>
            <p class="description"><?= nl2br(e($media['description']), false) ?></p>
        </section>
    <?php endif; ?>

    <?php
    $links = [];
    if ($media['trailer_url']) {
        $links[] = [t('media.trailer'), $media['trailer_url'], 'primary'];
    }
    if ($type === 'musique' && $media['external_url']) {
        $links[] = [t('media.listen_deezer'), $media['external_url'], 'primary'];
    } elseif ($type === 'video' && $media['external_url']) {
        $links[] = [t('media.watch_youtube'), $media['external_url'], 'primary'];
    } elseif ($media['external_url']) {
        $links[] = [isset($sourceLabels[$media['source']]) ? t('media.see_on', ['site' => $sourceLabels[$media['source']]]) : t('media.link'), $media['external_url'], ''];
    }
    if ($imdb) {
        $links[] = [t('media.see_on', ['site' => 'IMDb']), 'https://www.imdb.com/title/' . $imdb . '/', ''];
    }
    ?>
    <?php if ($links || $shopLinks || $providers !== null): ?>
        <section class="panel">
            <h2 class="section-title"><?= e(t('media.links')) ?></h2>
            <?php if ($links): ?>
                <ul class="link-list">
                    <?php foreach ($links as [$label, $href, $style]): ?>
                        <li><a class="button<?= $style === 'primary' ? ' button-primary' : '' ?>" href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer"><?= e($label) ?> <?= partial('partials/icon', ['name' => 'external']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php if ($providers !== null): ?>
                <?= partial('partials/providers', ['providers' => $providers, 'media' => $media]) ?>
            <?php endif; ?>

            <?php if ($shopLinks): ?>
                <h3 class="subsection-title"><?= e(t('media.buy_book')) ?></h3>
                <ul class="link-list">
                    <?php foreach ($shopLinks as $shop): ?>
                        <li><a class="button" href="<?= e($shop['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($shop['nom']) ?> <?= partial('partials/icon', ['name' => 'external']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($media['source'] === 'rawg'): ?>
                <p class="hint"><?= e(t('media.rawg_attribution')) ?> <a href="https://rawg.io/" target="_blank" rel="noopener noreferrer">RAWG</a></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="panel" aria-labelledby="avis-groupe">
        <h2 id="avis-groupe" class="section-title"><?= e(t('media.group_opinions')) ?></h2>
        <?php if (!$opinions): ?>
            <p class="muted"><?= e(t('media.no_opinions')) ?></p>
        <?php else: ?>
            <ul class="opinions">
                <?php foreach ($opinions as $op): ?>
                    <li>
                        <?= partial('partials/avatar', ['name' => $op['display_name'], 'color' => $op['avatar_color']]) ?>
                        <a href="<?= e(url('membre', ['id' => $op['id']])) ?>"><?= e($op['display_name']) ?></a>
                        <span class="pill pill-<?= e($op['status']) ?>"><?= e(Types::statusLabel($op['status'], $type)) ?></span>
                        <?php if ($op['status'] === 'vu' && $op['rating'] !== null): ?>
                            <?= partial('partials/stars', ['value' => $op['rating']]) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?= partial('partials/comments', ['media' => $media, 'comments' => $comments]) ?>

    <?php if ($canEdit): ?>
        <section class="panel panel-quiet">
            <h2 class="section-title"><?= e(t('media.manage')) ?></h2>
            <div class="button-row">
                <a class="button" href="<?= e(url('media/modifier', ['id' => $media['id']])) ?>"><?= partial('partials/icon', ['name' => 'edit']) ?> <?= e(t('media.edit')) ?></a>
                <?php if ($media['source'] !== 'manuel'): ?>
                    <form method="post" action="<?= e(url('media/resynchroniser')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $media['id'] ?>">
                        <button type="submit" class="button"><?= e(t('media.resync')) ?></button>
                    </form>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <a class="button" href="<?= e(url('media/fusionner', ['id' => $media['id']])) ?>"><?= e(t('merge.button')) ?></a>
                <?php endif; ?>
                <a class="button button-danger" href="<?= e(url('media/supprimer', ['id' => $media['id']])) ?>"><?= partial('partials/icon', ['name' => 'trash']) ?> <?= e(t('media.delete')) ?></a>
            </div>
        </section>
    <?php endif; ?>
</article>
