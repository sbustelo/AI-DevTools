<?php
// PATH: ai0/j0/quickstart/j0-bootstrap-compact.php
declare(strict_types=1);

if (!function_exists('j0_boot')) {
    /**
     * Compact Layer 0 j0 locator with .j0route.php self-healing associative cache.
     *
     * @param array<int, string> $targets
     */
    function j0_boot(
        string $dir = '',
        array $targets = ['_j0/api.php', 'j0/api.php', 'vendors/j0/api.php', 'vendor/j0/api.php', '_ai0/j0/api.php', 'ai0/j0/api.php']
    ): bool {
        if (!defined('J0_EXEC')) {
            define('J0_EXEC', true);
        }
        if (function_exists('j0_api')) {
            return true;
        }
        $base = $dir !== '' ? $dir : dirname(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? __FILE__);
        $origin = rtrim(str_replace(chr(92), '/', $base), '/');
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
                        $tmp = $origin . '/.__j0route_' . uniqid('', true);
                        if (@file_put_contents($tmp, $payload) !== false && @rename($tmp, $route)) {
                            if (function_exists('opcache_invalidate')) {
                                @opcache_invalidate($route, true);
                            }
                        } else {
                            @unlink($tmp);
                        }
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
        exit('This application requires j0 (https://j0.bustelo.com.ar/). Link or place j0 in your project root.');
    }
}