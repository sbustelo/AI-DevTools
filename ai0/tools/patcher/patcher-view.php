<?php
// PATH: ai0/tools/patcher/patcher-view.php
declare(strict_types=1);

/**
 * @param array{parseErrors: array<int, string>, execResult: array<string, mixed>|null, flash: string|null, restoreUrl: string} $vm
 */
function patcher_render_view(array $vm): void
{
    $parseErrors = $vm['parseErrors'];
    $execResult = $vm['execResult'];
    $flash = $vm['flash'];
    $commands = (string) ($vm['commands'] ?? '');
    $projectRoot = (string) ($vm['projectRoot'] ?? '');
    $safeProjectRoot = htmlspecialchars($projectRoot, ENT_QUOTES, 'UTF-8');
    $rootMismatch = is_array($vm['rootMismatch'] ?? null) ? $vm['rootMismatch'] : null;
    $iconsCfg = is_array($vm['icons'] ?? null) ? $vm['icons'] : [];
    $rootIconHtml = (string) ($vm['rootIconHtml'] ?? '');
    $repIcons = is_array($iconsCfg['report'] ?? null) ? $iconsCfg['report'] : [];
    $getIcon = static function (array $branch, string $fallback): string {
        $val = $branch['icon'] ?? '';
        return (is_string($val) && $val !== '') ? $val : $fallback;
    };

    $outChunks = [];
    if ($flash !== null) {
        $outChunks[] = $flash;
    }
    $icoRoot = $getIcon((array) ($repIcons['root'] ?? []), '📁');
    $icoErrTitle = $getIcon((array) ($repIcons['error']['title'] ?? []), '🚨');
    $icoErrItem = $getIcon((array) ($repIcons['error']['item'] ?? []), '❌');
    $icoWarnTitle = $getIcon((array) ($repIcons['warning']['title'] ?? []), '⚠️');
    $icoWarnItem = $getIcon((array) ($repIcons['warning']['item'] ?? []), '🤨');
    $icoDryTitle = $getIcon((array) ($repIcons['dry_run']['title'] ?? []), '🧪');
    $icoOkTitle = $getIcon((array) ($repIcons['ok']['title'] ?? []), '✅');
    $okItems = is_array($repIcons['ok']['items'] ?? null) ? $repIcons['ok']['items'] : [];

    if ($parseErrors !== []) {
        $errLines = ["{$icoErrTitle} PARSE ERRORS _ai0:"];
        foreach ($parseErrors as $err) {
            $errLines[] = "{$icoErrItem} " . $err;
        }
        $outChunks[] = implode("\n", $errLines);
    }
    if ($execResult !== null) {
        $isDry = !empty($execResult['dry_run']);
        $hasErr = !empty($execResult['errors']);
        $hasWarn = !empty($execResult['warnings']);

        if ($hasErr) {
            $headLine = "{$icoErrTitle} Errors _ai0 (Batch {$execResult['timestamp']})";
        } elseif ($hasWarn) {
            $headLine = "{$icoWarnTitle} Warnings _ai0 (Batch {$execResult['timestamp']})";
        } elseif ($isDry) {
            $headLine = "{$icoDryTitle} DRY-RUN _ai0 (Batch {$execResult['timestamp']})";
        } else {
            $headLine = "{$icoOkTitle} OK _ai0 (Batch {$execResult['timestamp']})";
        }

        $summaryLines = [
            $headLine,
            "{$icoRoot} Root: {$projectRoot}",
            '---',
        ];

        if ($hasErr) {
            foreach ($execResult['errors'] as $e) {
                $summaryLines[] = "{$icoErrItem} {$e['path']}: {$e['reason']}";
            }
            if (!empty($execResult['staged_batch'])) {
                $stagedList = is_array($execResult['staged_files'] ?? null) ? implode(', ', $execResult['staged_files']) : '';
                $summaryLines[] = '⏸️ STAGED: ' . $execResult['staged_batch'] . ($stagedList !== '' ? " ({$stagedList})" : '');
                $summaryLines[] = 'Recover without re-emitting intact hunks using === RESOLVE: ' . $execResult['staged_batch'] . ' === or === RETRY_HUNK: ' . $execResult['staged_batch'] . ' <file> #<n> ===.';
            } else {
                $summaryLines[] = 'Regenerate only the failed blocks with exact START/END anchors.';
            }
        }

        if ($hasWarn) {
            if ($hasErr) {
                $summaryLines[] = '';
                $summaryLines[] = "{$icoWarnTitle} Warnings:";
            }
            foreach ($execResult['warnings'] as $warn) {
                $summaryLines[] = "{$icoWarnItem} " . $warn;
            }
        }

        if (!empty($execResult['applied'])) {
            if ($hasErr || $hasWarn) {
                $summaryLines[] = '';
                $summaryLines[] = $isDry ? "{$icoDryTitle} Planned:" : "{$icoOkTitle} OK:";
            }
            foreach ($execResult['applied'] as $appliedLine) {
                $lineStr = (string) $appliedLine;
                if (str_starts_with($lineStr, 'CREATED:') || str_starts_with($lineStr, 'DRY-RUN CREATE:')) {
                    $itemKey = 'created';
                    $fallback = '👍🏻';
                } elseif (str_starts_with($lineStr, 'UPDATED:') || str_starts_with($lineStr, 'DRY-RUN UPDATE:')) {
                    $itemKey = 'updated';
                    $fallback = '👌🏻';
                } elseif (str_starts_with($lineStr, 'MOVED:') || str_starts_with($lineStr, 'DRY-RUN MOVE:')) {
                    $itemKey = 'moved';
                    $fallback = '📦';
                } elseif (str_starts_with($lineStr, 'DISABLED:') || str_starts_with($lineStr, 'DRY-RUN DISABLE:')) {
                    $itemKey = 'disabled';
                    $fallback = '🫥';
                } elseif (str_starts_with($lineStr, 'DISCARDED:') || str_starts_with($lineStr, 'DRY-RUN DISCARD:')) {
                    $itemKey = 'discarded';
                    $fallback = '🗑️';
                } elseif (str_starts_with($lineStr, 'DUMP') || str_starts_with($lineStr, 'TREE')) {
                    $itemKey = 'inspect';
                    $fallback = '📄';
                } else {
                    $itemKey = 'default';
                    $fallback = '✅';
                }
                $itemIco = $getIcon((array) ($okItems[$itemKey] ?? []), $fallback);
                $summaryLines[] = "{$itemIco} " . $lineStr;
            }
        }

        $outChunks[] = implode("\n", $summaryLines);
        $readOut = trim((string) ($execResult['read_output'] ?? ''));
        if ($readOut !== '') {
            $outChunks[] = $readOut;
        }
    }
    $unifiedOutput = implode("\n\n", $outChunks);
    $hasErrors = ($parseErrors !== []) || (!empty($execResult['errors']));
    $hasWriteApplied = ($execResult !== null) && empty($execResult['dry_run']) && ($execResult['applied'] !== []) && empty($execResult['read_output']);
    ?>
    <div class="j0ui-grid-2">
        <j0-pane class="j0ui-card j0ui-pane-card">
            <form method="post" class="j0ui-pane-form">
                <j0-region role="head">
                    <div class="j0ui-tree-toolbar j0ui-tree-toolbar--root">
                        <div class="j0ui-tree-toolbar-title">
                            <h2><?= htmlspecialchars(L('Input — ai0 Commands', 'Entrada — Comandos ai0'), ENT_QUOTES, 'UTF-8') ?></h2>
                            <?= $rootIconHtml ?>
                            <span class="j0ui-badge j0ui-mono" title="<?= $safeProjectRoot ?>"><?= htmlspecialchars(basename($projectRoot) ?: '/', ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                    <p class="j0ui-muted"><?= htmlspecialchars(M('patcher.help.summary', 'Supports CREATE, PATCH, MOVE, DISABLE (_), DISCARD (_🗑️), DUMP, and TREE.'), ENT_QUOTES, 'UTF-8') ?></p>
                </j0-region>
                <j0-region role="body">
                    <textarea name="commands" class="j0ui-textarea j0ui-textarea--fill" placeholder="<?= htmlspecialchars(M('patcher.placeholder', 'Paste the ai0 commands generated by the AI here...'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($commands, ENT_QUOTES, 'UTF-8') ?></textarea>
                </j0-region>
                <j0-region role="foot">
                    <div class="j0ui-actions">
                        <button type="submit" name="action" value="apply" class="j0ui-btn j0ui-btn--primary"><?= htmlspecialchars(L('Run / Apply', 'Ejecutar / Aplicar'), ENT_QUOTES, 'UTF-8') ?></button>
                        <button type="submit" name="action" value="dry_run" class="j0ui-btn"><?= htmlspecialchars(L('Dry-Run Preview', 'Vista previa (Dry-Run)'), ENT_QUOTES, 'UTF-8') ?></button>
                    </div>
                </j0-region>
            </form>
        </j0-pane>

        <j0-pane class="j0ui-card j0ui-pane-card">
            <j0-region role="head">
                <div class="j0ui-tree-toolbar">
                    <div class="j0ui-tree-toolbar-title">
                        <h2><?= htmlspecialchars(L('Output — Report & Mini-Dump', 'Salida — Reporte y Mini-Dump'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <?php if ($execResult !== null): ?>
                            <?php if (!empty($execResult['dry_run'])): ?>
                                <span class="j0ui-badge j0ui-badge-accent">DRY-RUN</span>
                            <?php elseif ($hasErrors): ?>
                                <span class="j0ui-badge j0ui-badge-warning">ERROR</span>
                            <?php else: ?>
                                <span class="j0ui-badge">OK</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="j0ui-muted"><?= htmlspecialchars(L('Copy this output right back into the AI chat.', 'Copiá esta salida directo al chat de la IA.'), ENT_QUOTES, 'UTF-8') ?></p>
            </j0-region>
            <j0-region role="body">
                <textarea class="j0ui-textarea j0ui-textarea--fill" readonly data-j0-copy-source="patcher-out" placeholder="<?= htmlspecialchars(L('Execution report, error trace, or requested mini-dump will appear here...', 'El reporte, error o mini-dump solicitado aparecerá acá...'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($unifiedOutput, ENT_QUOTES, 'UTF-8') ?></textarea>
            </j0-region>
            <j0-region role="foot">
                <div class="j0ui-actions">
                    <button type="button" class="j0ui-btn j0ui-btn--primary" data-j0-action="copy-target" data-j0-target="patcher-out"><?= htmlspecialchars(L('Copy Output for AI', 'Copiar salida para IA'), ENT_QUOTES, 'UTF-8') ?></button>
                    <?php if ($hasWriteApplied): ?>
                        <?php $rollbackBtnClass = $hasErrors ? 'j0ui-btn j0ui-btn--danger' : 'j0ui-btn j0ui-btn--ghost-danger'; ?>
                        <form method="post" class="j0ui-inline-form j0ui-push-end">
                            <input type="hidden" name="action" value="rollback_batch">
                            <input type="hidden" name="timestamp" value="<?= htmlspecialchars((string) $execResult['timestamp'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="<?= $rollbackBtnClass ?>"><?= htmlspecialchars(L('Rollback batch', 'Revertir lote'), ENT_QUOTES, 'UTF-8') ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            </j0-region>
        </j0-pane>
    </div>

    <?php if ($rootMismatch !== null): ?>
        <?php
        $misCurrent = htmlspecialchars((string) ($rootMismatch['current'] ?? ''), ENT_QUOTES, 'UTF-8');
        $misDeclared = htmlspecialchars((string) ($rootMismatch['declared'] ?? ''), ENT_QUOTES, 'UTF-8');
        $misAction = htmlspecialchars((string) ($rootMismatch['action'] ?? 'apply'), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="j0ui-modal-backdrop" role="dialog" aria-modal="true" aria-label="<?= htmlspecialchars(L('Project Root Mismatch', 'Diferencia de Project Root'), ENT_QUOTES, 'UTF-8') ?>">
            <div class="j0ui-modal">
                <div class="j0ui-modal-head">
                    <h3><?= htmlspecialchars(L('Project Root Mismatch', 'Diferencia de Project Root'), ENT_QUOTES, 'UTF-8') ?></h3>
                </div>
                <p><?= htmlspecialchars(L('The active Project Root differs from the ROOT path declared in these ai0 commands:', 'El Project Root activo difiere de la ruta ROOT declarada en estos comandos ai0:'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="j0ui-subbox j0ui-mono">
                    <div><strong><?= htmlspecialchars(L('Current Project Root:', 'Project Root actual:'), ENT_QUOTES, 'UTF-8') ?></strong> <?= $misCurrent ?></div>
                    <div><strong><?= htmlspecialchars(L('Declared ROOT:', 'ROOT declarado:'), ENT_QUOTES, 'UTF-8') ?></strong> <?= $misDeclared ?></div>
                </div>
                <p class="j0ui-muted"><?= htmlspecialchars(L('Switch the active Project Root to the declared path and run the commands?', '¿Cambiar el Project Root activo a la ruta declarada y ejecutar los comandos?'), ENT_QUOTES, 'UTF-8') ?></p>
                <form method="post" class="j0ui-modal-foot">
                    <textarea name="commands" data-j0-hidden="true"><?= htmlspecialchars($commands, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <input type="hidden" name="confirm_root_switch" value="1">
                    <a class="j0ui-btn" href="index.php"><?= htmlspecialchars(L('Cancel', 'Cancelar'), ENT_QUOTES, 'UTF-8') ?></a>
                    <button type="submit" name="action" value="<?= $misAction ?>" class="j0ui-btn j0ui-btn--primary"><?= htmlspecialchars(L('Switch Project Root & Apply', 'Cambiar Project Root y Aplicar'), ENT_QUOTES, 'UTF-8') ?></button>
                </form>
            </div>
        </div>
    <?php endif; ?>
    <?php
}