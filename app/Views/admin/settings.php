<?php defined('CANTINE') || exit; ?>
<div class="page-head"><h1><?= e(t('settings.title')) ?></h1></div>
<?= partial('admin/nav') ?>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/reglages')) ?>" class="form">
    <?= csrf_field() ?>
    <section class="panel">
        <h2 class="section-title"><?= e(t('settings.general')) ?></h2>
        <div class="field">
            <label for="site_name"><?= e(t('settings.site_name')) ?></label>
            <input type="text" id="site_name" name="site_name" value="<?= e($siteName) ?>" maxlength="60" placeholder="Les reco de la cantine">
        </div>
    </section>

    <section class="panel">
        <h2 class="section-title"><?= e(t('settings.api_keys')) ?></h2>
        <p class="hint"><?= e(t('settings.keys_hint')) ?></p>
        <?php foreach (['tmdb_key' => ['TMDB', $tmdbMasked, t('settings.tmdb_help')], 'rawg_key' => ['RAWG', $rawgMasked, t('settings.rawg_help')]] as $name => [$label, $masked, $help]): ?>
            <div class="field">
                <label for="<?= e($name) ?>"><?= e(t('settings.key_label', ['api' => $label])) ?></label>
                <input type="password" id="<?= e($name) ?>" name="<?= e($name) ?>" value="" autocomplete="off" placeholder="<?= e($masked !== '' ? $masked : t('settings.no_key')) ?>">
                <p class="hint"><?= e($help) ?> <?= e($masked !== '' ? t('settings.key_keep') : '') ?></p>
                <?php if ($masked !== ''): ?>
                    <div class="field-check">
                        <input type="checkbox" id="remove_<?= e($name) ?>" name="remove_<?= e($name) ?>" value="1">
                        <label for="remove_<?= e($name) ?>"><?= e(t('settings.key_remove')) ?></label>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="field field-short">
            <label for="watch_region"><?= e(t('settings.region')) ?></label>
            <input type="text" id="watch_region" name="watch_region" value="<?= e($region) ?>" maxlength="2" pattern="[A-Za-z]{2}">
            <p class="hint"><?= e(t('settings.region_hint')) ?></p>
        </div>
    </section>

    <section class="panel">
        <h2 class="section-title"><?= e(t('settings.shops')) ?></h2>
        <div class="field">
            <label for="shop_links"><?= e(t('settings.shops_label')) ?></label>
            <textarea id="shop_links" name="shop_links" rows="5" spellcheck="false" class="mono"><?= e($shopText) ?></textarea>
            <p class="hint"><?= e(t('settings.shops_hint')) ?></p>
        </div>
    </section>

    <button type="submit" class="button button-primary button-large"><?= e(t('common.save')) ?></button>
</form>

<section class="panel">
    <h2 class="section-title"><?= e(t('settings.test_keys')) ?></h2>
    <div class="button-row">
        <?php foreach (['tmdb' => [$tmdbMasked, 'TMDB'], 'rawg' => [$rawgMasked, 'RAWG']] as $which => [$masked, $label]): ?>
            <form method="post" action="<?= e(url('admin/reglages/tester')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="which" value="<?= e($which) ?>">
                <button type="submit" class="button"<?= $masked === '' ? ' disabled' : '' ?>><?= e(t('settings.test', ['api' => $label])) ?></button>
            </form>
        <?php endforeach; ?>
    </div>
</section>
