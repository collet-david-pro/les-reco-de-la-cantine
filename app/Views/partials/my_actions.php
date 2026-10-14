<?php defined('CANTINE') || exit;
/**
 * Mes actions sur un contenu : statut (4 boutons) et note (1 à 5, seulement si "vu").
 * Fonctionne sans JavaScript (formulaires classiques) ; avec JavaScript, envoi en AJAX.
 * Paramètres : $media, $mine (status, rating).
 */
use Cantine\Controllers\MediaController;

$status = $mine['status'];
$rating = $mine['rating'];
$back = url('media', ['id' => $media['id']]);
?>
<section class="my-actions" aria-labelledby="mes-actions" data-media-actions>
    <h2 id="mes-actions" class="section-title"><?= e(t('media.my_status')) ?></h2>
    <form method="post" action="<?= e(url('media/statut')) ?>" class="status-buttons" data-ajax-status>
        <?= csrf_field() ?>
        <input type="hidden" name="media_id" value="<?= (int) $media['id'] ?>">
        <input type="hidden" name="_back" value="<?= e($back) ?>">
        <?php foreach (MediaController::statusChoices($media['type']) as $code => $label): ?>
            <button type="submit" name="status" value="<?= e($code) ?>" class="status-button status-<?= e($code) ?>" aria-pressed="<?= $code === $status ? 'true' : 'false' ?>"><?= e($label) ?></button>
        <?php endforeach; ?>
    </form>

    <form method="post" action="<?= e(url('media/note')) ?>" class="rating-form" data-ajax-rating<?= $status !== 'vu' ? ' hidden' : '' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="media_id" value="<?= (int) $media['id'] ?>">
        <input type="hidden" name="_back" value="<?= e($back) ?>">
        <fieldset class="stars-input">
            <legend><?= e(t('rating.my_rating')) ?> <span class="muted"><?= e(t('rating.optional')) ?></span></legend>
            <div class="stars-choices">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" name="rating" id="note-<?= $i ?>" value="<?= $i ?>"<?= $rating === $i ? ' checked' : '' ?>>
                    <label for="note-<?= $i ?>" title="<?= e(t_plural('rating.stars', $i)) ?>"><span aria-hidden="true">★</span><span class="sr-only"><?= e(t_plural('rating.stars', $i)) ?></span></label>
                <?php endfor; ?>
            </div>
            <div class="rating-actions">
                <button type="submit" class="button button-small no-js-only"><?= e(t('rating.save')) ?></button>
                <button type="submit" name="rating" value="0" class="link-button" data-clear-rating<?= $rating === null ? ' hidden' : '' ?>><?= e(t('rating.clear')) ?></button>
            </div>
        </fieldset>
    </form>
    <p class="hint" data-rating-hint<?= $status === 'vu' ? ' hidden' : '' ?>><?= e(t('rating.only_when_seen', ['seen' => mb_strtolower(\Cantine\Types::statusLabel('vu', $media['type']))])) ?></p>
    <p class="sr-only" aria-live="polite" data-live></p>
</section>
