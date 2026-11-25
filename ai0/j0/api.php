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
require_once J0_DIR . '/j0-locate.php';
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

            public function projectRoot(): string
            {
                return j0_project_root();
            }

            /**
             * @param string|array<int, string> $dirs
             */
            public function mount(string|array $dirs, ?string $projectRoot = null): void
            {
                j0_mount($dirs, $projectRoot);
            }

            /**
             * @param array{deep?: bool, cache?: bool} $options
             */
            public function src(string $id, array $options = []): string
            {
                return j0_src($id, $options);
            }

            /**
             * @param array<string, mixed> $vars
             * @param array{deep?: bool, cache?: bool} $options
             */
            public function text(string $id, array $vars = [], array $options = []): mixed
            {
                return j0_text($id, $vars, $options);
            }

            /**
             * @param array{deep?: bool, cache?: bool} $options
             */
            public function path(string $id, string $legacyFilename = '', array $options = []): string
            {
                return j0_path($id, $legacyFilename, $options);
            }
        };

        return $facade;
    }
}