<?php
// PATH: j0/core/j0-use.php
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

if (!function_exists('j0_use')) {
    /**
     * Lazily loads one or more j0 module domains ('core', 'tooling', 'vfs', 'all').
     * Individual j0 files remain 100% standalone-includable via require_once.
     *
     * @return array<int, string>
     */
    function j0_use(string ...$domains): array
    {
        static $loadedDomains = [];
        $root = j0_root();
        $domainDirs = [
            'core'    => [$root . '/core'],
            'tooling' => [$root . '/tooling'],
            'vfs'     => [$root . '/src/vfs', $root . '/src/vfs--remote'],
        ];
        $domainDirs['all'] = array_merge($domainDirs['core'], $domainDirs['tooling'], $domainDirs['vfs']);

        $included = [];
        foreach ($domains as $domain) {
            $key = strtolower(trim($domain));
            if ($key === '') {
                continue;
            }
            if (!isset($domainDirs[$key])) {
                continue;
            }
            if (isset($loadedDomains[$key])) {
                continue;
            }
            $loadedDomains[$key] = true;
            foreach ($domainDirs[$key] as $dir) {
                foreach (j0_scan_files($dir, ['php']) as $phpFile) {
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
     * Resolves logical j0 client asset files without exposing internal directory layout.
     */
    function j0_asset_path(string $key): ?string
    {
        $root = j0_root();
        $map = [
            'kernel.js'     => $root . '/core/01-j0kernel.js',
            'vfs-remote.js' => $root . '/src/vfs--remote/j0010-vfs-remote.js',
        ];
        if (!isset($map[$key])) {
            return null;
        }
        $path = $map[$key];
        if (!is_file($path)) {
            return null;
        }
        return $path;
    }
}

if (!function_exists('j0_asset_dir')) {
    /**
     * Resolves logical j0 module directories without exposing internal directory layout.
     */
    function j0_asset_dir(string $key): ?string
    {
        $root = j0_root();
        $map = [
            'core'       => $root . '/core',
            'tooling'    => $root . '/tooling',
            'vfs'        => $root . '/src/vfs',
            'vfs-remote' => $root . '/src/vfs--remote',
        ];
        if (!isset($map[$key])) {
            return null;
        }
        $dir = $map[$key];
        if (!is_dir($dir)) {
            return null;
        }
        return $dir;
    }
}