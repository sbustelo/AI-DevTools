<?php
// PATH: ai0/tools/dumper/dumper-scanner.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

final class DumperScanner
{
    /**
     * Scans a directory tree and classifies files and directories into exclusion categories.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function scan(string $projectRoot, array $config, string $scopeRel = ''): array
    {
        $rootClean = rtrim($projectRoot, '/');
        $scanRoot = $scopeRel !== '' ? $rootClean . '/' . trim($scopeRel, '/') : $rootClean;
        $categories = is_array($config['categories'] ?? null) ? $config['categories'] : [];

        $counts = [];
        foreach (array_keys($categories) as $catId) {
            $counts[$catId] = 0;
        }

        $stats = ['total' => 0, 'included' => 0];
        $purgeTotals = ['discard' => 0, 'backup' => 0, 'disabled' => 0, 'os_junk' => 0];
        $tree = self::buildBranch($scanRoot, $scanRoot, $config, null, false, $counts, $stats, $purgeTotals);

        return [
            'tree'           => $tree,
            'counts'         => $counts,
            'total_files'    => $stats['total'],
            'included_files' => $stats['included'],
            'purge_totals'   => $purgeTotals,
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
        string $baseRoot,
        array $config,
        ?string $inheritedCat,
        bool $inheritedExcluded,
        array &$counts,
        array &$stats,
        array &$purgeTotals
    ): array {
        if (!is_dir($dir) || is_link($dir)) {
            return [];
        }

        $raw = @scandir($dir);
        if (!is_array($raw)) {
            return [];
        }

        $items = array_values(array_diff($raw, ['.', '..']));
        natcasesort($items);
        $items = array_values($items);

        $categories = is_array($config['categories'] ?? null) ? $config['categories'] : [];
        $prefixCfg = is_array($config['prefixes'] ?? null) ? $config['prefixes'] : [];
        $j0Pragmas = is_array($config['j0_pragmas'] ?? null) ? $config['j0_pragmas'] : [];

        $nodes = [];
        $basePrefix = rtrim($baseRoot, '/') . '/';

        foreach ($items as $name) {
            if ($name === '_j0-temp') {
                continue;
            }
            $fullPath = $dir . '/' . $name;
            $relPath = str_starts_with($fullPath, $basePrefix)
                ? substr($fullPath, strlen($basePrefix))
                : ltrim($fullPath, '/');

            $isDir = is_dir($fullPath) && !is_link($fullPath);
            $ownCat = self::classifyItem($name, $dir, $items, $categories, $prefixCfg, $j0Pragmas);
            $cat = $ownCat ?? $inheritedCat ?? 'none';

            $catExcluded = false;
            if ($ownCat !== null && isset($categories[$ownCat])) {
                $catExcluded = (bool) ($categories[$ownCat]['default_excluded'] ?? true);
            }
            $excluded = $inheritedExcluded || $catExcluded;

            if ($ownCat !== null && isset($counts[$ownCat])) {
                $counts[$ownCat]++;
            }

            if (!$inheritedExcluded && str_starts_with($name, '_') && function_exists('j0_classify_prefix')) {
                $pInfo = j0_classify_prefix($name, $prefixCfg);
                $pState = (string) ($pInfo['state'] ?? 'disabled');
                if (isset($purgeTotals[$pState])) {
                    $purgeTotals[$pState]++;
                }
            }

            $children = [];
            $heavy = false;
            if ($isDir) {
                $children = self::buildBranch($fullPath, $baseRoot, $config, $cat, $excluded, $counts, $stats, $purgeTotals);
                $heavy = in_array(strtolower($name), ['node_modules', 'vendor', '.git'], true);
            } else {
                $stats['total']++;
                if (!$excluded) {
                    $stats['included']++;
                }
            }

            $nodes[] = [
                'name'     => $name,
                'rel'      => $relPath,
                'is_dir'   => $isDir,
                'cat'      => $cat,
                'excluded' => $excluded,
                'heavy'    => $heavy,
                'children' => $children,
            ];
        }

        return $nodes;
    }

    /**
     * @param array<int, string> $siblingItems
     * @param array<string, array<string, mixed>> $categories
     * @param array<string, mixed> $prefixCfg
     * @param array<string, mixed> $j0Pragmas
     */
    private static function classifyItem(
        string $name,
        string $dir,
        array $siblingItems,
        array $categories,
        array $prefixCfg,
        array $j0Pragmas
    ): ?string {
        if (str_starts_with($name, '_')) {
            if (function_exists('j0_classify_prefix')) {
                $prefixInfo = j0_classify_prefix($name, $prefixCfg);
                $state = (string) ($prefixInfo['state'] ?? 'disabled');
                foreach ($categories as $catId => $def) {
                    if ((string) ($def['prefix_state'] ?? '') === $state) {
                        return (string) $catId;
                    }
                }
            }
            return isset($categories['disabled']) ? 'disabled' : null;
        }

        $artifactKind = self::detectJ0Artifact($name, $dir, $siblingItems, $j0Pragmas);
        if ($artifactKind !== null) {
            foreach ($categories as $catId => $def) {
                if ((string) ($def['j0_artifact'] ?? '') === $artifactKind) {
                    return (string) $catId;
                }
            }
        }

        foreach ($categories as $catId => $def) {
            $pattern = (string) ($def['pattern'] ?? '');
            if ($pattern !== '' && @preg_match($pattern, $name) === 1) {
                return (string) $catId;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $siblingItems
     * @param array<string, mixed> $j0Pragmas
     */
    private static function detectJ0Artifact(string $name, string $dir, array $siblingItems, array $j0Pragmas): ?string
    {
        $abs = $dir . '/' . $name;
        if (!is_file($abs)) {
            return null;
        }

        $lower = strtolower($name);
        if (preg_match('/\.min\.(js|css)\z/', $lower) === 1) {
            return 'minified';
        }

        $minPragmas = isset($j0Pragmas['minified']) && is_array($j0Pragmas['minified']) ? $j0Pragmas['minified'] : [];
        if (self::matchesPragma($abs, $minPragmas)) {
            return 'minified';
        }

        if (preg_match('/^(.+)\.(js|css)\z/', $name, $m) === 1) {
            $stem = $m[1];
            $hasSiblingDir = in_array($stem, $siblingItems, true) && is_dir($dir . '/' . $stem);
            $hasSig = in_array('.' . $name . '.sig', $siblingItems, true);
            $compiledPragmas = isset($j0Pragmas['compiled']) && is_array($j0Pragmas['compiled']) ? $j0Pragmas['compiled'] : [];
            if ($hasSiblingDir || $hasSig || self::matchesPragma($abs, $compiledPragmas)) {
                return 'compiled';
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $pragmas
     */
    private static function matchesPragma(string $absFile, array $pragmas): bool
    {
        if ($pragmas === [] || !is_readable($absFile)) {
            return false;
        }

        $fh = @fopen($absFile, 'rb');
        if (!is_resource($fh)) {
            return false;
        }
        $head = (string) @fread($fh, 512);
        @fclose($fh);

        foreach ($pragmas as $pragma) {
            if (is_string($pragma) && $pragma !== '' && str_contains($head, $pragma)) {
                return true;
            }
        }

        return false;
    }
}
