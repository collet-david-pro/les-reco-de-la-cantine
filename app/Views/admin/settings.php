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

        <h3 class="subsection-title"><?= e(t('settings.movies_title')) ?></h3>
        <?= partial('admin/key_field', ['name' => 'tmdb_key', 'label' => t('settings.key_label', ['api' => 'TMDB']), 'masked' => $masked['tmdb_key'], 'help' => t('settings.tmdb_help')]) ?>

        <h3 class="subsection-title"><?= e(t('settings.games_title')) ?></h3>
        <p class="callout"><?= e(t('settings.games_source_' . $gameSource)) ?></p>
        <p class="hint"><?= e(t('settings.games_hint')) ?></p>
        <details class="key-help">
            <summary><?= e(t('settings.igdb_how')) ?></summary>
            <ol>
                <li><?= e(t('settings.igdb_step1')) ?></li>
                <li><?= e(t('settings.igdb_step2')) ?></li>
                <li><?= e(t('settings.igdb_step3')) ?></li>
                <li><?= e(t('settings.igdb_step4')) ?></li>
            </ol>
        </details>
        <?= partial('admin/key_field', ['name' => 'igdb_client_id', 'label' => t('settings.igdb_client_id'), 'masked' => $masked['igdb_client_id'], 'help' => '']) ?>
        <?= partial('admin/key_field', ['name' => 'igdb_client_secret', 'label' => t('settings.igdb_client_secret'), 'masked' => $masked['igdb_client_secret'], 'help' => '']) ?>
        <?= partial('admin/key_field', ['name' => 'rawg_key', 'label' => t('settings.key_label', ['api' => 'RAWG']) . ' ' . t('settings.optional'), 'masked' => $masked['rawg_key'], 'help' => t('settings.rawg_help')]) ?>

        <h3 class="subsection-title"><?= e(t('settings.providers_title')) ?></h3>
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
        <?php foreach (['tmdb' => [$masked['tmdb_key'], 'TMDB'], 'igdb' => [$masked['igdb_client_secret'], 'IGDB'], 'rawg' => [$masked['rawg_key'], 'RAWG']] as $which => [$keyMasked, $label]): ?>
            <form method="post" action="<?= e(url('admin/reglages/tester')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="which" value="<?= e($which) ?>">
                <button type="submit" class="button"<?= $keyMasked === '' ? ' disabled' : '' ?>><?= e(t('settings.test', ['api' => $label])) ?></button>
            </form>
        <?php endforeach; ?>
    </div>
</section>
