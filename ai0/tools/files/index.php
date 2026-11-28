<?php
// PATH: ai0/tools/files/index.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    define('J0_EXEC', true);
}

require_once dirname(__DIR__, 2) . '/core/ai0-context.php';
require_once dirname(__DIR__, 2) . '/ui/ui-layout.php';
require_once __DIR__ . '/dumper-config.php';
require_once __DIR__ . '/dumper-scanner.php';
require_once __DIR__ . '/dumper-formatter.php';
require_once __DIR__ . '/dumper-view.php';
require_once dirname(__DIR__) . '/purge/purge-manager.php';
require_once dirname(__DIR__) . '/purge/purge-view.php';
require_once dirname(__DIR__) . '/restore/restore-manager.php';
require_once dirname(__DIR__) . '/restore/restore-view.php';

$ctx = ai0_resolve_context(__DIR__);
$state = ai0_get_state($ctx);
$debug = (bool) ($state['debug'] ?? false);

$dumperConfig = dumper_load_config($ctx);

$bs = chr(92);
$defaultRoot = rtrim(str_replace($bs, '/', (string) ($ctx['project_root'] ?? $ctx['root'] ?? __DIR__)), '/');
if ($defaultRoot === '') {
    $defaultRoot = '/';
}

$iconsCfg = j0_config_json('icons', $ctx['domains']);
$navHighlightedRel = '';
$navInput = trim((string) ($_GET['nav'] ?? ''));

if ($navInput !== '' && function_exists('ai0_resolve_postel_path')) {
    $fromRaw = (string) ($_GET['from'] ?? '');
    $fromClean = $fromRaw !== '' ? ai0_parse_input_path($fromRaw, $defaultRoot, $iconsCfg) : '';
    $fromRoot = ($fromClean !== '' && !str_contains($fromClean, '..') && is_dir($fromClean)) ? $fromClean : $defaultRoot;
    $navRes = ai0_resolve_postel_path($navInput, $fromRoot, $defaultRoot, $iconsCfg, (string) ($ctx['temp_dir'] ?? ''));
    $browseRoot = $navRes['dir'];
    if ($navRes['matched'] && $navRes['kind'] === 'file') {
        $navHighlightedRel = $navRes['file_rel'];
    } elseif (!$navRes['matched']) {
        $_GET['q'] = $navRes['fallback_query'];
    }
} else {
    $requestedRoot = (string) ($_POST['browse_root'] ?? $_GET['browse'] ?? $_GET['root'] ?? '');
    $cleanReqRoot = ai0_parse_input_path($requestedRoot, $defaultRoot, $iconsCfg);
    if ($cleanReqRoot === '') {
        $cleanReqRoot = '/';
    }

    $browseRoot = ($requestedRoot !== '' && !str_contains($cleanReqRoot, '..') && is_dir($cleanReqRoot))
        ? $cleanReqRoot
        : $defaultRoot;
}

if (((string) ($_GET['init_root'] ?? '')) === '1') {
    if (ai0_set_project_root($ctx, $browseRoot)) {
        $defaultRoot = $browseRoot;
    }
}

$scopeRel = (string) ($ctx['down_rel'] ?? '');
if (isset($_GET['scope']) && $_GET['scope'] === 'root') {
    $scopeRel = '';
}
if ($browseRoot !== $defaultRoot) {
    $scopeRel = '';
}
$scanRoot = $scopeRel !== '' ? rtrim($browseRoot, '/') . '/' . trim($scopeRel, '/') : $browseRoot;
$isInsideDefault = ($browseRoot === $defaultRoot) || str_starts_with(rtrim($browseRoot, '/') . '/', rtrim($defaultRoot, '/') . '/');
$explicitScan = ((string) ($_POST['scan'] ?? $_GET['scan'] ?? '')) === '1';
$isShallowMode = !$isInsideDefault && !$explicitScan;
$ctx['active_root'] = $defaultRoot;

$flash = null;
$flashType = 'j0ui-alert-success';
$dumpOutput = null;
$compact = false;

$rawTab = (string) ($_POST['active_tab'] ?? $_GET['tab'] ?? '');
$hasExplicitTab = in_array($rawTab, ['view', 'dump', 'purge', 'restore'], true);
$activeTab = $hasExplicitTab ? $rawTab : 'view';

$domains = ($browseRoot === $defaultRoot)
    ? $ctx['domains']
    : j0_config_domains($browseRoot, $ctx['ai0_dir']);
$prefixCfg = j0_config_json('prefixes', $domains);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $postAction = (string) ($_POST['action'] ?? '');

    if ($postAction === 'purge_selected') {
        $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
        $deletedCount = PurgeManager::deleteSelected($browseRoot, $items, $prefixCfg);
        $flash = L('Purge completed: {0} item(s) permanently deleted.', 'Purga completada: {0} ítem(s) eliminado(s) permanentemente.', [$deletedCount]);
        $activeTab = 'purge';
        $hasExplicitTab = true;
    } elseif ($postAction === 'restore_batch') {
        $ts = (string) ($_POST['timestamp'] ?? '');
        $restored = RestoreManager::restoreBatch($browseRoot, $ts);
        $flash = L('Batch {0} restored ({1} files).', 'Lote {0} restaurado ({1} archivos).', [$ts, count($restored)]);
        $activeTab = 'restore';
        $hasExplicitTab = true;
    } elseif ($postAction === 'restore_single') {
        $bak = (string) ($_POST['backup_rel'] ?? '');
        $restoredFile = RestoreManager::restoreSingle($browseRoot, $bak);
        if ($restoredFile !== null) {
            $flash = L('Restored: {0}', 'Restaurado: {0}', [$restoredFile]);
        } else {
            $flash = L('Failed to restore selected backup file.', 'No se pudo restaurar el archivo de respaldo seleccionado.');
            $flashType = 'j0ui-alert-danger';
        }
        $activeTab = 'restore';
        $hasExplicitTab = true;
    } elseif ($postAction === 'generate_dump') {
        $selected = isset($_POST['selected_files']) && is_array($_POST['selected_files']) ? $_POST['selected_files'] : [];
        $compact = isset($_POST['compact']) && $_POST['compact'] === '1';
        $dumpOutput = DumperFormatter::buildDump($scanRoot, $selected, $dumperConfig['binary_extensions'], $compact);

        $byteLen = strlen($dumpOutput);
        $includedCount = substr_count($dumpOutput, '// PATH:');
        if ($byteLen === 0 || $includedCount === 0) {
            $tempDir = rtrim(str_replace($bs, '/', (string) ($ctx['temp_dir'] ?? ($defaultRoot . '/_j0-temp'))), '/');
            if (is_dir($tempDir) || @mkdir($tempDir, 0775, true)) {
                $logEntry = [
                    'ts'             => date('c'),
                    'browse_root'    => $browseRoot,
                    'post_count'     => count($selected),
                    'max_input_vars' => (string) ini_get('max_input_vars'),
                    'included_files' => $includedCount,
                    'bytes'          => $byteLen,
                    'sample_paths'   => array_slice(array_map('strval', $selected), 0, 25),
                ];
                @file_put_contents($tempDir . '/dumper.log', json_encode($logEntry, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
            }
        }
    } elseif ($postAction === 'rename_item') {
        $relItem = ltrim(str_replace($bs, '/', trim((string) ($_POST['rel_path'] ?? ''))), '/');
        $newName = trim((string) ($_POST['new_name'] ?? ''));
        if ($relItem === '' || str_contains($relItem, '..') || $newName === '' || str_contains($newName, '/') || str_contains($newName, $bs) || str_contains($newName, '..')) {
            $flash = L('Invalid item path or new name for rename.', 'Ruta o nombre nuevo inválido para renombrar.');
            $flashType = 'j0ui-alert-danger';
        } else {
            $srcFull = $scanRoot . '/' . $relItem;
            $dstFull = dirname($srcFull) . '/' . $newName;
            if (!file_exists($srcFull)) {
                $flash = L('Target item not found.', 'Elemento objetivo no encontrado.');
                $flashType = 'j0ui-alert-danger';
            } elseif (file_exists($dstFull)) {
                $flash = L('Destination name already exists.', 'El nombre de destino ya existe.');
                $flashType = 'j0ui-alert-danger';
            } elseif (!@rename($srcFull, $dstFull)) {
                $flash = L('Failed to rename item.', 'Error al renombrar el elemento.');
                $flashType = 'j0ui-alert-danger';
            } else {
                $flash = L('Renamed to {0}.', 'Renombrado a {0}.', [$newName]);
            }
        }
    } elseif ($postAction === 'toggle_disable') {
        $relItem = ltrim(str_replace($bs, '/', trim((string) ($_POST['rel_path'] ?? ''))), '/');
        if ($relItem === '' || str_contains($relItem, '..')) {
            $flash = L('Invalid item path for disable toggle.', 'Ruta inválida para alternar prefijo _.');
            $flashType = 'j0ui-alert-danger';
        } else {
            $srcFull = $scanRoot . '/' . $relItem;
            $base = basename($srcFull);
            $nextBase = str_starts_with($base, '_') ? substr($base, 1) : ('_' . $base);
            $dstFull = dirname($srcFull) . '/' . $nextBase;
            if (!file_exists($srcFull) || $nextBase === '') {
                $flash = L('Target item not found.', 'Elemento objetivo no encontrado.');
                $flashType = 'j0ui-alert-danger';
            } elseif (file_exists($dstFull)) {
                $flash = L('Destination name already exists.', 'El nombre de destino ya existe.');
                $flashType = 'j0ui-alert-danger';
            } elseif (!@rename($srcFull, $dstFull)) {
                $flash = L('Failed to toggle _ prefix.', 'Error al alternar el prefijo _.');
                $flashType = 'j0ui-alert-danger';
            } else {
                $flash = L('Toggled: {0} -> {1}', 'Alternado: {0} -> {1}', [$base, $nextBase]);
            }
        }
    } elseif ($postAction === 'reveal_finder') {
        $relItem = ltrim(str_replace($bs, '/', trim((string) ($_POST['rel_path'] ?? ''))), '/');
        $candidateFull = $relItem !== '' ? ($scanRoot . '/' . $relItem) : $scanRoot;
        $realTarget = realpath($candidateFull);
        $realRoot = realpath($scanRoot);
        if (PHP_OS_FAMILY !== 'Darwin') {
            $flash = L('Reveal in Finder is available only on macOS.', 'Mostrar en Finder solo está disponible en macOS.');
            $flashType = 'j0ui-alert-warning';
        } elseif (str_contains($relItem, '..') || $realTarget === false || !file_exists($realTarget)) {
            $flash = L('Target path does not exist.', 'La ruta objetivo no existe.');
            $flashType = 'j0ui-alert-danger';
        } elseif ($realRoot !== false && $realTarget !== $realRoot && !str_starts_with($realTarget . '/', $realRoot . '/')) {
            $flash = L('Target path is outside active root.', 'La ruta objetivo está fuera de la raíz activa.');
            $flashType = 'j0ui-alert-danger';
        } else {
            $out = [];
            $code = 0;
            $cmd = '/usr/bin/open -R ' . escapeshellarg($realTarget) . ' 2>&1';
            @exec($cmd, $out, $code);
            if ($code !== 0) {
                $escapedApple = str_replace(['\\', '"'], ['\\\\', '\\"'], $realTarget);
                $osaOut = [];
                $osaCode = 0;
                @exec('/usr/bin/osascript -e ' . escapeshellarg('tell application "Finder" to reveal POSIX file "' . $escapedApple . '"') . ' -e ' . escapeshellarg('tell application "Finder" to activate') . ' 2>&1', $osaOut, $osaCode);
                if ($osaCode === 0) {
                    $code = 0;
                } else {
                    $out = array_merge($out, $osaOut);
                }
            }
            if ($code !== 0) {
                $flash = L('Failed to reveal in Finder: {0}', 'No se pudo mostrar en Finder: {0}', [implode(' ', $out)]);
                $flashType = 'j0ui-alert-danger';
            } else {
                $flash = L('Revealed in Finder: {0}', 'Mostrado en Finder: {0}', [basename($realTarget)]);
            }
        }
    } elseif ($postAction === 'snapshot_item') {
        require_once dirname(__DIR__, 2) . '/core/ai0-context.php';
        $relItem = ltrim(str_replace($bs, '/', trim((string) ($_POST['rel_path'] ?? ''))), '/');
        $candidateFull = $relItem !== '' ? ($scanRoot . '/' . $relItem) : $scanRoot;
        $realTarget = realpath($candidateFull);
        $realRoot = realpath($scanRoot);
        if (str_contains($relItem, '..') || $realTarget === false || !file_exists($realTarget)) {
            $flash = L('Target path does not exist.', 'La ruta objetivo no existe.');
            $flashType = 'j0ui-alert-danger';
        } elseif ($realRoot !== false && $realTarget !== $realRoot && !str_starts_with($realTarget . '/', $realRoot . '/')) {
            $flash = L('Target path is outside active root.', 'La ruta objetivo está fuera de la raíz activa.');
            $flashType = 'j0ui-alert-danger';
        } elseif (is_dir($realTarget)) {
            $zipRes = ai0_create_zip_snapshot($realTarget);
            $flash = $zipRes['message'];
            $flashType = $zipRes['ok'] ? 'j0ui-alert-success' : 'j0ui-alert-danger';
        } else {
            j0_use('fs-io');
            $snapStamp = date('Y-m-d\TH-i-s');
            $bakPath = j0_backup_rotate($realTarget, "_\u{1F4E6}", $snapStamp, false);
            if (is_string($bakPath) && is_file($bakPath)) {
                $flash = L('Snapshot created: {0}', 'Snapshot creado: {0}', [basename($bakPath)]);
                $flashType = 'j0ui-alert-success';
            } else {
                $flash = L('Failed to create snapshot.', 'Error al crear el snapshot.');
                $flashType = 'j0ui-alert-danger';
            }
        }
    } elseif ($postAction === 'discard_item') {
        $relItem = ltrim(str_replace($bs, '/', trim((string) ($_POST['rel_path'] ?? ''))), '/');
        $candidateFull = $relItem !== '' ? ($scanRoot . '/' . $relItem) : '';
        $realTarget = $candidateFull !== '' ? realpath($candidateFull) : false;
        $realRoot = realpath($scanRoot);
        if ($relItem === '' || str_contains($relItem, '..') || $realTarget === false || !file_exists($realTarget)) {
            $flash = L('Target path does not exist.', 'La ruta objetivo no existe.');
            $flashType = 'j0ui-alert-danger';
        } elseif ($realRoot !== false && (!str_starts_with($realTarget . '/', $realRoot . '/') || $realTarget === $realRoot)) {
            $flash = L('Target path is outside active root.', 'La ruta objetivo está fuera de la raíz activa.');
            $flashType = 'j0ui-alert-danger';
        } else {
            $base = basename($realTarget);
            $discardPrefix = "_\u{1F5D1}";
            if (str_starts_with($base, $discardPrefix)) {
                $flash = L('Already discarded: {0}', 'Ya descartado: {0}', [$base]);
                $flashType = 'j0ui-alert-warning';
            } else {
                $dstFull = dirname($realTarget) . '/' . $discardPrefix . $base;
                if (file_exists($dstFull)) {
                    $info = pathinfo($realTarget);
                    $ext = isset($info['extension']) && $info['extension'] !== '' ? ('.' . $info['extension']) : '';
                    $dstFull = dirname($realTarget) . '/' . $discardPrefix . $info['filename'] . '_' . date('Y-m-d\TH-i-s') . $ext;
                }
                if (!@rename($realTarget, $dstFull)) {
                    $flash = L('Failed to discard item.', 'Error al descartar el elemento.');
                    $flashType = 'j0ui-alert-danger';
                } else {
                    $flash = L('Discarded: {0}', 'Descartado: {0}', [basename($dstFull)]);
                    $flashType = 'j0ui-alert-success';
                }
            }
        }
    }
}

$pathLevels = [];
if ($browseRoot === '/') {
    $pathLevels[] = ['path' => '/', 'label' => '/'];
} else {
    $parts = explode('/', trim($browseRoot, '/'));
    $accum = '';
    foreach ($parts as $idx => $seg) {
        $accum .= '/' . $seg;
        $indent = str_repeat('  ', $idx);
        $pathLevels[] = ['path' => $accum, 'label' => $indent . $seg];
    }
}

$shallowItems = [];
$purgeScan = ['discard' => [], 'backup' => [], 'disabled' => [], 'os_junk' => []];
$restoreScan = ['batches' => [], 'total_files' => 0];
if ($isShallowMode) {
    $scan = ['tree' => [], 'counts' => [], 'total_files' => 0, 'included_files' => 0];
    $rawEntries = @scandir($browseRoot);
    if (is_array($rawEntries)) {
        $dirs = [];
        $files = [];
        foreach ($rawEntries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = rtrim($browseRoot, '/') . '/' . $entry;
            if (is_dir($full)) {
                $dirs[] = $entry;
            } else {
                $files[] = $entry;
            }
        }
        natcasesort($dirs);
        natcasesort($files);
        foreach ($dirs as $d) {
            $shallowItems[] = ['name' => $d, 'is_dir' => true];
        }
        foreach ($files as $f) {
            $shallowItems[] = ['name' => $f, 'is_dir' => false];
        }
    }
} else {
    $scan = DumperScanner::scan($browseRoot, $dumperConfig, $scopeRel);
    $purgeScan = PurgeManager::scan($browseRoot, $prefixCfg, $scopeRel);
    $restoreScan = RestoreManager::scan($browseRoot, $scopeRel);
}

$projectName = basename($browseRoot) ?: 'project';
$downloadName = "_{$projectName}-" . date('Y-m-d--H-i-s') . '.dump.txt';
$fullProjectUrl = (!$isShallowMode && $scopeRel !== '') ? '?scope=root' : null;

ai0_render_head('ai0 // Files', $ctx, 'files', $debug);
if ($flash !== null) {
    echo '<div class="j0ui-alert-inline ' . htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') . '</div>';
}
dumper_render_view([
    'scan'            => $scan,
    'categories'      => $dumperConfig['categories'],
    'dumpOutput'      => $dumpOutput,
    'downloadName'    => $downloadName,
    'compact'         => $compact,
    'scopeRel'        => $scopeRel,
    'fullProjectUrl'  => $fullProjectUrl,
    'pathLevels'      => $pathLevels,
    'browseRoot'      => $browseRoot,
    'canRevealFinder' => PHP_OS_FAMILY === 'Darwin',
    'icons'           => $iconsCfg,
    'apiUrl'          => ai0_url($ctx, 'tools/files/api.php'),
    'tempDir'         => (string) ($ctx['temp_dir'] ?? ''),
    'defaultRoot'     => $defaultRoot,
    'isShallowMode'   => $isShallowMode,
    'explicitScan'    => $explicitScan,
    'shallowItems'    => $shallowItems,
    'purgeScan'       => $purgeScan,
    'restoreScan'     => $restoreScan,
    'activeTab'       => $activeTab,
    'hasExplicitTab'  => $hasExplicitTab,
    'highlightedRel'  => $navHighlightedRel !== '' ? $navHighlightedRel : (string) ($_POST['rel_path'] ?? ''),
]);
ai0_render_foot($ctx, $debug);
