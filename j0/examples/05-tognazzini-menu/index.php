<?php
// PATH: j0/examples/05-tognazzini-menu/index.php
declare(strict_types=1);

/**
 * Example 05: Full Self-Contained j0 App (Tognazzini Safe-Triangle Submenu)
 *
 * This example combines every j0 portability feature so this folder can be copied anywhere:
 *   1. Embedded Move-Safe Locator (`j0_boot` with `on_missing => 'silent'`):
 *      - No fragile `require_once '../../...'` paths.
 *      - Automatically invalidates `.j0route.php` if the folder is copied or moved.
 *   2. Self-Contained `dist/` Bundles (`j0_asset_tags`):
 *      - Concatenates & minifies IPAX tokens + `0510-ui-menus` + local CSS/JS into `dist/demo-menu.min.{css,js}`.
 *      - Emits relative `dist/...` URLs so the app works at any path depth or when `j0/` sits above DOCUMENT_ROOT.
 *      - Pass `?debug=1` to bypass minification/caching and inspect readable sources.
 *   3. Static HTML Snapshot (`j0_static_html` -> `index.html`):
 *      - Generates `index.html` automatically whenever any source changes.
 *      - Upload this folder (with `index.html` + `dist/`) to any static host (GitHub Pages, S3, Netlify) or PHP host
 *        without `j0/` and it runs identically with zero external dependencies.
 */

// ─── STEP 1: Move-Safe Layer 0 Locator (with silent standalone fallback) ───
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

        if (($options['on_missing'] ?? 'strict') === 'strict') {
            http_response_code(500);
            exit('This application requires j0 (https://j0.bustelo.com.ar/).');
        }
        return false;
    }
}

$isDebug = isset($_GET['debug']) && in_array(strtolower(trim((string) $_GET['debug'])), ['1', 'true', 'yes', 'on'], true);
$hasJ0 = j0_boot(__DIR__, ['on_missing' => 'silent']);

// ─── STEP 2 & 3: Load j0 Domains & Build/Resolve Self-Contained dist/ Bundles ───
if ($hasJ0) {
    j0_use('core', 'assets', 'bundler', 'cache', 'ui-menus');

    // Pass raw array (no array_filter) so j0_asset_tags protects dist/ if a source is ever missing
    $cssSources = [
        j0_asset_dir('ipax'),
        j0_asset_path('c0010-j0-menus.css'),
        __DIR__ . '/c0010-demo-tognazzini.css',
    ];
    $jsSources = [
        j0_asset_path('j0010-kernel.js'),
        j0_asset_path('j0506-floater.js'),
        j0_asset_path('j0010-j0-menus.js'),
        __DIR__ . '/j0010-demo-tognazzini.js',
    ];

    $cssTags = j0_asset_tags($cssSources, __DIR__ . '/dist/demo-menu', 'dist', 'css', $isDebug);
    $jsTags = j0_asset_tags($jsSources, __DIR__ . '/dist/demo-menu', 'dist', 'js', $isDebug);
} else {
    // Standalone PHP mode (folder copied without j0/): serve static index.html or pre-built dist/ bundles
    if (!$isDebug && is_file(__DIR__ . '/index.html')) {
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/index.html');
        exit;
    }
    $cssVer = is_file(__DIR__ . '/dist/demo-menu.min.css') ? (int) filemtime(__DIR__ . '/dist/demo-menu.min.css') : 1;
    $jsVer = is_file(__DIR__ . '/dist/demo-menu.min.js') ? (int) filemtime(__DIR__ . '/dist/demo-menu.min.js') : 1;
    $cssTags = '<link rel="stylesheet" href="dist/demo-menu.min.css?v=' . $cssVer . '">';
    $jsTags = '<script src="dist/demo-menu.min.js?v=' . $jsVer . '"></script>';
}

// ─── STEP 4: Render Page & Persist Static index.html Snapshot ───
$renderDemo = static function () use ($cssTags, $jsTags): void {
    ?>
<!DOCTYPE html>
<html lang="en" data-ipax-theme="dark" data-demo-show-safezone="true">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>j0 Example 05 — Bruce Tognazzini Safe-Triangle Menu</title>
    <?= $cssTags . "\n" ?>
</head>
<body>
<main class="demo-shell">
    <section class="demo-hero">
        <h1>Bruce Tognazzini's Safe-Triangle Submenu Pattern (1986)</h1>
        <p class="demo-muted">
            When moving the pointer diagonally from a parent menu item toward the bottom of an open submenu,
            the cursor naturally crosses neighboring rows. Naive hover menus immediately close or switch submenus.
            <code>j0/src/0510-ui-menus</code> projects a live triangular safe-zone polygon (<code>clip-path</code>)
            from the pointer vertex to the submenu's near corners, preserving hover intent during diagonal travel.
        </p>
        <div class="demo-controls">
            <label class="demo-toggle">
                <input type="checkbox" checked data-demo-safezone-toggle>
                <span>Show Safe-Zone Triangle (<code>--submenu-hover-area</code>)</span>
            </label>
			
            Switch theme:
			<button type="button" class="demo-toggle" data-demo-theme-toggle>☀️ Light / 🌙 Dark</button>
            <span class="demo-status" data-demo-status>Hover "Export ▸" and aim diagonally toward "SVG Vector"</span>
        </div>
    </section>

    <div class="demo-grid">
        <section class="demo-card">
            <div class="demo-card-head">
                <h2>1. Without Safe-Triangle</h2>
                <span class="demo-badge demo-badge--bad">Naive Hover</span>
            </div>
            <p class="demo-muted">Hover <strong>Export ▸</strong>, then move diagonally toward <strong>SVG Vector</strong>. Crossing <em>Rename</em> kills the submenu.</p>
            <div class="demo-menu-stage" data-demo-naive-stage>
                <div class="demo-menu" role="menu" data-demo-naive-menu>
                    <button type="button" role="menuitem" class="demo-item j0-menu-item--has-submenu" data-demo-naive-trigger>
                        <span>📤 Export</span>
                        <span class="j0-submenu-caret">▸</span>
                    </button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Naive: Rename">✏️ Rename</button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Naive: Duplicate">📄 Duplicate</button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Naive: Snapshot">📦 Snapshot</button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Naive: Archive">🗜️ Archive</button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Naive: Discard">🗑️ Discard</button>
                </div>
                <div class="demo-naive-sub" role="menu" data-demo-naive-sub data-j0-hidden="true">
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Naive Export: PNG">PNG Image</button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Naive Export: WebP">WebP Optimized</button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Naive Export: PDF">PDF Document</button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Naive Export: JSON">JSON Bundle</button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Naive Export: SVG">SVG Vector</button>
                </div>
            </div>
        </section>

        <section class="demo-card">
            <div class="demo-card-head">
                <h2>2. With <code>j0</code> 0510-ui-menus</h2>
                <span class="demo-badge demo-badge--good">Tognazzini Safe-Triangle</span>
            </div>
            <p class="demo-muted">Hover <strong>Export ▸</strong> and glide diagonally through the cyan triangle straight to <strong>SVG Vector</strong>.</p>
            <div class="demo-menu-stage">
                <div class="demo-menu" role="menu">
                    <button type="button" role="menuitem" class="demo-item j0-menu-item--has-submenu" data-j0-submenu-trigger="demo-export">
                        <span>📤 Export</span>
                        <span class="j0-submenu-caret">▸</span>
                    </button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Tognazzini: Rename">✏️ Rename</button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Tognazzini: Duplicate">📄 Duplicate</button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Tognazzini: Snapshot">📦 Snapshot</button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Tognazzini: Archive">🗜️ Archive</button>
                    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Tognazzini: Discard">🗑️ Discard</button>
                </div>
            </div>
        </section>
    </div>

    <div class="demo-pad">
        <strong>Anywhere-on-Screen Test:</strong> Right-Click or <code>Ctrl+Click</code> anywhere in this window (try near the right or bottom edges to watch automatic submenu flipping and triangle inversion).
    </div>

	<footer class="demo-footer">
	Built with <strong>j0</strong> (zero-dependency PHP/JS library), part of the <a href="https://ai0.bustelo.com.ar/">ai0 DevTools suite</a> • Santiago Bustelo
</footer>
</main>

<div class="j0-submenu" role="menu" data-j0-submenu="demo-export" data-j0-hidden="true">
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Export: PNG">PNG Image</button>
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Export: WebP">WebP Optimized</button>
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Export: PDF">PDF Document</button>
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Export: JSON">JSON Bundle</button>
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Export: SVG">SVG Vector</button>
</div>

<div class="demo-menu demo-menu--floating" role="menu" data-demo-ctx-menu data-j0-hidden="true">
    <button type="button" role="menuitem" class="demo-item j0-menu-item--has-submenu" data-j0-submenu-trigger="demo-ctx-color">
        <span>🎨 Label Color</span>
        <span class="j0-submenu-caret">▸</span>
    </button>
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Context: Rename">✏️ Rename</button>
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Context: Snapshot">📦 Snapshot</button>
    <div class="demo-divider" role="separator"></div>
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Context: Discard">🗑️ Discard</button>
</div>

<div class="j0-submenu" role="menu" data-j0-submenu="demo-ctx-color" data-j0-hidden="true">
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Color: Red">🔴 Red</button>
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Color: Orange">🟠 Orange</button>
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Color: Yellow">🟡 Yellow</button>
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Color: Green">🟢 Green</button>
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Color: Blue">🔵 Blue</button>
    <button type="button" role="menuitem" class="demo-item" data-demo-leaf="Color: Violet">🟣 Violet</button>
</div>




<?= $jsTags . "\n" ?>
</body>
</html>
    <?php
};

header('Content-Type: text/html; charset=utf-8');
if ($hasJ0) {
    j0_static_html(__FILE__, $renderDemo, [], $isDebug);
}
$renderDemo();