<?php defined('CANTINE') || exit;
/**
 * Carte d'un contenu dans une grille.
 * Paramètres : $m (ligne de liste), $previousSeenAt, $me (id du membre courant).
 */
use Cantine\Types;

$isNew = !empty($previousSeenAt) && $m['created_at'] > $previousSeenAt && (int) ($m['added_by'] ?? 0) !== (int) $me;
$status = $m['my_status'] ?? 'a_decouvrir';
?>
<article class="card">
    <a class="card-link" href="<?= e(url('media', ['id' => $m['id']])) ?>">
        <?= partial('partials/cover', ['media' => $m]) ?>
        <span class="card-type" title="<?= e(Types::label($m['type'])) ?>"><span aria-hidden="true"><?= Types::icon($m['type']) ?></span><span class="sr-only"><?= e(Types::label($m['type'])) ?></span></span>
        <?php if ($isNew): ?><span class="badge badge-new"><?= e(t('common.new')) ?></span><?php endif; ?>
        <span class="card-body">
            <span class="card-title"><?= e($m['title']) ?></span>
            <span class="card-meta">
                <?= $m['year'] ? e($m['year']) . ' · ' : '' ?><?= e(Types::label($m['type'])) ?>
            </span>
            <span class="card-stats">
                <?php if ($m['avg_rating'] !== null): ?>
                    <span class="card-avg"><span aria-hidden="true">★</span> <?= e(number_fr((float) $m['avg_rating'])) ?><span class="sr-only"> <?= e(t('rating.out_of_5')) ?></span></span>
                <?php endif; ?>
                <?php if ((int) $m['seen_count'] > 0): ?>
                    <span><?= e(t_plural('media.seen_count', (int) $m['seen_count'])) ?></span>
                <?php endif; ?>
            </span>
            <?php if ($status !== 'a_decouvrir'): ?>
                <span class="pill pill-<?= e($status) ?>"><?= e(Types::statusLabel($status, $m['type'])) ?><?= ($status === 'vu' && $m['my_rating']) ? ' · ' . (int) $m['my_rating'] . '★' : '' ?></span>
            <?php endif; ?>
        </span>
    </a>
</article>
