<?php
// PATH: ai0/j0/core/j0-use.php
declare(strict_types=1);

if (!function_exists('j0_scan_files')) {
    require_once __DIR__ . '/j0-scan.php';
}

if (!function_exists('j0_root')) {
    /**
     * Returns the normalized root directory of the j0 framework.
     */
    function j0_root(): string
    {
        if (defined('J0_DIR')) {
            return rtrim(str_replace(chr(92), '/', (string) J0_DIR), '/');
        }
        return rtrim(str_replace(chr(92), '/', dirname(__DIR__)), '/');
    }
}

if (!function_exists('j0_discover_domains')) {
    /**
     * Derives available j0 module domains dynamically from the filesystem.
     * Top-level layers (core, tooling, etc.) and src/<module>[--capability] folders
     * are indexed once per request in SORT_NATURAL order.
     *
     * @return array<string, array<int, string>>
     */
    function j0_discover_domains(): array
    {
        static $cachedRoot = null;
        static $cachedDomains = null;

        $root = j0_root();
        if ($cachedDomains !== null && $cachedRoot === $root) {
            return $cachedDomains;
        }

        $domains = [];
        $reserved = ['src', 'quickstart', 'examples', 'docs'];

        $topEntries = @scandir($root);
        if (is_array($topEntries)) {
            sort($topEntries, SORT_NATURAL);
            foreach ($topEntries as $entry) {
                if ($entry === '' || $entry[0] === '.' || $entry[0] === '_') {
                    continue;
                }
                if (in_array($entry, $reserved, true)) {
                    continue;
                }
                $abs = $root . '/' . $entry;
                if (is_dir($abs)) {
                    $domains[$entry] = [$abs];
                }
            }
        }

        $srcRoot = $root . '/src';
        $srcEntries = @scandir($srcRoot);
        if (is_array($srcEntries)) {
            sort($srcEntries, SORT_NATURAL);
            foreach ($srcEntries as $entry) {
                if ($entry === '' || $entry[0] === '.' || $entry[0] === '_') {
                    continue;
                }
                $abs = $srcRoot . '/' . $entry;
                if (!is_dir($abs)) {
                    continue;
                }
                $domains[$entry] = [$abs];
                $baseDomain = explode('--', $entry, 2)[0];
                if ($baseDomain !== '' && $baseDomain !== $entry) {
                    $domains[$baseDomain] = $domains[$baseDomain] ?? [];
                    if (!in_array($abs, $domains[$baseDomain], true)) {
                        $domains[$baseDomain][] = $abs;
                    }
                }
            }
        }

        $allDirs = [];
        foreach ($domains as $dirs) {
            foreach ($dirs as $dir) {
                if (!in_array($dir, $allDirs, true)) {
                    $allDirs[] = $dir;
                }
            }
        }
        $domains['all'] = $allDirs;

        $cachedRoot = $root;
        $cachedDomains = $domains;
        return $domains;
    }
}

if (!function_exists('j0_use')) {
    /**
     * Lazily loads one or more filesystem-derived j0 module domains (e.g. 'core', 'tooling', 'vfs', 'all').
     * Individual j0 files remain 100% standalone-includable via require_once.
     *
     * @return array<int, string>
     */
    function j0_use(string ...$domains): array
    {
        static $loadedDomains = [];
        static $loadedFiles = [];

        $domainDirs = j0_discover_domains();
        $included = [];

        foreach ($domains as $domain) {
            $key = strtolower(trim($domain));
            if ($key === '' || !isset($domainDirs[$key]) || isset($loadedDomains[$key])) {
                continue;
            }
            $loadedDomains[$key] = true;
            foreach ($domainDirs[$key] as $dir) {
                foreach (j0_scan_files($dir, ['php']) as $phpFile) {
                    if (isset($loadedFiles[$phpFile])) {
                        continue;
                    }
                    $loadedFiles[$phpFile] = true;
                    require_once $phpFile;
                    $included[] = $phpFile;
                }
            }
        }

        return $included;
    }
}

if (!function_exists('j0_asset_path')) {
    /**
     * Resolves logical j0 client/server asset files dynamically without hardcoded maps.
     * Matches exact filenames (e.g. '01-j0kernel.js') or prefix-stripped logical keys (e.g. 'kernel.js', 'vfs-remote.js').
     */
    function j0_asset_path(string $key): ?string
    {
        static $indexedRoot = null;
        static $assetMap = null;

        $root = j0_root();
        $cleanKey = ltrim(str_replace(chr(92), '/', trim($key)), '/');
        if ($cleanKey === '' || str_contains($cleanKey, '..')) {
            return null;
        }

        $direct = $root . '/' . $cleanKey;
        if (is_file($direct)) {
            return $direct;
        }

        if ($assetMap === null || $indexedRoot !== $root) {
            $map = [];
            foreach (j0_scan_files($root, [], ['_', '.']) as $absFile) {
                $base = basename($absFile);
                if (!isset($map[$base])) {
                    $map[$base] = $absFile;
                }
                $logical = preg_replace('/^[a-z]?\d+-/', '', $base);
                if (is_string($logical) && $logical !== '' && !isset($map[$logical])) {
                    $map[$logical] = $absFile;
                }
            }
            $indexedRoot = $root;
            $assetMap = $map;
        }

        return $assetMap[$cleanKey] ?? null;
    }
}

if (!function_exists('j0_asset_dir')) {
    /**
     * Resolves logical j0 module directories dynamically from the filesystem.
     */
    function j0_asset_dir(string $key): ?string
    {
        $cleanKey = trim(str_replace(chr(92), '/', $key), '/');
        if ($cleanKey === '' || str_contains($cleanKey, '..')) {
            return null;
        }

        $domains = j0_discover_domains();
        if (isset($domains[$cleanKey][0]) && is_dir($domains[$cleanKey][0])) {
            return $domains[$cleanKey][0];
        }

        $root = j0_root();
        $candidates = [
            $root . '/' . $cleanKey,
            $root . '/src/' . $cleanKey,
            $root . '/src/' . preg_replace('/-/', '--', $cleanKey, 1),
        ];

        foreach ($candidates as $dir) {
            if (is_string($dir) && is_dir($dir)) {
                return $dir;
            }
        }

        return null;
    }
}