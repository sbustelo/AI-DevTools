<?php
// PATH: ai0/tools/files/dumper-view.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

/**
 * Renders the dumper UI view, root navigation controls, VFS category persistence, and context menu.
 *
 * @param array<string, mixed> $vm
 */
function dumper_render_view(array $vm): void
{
    $scan = is_array($vm['scan'] ?? null) ? $vm['scan'] : ['tree' => [], 'counts' => [], 'total_files' => 0, 'included_files' => 0];
    $categories = is_array($vm['categories'] ?? null) ? $vm['categories'] : [];
    $dumpOutput = isset($vm['dumpOutput']) && is_string($vm['dumpOutput']) ? $vm['dumpOutput'] : null;
    $downloadName = htmlspecialchars((string) ($vm['downloadName'] ?? 'dump.txt'), ENT_QUOTES, 'UTF-8');
    $compact = !empty($vm['compact']);
    $scopeLabel = ((string) ($vm['scopeRel'] ?? '')) === '' ? '/' : (string) $vm['scopeRel'];
    $pathLevels = is_array($vm['pathLevels'] ?? null) ? $vm['pathLevels'] : [];
    $currentRoot = (string) ($vm['browseRoot'] ?? '');
    $canRevealFinder = !empty($vm['canRevealFinder']);
    $iconsCfg = is_array($vm['icons'] ?? null) ? $vm['icons'] : [];
    $fullProjectUrl = isset($vm['fullProjectUrl']) && is_string($vm['fullProjectUrl']) ? $vm['fullProjectUrl'] : null;
    $isShallowMode = !empty($vm['isShallowMode']);
    $explicitScan = !empty($vm['explicitScan']);
    $shallowItems = is_array($vm['shallowItems'] ?? null) ? $vm['shallowItems'] : [];
    $setRootUrl = '?root=' . rawurlencode($currentRoot) . '&scan=1&init_root=1';
    $purgeScan = is_array($vm['purgeScan'] ?? null) ? $vm['purgeScan'] : ['discard' => [], 'backup' => [], 'disabled' => [], 'os_junk' => []];
    $restoreScan = is_array($vm['restoreScan'] ?? null) ? $vm['restoreScan'] : ['batches' => [], 'total_files' => 0];
    $activeTab = (string) ($vm['activeTab'] ?? 'view');
    if (!in_array($activeTab, ['view', 'dump', 'purge', 'restore'], true)) {
        $activeTab = 'view';
    }
    $apiUrl = htmlspecialchars((string) ($vm['apiUrl'] ?? ''), ENT_QUOTES, 'UTF-8');
    $hasExplicitTab = !empty($vm['hasExplicitTab']);
    $highlightedRel = trim((string) ($vm['highlightedRel'] ?? ''));
    $purgeQueuedCount = count($purgeScan['discard'] ?? [])
        + count($purgeScan['backup'] ?? [])
        + count($purgeScan['os_junk'] ?? []);
    $restoreBatchCount = count($restoreScan['batches'] ?? []);
    $rootColor = '';
    if ($currentRoot !== '' && is_file(rtrim($currentRoot, '/') . '/.ai0root.json')) {
        $rcData = json_decode((string) @file_get_contents(rtrim($currentRoot, '/') . '/.ai0root.json'), true);
        if (is_array($rcData) && !empty($rcData['color'])) {
            $rootColor = preg_replace('/[^a-z]/', '', strtolower((string) $rcData['color'])) ?: '';
        }
    }
    ?>
    <?php if ($dumpOutput !== null): ?>
        <section class="j0ui-card j0ui-dump-result-card">
            <h2><?= htmlspecialchars(L('Generated Dump ({0} files)', 'Dump generado ({0} archivos)', [substr_count($dumpOutput, '// PATH:')]), ENT_QUOTES, 'UTF-8') ?></h2>
            <textarea class="j0ui-textarea j0ui-textarea--fill" rows="8" readonly data-j0-copy-source="dumper-out"><?= htmlspecialchars($dumpOutput, ENT_QUOTES, 'UTF-8') ?></textarea>
            <div class="j0ui-actions">
                <button type="button" class="j0ui-btn j0ui-btn--primary" data-j0-action="copy-target" data-j0-target="dumper-out"><?= htmlspecialchars(L('Copy Dump', 'Copiar Dump'), ENT_QUOTES, 'UTF-8') ?></button>
                <button type="button" class="j0ui-btn" data-j0-action="download-target" data-j0-target="dumper-out" data-j0-filename="<?= $downloadName ?>"><?= htmlspecialchars(L('Download .txt', 'Descargar .txt'), ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </section>
    <?php endif; ?>

    <div class="j0ui-grid-explorer" data-j0-tree-host data-j0-files-workspace data-j0-api-url="<?= $apiUrl ?>" data-j0-tree-root-key="<?= htmlspecialchars($currentRoot, ENT_QUOTES, 'UTF-8') ?>">
        <form method="post" class="j0ui-pane-form" data-j0-dumper-form>
            <input type="hidden" name="action" value="generate_dump">
            <input type="hidden" name="active_tab" value="dump">
            <input type="hidden" name="browse_root" value="<?= htmlspecialchars($currentRoot, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="scan" value="<?= $explicitScan ? '1' : '0' ?>">
            <input type="hidden" name="compact" value="<?= $compact ? '1' : '0' ?>" data-j0-compact-input>

            <j0-pane class="j0ui-card j0ui-pane-card" data-j0-root-pane<?= $rootColor !== '' ? (' data-j0-label-color="' . htmlspecialchars($rootColor, ENT_QUOTES, 'UTF-8') . '"') : '' ?>>
                <j0-region role="head">
                <div class="j0ui-tree-toolbar <?= !$isShallowMode ? 'j0ui-tree-toolbar--root' : 'j0ui-tree-toolbar--shallow' ?>"
                     data-j0-tree-node
                     data-j0-node-type="dir"
                     data-j0-node-path=""
                     data-j0-node-name="<?= htmlspecialchars(basename($currentRoot) ?: '/', ENT_QUOTES, 'UTF-8') ?>">
                    <?php
                    $ctxForIcon = [
                        'root'    => (string) ($vm['defaultRoot'] ?? $currentRoot),
                        'ai0_dir' => dirname(__DIR__, 2),
                    ];
                    $rootIconSpec = function_exists('ai0_resolve_icon')
                        ? ai0_resolve_icon($currentRoot, $ctxForIcon, 'dir', 'j0ui-toolbar-icon' . ($isShallowMode ? ' j0ui-icon-mono' : ''))
                        : ['html' => '<span aria-hidden="true">📁</span>'];
                    ?>
                    <?php if (!$isShallowMode): ?>
                        <input type="checkbox" data-j0-tree-check-all aria-label="<?= htmlspecialchars(L('Select all files', 'Seleccionar todos los archivos'), ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                    <input type="hidden" value="<?= htmlspecialchars($currentRoot, ENT_QUOTES, 'UTF-8') ?>" data-j0-root-select>
                    <?php if ($pathLevels !== []): ?>
                        <div class="j0-dropdown">
                            <button type="button"
                                    class="j0-dropdown-trigger"
                                    data-j0-dropdown-trigger="ai0-files-root"
                                    aria-haspopup="listbox"
                                    aria-expanded="false"
                                    aria-label="<?= htmlspecialchars(L('Root path level', 'Nivel de ruta raíz'), ENT_QUOTES, 'UTF-8') ?>">
                                <?= $rootIconSpec['html'] ?>
                                <span class="j0-dropdown-label"><?= htmlspecialchars(basename($currentRoot) ?: '/', ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="j0-dropdown-caret" aria-hidden="true">▾</span>
                            </button>
                            <div class="j0-dropdown-menu" role="listbox" data-j0-dropdown-menu="ai0-files-root" data-j0-hidden="true">
                                <?php foreach ($pathLevels as $lvlIdx => $lvl): ?>
                                    <?php
                                    $lvlPath = (string) ($lvl['path'] ?? '');
                                    $lvlName = trim((string) ($lvl['label'] ?? '')) ?: (basename($lvlPath) ?: '/');
                                    $lvlIconHtml = function_exists('ai0_resolve_icon')
                                        ? ai0_resolve_icon($lvlPath, $ctxForIcon, 'dir', 'j0ui-tree-icon')['html']
                                        : '<span aria-hidden="true">📁</span>';
                                    ?>
                                    <button type="button"
                                            role="option"
                                            class="j0-dropdown-option"
                                            style="--j0-dd-depth: <?= (int) $lvlIdx ?>;"
                                            data-j0-dropdown-option
                                            data-j0-value="<?= htmlspecialchars($lvlPath, ENT_QUOTES, 'UTF-8') ?>"
                                            data-j0-selected="<?= ($lvlPath === $currentRoot) ? 'true' : 'false' ?>">
                                        <?= $lvlIconHtml ?>
                                        <span class="j0-dropdown-label"><?= htmlspecialchars($lvlName, ENT_QUOTES, 'UTF-8') ?></span>
                                    </button>
                                <?php endforeach; ?>
                                <div class="j0ui-menu-divider" role="separator" data-j0-search-dd-sep data-j0-hidden="true"></div>
                                <div class="j0-dropdown-option"
                                     role="option"
                                     data-j0-search-dd-item
                                     data-j0-hidden="true"
                                     data-j0-selected="true"
                                     data-j0-search-prefix="<?= htmlspecialchars(L('🔍 Search results in', '🔍 Resultados en'), ENT_QUOTES, 'UTF-8') ?>"
                                     data-j0-root-label="<?= htmlspecialchars(basename($currentRoot) ?: '/', ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if (!$isShallowMode): ?>
                                        <input type="checkbox" data-j0-search-dd-check aria-label="<?= htmlspecialchars(L('Select all search matches', 'Seleccionar todas las coincidencias'), ENT_QUOTES, 'UTF-8') ?>">
                                    <?php endif; ?>
                                    <span class="j0-dropdown-label" data-j0-search-dd-label></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!$isShallowMode): ?>
                        <div class="j0ui-toolbar-icons">
                            <div class="j0-segmented" role="group" aria-label="<?= htmlspecialchars(L('Explorer view mode', 'Modo de vista del explorador'), ENT_QUOTES, 'UTF-8') ?>" data-j0-segmented="files-view-mode">
                                <button type="button" class="j0-segment-btn j0-active" data-j0-segment="tree" aria-pressed="true" title="<?= htmlspecialchars(L('Tree view', 'Vista de árbol'), ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="material-symbols-outlined" aria-hidden="true">format_list_bulleted</span>
                                </button>
                                <button type="button" class="j0-segment-btn" data-j0-segment="columns" aria-pressed="false" title="<?= htmlspecialchars(L('Columns view', 'Vista de columnas'), ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="material-symbols-outlined" aria-hidden="true">view_column</span>
                                </button>
                            </div>
                            <button type="button" class="j0ui-btn j0ui-btn--toolbar-text" data-j0-action="tree-toggle-all" data-j0-collapsed-state="true" data-j0-label-expand="<?= htmlspecialchars(L('Expand all', 'Expandir todo'), ENT_QUOTES, 'UTF-8') ?>" data-j0-label-collapse="<?= htmlspecialchars(L('Collapse all', 'Colapsar todo'), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(L('Expand all', 'Expandir todo'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(L('Expand all', 'Expandir todo'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(L('Expand all', 'Expandir todo'), ENT_QUOTES, 'UTF-8') ?></button>
                            <?php if ($fullProjectUrl !== null): ?>
                                <a class="j0ui-btn j0ui-btn--icon" href="<?= htmlspecialchars($fullProjectUrl, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars(L('Expand to root', 'Expandir a raíz'), ENT_QUOTES, 'UTF-8') ?>" aria-label="<?= htmlspecialchars(L('Expand to root', 'Expandir a raíz'), ENT_QUOTES, 'UTF-8') ?>">⤴️</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="j0ui-row-menu-trigger" data-j0-row-menu-trigger aria-label="<?= htmlspecialchars(L('Folder actions', 'Acciones de la carpeta'), ENT_QUOTES, 'UTF-8') ?>">▾</button>
                </div>

                <div class="j0ui-tree-search-banner" data-j0-search-banner data-j0-hidden="true" data-j0-label-matches="<?= htmlspecialchars(L('matches for', 'coincidencias para'), ENT_QUOTES, 'UTF-8') ?>">
                    <label class="j0ui-cat-main">
                        <?php if (!$isShallowMode): ?>
                            <input type="checkbox" data-j0-search-check-all aria-label="<?= htmlspecialchars(L('Select all search matches', 'Seleccionar todas las coincidencias'), ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                        <span class="j0ui-mono" data-j0-search-banner-text></span>
                    </label>
                    <button type="button" class="j0ui-btn j0ui-btn--icon" data-j0-action="clear-tree-search" title="<?= htmlspecialchars(L('Clear search', 'Limpiar búsqueda'), ENT_QUOTES, 'UTF-8') ?>">×</button>
                </div>
                </j0-region>

                <j0-region role="body">
                <?php if ($isShallowMode): ?>
                    <ul class="j0ui-tree-root j0ui-shallow-list">
                        <?php foreach ($shallowItems as $item): ?>
                            <?php
                            $isItemDir = !empty($item['is_dir']);
                            $rawItemName = (string) ($item['name'] ?? '');
                            $safeItemName = htmlspecialchars($rawItemName, ENT_QUOTES, 'UTF-8');
                            $isDimmedItem = (str_starts_with($rawItemName, '_') || str_starts_with($rawItemName, '.'));
                            $isHighlighted = ($highlightedRel !== '' && $highlightedRel === $rawItemName);
                            ?>
                            <li class="j0ui-tree-node j0ui-shallow-item <?= $isHighlighted ? 'j0ui-tree-node--selected' : '' ?>"
                                <?= $isItemDir ? 'data-j0-shallow-dir' : '' ?>
                                data-j0-node-type="<?= $isItemDir ? 'dir' : 'file' ?>"
                                data-j0-node-path="<?= $safeItemName ?>"
                                data-j0-node-name="<?= $safeItemName ?>">
                                <?php
                                $shallowIconSpec = ($isItemDir && function_exists('ai0_resolve_icon'))
                                    ? ai0_resolve_icon(rtrim($currentRoot, '/') . '/' . $rawItemName, $ctxForIcon, 'dir', 'j0ui-tree-icon')
                                    : ['html' => '<span class="j0ui-shallow-icon">' . ($isItemDir ? '📁' : '📄') . '</span>'];
                                ?>
                                <div class="j0ui-tree-row">
                                    <?= $shallowIconSpec['html'] ?>
                                    <span class="j0ui-tree-label <?= $isDimmedItem ? 'j0ui-item-dimmed' : '' ?>"><?= $safeItemName ?></span>
                                    <button type="button" class="j0ui-row-menu-trigger" data-j0-row-menu-trigger aria-label="<?= htmlspecialchars(L('Item actions', 'Acciones del elemento'), ENT_QUOTES, 'UTF-8') ?>">▾</button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <ul class="j0ui-tree-root j0ui-scroll-area" data-j0-tree-root>
                        <?php dumper_render_tree_nodes(is_array($scan['tree'] ?? null) ? $scan['tree'] : [], $highlightedRel, $currentRoot, $ctxForIcon); ?>
                    </ul>
                    <div class="j0ui-miller-host" data-j0-miller-host data-j0-hidden="true"></div>
                <?php endif; ?>
                </j0-region>
            </j0-pane>
        </form>

        <div class="j0ui-col-splitter" role="separator" aria-orientation="vertical" aria-label="<?= htmlspecialchars(L('Resize explorer columns', 'Redimensionar columnas del explorador'), ENT_QUOTES, 'UTF-8') ?>" data-j0-files-splitter></div>

        <j0-pane class="j0ui-card j0ui-pane-card j0ui-files-right-pane">
            <?php if ($isShallowMode): ?>
                <j0-region role="body" class="j0ui-scroll-area">
                <h2><?= htmlspecialchars(L('Outside Project Root', 'Fuera de la Raíz del Proyecto'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="j0ui-muted"><?= htmlspecialchars(L('You are browsing outside the active project root: recursive tree scanning is disabled here.', 'Estás navegando fuera de la raíz activa del proyecto: el escaneo recursivo del árbol está desactivado acá.'), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="j0ui-muted"><?= htmlspecialchars(L('Double-click folders on the left to navigate to the target directory before confirming below.', 'Hacé doble clic en las carpetas de la izquierda hasta llegar al directorio deseado antes de confirmar abajo.'), ENT_QUOTES, 'UTF-8') ?></p>
                <div class="j0ui-actions">
                    <a class="j0ui-btn j0ui-btn--primary" href="<?= htmlspecialchars($setRootUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(L('Set this as project root', 'Usar esta carpeta como raíz'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
                <?php
                $tempDirVm = (string) ($vm['tempDir'] ?? '');
                $defaultRootVm = (string) ($vm['defaultRoot'] ?? '');
                $declaredRoots = function_exists('ai0_list_declared_roots')
                    ? ai0_list_declared_roots($tempDirVm, $defaultRootVm)
                    : [];
                ?>
                <?php if ($declaredRoots !== []): ?>
                    <hr class="j0ui-divider">
                    <h3><?= htmlspecialchars(L('Declared Project Roots', 'Raíces de Proyecto Declaradas'), ENT_QUOTES, 'UTF-8') ?></h3>
                    <ul class="j0ui-cat-list">
                        <?php foreach ($declaredRoots as $dRoot): ?>
                            <?php
                            $dIconSpec = function_exists('ai0_resolve_icon')
                                ? ai0_resolve_icon((string) $dRoot['path'], $ctxForIcon, 'dir', 'j0ui-tree-icon')
                                : ['html' => '<span>' . htmlspecialchars((string) $dRoot['icon'], ENT_QUOTES, 'UTF-8') . '</span>'];
                            ?>
                            <li class="j0ui-cat-item">
                                <a class="j0ui-tree-label" href="?browse=<?= rawurlencode((string) $dRoot['path']) ?>&init_root=1">
                                    <?= $dIconSpec['html'] ?>
                                    <span class="j0ui-tree-name"><?= htmlspecialchars((string) $dRoot['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                </j0-region>
            <?php else: ?>
                <j0-region role="head">
                <div class="j0ui-tabbar" data-j0-tab-group="explorer-workspace" data-j0-server-tab="<?= $hasExplicitTab ? htmlspecialchars($activeTab, ENT_QUOTES, 'UTF-8') : '' ?>">
                    <button type="button" class="j0ui-btn <?= $activeTab === 'view' ? 'j0ui-active' : '' ?>" data-j0-target="view"><?= htmlspecialchars(L('View', 'Vista'), ENT_QUOTES, 'UTF-8') ?></button>
                    <button type="button" class="j0ui-btn <?= $activeTab === 'dump' ? 'j0ui-active' : '' ?>" data-j0-target="dump">Dump</button>
                    <button type="button" class="j0ui-btn <?= $activeTab === 'purge' ? 'j0ui-active' : '' ?>" data-j0-target="purge">
                        Purge <span class="j0ui-badge"><?= $purgeQueuedCount ?></span>
                    </button>
                    <button type="button" class="j0ui-btn <?= $activeTab === 'restore' ? 'j0ui-active' : '' ?>" data-j0-target="restore">
                        Restore <span class="j0ui-badge"><?= $restoreBatchCount ?></span>
                    </button>
                </div>
                </j0-region>

                <j0-region role="body" data-j0-pane-group="explorer-workspace">
                    <j0-pane class="<?= $activeTab === 'view' ? 'j0ui-active' : '' ?>" data-j0-panel="view" data-j0-inspector-host data-j0-api-url="<?= $apiUrl ?>">
                        <j0-region role="head">
                        <div class="j0ui-view-controls">
                            <label class="j0ui-cat-main">
                                <input type="checkbox" checked data-j0-view-toggle="underscore">
                                <span><?= htmlspecialchars(L('Show commented (_)', 'Mostrar comentados (_)'), ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                            <label class="j0ui-cat-main">
                                <input type="checkbox" checked data-j0-view-toggle="dot">
                                <span><?= htmlspecialchars(L('Show hidden (.)', 'Mostrar invisibles (.)'), ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                        </div>

                        <div class="j0ui-alert-inline" data-j0-insp-status data-j0-hidden="true"></div>

                        <div class="j0ui-inspector-head">
                            <div class="j0ui-inspector-title">
                                <button type="button" class="j0ui-inspector-big-icon-btn" data-j0-insp-edit-root-icon data-j0-editable="true" title="<?= htmlspecialchars(L('Change root icon', 'Cambiar ícono de raíz'), ENT_QUOTES, 'UTF-8') ?>">
                                    <span class="j0ui-inspector-big-icon" data-j0-insp-big-icon>📁</span>
                                    <span class="j0ui-big-icon-overlay j0ui-icon-mono" aria-hidden="true">✏️</span>
                                </button>
                                <div class="j0ui-inspector-name-wrap">
                                    <span class="j0ui-inspector-name" data-j0-insp-name>/</span>
                                    <button type="button" class="j0ui-edit-lock j0ui-icon-mono" data-j0-insp-edit-name data-j0-hidden="true" title="<?= htmlspecialchars(L('Rename', 'Renombrar'), ENT_QUOTES, 'UTF-8') ?>">✏️</button>
                                </div>
                            </div>
                            <button type="button" class="j0ui-btn j0ui-push-end" data-j0-insp-back-root data-j0-hidden="true" title="<?= htmlspecialchars(L('Back to container folder (Esc)', 'Volver a la carpeta contenedora (Esc)'), ENT_QUOTES, 'UTF-8') ?>">↩ <?= htmlspecialchars(L('Container', 'Contenedor'), ENT_QUOTES, 'UTF-8') ?></button>
                        </div>
                        </j0-region>

                        <j0-region role="body" class="j0ui-scroll-area">
                        <div class="j0ui-inspector-grid">
                            <span class="j0ui-inspector-key"><?= htmlspecialchars(L('Kind:', 'Tipo:'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="j0ui-inspector-val" data-j0-insp-kind>Folder</span>

                            <span class="j0ui-inspector-key"><?= htmlspecialchars(L('Where:', 'Ubicación:'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="j0ui-inspector-val j0ui-where-wrap" data-j0-insp-path></span>

                            <span class="j0ui-inspector-key"><?= htmlspecialchars(L('Size:', 'Tamaño:'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="j0ui-inspector-val" data-j0-insp-size>—</span>

                            <span class="j0ui-inspector-key"><?= htmlspecialchars(L('Modified:', 'Modificado:'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="j0ui-inspector-val" data-j0-insp-mtime>—</span>

                            <span class="j0ui-inspector-key" data-j0-insp-items-row><?= htmlspecialchars(L('Items:', 'Ítems:'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="j0ui-inspector-val" data-j0-insp-items>0</span>

                            <span class="j0ui-inspector-key" data-j0-insp-cat-row data-j0-hidden="true"><?= htmlspecialchars(L('Category:', 'Categoría:'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="j0ui-inspector-val" data-j0-insp-cat-val data-j0-hidden="true"><span class="j0ui-badge j0ui-badge-accent" data-j0-insp-cat-badge></span></span>

                            <span class="j0ui-inspector-key" data-j0-insp-root-row data-j0-hidden="true"><?= htmlspecialchars(L('Root:', 'Raíz:'), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="j0ui-inspector-val" data-j0-insp-root-val data-j0-hidden="true">
                                <label class="j0ui-cat-main">
                                    <input type="checkbox" data-j0-insp-root-toggle>
                                    <span><?= htmlspecialchars(L('Declared Project Root', 'Project Root declarado'), ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                            </span>
                        </div>

                        <div class="j0ui-inspector-section">
                            <div class="j0ui-inspector-section-head">
                                <span><?= htmlspecialchars(L('Sharing & Permissions', 'Compartir y Permisos'), ENT_QUOTES, 'UTF-8') ?></span>
                                <div>
                                    <button type="button" class="j0ui-btn j0ui-btn--primary" data-j0-perm-save data-j0-hidden="true"><?= htmlspecialchars(L('Save', 'Guardar'), ENT_QUOTES, 'UTF-8') ?></button>
                                    <button type="button" class="j0ui-edit-lock j0ui-icon-mono" data-j0-perm-lock data-j0-locked="true" title="<?= htmlspecialchars(L('Unlock permissions', 'Desbloquear permisos'), ENT_QUOTES, 'UTF-8') ?>">🔒</button>
                                </div>
                            </div>
                            <table class="j0ui-perm-table">
                                <tbody>
                                    <tr>
                                        <td><?= htmlspecialchars(L('Owner (Me)', 'Propietario (Yo)'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <select class="j0ui-btn j0ui-perm-select" data-j0-perm="owner" disabled>
                                                <option value="rw"><?= htmlspecialchars(L('Read & Write', 'Lectura y Escritura'), ENT_QUOTES, 'UTF-8') ?></option>
                                                <option value="r"><?= htmlspecialchars(L('Read only', 'Solo lectura'), ENT_QUOTES, 'UTF-8') ?></option>
                                                <option value="none"><?= htmlspecialchars(L('No Access', 'Sin acceso'), ENT_QUOTES, 'UTF-8') ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?= htmlspecialchars(L('Group', 'Grupo'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <select class="j0ui-btn j0ui-perm-select" data-j0-perm="group" disabled>
                                                <option value="rw"><?= htmlspecialchars(L('Read & Write', 'Lectura y Escritura'), ENT_QUOTES, 'UTF-8') ?></option>
                                                <option value="r"><?= htmlspecialchars(L('Read only', 'Solo lectura'), ENT_QUOTES, 'UTF-8') ?></option>
                                                <option value="none"><?= htmlspecialchars(L('No Access', 'Sin acceso'), ENT_QUOTES, 'UTF-8') ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><?= htmlspecialchars(L('Everyone', 'Todos'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <select class="j0ui-btn j0ui-perm-select" data-j0-perm="everyone" disabled>
                                                <option value="rw"><?= htmlspecialchars(L('Read & Write', 'Lectura y Escritura'), ENT_QUOTES, 'UTF-8') ?></option>
                                                <option value="r"><?= htmlspecialchars(L('Read only', 'Solo lectura'), ENT_QUOTES, 'UTF-8') ?></option>
                                                <option value="none"><?= htmlspecialchars(L('No Access', 'Sin acceso'), ENT_QUOTES, 'UTF-8') ?></option>
                                            </select>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="j0ui-inspector-section" data-j0-insp-preview-section data-j0-hidden="true">
                            <div class="j0ui-inspector-section-head">
                                <span><?= htmlspecialchars(L('Preview (first 2048 chars)', 'Vista previa (primeros 2048 caracteres)'), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <pre class="j0ui-preview-box" data-j0-insp-preview></pre>
                        </div>
                        </j0-region>
                    </j0-pane>

                    <j0-pane class="<?= $activeTab === 'dump' ? 'j0ui-active' : '' ?>" data-j0-panel="dump">
                        <j0-region role="head">
                        <div class="j0ui-dump-cta">
                            <button type="button" class="j0ui-btn j0ui-btn--primary j0ui-btn--cta" data-j0-action="submit-dump"><?= htmlspecialchars(L('Generate Dump', 'Generar Dump'), ENT_QUOTES, 'UTF-8') ?></button>
                            <span class="j0ui-badge" data-j0-tree-stats data-j0-label-files="<?= htmlspecialchars(L('files', 'archivos'), ENT_QUOTES, 'UTF-8') ?>"><?= (int) ($scan['included_files'] ?? 0) ?> / <?= (int) ($scan['total_files'] ?? 0) ?> <?= htmlspecialchars(L('files', 'archivos'), ENT_QUOTES, 'UTF-8') ?></span>
                            <label class="j0ui-cat-main">
                                <input type="checkbox" data-j0-compact-toggle <?= $compact ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars(L('Compact mode (collapse blank lines)', 'Modo compacto (colapsar líneas vacías)'), ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                        </div>

                        <h2><?= htmlspecialchars(L('Exclusion Categories', 'Categorías de Exclusión'), ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="j0ui-muted"><?= htmlspecialchars(L('Checked = include in dump. Unchecked = exclude.', 'Marcado = incluir en el dump. Desmarcado = excluir.'), ENT_QUOTES, 'UTF-8') ?></p>
                        </j0-region>
                        <j0-region role="body" class="j0ui-scroll-area">
                        <ul class="j0ui-cat-list">
                            <?php foreach ($categories as $catId => $def): ?>
                                <?php
                                $count = (int) ($scan['counts'][$catId] ?? 0);
                                $isIncluded = !((bool) ($def['default_excluded'] ?? true));
                                $rawCatLabel = $def['label'] ?? $catId;
                                $catLabel = is_array($rawCatLabel) ? L($rawCatLabel) : (string) $rawCatLabel;
                                $hint = (string) ($def['pattern'] ?? (isset($def['j0_artifact']) ? ('j0 artifact: ' . $def['j0_artifact']) : ('prefix: ' . ($def['prefix_state'] ?? $catId))));
                                ?>
                                <li class="j0ui-cat-item">
                                    <label class="j0ui-cat-main">
                                        <input type="checkbox" data-j0-cat-check="<?= htmlspecialchars((string) $catId, ENT_QUOTES, 'UTF-8') ?>" <?= $isIncluded ? 'checked' : '' ?>>
                                        <span>
                                            <span class="j0ui-cat-title"><?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="j0ui-cat-pattern"><?= htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') ?></span>
                                        </span>
                                    </label>
                                    <span class="j0ui-badge" data-j0-cat-count="<?= htmlspecialchars((string) $catId, ENT_QUOTES, 'UTF-8') ?>"><?= $count ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        </j0-region>
                    </j0-pane>

                    <j0-pane class="<?= $activeTab === 'purge' ? 'j0ui-active' : '' ?>" data-j0-panel="purge">
                        <?php
                        purge_render_view([
                            'scan'         => $purgeScan,
                            'flash'        => null,
                            'scopeRel'     => (string) ($vm['scopeRel'] ?? ''),
                            'restoreUrl'   => '',
                            'activeRoot'   => $currentRoot,
                            'embedded'     => true,
                            'explicitScan' => $explicitScan,
                        ]);
                        ?>
                    </j0-pane>

                    <j0-pane class="<?= $activeTab === 'restore' ? 'j0ui-active' : '' ?>" data-j0-panel="restore">
                        <?php
                        restore_render_view([
                            'scan'         => $restoreScan,
                            'flash'        => null,
                            'scopeRel'     => (string) ($vm['scopeRel'] ?? ''),
                            'purgeUrl'     => '',
                            'activeRoot'   => $currentRoot,
                            'embedded'     => true,
                            'explicitScan' => $explicitScan,
                        ]);
                        ?>
                    </j0-pane>
                </j0-region>
            <?php endif; ?>
        </j0-pane>
    </div>

    <?php
    ai0_render_context_menu($iconsCfg, [
        'root_field' => 'browse_root',
        'can_reveal' => $canRevealFinder,
    ]);
    ?>


    <?php
}

/**
 * @param array<int, array<string, mixed>> $nodes
 */
function dumper_render_tree_nodes(array $nodes, string $highlightedRel = '', string $baseRoot = '', array $ctxForIcon = []): void
{
    if ($ctxForIcon === []) {
        $ctxForIcon = ['root' => $baseRoot, 'ai0_dir' => dirname(__DIR__, 2)];
    }
    foreach ($nodes as $node) {
        $isDir = !empty($node['is_dir']);
        $hasChildren = $isDir && is_array($node['children'] ?? null) && $node['children'] !== [];
        $excluded = !empty($node['excluded']);
        $rawRel = (string) ($node['rel'] ?? '');
        $isHighlighted = ($highlightedRel !== '' && $rawRel === $highlightedRel);
        $cat = htmlspecialchars((string) ($node['cat'] ?? 'none'), ENT_QUOTES, 'UTF-8');
        $rel = htmlspecialchars($rawRel, ENT_QUOTES, 'UTF-8');
        $rawName = (string) ($node['name'] ?? '');
        $isDimmed = (str_starts_with($rawName, '_') || str_starts_with($rawName, '.'));
        $name = htmlspecialchars($rawName, ENT_QUOTES, 'UTF-8');
        ?>
        <?php
        $nodeColor = '';
        if ($isDir && $baseRoot !== '') {
            $colorFile = rtrim($baseRoot, '/') . '/' . $rawRel . '/.ai0root.json';
            if (is_file($colorFile)) {
                $cData = json_decode((string) file_get_contents($colorFile), true);
                if (is_array($cData) && !empty($cData['color'])) {
                    $nodeColor = preg_replace('/[^a-z]/', '', strtolower((string) $cData['color'])) ?: '';
                }
            }
        }
        ?>
        <li class="j0ui-tree-node <?= $isHighlighted ? 'j0ui-tree-node--selected' : '' ?>"
            <?= $nodeColor !== '' ? ('data-j0-label-color="' . htmlspecialchars($nodeColor, ENT_QUOTES, 'UTF-8') . '"') : '' ?>
            data-j0-tree-node
            data-j0-node-type="<?= $isDir ? 'dir' : 'file' ?>"
            data-j0-node-path="<?= $rel ?>"
            data-j0-node-name="<?= $name ?>"
            data-j0-cat="<?= $cat ?>"
            data-j0-excluded="<?= $excluded ? 'true' : 'false' ?>"
            data-j0-collapsed="true">
            <div class="j0ui-tree-row">
                <?php if ($hasChildren): ?>
                    <button type="button" class="j0ui-tree-caret" data-j0-tree-toggle>▸</button>
                <?php else: ?>
                    <span class="j0ui-tree-caret-spacer"></span>
                <?php endif; ?>
                <input type="checkbox"
                       data-j0-tree-check
                       <?= (!$isDir || !empty($node['heavy'])) ? 'name="selected_files[]"' : '' ?>
                       value="<?= $rel ?>"
                       <?= !$excluded ? 'checked' : '' ?>>
                <?php
                $nodeIconHtml = ($isDir && $baseRoot !== '' && function_exists('ai0_resolve_icon'))
                    ? ai0_resolve_icon(rtrim($baseRoot, '/') . '/' . $rawRel, $ctxForIcon, 'dir', 'j0ui-tree-icon')['html']
                    : '<span aria-hidden="true">' . ($isDir ? '📁' : '📄') . '</span>';
                ?>
                <span class="j0ui-tree-label" data-j0-tree-select title="<?= $name ?>">
                    <?= $nodeIconHtml ?>
                    <span class="j0ui-tree-name <?= $isDimmed ? 'j0ui-item-dimmed' : '' ?>"><?= $name ?></span>
                </span>
                <button type="button" class="j0ui-row-menu-trigger" data-j0-row-menu-trigger aria-label="<?= htmlspecialchars(L('Item actions', 'Acciones del elemento'), ENT_QUOTES, 'UTF-8') ?>">▾</button>
            </div>
            <?php if ($hasChildren): ?>
                <ul class="j0ui-tree-branch">
                    <?php dumper_render_tree_nodes($node['children'], $highlightedRel, $baseRoot, $ctxForIcon); ?>
                </ul>
            <?php endif; ?>
        </li>
        <?php
    }
}
