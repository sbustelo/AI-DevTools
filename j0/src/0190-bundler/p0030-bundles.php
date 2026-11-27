<?php
// PATH: ai0/j0/src/0190-bundler/p0030-bundles.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/j0-scan.php';
require_once dirname(__DIR__, 2) . '/core/j0-prefix.php';
require_once dirname(__DIR__, 2) . '/core/j0-locate.php';
require_once __DIR__ . '/p0040-build.php';

if (!function_exists('j0_discover_bundles')) {
    /**
     * Discovers root-level asset bundles (folders or standalone files) in $rootDir.
     *
     * @param array<string, mixed> $options
     * @return array<int, array{
     *   name: string,
     *   sem_id: string,
     *   tier: string,
     *   tags: array<int, string>,
     *   is_admin: bool,
     *   path: string,
     *   out_base: string,
     *   raw_file: string,
     *   min_file: string,
     *   sources: array<int, string>,
     *   missing: bool,
     *   empty: bool
     * }>
     */
    function j0_discover_bundles(string $rootDir, string $ext, array $options = []): array
    {
        $bs = chr(92);
        $rootClean = rtrim(str_replace($bs, '/', $rootDir), '/');
        if (!is_dir($rootClean)) {
            return [];
        }

        $extClean = strtolower(ltrim($ext, '.'));
        $sortMode = (string) ($options['sort'] ?? 'tree');
        $entries = @scandir($rootClean);
        if (!is_array($entries)) {
            return [];
        }
        sort($entries, SORT_NATURAL);

        $dirSet = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $prefixMeta = j0_classify_prefix($entry);
            if ($prefixMeta['excluded_by_default']) {
                continue;
            }
            $abs = $rootClean . '/' . $entry;
            if (is_dir($abs)) {
                $dirSet[$entry] = true;
            }
        }

        $bundles = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $prefixMeta = j0_classify_prefix($entry);
            if ($prefixMeta['excluded_by_default']) {
                continue;
            }

            $abs = $rootClean . '/' . $entry;
            $isBrokenLink = is_link($abs) && !file_exists($abs);

            if ($isBrokenLink || is_dir($abs)) {
                $bundleName = $entry;
                $meta = j0_locate_parse_module($bundleName);
                $sources = [];
                if (!$isBrokenLink) {
                    foreach (j0_scan_files($abs, [$extClean], ['_', '.'], [], $sortMode) as $srcFile) {
                        if (str_ends_with(strtolower($srcFile), '.min.' . $extClean)) {
                            continue;
                        }
                        $sources[] = $srcFile;
                    }
                }
                $outBase = $rootClean . '/' . $bundleName;
                $bundles[] = [
                    'name'     => $bundleName,
                    'sem_id'   => $meta['sem_id'],
                    'tier'     => $meta['tier'],
                    'tags'     => $meta['tags'],
                    'is_admin' => in_array('isadmin', $meta['tags'], true),
                    'path'     => $abs,
                    'out_base' => $outBase,
                    'raw_file' => $outBase . '.' . $extClean,
                    'min_file' => $outBase . '.min.' . $extClean,
                    'sources'  => $sources,
                    'missing'  => $isBrokenLink,
                    'empty'    => !$isBrokenLink && $sources === [],
                ];
                continue;
            }

            if (!is_file($abs)) {
                continue;
            }

            $lower = strtolower($entry);
            if (!str_ends_with($lower, '.' . $extClean) || str_ends_with($lower, '.min.' . $extClean)) {
                continue;
            }

            $bundleName = substr($entry, 0, -(strlen($extClean) + 1));
            if ($bundleName === '' || isset($dirSet[$bundleName])) {
                continue;
            }

            $meta = j0_locate_parse_module($bundleName);
            $outBase = $rootClean . '/' . $bundleName;
            $bundles[] = [
                'name'     => $bundleName,
                'sem_id'   => $meta['sem_id'],
                'tier'     => $meta['tier'],
                'tags'     => $meta['tags'],
                'is_admin' => in_array('isadmin', $meta['tags'], true),
                'path'     => $abs,
                'out_base' => $outBase,
                'raw_file' => $outBase . '.' . $extClean,
                'min_file' => $outBase . '.min.' . $extClean,
                'sources'  => [$abs],
                'missing'  => false,
                'empty'    => false,
            ];
        }

        return $bundles;
    }
}

if (!function_exists('j0_build_bundles')) {
    /**
     * Builds and minifies all active root-level bundles in $rootDir.
     *
     * @param array<string, mixed> $options
     * @return array{bundles: array<int, array<string, mixed>>, warnings: array<int, string>}
     */
    function j0_build_bundles(string $rootDir, string $ext, array $options = []): array
    {
        $extClean = strtolower(ltrim($ext, '.'));
        $discovered = j0_discover_bundles($rootDir, $extClean, $options);
        $warnings = [];
        $results = [];

        foreach ($discovered as $bundle) {
            if ($bundle['missing']) {
                $target = is_link($bundle['path']) ? (string) @readlink($bundle['path']) : $bundle['path'];
                $warnings[] = "Missing symlink target for '{$bundle['name']}' ({$target}); skipped.";
                continue;
            }
            if ($bundle['empty']) {
                continue;
            }

            $buildOpts = array_merge([
                'sources'  => $bundle['sources'],
                'out_file' => $bundle['raw_file'],
                'ext'      => $extClean,
                'minify'   => true,
                'backup'   => true,
                'exclude'  => [$bundle['raw_file'], $bundle['min_file']],
            ], $options);

            $res = j0_build_bundle($buildOpts);
            if (!empty($res['ok'])) {
                $sigEntries = [];
                foreach ($bundle['sources'] as $file) {
                    $sigEntries[] = $file . '|' . (int) @filemtime($file) . '|' . (int) @filesize($file);
                }
                $sigFile = dirname($bundle['out_base']) . '/.' . basename($bundle['out_base']) . '.' . $extClean . '.sig';
                @file_put_contents($sigFile, sha1(implode("\n", $sigEntries)) . "\n", LOCK_EX);
            }

            $results[] = [
                'bundle' => $bundle,
                'build'  => $res,
            ];
        }

        return [
            'bundles'  => $results,
            'warnings' => $warnings,
        ];
    }
}