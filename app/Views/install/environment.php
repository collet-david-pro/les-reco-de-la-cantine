<?php defined('CANTINE') || exit; ?>
<section class="panel">
    <h1>Vérification du serveur</h1>
    <p>Voyons si votre hébergement a tout ce qu'il faut. Les points ⚠ n'empêchent pas l'installation.</p>
    <?= partial('partials/checks', ['checks' => $checks]) ?>
    <div class="button-row">
        <a class="button" href="install.php?etape=1">Retester</a>
        <?php if ($ok): ?>
            <a class="button button-primary" href="install.php?etape=2">Continuer</a>
        <?php else: ?>
            <span class="alert alert-error">Corrigez les points ✘ puis cliquez sur « Retester ».</span>
        <?php endif; ?>
    </div>
</section>
