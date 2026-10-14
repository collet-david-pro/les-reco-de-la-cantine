<?php
/** Tests des fonctions de texte, YouTube et liens boutiques (sans base). */
declare(strict_types=1);

use Cantine\Services\Api\YouTube;
use Cantine\Services\ShopLinks;
use Cantine\Support\Text;

return [
    'normalisation des titres' => function (): void {
        same('ete meurtrier', Text::normalize("L'Été meurtrier !"));
        same('miserables', Text::normalize('  Les   Misérables  '));
        same('matrix', Text::normalize('The Matrix'));
        same('le', Text::normalize('Le'), 'un article seul est gardé');
        same('cafe creme 2', Text::normalize('Café-Crème 2'));
        same('oeuvre complete', Text::normalize('Œuvre complète'));
        same('', Text::normalize('!!!'));
    },

    'initiales des avatars' => function (): void {
        same('JD', Text::initials('Jean-Paul Dupont'));
        same('CH', Text::initials('Chloé'));
        same('ÉM', Text::initials('élodie martin'));
        same('?', Text::initials('  '));
    },

    'dates relatives' => function (): void {
        $now = strtotime('2026-10-14 12:00:00 UTC');
        same('à l\'instant', Text::relativeDate('2026-10-14 11:59:30', $now));
        same('il y a 5 min', Text::relativeDate('2026-10-14 11:55:00', $now));
        same('il y a 3 h', Text::relativeDate('2026-10-14 09:00:00', $now));
        same('hier', Text::relativeDate('2026-10-13 10:00:00', $now));
        same('il y a 4 jours', Text::relativeDate('2026-10-10 12:00:00', $now));
        same('le 01/10/2026', Text::relativeDate('2026-10-01 10:00:00', $now));
    },

    'commentaires : échappement, liens, sauts de ligne' => function (): void {
        $html = Text::commentHtml("<script>alert(1)</script>\nVoir https://exemple.org/page?a=1&b=2.");
        check(!str_contains($html, '<script>'), 'le HTML doit être échappé');
        check(str_contains($html, '&lt;script&gt;'));
        check(str_contains($html, '<br>'), 'saut de ligne conservé');
        check(str_contains($html, 'href="https://exemple.org/page?a=1&amp;b=2"'), 'lien cliquable sans le point final');
        check(str_contains($html, 'rel="noopener noreferrer nofollow"'));
        $evil = Text::commentHtml('javascript:alert(1) "onmouseover=x');
        check(!str_contains($evil, '<a '), 'pas de lien javascript:');
    },

    'mot de passe lisible généré' => function (): void {
        $password = Text::readablePassword();
        check((bool) preg_match('/^[a-z]+-[a-z]+-[a-z]+-\d{2}$/', $password), $password);
        check(strlen($password) >= 12);
    },

    'YouTube : extraction de l\'identifiant' => function (): void {
        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtube.com/watch?v=dQw4w9WgXcQ&t=42',
            'https://youtu.be/dQw4w9WgXcQ?si=abc',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://music.youtube.com/watch?v=dQw4w9WgXcQ&list=x',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'youtu.be/dQw4w9WgXcQ',
            'dQw4w9WgXcQ',
        ] as $url) {
            same('dQw4w9WgXcQ', YouTube::extractId($url), $url);
        }
        foreach (['https://evil.example/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/watch?v=short', 'http://127.0.0.1/', ''] as $url) {
            same(null, YouTube::extractId($url), $url);
        }
        same('https://www.youtube.com/watch?v=dQw4w9WgXcQ', YouTube::canonicalUrl('dQw4w9WgXcQ'));
    },

    'liens boutiques' => function (): void {
        $templates = [
            ['nom' => 'ISBN seul', 'url' => 'https://a.example/s?k={isbn}'],
            ['nom' => 'Titre', 'url' => 'https://b.example/s?q={titre}'],
        ];
        $links = ShopLinks::build($templates, '9782070360024', 'L\'Étranger', 'Albert Camus');
        same('https://a.example/s?k=9782070360024', $links[0]['url']);
        same('https://b.example/s?q=L%27%C3%89tranger%20Albert%20Camus', $links[1]['url']);
        $noIsbn = ShopLinks::build($templates, null, 'Titre', null);
        same('https://a.example/s?k=Titre', $noIsbn[0]['url'], 'sans ISBN : repli sur le titre');

        [$ok, $errors] = ShopLinks::parse("Amazon | https://www.amazon.fr/s?k={isbn}\n\nMauvais | http://x.fr/{isbn}\nSans marqueur | https://x.fr/\nformat incorrect");
        same(1, count($ok));
        same(3, count($errors));
    },
];
