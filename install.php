<?php
/*
 * Les reco de la cantine — installeur.
 *
 * Ce début de fichier est volontairement écrit en syntaxe PHP ancienne : sur un serveur trop vieux,
 * on affiche un message clair au lieu d'une page blanche.
 */
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Installation impossible</title>';
    echo '<h1>Installation impossible</h1><p>Ce site nécessite PHP 8.1 ou plus récent. Version actuelle : '
        . htmlspecialchars(PHP_VERSION) . '.</p><p>Demandez à votre hébergeur de passer à une version plus récente de PHP.</p>';
    exit;
}

define('CANTINE', true);
define('CANTINE_INSTALLER', true);

require __DIR__ . '/app/bootstrap.php';

Cantine\Installer::run();
