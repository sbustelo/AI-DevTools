<?php
// PATH: ai0/j0/src/vfs/p0010-vfs-ssr.php
declare(strict_types=1);

if (!function_exists('j0_vfs_read_ui_cookie')) {
    /**
     * Decodes the compact j0_ui cookie (e.g. "t:d|s:o") on the server so SSR renders
     * the exact user UI state on first byte with zero FOUC.
     *
     * @param array<string, array{key: string, values?: array<string, string>}> $map
     * @return array<string, string>
     */
    function j0_vfs_read_ui_cookie(array $map = [], string $cookieName = 'j0_ui'): array
    {
        if ($map === []) {
            $map = [
                't' => [
                    'key'    => 'theme',
                    'values' => ['l' => 'light', 'd' => 'dark'],
                ],
            ];
        }

        $raw = (string) ($_COOKIE[$cookieName] ?? '');
        if ($raw === '') {
            return [];
        }

        $decoded = urldecode($raw);
        $result = [];

        foreach (explode('|', $decoded) as $pair) {
            $sep = strpos($pair, ':');
            if ($sep === false || $sep === 0) {
                continue;
            }
            $shortKey = substr($pair, 0, $sep);
            $shortVal = substr($pair, $sep + 1);
            if (!isset($map[$shortKey])) {
                continue;
            }
            $spec = $map[$shortKey];
            $fullKey = $spec['key'];
            $result[$fullKey] = $spec['values'][$shortVal] ?? $shortVal;
        }

        return $result;
    }
}

if (!function_exists('j0_vfs_ui_attr')) {
    /**
     * @param array<string, array{key: string, values?: array<string, string>}> $map
     */
    function j0_vfs_ui_attr(string $key, string $default = '', array $map = []): string
    {
        $state = j0_vfs_read_ui_cookie($map);
        $val = $state[$key] ?? $default;
        return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    }
}