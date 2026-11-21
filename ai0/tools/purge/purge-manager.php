<?php
// PATH: ai0/tools/purge/purge-manager.php
declare(strict_types=1);

final class PurgeManager
{
    private const PROTECTED_DIRS = ['.git', 'node_modules', 'vendor', '_j0-temp', '_ai0', '_ai0config'];

    /**
     * Scans leading-underscore items and classifies them via j0_classify_prefix():
     * - discard: _🗑️* and __* (preselected for deletion)
     * - backup: _📦* (preselected for deletion)
     * - disabled: _* (protected / unchecked by default)
     *
     * @param array<string, mixed> $prefixCfg
     * @return array{
     *   discard: array<int, array{rel: string, marker: string}>,
     *   backup: array<int, array{rel: string, marker: string}>,
     *   disabled: array<int, array{rel: string, marker: string}>
     * }
     */
    public static function scan(string $projectRoot, array $prefixCfg, string $scopeRel = ''): array
    {
        $rootClean = rtrim($projectRoot, '/');
        $scanBase = $scopeRel !== '' ? $rootClean . '/' . trim($scopeRel, '/') : $rootClean;
        $baseLen = strlen($rootClean) + 1;

        if (!is_dir($scanBase)) {
            return ['discard' => [], 'backup' => [], 'disabled' => []];
        }

        $ignoreDirs = self::PROTECTED_DIRS;
        $dirIter = new RecursiveDirectoryIterator($scanBase, FilesystemIterator::SKIP_DOTS);
        $filterIter = new RecursiveCallbackFilterIterator(
            $dirIter,
            static function (SplFileInfo $current) use ($ignoreDirs): bool {
                if ($current->isDir()) {
                    return !in_array($current->getFilename(), $ignoreDirs, true);
                }
                return true;
            }
        );

        $iterator = new RecursiveIteratorIterator($filterIter, RecursiveIteratorIterator::SELF_FIRST);
        $bs = chr(92);
        $groups = ['discard' => [], 'backup' => [], 'disabled' => []];

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $name = $item->getFilename();
            if (!str_starts_with($name, '_')) {
                continue;
            }

            $abs = str_replace($bs, '/', $item->getPathname());
            $rel = substr($abs, $baseLen) . ($item->isDir() ? '/' : '');
            $info = j0_classify_prefix($name, $prefixCfg);
            $state = $info['state'];
            $bucket = isset($groups[$state]) ? $state : 'disabled';

            $groups[$bucket][] = [
                'rel'    => $rel,
                'marker' => $info['marker'],
            ];
        }

        foreach ($groups as &$list) {
            usort($list, static fn(array $a, array $b): int => strnatcasecmp($a['rel'], $b['rel']));
        }
        unset($list);

        return $groups;
    }

    /**
     * @param array<int, string> $relPaths
     */
    public static function deleteSelected(string $projectRoot, array $relPaths): int
    {
        $bs = chr(92);
        $rootClean = rtrim(str_replace($bs, '/', $projectRoot), '/');
        $deleted = 0;

        rsort($relPaths, SORT_NATURAL);
        foreach ($relPaths as $rel) {
            $clean = trim(str_replace($bs, '/', $rel), '/');
            if ($clean === '' || str_contains($clean, '..') || str_contains($clean, "\0")) {
                continue;
            }
            if (!str_starts_with(basename($clean), '_')) {
                continue;
            }

            $blocked = false;
            foreach (explode('/', $clean) as $segment) {
                if ($segment === '' || in_array($segment, self::PROTECTED_DIRS, true)) {
                    $blocked = true;
                    break;
                }
            }
            if ($blocked) {
                continue;
            }

            $full = $rootClean . '/' . $clean;
            if (is_link($full)) {
                if (@unlink($full)) {
                    $deleted++;
                }
                continue;
            }
            if (is_file($full)) {
                if (@unlink($full)) {
                    $deleted++;
                }
                continue;
            }
            if (is_dir($full)) {
                self::removeDirRecursive($full);
                if (!is_dir($full)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    private static function removeDirRecursive(string $dir): void
    {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        /** @var SplFileInfo $item */
        foreach ($it as $item) {
            $path = $item->getPathname();
            if (is_link($path) || !$item->isDir()) {
                @unlink($path);
            } else {
                @rmdir($path);
            }
        }
        @rmdir($dir);
    }
}