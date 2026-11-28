# `j0 v2` Complete Function, Module & Pattern Reference

Every file under `ai0/j0/` is a zero-dependency PHP 8.2+ or Vanilla ES2020+ module organized into `quickstart/`, `api.php`, `core/` (pure PHP), and OS-layered `src/`. Every PHP file can be included standalone via `require_once` or loaded lazily by domain via `j0_use()`.

---

## 1. Layer 0 Bootstrap (`j0/quickstart/`)

### `j0_boot()` — Self-Healing Upward Locator (`j0-bootstrap.php` / `j0-bootstrap-compact.php`)
Walks lexically upward from the caller directory (preserving symlinks), locates `j0/api.php`, caches `'__j0_api__'` in `.j0route.php`, and loads the public facade.

```php
<?php
require_once __DIR__ . '/j0/quickstart/j0-bootstrap.php';

// Full options signature (j0-bootstrap.php)
$ok = j0_boot(__DIR__, [
    'targets'     => ['_j0/api.php', 'j0/api.php', '_ai0/j0/api.php'],
    'cache_route' => true,
    'max_levels'  => 12,
    'on_missing'  => 'strict', // 'strict' (HTTP 500 + exit) or 'silent' (returns bool)
]);

// Compact signature (j0-bootstrap-compact.php)
// require_once __DIR__ . '/j0/quickstart/j0-bootstrap-compact.php';
// $ok = j0_boot(__DIR__, ['_j0/api.php', 'j0/api.php']);
```

---

## 2. Public Boundary Facade (`j0/api.php`)

### `j0_api()` — Single Consumer Entrypoint
Returns a singleton facade exposing core loader and locator capabilities without hardcoding internal `j0` subdirectories.

```php
<?php
require_once __DIR__ . '/j0/api.php';

$j0 = j0_api();
$rootDir     = $j0->root();
$loadedFiles = $j0->use('tooling', 'vfs');
$kernelPath  = $j0->assetPath('kernel.js');
$vfsDir      = $j0->assetDir('vfs');
$projectRoot = $j0->projectRoot();

$j0->mount(['tpl', 'assets'], $projectRoot);
$logoUrl     = $j0->src('logo.svg');
$cardHtml    = $j0->text('card', ['title' => 'Hello']);
$helperFile  = $j0->path('helper.php');
```

---

## 3. Domain & Asset Discovery (`j0/core/j0-use.php`)

### `j0_root()`
Returns the normalized absolute root path of the `j0` directory.
```php
<?php
require_once __DIR__ . '/j0/core/j0-use.php';
$j0Dir = j0_root(); // e.g. '/var/www/project/ai0/j0'
```

### `j0_discover_domains()`
Scans top-level `j0` directories (`core`, `tooling`, etc.) and `j0/src/<module>[--capability]` folders once per request in `SORT_NATURAL` order.
```php
<?php
require_once __DIR__ . '/j0/core/j0-use.php';
$domains = j0_discover_domains();
// ['core' => ['.../core'], 'tooling' => ['.../tooling'], 'vfs' => ['.../0100-vfs', '.../0100-vfs--remote'], 'all' => [...]]
```

### `j0_use(string ...$domains)`
Lazily includes all `.php` files belonging to one or more filesystem-derived domains (`'core'`, `'tooling'`, `'vfs'`, `'all'`).
```php
<?php
require_once __DIR__ . '/j0/core/j0-use.php';
$included = j0_use('core', 'tooling', 'vfs');
```

### `j0_asset_path(string $key)`
Resolves exact filenames (`'01-j0kernel.js'`) or prefix-stripped logical keys (`'kernel.js'`, `'vfs-core.js'`, `'emitter.js'`) to their physical file path inside `j0`.
```php
<?php
require_once __DIR__ . '/j0/core/j0-use.php';
$kernelFile  = j0_asset_path('kernel.js');
$emitterFile = j0_asset_path('emitter.js');
```

### `j0_asset_dir(string $key)`
Resolves logical `j0` module directories (`'tooling'`, `'vfs'`, `'0500-ui-patterns'`) to their physical directory path.
```php
<?php
require_once __DIR__ . '/j0/core/j0-use.php';
$vfsDir      = j0_asset_dir('vfs');
$patternsDir = j0_asset_dir('0500-ui-patterns');
```

---

## 4. Core PHP Primitives (`j0/core/`)

### `j0_closest()` (`j0-closest.php`)
Walks lexically upward from `$startDir` without resolving symlinks until any relative target exists, returning bidirectional relative geometry (`up_rel`, `down_rel`).
```php
<?php
require_once __DIR__ . '/j0/core/j0-closest.php';

$hit = j0_closest(__DIR__, ['_ai0/index.php', 'ai0/index.php'], 12);
// ['root' => '...', 'match' => '_ai0/index.php', 'base_dir' => '_ai0', 'target_abs' => '...', 'origin_abs' => '...', 'up_rel' => '../../', 'down_rel' => 'src/billing']
```

### `j0_is_local()`, `j0_config_domains()`, `j0_config_json()`, `j0_config_php()` (`j0-config.php`)
Environment guard and 4-tier `[_]<namespace>config` cascade (`project/_config` > `project/config` > `engine/_config` > `engine/config`).
```php
<?php
define('J0_EXEC', true);
require_once __DIR__ . '/j0/core/j0-config.php';

if (!j0_is_local(true)) {
    exit('Local environment only.');
}

$domains  = j0_config_domains('/var/www/app', '/var/www/app/_ai0', 'ai0');
$rules    = j0_config_json('dumper-categories', $domains); // Bottom-up recursive merge
$secrets  = j0_config_php('secrets/deploy', $domains);     // Top-down first-match (requires J0_EXEC)
```

### `j0_normalize_prefix_text()`, `j0_default_prefix_config()`, `j0_classify_prefix()` (`j0-prefix.php`)
Classifies leading-underscore semantics (`_🗑️` system discard, `_📦` system backup, `__` human discard, `_*` human disabled/private) with macOS APFS `U+FE0F` normalization.
```php
<?php
require_once __DIR__ . '/j0/core/j0-prefix.php';

$clean   = j0_normalize_prefix_text("_\u{1F5D1}\u{FE0F}old.php"); // "_\u{1F5D1}old.php"
$defaults = j0_default_prefix_config();
$meta    = j0_classify_prefix('_📦app.js_2026-11-24T10-00-00.js', $defaults);
// ['state' => 'backup', 'actor' => 'system', 'excluded_by_default' => true, 'purge_preselected' => true, 'marker' => '_📦']
```

### `j0_scan_files()`, `j0_dir_max_mtime()`, `j0_dir_signature()` (`j0-scan.php`)
Recursive symlink-safe directory scanner (`'tree'` hierarchy sort or `'flat'` natural sort), latest `mtime` calculator, and deletion-safe SHA-1 tree fingerprint (`path|mtime|size`).
```php
<?php
require_once __DIR__ . '/j0/core/j0-scan.php';

$files = j0_scan_files(__DIR__ . '/src', ['js', 'css'], ['_', '.'], [], 'tree');
$mtime = j0_dir_max_mtime(__DIR__ . '/src', ['js', 'css']);
$sig   = j0_dir_signature(__DIR__ . '/src', ['js', 'css']);
```

---

## 5. Unified Micro-Kernel (`j0/src/0010-kernel/j0010-kernel.js`)

Anchored on `globalThis.j0` so identical code runs in browser windows, Web Workers, and sandboxed plugin threads.

### `EventChannel`, `j0.bus` (`on`, `once`, `off`, `emit`, `schema`), `j0.register`, `j0.get`, `j0.has`, `j0.logger` (`log`, `warn`, `error`, `history`, `clear`)
```javascript
(function () {
    'use strict';

    const j0 = globalThis.j0;

    // 1. Service Registry
    j0.register('palette:math', { invert: (v) => 1 - v });
    if (j0.has('palette:math')) {
        const math = j0.get('palette:math');
        j0.logger.log('Inverted value', math.invert(0.25));
    }

    // 2. Bus Schema Validation (enforced when j0.QA = true)
    j0.QA = true;
    j0.bus.schema['doc:saved'] = { id: 'string', bytes: 'number' };

    // 3. Subscriptions: on(), once(), off(), emit()
    const onSaved = (payload) => j0.logger.log('Saved doc', payload);
    const unsubscribe = j0.bus.on('doc:saved', onSaved);
    j0.bus.once('app:booted', () => j0.logger.log('First boot complete'));

    j0.bus.emit('app:booted', {});
    j0.bus.emit('doc:saved', { id: 'doc-1', bytes: 512 });

    unsubscribe();
    j0.bus.off('doc:saved', onSaved);

    // 4. Isolated Custom EventChannel
    const localChannel = new j0.EventChannel('inspector');
    localChannel.on('tick', (p) => j0.logger.warn('Inspector tick', p));
    localChannel.emit('tick', { frame: 1 });

    // 5. Passive Circular Logger Inspection
    j0.logger.error('Sample diagnostic error', { code: 500 });
    const recentLogs = j0.logger.history();
    j0.logger.clear();
})();
```

---

## 6. Resilient Locator & Primitives `S()` / `T()` (`j0/core/j0-locate.php`)

### `j0_locate_init()`, `j0_project_root()`, `j0_mount()`, `j0_route_cache_read()`, `j0_route_cache_write()`
Configures the host root, mounts loose directories (Library Mode), and reads/writes the guarded `.j0route.php` OPcache map.
```php
<?php
define('J0_EXEC', true);
require_once __DIR__ . '/j0/j0-locate.php';

j0_locate_init(__DIR__);
$root = j0_project_root();
j0_mount(['tpl', 'assets'], $root);

$routes = j0_route_cache_read();
$routes['custom:key'] = __DIR__ . '/tpl/h0010-card.html';
j0_route_cache_write($routes);
```

### `j0_locate_parse_module()`, `j0_locate_index()`, `j0_locate_ring_search()`, `j0_locate()`, `j0_path()`, `j0_path_to_web_url()`
Low-level module metadata parsing, Union Mount indexing (`j0/src` -> `project/src` -> mounted dirs), opt-in pruned ring search, and disk/URL path resolution.
```php
<?php
define('J0_EXEC', true);
require_once __DIR__ . '/j0/j0-locate.php';

$meta    = j0_locate_parse_module('0300-billing--isprotected');
// ['sem_id' => 'billing', 'tier' => 'isprotected', 'tags' => ['isprotected']]

$index   = j0_locate_index();
$record  = j0_locate('card', 'tpl', ['deep' => true, 'cache' => true]);
$absPhp  = j0_path('billing', 'invoice.php');
$deepHit = j0_locate_ring_search('orphan-widget.html', 4);
$webUrl  = $record !== null ? j0_path_to_web_url($record['path']) : null;
```

### `j0_src()` / `S()` and `j0_text()` / `j0_tpl()` / `T()`
- **`S($id, $options)` / `j0_src()`:** Returns public HTTP URL for web assets, `api/serve.php?s=...` for non-public tiers, or absolute disk path for `.php` files.
- **`T($id, $vars, $options)` / `j0_text()` / `j0_tpl()`:** Renders HTML templates (stripping `<j0-ifjs>` and unwrapping `<j0-ifphp>`), inline `<svg>`, or decoded `.json`.

```php
<?php
define('J0_EXEC', true);
require_once __DIR__ . '/j0/j0-locate.php';

j0_mount(['tpl', 'img']);

// Spatial resolution ("Where")
$logoUrl  = S('logo.svg');
$logicPhp = j0_src('billing/calc.php');

// Tangible content rendering ("What")
$html = T('greeting-card', [
    'user'      => 'Ada Lovelace',
    'badgeHtml' => '<span class="badge">PRO</span>', // {{{badgeHtml}}} raw
]);
$cfgArray = j0_tpl('settings.json');
```

### `j0_resolve_text_macros()` & `j0_interpolate_text()`
Standalone template processors called automatically by `j0_text()` and directly available for custom string pipelines.
```php
<?php
define('J0_EXEC', true);
require_once __DIR__ . '/j0/j0-locate.php';

$raw = '<img src="{S(\'logo.svg\')}"><h1>Hello {{name}}</h1><div>{{{rawHtml}}}</div>';
$withMacros = j0_resolve_text_macros($raw);
$finalHtml  = j0_interpolate_text($withMacros, [
    'name'    => '<World>', // Escaped to &lt;World&gt;
    'rawHtml' => '<em>Trusted markup</em>',
]);
```

---

## 7. Assets, Cache, FS-IO & Bundler (`j0/src/0120-assets/`, `0130-cache/`, `0180-fs-io/`, `0190-bundler/`)

### `j0_backup_rotate()` (`0180-fs-io/p0010-backup.php`)
Copies or moves a file into a timestamped `_📦` backup (`<dir>/_📦<name>_<ISO-TS>.<ext>`).
```php
<?php
j0_use('fs-io');

$bakCopy = j0_backup_rotate(__DIR__ . '/dist/app.js');
$bakMove = j0_backup_rotate(__DIR__ . '/dist/old.js', "_\u{1F4E6}", null, true);
```

### `j0_concat_files()` (`j0-concat.php`)
Joins files in memory with normalized LF line endings, optional file header, and `/* === SOURCE: ... === */` boundary comments.
```php
<?php
require_once __DIR__ . '/j0/tooling/j0-concat.php';

$bundle = j0_concat_files([__DIR__ . '/a.js', __DIR__ . '/b.js'], "\n;\n", '/*! Bundle v1 */', true);
```

### `j0_minify_extract_banner()`, `j0_minify_remote()`, `j0_minify_dispatch()`, `j0_minify_css()`, `j0_minify_js()` (`j0-minify.php`)
Minifies CSS/JS via local plugin (`tooling/plugins/minify-local.php` or callable) or remote API while preserving leading `/*! ... */` license banners and falling back safely to uncompacted source with a warning header.
```php
<?php
require_once __DIR__ . '/j0/tooling/j0-minify.php';

$banner = j0_minify_extract_banner("/*! MIT License */\nbody { margin: 0; }");
$minCss = j0_minify_css("/*! MIT */\nbody { color: red; }", ['allow_remote' => false]);
$minJs  = j0_minify_js("function hi(){ return 1; }", [
    'local_plugin' => static fn(string $code, string $type): string => preg_replace('/\s+/', ' ', $code),
]);
```

### `j0_smart_save()` (`j0-save.php`)
Writes atomically (`tmp` + `rename()`) only when the content MD5 differs from the existing file on disk, optionally rotating a `_📦` backup first.
```php
<?php
require_once __DIR__ . '/j0/tooling/j0-save.php';

$res = j0_smart_save(__DIR__ . '/dist/app.css', "body{margin:0}\n", true);
// ['written' => true, 'backup' => '.../_📦app_2026-11-24T12-00-00.css', 'reason' => 'saved']
```

### `j0_build_bundle()` (`j0-build.php`)
Scans sources, concatenates, writes `<name>.<ext>`, minifies, and writes `<name>.min.<ext>`.
```php
<?php
require_once __DIR__ . '/j0/tooling/j0-build.php';

$status = j0_build_bundle([
    'sources'   => [__DIR__ . '/core.js', __DIR__ . '/modules'],
    'out_file'  => __DIR__ . '/dist/app.js',
    'ext'       => 'js',
    'minify'    => true,
    'backup'    => true,
    'transform' => static fn(string $raw, string $ext): string => $raw,
]);
```

### `j0_discover_bundles()` & `j0_build_bundles()` (`j0-bundles.php`)
Discovers all active root-level module folders or standalone files in a directory and compiles/minifies each into `<name>.<ext>` + `<name>.min.<ext>` with SHA-1 `.sig` tracking.
```php
<?php
require_once __DIR__ . '/j0/tooling/j0-bundles.php';

$bundles = j0_discover_bundles(__DIR__ . '/ui/modules', 'css');
$report  = j0_build_bundles(__DIR__ . '/ui/modules', 'css', ['allow_remote' => false]);
```

### `j0_asset_deps()` & `j0_asset_tags()` (`j0-asset-tags.php`)
Rebuilds stale `.min` bundles using SHA-1 tree signatures, records scanned file dependencies for `j0_static_html()`, and emits either a single `.min` tag or individual debug tags.
```php
<?php
require_once __DIR__ . '/j0/tooling/j0-asset-tags.php';

$tags = j0_asset_tags(__DIR__ . '/modules', __DIR__ . '/dist/app', '/dist', 'js', false);
$trackedFiles = j0_asset_deps(); // Returns all files scanned across j0_asset_tags() calls
```

### `j0_static_html()` (`j0-static-html.php`)
Serves a sibling `.html` snapshot when newer than the PHP script and all tracked dependencies (`j0_asset_deps()` + explicit `$dependencies`); otherwise renders, saves atomically, and outputs HTML.
```php
<?php
require_once __DIR__ . '/j0/tooling/j0-asset-tags.php';
require_once __DIR__ . '/j0/tooling/j0-static-html.php';

$js = j0_asset_tags(__DIR__ . '/modules', __DIR__ . '/dist/app', 'dist', 'js', false);

j0_static_html(__FILE__, static function () use ($js): void {
    echo "<!doctype html><html><head>{$js}</head><body><h1>Cached</h1></body></html>";
}, [__DIR__ . '/partials']);
```

---

## 8. Isomorphic Virtual File System (`j0/src/0100-vfs/` & `0100-vfs--remote/`)

### Key-Value Store, Drivers, Modes & SSR Cookie Sync (`j0010-vfs-core.js`)
```javascript
(function () {
    'use strict';

    const vfs = globalThis.j0.vfs;

    // 1. Register UI key to mirror to compact 'j0_ui' cookie (zero-FOUC SSR)
    vfs.registerUiSync('theme', {
        shortKey: 't',
        encode: (v) => (v === 'light' ? 'l' : 'd'),
        decode: (v) => (v === 'l' ? 'light' : 'dark')
    });

    // 2. Synchronous KV with optional TTL (milliseconds)
    vfs.set('theme', 'dark', { profile: 'ui-sync' });
    const currentTheme = vfs.get('theme', 'dark');

    vfs.kv.set('banner_seen', '1');
    const seen = vfs.kv.get('banner_seen', '0', 86400000);
    vfs.kv.remove('banner_seen');

    // 3. Storage Drivers & Modes ('local' vs 'hybrid-sync')
    vfs.registerDriver('memory-custom', { name: 'custom' });
    const drv = vfs.getDriver('memory-custom');
    vfs.setMode('local');
    const mode = vfs.mode(); // 'local'
})();
```

### Content-Addressable Blobs & Offline/Online Documents (`j0020-vfs-idb.js` + `j0030-vfs-docs.js`)
```javascript
(async function () {
    'use strict';

    const vfs = globalThis.j0.vfs;

    // 1. Content-Addressed Blobs (SHA-256 deduplicated in IndexedDB)
    const sampleBlob = new Blob(['Hello j0'], { type: 'text/plain' });
    const hash = await vfs.blobs.put(sampleBlob);
    const exists = await vfs.blobs.has(hash);
    const fetchedBlob = await vfs.blobs.get(hash);
    const allHashes = await vfs.blobs.list();

    // 2. Structured Documents CRUD + Portable JSON Export/Import
    const saved = await vfs.docs.save('notes', {
        title: 'Architecture Note',
        preview: 'Zero tech debt',
        blobs: [hash],
        data: { tags: ['j0', 'vfs'] }
    });

    const loaded = await vfs.docs.get('notes', saved.id);
    const list   = await vfs.docs.list('notes');
    const bundle = await vfs.docs.exportJson('notes', saved.id);

    await vfs.docs.remove('notes', saved.id);
    const imported = await vfs.docs.importJson('notes', bundle);

    // 3. Garbage Collection Check for Unreferenced Blobs
    const orphanHashes = await vfs.docs.syncOrphans('notes');
    for (const orphan of orphanHashes) {
        await vfs.blobs.remove(orphan);
    }
})();
```

### PHP SSR Cookie Reader (`p0010-vfs-ssr.php`)
Reads the `j0_ui` cookie written by `j0010-vfs-core.js` so PHP outputs exact UI attributes on the first byte.
```php
<?php
require_once __DIR__ . '/j0/src/0100-vfs/p0010-vfs-ssr.php';

$cookieMap = [
    't' => ['key' => 'theme', 'values' => ['l' => 'light', 'd' => 'dark']],
];
$allUiState = j0_vfs_read_ui_cookie($cookieMap);
$themeAttr  = j0_vfs_ui_attr('theme', 'dark', $cookieMap);
echo "<html lang=\"en\" data-theme=\"{$themeAttr}\">";
```

### Remote Cloud Sync Extension (`0100-vfs--remote/j0100-vfs-remote.js`)
Upgrades `j0.vfs` automatically to `'hybrid-sync'` when `auth:login` or `auth:session-ready` fires on `globalThis.j0.bus`, and reverts to `'local'` on `auth:logout`.
```javascript
(function () {
    'use strict';

    const j0 = globalThis.j0;
    const vfs = j0.vfs;

    vfs.configureRemote({
        endpoint: '/api/vfs',
        headers: { 'X-App-Client': 'web' }
    });

    // Listen to sync lifecycle events
    j0.bus.on('j0:vfs:mode-changed', ({ mode }) => j0.logger.log('VFS mode:', mode));
    j0.bus.on('j0:vfs:syncing', ({ collection }) => j0.logger.log('Syncing:', collection));
    j0.bus.on('j0:vfs:synced', ({ collection, count }) => j0.logger.log('Synced:', collection, count));
    j0.bus.on('j0:vfs:sync-error', ({ message }) => j0.logger.error('Sync error:', message));

    // Trigger authenticated hybrid-sync
    j0.bus.emit('auth:login', { token: 'jwt-or-session-token' });

    // Manual driver operations if needed
    const remote = vfs.getDriver('remote');
    if (remote) {
        remote.syncDirty('notes');
    }
})();
```

---

## 9. Declarative UI Engines (`j0/src/0500-ui-patterns/`, `0510-ui-menus/`, `0520-ui-windows/`)

All 6 pattern engines bind via `data-j0-*` attributes (zero `id=""` hooks, zero inline `onclick`), complemented by `0510-ui-menus` (body-ported dropdowns & safe-zone submenus) and `0520-ui-windows` (`j0-pane` / `j0-region` fractal layout, 3D Flip `j0-window`, and `j0ui-window-sheet`).

### 1. Emitter (`j0501-emitter.js`)
Dispatches click/touchend triggers (`data-j0-action`, optional JSON `data-j0-payload`, `50ms` anti-double-fire guard, respects `disabled` and `.is-disabled`) onto `globalThis.j0.bus`.
```html
<button type="button" data-j0-action="item:delete" data-j0-payload='{"itemId":42}'>Delete Item</button>
```
```javascript
globalThis.j0.bus.on('item:delete', ({ itemId, trigger }) => {
    globalThis.j0.logger.log('Deleting item', itemId, trigger);
});
```

### 2. Switcher (`j0502-switcher.js`)
Coordinates tabs (`[data-j0-tab-group]`, `[data-j0-target]`) and panels (`[data-j0-pane-group]`, `[data-j0-panel]`), plus programmatic `j0.ui.switchPane()` and `j0.ui.activateItem()`.
```html
<div data-j0-tab-group="inspector">
    <button type="button" class="j0ui-active" data-j0-target="oklch">OKLCH</button>
    <button type="button" data-j0-target="rgb">RGB</button>
</div>
<div data-j0-pane-group="inspector">
    <section class="j0ui-active" data-j0-panel="oklch">OKLCH Controls</section>
    <section data-j0-panel="rgb">RGB Controls</section>
</div>
```
```javascript
// Programmatic switching
globalThis.j0.ui.switchPane('inspector', 'rgb');
```

### 3. Binder (`j0503-binder.js`)
Two-way synchronization across inputs, selects, textareas, radios, checkboxes, `<output>`, and text sinks (`data-j0-bind`, `data-j0-bind-scope`, `data-j0-bind-scale`, `data-j0-bind-transform`, `data-j0-bind-format`, `data-j0-bind-debounce`). Built-in transforms: `length`, `percent`, `round`, `abs`, `invert`, `bool`.
```html
<div data-j0-bind-scope="color-panel">
    <input type="range" data-j0-bind="lightness" min="0" max="100" value="50">
    <input type="number" data-j0-bind="lightness" data-j0-bind-scale="0.01" step="0.01" value="0.50">
    <output data-j0-bind="lightness" data-j0-bind-transform="percent">50%</output>
    <textarea data-j0-bind="bio" data-j0-bind-debounce="100"></textarea>
    <span data-j0-bind="bio" data-j0-bind-transform="length">0</span>
</div>
```
```javascript
// Custom transform + programmatic update + dynamic DOM re-scan
globalThis.j0.ui.bindTransforms.upper = (val) => String(val ?? '').toUpperCase();
globalThis.j0.ui.bind('lightness', 0.75, 'color-panel');
globalThis.j0.ui.initBindings(document);
```

### 4. Conditioner (`j0504-conditioner.js`)
Declarative visibility and availability (`data-j0-show-when`, `data-j0-hide-when`, `data-j0-enable-when`) driven by `j0.ui.setState(channel, value)` and `j0.ui.getState(channel)`.
```html
<div data-j0-show-when="auth:logged-in">Welcome back</div>
<div data-j0-hide-when="auth:logged-in">Please sign in</div>
<button type="button" data-j0-enable-when="form:valid">Submit</button>
```
```javascript
globalThis.j0.ui.setState('auth', 'logged-in');
globalThis.j0.ui.setState('form', 'valid');
const currentAuth = globalThis.j0.ui.getState('auth'); // 'logged-in'
```

### 5. Dragger (`j0505-dragger.js`)
Pointer and keyboard dragging with rectangular/circular/viewport bounds (`j0.ui.dragBounds`), cartesian/axis/polar coordinate transforms (`j0.ui.dragTransform`), snapping, momentum, ghost preview, and multi-thumb collision groups (`push`, `stop`, `snap`).
```html
<!-- Declarative multi-thumb range group -->
<div data-j0-drag-group-container data-j0-drag-axis="x" data-j0-drag-collide="push" data-j0-drag-order="min-val,max-val">
    <div data-j0-draggable data-j0-bind="min-val"></div>
    <div data-j0-draggable data-j0-bind="max-val"></div>
</div>
```
```javascript
// Programmatic draggable with circular bounds & polar output
const handle = globalThis.j0.ui.draggable(wheelKnobEl, {
    bounds: globalThis.j0.ui.dragBounds.circle(wheelContainerEl),
    transform: globalThis.j0.ui.dragTransform.polar(),
    snap: 0.01,
    momentum: true,
    onMove: ({ value }) => {
        globalThis.j0.logger.log('Polar coords', value.angle, value.radius);
    }
});
// Cleanup when unmounting: handle.destroy();
```

### 6. Floater (`j0506-floater.js`)
Viewport-clamped floating positioning (`j0.ui.floatAt(el, x, y)`, `j0.ui.floatCloseAll()`) with global quiescence on outside clicks, `Escape`, and `ui:dismiss` bus events.

---

_ai0 & j0 (AI-DevTools) v5.0.11 • 2026-11-27 • Santiago Bustelo • [Apache 2.0 / CC BY 4.0 License](../LICENSE)_