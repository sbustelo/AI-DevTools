<?php
// PATH: ai0/tools/purge/purge-manager.php
declare(strict_types=1);

final class PurgeManager
{
    private const PROTECTED_DIRS = ['.git', 'node_modules', 'vendor'];

    /**
     * Scans purgeable items across the project (including _📦, _🗑️, and OS junk inside temp dirs)
     * delegating classification to the j0 SSOT prefix engine.
     *
     * @param array<string, mixed> $prefixCfg
     * @return array{
     *   discard: array<int, array{rel: string, marker: string}>,
     *   backup: array<int, array{rel: string, marker: string}>,
     *   disabled: array<int, array{rel: string, marker: string}>,
     *   os_junk: array<int, array{rel: string, marker: string}>
     * }
     */
    public static function scan(string $projectRoot, array $prefixCfg, string $scopeRel = ''): array
    {
        $rootClean = rtrim($projectRoot, '/');
        $scanBase = $scopeRel !== '' ? $rootClean . '/' . trim($scopeRel, '/') : $rootClean;
        $baseLen = strlen($rootClean) + 1;

        if (!is_dir($scanBase)) {
            return ['discard' => [], 'backup' => [], 'disabled' => [], 'os_junk' => []];
        }

        $ignoreDirs = self::PROTECTED_DIRS;
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

        $iterator = new RecursiveIteratorIterator($filterIter, RecursiveIteratorIterator::SELF_FIRST);
        $bs = chr(92);
        $groups = ['discard' => [], 'backup' => [], 'disabled' => [], 'os_junk' => []];

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $name = $item->getFilename();
            $abs = str_replace($bs, '/', $item->getPathname());
            $rel = substr($abs, $baseLen) . ($item->isDir() ? '/' : '');
            $parentRel = dirname(rtrim($rel, '/'));
            $insideTemp = $parentRel !== '.' && j0_is_temp_path($parentRel);

            $classified = j0_classify_purge_item($name, $prefixCfg, $insideTemp);
            if ($classified === null) {
                continue;
            }

            $bucket = $classified['bucket'];
            $groups[$bucket][] = [
                'rel'    => $rel,
                'marker' => $classified['marker'],
            ];
        }

        foreach ($groups as &$list) {
            usort($list, static fn(array $a, array $b): int => strnatcasecmp($a['rel'], $b['rel']));
        }
        unset($list);

        return $groups;
    }

    /**
     * Permanently deletes selected items verified against the j0 SSOT purge classifier.
     *
     * @param array<int, string> $relPaths
     * @param array<string, mixed> $prefixCfg
     */
    public static function deleteSelected(string $projectRoot, array $relPaths, array $prefixCfg = []): int
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

            $segments = explode('/', $clean);
            $lastIdx = count($segments) - 1;
            $base = $segments[$lastIdx];
            $parentRel = $lastIdx > 0 ? implode('/', array_slice($segments, 0, $lastIdx)) : '';
            $insideTemp = $parentRel !== '' && j0_is_temp_path($parentRel);

            if (j0_classify_purge_item($base, $prefixCfg, $insideTemp) === null) {
                continue;
            }

            $blocked = false;
            $accumPath = $rootClean;
            foreach ($segments as $idx => $segment) {
                if ($segment === '' || in_array($segment, self::PROTECTED_DIRS, true)) {
                    $blocked = true;
                    break;
                }
                $accumPath .= '/' . $segment;
                if ($idx < $lastIdx && is_link($accumPath)) {
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