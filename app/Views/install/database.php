<?php defined('CANTINE') || exit;
$isMysql = $values['driver'] === 'mysql';
?>
<section class="panel">
    <h1>Base de données</h1>
    <p>Choisissez où ranger les données du site.</p>
    <?php if ($error): ?><p class="alert alert-error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($results !== null): ?>
        <?= partial('partials/checks', ['checks' => $results]) ?>
    <?php endif; ?>
    <form method="post" action="install.php?etape=2" class="form">
        <?= csrf_field() ?>
        <fieldset class="field">
            <legend>Moteur</legend>
            <div class="field-check">
                <input type="radio" id="drv-mysql" name="driver" value="mysql"<?= $isMysql ? ' checked' : '' ?><?= $hasMysql ? '' : ' disabled' ?>>
                <label for="drv-mysql"><strong>MySQL / MariaDB</strong> — conseillé en ligne (proposé par presque tous les hébergeurs)</label>
            </div>
            <div class="field-check">
                <input type="radio" id="drv-sqlite" name="driver" value="sqlite"<?= !$isMysql ? ' checked' : '' ?><?= $hasSqlite ? '' : ' disabled' ?>>
                <label for="drv-sqlite"><strong>SQLite</strong> — aucune configuration : un simple fichier dans data/ (idéal pour tester)</label>
            </div>
        </fieldset>
        <fieldset class="mysql-fields" data-show-when="mysql">
            <legend>Connexion MySQL / MariaDB</legend>
            <p class="hint">Ces informations sont fournies par votre hébergeur. La base doit déjà exister.</p>
            <div class="field">
                <label for="host">Hôte du serveur</label>
                <input type="text" id="host" name="host" value="<?= e($values['host']) ?>" placeholder="localhost">
            </div>
            <div class="field field-short">
                <label for="port">Port <span class="muted">(facultatif)</span></label>
                <input type="text" id="port" name="port" value="<?= e($values['port']) ?>" inputmode="numeric" placeholder="3306">
            </div>
            <div class="field">
                <label for="name">Nom de la base</label>
                <input type="text" id="name" name="name" value="<?= e($values['name']) ?>">
            </div>
            <div class="field">
                <label for="user">Utilisateur</label>
                <input type="text" id="user" name="user" value="<?= e($values['user']) ?>" autocomplete="off">
            </div>
            <div class="field">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" value="<?= e($values['password']) ?>" autocomplete="off">
            </div>
        </fieldset>
        <div class="field">
            <label for="prefix">Préfixe des tables <span class="muted">(facultatif)</span></label>
            <input type="text" id="prefix" name="prefix" value="<?= e($values['prefix']) ?>" placeholder="cantine_" maxlength="20">
            <p class="hint">Utile si la base est partagée avec d'autres applications. Exemple : cantine_</p>
        </div>
        <div class="button-row">
            <button type="submit" name="action" value="test" class="button">Tester la connexion</button>
            <button type="submit" name="action" value="continue" class="button button-primary">Continuer</button>
        </div>
    </form>
</section>
