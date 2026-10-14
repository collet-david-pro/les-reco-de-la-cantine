<?php defined('CANTINE') || exit;
/** Un événement du fil "Quoi de neuf". Paramètre : $event. */
use Cantine\Types;

$name = $event['display_name'] ?? t('user.former');
$who = $event['user_id'] !== null
    ? '<a href="' . e(url('membre', ['id' => $event['user_id']])) . '">' . e($name) . '</a>'
    : e($name);
$what = static fn (array $ev): string => '<a href="' . e(url('media', ['id' => $ev['media_id']])) . '">' . e($ev['title']) . '</a>';
?>
<li class="feed-item feed-<?= e($event['kind']) ?>">
    <?= partial('partials/avatar', ['name' => $event['display_name'], 'color' => $event['avatar_color']]) ?>
    <div class="feed-body">
        <?php if ($event['kind'] === 'media'): ?>
            <p><?= t('feed.recommended', ['who' => $who, 'what' => $what($event), 'type' => e(mb_strtolower(Types::label($event['type'])))]) ?></p>
            <a class="feed-cover" href="<?= e(url('media', ['id' => $event['media_id']])) ?>" tabindex="-1" aria-hidden="true"><?= partial('partials/cover', ['media' => $event, 'size' => 'thumb']) ?></a>
        <?php elseif ($event['kind'] === 'rating'): ?>
            <?php if (count($event['items']) === 1): ?>
                <p><?= t('feed.rated', ['who' => $who, 'what' => $what($event)]) ?> <?= partial('partials/stars', ['value' => $event['rating']]) ?></p>
            <?php else: ?>
                <p><?= t('feed.rated_many', ['who' => $who, 'n' => (string) count($event['items'])]) ?></p>
                <ul class="feed-sublist">
                    <?php foreach ($event['items'] as $item): ?>
                        <li><?= $what($item) ?> <?= partial('partials/stars', ['value' => $item['rating']]) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php else: ?>
            <p><?= t('feed.commented', ['who' => $who, 'what' => $what($event)]) ?></p>
            <?php $excerpt = mb_strlen($event['body']) > 180 ? mb_substr($event['body'], 0, 179) . '…' : $event['body']; ?>
            <?php if ((int) $event['is_spoiler'] === 1): ?>
                <details class="spoiler"><summary><?= e(t('comment.spoiler_show')) ?></summary><blockquote><?= e($excerpt) ?></blockquote></details>
            <?php else: ?>
                <blockquote class="feed-quote"><?= e($excerpt) ?></blockquote>
            <?php endif; ?>
        <?php endif; ?>
        <p class="feed-date"><time datetime="<?= e(str_replace(' ', 'T', $event['at'])) ?>Z"><?= e(date_relative($event['at'])) ?></time></p>
    </div>
</li>
