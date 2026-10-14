<?php
/**
 * Réglages : nom du site, clés d'API (affichées masquées), région "où regarder", liens boutiques.
 */
declare(strict_types=1);

namespace Cantine\Controllers\Admin;

defined('CANTINE') || exit;

use Cantine\Controllers\Controller;
use Cantine\Services\Api\Rawg;
use Cantine\Services\Api\Tmdb;
use Cantine\Services\ApiException;
use Cantine\Services\HttpClient;
use Cantine\Services\ShopLinks;
use Cantine\Support\Flash;
use Cantine\Support\Request;
use Cantine\Support\Response;

final class SettingsController extends Controller
{
    public function index(): Response
    {
        return $this->view('admin/settings', [
            'title' => t('settings.title'),
            'siteName' => $this->settings->get('site_name'),
            'tmdbMasked' => self::mask($this->settings->get('tmdb_key')),
            'rawgMasked' => self::mask($this->settings->get('rawg_key')),
            'region' => $this->settings->get('watch_region', 'FR'),
            'shopText' => self::shopText($this->settings->json('shop_links', ShopLinks::DEFAULTS)),
            'errors' => [],
        ]);
    }

    /** Seuls les 4 derniers caractères sont affichés. La clé n'est jamais renvoyée au navigateur. */
    public static function mask(string $key): string
    {
        return $key === '' ? '' : '••••••••' . substr($key, -4);
    }

    private static function shopText(array $links): string
    {
        return implode("\n", array_map(static fn ($l) => $l['nom'] . ' | ' . $l['url'], $links));
    }

    public function save(): Response
    {
        $errors = [];
        $siteName = mb_substr(Request::post('site_name'), 0, 60);
        $this->settings->set('site_name', $siteName);

        // Clés : un champ vide = inchangé ; la case "supprimer" efface la clé.
        foreach (['tmdb_key', 'rawg_key'] as $name) {
            if (Request::postBool('remove_' . $name)) {
                $this->settings->set($name, '');
                continue;
            }
            $value = Request::post($name);
            if ($value !== '') {
                if (strlen($value) > 400 || !preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
                    $errors[] = t('settings.key_invalid');
                } else {
                    $this->settings->set($name, $value);
                }
            }
        }

        $region = strtoupper(Request::post('watch_region', 'FR'));
        if (preg_match('/^[A-Z]{2}$/', $region)) {
            $this->settings->set('watch_region', $region);
        } else {
            $errors[] = t('settings.region_invalid');
        }

        [$links, $shopErrors] = ShopLinks::parse(Request::post('shop_links'));
        if ($shopErrors) {
            $errors = array_merge($errors, $shopErrors);
        } else {
            $this->settings->set('shop_links', (string) json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        if ($errors) {
            return $this->view('admin/settings', [
                'title' => t('settings.title'),
                'siteName' => $siteName,
                'tmdbMasked' => self::mask($this->settings->get('tmdb_key')),
                'rawgMasked' => self::mask($this->settings->get('rawg_key')),
                'region' => $region,
                'shopText' => Request::post('shop_links'),
                'errors' => $errors,
            ], 422);
        }
        Flash::success(t('settings.saved'));
        return $this->redirect('admin/reglages');
    }

    /** Bouton "Tester la clé" : appel réel à l'API, résultat ✔ / ✘. */
    public function testKey(): Response
    {
        $which = Request::post('which');
        $http = new HttpClient($this->db);
        try {
            if ($which === 'tmdb') {
                (new Tmdb($http, $this->settings->get('tmdb_key')))->testKey();
            } elseif ($which === 'rawg') {
                (new Rawg($http, $this->settings->get('rawg_key')))->testKey();
            } else {
                return $this->redirect('admin/reglages');
            }
            Flash::success(t('settings.key_ok', ['api' => strtoupper($which)]));
        } catch (ApiException $e) {
            Flash::error(t('settings.key_failed', ['api' => strtoupper($which), 'error' => $e->getMessage()]));
        }
        return $this->redirect('admin/reglages');
    }
}
