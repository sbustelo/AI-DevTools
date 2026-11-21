<?php
// PATH: ai0-0010/j0/php/j0-scan.php
declare(strict_types=1);

if (!function_exists('j0_scan_files')) {
    /**
     * Recursively scans $dir and returns matching file paths sorted with SORT_NATURAL.
     *
     * @param array<int, string> $extensions
     * @param array<int, string> $ignorePrefixes
     * @return array<int, string>
     */
    function j0_scan_files(string $dir, array $extensions = [], array $ignorePrefixes = ['_', '.']): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $extFilter = array_map(static fn(string $e): string => strtolower(ltrim($e, '.')), $extensions);
        $dirIter = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $filterIter = new RecursiveCallbackFilterIterator(
            $dirIter,
            static function (SplFileInfo $current) use ($ignorePrefixes): bool {
                $name = $current->getFilename();
                foreach ($ignorePrefixes as $prefix) {
                    if ($prefix !== '' && str_starts_with($name, $prefix)) {
                        return false;
                    }
                }
                return true;
            }
        );

        $files = [];
        $iterator = new RecursiveIteratorIterator($filterIter, RecursiveIteratorIterator::LEAVES_ONLY);
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            if ($extFilter !== []) {
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, $extFilter, true)) {
                    continue;
                }
            }
            $files[] = str_replace(chr(92), '/', $file->getPathname());
        }

        sort($files, SORT_NATURAL);
        return $files;
    }
}

if (!function_exists('j0_dir_max_mtime')) {
    /**
     * @param array<int, string> $extensions
     * @param array<int, string> $ignorePrefixes
     */
    function j0_dir_max_mtime(string $dir, array $extensions = [], array $ignorePrefixes = ['_', '.']): int
    {
        $max = 0;
        foreach (j0_scan_files($dir, $extensions, $ignorePrefixes) as $file) {
            $mtime = (int) @filemtime($file);
            if ($mtime > $max) {
                $max = $mtime;
            }
        }
        return $max;
    }
}