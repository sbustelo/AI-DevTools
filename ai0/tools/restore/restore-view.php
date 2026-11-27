<?php
// PATH: ai0/tools/restore/restore-view.php
declare(strict_types=1);

/**
 * Renders single-column _📦 backup batches for standalone restore and embedded explorer tab.
 *
 * @param array<string, mixed> $vm
 */
function restore_render_view(array $vm): void
{
    $scan = is_array($vm['scan'] ?? null) ? $vm['scan'] : ['batches' => [], 'total_files' => 0];
    $flash = isset($vm['flash']) && is_string($vm['flash']) ? $vm['flash'] : null;
    $scopeRel = (string) ($vm['scopeRel'] ?? '');
    $scopeLabel = $scopeRel === '' ? L('Entire project', 'Proyecto completo') : $scopeRel;
    $batches = is_array($scan['batches'] ?? null) ? $scan['batches'] : [];
    $purgeUrl = htmlspecialchars((string) ($vm['purgeUrl'] ?? ''), ENT_QUOTES, 'UTF-8');
    $activeRoot = htmlspecialchars((string) ($vm['activeRoot'] ?? ''), ENT_QUOTES, 'UTF-8');
    $embedded = !empty($vm['embedded']);
    $explicitScan = !empty($vm['explicitScan']);
    ?>
    <?php if ($flash !== null): ?>
        <div class="j0ui-alert-inline j0ui-alert-success"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <j0-pane class="<?= $embedded ? '' : 'j0ui-card j0ui-pane-card' ?>">
        <j0-region role="head">
        <div class="j0ui-tree-toolbar">
            <div>
                <strong><?= htmlspecialchars(L('Backup Batches', 'Lotes de Respaldo'), ENT_QUOTES, 'UTF-8') ?> <code class="j0ui-mono">_📦</code> (<?= count($batches) ?>)</strong>
                <span class="j0ui-badge"><?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="j0ui-muted"><?= (int) ($scan['total_files'] ?? 0) ?> <?= htmlspecialchars(L('backup files', 'archivos de respaldo'), ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <?php if (!$embedded && $purgeUrl !== ''): ?>
                <div class="j0ui-push-end">
                    <a class="j0ui-btn" href="<?= $purgeUrl ?>"><?= htmlspecialchars(L('Clean up in purge', 'Limpiar en purge'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
            <?php endif; ?>
        </div>
        </j0-region>

        <j0-region role="body" class="j0ui-scroll-area">
        <?php if ($batches === []): ?>
            <p class="j0ui-muted"><?= htmlspecialchars(L('No timestamped _📦 backup batches found.', 'No se encontraron lotes de respaldo _📦.'), ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
            <div class="j0ui-stack">
                <?php foreach ($batches as $ts => $entries): ?>
                    <div class="j0ui-restore-batch">
                        <div class="j0ui-restore-head">
                            <div>
                                <strong class="j0ui-mono"><?= htmlspecialchars((string) $ts, ENT_QUOTES, 'UTF-8') ?></strong>
                                <span class="j0ui-badge"><?= count($entries) ?> <?= htmlspecialchars(L('files', 'archivos'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <form method="post" class="j0ui-inline-form" data-j0-confirm="<?= htmlspecialchars(L('Restore entire batch {0}?', '¿Restaurar el lote completo {0}?', [(string) $ts]), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="restore_batch">
                                <input type="hidden" name="active_tab" value="restore">
                                <input type="hidden" name="browse_root" value="<?= $activeRoot ?>">
                                <input type="hidden" name="scan" value="<?= $explicitScan ? '1' : '0' ?>">
                                <input type="hidden" name="timestamp" value="<?= htmlspecialchars((string) $ts, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="j0ui-btn j0ui-btn--primary"><?= htmlspecialchars(L('Restore Batch', 'Restaurar Lote'), ENT_QUOTES, 'UTF-8') ?></button>
                            </form>
                        </div>
                        <ul class="j0ui-restore-files j0ui-mono">
                            <?php foreach ($entries as $entry): ?>
                                <li class="j0ui-restore-row">
                                    <span>
                                        <?= htmlspecialchars((string) $entry['orig_rel'], ENT_QUOTES, 'UTF-8') ?>
                                        <span class="j0ui-muted">(<?= htmlspecialchars(basename((string) $entry['backup_rel']), ENT_QUOTES, 'UTF-8') ?>)</span>
                                    </span>
                                    <form method="post" class="j0ui-inline-form">
                                        <input type="hidden" name="action" value="restore_single">
                                        <input type="hidden" name="active_tab" value="restore">
                                        <input type="hidden" name="browse_root" value="<?= $activeRoot ?>">
                                        <input type="hidden" name="scan" value="<?= $explicitScan ? '1' : '0' ?>">
                                        <input type="hidden" name="backup_rel" value="<?= htmlspecialchars((string) $entry['backup_rel'], ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="j0ui-btn"><?= htmlspecialchars(L('Restore file', 'Restaurar archivo'), ENT_QUOTES, 'UTF-8') ?></button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </j0-region>
    </j0-pane>
    <?php
}