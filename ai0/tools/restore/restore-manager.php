<?php
// PATH: ai0/tools/restore/restore-manager.php
declare(strict_types=1);

final class RestoreManager
{
    private const BACKUP_REGEX = '/^_\x{1F4E6}(.+)_(\d{4}-\d{2}-\d{2}T\d{2}-\d{2}-\d{2})(?:_(\d+))?(\.[^.]+)?$/u';

    /**
     * Discovers timestamped _📦 backup files across the project and groups them by batch timestamp.
     *
     * @return array{batches: array<string, array<int, array{backup_rel: string, orig_rel: string}>>, total_files: int}
     */
    public static function scan(string $projectRoot, string $scopeRel = ''): array
    {
        $rootClean = rtrim($projectRoot, '/');
        $scanBase = $scopeRel !== '' ? $rootClean . '/' . trim($scopeRel, '/') : $rootClean;
        $baseLen = strlen($rootClean) + 1;

        if (!is_dir($scanBase)) {
            return ['batches' => [], 'total_files' => 0];
        }

        $ignoreDirs = ['.git', 'node_modules', 'vendor', '_j0-temp'];
        $dirIter = new RecursiveDirectoryIterator($scanBase, FilesystemIterator::SKIP_DOTS);
        $filterIter = new RecursiveCallbackFilterIterator(
            $dirIter,
            static function (SplFileInfo $current) use ($ignoreDirs): bool {
                if ($current->isLink()) {
                    return false;
                }
                if ($current->isDir()) {
                    return !in_array($current->getFilename(), $ignoreDirs, true);
                }
                return true;
            }
        );

        $iterator = new RecursiveIteratorIterator($filterIter, RecursiveIteratorIterator::LEAVES_ONLY);
        $bs = chr(92);
        $batches = [];
        $totalFiles = 0;

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $normName = j0_normalize_prefix_text($item->getFilename());
            if (!preg_match(self::BACKUP_REGEX, $normName, $m)) {
                continue;
            }

            $abs = str_replace($bs, '/', $item->getPathname());
            $backupRel = substr($abs, $baseLen);
            $dirRel = dirname($backupRel);
            $origName = $m[1] . ($m[4] ?? '');
            $origRel = ($dirRel === '.' ? '' : $dirRel . '/') . $origName;
            $ts = $m[2];

            $batches[$ts][] = [
                'backup_rel' => $backupRel,
                'orig_rel'   => $origRel,
            ];
            $totalFiles++;
        }

        krsort($batches);
        return ['batches' => $batches, 'total_files' => $totalFiles];
    }

    /**
     * Restores all _📦 backup files belonging to a given batch timestamp.
     *
     * @return array<int, string>
     */
    public static function restoreBatch(string $projectRoot, string $timestamp): array
    {
        $rootClean = rtrim($projectRoot, '/');
        $scanned = self::scan($rootClean);
        $restored = [];

        if (!isset($scanned['batches'][$timestamp])) {
            return [];
        }

        foreach ($scanned['batches'][$timestamp] as $entry) {
            $fullBackup = $rootClean . '/' . $entry['backup_rel'];
            $fullOrig = $rootClean . '/' . $entry['orig_rel'];
            if (is_file($fullBackup) && rename($fullBackup, $fullOrig)) {
                $restored[] = $entry['orig_rel'];
            }
        }

        return $restored;
    }

    /**
     * Restores a single _📦 backup file to its original filename.
     */
    public static function restoreSingle(string $projectRoot, string $backupRel): ?string
    {
        $rootClean = rtrim($projectRoot, '/');
        $clean = ltrim(str_replace(chr(92), '/', $backupRel), '/');
        if ($clean === '' || str_contains($clean, '..')) {
            return null;
        }

        $normName = j0_normalize_prefix_text(basename($clean));
        if (!preg_match(self::BACKUP_REGEX, $normName, $m)) {
            return null;
        }

        $fullBackup = $rootClean . '/' . $clean;
        $origName = $m[1] . ($m[4] ?? '');
        $fullOrig = dirname($fullBackup) . '/' . $origName;

        if (is_file($fullBackup) && rename($fullBackup, $fullOrig)) {
            $dirRel = dirname($clean);
            return ($dirRel === '.' ? '' : $dirRel . '/') . $origName;
        }

        return null;
    }
}