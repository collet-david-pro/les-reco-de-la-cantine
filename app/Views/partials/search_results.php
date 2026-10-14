<?php defined('CANTINE') || exit;
/**
 * Résultats de recherche : d'abord les contenus déjà sur le site (alerte de doublon précoce), puis ceux de l'API.
 * Choisir un résultat envoie uniquement (type, source, id externe) : le serveur relit lui-même la fiche.
 * Paramètres : $type, $query, $results (null si pas de recherche).
 */
if ($results === null) {
    return;
}
?>
<?php if ($results['local']): ?>
    <section class="results-block results-local">
        <h2 class="subsection-title"><?= e(t('add.already_here')) ?></h2>
        <ul class="result-list">
            <?php foreach ($results['local'] as $m): ?>
                <li class="result">
                    <?= partial('partials/cover', ['media' => $m, 'size' => 'thumb']) ?>
                    <div class="result-text">
                        <a class="result-title" href="<?= e(url('media', ['id' => $m['id']])) ?>"><?= e($m['title']) ?></a>
                        <span class="muted"><?= e(t('add.already_by', ['name' => $m['added_by_name'] ?? t('user.former'), 'date' => date_short($m['created_at'])])) ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<?php if ($results['error']): ?>
    <p class="alert alert-info"><?= e($results['error']) ?> <?= e(t('add.manual_possible')) ?></p>
<?php elseif (!$results['remote']): ?>
    <p class="muted"><?= e(t('add.no_results', ['q' => $query])) ?></p>
<?php else: ?>
    <section class="results-block">
        <h2 class="subsection-title"><?= e(t('add.results')) ?></h2>
        <ul class="result-list">
            <?php foreach ($results['remote'] as $r): ?>
                <li class="result">
                    <?= partial('partials/cover', ['media' => ['type' => $type, 'title' => $r['title'], 'image_url' => $r['image_url']], 'size' => 'thumb']) ?>
                    <div class="result-text">
                        <span class="result-title"><?= e($r['title']) ?><?= $r['year'] ? ' <span class="muted">(' . e($r['year']) . ')</span>' : '' ?></span>
                        <?php if (!empty($r['info'])): ?><span class="muted result-info"><?= e($r['info']) ?></span><?php endif; ?>
                        <?php if (!empty($r['existing'])): ?>
                            <span class="badge"><?= e(t('add.already_short')) ?></span>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="<?= e(url('ajouter/choisir')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="type" value="<?= e($type) ?>">
                        <input type="hidden" name="source" value="<?= e($r['source']) ?>">
                        <input type="hidden" name="id" value="<?= e($r['external_id']) ?>">
                        <button type="submit" class="button button-small<?= empty($r['existing']) ? ' button-primary' : '' ?>"><?= e(empty($r['existing']) ? t('add.choose') : t('add.see')) ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
