<?php defined('CANTINE') || exit;
/** Icônes SVG en ligne (traits). Paramètre : $name. */
$paths = [
    'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
    'list' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 12h8M8 15h5"/>',
    'plus' => '<path d="M12 5v14M5 12h14"/>',
    'dice' => '<rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="9" cy="9" r="1.2" fill="currentColor"/><circle cx="15" cy="15" r="1.2" fill="currentColor"/><circle cx="15" cy="9" r="1.2" fill="currentColor"/><circle cx="9" cy="15" r="1.2" fill="currentColor"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
    'search' => '<circle cx="11" cy="11" r="6"/><path d="m20 20-4.5-4.5"/>',
    'edit' => '<path d="M4 20h4L19 9l-4-4L4 16v4Z"/>',
    'trash' => '<path d="M5 7h14M10 7V4h4v3M7 7l1 13h8l1-13"/>',
    'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/>',
    'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9L12 3Z"/>',
];
$svg = $paths[$name] ?? '';
?>
<svg class="icon" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><?= $svg ?></svg>
