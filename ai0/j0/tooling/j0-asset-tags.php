<?php
// PATH: ai0/j0/tooling/j0-asset-tags.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/j0-scan.php';
require_once __DIR__ . '/j0-build.php';

if (!function_exists('j0_asset_deps')) {
    /**
     * Internal accumulator for scanned assets, used by j0_static_html for cache invalidation.
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
        $extClean = strtolower(ltrim($ext, '.'));
        $rawOut = "{$outBase}.{$extClean}";
        $minOut = "{$outBase}.min.{$extClean}";
        $webPrefix = rtrim($webBase, '/') . '/';

        $files = [];
        foreach ((array) $sources as $src) {
            $src = rtrim((string) $src, '/');
            if (is_file($src) && str_ends_with(strtolower($src), '.' . $extClean)) {
                $files[] = $src;
            } elseif (is_dir($src)) {
                $files = array_merge($files, j0_scan_files($src, [$extClean]));
            }
        }
        $files = array_unique($files);
        j0_asset_deps($files);

        $latestSrc = 0;
        foreach ($files as $file) {
            $m = (int) @filemtime($file);
            if ($m > $latestSrc) {
                $latestSrc = $m;
            }
        }

        $minMtime = is_file($minOut) ? (int) filemtime($minOut) : 0;
        $canWrite = is_dir(dirname($minOut)) ? is_writable(dirname($minOut)) : is_writable(dirname($minOut, 2));

        if ($latestSrc > $minMtime && $canWrite) {
            j0_build_bundle(array_merge([
                'sources'  => $sources,
                'out_file' => $rawOut,
                'ext'      => $extClean,
                'minify'   => true,
                'backup'   => true,
            ], $buildOptions));
        }

        if (!$debug && is_file($minOut)) {
            $ver = (int) filemtime($minOut);
            $href = htmlspecialchars($webPrefix . basename($minOut) . '?v=' . $ver, ENT_QUOTES, 'UTF-8');
            return $extClean === 'css'
                ? "<link rel=\"stylesheet\" href=\"{$href}\">"
                : "<script src=\"{$href}\"></script>";
        }

        $webBaseName = basename(rtrim($webBase, '/'));
        $tags = [];
        foreach ($files as $absFile) {
            $name = basename($absFile);
            $parent = basename(dirname($absFile));
            $relPath = ($parent !== '' && $parent !== $webBaseName) ? ($parent . '/' . $name) : $name;
            $ver = (int) @filemtime($absFile);
            $url = htmlspecialchars($webPrefix . $relPath . '?v=' . $ver, ENT_QUOTES, 'UTF-8');
            $tags[] = $extClean === 'css'
                ? "<link rel=\"stylesheet\" href=\"{$url}\">"
                : "<script src=\"{$url}\"></script>";
        }

        return implode("\n", $tags);
    }
}