<?php
// PATH: ai0-0010/tools/purge/purge-view.php
declare(strict_types=1);

/**
 * @param array{scan: array<string, mixed>, flash: string|null, scopeRel: string, restoreUrl: string} $vm
 */
function purge_render_view(array $vm): void
{
    $scan = $vm['scan'];
    $flash = $vm['flash'];
    $scopeLabel = $vm['scopeRel'] === '' ? 'Entire project' : $vm['scopeRel'];
    $discard = $scan['discard'];
    $backup = $scan['backup'];
    $disabled = $scan['disabled'];
    $osJunk = $scan['os_junk'] ?? [];
    $osJunkCount = count($osJunk);
    $preselectedCount = count($discard) + count($backup);
    $totalQueued = $preselectedCount + $osJunkCount;
    $restoreUrl = htmlspecialchars($vm['restoreUrl'], ENT_QUOTES, 'UTF-8');
    ?>
    <?php if ($flash !== null): ?>
        <div class="j0ui-alert-inline j0ui-alert-success"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" data-j0-confirm="Permanently delete selected items?">
        <input type="hidden" name="action" value="purge_selected">
        <div class="j0ui-tree-toolbar">
            <div>
                <strong>Space Reclamation</strong>
                <span class="j0ui-badge"><?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="j0ui-actions">
                <a class="j0ui-btn" href="<?= $restoreUrl ?>">Restore _📦 batches</a>
            </div>
        </div>

        <section class="j0ui-grid-2">
            <article class="j0ui-card">
                <h2>Preselected: Discarded (<code class="j0ui-mono">_🗑️</code> / <code class="j0ui-mono">__</code>) &amp; Backups (<code class="j0ui-mono">_📦</code>) (<?= $preselectedCount ?>)</h2>
                <p class="j0ui-muted">Marked by default for safe cleanup.</p>
                <?php if ($preselectedCount === 0): ?>
                    <p class="j0ui-muted">Nothing queued for reclamation.</p>
                <?php else: ?>
                    <ul class="j0ui-cat-list">
                        <?php foreach ($discard as $entry): ?>
                            <li class="j0ui-cat-item">
                                <label class="j0ui-cat-main j0ui-mono">
                                    <input type="checkbox" name="items[]" value="<?= htmlspecialchars($entry['rel'], ENT_QUOTES, 'UTF-8') ?>" checked>
                                    <span><?= htmlspecialchars($entry['rel'], ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                                <span class="j0ui-badge j0ui-badge-warning"><?= htmlspecialchars($entry['marker'], ENT_QUOTES, 'UTF-8') ?></span>
                            </li>
                        <?php endforeach; ?>
                        <?php foreach ($backup as $entry): ?>
                            <li class="j0ui-cat-item">
                                <label class="j0ui-cat-main j0ui-mono">
                                    <input type="checkbox" name="items[]" value="<?= htmlspecialchars($entry['rel'], ENT_QUOTES, 'UTF-8') ?>" checked>
                                    <span><?= htmlspecialchars($entry['rel'], ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                                <span class="j0ui-badge"><?= htmlspecialchars($entry['marker'], ENT_QUOTES, 'UTF-8') ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>

            <article class="j0ui-card">
                <h2>Protected: Disabled / Private (<code class="j0ui-mono">_</code>) (<?= count($disabled) ?>)</h2>
                <p class="j0ui-muted">Human-disabled modules or notes. Unchecked by default.</p>
                <?php if ($disabled === []): ?>
                    <p class="j0ui-muted">No single-underscore items found.</p>
                <?php else: ?>
                    <ul class="j0ui-cat-list">
                        <?php foreach ($disabled as $entry): ?>
                            <li class="j0ui-cat-item">
                                <label class="j0ui-cat-main j0ui-mono">
                                    <input type="checkbox" name="items[]" value="<?= htmlspecialchars($entry['rel'], ENT_QUOTES, 'UTF-8') ?>">
                                    <span><?= htmlspecialchars($entry['rel'], ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                                <span class="j0ui-badge">_</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        </section>

        <article class="j0ui-card">
            <h2>Preselected: OS Junk (<code class="j0ui-mono">.DS_Store</code> / <code class="j0ui-mono">Thumbs.db</code> / <code class="j0ui-mono">._*</code>) (<?= $osJunkCount ?>)</h2>
            <p class="j0ui-muted">Operating system metadata files safe to purge.</p>
            <?php if ($osJunk === []): ?>
                <p class="j0ui-muted">No OS junk files detected.</p>
            <?php else: ?>
                <ul class="j0ui-cat-list">
                    <?php foreach ($osJunk as $entry): ?>
                        <li class="j0ui-cat-item">
                            <label class="j0ui-cat-main j0ui-mono">
                                <input type="checkbox" name="items[]" value="<?= htmlspecialchars($entry['rel'], ENT_QUOTES, 'UTF-8') ?>" checked>
                                <span><?= htmlspecialchars($entry['rel'], ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                            <span class="j0ui-badge"><?= htmlspecialchars($entry['marker'], ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <?php if ($totalQueued > 0 || $disabled !== []): ?>
            <div class="j0ui-actions">
                <button type="submit" class="j0ui-btn j0ui-btn--danger">Delete selected items</button>
            </div>
        <?php endif; ?>
    </form>
    <?php
}