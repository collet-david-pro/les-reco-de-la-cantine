<?php defined('CANTINE') || exit;
/** Message d'erreur sous un champ. Paramètres : $errors, $field. */
if (empty($errors[$field])) {
    return;
}
?>
<p class="field-error" id="err-<?= e($field) ?>"><?= e($errors[$field]) ?></p>
