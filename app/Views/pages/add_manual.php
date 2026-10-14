<?php defined('CANTINE') || exit;
use Cantine\Types;
?>
<div class="page-head">
    <h1><span aria-hidden="true"><?= Types::icon($type) ?></span> <?= e(t('add.manual_title')) ?></h1>
    <a class="link-button" href="<?= e(url('ajouter/recherche', ['type' => $type])) ?>"><?= e(t('add.back_to_search')) ?></a>
</div>
<section class="panel">
    <p class="hint"><?= e(t('add.manual_intro', ['type' => mb_strtolower(Types::label($type))])) ?></p>
    <?php if ($errors): ?><p class="alert alert-error" role="alert"><?= e(t('form.has_errors')) ?></p><?php endif; ?>
    <form method="post" action="<?= e(url('ajouter/manuel')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <?= partial('partials/media_fields', ['type' => $type, 'values' => $values, 'errors' => $errors, 'full' => true]) ?>
        <button type="submit" class="button button-primary button-large"><?= e(t('add.confirm')) ?></button>
    </form>
</section>
