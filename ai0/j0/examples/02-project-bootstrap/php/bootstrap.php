<?php
// PATH: ai0/j0/examples/02-project-bootstrap/php/bootstrap.php
declare(strict_types=1);

/**
 * Example 02: Centralized Project Bootstrap
 *
 * Only this file locates j0/api.php (caching _j0route.php in php/_j0route.php).
 * All pages in the project include this file via __DIR__ and call the project helpers below.
 */
if (!function_exists('j0_boot')) {
    function j0_boot(
        string $dir = __DIR__,
        array $targets = ['_j0/api.php', 'j0/api.php', 'vendors/j0/api.php', 'vendor/j0/api.php', '_ai0/j0/api.php', 'ai0/j0/api.php']
    ): bool {
        if (function_exists('j0_api')) {
            return true;
        }
        $origin = rtrim(str_replace(chr(92), '/', $dir), '/');
        $route = $origin . '/_j0route.php';

        if (is_file($route)) {
            $cached = @include $route;
            if (is_string($cached) && is_file($cached)) {
                require_once $cached;
                return true;
            }
            @unlink($route);
        }

        for ($cursor = $origin, $i = 0; $i <= 12; $i++, $cursor = dirname($cursor)) {
            foreach ($targets as $rel) {
                if (is_file($hit = $cursor . '/' . $rel)) {
                    if (is_writable($origin)) {
                        @file_put_contents($route, "<?php\nreturn " . var_export($hit, true) . ";\n", LOCK_EX);
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

j0_boot(__DIR__);
j0_use('tooling');

function app_is_debug(): bool
{
    if (!isset($_GET['debug'])) {
        return false;
    }
    return in_array(strtolower(trim((string) $_GET['debug'])), ['1', 'true', 'yes', 'on'], true);
}

/**
 * @param array<int, string> $sources
 */
function app_js_tags(array $sources, string $bundleName): string
{
    $projectRoot = dirname(__DIR__);
    $outBase = $projectRoot . '/dist/' . $bundleName;
    return j0_asset_tags($sources, $outBase, 'dist', 'js', app_is_debug(), ['backup' => false]);
}

/**
 * @param array<int, string> $extraDeps
 */
function app_render_page(string $phpFile, callable $renderCallback, array $extraDeps = []): void
{
    if (app_is_debug()) {
        $renderCallback();
        return;
    }
    j0_static_html($phpFile, $renderCallback, $extraDeps);
}