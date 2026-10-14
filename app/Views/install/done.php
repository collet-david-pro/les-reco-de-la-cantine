<?php defined('CANTINE') || exit; ?>
<section class="panel">
    <?php if ($written): ?>
        <h1>🎉 Installation terminée</h1>
        <p class="alert alert-success">Les tables sont créées, votre compte administrateur aussi, et la configuration est enregistrée dans <code>data/config.php</code>.</p>
    <?php else: ?>
        <h1>Presque fini : une étape manuelle</h1>
        <p class="alert alert-warning">Les tables et votre compte sont créés, mais le serveur n'a pas pu écrire <code>data/config.php</code>.
            Créez ce fichier vous-même (par FTP) et collez-y exactement le contenu ci-dessous, puis rechargez le site.</p>
        <textarea class="mono" rows="16" readonly data-select-all><?= e($config) ?></textarea>
    <?php endif; ?>
    <h2 class="section-title">Prochaines étapes</h2>
    <ol class="next-steps">
        <li><strong>Supprimez le fichier <code>install.php</code></strong> du serveur (il est verrouillé, mais c'est plus propre).</li>
        <li>Dans <em>Administration › Réglages</em>, saisissez vos clés <strong>TMDB</strong> (films et séries) et <strong>RAWG</strong> (jeux). Livres, musique et YouTube fonctionnent sans clé.</li>
        <li>Dans <em>Administration › Membres</em>, créez les comptes de vos amis.</li>
        <li>Envie de voir à quoi ça ressemble d'abord ? <em>Administration</em> propose de charger des <strong>données de démonstration</strong>, supprimables en un clic.</li>
    </ol>
    <p><a class="button button-primary button-large" href="<?= e($siteUrl) ?>">Aller au site</a></p>
</section>
