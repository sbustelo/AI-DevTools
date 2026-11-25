<?php
// PATH: j0/core/j0-config.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    define('J0_EXEC', true);
}

if (!function_exists('j0_is_local')) {
    /**
     * Determines whether execution is running in a local development environment.
     */
    function j0_is_local(bool $allowCli = false): bool
    {
        if ($allowCli && PHP_SAPI === 'cli') {
            return true;
        }

        $addr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

        if (in_array($addr, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        return $host === 'localhost'
            || str_starts_with($host, 'localhost:')
            || str_ends_with($host, '.test')
            || str_contains($host, '.test:')
            || str_ends_with($host, '.local')
            || str_contains($host, '.local:');
    }
}

if (!function_exists('j0_config_domains')) {
    /**
     * Builds the 4-tier [_]<namespace>config resolution list ordered from highest priority (1) to lowest (4).
     * Supports both 'ai0' ([_]ai0config) and 'j0' ([_]j0config) cascades.
     *
     * @return array<int, string>
     */
    function j0_config_domains(string $projectRoot, string $engineDir, string $namespace = 'ai0'): array
    {
        $bs = chr(92);
        $rootClean = rtrim(str_replace($bs, '/', $projectRoot), '/');
        $engineClean = rtrim(str_replace($bs, '/', $engineDir), '/');
        $ns = preg_replace('/[^a-zA-Z0-9_-]/', '', ltrim($namespace, '_')) ?: 'ai0';
        $folder = $ns . 'config';

        $candidates = [
            $rootClean . '/_' . $folder,
            $rootClean . '/' . $folder,
            $engineClean . '/_' . $folder,
            $engineClean . '/' . $folder,
        ];

        return array_values(array_unique($candidates));
    }
}

if (!function_exists('j0_config_json')) {
    /**
     * Loads and recursively merges declarative JSON configs from lowest priority (4) to highest (1).
     * Files whose basename starts with '_' are treated as disabled and ignored.
     *
     * @param array<int, string> $domains
     * @return array<string, mixed>
     */
    function j0_config_json(string $name, array $domains): array
    {
        $cleanName = ltrim(str_replace(chr(92), '/', $name), '/');
        if ($cleanName === '' || str_contains($cleanName, '..')) {
            return [];
        }
        if (str_starts_with(basename($cleanName), '_')) {
            return [];
        }

        $merged = [];
        foreach (array_reverse($domains) as $dir) {
            $file = rtrim($dir, '/') . '/' . $cleanName . '.json';
            if (!is_file($file)) {
                continue;
            }
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $merged = array_replace_recursive($merged, $decoded);
            }
        }

        return $merged;
    }
}

if (!function_exists('j0_config_php')) {
    /**
     * Resolves sensitive PHP configuration files (guarded by J0_EXEC) using top-down first-match (1 -> 4).
     *
     * @param array<int, string> $domains
     * @return array<string, mixed>|null
     */
    function j0_config_php(string $name, array $domains): ?array
    {
        $cleanName = ltrim(str_replace(chr(92), '/', $name), '/');
        if ($cleanName === '' || str_contains($cleanName, '..')) {
            return null;
        }
        if (str_starts_with(basename($cleanName), '_')) {
            return null;
        }

        foreach ($domains as $dir) {
            $file = rtrim($dir, '/') . '/' . $cleanName . '.php';
            if (!is_file($file)) {
                continue;
            }
            $data = require $file;
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }
}
