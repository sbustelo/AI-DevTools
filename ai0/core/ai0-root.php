<?php
// PATH: ai0/core/ai0-root.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit('Direct access forbidden.');
}

/**
 * Walks upward from $dir looking for .ai0root.json (or falls back to DOCUMENT_ROOT).
 *
 * @param array<string, mixed> $iconsCfg
 * @return array{dir: string, file: string|null, data: array<string, mixed>}|null
 */
function ai0_find_root_marker(string $dir, array $iconsCfg = []): ?array
{
    $bs = chr(92);
    $cursor = rtrim(str_replace($bs, '/', $dir), '/');
    if ($cursor === '') {
        $cursor = '/';
    }

    for ($i = 0; $i <= 12; $i++) {
        $candidate = ($cursor === '/' ? '' : $cursor) . '/.ai0root.json';
        if (is_file($candidate) && is_readable($candidate)) {
            $decoded = json_decode((string) file_get_contents($candidate), true);
            if (is_array($decoded) && (!isset($decoded['is_project_root']) || $decoded['is_project_root'] !== false)) {
                return [
                    'dir'  => $cursor,
                    'file' => $candidate,
                    'data' => $decoded,
                ];
            }
        }
        $parent = dirname($cursor);
        if ($parent === $cursor) {
            break;
        }
        $cursor = $parent;
    }

    return null;
}

/**
 * Resolves a filename icon (svg/png/webp) to its public web URL across ai0 and user icon folders.
 */
function ai0_resolve_icon_url(string $iconValue, string $projectRoot = '', string $ai0Dir = ''): ?string
{
    $clean = trim($iconValue);
    if ($clean === '' || preg_match('/^[a-zA-Z0-9._\/-]+\.(svg|png|webp|jpg|jpeg|gif)$/i', $clean) !== 1) {
        return null;
    }

    $bs = chr(92);
    $ai0Base = $ai0Dir !== '' ? rtrim(str_replace($bs, '/', $ai0Dir), '/') : str_replace($bs, '/', dirname(__DIR__));
    $rootBase = $projectRoot !== '' ? rtrim(str_replace($bs, '/', $projectRoot), '/') : '';

    $dirs = array_filter([
        $rootBase !== '' ? ($rootBase . '/_ai0src/assets/icons') : '',
        $rootBase !== '' ? ($rootBase . '/ai0src/assets/icons') : '',
        $ai0Base . '/ai0src/assets/icons',
    ]);

    foreach ($dirs as $dir) {
        $abs = $dir . '/' . basename($clean);
        if (is_file($abs) && function_exists('j0_path_to_web_url')) {
            $url = j0_path_to_web_url($abs);
            if ($url !== null) {
                return $url;
            }
        }
    }

    return null;
}

/**
 * Registers a declared .ai0root.json directory in <temp_dir>/ai0-roots.json.
 */
function ai0_register_declared_root(string $dir, string $tempDir): void
{
    $bs = chr(92);
    $cleanDir = rtrim(str_replace($bs, '/', $dir), '/');
    $cleanTemp = rtrim(str_replace($bs, '/', $tempDir), '/');
    if ($cleanDir === '' || $cleanTemp === '') {
        return;
    }
    if (!is_dir($cleanTemp) && !@mkdir($cleanTemp, 0775, true)) {
        return;
    }
    $regFile = $cleanTemp . '/ai0-roots.json';
    $list = is_file($regFile) ? (json_decode((string) file_get_contents($regFile), true) ?: []) : [];
    if (!is_array($list)) {
        $list = [];
    }
    if (!in_array($cleanDir, $list, true)) {
        $list[] = $cleanDir;
        sort($list, SORT_NATURAL);
        @file_put_contents($regFile, json_encode(array_values($list), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    }
}

/**
 * Returns all existing declared project roots (those with .ai0root.json).
 *
 * @return array<int, array{path: string, label: string, icon: string}>
 */
function ai0_list_declared_roots(string $tempDir, string $defaultRoot = ''): array
{
    $bs = chr(92);
    $cleanTemp = rtrim(str_replace($bs, '/', $tempDir), '/');
    $regFile = $cleanTemp . '/ai0-roots.json';
    $raw = is_file($regFile) ? (json_decode((string) file_get_contents($regFile), true) ?: []) : [];
    if (!is_array($raw)) {
        $raw = [];
    }
    if ($defaultRoot !== '' && is_dir($defaultRoot) && !in_array($defaultRoot, $raw, true)) {
        $raw[] = rtrim(str_replace($bs, '/', $defaultRoot), '/');
    }

    $cleanDefault = $defaultRoot !== '' ? rtrim(str_replace($bs, '/', $defaultRoot), '/') : '';
    $out = [];
    $validRegistry = [];
    $pruned = false;

    foreach (array_unique($raw) as $dir) {
        if (!is_string($dir) || !is_dir($dir)) {
            $pruned = true;
            continue;
        }
        $cleanDir = rtrim(str_replace($bs, '/', $dir), '/');
        $isDefault = ($cleanDefault !== '' && $cleanDir === $cleanDefault);
        $markerFile = $cleanDir . '/.ai0root.json';
        $data = is_file($markerFile) ? json_decode((string) file_get_contents($markerFile), true) : null;
        $hasValidMarker = is_array($data) && (!isset($data['is_project_root']) || $data['is_project_root'] !== false);

        if (!$hasValidMarker && !$isDefault) {
            $pruned = true;
            continue;
        }

        if ($hasValidMarker) {
            $validRegistry[] = $cleanDir;
        }

        $safeData = is_array($data) ? $data : [];
        $rawIcon = (string) ($safeData['icon'] ?? '🏠');
        $isAsset = preg_match('/\.(svg|png|webp|jpg|jpeg|gif)$/i', $rawIcon) === 1;
        $out[] = [
            'path'  => $cleanDir,
            'label' => (string) ($safeData['label'] ?? basename($cleanDir) ?: $cleanDir),
            'icon'  => $isAsset ? '📁' : ($rawIcon !== '' ? $rawIcon : '🏠'),
        ];
    }

    if ($pruned && is_file($regFile) && is_writable($regFile)) {
        sort($validRegistry, SORT_NATURAL);
        @file_put_contents($regFile, json_encode(array_values(array_unique($validRegistry)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
    }

    return $out;
}

/**
 * Formats an absolute path relative to the nearest .ai0root.json marker (e.g. 🏠/repo/app).
 *
 * @param array<string, mixed> $iconsCfg
 */
function ai0_format_display_path(string $absPath, array $iconsCfg = []): string
{
    $bs = chr(92);
    $clean = rtrim(str_replace($bs, '/', $absPath), '/');
    if ($clean === '') {
        return '/';
    }

    $marker = ai0_find_root_marker($clean, $iconsCfg);
    if ($marker === null) {
        return $clean;
    }

    $mDir = $marker['dir'];
    $rawIcon = (string) ($marker['data']['icon'] ?? '🏠');
    $isAssetFile = preg_match('/\.(svg|png|webp|jpg|jpeg|gif)$/i', $rawIcon) === 1;
    $prefix = $isAssetFile
        ? ((string) ($marker['data']['label'] ?? basename($mDir) ?: 'root'))
        : ($rawIcon !== '' ? $rawIcon : '🏠');

    if ($clean === $mDir) {
        return $prefix;
    }
    if (str_starts_with($clean . '/', $mDir . '/')) {
        $rel = ltrim(substr($clean, strlen($mDir)), '/');
        return $prefix . '/' . $rel;
    }

    return $clean;
}

/**
 * Parses user-pasted paths: absolute (/Users/...), tilde (~/...), or emoji-prefixed (🏠/...).
 *
 * @param array<string, mixed> $iconsCfg
 */
function ai0_parse_input_path(string $rawInput, string $fallbackDir = '', array $iconsCfg = []): string
{
    $bs = chr(92);
    $trimmed = trim(str_replace($bs, '/', $rawInput));
    if ($trimmed === '') {
        return '';
    }

    if ($trimmed === '~' || str_starts_with($trimmed, '~/')) {
        $home = (string) ($_SERVER['HOME'] ?? getenv('HOME') ?: '');
        if ($home !== '') {
            $homeClean = rtrim(str_replace($bs, '/', $home), '/');
            $trimmed = $trimmed === '~' ? $homeClean : ($homeClean . substr($trimmed, 1));
        }
    } elseif (!str_starts_with($trimmed, '/') && $fallbackDir !== '') {
        $marker = ai0_find_root_marker($fallbackDir, $iconsCfg);
        if ($marker !== null) {
            $icon = (string) ($marker['data']['icon'] ?? '🏠');
            if ($icon !== '' && ($trimmed === $icon || str_starts_with($trimmed, $icon . '/'))) {
                $rest = ltrim(substr($trimmed, strlen($icon)), '/');
                $trimmed = $rest === '' ? $marker['dir'] : ($marker['dir'] . '/' . $rest);
            }
        }
    }

    $clean = rtrim($trimmed, '/');
    return $clean === '' ? '/' : $clean;
}

/**
 * Auto-detects local server mode (Valet .test vhost vs MAMP/generic path) for .ai0root.json.
 *
 * @return array<string, string>
 */
function ai0_autodetect_root_config(string $dir): array
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $isValet = str_ends_with($host, '.test') || str_contains($host, '.test:') || stripos($dir, 'valet') !== false;

    if ($isValet) {
        return [
            'icon'        => '🏠',
            'label'       => basename($dir) ?: 'Valet',
            'server_mode' => 'vhost',
            'tld'         => '.test',
            'base_url'    => '',
        ];
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $host !== '' ? ($scheme . '://' . $host) : 'http://localhost';

    return [
        'icon'        => '🏠',
        'label'       => basename($dir) ?: 'Local',
        'server_mode' => 'path',
        'tld'         => '',
        'base_url'    => $baseUrl,
    ];
}

/**
 * Writes or updates .ai0root.json atomically in $dir.
 *
 * @param array<string, mixed> $overrides
 */
function ai0_write_root_marker(string $dir, array $overrides = [], string $tempDir = ''): bool
{
    $bs = chr(92);
    $cleanDir = rtrim(str_replace($bs, '/', $dir), '/');
    if (!is_dir($cleanDir) || !is_writable($cleanDir)) {
        return false;
    }

    $file = $cleanDir . '/.ai0root.json';
    $existing = [];
    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }

    $base = $existing !== [] ? $existing : ai0_autodetect_root_config($cleanDir);
    $merged = array_merge($base, $overrides);
    $json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

    $tmp = $cleanDir . '/.__ai0root_' . uniqid('', true);
    if (@file_put_contents($tmp, $json) === false || !@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }

    $isProjectRoot = !isset($merged['is_project_root']) || $merged['is_project_root'] !== false;
    $regTemp = $tempDir !== ''
        ? $tempDir
        : (isset($GLOBALS['ai0_context']['root']) ? (rtrim((string) $GLOBALS['ai0_context']['root'], '/') . '/_j0-temp') : '');
    if ($isProjectRoot && $regTemp !== '') {
        ai0_register_declared_root($cleanDir, $regTemp);
    }

    return true;
}