<?php
// PATH: ai0-0010/core/ai0-context.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    define('J0_EXEC', true);
}

require_once dirname(__DIR__) . '/j0/core/j0-closest.php';
require_once dirname(__DIR__) . '/j0/core/j0-config.php';
require_once dirname(__DIR__) . '/j0/core/j0-prefix.php';
require_once dirname(__DIR__) . '/j0/j0-locate.php';

function ai0_guard_localhost(): void
{
    if (!j0_is_local()) {
        http_response_code(403);
        exit('ai0: local environment access only.');
    }
}

/**
 * Resolves project root, active ai0 directory, 4-tier [_]ai0config domains, and _j0-temp vault.
 *
 * @return array{
 *   root: string,
 *   ai0_dir: string,
 *   domains: array<int, string>,
 *   temp_dir: string,
 *   match: string,
 *   base_dir: string,
 *   target_abs: string,
 *   origin_abs: string,
 *   up_rel: string,
 *   down_rel: string,
 *   via_stub: bool
 * }
 */
function ai0_resolve_context(string $fallbackDir): array
{
    ai0_guard_localhost();

    $bs = chr(92);
    $ai0Dir = str_replace($bs, '/', dirname(__DIR__));

    if (isset($GLOBALS['ai0_context']) && is_array($GLOBALS['ai0_context'])) {
        $ctx = $GLOBALS['ai0_context'];
        $ctx['via_stub'] = true;
    } else {
        $scriptFile = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $scriptDir = $scriptFile !== ''
            ? dirname(str_replace($bs, '/', $scriptFile))
            : str_replace($bs, '/', $fallbackDir);

        $found = j0_closest($scriptDir, ['_ai0', 'ai0', 'ai0-0010']);
        if ($found === null) {
            $root = str_replace($bs, '/', dirname(__DIR__, 2));
            $ctx = [
                'root'       => $root,
                'match'      => basename($ai0Dir),
                'base_dir'   => basename($ai0Dir),
                'target_abs' => $scriptDir,
                'origin_abs' => $root,
                'up_rel'     => '../',
                'down_rel'   => '',
                'via_stub'   => false,
            ];
        } else {
            $found['origin_abs'] = $found['root'];
            $found['down_rel'] = '';
            $found['via_stub'] = false;
            $ctx = $found;
        }
    }

    $rootClean = rtrim($ctx['root'], '/');
    $mountPath = $rootClean . '/' . ($ctx['base_dir'] ?? basename($ai0Dir));
    $isContained = !is_link($mountPath)
        && !is_dir($rootClean . '/_ai0config')
        && !is_dir($rootClean . '/ai0config')
        && (is_dir($ai0Dir . '/_ai0config') || is_dir($ai0Dir . '/_j0-temp'));

    $ctx['ai0_dir'] = $ai0Dir;
    $ctx['domains'] = j0_config_domains($rootClean, $ai0Dir);
    $ctx['temp_dir'] = $isContained ? ($ai0Dir . '/_j0-temp') : ($rootClean . '/_j0-temp');
    j0_locate_init($rootClean);

    return $ctx;
}

/**
 * @param array<string, mixed> $ctx
 * @return array<string, mixed>
 */
function ai0_get_state(array $ctx): array
{
    $stateFile = rtrim((string) $ctx['temp_dir'], '/') . '/ai0-state.json';
    $defaults = ['debug' => false];
    if (!is_file($stateFile)) {
        return $defaults;
    }
    $decoded = json_decode((string) file_get_contents($stateFile), true);
    if (!is_array($decoded)) {
        return $defaults;
    }
    return array_merge($defaults, $decoded);
}

/**
 * @param array<string, mixed> $ctx
 * @param array<string, mixed> $state
 */
function ai0_save_state(array $ctx, array $state): bool
{
    $tempDir = rtrim((string) $ctx['temp_dir'], '/');
    if (!is_dir($tempDir) && !mkdir($tempDir, 0775, true) && !is_dir($tempDir)) {
        return false;
    }
    $stateFile = $tempDir . '/ai0-state.json';
    $json = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents($stateFile, (string) $json) !== false;
}

/**
 * Creates a full project ZIP snapshot prefixed with _📦 (mechanical backup/archive).
 *
 * @return array{ok: bool, file: string, message: string}
 */
function ai0_create_zip_snapshot(string $projectRoot): array
{
    $rootClean = rtrim($projectRoot, '/');
    $projectName = basename($rootClean) ?: 'project';
    $stamp = date('Y m d Hi');
    $boxPrefix = "_\u{1F4E6}";
    $baseZip = "{$boxPrefix}{$projectName} {$stamp}.zip";
    $zipPath = $rootClean . '/' . $baseZip;

    $counter = 1;
    while (file_exists($zipPath)) {
        $baseZip = "{$boxPrefix}{$projectName} {$stamp} {$counter}.zip";
        $zipPath = $rootClean . '/' . $baseZip;
        $counter++;
    }

    $ignoreDirs = ['.git', 'node_modules', 'vendor'];

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['ok' => false, 'file' => '', 'message' => 'Failed to initialize ZipArchive.'];
        }

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootClean, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        $baseLen = strlen($rootClean) + 1;

        /** @var SplFileInfo $file */
        foreach ($iter as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $abs = $file->getPathname();
            $rel = substr($abs, $baseLen);
            $firstSeg = explode('/', $rel)[0];
            if (in_array($firstSeg, $ignoreDirs, true)) {
                continue;
            }
            $base = basename($rel);
            if (str_ends_with(strtolower($base), '.zip') && str_starts_with($base, $boxPrefix)) {
                continue;
            }
            $zip->addFile($abs, $rel);
        }
        $zip->close();
        return ['ok' => true, 'file' => $baseZip, 'message' => "Snapshot created: {$baseZip}"];
    }

    $cmd = 'cd ' . escapeshellarg($rootClean)
        . ' && /usr/bin/zip -rq ' . escapeshellarg($zipPath)
        . ' . -x ".git/*" "node_modules/*" "vendor/*" "_*.zip" 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    if ($code !== 0) {
        return ['ok' => false, 'file' => '', 'message' => 'Failed to execute /usr/bin/zip: ' . implode(' ', $out)];
    }
    if (!is_file($zipPath)) {
        return ['ok' => false, 'file' => '', 'message' => 'ZIP snapshot file was not generated.'];
    }
    return ['ok' => true, 'file' => $baseZip, 'message' => "Snapshot created: {$baseZip}"];
}