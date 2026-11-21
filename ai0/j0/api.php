<?php
// PATH: j0/api.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    define('J0_EXEC', true);
}

if (!defined('J0_DIR')) {
    define('J0_DIR', rtrim(str_replace(chr(92), '/', __DIR__), '/'));
}

require_once J0_DIR . '/core/j0-use.php';
j0_use('core');

if (!function_exists('j0_api')) {
    /**
     * Public facade entry point for j0 capabilities.
     * Consumers include j0/api.php and request domains/assets without hardcoding internal paths.
     */
    function j0_api(): object
    {
        static $facade = null;
        if ($facade !== null) {
            return $facade;
        }

        $facade = new class {
            public function root(): string
            {
                return j0_root();
            }

            /**
             * @return array<int, string>
             */
            public function use(string ...$domains): array
            {
                return j0_use(...$domains);
            }

            public function assetPath(string $key): ?string
            {
                return j0_asset_path($key);
            }

            public function assetDir(string $key): ?string
            {
                return j0_asset_dir($key);
            }
        };

        return $facade;
    }
}