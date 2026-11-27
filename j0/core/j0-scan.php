<?php
// PATH: ai0-0010/j0/php/j0-scan.php
declare(strict_types=1);

if (!function_exists('j0_scan_files')) {
    /**
     * Recursively scans $dir (following safe symlinks) and returns matching file paths.
     * Supports 'tree' (level-by-level directory hierarchy) and 'flat' sorting modes.
     *
     * @param array<int, string> $extensions
     * @param array<int, string> $ignorePrefixes
     * @param array<int, string> $excludePaths
     * @return array<int, string>
     */
    function j0_scan_files(
        string $dir,
        array $extensions = [],
        array $ignorePrefixes = ['_', '.'],
        array $excludePaths = [],
        string $sortMode = 'tree'
    ): array {
        if (!is_dir($dir)) {
            return [];
        }

        $bs = chr(92);
        $normRoot = rtrim(str_replace($bs, '/', $dir), '/');
        $excludedLookup = [];
        foreach ($excludePaths as $ex) {
            $normEx = rtrim(str_replace($bs, '/', (string) $ex), '/');
            if ($normEx !== '') {
                $excludedLookup[$normEx] = true;
            }
        }

        $visitedReal = [];
        $rootReal = realpath($normRoot);
        if ($rootReal !== false) {
            $visitedReal[$rootReal] = true;
        }

        $extFilter = array_map(static fn(string $e): string => strtolower(ltrim($e, '.')), $extensions);
        $dirIter = new RecursiveDirectoryIterator(
            $normRoot,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
        );
        $filterIter = new RecursiveCallbackFilterIterator(
            $dirIter,
            static function (SplFileInfo $current) use ($ignorePrefixes, &$visitedReal): bool {
                $name = $current->getFilename();
                foreach ($ignorePrefixes as $prefix) {
                    if ($prefix !== '' && str_starts_with($name, $prefix)) {
                        return false;
                    }
                }
                if ($current->isDir()) {
                    $real = $current->getRealPath();
                    if ($real === false || isset($visitedReal[$real])) {
                        return false;
                    }
                    $visitedReal[$real] = true;
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
            $normPath = str_replace($bs, '/', $file->getPathname());
            if ($excludedLookup !== [] && isset($excludedLookup[$normPath])) {
                continue;
            }
            $files[] = $normPath;
        }

        if ($sortMode === 'flat') {
            sort($files, SORT_NATURAL);
            return $files;
        }

        $prefixLen = strlen($normRoot) + 1;
        usort($files, static function (string $a, string $b) use ($prefixLen): int {
            $relA = substr($a, $prefixLen);
            $relB = substr($b, $prefixLen);
            $segA = explode('/', $relA);
            $segB = explode('/', $relB);
            $limit = min(count($segA), count($segB));
            for ($i = 0; $i < $limit; $i++) {
                $cmp = strnatcasecmp($segA[$i], $segB[$i]);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }
            return count($segA) <=> count($segB);
        });

        return $files;
    }
}

if (!function_exists('j0_dir_max_mtime')) {
    /**
     * @param array<int, string> $extensions
     * @param array<int, string> $ignorePrefixes
     * @param array<int, string> $excludePaths
     */
    function j0_dir_max_mtime(
        string $dir,
        array $extensions = [],
        array $ignorePrefixes = ['_', '.'],
        array $excludePaths = []
    ): int {
        $max = 0;
        foreach (j0_scan_files($dir, $extensions, $ignorePrefixes, $excludePaths) as $file) {
            $mtime = (int) @filemtime($file);
            if ($mtime > $max) {
                $max = $mtime;
            }
        }
        return $max;
    }
}

if (!function_exists('j0_dir_signature')) {
    /**
     * Computes a deterministic content-tree fingerprint (path + mtime + size) so
     * added, modified, renamed, or deleted files always invalidate dependent caches.
     *
     * @param array<int, string> $extensions
     * @param array<int, string> $ignorePrefixes
     * @param array<int, string> $excludePaths
     */
    function j0_dir_signature(
        string $dir,
        array $extensions = [],
        array $ignorePrefixes = ['_', '.'],
        array $excludePaths = []
    ): string {
        $files = j0_scan_files($dir, $extensions, $ignorePrefixes, $excludePaths);
        if ($files === []) {
            return sha1('empty');
        }

        $entries = [];
        foreach ($files as $file) {
            $mtime = (int) @filemtime($file);
            $size = (int) @filesize($file);
            $entries[] = $file . '|' . $mtime . '|' . $size;
        }

        return sha1(implode("\n", $entries));
    }
}