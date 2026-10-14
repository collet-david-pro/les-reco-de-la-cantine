<?php defined('CANTINE') || exit;
/** Commentaires d'un contenu + formulaire d'ajout. Paramètres : $media, $comments. */
use Cantine\Repositories\CommentRepository;
use Cantine\Support\Text;
?>
<section class="panel" id="commentaires" aria-labelledby="titre-commentaires">
    <h2 id="titre-commentaires" class="section-title"><?= e(t_plural('comment.title', count($comments))) ?></h2>
    <?php if ($comments): ?>
        <ol class="comments">
            <?php foreach ($comments as $c): ?>
                <li class="comment" id="commentaire-<?= (int) $c['id'] ?>">
                    <?= partial('partials/avatar', ['name' => $c['display_name'], 'color' => $c['avatar_color']]) ?>
                    <div class="comment-body">
                        <p class="comment-meta">
                            <a href="<?= e(url('membre', ['id' => $c['user_id']])) ?>"><strong><?= e($c['display_name']) ?></strong></a>
                            <time datetime="<?= e(str_replace(' ', 'T', $c['created_at'])) ?>Z"><?= e(date_relative($c['created_at'])) ?></time>
                            <?php if ($c['updated_at']): ?><span class="muted"><?= e(t('comment.edited')) ?></span><?php endif; ?>
                        </p>
                        <?php if ((int) $c['is_spoiler'] === 1): ?>
                            <details class="spoiler"><summary><?= e(t('comment.spoiler_show')) ?></summary><div class="comment-text"><?= Text::commentHtml($c['body']) ?></div></details>
                        <?php else: ?>
                            <div class="comment-text"><?= Text::commentHtml($c['body']) ?></div>
                        <?php endif; ?>
                        <?php if ($isAdmin || (int) $c['user_id'] === (int) $currentUser['id']): ?>
                            <div class="comment-actions">
                                <a class="link-button" href="<?= e(url('commentaire/modifier', ['id' => $c['id']])) ?>"><?= e(t('common.edit')) ?></a>
                                <form method="post" action="<?= e(url('commentaire/supprimer')) ?>" class="inline-form" data-confirm="<?= e(t('comment.delete_confirm')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="link-button link-danger"><?= e(t('common.delete')) ?></button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php else: ?>
        <p class="muted"><?= e(t('comment.none')) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('commentaire')) ?>" class="form comment-form">
        <?= csrf_field() ?>
        <input type="hidden" name="media_id" value="<?= (int) $media['id'] ?>">
        <div class="field">
            <label for="comment-body"><?= e(t('comment.add_label')) ?></label>
            <textarea id="comment-body" name="body" rows="3" maxlength="<?= CommentRepository::MAX_LENGTH ?>" required data-counter></textarea>
            <p class="hint" data-counter-text><?= e(t('comment.max', ['n' => (string) CommentRepository::MAX_LENGTH])) ?></p>
        </div>
        <div class="field field-check">
            <input type="checkbox" id="comment-spoiler" name="spoiler" value="1">
            <label for="comment-spoiler"><?= e(t('comment.spoiler_label')) ?></label>
        </div>
        <button type="submit" class="button button-primary"><?= e(t('comment.submit')) ?></button>
    </form>
</section>
