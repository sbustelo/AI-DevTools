<?php
// PATH: ai0/j0/tooling/j0-build.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/j0-scan.php';
require_once __DIR__ . '/j0-concat.php';
require_once __DIR__ . '/j0-minify.php';
require_once __DIR__ . '/j0-save.php';

if (!function_exists('j0_build_bundle')) {
    /**
     * Scans, concatenates, saves <name>.<ext>, minifies, and saves <name>.min.<ext>.
     *
     * @param array{
     *   src_dir?: string,
     *   sources?: string|array<int, string>,
     *   out_file: string,
     *   ext: string,
     *   minify?: bool,
     *   backup?: bool,
     *   header?: string,
     *   markers?: bool
     * } $options
     * @return array{ok: bool, files_count: int, bundle_file: string, min_file: string|null, reason: string}
     */
    function j0_build_bundle(array $options): array
    {
        $outFile = $options['out_file'];
        $ext = strtolower(ltrim($options['ext'], '.'));
        $doMinify = $options['minify'] ?? true;
        $doBackup = $options['backup'] ?? true;
        $header = $options['header'] ?? '';
        $markers = $options['markers'] ?? true;

        $sources = $options['sources'] ?? (isset($options['src_dir']) ? [$options['src_dir']] : []);
        $files = [];

        foreach ((array) $sources as $src) {
            $src = rtrim((string) $src, '/');
            if (is_file($src) && str_ends_with(strtolower($src), '.' . $ext)) {
                $files[] = $src;
            } elseif (is_dir($src)) {
                $files = array_merge($files, j0_scan_files($src, [$ext]));
            }
        }

        $files = array_unique($files);
        if ($files === []) {
            return ['ok' => false, 'files_count' => 0, 'bundle_file' => $outFile, 'min_file' => null, 'reason' => 'no_sources'];
        }

        $separator = $ext === 'js' ? "\n;\n" : "\n";
        $rawBundle = j0_concat_files($files, $separator, $header, $markers);
        $saveRaw = j0_smart_save($outFile, $rawBundle, $doBackup);

        $minFile = null;
        if ($doMinify) {
            $info = pathinfo($outFile);
            $dir = $info['dirname'] ?? '.';
            $base = $info['filename'] ?? 'bundle';
            $minFile = "{$dir}/{$base}.min.{$ext}";

            $minCode = $ext === 'css' ? j0_minify_css($rawBundle) : j0_minify_js($rawBundle);
            j0_smart_save($minFile, $minCode, $doBackup);
        }

        return [
            'ok'          => true,
            'files_count' => count($files),
            'bundle_file' => $outFile,
            'min_file'    => $minFile,
            'reason'      => $saveRaw['reason'],
        ];
    }
}