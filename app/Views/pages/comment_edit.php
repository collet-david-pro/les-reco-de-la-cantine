<?php defined('CANTINE') || exit;
use Cantine\Repositories\CommentRepository;
?>
<section class="panel">
    <h1><?= e(t('comment.edit_title')) ?></h1>
    <p class="muted"><?= e(t('comment.on', ['title' => $media['title']])) ?></p>
    <form method="post" action="<?= e(url('commentaire/modifier')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $comment['id'] ?>">
        <div class="field">
            <label for="body"><?= e(t('comment.text')) ?></label>
            <textarea id="body" name="body" rows="5" maxlength="<?= CommentRepository::MAX_LENGTH ?>" required data-counter><?= e($comment['body']) ?></textarea>
            <p class="hint" data-counter-text><?= e(t('comment.max', ['n' => (string) CommentRepository::MAX_LENGTH])) ?></p>
        </div>
        <div class="field field-check">
            <input type="checkbox" id="spoiler" name="spoiler" value="1"<?= (int) $comment['is_spoiler'] === 1 ? ' checked' : '' ?>>
            <label for="spoiler"><?= e(t('comment.spoiler_label')) ?></label>
        </div>
        <div class="button-row">
            <button type="submit" class="button button-primary"><?= e(t('common.save')) ?></button>
            <a class="button button-ghost" href="<?= e(url('media', ['id' => $media['id']])) ?>#commentaire-<?= (int) $comment['id'] ?>"><?= e(t('common.cancel')) ?></a>
        </div>
    </form>
</section>
