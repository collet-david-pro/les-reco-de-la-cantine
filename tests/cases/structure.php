<?php
/** Tests de structure : routes, droits, textes de l'interface, gardes des fichiers. */
declare(strict_types=1);

return [
    'routes : droits cohérents' => function (): void {
        $routes = require CANTINE_APP . '/routes.php';
        foreach ($routes as $key => [$class, $method, $access]) {
            [$verb, $route] = explode(' ', $key, 2);
            check(in_array($access, ['public', 'auth', 'member', 'admin'], true), "$key : accès inconnu");
            check(method_exists($class, $method), "$key : $class::$method absent");
            if (str_starts_with($route, 'admin') || $route === 'media/fusionner') {
                same('admin', $access, "$key doit être réservé à l'admin");
            }
            if ($verb === 'POST' && $route !== 'connexion') {
                check($access !== 'public', "$key : action POST publique");
            }
        }
    },

    'textes : toutes les clés utilisées existent' => function (): void {
        $texts = require CANTINE_APP . '/lang/fr.php';
        $missing = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CANTINE_APP));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all("/\b(?:t|t_plural|Lang::get|Lang::plural)\('([a-z_]+\.[a-z_0-9.]*[a-z0-9])'/", (string) file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $key) {
                if (!isset($texts[$key])) {
                    $missing[] = $key . ' (' . basename($file->getPathname()) . ')';
                }
            }
        }
        // Clés construites dynamiquement.
        foreach (['status.a_decouvrir', 'status.en_cours', 'status.vu', 'status.pas_pour_moi', 'error.title_404', 'error.title_403',
            'error.title_other', 'diagnostics.status_ok', 'diagnostics.status_warn', 'diagnostics.status_fail',
            'list.sort_recent', 'list.sort_note', 'list.sort_populaire', 'list.sort_titre'] as $key) {
            if (!isset($texts[$key])) {
                $missing[] = $key;
            }
        }
        foreach (Cantine\Repositories\MediaRepository::TABS as $tab) {
            if (!isset($texts['list.empty_' . $tab])) {
                $missing[] = 'list.empty_' . $tab;
            }
        }
        same([], array_values(array_unique($missing)), 'clés manquantes');
    },

    'fichiers PHP internes protégés par la garde CANTINE' => function (): void {
        $unprotected = [];
        foreach ([CANTINE_APP, CANTINE_ROOT . '/migrations'] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
            foreach ($files as $file) {
                if ($file->getExtension() === 'php' && !str_contains((string) file_get_contents($file->getPathname()), "defined('CANTINE') || exit")) {
                    $unprotected[] = substr($file->getPathname(), strlen(CANTINE_ROOT) + 1);
                }
            }
        }
        same([], $unprotected);
    },

    'registre des types complet' => function (): void {
        foreach (Cantine\Types::ALL as $code => $def) {
            foreach (['label', 'plural', 'seen', 'icon', 'sources', 'extra'] as $field) {
                check(array_key_exists($field, $def), "$code : $field manquant");
            }
            check(is_file(CANTINE_ROOT . '/assets/img/placeholder-' . $code . '.svg'), "image de remplacement $code");
        }
    },
];
