<?php defined('CANTINE') || exit;
/** Ajout manuel : un contenu semblable existe peut-être déjà. */
$hidden = static function (array $post): string {
    $html = '';
    foreach ($post as $name => $value) {
        if ($name === '_csrf' || $name === 'same_as' || $name === 'confirm_distinct') {
            continue;
        }
        if (is_array($value)) {
            foreach ($value as $key => $v) {
                if (is_string($v)) {
                    $html .= '<input type="hidden" name="' . e($name) . '[' . e((string) $key) . ']" value="' . e($v) . '">';
                }
            }
        } elseif (is_string($value)) {
            $html .= '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
        }
    }
    return $html;
};
?>
<section class="panel panel-narrow">
    <h1><?= e(t('add.duplicate_title')) ?></h1>
    <p><?= e(t('add.duplicate_intro')) ?></p>
    <ul class="result-list">
        <?php foreach ($similar as $m): ?>
            <li class="result">
                <?= partial('partials/cover', ['media' => $m, 'size' => 'thumb']) ?>
                <div class="result-text">
                    <a class="result-title" href="<?= e(url('media', ['id' => $m['id']])) ?>" target="_blank" rel="noopener noreferrer"><?= e($m['title']) ?></a>
                    <span class="muted"><?= $m['year'] ? e($m['year']) . ' · ' : '' ?><?= e(t('add.already_by', ['name' => $m['added_by_name'] ?? t('user.former'), 'date' => date_short($m['created_at'])])) ?></span>
                </div>
                <form method="post" action="<?= e(url('ajouter/manuel')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="type" value="<?= e($type) ?>">
                    <input type="hidden" name="same_as" value="<?= (int) $m['id'] ?>">
                    <button type="submit" class="button button-small button-primary"><?= e(t('add.same')) ?></button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
    <form method="post" action="<?= e(url('ajouter/manuel')) ?>" class="button-row">
        <?= csrf_field() ?>
        <?= $hidden($post) ?>
        <input type="hidden" name="confirm_distinct" value="1">
        <button type="submit" class="button"><?= e(t('add.different')) ?></button>
    </form>
</section>
