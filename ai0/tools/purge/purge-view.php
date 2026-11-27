<?php
// PATH: ai0/tools/purge/purge-view.php
declare(strict_types=1);

/**
 * Renders 3 stacked collapsible purge groups with live (X of Y) counters and muted empty states.
 *
 * @param array<string, mixed> $vm
 */
function purge_render_view(array $vm): void
{
    $scan = is_array($vm['scan'] ?? null) ? $vm['scan'] : ['discard' => [], 'backup' => [], 'disabled' => [], 'os_junk' => []];
    $flash = isset($vm['flash']) && is_string($vm['flash']) ? $vm['flash'] : null;
    $scopeRel = (string) ($vm['scopeRel'] ?? '');
    $scopeLabel = $scopeRel === '' ? L('Entire project', 'Proyecto completo') : $scopeRel;
    $discard = is_array($scan['discard'] ?? null) ? $scan['discard'] : [];
    $backup = is_array($scan['backup'] ?? null) ? $scan['backup'] : [];
    $disabled = is_array($scan['disabled'] ?? null) ? $scan['disabled'] : [];
    $osJunk = is_array($scan['os_junk'] ?? null) ? $scan['os_junk'] : [];

    $reclaimItems = [];
    foreach ($discard as $dItem) {
        $reclaimItems[] = ['rel' => (string) $dItem['rel'], 'marker' => (string) $dItem['marker'], 'warn' => true];
    }
    foreach ($backup as $bItem) {
        $reclaimItems[] = ['rel' => (string) $bItem['rel'], 'marker' => (string) $bItem['marker'], 'warn' => false];
    }

    $reclaimCount = count($reclaimItems);
    $disabledCount = count($disabled);
    $osJunkCount = count($osJunk);
    $initialChecked = $reclaimCount + $osJunkCount;
    $totalAny = $reclaimCount + $disabledCount + $osJunkCount;

    $restoreUrl = htmlspecialchars((string) ($vm['restoreUrl'] ?? ''), ENT_QUOTES, 'UTF-8');
    $activeRoot = htmlspecialchars((string) ($vm['activeRoot'] ?? ''), ENT_QUOTES, 'UTF-8');
    $embedded = !empty($vm['embedded']);
    $explicitScan = !empty($vm['explicitScan']);
    $ofWord = L('of', 'de');

    $groups = [
        [
            'title'       => L('Preselected: Discarded (_🗑️ / __) & Backups (_📦)', 'Preseleccionados: Descartados (_🗑️ / __) y Respaldos (_📦)'),
            'desc'        => L('Mechanical backups and discarded files marked by default for safe cleanup.', 'Respaldos mecánicos y archivos descartados marcados por defecto para limpieza segura.'),
            'empty_msg'   => L('Nothing queued for reclamation.', 'Nada en cola para limpiar.'),
            'items'       => $reclaimItems,
            'preselected' => true,
        ],
        [
            'title'       => L('Protected: Disabled / Private (_)', 'Protegidos: Deshabilitados / Privados (_)'),
            'desc'        => L('Human-disabled modules or private notes. Unchecked by default.', 'Módulos deshabilitados manualmente o notas privadas. Desmarcados por defecto.'),
            'empty_msg'   => L('No single-underscore items found.', 'No se encontraron elementos con guion bajo simple.'),
            'items'       => array_map(static fn(array $e): array => ['rel' => (string) $e['rel'], 'marker' => '_', 'warn' => false], $disabled),
            'preselected' => false,
        ],
        [
            'title'       => L('Preselected: OS Junk (.DS_Store / Thumbs.db / ._*)', 'Preseleccionados: Basura del Sistema (.DS_Store / Thumbs.db / ._*)'),
            'desc'        => L('Operating system metadata files safe to purge.', 'Archivos de metadatos del sistema operativo seguros de eliminar.'),
            'empty_msg'   => L('No OS junk files detected.', 'No se detectó basura del sistema operativo.'),
            'items'       => array_map(static fn(array $e): array => ['rel' => (string) $e['rel'], 'marker' => (string) $e['marker'], 'warn' => false], $osJunk),
            'preselected' => true,
        ],
    ];
    ?>
    <?php if ($flash !== null): ?>
        <div class="j0ui-alert-inline j0ui-alert-success"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" class="j0ui-pane-form" data-j0-purge-form data-j0-confirm="<?= htmlspecialchars(L('Permanently delete selected items?', '¿Eliminar permanentemente los elementos seleccionados?'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="purge_selected">
        <input type="hidden" name="active_tab" value="purge">
        <input type="hidden" name="browse_root" value="<?= $activeRoot ?>">
        <input type="hidden" name="scan" value="<?= $explicitScan ? '1' : '0' ?>">

        <j0-region role="head">
        <div class="j0ui-tree-toolbar">
            <div>
                <strong><?= htmlspecialchars(L('Space Reclamation', 'Limpieza de Espacio'), ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="j0ui-badge"><?= htmlspecialchars($scopeLabel, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="j0ui-push-end">
                <?php if (!$embedded && $restoreUrl !== ''): ?>
                    <a class="j0ui-btn" href="<?= $restoreUrl ?>"><?= htmlspecialchars(L('Restore _📦 batches', 'Restaurar lotes _📦'), ENT_QUOTES, 'UTF-8') ?></a>
                <?php endif; ?>
                <?php if ($totalAny > 0): ?>
                    <button type="submit" class="j0ui-btn j0ui-btn--danger" data-j0-purge-submit <?= $initialChecked === 0 ? 'disabled' : '' ?>>
                        <span><?= htmlspecialchars(L('Delete selected items', 'Eliminar seleccionados'), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="j0ui-nowrap">(<span data-j0-purge-total><?= $initialChecked ?></span>)</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        </j0-region>

        <j0-region role="body" class="j0ui-scroll-area">
        <div class="j0ui-stack">
            <?php foreach ($groups as $grp): ?>
                <?php
                $grpItems = $grp['items'];
                $grpTotal = count($grpItems);
                $grpChecked = $grp['preselected'] ? $grpTotal : 0;
                $isEmpty = ($grpTotal === 0);
                ?>
                <details class="j0ui-card j0ui-purge-group" data-j0-purge-group <?= $isEmpty ? 'data-j0-empty="true"' : '' ?>>
                    <summary class="j0ui-purge-summary">
                        <?= htmlspecialchars($grp['title'], ENT_QUOTES, 'UTF-8') ?>
                        (<span data-j0-purge-counter data-j0-label-of="<?= htmlspecialchars($ofWord, ENT_QUOTES, 'UTF-8') ?>"><?= $grpChecked ?> <?= htmlspecialchars($ofWord, ENT_QUOTES, 'UTF-8') ?> <?= $grpTotal ?></span>)
                    </summary>
                    <div class="j0ui-purge-body">
                        <p class="j0ui-muted"><?= htmlspecialchars($grp['desc'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if ($isEmpty): ?>
                            <p class="j0ui-muted"><?= htmlspecialchars($grp['empty_msg'], ENT_QUOTES, 'UTF-8') ?></p>
                        <?php else: ?>
                            <div class="j0ui-actions">
                                <button type="button" class="j0ui-btn" data-j0-purge-select="all"><?= htmlspecialchars(L('Select all', 'Seleccionar todo'), ENT_QUOTES, 'UTF-8') ?></button>
                                <button type="button" class="j0ui-btn" data-j0-purge-select="none"><?= htmlspecialchars(L('Clear', 'Limpiar'), ENT_QUOTES, 'UTF-8') ?></button>
                            </div>
                            <ul class="j0ui-cat-list">
                                <?php foreach ($grpItems as $entry): ?>
                                    <li class="j0ui-cat-item">
                                        <label class="j0ui-cat-main j0ui-mono">
                                            <input type="checkbox" name="items[]" value="<?= htmlspecialchars($entry['rel'], ENT_QUOTES, 'UTF-8') ?>" <?= $grp['preselected'] ? 'checked' : '' ?>>
                                            <span><?= htmlspecialchars($entry['rel'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </label>
                                        <span class="j0ui-badge <?= $entry['warn'] ? 'j0ui-badge-warning' : '' ?>"><?= htmlspecialchars($entry['marker'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>

        </j0-region>
    </form>
    <?php
}