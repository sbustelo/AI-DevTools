<?php
// PATH: ai0/j0/tooling/j0-asset-tags.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/j0-scan.php';
require_once __DIR__ . '/j0-build.php';

if (!function_exists('j0_asset_tags')) {
    /**
     * Recompiles stale bundles when writable and emits either a single .min tag or individual source tags in DEBUG mode.
     *
     * @param array<string, mixed> $buildOptions
     */
    function j0_asset_tags(
        string $srcDir,
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

        $latestSrc = j0_dir_max_mtime($srcDir, [$extClean]);
        $minMtime = is_file($minOut) ? (int) filemtime($minOut) : 0;
        $canWrite = is_dir(dirname($minOut)) ? is_writable(dirname($minOut)) : is_writable(dirname($minOut, 2));

        if ($latestSrc > $minMtime && $canWrite) {
            j0_build_bundle(array_merge([
                'src_dir'  => $srcDir,
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

        $files = j0_scan_files($srcDir, [$extClean]);
        $srcRootClean = rtrim(str_replace(chr(92), '/', $srcDir), '/');
        $srcFolderName = basename($srcRootClean);
        $tags = [];

        foreach ($files as $absFile) {
            $relInside = ltrim(substr($absFile, strlen($srcRootClean)), '/');
            $ver = (int) @filemtime($absFile);
            $url = htmlspecialchars($webPrefix . $srcFolderName . '/' . $relInside . '?v=' . $ver, ENT_QUOTES, 'UTF-8');
            $tags[] = $extClean === 'css'
                ? "<link rel=\"stylesheet\" href=\"{$url}\">"
                : "<script src=\"{$url}\"></script>";
        }

        return implode("\n", $tags);
    }
}