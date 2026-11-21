<?php
// PATH: ai0-0010/tools/restore/restore-view.php
declare(strict_types=1);

/**
 * @param array{scan: array<string, mixed>, flash: string|null, scopeRel: string, purgeUrl: string} $vm
 */
function restore_render_view(array $vm): void
{
    $scan = $vm['scan'];
    $flash = $vm['flash'];
    $scopeLabel = $vm['scopeRel'] === '' ? 'Entire project' : $vm['scopeRel'];
    $batches = $scan['batches'];
    $purgeUrl = htmlspecialchars($vm['purgeUrl'], ENT_QUOTES, 'UTF-8');
    ?>
    <?php if ($flash !== null): ?>
        <div class="j0ui-alert-inline j0ui-alert-success"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="j0ui-card">
        <div class="j0ui-tree-toolbar">
            <div>
                <strong>Backup Batches <code class="j0ui-mono">_📦</code> (<?= count($batches) ?>)</strong>
                <span class="j0ui-badge"><?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="j0ui-muted"><?= (int) $scan['total_files'] ?> backup files</span>
            </div>
            <div class="j0ui-actions">
                <a class="j0ui-btn" href="<?= $purgeUrl ?>">Clean up in purge</a>
            </div>
        </div>

        <?php if ($batches === []): ?>
            <p class="j0ui-muted">No timestamped <code class="j0ui-mono">_📦</code> backup batches found.</p>
        <?php else: ?>
            <ul class="j0ui-cat-list">
                <?php foreach ($batches as $ts => $entries): ?>
                    <li class="j0ui-cat-item">
                        <div>
                            <strong class="j0ui-mono"><?= htmlspecialchars($ts, ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="j0ui-badge"><?= count($entries) ?> files</span>
                            <ul class="j0ui-mono">
                                <?php foreach ($entries as $entry): ?>
                                    <li>
                                        <?= htmlspecialchars($entry['orig_rel'], ENT_QUOTES, 'UTF-8') ?>
                                        <span class="j0ui-muted">(<?= htmlspecialchars(basename($entry['backup_rel']), ENT_QUOTES, 'UTF-8') ?>)</span>
                                        <form method="post" class="j0ui-inline-form">
                                            <input type="hidden" name="action" value="restore_single">
                                            <input type="hidden" name="backup_rel" value="<?= htmlspecialchars($entry['backup_rel'], ENT_QUOTES, 'UTF-8') ?>">
                                            <button type="submit" class="j0ui-btn">Restore file</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <form method="post" data-j0-confirm="Restore entire batch <?= htmlspecialchars($ts, ENT_QUOTES, 'UTF-8') ?>?">
                            <input type="hidden" name="action" value="restore_batch">
                            <input type="hidden" name="timestamp" value="<?= htmlspecialchars($ts, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="j0ui-btn j0ui-btn--primary">Restore Batch</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php
}