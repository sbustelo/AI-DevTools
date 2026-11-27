<?php
// PATH: ai0/j0/examples/01-standalone-php/hello-j0.php
declare(strict_types=1);

/**
 * Example 01: Standalone PHP Script using j0
 *
 * Paste j0_boot() into any standalone PHP file (or require_once quickstart/j0-bootstrap.php).
 * On first run it locates j0/api.php upward and caches _j0route.php in __DIR__.
 */
if (!function_exists('j0_boot')) {
    function j0_boot(
        string $dir = __DIR__,
        array $targets = ['_j0/api.php', 'j0/api.php', 'vendors/j0/api.php', 'vendor/j0/api.php', '_ai0/j0/api.php', 'ai0/j0/api.php']
    ): bool {
        if (function_exists('j0_api')) {
            return true;
        }
        if (!defined('J0_EXEC')) {
            define('J0_EXEC', true);
        }
        $origin = rtrim(str_replace(chr(92), '/', $dir), '/');
        $route = $origin . '/.j0route.php';
        $routes = [];

        if (@is_file($route)) {
            $loaded = @include $route;
            if (is_array($loaded)) {
                $routes = $loaded;
                if (isset($routes['__j0_api__']) && is_string($routes['__j0_api__']) && @is_file($routes['__j0_api__'])) {
                    require_once $routes['__j0_api__'];
                    return true;
                }
                unset($routes['__j0_api__']);
            }
        }

        for ($cursor = $origin, $i = 0; $i <= 12; $i++, $cursor = dirname($cursor)) {
            foreach ($targets as $rel) {
                if (@is_file($hit = $cursor . '/' . $rel)) {
                    if (is_writable($origin)) {
                        $routes['__j0_api__'] = $hit;
                        ksort($routes, SORT_NATURAL);
                        $payload = "<?php\nif (!defined('J0_EXEC')) {\n    exit;\n}\nreturn " . var_export($routes, true) . ";\n";
                        @file_put_contents($route, $payload, LOCK_EX);
                    }
                    require_once $hit;
                    return true;
                }
            }
            if (dirname($cursor) === $cursor) {
                break;
            }
        }

        http_response_code(500);
        exit('This script requires j0 (https://j0.bustelo.com.ar/). Link or place j0 in your project root.');
    }
}

j0_boot(__DIR__);
j0_use('core', 'bundler');

$phpFiles = j0_scan_files(j0_root() . '/core', ['php']);

header('Content-Type: text/plain; charset=utf-8');
echo "j0 root: " . j0_root() . "\n";
echo "Core PHP modules discovered (" . count($phpFiles) . "):\n";
foreach ($phpFiles as $file) {
    echo " - " . basename($file) . "\n";
}