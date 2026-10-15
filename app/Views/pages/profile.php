<?php defined('CANTINE') || exit;
/** Profil d'un membre. */
use Cantine\Types;
?>
<section class="profile-head panel">
    <?= partial('partials/avatar', ['name' => $member['display_name'], 'color' => $member['avatar_color'], 'size' => 'lg']) ?>
    <div>
        <h1><?= e($member['display_name']) ?></h1>
        <p class="muted"><?= e(t('profile.member_since', ['date' => date_short($member['created_at'])])) ?>
            <?php if ((int) $member['is_active'] === 0): ?> · <?= e(t('profile.inactive')) ?><?php endif; ?></p>
    </div>
    <?php if ($isMe): ?>
        <div class="profile-links">
            <a class="button" href="<?= e(url('mon-compte')) ?>"><?= e(t('nav.account')) ?></a>
            <?php if ($isAdmin): ?><a class="button" href="<?= e(url('admin')) ?>"><?= e(t('nav.admin')) ?></a><?php endif; ?>
            <form method="post" action="<?= e(url('deconnexion')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="button button-ghost"><?= e(t('nav.logout')) ?></button>
            </form>
        </div>
    <?php endif; ?>
</section>

<?php if ($isMe): ?><?= partial('partials/install_app') ?><?php endif; ?>

<section class="panel">
    <h2 class="section-title"><?= e(t('profile.numbers')) ?></h2>
    <ul class="stat-grid">
        <li><strong><?= (int) $stats['added_count'] ?></strong><span><?= e(t_plural('profile.recos', $stats['added_count'])) ?></span></li>
        <?php foreach (Types::ALL as $code => $def): ?>
            <?php $n = (int) ($stats['seen_by_type'][$code] ?? 0); ?>
            <?php if ($n > 0): ?>
                <li><strong><?= $n ?></strong><span><span aria-hidden="true"><?= $def['icon'] ?></span> <?= e(mb_strtolower($n > 1 ? $def['plural'] : $def['label'])) ?> · <?= e(mb_strtolower($def['seen'])) ?></span></li>
            <?php endif; ?>
        <?php endforeach; ?>
        <li><strong><?= $stats['avg_rating'] !== null ? e(number_fr($stats['avg_rating'])) : '–' ?></strong><span><?= e(t('profile.avg_given', ['n' => (string) $stats['rating_count']])) ?></span></li>
    </ul>
</section>

<?php if ($ratings): ?>
    <section class="panel">
        <h2 class="section-title"><?= e(t('profile.recent_ratings')) ?></h2>
        <ul class="rating-list">
            <?php foreach ($ratings as $r): ?>
                <li>
                    <a href="<?= e(url('media', ['id' => $r['id']])) ?>"><span aria-hidden="true"><?= Types::icon($r['type']) ?></span> <?= e($r['title']) ?></a>
                    <?= partial('partials/stars', ['value' => $r['rating']]) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<section>
    <h2 class="section-title"><?= e(t('profile.their_recos', ['n' => (string) $total])) ?></h2>
    <?php if (!$items): ?>
        <p class="muted"><?= e(t('profile.no_recos')) ?></p>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($items as $m): ?>
                <?= partial('partials/card', ['m' => $m, 'previousSeenAt' => $currentUser['previous_seen_at'], 'me' => $currentUser['id']]) ?>
            <?php endforeach; ?>
        </div>
        <?= partial('partials/pagination', ['page' => $page, 'pages' => $pages, 'route' => 'membre', 'params' => ['id' => $member['id']]]) ?>
    <?php endif; ?>
</section>
