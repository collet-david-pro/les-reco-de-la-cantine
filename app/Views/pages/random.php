<?php defined('CANTINE') || exit;
/** Tirage au sort "Que regarder ce soir ?" parmi mes contenus "À découvrir". */
use Cantine\Types;

$filters = array_filter(['type' => $type, 'genre' => $genre]);
?>
<div class="page-head">
    <h1><?= e(t('random.title')) ?></h1>
</div>

<form method="get" action="<?= e(url()) ?>" class="panel form random-form">
    <input type="hidden" name="route" value="hasard">
    <input type="hidden" name="tirer" value="1">
    <fieldset class="type-filter">
        <legend><?= e(t('random.what')) ?></legend>
        <div class="chips-input">
            <input type="radio" id="rt-all" name="type" value=""<?= $type === null ? ' checked' : '' ?>>
            <label for="rt-all"><?= e(t('random.anything')) ?></label>
            <?php foreach (Types::ALL as $code => $def): ?>
                <input type="radio" id="rt-<?= e($code) ?>" name="type" value="<?= e($code) ?>"<?= $type === $code ? ' checked' : '' ?>>
                <label for="rt-<?= e($code) ?>"><span aria-hidden="true"><?= $def['icon'] ?></span> <?= e($def['label']) ?></label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <?php if ($genres): ?>
        <div class="field">
            <label for="r-genre"><?= e(t('list.filter_genre')) ?></label>
            <select id="r-genre" name="genre">
                <option value=""><?= e(t('list.all_genres')) ?></option>
                <?php foreach ($genres as $g): ?>
                    <option value="<?= (int) $g['id'] ?>"<?= $genre === (int) $g['id'] ? ' selected' : '' ?>><?= e($g['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
    <button type="submit" class="button button-primary button-large"><?= partial('partials/icon', ['name' => 'dice']) ?> <?= e(t('random.draw')) ?></button>
</form>

<?php if ($draw): ?>
    <?php if ($media === null): ?>
        <section class="panel empty">
            <p class="empty-icon" aria-hidden="true">🤷</p>
            <p><?= e(t('random.nothing')) ?></p>
        </section>
    <?php else: ?>
        <section class="panel random-result" aria-live="polite">
            <a href="<?= e(url('media', ['id' => $media['id']])) ?>" class="random-cover"><?= partial('partials/cover', ['media' => $media, 'size' => 'large']) ?></a>
            <div class="random-text">
                <p class="media-kicker"><span aria-hidden="true"><?= Types::icon($media['type']) ?></span> <?= e(Types::label($media['type'])) ?><?= $media['year'] ? ' · ' . e($media['year']) : '' ?></p>
                <h2 class="random-title"><a href="<?= e(url('media', ['id' => $media['id']])) ?>"><?= e($media['title']) ?></a></h2>
                <p class="muted"><?= e(t('random.by', ['name' => $media['added_by_name'] ?? t('user.former')])) ?></p>
                <div class="button-row">
                    <form method="post" action="<?= e(url('hasard/commencer')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="media_id" value="<?= (int) $media['id'] ?>">
                        <button type="submit" class="button button-primary"><?= e(t('random.start')) ?></button>
                    </form>
                    <a class="button" href="<?= e(url('hasard', ['tirer' => 1, 'sauf' => $media['id']] + $filters)) ?>"><?= e(t('random.another')) ?></a>
                </div>
            </div>
        </section>
    <?php endif; ?>
<?php endif; ?>
