<?php
// PATH: ai0-0010/tools/dumper/dumper-scanner.php
declare(strict_types=1);

final class DumperScanner
{
    /**
     * Builds the project tree from $scanRoot using j0_classify_prefix() and regex exclusion rules.
     *
     * @param array<string, mixed> $config
     * @return array{tree: array<int, array<string, mixed>>, counts: array<string, int>, total_files: int, included_files: int}
     */
    public static function scan(string $projectRoot, array $config, string $scopeRel = ''): array
    {
        $rootClean = rtrim($projectRoot, '/');
        $scanRoot = $scopeRel !== '' ? $rootClean . '/' . trim($scopeRel, '/') : $rootClean;
        $categories = $config['categories'];

        $counts = [];
        foreach (array_keys($categories) as $catId) {
            $counts[$catId] = 0;
        }

        $stats = ['total' => 0, 'included' => 0];
        $tree = self::buildBranch($scanRoot, $rootClean, $config, null, false, $counts, $stats);

        return [
            'tree'           => $tree,
            'counts'         => $counts,
            'total_files'    => $stats['total'],
            'included_files' => $stats['included'],
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, int> $counts
     * @param array{total: int, included: int} $stats
     * @return array<int, array<string, mixed>>
     */
    private static function buildBranch(
        string $dir,
        string $projectRoot,
        array $config,
        ?string $inheritedCat,
        bool $inheritedExcluded,
        array &$counts,
        array &$stats
    ): array {
        if (!is_dir($dir)) {
            return [];
        }

        $raw = @scandir($dir);
        if (!is_array($raw)) {
            return [];
        }

        $items = array_values(array_diff($raw, ['.', '..']));
        usort($items, static function (string $a, string $b) use ($dir): int {
            $aIsDir = is_dir($dir . '/' . $a);
            $bIsDir = is_dir($dir . '/' . $b);
            if ($aIsDir !== $bIsDir) {
                return $aIsDir ? -1 : 1;
            }
            return strnatcasecmp($a, $b);
        });

        $categories = $config['categories'];
        $prefixCfg = $config['prefixes'];
        $baseLen = strlen($projectRoot) + 1;
        $nodes = [];

        foreach ($items as $name) {
            $abs = $dir . '/' . $name;
            $rel = substr($abs, $baseLen);
            $isDir = is_dir($abs);

            $ownCat = self::classifyItem($name, $categories, $prefixCfg);
            $effectiveCat = $ownCat ?? $inheritedCat;
            $isExcluded = $inheritedExcluded;

            if ($ownCat !== null && isset($categories[$ownCat])) {
                $isExcluded = (bool) ($categories[$ownCat]['default_excluded'] ?? true);
            }

            if ($effectiveCat !== null && isset($counts[$effectiveCat])) {
                if (!$isDir || in_array($effectiveCat, ['vendors', 'dotfiles'], true)) {
                    $counts[$effectiveCat]++;
                }
            }

            $children = [];
            $isHeavyDir = $isDir && in_array($effectiveCat, ['vendors', 'dotfiles'], true);

            if ($isDir && !$isHeavyDir) {
                $children = self::buildBranch($abs, $projectRoot, $config, $effectiveCat, $isExcluded, $counts, $stats);
            }

            if (!$isDir) {
                $stats['total']++;
                if (!$isExcluded) {
                    $stats['included']++;
                }
            }

            $nodes[] = [
                'name'     => $name,
                'rel'      => $rel,
                'is_dir'   => $isDir,
                'cat'      => $effectiveCat ?? 'none',
                'excluded' => $isExcluded,
                'heavy'    => $isHeavyDir,
                'children' => $children,
            ];
        }

        return $nodes;
    }

    /**
     * @param array<string, array<string, mixed>> $categories
     * @param array<string, mixed> $prefixCfg
     */
    private static function classifyItem(string $name, array $categories, array $prefixCfg): ?string
    {
        if (str_starts_with($name, '_')) {
            $prefixInfo = j0_classify_prefix($name, $prefixCfg);
            $state = $prefixInfo['state'];
            foreach ($categories as $catId => $def) {
                if (($def['prefix_state'] ?? '') === $state) {
                    return $catId;
                }
            }
            return 'disabled';
        }

        foreach ($categories as $catId => $def) {
            $pattern = (string) ($def['pattern'] ?? '');
            if ($pattern !== '' && preg_match($pattern, $name)) {
                return $catId;
            }
        }

        return null;
    }
}