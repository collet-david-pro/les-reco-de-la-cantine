<?php defined('CANTINE') || exit;
/** Jaquette avec image de remplacement locale. Paramètres : $media (type, title, image_url), $size ('card'|'large'|'thumb'). */
$size ??= 'card';
$placeholder = \Cantine\Types::placeholder((string) $media['type']);
$src = !empty($media['image_url']) ? $media['image_url'] : $placeholder;
$dims = ['card' => [200, 300], 'large' => [342, 513], 'thumb' => [60, 90]][$size] ?? [200, 300];
$wide = in_array($media['type'], ['video', 'musique'], true);
?>
<div class="cover cover-<?= e($size) ?><?= $wide ? ' cover-wide cover-' . e($media['type']) : '' ?>">
    <img src="<?= e($src) ?>" data-fallback="<?= e($placeholder) ?>" alt="<?= e(t('media.cover_alt', ['title' => $media['title']])) ?>" loading="lazy" decoding="async" width="<?= $dims[0] ?>" height="<?= $dims[1] ?>" referrerpolicy="no-referrer">
</div>
