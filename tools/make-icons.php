<?php
/**
 * Génère les icônes PNG de l'application installable (PWA) avec GD.
 * À lancer en développement uniquement : php tools/make-icons.php
 * Dessin : bol blanc fumant avec une étoile, sur fond terracotta (même motif que favicon.svg).
 */
declare(strict_types=1);

$out = dirname(__DIR__) . '/assets/img';

/** Dessine l'icône en grand (suréchantillonnage) puis la réduit pour lisser les bords. */
function icon(int $size, bool $maskable, bool $rounded): GdImage
{
    $scale = 4;
    $s = $size * $scale;
    $img = imagecreatetruecolor($s, $s);
    imagealphablending($img, true);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    $accent = imagecolorallocate($img, 0xC2, 0x41, 0x0C);
    $white = imagecolorallocate($img, 0xFF, 0xFF, 0xFF);
    $steam = imagecolorallocate($img, 0xFD, 0xEB, 0xDD);
    $star = imagecolorallocate($img, 0xD9, 0x77, 0x06);

    // Fond : plein cadre (maskable) ou carré arrondi.
    if ($rounded) {
        $r = (int) ($s * 0.22);
        imagefilledrectangle($img, $r, 0, $s - $r, $s, $accent);
        imagefilledrectangle($img, 0, $r, $s, $s - $r, $accent);
        foreach ([[$r, $r], [$s - $r, $r], [$r, $s - $r], [$s - $r, $s - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, 2 * $r, 2 * $r, $accent);
        }
    } else {
        imagefilledrectangle($img, 0, 0, $s, $s, $accent);
    }

    // Zone de dessin : réduite pour les icônes "maskable" (zone de sécurité de 80 %).
    $pad = $maskable ? 0.2 : 0.0;
    $u = static fn (float $v): int => (int) round(($pad / 2 + $v * (1 - $pad)) * $s / 64);

    // Vapeur : trois traits ondulés (tracés avec des disques pour des bords lisses).
    $dot = $u(3.2) - $u(0);
    foreach ([24, 32, 40] as $x) {
        for ($t = 0; $t <= 120; $t++) {
            $y = 10 + $t * 0.1;
            $px = $x + sin($t / 120 * M_PI * 2) * 2.2;
            imagefilledellipse($img, $u($px), $u($y), $dot, $dot, $steam);
        }
    }

    // Bol : demi-disque + pied.
    imagefilledarc($img, $u(32), $u(30), $u(54) - $u(10), $u(52) - $u(8), 0, 180, $white, IMG_ARC_PIE);
    imagefilledrectangle($img, $u(10), $u(29), $u(54), $u(31), $white);
    imagefilledrectangle($img, $u(23), $u(50), $u(41), $u(55), $white);

    // Étoile.
    $points = [];
    for ($i = 0; $i < 10; $i++) {
        $radius = $i % 2 === 0 ? 8.5 : 3.8;
        $angle = -M_PI / 2 + $i * M_PI / 5;
        $points[] = $u(32 + cos($angle) * $radius);
        $points[] = $u(42 + sin($angle) * $radius);
    }
    imagefilledpolygon($img, $points, $star);

    $final = imagecreatetruecolor($size, $size);
    imagealphablending($final, false);
    imagesavealpha($final, true);
    imagecopyresampled($final, $img, 0, 0, 0, 0, $size, $size, $s, $s);
    return $final;
}

$targets = [
    'icon-192.png' => [192, false, true],
    'icon-512.png' => [512, false, true],
    'icon-maskable-512.png' => [512, true, false],
    'apple-touch-icon.png' => [180, false, false], // iOS arrondit lui-même
];
foreach ($targets as $file => [$size, $maskable, $rounded]) {
    imagepng(icon($size, $maskable, $rounded), $out . '/' . $file, 9);
    echo "$file\n";
}
