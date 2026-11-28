<?php
// PATH: j0/examples/01-standalone-php/hello-j0.php
declare(strict_types=1);

/**
 * Example 01 (Step 1 of 5): Layer 0 Locator & Core Scanner
 *
 * What this example does:
 *   Locates j0/api.php by walking upward from __DIR__ without Composer or hardcoded paths,
 *   caches the resolved location in a local .j0route.php bound to __DIR__ (__j0_origin__),
 *   and lists the core PHP files discovered inside j0/core/.
 *
 * What you should see on screen:
 *   A clean status card showing OK, the resolved origin/target paths, and the list of core PHP files.
 *   If you copy this folder elsewhere and reload, .j0route.php detects the move and re-binds cleanly.
 *
 * Why this matters:
 *   You can drop j0 into any single PHP script or shared hosting folder without Composer or brittle
 *   relative include chains.
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
        exit('This script requires j0 (https://ai0.bustelo.com.ar/). Link or place j0 in your project root.');
    }
}

j0_boot(__DIR__);
j0_use('core', 'bundler');

$j0Root = j0_root();
$phpFiles = j0_scan_files($j0Root . '/core', ['php']);
$routeFile = rtrim(str_replace(chr(92), '/', __DIR__), '/') . '/.j0route.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>j0 Example 01 — Standalone Locator &amp; Core Scan</title>
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
        h2 { margin: 0 0 10px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); }
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
        code, pre { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; }
        .kv { margin: 0; display: grid; grid-template-columns: 140px 1fr; gap: 6px 12px; }
        .kv dt { color: var(--muted); }
        .kv dd { margin: 0; word-break: break-all; }
        ul { margin: 0; padding-left: 18px; }
        li { margin: 3px 0; }
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
        <span class="badge">OK • j0_boot() resolved</span>
        <h1>01. Standalone PHP Locator &amp; Core Scan</h1>
        <p><strong>What this does:</strong> Finds <code>j0/api.php</code> walking upward from this script's folder, caches the path in <code>.j0route.php</code> bound to <code>__DIR__</code>, and loads <code>core</code> + <code>bundler</code>.</p>
        <p><strong>What you are seeing:</strong> A healthy boot confirmation. If you copy this folder somewhere else and reload, <code>.j0route.php</code> notices the new directory and re-resolves automatically.</p>
        <p><strong>Why it matters:</strong> Zero Composer, zero vendor lock-in, and no fragile <code>../../..</code> include paths when scripts move.</p>
    </section>

    <section class="card">
        <h2>Resolved Paths</h2>
        <dl class="kv">
            <dt>Origin Dir</dt>
            <dd><code><?= htmlspecialchars(__DIR__, ENT_QUOTES, 'UTF-8') ?></code></dd>
            <dt>Route Cache</dt>
            <dd><code><?= htmlspecialchars($routeFile, ENT_QUOTES, 'UTF-8') ?></code> (<?= is_file($routeFile) ? 'present' : 'not written' ?>)</dd>
            <dt>j0 Root</dt>
            <dd><code><?= htmlspecialchars($j0Root, ENT_QUOTES, 'UTF-8') ?></code></dd>
        </dl>
    </section>

    <section class="card">
        <h2>Core PHP Files Discovered (<?= count($phpFiles) ?>)</h2>
        <ul>
            <?php foreach ($phpFiles as $file): ?>
                <li><code><?= htmlspecialchars(basename($file), ENT_QUOTES, 'UTF-8') ?></code></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <footer>
        Built with <strong>j0</strong> (zero-dependency PHP/JS library), part of the <a href="https://ai0.bustelo.com.ar/">ai0 DevTools suite</a> • Santiago Bustelo
    </footer>
</main>
</body>
</html>