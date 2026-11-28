<?php
// PATH: j0/src/0130-cache/p0010-static-html.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/j0-scan.php';
require_once dirname(__DIR__) . '/0180-fs-io/p0020-save.php';

if (!function_exists('j0_static_html')) {
    /**
     * Serves the sibling .html snapshot when up to date relative to the PHP script and all tracked
     * dependencies (file list + mtime + size signature); otherwise renders, saves .html, and outputs it.
     * When $bypass is true (e.g. ?debug=1), renders dynamically without reading or overwriting .html.
     *
     * @param array<int, string> $dependencies Explicit dependencies (auto-merged with j0_asset_deps)
     */
    function j0_static_html(string $phpFile, callable $renderCallback, array $dependencies = [], bool $bypass = false): void
    {
        if ($bypass) {
            $renderCallback();
            exit;
        }

        $bs = chr(92);
        $phpNorm = str_replace($bs, '/', $phpFile);
        $htmlFile = preg_replace('/\.php$/i', '.html', $phpNorm);
        if (!is_string($htmlFile)) {
            $htmlFile = $phpNorm . '.html';
        }
        $sigFile = dirname($htmlFile) . '/.' . basename($htmlFile) . '.sig';

        $autoDeps = function_exists('j0_asset_deps') ? j0_asset_deps() : [];
        $allDeps = array_values(array_unique(array_merge([$phpNorm], $dependencies, $autoDeps)));
        sort($allDeps, SORT_NATURAL);

        $sigParts = [];
        foreach ($allDeps as $dep) {
            $depNorm = str_replace($bs, '/', (string) $dep);
            if (is_file($depNorm)) {
                $sigParts[] = $depNorm . '|' . (int) @filemtime($depNorm) . '|' . (int) @filesize($depNorm);
            } elseif (is_dir($depNorm)) {
                foreach (j0_scan_files($depNorm) as $inner) {
                    $sigParts[] = $inner . '|' . (int) @filemtime($inner) . '|' . (int) @filesize($inner);
                }
            }
        }
        $currentSig = sha1(implode("\n", $sigParts));
        $cachedSig = is_file($sigFile) ? trim((string) @file_get_contents($sigFile)) : '';

        if (is_file($htmlFile) && $cachedSig !== '' && $cachedSig === $currentSig) {
            readfile($htmlFile);
            exit;
        }

        ob_start();
        $renderCallback();
        $content = (string) ob_get_clean();

        j0_smart_save($htmlFile, $content, false);
        if (is_writable(dirname($htmlFile))) {
            @file_put_contents($sigFile, $currentSig . "\n", LOCK_EX);
        }
        echo $content;
        exit;
    }
}