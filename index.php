<?php
/**
 * Les reco de la cantine — contrôleur frontal unique.
 * Toutes les pages passent par ce fichier : index.php?route=...
 */
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    header('Content-Type: text/html; charset=utf-8', true, 500);
    echo 'Ce site nécessite PHP 8.1 ou plus récent (version actuelle : ' . htmlspecialchars(PHP_VERSION) . ').';
    exit;
}

define('CANTINE', true);

require __DIR__ . '/app/bootstrap.php';

Cantine\App::run();
