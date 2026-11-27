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
        foreach ((array) $sources as $src) {
            $srcClean = rtrim(str_replace($bs, '/', (string) $src), '/');
            if (is_file($srcClean) && str_ends_with(strtolower($srcClean), '.' . $extClean)) {
                if (!isset($excludeLookup[$srcClean])) {
                    $files[] = $srcClean;
                }
            } elseif (is_dir($srcClean)) {
                $files = array_merge($files, j0_scan_files($srcClean, [$extClean], ['_', '.'], $excludePaths));
            }
        }
        $files = array_values(array_unique($files));
        j0_asset_deps($files);

        $sigEntries = [];
        foreach ($files as $file) {
            $sigEntries[] = $file . '|' . (int) @filemtime($file) . '|' . (int) @filesize($file);
        }
        $currentSig = sha1(implode("\n", $sigEntries));
        $cachedSig = is_file($sigFile) ? trim((string) @file_get_contents($sigFile)) : '';

        $canWrite = is_dir(dirname($minOut)) ? is_writable(dirname($minOut)) : is_writable(dirname($minOut, 2));
        $needsBuild = ($files !== []) && (!is_file($minOut) || $cachedSig !== $currentSig);

        if ($needsBuild && $canWrite) {
            if (!function_exists('j0_build_bundle')) {
                $bundlerFile = dirname(__DIR__) . '/0190-bundler/p0040-build.php';
                if (is_file($bundlerFile)) {
                    require_once $bundlerFile;
                }
            }
            if (function_exists('j0_build_bundle')) {
                $buildRes = j0_build_bundle(array_merge([
                    'sources'  => $sources,
                    'out_file' => $rawOut,
                    'ext'      => $extClean,
                    'minify'   => true,
                    'backup'   => !(function_exists('j0_is_temp_path') && j0_is_temp_path($rawOut)),
                    'exclude'  => $excludePaths,
                ], $buildOptions));
                if (!empty($buildRes['ok'])) {
                    @file_put_contents($sigFile, $currentSig . "\n", LOCK_EX);
                }
            }
        }

        if ((!$debug || $files === []) && is_file($minOut)) {
            $ver = (int) filemtime($minOut);
            $href = htmlspecialchars($webPrefix . basename($minOut) . '?v=' . $ver, ENT_QUOTES, 'UTF-8');
            return $extClean === 'css'
                ? "<link rel=\"stylesheet\" href=\"{$href}\">"
                : "<script src=\"{$href}\"></script>";
        }

        $outDir = rtrim(str_replace($bs, '/', dirname($outClean)), '/');
        $j0Real = defined('J0_DIR') ? rtrim(str_replace($bs, '/', (string) J0_DIR), '/') : str_replace($bs, '/', dirname(__DIR__, 2));
        $j0Alias = null;
        $cursor = $outDir;
        for ($lvl = 0; $lvl <= 8; $lvl++) {
            foreach (['j0', '_j0', 'ai0/j0', '_ai0/j0'] as $candAlias) {
                if (is_dir($cursor . '/' . $candAlias)) {
                    $j0Alias = $cursor . '/' . $candAlias;
                    break 2;
                }
            }
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                break;
            }
            $cursor = $parent;
        }

        $tags = [];
        foreach ($files as $absFile) {
            $normFile = str_replace($bs, '/', $absFile);
            if ($j0Alias !== null && str_starts_with($normFile, $j0Real . '/')) {
                $normFile = $j0Alias . substr($normFile, strlen($j0Real));
            }

            if (str_starts_with($webPrefix, '/') && function_exists('j0_path_to_web_url')) {
                $mappedUrl = j0_path_to_web_url($normFile);
                if ($mappedUrl !== null) {
                    $ver = (int) @filemtime($absFile);
                    $url = htmlspecialchars($mappedUrl . '?v=' . $ver, ENT_QUOTES, 'UTF-8');
                    $tags[] = $extClean === 'css'
                        ? "<link rel=\"stylesheet\" href=\"{$url}\">"
                        : "<script src=\"{$url}\"></script>";
                    continue;
                }
            }

            $fromParts = $outDir === '' ? [] : explode('/', trim($outDir, '/'));
            $toParts = explode('/', trim($normFile, '/'));
            $common = 0;
            $limit = min(count($fromParts), count($toParts));
            while ($common < $limit && $fromParts[$common] === $toParts[$common]) {
                $common++;
            }
            $upCount = count($fromParts) - $common;
            $relFromOut = str_repeat('../', $upCount) . implode('/', array_slice($toParts, $common));

            $schemePrefix = '';
            $pathToClean = $webPrefix . $relFromOut;
            if (preg_match('#^(https?://[^/]+|//[^/]+)(.*)$#i', $pathToClean, $urlMatch)) {
                $schemePrefix = $urlMatch[1];
                $pathToClean = $urlMatch[2];
            }
            $rawSegments = explode('/', $pathToClean);
            $cleanSegments = [];
            foreach ($rawSegments as $seg) {
                if ($seg === '' || $seg === '.') {
                    continue;
                }
                if ($seg === '..' && $cleanSegments !== [] && end($cleanSegments) !== '..') {
                    array_pop($cleanSegments);
                } else {
                    $cleanSegments[] = $seg;
                }
            }
            $leadingSlash = ($schemePrefix !== '' || str_starts_with($pathToClean, '/')) ? '/' : '';
            $finalRel = $schemePrefix . $leadingSlash . implode('/', $cleanSegments);

            $ver = (int) @filemtime($absFile);
            $url = htmlspecialchars($finalRel . '?v=' . $ver, ENT_QUOTES, 'UTF-8');
            $tags[] = $extClean === 'css'
                ? "<link rel=\"stylesheet\" href=\"{$url}\">"
                : "<script src=\"{$url}\"></script>";
        }

        return implode("\n", $tags);
    }
}