<?php
// PATH: j0/examples/03-locator-and-templates/demo-locate.php
declare(strict_types=1);

/**
 * Example 03 (Step 3 of 5): Union Mount Locator (S / j0_path) & Templates (T)
 *
 * What this example does:
 *   Mounts the local tpl/ directory into j0's locator and resolves resources by logical ID
 *   using S() (URL/asset source), j0_path() (server path), and T() (HTML template + vars +
 *   conditional <j0-ifphp> / <j0-ifjs> blocks).
 *
 * What you should see on screen:
 *   The exact values returned by j0_project_root(), S(), and j0_path(), followed by the
 *   rendered greeting-card template below.
 *
 * Why this matters:
 *   Templates and assets stay organized in logical folders without hardcoding filesystem paths
 *   across your PHP pages.
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
        $targets = ['_j0/api.php', 'j0/api.php', 'vendors/j0/api.php', 'vendor/j0/api.php', '_ai0/j0/api.php', 'ai0/j0/api.php'];
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

        http_response_code(500);
        exit('This example requires j0 (https://ai0.bustelo.com.ar/). Link or place j0 in your project root.');
    }
}

j0_boot(__DIR__);
j0_use('core');

j0_locate_init(__DIR__);
j0_mount('tpl', __DIR__);

$logoUrl = S('tpl/h0010-greeting-card.html');
$selfPhp = j0_path('demo-locate.php');
$renderedCard = T('greeting-card', [
    'title'     => 'Location-Independent Templates',
    'user'      => 'Architect',
    'badgeHtml' => '<strong>j0 v2</strong>',
]);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>j0 Example 03 — Locator &amp; Templates (S / T)</title>
    <style>
        /* Minimal standalone styles kept inline before CSS bundling is introduced in later examples */
        :root {
            --bg: #111317;
            --card: #191d24;
            --ink: #eef1f6;
            --muted: #9aa4b2;
            --line: #2a313d;
            --ok: #34d399;
        }
        @media (prefers-color-scheme: light) {
            :root {
                --bg: #f5f7fa;
                --card: #ffffff;
                --ink: #161a20;
                --muted: #556070;
                --line: #dde2ea;
                --ok: #059669;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 28px 20px 48px;
            background: var(--bg);
            color: var(--ink);
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 14px;
            line-height: 1.55;
        }
        .wrap { max-width: 780px; margin: 0 auto; display: flex; flex-direction: column; gap: 16px; }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 18px 20px;
        }
        h1 { margin: 0 0 8px; font-size: 20px; }
        h2 { margin: 0 0 10px; font-size: 15px; }
        p { margin: 0 0 10px; }
        p:last-child { margin-bottom: 0; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            color: var(--ok);
            border: 1px solid var(--ok);
            margin-bottom: 10px;
        }
        code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; word-break: break-all; }
        .kv { margin: 0; display: grid; grid-template-columns: 210px 1fr; gap: 6px 12px; }
        .kv dt { color: var(--muted); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; }
        .kv dd { margin: 0; }
        .j0-demo-card {
            border-left: 3px solid var(--ok);
            padding-left: 14px;
        }
        footer {
            margin-top: 8px;
            padding-top: 14px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 12.5px;
        }
        footer a { color: inherit; }
    </style>
</head>
<body>
<main class="wrap">
    <section class="card">
        <span class="badge">OK • Mounted tpl/</span>
        <h1>03. Union Mount Locator (S / j0_path) &amp; Templates (T)</h1>
        <p><strong>What this does:</strong> Registers <code>tpl/</code> via <code>j0_mount()</code>, looks up resource URLs with <code>S()</code> and file paths with <code>j0_path()</code>, and renders <code>tpl/h0010-greeting-card.html</code> with <code>T()</code>.</p>
        <p><strong>What you are seeing:</strong> Below are the resolved values on the server and the rendered card output (note how <code>&lt;j0-ifjs&gt;</code> is stripped during PHP rendering while <code>{{var}}</code> is escaped and <code>{{{raw}}}</code> is injected).</p>
        <p><strong>Why it matters:</strong> Templates stay decoupled from hardcoded paths and can be overridden or layered cleanly.</p>
    </section>

    <section class="card">
        <h2>Locator Results</h2>
        <dl class="kv">
            <dt>j0_project_root()</dt>
            <dd><code><?= htmlspecialchars(j0_project_root(), ENT_QUOTES, 'UTF-8') ?></code></dd>
            <dt>S('tpl/h0010-greeting-card.html')</dt>
            <dd><code><?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?></code></dd>
            <dt>j0_path('demo-locate.php')</dt>
            <dd><code><?= htmlspecialchars($selfPhp, ENT_QUOTES, 'UTF-8') ?></code></dd>
        </dl>
    </section>

    <section class="card">
        <h2>Rendered Template via T('greeting-card', ...)</h2>
        <?= $renderedCard . "\n" ?>
    </section>

    <footer>
        Built with <strong>j0</strong> (zero-dependency PHP/JS library), part of the <a href="https://ai0.bustelo.com.ar/">ai0 DevTools suite</a> • Santiago Bustelo
    </footer>
</main>
</body>
</html>