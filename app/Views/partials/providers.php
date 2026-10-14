<?php defined('CANTINE') || exit;
/** "Où regarder" (films, séries) : données JustWatch via TMDB. Paramètres : $providers, $media. */
$data = $providers['data'] ?? [];
$groups = [
    'flatrate' => t('providers.flatrate'),
    'free' => t('providers.free'),
];
$folded = [
    'rent' => t('providers.rent'),
    'buy' => t('providers.buy'),
];
$any = !empty($data['flatrate']) || !empty($data['free']) || !empty($data['rent']) || !empty($data['buy']);
$renderList = static function (array $items): string {
    $html = '<ul class="providers">';
    foreach ($items as $p) {
        $html .= '<li class="provider">';
        if (!empty($p['logo'])) {
            $html .= '<img src="' . e($p['logo']) . '" alt="" width="40" height="40" loading="lazy" referrerpolicy="no-referrer">';
        }
        $html .= '<span>' . e($p['name']) . '</span></li>';
    }
    return $html . '</ul>';
};
?>
<div class="where-to-watch">
    <h3 class="subsection-title"><?= e(t('providers.title', ['region' => $providers['region'] ?? 'FR'])) ?></h3>
    <?php if (!$any): ?>
        <p class="muted"><?= e(t('providers.none')) ?></p>
    <?php else: ?>
        <?php foreach ($groups as $key => $label): ?>
            <?php if (!empty($data[$key])): ?>
                <p class="providers-label"><?= e($label) ?></p>
                <?= $renderList($data[$key]) ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php foreach ($folded as $key => $label): ?>
            <?php if (!empty($data[$key])): ?>
                <details class="providers-more">
                    <summary><?= e($label) ?> (<?= count($data[$key]) ?>)</summary>
                    <?= $renderList($data[$key]) ?>
                </details>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <p class="hint">
        <?= e(t('providers.attribution')) ?> <a href="https://www.justwatch.com/" target="_blank" rel="noopener noreferrer">JustWatch</a>
        <?php if (!empty($providers['updated_at'])): ?> · <?= e(t('providers.as_of', ['date' => date_short($providers['updated_at'])])) ?><?php endif; ?>
        <?php if (!empty($data['link'])): ?> · <a href="<?= e($data['link']) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('providers.details')) ?></a><?php endif; ?>
    </p>
</div>
