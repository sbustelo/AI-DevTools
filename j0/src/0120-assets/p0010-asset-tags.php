<?php
// PATH: j0/src/0120-assets/p0010-asset-tags.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/j0-prefix.php';
require_once dirname(__DIR__, 2) . '/core/j0-scan.php';

if (!function_exists('j0_asset_deps')) {
    /**
     * Internal accumulator for scanned assets, used by j0_static_html for cache invalidation.
     *
     * @param array<int, string>|null $appendFiles
     * @return array<int, string>
     */
    function j0_asset_deps(?array $appendFiles = null): array
    {
        static $deps = [];
        if ($appendFiles !== null) {
            foreach ($appendFiles as $file) {
                $deps[$file] = true;
            }
        }
        return array_keys($deps);
    }
}

if (!function_exists('j0_asset_tags')) {
    /**
     * Compiles bundles on demand when stale and emits either a single .min tag or
     * deep relative debug tags preserving arbitrary nested directory hierarchies.
     *
     * @param string|array<int, string> $sources File(s) or folder(s) to scan.
     * @param array<string, mixed> $buildOptions
     */
    function j0_asset_tags(
        string|array $sources,
        string $outBase,
        string $webBase,
        string $ext,
        bool $debug = false,
        array $buildOptions = []
    ): string {
        $bs = chr(92);
        $extClean = strtolower(ltrim($ext, '.'));
        $outClean = str_replace($bs, '/', $outBase);
        $rawOut = "{$outClean}.{$extClean}";
        $minOut = "{$outClean}.min.{$extClean}";
        $sigFile = dirname($outClean) . '/.' . basename($outClean) . '.' . $extClean . '.sig';
        $webClean = rtrim(str_replace($bs, '/', $webBase), '/');
        $webPrefix = $webClean === '' ? '' : $webClean . '/';
        $excludePaths = [$rawOut, $minOut];
        $excludeLookup = array_flip($excludePaths);

        $files = [];
        $sourcesComplete = true;
        foreach ((array) $sources as $src) {
            if (!is_string($src) || trim($src) === '') {
                $sourcesComplete = false;
                continue;
            }
            $srcClean = rtrim(str_replace($bs, '/', $src), '/');
            if (is_file($srcClean) && str_ends_with(strtolower($srcClean), '.' . $extClean)) {
                if (!isset($excludeLookup[$srcClean])) {
                    $files[] = $srcClean;
                }
            } elseif (is_dir($srcClean)) {
                $scanned = j0_scan_files($srcClean, [$extClean], ['_', '.'], $excludePaths);
                if ($scanned === []) {
                    $sourcesComplete = false;
                }
                $files = array_merge($files, $scanned);
            } else {
                $sourcesComplete = false;
            }
        }
        $files = array_values(array_unique($files));
        j0_asset_deps($files);

        $sigEntries = [];
        foreach ($files as $file) {
            $sigEntries[] = $file . '|' . (int) @filemtime($file) . '|' . (int) @filesize($file);
        }
        $inputsHash = sha1(implode("\n", $sigEntries));
        $minStat = is_file($minOut) ? ((int) @filemtime($minOut) . ':' . (int) @filesize($minOut)) : 'missing';
        $currentSig = $inputsHash . '@' . $minStat;
        $cachedSig = is_file($sigFile) ? trim((string) @file_get_contents($sigFile)) : '';

        $outParent = dirname($minOut);
        $canWrite = is_dir($outParent) ? is_writable($outParent) : is_writable(dirname($outParent));
        $needsBuild = $sourcesComplete && ($files !== []) && (!is_file($minOut) || ($debug && !is_file($rawOut)) || $cachedSig !== $currentSig);
        $isDistDir = strtolower(basename($outParent)) === 'dist';
        $defaultBackup = !($isDistDir || (function_exists('j0_is_temp_path') && j0_is_temp_path($rawOut)));

        if ($needsBuild && $canWrite) {
            if (!is_dir($outParent)) {
                @mkdir($outParent, 0775, true);
            }
            if (!function_exists('j0_build_bundle')) {
                $bundlerFile = dirname(__DIR__) . '/0190-bundler/p0040-build.php';
                if (is_file($bundlerFile)) {
                    require_once $bundlerFile;
                }
            }
            if (function_exists('j0_build_bundle')) {
                $validSources = array_values(array_filter((array) $sources, static fn($s): bool => is_string($s) && trim($s) !== ''));
                $buildRes = j0_build_bundle(array_merge([
                    'sources'  => $validSources,
                    'out_file' => $rawOut,
                    'ext'      => $extClean,
                    'minify'   => true,
                    'backup'   => $defaultBackup,
                    'exclude'  => $excludePaths,
                ], $buildOptions));
                if (!empty($buildRes['ok'])) {
                    clearstatcache(true, $minOut);
                    clearstatcache(true, $rawOut);
                    $builtStat = is_file($minOut) ? ((int) @filemtime($minOut) . ':' . (int) @filesize($minOut)) : 'missing';
                    @file_put_contents($sigFile, $inputsHash . '@' . $builtStat . "\n", LOCK_EX);
                }
            }
        }

        if ((!$debug || !$sourcesComplete || $files === []) && is_file($minOut)) {
            $ver = (int) filemtime($minOut);
            $href = htmlspecialchars($webPrefix . basename($minOut) . '?v=' . $ver, ENT_QUOTES, 'UTF-8');
            return $extClean === 'css'
                ? "<link rel=\"stylesheet\" href=\"{$href}\">"
                : "<script src=\"{$href}\"></script>";
        }

        // Debug mode: emit individual tags ONLY if every source file is web-reachable under DOCUMENT_ROOT.
        // When j0 lives outside DOCUMENT_ROOT (e.g. sibling folder above vhost), serve unminified dist/<name>.<ext> instead of 404 links.
        $tags = [];
        $allWebReachable = function_exists('j0_path_to_web_url') && isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '';
        if ($allWebReachable) {
            foreach ($files as $absFile) {
                $normFile = function_exists('j0_logical_path') ? j0_logical_path($absFile) : str_replace($bs, '/', $absFile);
                $mappedUrl = j0_path_to_web_url($normFile);
                if ($mappedUrl === null) {
                    $allWebReachable = false;
                    break;
                }
                $ver = (int) @filemtime($absFile);
                $url = htmlspecialchars($mappedUrl . '?v=' . $ver, ENT_QUOTES, 'UTF-8');
                $tags[] = $extClean === 'css'
                    ? "<link rel=\"stylesheet\" href=\"{$url}\">"
                    : "<script src=\"{$url}\"></script>";
            }
        }

        if ($allWebReachable && $tags !== []) {
            return implode("\n", $tags);
        }

        if (is_file($rawOut)) {
            $ver = (int) filemtime($rawOut);
            $href = htmlspecialchars($webPrefix . basename($rawOut) . '?v=' . $ver, ENT_QUOTES, 'UTF-8');
            return $extClean === 'css'
                ? "<link rel=\"stylesheet\" href=\"{$href}\">"
                : "<script src=\"{$href}\"></script>";
        }

        if (is_file($minOut)) {
            $ver = (int) filemtime($minOut);
            $href = htmlspecialchars($webPrefix . basename($minOut) . '?v=' . $ver, ENT_QUOTES, 'UTF-8');
            return $extClean === 'css'
                ? "<link rel=\"stylesheet\" href=\"{$href}\">"
                : "<script src=\"{$href}\"></script>";
        }

        return '';
    }
}