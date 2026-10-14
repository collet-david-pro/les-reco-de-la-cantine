<?php defined('CANTINE') || exit;
/** Recherche d'un contenu. Avec JavaScript, les résultats s'affichent pendant la frappe (anti-rebond 400 ms). */
use Cantine\Types;

$isVideo = $type === 'video';
$isMusic = $type === 'musique';
?>
<div class="page-head">
    <h1><span aria-hidden="true"><?= Types::icon($type) ?></span> <?= e(t('add.search_title', ['type' => mb_strtolower(Types::label($type))])) ?></h1>
    <a class="link-button" href="<?= e(url('ajouter')) ?>"><?= e(t('add.change_type')) ?></a>
</div>

<?php if (!$available): ?>
    <p class="alert alert-info"><?= e(t('add.search_unavailable')) ?> <?= e(t('add.manual_possible')) ?></p>
<?php endif; ?>

<form method="get" action="<?= e(url()) ?>" class="search-form" role="search" data-live-search="<?= e(url('api/recherche')) ?>">
    <input type="hidden" name="route" value="ajouter/recherche">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <?php if ($isMusic): ?>
        <fieldset class="segmented">
            <legend class="sr-only"><?= e(t('add.music_kind')) ?></legend>
            <input type="radio" id="kind-album" name="kind" value="album"<?= $kind === 'album' ? ' checked' : '' ?>>
            <label for="kind-album"><?= e(t('add.album')) ?></label>
            <input type="radio" id="kind-track" name="kind" value="track"<?= $kind === 'track' ? ' checked' : '' ?>>
            <label for="kind-track"><?= e(t('add.track')) ?></label>
        </fieldset>
    <?php endif; ?>
    <div class="search-row">
        <label for="search-q" class="sr-only"><?= e($isVideo ? t('add.youtube_label') : t('add.search_label')) ?></label>
        <input type="<?= $isVideo ? 'url' : 'search' ?>" id="search-q" name="q" value="<?= e($query) ?>"
               placeholder="<?= e($isVideo ? t('add.youtube_placeholder') : t('add.search_placeholder')) ?>"
               autocomplete="off" autofocus maxlength="200" <?= $available ? '' : 'disabled' ?>>
        <button type="submit" class="button button-primary"<?= $available ? '' : ' disabled' ?>><?= partial('partials/icon', ['name' => 'search']) ?><span class="sr-only-mobile"> <?= e($isVideo ? t('add.youtube_go') : t('list.search')) ?></span></button>
    </div>
</form>

<div class="search-results" data-search-results aria-live="polite">
    <?= partial('partials/search_results', ['type' => $type, 'query' => $query, 'results' => $results]) ?>
</div>

<p class="manual-link">
    <a class="button button-ghost" href="<?= e(url('ajouter/manuel', ['type' => $type, 'q' => $isVideo ? '' : $query])) ?>" data-manual-link><?= e(t('add.not_found')) ?></a>
</p>
