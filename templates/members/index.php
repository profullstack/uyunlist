<?php
$title = $title ?? 'Members';
ob_start();
?>

<h1>Members</h1>
<p><?= count($members) ?> member<?= count($members) === 1 ? '' : 's' ?></p>

<div class="members-list" style="display:flex;flex-direction:column;gap:.5rem;margin-top:1rem;">
    <?php foreach ($members as $m): ?>
        <div style="display:flex;align-items:center;gap:.75rem;border:1px solid #444;border-radius:6px;padding:.6rem .8rem;">
            <?php if (!empty($m['avatar_path'])): ?>
                <img src="/<?= htmlspecialchars(ltrim($m['avatar_path'], '/')) ?>" alt=""
                     style="width:40px;height:40px;border-radius:50%;object-fit:cover;flex:0 0 auto;">
            <?php else: ?>
                <span style="width:40px;height:40px;border-radius:50%;background:#333;display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;">🧅</span>
            <?php endif; ?>
            <div style="flex:1 1 auto;min-width:0;">
                <div>
                    <strong><?= htmlspecialchars($m['handle']) ?></strong>
                    <?= $m['is_admin'] ? ' <span title="Administrator">🛡️</span>' : '' ?>
                </div>
                <?php if (!empty($m['about'])): ?>
                    <div style="opacity:.75;font-size:.9em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= htmlspecialchars($m['about']) ?>
                    </div>
                <?php endif; ?>
            </div>
            <div style="text-align:right;flex:0 0 auto;opacity:.8;font-size:.9em;">
                <div><?= (int)$m['listing_count'] ?> listing<?= (int)$m['listing_count'] === 1 ? '' : 's' ?></div>
                <div>joined <?= htmlspecialchars(date('Y-m-d', strtotime((string)$m['created_at']))) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout/base.php';
?>
