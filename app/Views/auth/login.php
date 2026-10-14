<?php defined('CANTINE') || exit; ?>
<section class="panel panel-narrow">
    <h1><?= e(t('auth.title')) ?></h1>
    <?php if (!empty($error)): ?>
        <p class="alert alert-error" role="alert"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('connexion')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="field">
            <label for="username"><?= e(t('auth.username')) ?></label>
            <input type="text" id="username" name="username" value="<?= e($username) ?>" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required autofocus maxlength="100">
        </div>
        <div class="field">
            <label for="password"><?= e(t('auth.password')) ?></label>
            <input type="password" id="password" name="password" autocomplete="current-password" required maxlength="200">
        </div>
        <div class="field field-check">
            <input type="checkbox" id="remember" name="remember" value="1">
            <label for="remember"><?= e(t('auth.remember')) ?></label>
        </div>
        <button type="submit" class="button button-primary button-block"><?= e(t('auth.submit')) ?></button>
    </form>
    <p class="hint"><?= e(t('auth.forgot')) ?></p>
</section>
<p class="bare-footer"><a href="<?= e(url('a-propos')) ?>"><?= e(t('footer.about')) ?></a></p>
