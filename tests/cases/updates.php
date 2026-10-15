<?php
/** Tests de la notification de nouvelle version (réponse GitHub enregistrée, aucun appel réseau). */
declare(strict_types=1);

use Cantine\Services\ApiException;
use Cantine\Services\HttpClient;
use Cantine\Services\UpdateChecker;
use Cantine\Support\Settings;

/** Client HTTP de test : renvoie une réponse fixe, ou échoue, et compte les appels. */
final class FakeGithub extends HttpClient
{
    public int $calls = 0;

    public function __construct(private ?string $fixture)
    {
        parent::__construct(null);
    }

    protected function request(string $url, array $headers, ?string $postBody = null): string
    {
        $this->calls++;
        if ($this->fixture === null) {
            throw new ApiException('Le service de recherche ne répond pas pour le moment.');
        }
        return (string) file_get_contents(dirname(__DIR__) . '/fixtures/' . $this->fixture);
    }
}

const TEST_REPO = 'https://github.com/collet-david-pro/les-reco-de-la-cantine';

return [
    'mises à jour : lecture des versions et du dépôt' => function (): void {
        same('collet-david-pro/les-reco-de-la-cantine', UpdateChecker::repoPath(TEST_REPO));
        same('a/b', UpdateChecker::repoPath('https://github.com/a/b.git'));
        same(null, UpdateChecker::repoPath('https://gitlab.com/a/b'));
        same('1.2.3', UpdateChecker::normalizeVersion('v1.2.3'));
        same('1.2.3', UpdateChecker::normalizeVersion('1.2.3'));
        same(null, UpdateChecker::normalizeVersion('v1.2'));
        same(null, UpdateChecker::normalizeVersion('latest'));
    },

    'mises à jour : réponse GitHub filtrée' => function (): void {
        $data = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/github-release.json'), true);
        $repo = 'collet-david-pro/les-reco-de-la-cantine';
        $release = UpdateChecker::parseRelease($data, $repo);
        same('9.1.0', $release['version']);
        same('https://github.com/collet-david-pro/les-reco-de-la-cantine/releases/download/v9.1.0/cantine-v9.1.0.zip', $release['zip_url']);
        check($release['published_at'] !== null);

        same(null, UpdateChecker::parseRelease(['tag_name' => 'v2.0.0', 'prerelease' => true] + $data, $repo), 'pré-version ignorée');
        same(null, UpdateChecker::parseRelease(['tag_name' => 'v2.0.0', 'draft' => true] + $data, $repo), 'brouillon ignoré');
        $evil = $data;
        $evil['html_url'] = 'https://evil.example/phishing';
        $evil['assets'][0]['browser_download_url'] = 'https://evil.example/cantine-v9.1.0.zip';
        $parsed = UpdateChecker::parseRelease($evil, $repo);
        same('https://github.com/' . $repo . '/releases', $parsed['url'], 'lien étranger remplacé');
        same(null, $parsed['zip_url'], 'archive étrangère ignorée');
    },

    'db: mises à jour : nouvelle version signalée, au plus une fois par jour' => function (string $driver): void {
        $db = freshDb($driver);
        $settings = new Settings($db);
        $http = new FakeGithub('github-release.json');
        $checker = new UpdateChecker($settings, $http, TEST_REPO, '0.2.0');
        same(null, $checker->available(), 'rien avant la première vérification');
        $checker->checkIfDue();
        same(1, $http->calls);
        same('9.1.0', $checker->available()['version']);
        $checker->checkIfDue();
        same(1, $http->calls, 'pas de nouvel appel dans les 24 h');
        $settings->set('update_checked_at', '2000-01-01 00:00:00');
        $checker->checkIfDue();
        same(2, $http->calls, 'nouvel appel après 24 h');

        same(null, (new UpdateChecker($settings, $http, TEST_REPO, '9.1.0'))->available(), 'déjà à jour');
        same(null, (new UpdateChecker($settings, $http, TEST_REPO, '10.0.0'))->available(), 'version installée plus récente');
    },

    'db: mises à jour : un échec est mémorisé sans casser la page' => function (string $driver): void {
        $db = freshDb($driver);
        $settings = new Settings($db);
        $http = new FakeGithub(null);
        $checker = new UpdateChecker($settings, $http, TEST_REPO, '0.2.0');
        check(!$checker->check());
        check($checker->status()['error'] !== null);
        check($checker->status()['checked_at'] !== null);
        $checker->checkIfDue();
        same(1, $http->calls, 'pas de nouvel essai immédiat après un échec');
        check(!(new UpdateChecker($settings, $http, 'https://exemple.org/x', '0.2.0'))->check(), 'dépôt non reconnu');
    },

    'mises à jour : notes de version en texte brut' => function (): void {
        $text = UpdateChecker::plainNotes("## Titre\n**Gras** et `code`\n- point [lien](https://x.y)\n\n\n\nfin");
        same("Titre\nGras et code\n• point lien\n\nfin", $text);
        check(mb_strlen(UpdateChecker::plainNotes(str_repeat('a', 3000), 100)) === 100);
    },
];
