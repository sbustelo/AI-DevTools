<?php
// PATH: ai0/tools/purge/purge-manager.php
declare(strict_types=1);

final class PurgeManager
{
    private const PROTECTED_DIRS = ['.git', 'node_modules', 'vendor', '_j0-temp', '_ai0', 'ai0', '_ai0config', 'ai0config', '_j0', 'j0', '_j0config', 'j0config'];

    private const DEFAULT_OS_JUNK = [
        '.DS_Store'        => 'macOS',
        '.AppleDouble'     => 'AppleDouble',
        '.LSOverride'      => 'macOS',
        'Thumbs.db'        => 'Windows',
        'ehthumbs.db'      => 'Windows',
        'ehthumbs_vista.db'=> 'Windows',
        'Desktop.ini'      => 'Windows',
        '.directory'       => 'Linux',
    ];

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
            return ['discard' => [], 'backup' => [], 'disabled' => [], 'os_junk' => []];
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
        $groups = ['discard' => [], 'backup' => [], 'disabled' => [], 'os_junk' => []];

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $name = $item->getFilename();
            $abs = str_replace($bs, '/', $item->getPathname());
            $rel = substr($abs, $baseLen) . ($item->isDir() ? '/' : '');

            if (str_starts_with($name, '_')) {
                $info = j0_classify_prefix($name, $prefixCfg);
                $state = $info['state'];
                $bucket = isset($groups[$state]) ? $state : 'disabled';

                $groups[$bucket][] = [
                    'rel'    => $rel,
                    'marker' => $info['marker'],
                ];
                continue;
            }

            $junkLabel = self::matchOsJunk($name, $prefixCfg);
            if ($junkLabel !== null) {
                $groups['os_junk'][] = [
                    'rel'    => $rel,
                    'marker' => $junkLabel,
                ];
            }
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
            $base = basename($clean);
            if (!str_starts_with($base, '_') && self::matchOsJunk($base, $prefixCfg) === null) {
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

    /**
     * @param array<string, mixed> $prefixCfg
     */
    private static function matchOsJunk(string $name, array $prefixCfg): ?string
    {
        $rules = isset($prefixCfg['os_junk']) && is_array($prefixCfg['os_junk'])
            ? $prefixCfg['os_junk']
            : array_merge(self::DEFAULT_OS_JUNK, ['._*' => 'AppleDouble']);

        $lowerName = strtolower($name);
        foreach ($rules as $pattern => $label) {
            if (!is_string($label) || $label === '') {
                continue;
            }
            $pat = (string) $pattern;
            if ($pat === '') {
                continue;
            }
            if (str_ends_with($pat, '*')) {
                $prefix = substr($pat, 0, -1);
                if ($prefix !== '' && str_starts_with($lowerName, strtolower($prefix))) {
                    return $label;
                }
                continue;
            }
            if ($lowerName === strtolower($pat)) {
                return $label;
            }
        }

        return null;
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