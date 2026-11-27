<?php
// PATH: ai0/tools/files/api.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    define('J0_EXEC', true);
}

require_once dirname(__DIR__, 2) . '/core/ai0-context.php';
require_once __DIR__ . '/dumper-config.php';

$ctx = ai0_resolve_context(__DIR__);
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'POST required.']);
    exit;
}

$rawBody = (string) file_get_contents('php://input');
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid JSON payload.']);
    exit;
}

$bs = chr(92);
$defaultRoot = rtrim(str_replace($bs, '/', (string) ($ctx['root'] ?? __DIR__)), '/');
$iconsCfg = j0_config_json('icons', $ctx['domains']);
$reqRoot = ai0_parse_input_path((string) ($input['browse_root'] ?? ''), $defaultRoot, $iconsCfg);
$browseRoot = ($reqRoot !== '' && !str_contains($reqRoot, '..') && is_dir($reqRoot)) ? $reqRoot : $defaultRoot;

$relPath = ltrim(str_replace($bs, '/', trim((string) ($input['rel_path'] ?? ''))), '/');
if (str_contains($relPath, '..')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Invalid relative path.']);
    exit;
}

$targetAbs = $relPath === '' ? $browseRoot : (rtrim($browseRoot, '/') . '/' . $relPath);
if (!file_exists($targetAbs)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Target not found.']);
    exit;
}

$isDir = is_dir($targetAbs);
$baseName = basename($targetAbs) ?: '/';
$dumperCfg = dumper_load_config($ctx);
$prefixInfo = function_exists('j0_classify_prefix')
    ? j0_classify_prefix($baseName, (array) ($dumperCfg['prefixes'] ?? []))
    : ['state' => 'active'];
$itemCat = $prefixInfo['state'] !== 'active'
    ? (string) $prefixInfo['state']
    : (str_starts_with($baseName, '.') ? 'hidden_dot' : '');

$targetInfo = [
    'abs'  => $targetAbs,
    'rel'  => $relPath,
    'name' => $baseName,
    'type' => $isDir ? 'dir' : 'file',
    'cat'  => $itemCat,
];

$op = (string) ($input['op'] ?? 'inspect');

/**
 * Maps a 3-bit Unix permission nibble to human-readable Finder-style privilege.
 */
$nibbleToPriv = static function (int $bits): string {
    $r = ($bits & 4) !== 0;
    $w = ($bits & 2) !== 0;
    if ($r && $w) {
        return 'rw';
    }
    if ($r) {
        return 'r';
    }
    return 'none';
};

/**
 * Maps Finder-style privilege back to 3-bit Unix nibble preserving execute bit when applicable.
 */
$privToNibble = static function (string $priv, int $origBits, bool $isDir): int {
    $execBit = $isDir ? 1 : ($origBits & 1);
    if ($priv === 'rw') {
        return 6 | $execBit;
    }
    if ($priv === 'r') {
        return 4 | $execBit;
    }
    return 0;
};

if ($op === 'inspect') {
    $perms = @fileperms($targetAbs);
    $mode = $perms !== false ? ($perms & 0777) : 0644;
    $ownerBits = ($mode >> 6) & 7;
    $groupBits = ($mode >> 3) & 7;
    $otherBits = $mode & 7;

    $sizeBytes = $isDir ? 0 : (int) @filesize($targetAbs);
    $directCount = 0;
    if ($isDir) {
        $entries = @scandir($targetAbs);
        if (is_array($entries)) {
            $directCount = count(array_diff($entries, ['.', '..']));
        }
    }

    $preview = null;
    $binaryExts = is_array($dumperCfg['binary_extensions'] ?? null) ? $dumperCfg['binary_extensions'] : [];
    $ext = strtolower(pathinfo($targetAbs, PATHINFO_EXTENSION));
    if (!$isDir && is_readable($targetAbs) && !in_array($ext, $binaryExts, true)) {
        $fh = @fopen($targetAbs, 'rb');
        if (is_resource($fh)) {
            $chunk = (string) @fread($fh, 2048);
            @fclose($fh);
            if (!str_contains($chunk, "\0")) {
                $preview = $chunk;
            }
        }
    }

    $marker = ai0_find_root_marker($targetAbs, $iconsCfg);
    $itemIconSpec = function_exists('ai0_resolve_icon')
        ? ai0_resolve_icon($targetAbs, $ctx, $isDir ? 'dir' : 'file')
        : ['value' => $isDir ? '📁' : '📄', 'url' => ''];
    $rootIcon = (string) ($itemIconSpec['value'] ?? '');
    $rootIconUrl = ($itemIconSpec['url'] ?? '') !== '' ? (string) $itemIconSpec['url'] : null;

    $actions = [];
    foreach (ai0_discover_actions($ctx, $targetInfo) as $act) {
        if (($act['type'] ?? '') === 'separator') {
            $actions[] = ['type' => 'separator', 'id' => $act['id']];
            continue;
        }
        $rawLabel = $act['label'] ?? $act['id'];
        $labelStr = is_array($rawLabel) ? L($rawLabel) : (string) $rawLabel;
        $promptStr = is_array($act['prompt'] ?? null) ? L($act['prompt']) : (is_string($act['prompt'] ?? null) ? $act['prompt'] : '');
        $confirmStr = is_array($act['confirm'] ?? null) ? L($act['confirm']) : (is_string($act['confirm'] ?? null) ? $act['confirm'] : '');
        $currentColor = '';
        if ($isDir && is_file($targetAbs . '/.ai0root.json')) {
            $mData = json_decode((string) file_get_contents($targetAbs . '/.ai0root.json'), true);
            if (is_array($mData) && isset($mData['color'])) {
                $currentColor = strtolower((string) $mData['color']);
            }
        }

        $submenuOut = null;
        if (is_array($act['submenu'] ?? null)) {
            $submenuOut = [];
            foreach ($act['submenu'] as $sub) {
                $subLbl = is_array($sub['label'] ?? null) ? L($sub['label']) : (string) ($sub['label'] ?? '');
                $subVal = (string) ($sub['value'] ?? '');
                $submenuOut[] = [
                    'value'   => $subVal,
                    'icon'    => (string) ($sub['icon'] ?? ''),
                    'label'   => $subLbl,
                    'checked' => $subVal === $currentColor,
                ];
            }
        }

        $actions[] = [
            'type'     => 'action',
            'id'       => $act['id'],
            'label'    => $labelStr,
            'icon'     => $act['icon'],
            'icon_url' => (string) ($act['icon_url'] ?? ''),
            'class'    => $act['class'],
            'danger'   => !empty($act['danger']),
            'prompt'   => $promptStr,
            'confirm'  => $confirmStr,
            'submenu'  => $submenuOut,
        ];
    }

    $markerDir = $marker !== null ? $marker['dir'] : '';
    $markerRawIcon = $marker !== null ? (string) ($marker['data']['icon'] ?? '') : '';
    $markerIsAsset = preg_match('/\.(svg|png|webp|jpg|jpeg|gif)$/i', $markerRawIcon) === 1;
    $markerIconUrl = ($markerIsAsset && $markerRawIcon !== '')
        ? (ai0_resolve_icon_url($markerRawIcon, $defaultRoot, (string) ($ctx['ai0_dir'] ?? '')) ?? '')
        : '';
    $markerTag = $marker !== null
        ? ($markerIsAsset ? ((string) ($marker['data']['label'] ?? basename($markerDir) ?: 'root')) : ($markerRawIcon !== '' ? $markerRawIcon : '🏠'))
        : '';
    $relFromMarker = ($markerDir !== '' && str_starts_with($targetAbs . '/', $markerDir . '/'))
        ? ltrim(substr($targetAbs, strlen($markerDir)), '/')
        : '';

    echo json_encode([
        'ok'              => true,
        'name'            => $baseName,
        'rel'             => $relPath,
        'abs_path'        => $targetAbs,
        'display_path'    => ai0_format_display_path($targetAbs, $iconsCfg),
        'marker_dir'      => $markerDir,
        'marker_tag'      => $markerTag,
        'marker_icon_url' => $markerIconUrl,
        'rel_from_marker' => $relFromMarker,
        'type'            => $isDir ? 'dir' : 'file',
        'size'            => $sizeBytes,
        'mtime'           => (int) @filemtime($targetAbs),
        'mtime_human'     => date('Y-m-d H:i', (int) @filemtime($targetAbs)),
        'direct_items'    => $directCount,
        'is_root_dir'     => $relPath === '',
        'root_icon'       => $rootIcon,
        'root_icon_url'   => $rootIconUrl,
        'permissions'   => [
            'owner'    => $nibbleToPriv($ownerBits),
            'group'    => $nibbleToPriv($groupBits),
            'everyone' => $nibbleToPriv($otherBits),
        ],
        'preview'      => $preview,
        'truncated'    => !$isDir && $sizeBytes > 2048,
        'actions'      => $actions,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($op === 'update_attr') {
    $field = (string) ($input['field'] ?? '');

    if ($field === 'name') {
        $res = ai0_run_action('rename', $ctx, $targetInfo, ['new_name' => (string) ($input['value'] ?? '')]);
        echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($field === 'permissions') {
        $perms = @fileperms($targetAbs);
        $origMode = $perms !== false ? ($perms & 0777) : ($isDir ? 0755 : 0644);
        $o = $privToNibble((string) ($input['owner'] ?? 'rw'), ($origMode >> 6) & 7, $isDir);
        $g = $privToNibble((string) ($input['group'] ?? 'r'), ($origMode >> 3) & 7, $isDir);
        $e = $privToNibble((string) ($input['everyone'] ?? 'r'), $origMode & 7, $isDir);
        $newMode = ($o << 6) | ($g << 3) | $e;
        if (!@chmod($targetAbs, $newMode)) {
            echo json_encode(['ok' => false, 'message' => L('Failed to update permissions.', 'Error al actualizar permisos.')]);
            exit;
        }
        echo json_encode(['ok' => true, 'message' => L('Permissions updated.', 'Permisos actualizados.')]);
        exit;
    }

    if ($field === 'root_icon' && $isDir) {
        $nextIcon = trim((string) ($input['value'] ?? ''));
        $overrides = ['icon' => $nextIcon];
        if ($relPath !== '' && !is_file($targetAbs . '/.ai0root.json')) {
            $overrides['is_project_root'] = false;
        }
        $ok = ai0_write_root_marker($targetAbs, $overrides, (string) ($ctx['temp_dir'] ?? ''));
        $resolvedIcon = function_exists('ai0_resolve_icon')
            ? ai0_resolve_icon($targetAbs, $ctx, 'dir')
            : ['value' => $nextIcon !== '' ? $nextIcon : '📁', 'url' => ''];
        echo json_encode([
            'ok'       => $ok,
            'reload'   => false,
            'rel'      => $relPath,
            'abs_path' => $targetAbs,
            'icon'     => (string) ($resolvedIcon['value'] ?? '📁'),
            'icon_url' => (string) ($resolvedIcon['url'] ?? ''),
            'message'  => $ok
                ? ($nextIcon === '' ? L('Icon cleared.', 'Ícono eliminado.') : L('Icon saved.', 'Ícono guardado.'))
                : L('Failed to write .ai0root.json.', 'Error al escribir .ai0root.json.'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Unknown attribute field.']);
    exit;
}

if ($op === 'list_icons') {
    $items = [
        ['value' => '🏠', 'label' => 'home / valet'],
        ['value' => '🗄️', 'label' => 'server / cabinet'],
        ['value' => '🗂️', 'label' => 'workspace / folders'],
        ['value' => '🗃️', 'label' => 'archive / box'],
        ['value' => '🚀', 'label' => 'project / launch'],
        ['value' => '⚙️', 'label' => 'engine / core'],
        ['value' => '🧪', 'label' => 'lab / sandbox'],
        ['value' => '📦', 'label' => 'package / lib'],
        ['value' => '🌐', 'label' => 'web / site'],
        ['value' => '🛠️', 'label' => 'tools / dev'],
    ];

    $iconDirs = [
        rtrim((string) ($ctx['ai0_dir'] ?? dirname(__DIR__, 2)), '/') . '/ai0src/assets/icons',
        rtrim($defaultRoot, '/') . '/ai0src/assets/icons',
        rtrim($defaultRoot, '/') . '/_ai0src/assets/icons',
    ];

    foreach ($iconDirs as $iDir) {
        if (!is_dir($iDir)) {
            continue;
        }
        $entries = @scandir($iDir);
        if (!is_array($entries)) {
            continue;
        }
        sort($entries, SORT_NATURAL);
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($ext, ['svg', 'png', 'webp'], true)) {
                continue;
            }
            $absFile = $iDir . '/' . $entry;
            $webUrl = function_exists('j0_path_to_web_url') ? j0_path_to_web_url($absFile) : null;
            $items[] = [
                'value' => $entry,
                'label' => pathinfo($entry, PATHINFO_FILENAME),
                'url'   => $webUrl ?? '',
            ];
        }
    }

    echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($op === 'upload_icon') {
    $rawName = basename(trim((string) ($input['filename'] ?? '')));
    $ext = strtolower(pathinfo($rawName, PATHINFO_EXTENSION));
    $dataUrl = (string) ($input['data_url'] ?? '');

    if (!in_array($ext, ['svg', 'png', 'webp'], true) || !str_contains($dataUrl, ',')) {
        echo json_encode(['ok' => false, 'message' => 'Only SVG, PNG, or WEBP files are allowed.']);
        exit;
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '-', $rawName) ?: ('icon.' . $ext);
    $encoded = explode(',', $dataUrl, 2)[1];
    $binary = base64_decode($encoded, true);
    if (!is_string($binary) || strlen($binary) === 0 || strlen($binary) > 262144) {
        echo json_encode(['ok' => false, 'message' => 'Invalid or oversized icon (max 256 KB).']);
        exit;
    }

    $destDir = rtrim($defaultRoot, '/') . '/_ai0src/assets/icons';
    if (!is_dir($destDir) && !@mkdir($destDir, 0775, true)) {
        echo json_encode(['ok' => false, 'message' => 'Failed to create _ai0src/assets/icons directory.']);
        exit;
    }

    $destFile = $destDir . '/' . $safeBase;
    if (@file_put_contents($destFile, $binary) === false) {
        echo json_encode(['ok' => false, 'message' => 'Failed to save uploaded icon.']);
        exit;
    }

    $webUrl = function_exists('j0_path_to_web_url') ? j0_path_to_web_url($destFile) : '';
    echo json_encode([
        'ok'   => true,
        'item' => [
            'value' => $safeBase,
            'label' => pathinfo($safeBase, PATHINFO_FILENAME),
            'url'   => $webUrl ?? '',
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($op === 'run_action') {
    $actionId = (string) ($input['action_id'] ?? '');
    $params = is_array($input['params'] ?? null) ? $input['params'] : [];
    $res = ai0_run_action($actionId, $ctx, $targetInfo, $params);
    echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'message' => 'Unsupported operation.']);