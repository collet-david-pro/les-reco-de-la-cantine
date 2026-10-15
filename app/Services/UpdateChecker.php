<?php
/**
 * Notification de nouvelle version : compare la version installée à la dernière release publiée sur GitHub.
 *
 * - L'appel à GitHub a lieu au plus une fois par 24 h, à l'ouverture du tableau de bord admin (ou sur demande).
 * - Le résultat (y compris un échec) est gardé dans les réglages : les autres pages ne font aucun appel réseau.
 * - Aucune mise à jour automatique : on indique seulement où trouver la nouvelle version et son tutoriel.
 */
declare(strict_types=1);

namespace Cantine\Services;

defined('CANTINE') || exit;

use Cantine\Support\Db;
use Cantine\Support\Logger;
use Cantine\Support\Settings;

final class UpdateChecker
{
    public const INTERVAL = 86400; // 24 h

    /** Réglages propres à l'instance (exclus des sauvegardes). */
    public const SETTINGS = ['update_latest', 'update_checked_at', 'update_error'];

    public function __construct(
        private Settings $settings,
        private HttpClient $http,
        private string $repoUrl = CANTINE_REPO_URL,
        private string $currentVersion = CANTINE_VERSION,
    ) {
    }

    /** "propriétaire/dépôt" à partir de l'adresse GitHub du projet, ou null si elle n'est pas reconnue. */
    public static function repoPath(string $url): ?string
    {
        return preg_match('~^https://github\.com/([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?/?$~', $url, $m) ? $m[1] . '/' . $m[2] : null;
    }

    /** "v1.2.3" ou "1.2.3" -> "1.2.3" ; null si ce n'est pas un numéro de version. */
    public static function normalizeVersion(mixed $tag): ?string
    {
        return is_string($tag) && preg_match('/^v?(\d{1,4}\.\d{1,4}\.\d{1,6})$/', trim($tag), $m) ? $m[1] : null;
    }

    /** Vérifie seulement si la dernière vérification date de plus de 24 h. */
    public function checkIfDue(): void
    {
        $last = $this->settings->get('update_checked_at');
        if ($last === '' || strtotime($last . ' UTC') < time() - self::INTERVAL) {
            $this->check();
        }
    }

    /** Interroge GitHub et enregistre le résultat. Renvoie vrai si l'appel a réussi. */
    public function check(): bool
    {
        $this->settings->set('update_checked_at', Db::now());
        $repo = self::repoPath($this->repoUrl);
        if ($repo === null) {
            $this->settings->set('update_error', t('updates.bad_repo'));
            return false;
        }
        try {
            $data = $this->http->getJson('https://api.github.com/repos/' . $repo . '/releases/latest', [
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
            ], 0);
            $release = self::parseRelease($data, $repo);
            if ($release === null) {
                throw new ApiException(t('updates.unreadable'));
            }
        } catch (ApiException $e) {
            Logger::warning('Vérification des mises à jour impossible : ' . $e->getMessage());
            $this->settings->set('update_error', $e->getMessage());
            return false;
        }
        $this->settings->set('update_latest', (string) json_encode($release, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->settings->set('update_error', '');
        return true;
    }

    /**
     * Lit la réponse de l'API GitHub en ne gardant que des valeurs sûres.
     * @return array{version: string, url: string, zip_url: ?string, published_at: ?string, notes: string}|null
     */
    public static function parseRelease(array $data, string $repo): ?array
    {
        $version = self::normalizeVersion($data['tag_name'] ?? null);
        if ($version === null || !empty($data['draft']) || !empty($data['prerelease'])) {
            return null;
        }
        $base = 'https://github.com/' . $repo . '/';
        $url = is_string($data['html_url'] ?? null) && str_starts_with($data['html_url'], $base) ? $data['html_url'] : $base . 'releases';

        // Archive prête à téléverser : cantine-vX.Y.Z.zip parmi les fichiers joints.
        $zip = null;
        foreach (is_array($data['assets'] ?? null) ? $data['assets'] : [] as $asset) {
            $name = is_array($asset) ? ($asset['name'] ?? '') : '';
            $link = is_array($asset) ? ($asset['browser_download_url'] ?? '') : '';
            if (is_string($name) && preg_match('/^cantine-v[\d.]+\.zip$/', $name) && is_string($link) && str_starts_with($link, $base . 'releases/download/')) {
                $zip = $link;
                break;
            }
        }

        $published = is_string($data['published_at'] ?? null) && preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})Z$/', $data['published_at'], $m)
            ? $m[1] . ' ' . $m[2] : null;

        return [
            'version' => $version,
            'url' => $url,
            'zip_url' => $zip,
            'published_at' => $published,
            'notes' => mb_substr(is_string($data['body'] ?? null) ? $data['body'] : '', 0, 6000),
        ];
    }

    /** Dernière release connue si elle est plus récente que la version installée, sinon null. */
    public function available(): ?array
    {
        $latest = $this->settings->json('update_latest');
        $version = self::normalizeVersion($latest['version'] ?? null);
        if ($version === null || !version_compare($version, $this->currentVersion, '>')) {
            return null;
        }
        return $latest;
    }

    /** État pour le tableau de bord. */
    public function status(): array
    {
        return [
            'current' => $this->currentVersion,
            'available' => $this->available(),
            'checked_at' => $this->settings->get('update_checked_at') ?: null,
            'error' => $this->settings->get('update_error') ?: null,
        ];
    }

    /**
     * Notes de version (Markdown) -> texte brut lisible : sans titres ni gras, liens réduits à leur texte.
     * Le résultat doit ensuite être échappé à l'affichage.
     */
    public static function plainNotes(string $markdown, int $max = 1500): string
    {
        $text = str_replace("\r\n", "\n", $markdown);
        $text = (string) preg_replace('/^#{1,6}\s*/m', '', $text);
        $text = (string) preg_replace('/\*\*(.+?)\*\*/s', '$1', $text);
        $text = (string) preg_replace('/`([^`]*)`/', '$1', $text);
        $text = (string) preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        $text = (string) preg_replace('/^\s*[-*]\s+/m', '• ', $text);
        $text = trim((string) preg_replace("/\n{3,}/", "\n\n", $text));
        return mb_strlen($text) > $max ? rtrim(mb_substr($text, 0, $max - 1)) . '…' : $text;
    }
}
