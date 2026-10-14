<?php defined('CANTINE') || exit;
/** "Ma liste" : onglets de statut, filtres, tri, recherche, grille paginée. */
use Cantine\Types;

$keep = array_filter([
    'vue' => $filters['tab'], 'type' => $filters['type'], 'par' => $filters['added_by'],
    'genre' => $filters['genre'], 'q' => $filters['q'], 'tri' => $sort !== 'recent' ? $sort : '',
]);
$tabs = [
    'a_decouvrir' => t('status.a_decouvrir'),
    'en_cours' => t('status.en_cours'),
    'vu' => t('list.tab_seen'),
    'tout' => t('list.tab_all'),
    'pas_pour_moi' => t('status.pas_pour_moi'),
];
$hasFilters = $filters['type'] || $filters['added_by'] || $filters['genre'] || $filters['q'];
?>
<div class="page-head">
    <h1><?= e(t('list.title')) ?></h1>
</div>

<nav class="tabs" aria-label="<?= e(t('list.tabs_label')) ?>">
    <?php foreach ($tabs as $code => $label): ?>
        <a href="<?= e(url('liste', ['vue' => $code] + array_diff_key($keep, ['vue' => 1]))) ?>" class="tabs-item<?= $filters['tab'] === $code ? ' is-active' : '' ?>"<?= $filters['tab'] === $code ? ' aria-current="page"' : '' ?>>
            <?= e($label) ?> <span class="tabs-count"><?= (int) $tabCounts[$code] ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<form method="get" action="<?= e(url()) ?>" class="filters" role="search">
    <input type="hidden" name="route" value="liste">
    <input type="hidden" name="vue" value="<?= e($filters['tab']) ?>">
    <div class="field field-search">
        <label for="q" class="sr-only"><?= e(t('list.search')) ?></label>
        <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= e(t('list.search_placeholder')) ?>" maxlength="100">
    </div>
    <div class="filters-row">
        <div class="field">
            <label for="f-type"><?= e(t('list.filter_type')) ?></label>
            <select id="f-type" name="type" data-autosubmit>
                <option value=""><?= e(t('list.all_types')) ?></option>
                <?php foreach (Types::ALL as $code => $def): ?>
                    <option value="<?= e($code) ?>"<?= $filters['type'] === $code ? ' selected' : '' ?>><?= e($def['plural']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="f-genre"><?= e(t('list.filter_genre')) ?></label>
            <select id="f-genre" name="genre" data-autosubmit>
                <option value=""><?= e(t('list.all_genres')) ?></option>
                <?php foreach ($genres as $genre): ?>
                    <option value="<?= (int) $genre['id'] ?>"<?= (int) $filters['genre'] === (int) $genre['id'] ? ' selected' : '' ?>><?= e($genre['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="f-par"><?= e(t('list.filter_by')) ?></label>
            <select id="f-par" name="par" data-autosubmit>
                <option value=""><?= e(t('list.everyone')) ?></option>
                <?php foreach ($adders as $adder): ?>
                    <option value="<?= (int) $adder['id'] ?>"<?= (int) $filters['added_by'] === (int) $adder['id'] ? ' selected' : '' ?>><?= e($adder['display_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="f-tri"><?= e(t('list.sort')) ?></label>
            <select id="f-tri" name="tri" data-autosubmit>
                <?php foreach (['recent', 'note', 'populaire', 'titre'] as $code): ?>
                    <option value="<?= e($code) ?>"<?= $sort === $code ? ' selected' : '' ?>><?= e(t('list.sort_' . $code)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="filters-actions">
        <button type="submit" class="button"><?= partial('partials/icon', ['name' => 'search']) ?> <?= e(t('list.apply')) ?></button>
        <?php if ($hasFilters): ?>
            <a class="link-button" href="<?= e(url('liste', ['vue' => $filters['tab']])) ?>"><?= e(t('list.reset')) ?></a>
        <?php endif; ?>
    </div>
</form>

<p class="result-count" aria-live="polite"><?= e(t_plural('list.count', $total)) ?></p>

<?php if (!$items): ?>
    <section class="panel empty">
        <p class="empty-icon" aria-hidden="true"><?= $filters['tab'] === 'a_decouvrir' && !$hasFilters ? '🎉' : '🔍' ?></p>
        <p><?= e($hasFilters ? t('list.empty_filtered') : t('list.empty_' . $filters['tab'])) ?></p>
        <p><a class="button" href="<?= e(url('ajouter')) ?>"><?= e(t('home.add')) ?></a></p>
    </section>
<?php else: ?>
    <div class="grid">
        <?php foreach ($items as $m): ?>
            <?= partial('partials/card', ['m' => $m, 'previousSeenAt' => $previousSeenAt, 'me' => $currentUser['id']]) ?>
        <?php endforeach; ?>
    </div>
    <?= partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'route' => 'liste', 'params' => $keep]) ?>
<?php endif; ?>
