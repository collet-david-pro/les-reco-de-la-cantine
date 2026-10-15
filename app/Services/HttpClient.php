<?php
/**
 * Client HTTP unique utilisé par tous les connecteurs d'API.
 *
 *  - HTTPS uniquement, vers une liste blanche d'hôtes (protection SSRF) ;
 *  - pas de suivi automatique des redirections ;
 *  - délais courts (3 s pour se connecter, 8 s au total) ;
 *  - réponses mises en cache en base (table api_cache).
 * Les clés d'API ne doivent jamais apparaître dans les journaux ni dans les messages d'erreur.
 */
declare(strict_types=1);

namespace Cantine\Services;

defined('CANTINE') || exit;

use Cantine\Support\Db;
use Cantine\Support\Logger;

class HttpClient
{
    public const ALLOWED_HOSTS = [
        'api.themoviedb.org',
        'openlibrary.org',
        'api.rawg.io',
        'api.deezer.com',
        'www.youtube.com',
        'api.github.com', // notification de nouvelle version
        'store.steampowered.com',
        'api.igdb.com',
        'id.twitch.tv', // jeton d'accès IGDB
    ];

    /** Hôtes d'images dont on peut vérifier l'existence (requête HEAD). */
    public const IMAGE_HOSTS = ['shared.akamai.steamstatic.com'];

    public const TTL_SEARCH = 86400;     // 24 h
    public const TTL_DETAILS = 604800;   // 7 jours

    /** Vrai pour ignorer le cache en lecture (re-synchronisation) ; la réponse fraîche est tout de même mise en cache. */
    public bool $refresh = false;

    public function __construct(private ?Db $db = null)
    {
    }

    /**
     * GET d'une URL JSON. Renvoie le tableau décodé.
     * @param array<string, string> $headers
     * @throws ApiException
     */
    public function getJson(string $url, array $headers = [], int $ttl = self::TTL_SEARCH): array
    {
        return $this->json($url, $headers, null, $ttl);
    }

    /**
     * POST d'un corps texte (requêtes IGDB) ; réponse JSON décodée.
     * @throws ApiException
     */
    public function postJson(string $url, string $body, array $headers = [], int $ttl = self::TTL_SEARCH): array
    {
        return $this->json($url, $headers, $body, $ttl);
    }

    private function json(string $url, array $headers, ?string $postBody, int $ttl): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array($host, self::ALLOWED_HOSTS, true)) {
            throw new ApiException('Hôte non autorisé : ' . $host);
        }

        $cacheKey = hash('sha256', $url . '|' . implode('|', $headers) . '|' . ($postBody ?? ''));
        if ($ttl > 0 && $this->db !== null && !$this->refresh) {
            $cached = $this->cacheGet($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $body = $this->request($url, $headers, $postBody);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new ApiException('Réponse illisible de ' . $host);
        }

        if ($ttl > 0 && $this->db !== null) {
            $this->cachePut($cacheKey, $body, $ttl);
        }
        return $data;
    }

    /** Vrai si l'image existe (HEAD, hôtes d'images autorisés uniquement). Surchargée dans les tests. */
    public function urlExists(string $url): bool
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !in_array((string) parse_url($url, PHP_URL_HOST), self::IMAGE_HOSTS, true)) {
            return false;
        }
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($curl);
        return (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE) === 200;
    }

    /** Requête réelle via cURL (GET, ou POST si $postBody est fourni). Surchargée dans les tests. */
    protected function request(string $url, array $headers, ?string $postBody = null): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $curl = curl_init($url);
        $httpHeaders = ['Accept: application/json'];
        foreach ($headers as $name => $value) {
            $httpHeaders[] = $name . ': ' . $value;
        }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => $httpHeaders,
            CURLOPT_USERAGENT => 'LesRecoDeLaCantine/' . CANTINE_VERSION . ' (+' . CANTINE_REPO_URL . ')',
            CURLOPT_ENCODING => '',
        ]);
        if ($postBody !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $postBody);
        }
        if (defined('CURLOPT_PROTOCOLS_STR')) {
            curl_setopt($curl, CURLOPT_PROTOCOLS_STR, 'https');
        } elseif (defined('CURLOPT_PROTOCOLS')) {
            curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        }

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);

        if ($body === false || $error !== '') {
            Logger::warning('Appel API échoué', ['host' => $host, 'error' => $error]);
            throw new ApiException(t('api.unreachable'));
        }
        if ($status === 401 || $status === 403) {
            throw new ApiException(t('api.bad_key'), $status);
        }
        if ($status === 404) {
            throw new ApiException(t('api.not_found'), 404);
        }
        if ($status === 429) {
            throw new ApiException(t('api.rate_limited'), 429);
        }
        if ($status < 200 || $status >= 300) {
            Logger::warning('Réponse API inattendue', ['host' => $host, 'status' => $status]);
            throw new ApiException(t('api.unreachable'), $status);
        }
        return (string) $body;
    }

    private function cacheGet(string $key): ?array
    {
        try {
            $row = $this->db->one('SELECT body, expires_at FROM {api_cache} WHERE cache_key = ?', [$key]);
        } catch (\PDOException) {
            return null;
        }
        if (!$row || $row['expires_at'] < Db::now()) {
            return null;
        }
        $data = json_decode((string) $row['body'], true);
        return is_array($data) ? $data : null;
    }

    private function cachePut(string $key, string $body, int $ttl): void
    {
        try {
            $this->db->upsert('api_cache', ['body' => $body, 'expires_at' => gmdate('Y-m-d H:i:s', time() + $ttl)], ['cache_key' => $key]);
            // Purge des entrées expirées environ une fois sur cent.
            if (random_int(1, 100) === 1) {
                $this->db->run('DELETE FROM {api_cache} WHERE expires_at < ?', [Db::now()]);
            }
        } catch (\PDOException $e) {
            Logger::warning('Cache API indisponible : ' . $e->getMessage());
        }
    }
}
