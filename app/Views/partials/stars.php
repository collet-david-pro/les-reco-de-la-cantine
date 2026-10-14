<?php defined('CANTINE') || exit;
/** Affichage d'une note (lecture seule). Paramètres : $value (1 à 5, décimal possible), $label facultatif. */
$value = (float) ($value ?? 0);
$rounded = (int) round($value);
?>
<span class="stars" role="img" aria-label="<?= e(t('rating.aria', ['n' => number_fr($value, $value == (int) $value ? 0 : 1)])) ?>">
    <?php for ($i = 1; $i <= 5; $i++): ?><span class="star<?= $i <= $rounded ? ' is-on' : '' ?>" aria-hidden="true">★</span><?php endfor; ?>
</span>
