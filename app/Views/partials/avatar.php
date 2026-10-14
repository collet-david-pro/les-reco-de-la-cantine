<?php defined('CANTINE') || exit;
/** Avatar : initiales sur la couleur choisie. Paramètres : $name, $color, $size ('sm'|'md'|'lg'). */
$size ??= 'sm';
$color = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($color ?? '')) ? $color : '#6B625A';
$label = $name ?? t('user.former');
?>
<span class="avatar avatar-<?= e($size) ?> avatar-c<?= e(substr(strtolower($color), 1)) ?>" aria-hidden="true"><?= e(\Cantine\Support\Text::initials($label)) ?></span>
