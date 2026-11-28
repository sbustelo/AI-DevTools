<?php
// PATH: j0/examples/02-project-bootstrap/php/bootstrap.php
declare(strict_types=1);

/**
 * Example 02: Centralized Project Bootstrap (Move-Safe + Static Fallback)
 *
 * Locates j0/api.php when available; if this folder is deployed standalone without j0/,
 * serves the pre-compiled dist/ bundle and static index.html cleanly.
 */
if (!function_exists('j0_boot')) {
    function j0_boot(string $dir = __DIR__, array $options = []): bool
    {
        if (function_exists('j0_api')) {
            return true;
        }
        if (!defined('J0_EXEC')) {
            define('J0_EXEC', true);
        }
        $targets = is_array($options['targets'] ?? null)
            ? $options['targets']
            : ['_j0/api.php', 'j0/api.php', 'vendors/j0/api.php', 'vendor/j0/api.php', '_ai0/j0/api.php', 'ai0/j0/api.php'];
        $origin = rtrim(str_replace(chr(92), '/', $dir), '/');
        $route = $origin . '/.j0route.php';
        $routes = [];

        if (@is_file($route)) {
            $loaded = @include $route;
            if (is_array($loaded) && ($loaded['__j0_origin__'] ?? '') === $origin) {
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
                        $routes['__j0_origin__'] = $origin;
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

        if (($options['on_missing'] ?? 'strict') === 'strict') {
            http_response_code(500);
            exit('This application requires j0 (https://j0.bustelo.com.ar/). Link or place j0 in your project root.');
        }
        return false;
    }
}

define('APP_HAS_J0', j0_boot(__DIR__, ['on_missing' => 'silent']));
if (APP_HAS_J0) {
    j0_use('assets', 'cache');
} else {
    function j0_asset_path(string $key): ?string { return null; }
    function j0_asset_dir(string $key): ?string { return null; }
}

function app_is_debug(): bool
{
    if (!isset($_GET['debug'])) {
        return false;
    }
    return in_array(strtolower(trim((string) $_GET['debug'])), ['1', 'true', 'yes', 'on'], true);
}

/**
 * @param array<int, string|null> $sources
 */
function app_js_tags(array $sources, string $bundleName): string
{
    $projectRoot = dirname(__DIR__);
    $outBase = $projectRoot . '/dist/' . $bundleName;
    if (!APP_HAS_J0) {
        $minFile = $outBase . '.min.js';
        $ver = is_file($minFile) ? (int) filemtime($minFile) : 1;
        return '<script src="dist/' . htmlspecialchars($bundleName, ENT_QUOTES, 'UTF-8') . '.min.js?v=' . $ver . '"></script>';
    }
    return j0_asset_tags($sources, $outBase, 'dist', 'js', app_is_debug(), ['backup' => false]);
}

/**
 * @param array<int, string> $extraDeps
 */
function app_render_page(string $phpFile, callable $renderCallback, array $extraDeps = []): void
{
    if (!APP_HAS_J0) {
        $htmlFile = (string) preg_replace('/\.php$/i', '.html', $phpFile);
        if (is_file($htmlFile) && !app_is_debug()) {
            readfile($htmlFile);
            exit;
        }
        $renderCallback();
        exit;
    }
    j0_static_html($phpFile, $renderCallback, $extraDeps, app_is_debug());
}