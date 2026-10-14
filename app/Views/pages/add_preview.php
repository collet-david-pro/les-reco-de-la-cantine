<?php defined('CANTINE') || exit;
/** Aperçu de la fiche avant enregistrement. */
use Cantine\Types;

$lines = Types::formatExtra($type, $details['extra'] ?? []);
?>
<div class="page-head">
    <h1><?= e(t('add.preview_title')) ?></h1>
</div>
<section class="panel preview">
    <div class="media-hero">
        <?= partial('partials/cover', ['media' => ['type' => $type, 'title' => $details['title'], 'image_url' => $details['image_url']], 'size' => 'large']) ?>
        <div class="media-hero-text">
            <p class="media-kicker"><span aria-hidden="true"><?= Types::icon($type) ?></span> <?= e(Types::label($type)) ?><?= $details['year'] ? ' · ' . e($details['year']) : '' ?></p>
            <h2 class="media-title"><?= e($details['title']) ?></h2>
            <?php if (!empty($details['genres'])): ?>
                <ul class="chips"><?php foreach ($details['genres'] as $g): ?><li><span class="chip"><?= e($g) ?></span></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?php if ($lines): ?>
                <dl class="facts">
                    <?php foreach ($lines as [$label, $value]): ?><div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!empty($details['description'])): ?>
        <p class="description"><?= nl2br(e(mb_strlen($details['description']) > 600 ? mb_substr($details['description'], 0, 599) . '…' : $details['description']), false) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('ajouter/enregistrer')) ?>" class="button-row">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <input type="hidden" name="source" value="<?= e($details['source']) ?>">
        <input type="hidden" name="id" value="<?= e($details['external_id']) ?>">
        <button type="submit" class="button button-primary button-large"><?= e(t('add.confirm')) ?></button>
        <a class="button button-ghost" href="<?= e(url('ajouter/recherche', ['type' => $type])) ?>"><?= e(t('add.back_to_search')) ?></a>
    </form>
    <p class="hint"><?= e(t('add.auto_seen', ['seen' => mb_strtolower(Types::statusLabel('vu', $type))])) ?></p>
</section>
