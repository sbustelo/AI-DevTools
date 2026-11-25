<?php
// PATH: ai0-0010/tools/dumper/dumper-view.php
declare(strict_types=1);

/**
 * @param array{
 *   scan: array<string, mixed>,
 *   categories: array<string, array<string, mixed>>,
 *   dumpOutput: string|null,
 *   downloadName: string,
 *   compact: bool,
 *   scopeRel: string,
 *   fullProjectUrl: string|null
 * } $vm
 */
function dumper_render_view(array $vm): void
{
    $scan = $vm['scan'];
    $categories = $vm['categories'];
    $dumpOutput = $vm['dumpOutput'];
    $downloadName = htmlspecialchars($vm['downloadName'], ENT_QUOTES, 'UTF-8');
    $compact = $vm['compact'];
    $scopeLabel = $vm['scopeRel'] === '' ? '/' : $vm['scopeRel'];
    ?>
    <?php if ($dumpOutput !== null): ?>
        <section class="j0ui-card">
            <h2>Generated Dump (<?= substr_count($dumpOutput, '// PATH:') ?> files)</h2>
            <textarea class="j0ui-textarea" rows="16" readonly data-j0-copy-source="dumper-out"><?= htmlspecialchars($dumpOutput, ENT_QUOTES, 'UTF-8') ?></textarea>
            <div class="j0ui-actions">
                <button type="button" class="j0ui-btn j0ui-btn--primary" data-j0-action="copy-target" data-j0-target="dumper-out">Copy Dump</button>
                <button type="button" class="j0ui-btn" data-j0-action="download-target" data-j0-target="dumper-out" data-j0-filename="<?= $downloadName ?>">Download .txt</button>
            </div>
        </section>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="generate_dump">
        <div class="j0ui-grid-sidebar" data-j0-tree-host>
            <section class="j0ui-card">
                <div class="j0ui-tree-toolbar">
                    <div class="j0ui-tree-toolbar-title">
                        <label class="j0ui-tree-label">
                            <input type="checkbox" data-j0-tree-check-all>
                            <strong>Project Tree</strong>
                        </label>
                        <span class="j0ui-badge"><?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="j0ui-muted" data-j0-tree-stats><?= (int) $scan['included_files'] ?> / <?= (int) $scan['total_files'] ?> files</span>
                    </div>
                    <div class="j0ui-actions">
                        <button type="button" class="j0ui-btn" data-j0-action="toggle-excluded-only" data-j0-active="false">Show excluded only</button>
                        <button type="button" class="j0ui-btn" data-j0-action="tree-toggle-all" data-j0-collapsed-state="false">Collapse all</button>
                        <?php if ($vm['fullProjectUrl'] !== null): ?>
                            <a class="j0ui-btn" href="<?= htmlspecialchars($vm['fullProjectUrl'], ENT_QUOTES, 'UTF-8') ?>">Expand to root</a>
                        <?php endif; ?>
                    </div>
                </div>

                <ul class="j0ui-tree-root" data-j0-tree-root>
                    <?php dumper_render_tree_nodes($scan['tree']); ?>
                </ul>

                <div class="j0ui-actions">
                    <label class="j0ui-cat-main">
                        <input type="checkbox" name="compact" value="1" <?= $compact ? 'checked' : '' ?>>
                        <span>Compact mode (collapse blank lines)</span>
                    </label>
                    <button type="submit" class="j0ui-btn j0ui-btn--primary">Generate Dump</button>
                </div>
            </section>

            <aside class="j0ui-card">
                <h2>Exclusion Categories</h2>
                <p class="j0ui-muted">Checked = include in dump. Unchecked = exclude.</p>
                <ul class="j0ui-cat-list">
                    <?php foreach ($categories as $catId => $def): ?>
                        <?php
                        $count = (int) ($scan['counts'][$catId] ?? 0);
                        $isIncluded = !((bool) ($def['default_excluded'] ?? true));
                        $hint = (string) ($def['pattern'] ?? ('prefix: ' . ($def['prefix_state'] ?? $catId)));
                        ?>
                        <li class="j0ui-cat-item">
                            <label class="j0ui-cat-main">
                                <input type="checkbox" data-j0-cat-check="<?= htmlspecialchars($catId, ENT_QUOTES, 'UTF-8') ?>" <?= $isIncluded ? 'checked' : '' ?>>
                                <span>
                                    <span class="j0ui-cat-title"><?= htmlspecialchars((string) $def['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="j0ui-cat-pattern"><?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') ?></span>
                                </span>
                            </label>
                            <span class="j0ui-badge" data-j0-cat-count="<?= htmlspecialchars($catId, ENT_QUOTES, 'UTF-8') ?>"><?= $count ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>
        </div>
    </form>
    <?php
}

/**
 * @param array<int, array<string, mixed>> $nodes
 */
function dumper_render_tree_nodes(array $nodes): void
{
    foreach ($nodes as $node) {
        $isDir = (bool) $node['is_dir'];
        $hasChildren = $isDir && $node['children'] !== [];
        $excluded = (bool) $node['excluded'];
        $cat = htmlspecialchars((string) $node['cat'], ENT_QUOTES, 'UTF-8');
        $rel = htmlspecialchars((string) $node['rel'], ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars((string) $node['name'], ENT_QUOTES, 'UTF-8');
        ?>
        <li class="j0ui-tree-node"
            data-j0-tree-node
            data-j0-node-type="<?= $isDir ? 'dir' : 'file' ?>"
            data-j0-cat="<?= $cat ?>"
            data-j0-excluded="<?= $excluded ? 'true' : 'false' ?>"
            data-j0-collapsed="false">
            <div class="j0ui-tree-row">
                <?php if ($hasChildren): ?>
                    <button type="button" class="j0ui-tree-caret" data-j0-tree-toggle>▾</button>
                <?php else: ?>
                    <span class="j0ui-tree-caret-spacer"></span>
                <?php endif; ?>
                <label class="j0ui-tree-label">
                    <input type="checkbox"
                           data-j0-tree-check
                           <?= (!$isDir || $node['heavy']) ? 'name="selected_files[]"' : '' ?>
                           value="<?= $rel ?>"
                           <?= !$excluded ? 'checked' : '' ?>>
                    <span><?= $isDir ? '📁 ' : '📄 ' ?><?= $name ?></span>
                </label>
                <?php if ($cat !== 'none'): ?>
                    <span class="j0ui-badge"><?= $cat ?></span>
                <?php endif; ?>
            </div>
            <?php if ($hasChildren): ?>
                <ul class="j0ui-tree-branch">
                    <?php dumper_render_tree_nodes($node['children']); ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
    }
}