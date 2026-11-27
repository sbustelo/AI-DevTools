<?php
// PATH: ai0-0010/j0/php/j0-concat.php
declare(strict_types=1);

if (!function_exists('j0_concat_files')) {
    /**
     * Joins a list of source files in memory with normalized LF line endings.
     *
     * @param array<int, string> $files
     */
    function j0_concat_files(
        array $files,
        string $separator = "\n",
        string $header = '',
        bool $includeFileMarkers = false
    ): string {
        $chunks = [];
        if ($header !== '') {
            $chunks[] = rtrim($header);
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $raw = (string) file_get_contents($file);
            $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
            if ($includeFileMarkers) {
                $chunks[] = '/* === SOURCE: ' . basename($file) . " === */\n" . rtrim($normalized);
            } else {
                $chunks[] = rtrim($normalized);
            }
        }

        if ($chunks === []) {
            return '';
        }

        return implode($separator, $chunks) . "\n";
    }
}