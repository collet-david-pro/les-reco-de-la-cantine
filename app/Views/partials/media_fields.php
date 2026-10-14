<?php defined('CANTINE') || exit;
/**
 * Champs d'un contenu (ajout manuel ou modification).
 * Paramètres : $type, $values, $errors, $full (vrai : tous les champs ; faux : contenu venant d'une API).
 */
use Cantine\Types;

$v = static fn (string $key): string => is_scalar($values[$key] ?? null) ? (string) $values[$key] : '';
$extra = is_array($values['extra'] ?? null) ? $values['extra'] : [];
$invalid = static fn (string $field): string => !empty($errors[$field]) ? ' aria-invalid="true" aria-describedby="err-' . $field . '"' : '';
?>
<div class="field">
    <label for="title"><?= e(t('add.field_title')) ?> <span class="required">*</span></label>
    <input type="text" id="title" name="title" value="<?= e($v('title')) ?>" required maxlength="255"<?= $invalid('title') ?>>
    <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'title']) ?>
</div>
<div class="field field-short">
    <label for="year"><?= e(t('add.field_year')) ?></label>
    <input type="text" id="year" name="year" value="<?= e($v('year')) ?>" inputmode="numeric" pattern="\d{4}" maxlength="4"<?= $invalid('year') ?>>
    <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'year']) ?>
</div>
<?php if ($full): ?>
    <?php foreach (Types::get($type)['extra'] as $key => [$label, $format]):
        $value = $extra[$key] ?? '';
        if (is_array($value)) {
            $value = implode(', ', $value);
        }
        $numeric = in_array($format, ['number', 'minutes', 'seconds'], true);
        ?>
        <div class="field<?= $numeric ? ' field-short' : '' ?>">
            <label for="extra-<?= e($key) ?>"><?= e($label) ?><?= $format === 'minutes' ? ' ' . e(t('add.in_minutes')) : ($format === 'seconds' ? ' ' . e(t('add.in_seconds')) : '') ?></label>
            <input type="text" id="extra-<?= e($key) ?>" name="extra[<?= e($key) ?>]" value="<?= e((string) $value) ?>" maxlength="200"<?= $numeric ? ' inputmode="numeric"' : '' ?><?= $invalid('extra_' . $key) ?>>
            <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'extra_' . $key]) ?>
        </div>
    <?php endforeach; ?>
    <?php if ($type === 'livre'): ?>
        <div class="field">
            <label for="isbn"><?= e(t('add.field_isbn')) ?></label>
            <input type="text" id="isbn" name="isbn" value="<?= e($v('isbn')) ?>" maxlength="20" inputmode="numeric"<?= $invalid('isbn') ?>>
            <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'isbn']) ?>
        </div>
    <?php endif; ?>
    <div class="field">
        <label for="genres"><?= e(t('add.field_genres')) ?></label>
        <input type="text" id="genres" name="genres" value="<?= e($v('genres') !== '' ? $v('genres') : $v('genres_text')) ?>" maxlength="300" placeholder="<?= e(t('add.genres_placeholder')) ?>">
        <p class="hint"><?= e(t('add.genres_hint')) ?></p>
    </div>
    <div class="field">
        <label for="external_url"><?= e(t('add.field_link')) ?></label>
        <input type="url" id="external_url" name="external_url" value="<?= e($v('external_url')) ?>" maxlength="500" placeholder="https://"<?= $invalid('external_url') ?>>
        <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'external_url']) ?>
    </div>
    <div class="field">
        <label for="image_url"><?= e(t('add.field_image')) ?></label>
        <input type="url" id="image_url" name="image_url" value="<?= e($v('image_url')) ?>" maxlength="500" placeholder="https://"<?= $invalid('image_url') ?>>
        <p class="hint"><?= e(t('add.image_hint')) ?></p>
        <?= partial('partials/field_error', ['errors' => $errors, 'field' => 'image_url']) ?>
    </div>
<?php endif; ?>
<div class="field">
    <label for="description"><?= e(t('add.field_description')) ?></label>
    <textarea id="description" name="description" rows="4" maxlength="2000"><?= e($v('description')) ?></textarea>
</div>
