<?php
// PATH: ai0/j0/src/0130-cache/p0010-static-html.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/j0-scan.php';
require_once dirname(__DIR__) . '/0180-fs-io/p0020-save.php';

if (!function_exists('j0_static_html')) {
    /**
     * Serves the sibling .html file if up to date relative to the PHP file and its dependencies;
     * otherwise executes the render callback, saves the static .html, and outputs it.
     *
     * @param array<int, string> $dependencies Explicit dependencies (auto-merged with j0_asset_deps)
     */
    function j0_static_html(string $phpFile, callable $renderCallback, array $dependencies = []): void
    {
        $htmlFile = preg_replace('/\.php$/i', '.html', $phpFile);
        if (!is_string($htmlFile)) {
            $htmlFile = $phpFile . '.html';
        }

        $latestSource = is_file($phpFile) ? (int) filemtime($phpFile) : time();
        $autoDeps = function_exists('j0_asset_deps') ? j0_asset_deps() : [];
        $allDeps = array_unique(array_merge($dependencies, $autoDeps));

        foreach ($allDeps as $dep) {
            if (is_file($dep)) {
                $m = (int) filemtime($dep);
                if ($m > $latestSource) {
                    $latestSource = $m;
                }
            } elseif (is_dir($dep)) {
                $m = j0_dir_max_mtime($dep);
                if ($m > $latestSource) {
                    $latestSource = $m;
                }
            }
        }

        if (is_file($htmlFile) && (int) filemtime($htmlFile) >= $latestSource) {
            readfile($htmlFile);
            exit;
        }

        ob_start();
        $renderCallback();
        $content = (string) ob_get_clean();

        j0_smart_save($htmlFile, $content, false);
        echo $content;
        exit;
    }
}