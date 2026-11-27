<?php
// PATH: j0/examples/05-tognazzini-menu/index.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/quickstart/j0-bootstrap.php';

j0_boot(__DIR__);
j0_use('core', 'assets', 'bundler', 'ui-menus');

$cssSources = array_values(array_filter([
    j0_asset_dir('ipax'),
    j0_asset_path('c0010-j0-menus.css'),
    __DIR__ . '/c0010-demo-tognazzini.css',
]));
$jsSources = array_values(array_filter([
    j0_asset_path('j0010-kernel.js'),
    j0_asset_path('j0506-floater.js'),
    j0_asset_path('j0010-j0-menus.js'),
    __DIR__ . '/j0010-demo-tognazzini.js',
]));

$cssTags = j0_asset_tags($cssSources, __DIR__ . '/dist/demo-menu', 'dist', 'css', true);
$jsTags = j0_asset_tags($jsSources, __DIR__ . '/dist/demo-menu', 'dist', 'js', true);

header('Content-Type: text/html; charset=utf-8');
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