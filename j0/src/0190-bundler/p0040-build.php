<?php
// PATH: j0/src/0190-bundler/p0040-build.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/j0-prefix.php';
require_once dirname(__DIR__, 2) . '/core/j0-scan.php';
require_once __DIR__ . '/p0010-concat.php';
require_once __DIR__ . '/p0020-minify.php';
require_once dirname(__DIR__) . '/0180-fs-io/p0020-save.php';

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
        $bs = chr(92);
        $outFile = str_replace($bs, '/', (string) $options['out_file']);
        $ext = strtolower(ltrim((string) $options['ext'], '.'));
        $doMinify = $options['minify'] ?? true;
        $doBackup = $options['backup'] ?? !(function_exists('j0_is_temp_path') && j0_is_temp_path($outFile));
        $header = (string) ($options['header'] ?? '');
        if ($header === '') {
            $header = '/*! j0 compiled */';
        }
        $markers = $options['markers'] ?? true;

        $info = pathinfo($outFile);
        $outDir = $info['dirname'] ?? '.';
        $outBase = $info['filename'] ?? 'bundle';
        $computedMinFile = "{$outDir}/{$outBase}.min.{$ext}";

        $excludePaths = [$outFile, $computedMinFile];
        if (!empty($options['exclude']) && is_array($options['exclude'])) {
            foreach ($options['exclude'] as $ex) {
                $excludePaths[] = str_replace($bs, '/', (string) $ex);
            }
        }
        $excludeLookup = array_flip($excludePaths);

        $sources = $options['sources'] ?? (isset($options['src_dir']) ? [$options['src_dir']] : []);
        $files = [];

        foreach ((array) $sources as $src) {
            $src = rtrim(str_replace($bs, '/', (string) $src), '/');
            if (is_file($src) && str_ends_with(strtolower($src), '.' . $ext)) {
                if (!isset($excludeLookup[$src])) {
                    $files[] = $src;
                }
            } elseif (is_dir($src)) {
                $files = array_merge($files, j0_scan_files($src, [$ext], ['_', '.'], $excludePaths));
            }
        }

        $files = array_values(array_unique($files));
        if ($files === []) {
            return ['ok' => false, 'files_count' => 0, 'bundle_file' => $outFile, 'min_file' => null, 'reason' => 'no_sources'];
        }

        $separator = $ext === 'js' ? "\n;\n" : "\n";
        $rawBundle = j0_concat_files($files, $separator, $header, (bool) $markers);
        if (isset($options['transform']) && is_callable($options['transform'])) {
            $rawBundle = (string) ($options['transform'])($rawBundle, $ext);
        }
        $saveRaw = j0_smart_save($outFile, $rawBundle, (bool) $doBackup);

        $minFile = null;
        if ($doMinify) {
            $minFile = $computedMinFile;
            $minCode = $ext === 'css' ? j0_minify_css($rawBundle, $options) : j0_minify_js($rawBundle, $options);
            j0_smart_save($minFile, $minCode, (bool) $doBackup);
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