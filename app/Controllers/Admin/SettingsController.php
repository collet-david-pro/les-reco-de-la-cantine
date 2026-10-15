<?php
/**
 * Réglages : nom du site, clés d'API (affichées masquées), région "où regarder", liens boutiques.
 */
declare(strict_types=1);

namespace Cantine\Controllers\Admin;

defined('CANTINE') || exit;

use Cantine\Controllers\Controller;
use Cantine\Services\Api\Igdb;
use Cantine\Services\Api\Rawg;
use Cantine\Services\Api\Sources;
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
            'masked' => $this->maskedKeys(),
            'gameSource' => $this->gameSource(),
            'region' => $this->settings->get('watch_region', 'FR'),
            'shopText' => self::shopText($this->settings->json('shop_links', ShopLinks::DEFAULTS)),
            'errors' => [],
        ]);
    }

    /** Clés et identifiants d'API modifiables dans les réglages. */
    public const KEYS = ['tmdb_key', 'rawg_key', 'igdb_client_id', 'igdb_client_secret'];

    /** Source actuellement utilisée pour rechercher les jeux (igdb, rawg ou steam). */
    private function gameSource(): string
    {
        return (new Sources($this->settings, new HttpClient($this->db)))->forType('jeu')?->name() ?? 'steam';
    }

    private function maskedKeys(): array
    {
        $masked = [];
        foreach (self::KEYS as $name) {
            $masked[$name] = self::mask($this->settings->get($name));
        }
        return $masked;
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
        foreach (self::KEYS as $name) {
            $before = $this->settings->get($name);
            if (Request::postBool('remove_' . $name)) {
                $this->settings->set($name, '');
            } else {
                $value = Request::post($name);
                if ($value !== '') {
                    if (strlen($value) > 400 || !preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
                        $errors[] = t('settings.key_invalid');
                    } else {
                        $this->settings->set($name, $value);
                    }
                }
            }
            // Identifiants IGDB modifiés : l'ancien jeton d'accès n'est plus valable.
            if (str_starts_with($name, 'igdb_') && $before !== $this->settings->get($name)) {
                $this->settings->set('igdb_token', '');
                $this->settings->set('igdb_token_expires', '0');
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
                'masked' => $this->maskedKeys(),
                'gameSource' => $this->gameSource(),
            'gameSource' => $this->gameSource(),
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
            } elseif ($which === 'igdb') {
                (new Igdb($http, $this->settings, $this->settings->get('igdb_client_id'), $this->settings->get('igdb_client_secret')))->testKey();
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
