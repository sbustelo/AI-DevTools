<?php
// PATH: j0/examples/02-project-bootstrap/index.php
declare(strict_types=1);

/**
 * Example 02 (Step 2 of 5): Centralized Bootstrap, JS Bundle & Static HTML Cache
 *
 * What this example does:
 *   Centralizes j0_boot() in php/bootstrap.php, compiles j0010-kernel.js + 0100-vfs into
 *   dist/app-runtime.min.js, and writes a static index.html snapshot (bypassed with ?debug=1).
 *
 * What you should see on screen:
 *   The active mode (static HTML cache vs. ?debug=1 live PHP), the compiled bundle size,
 *   and a live client check proving globalThis.j0.bus and globalThis.j0.vfs are running.
 *
 * Why this matters:
 *   You get compiled assets and static HTML speed on standard hosting without Node/npm
 *   or manual build commands, while keeping a standalone fallback if j0 is absent.
 */

require_once __DIR__ . '/php/bootstrap.php';

// Compute asset tags BEFORE app_render_page() so j0_static_html() tracks source mtimes.
$kernelJs = j0_asset_path('j0010-kernel.js');
$vfsDir = j0_asset_dir('vfs');
$sources = [$kernelJs, $vfsDir];
$scriptTags = app_js_tags($sources, 'app-runtime');

$minBundlePath = __DIR__ . '/dist/app-runtime.min.js';
$bundleBytes = is_file($minBundlePath) ? (int) filesize($minBundlePath) : 0;
$modeLabel = app_is_debug()
    ? 'Live PHP (?debug=1 bypasses static HTML)'
    : (APP_HAS_J0 ? 'Static HTML snapshot (index.html) via j0' : 'Standalone fallback (no j0 present)');

app_render_page(__FILE__, static function () use ($scriptTags, $bundleBytes, $modeLabel): void {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>j0 Example 02 — Project Bootstrap, Bundle &amp; Static HTML</title>
    <style>
        /* Minimal standalone styles kept inline before CSS bundling is introduced in later examples */
        :root {
            --bg: #111317;
            --card: #191d24;
            --ink: #eef1f6;
            --muted: #9aa4b2;
            --line: #2a313d;
            --ok: #34d399;
            --accent: #60a5fa;
        }
        @media (prefers-color-scheme: light) {
            :root {
                --bg: #f5f7fa;
                --card: #ffffff;
                --ink: #161a20;
                --muted: #556070;
                --line: #dde2ea;
                --ok: #059669;
                --accent: #1d4ed8;
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
        code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; }
        button {
            padding: 7px 12px;
            border-radius: 7px;
            border: 1px solid var(--line);
            background: var(--bg);
            color: var(--ink);
            font: inherit;
            font-size: 13px;
            cursor: pointer;
        }
        button:hover { border-color: var(--accent); }
        .status { margin-left: 10px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12.5px; color: var(--accent); }
        footer {
            margin-top: 8px;
            padding-top: 14px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 12.5px;
        }
        footer a { color: inherit; }
    </style>
    <?= $scriptTags . "\n" ?>
</head>
<body>
<main class="wrap">
    <section class="card">
        <span class="badge">OK • Runtime loaded</span>
        <h1>02. Centralized Bootstrap, Bundle &amp; Static HTML</h1>
        <p><strong>What this does:</strong> Loads <code>php/bootstrap.php</code> once, compiles <code>j0010-kernel.js</code> + <code>0100-vfs</code> into <code>dist/app-runtime.min.js</code> (<?= number_format($bundleBytes) ?> bytes), and saves a static <code>index.html</code> snapshot.</p>
        <p><strong>What you are seeing:</strong> Current render mode is <strong><?= htmlspecialchars($modeLabel, ENT_QUOTES, 'UTF-8') ?></strong>. Add <code>?debug=1</code> to the URL to bypass the static HTML cache and see unminified sources.</p>
        <p><strong>Why it matters:</strong> Fast static delivery in production with zero Node build pipeline, and automatic rebuilds when source files change.</p>
    </section>

    <section class="card">
        <h2>Live Browser Runtime Check (j0.bus + j0.vfs)</h2>
        <p>Click the button below to emit an event on <code>j0.bus</code> and write a sample key in <code>j0.vfs</code>:</p>
        <button type="button" data-demo-ping>Emit Event &amp; Write VFS</button>
        <span class="status" data-demo-runtime-status>Checking j0 global...</span>
    </section>

    <footer>
        Built with <strong>j0</strong> (zero-dependency PHP/JS library), part of the <a href="https://ai0.bustelo.com.ar/">ai0 DevTools suite</a> • Santiago Bustelo
    </footer>
</main>
<script>
(function () {
    'use strict';
    const statusEl = document.querySelector('[data-demo-runtime-status]');
    const pingBtn = document.querySelector('[data-demo-ping]');
    const j0 = globalThis.j0;

    if (!statusEl) {
        return;
    }
    if (!j0 || !j0.bus || !j0.vfs) {
        statusEl.textContent = 'j0 client runtime not detected';
        return;
    }

    let count = 0;
    statusEl.textContent = 'Ready: j0.bus + j0.vfs active';

    j0.bus.on('example02:ping', (payload) => {
        count += 1;
        statusEl.textContent = 'Event #' + count + ' received (vfs ping=' + payload.ping + ')';
    });

    if (pingBtn) {
        pingBtn.addEventListener('click', () => {
            const nextVal = 'ok-' + (count + 1);
            j0.vfs.set('example02_ping', nextVal);
            j0.bus.emit('example02:ping', { ping: nextVal });
        });
    }
})();
</script>
</body>
</html>
    <?php
});