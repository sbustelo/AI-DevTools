# `j0 v2` Core, Tooling & VFS API Reference

All files under `ai0/j0/` are zero-dependency PHP 8.2+ and Vanilla ES2020+ modules organized into three functional layers: `core/`, `tooling/`, and `src/`.

---

## Layer 0: `j0/core/` (BIOS & Micro-Kernel)

### 1. `01-j0kernel.js` — Unified JS EventBus, Service Registry & Passive Logger
Single-file micro-kernel that initializes `window.j0.bus`, `window.j0.registry`, and `window.j0.logger` without boot timers or external dependencies.

```javascript
// 1. Register & retrieve synchronous services (Service Locator)
window.j0.register('my-service', { compute: (x) => x * 2 });
const svc = window.j0.get('my-service');

// 2. Define optional QA payload contracts (enforced only when window.j0.QA = true)
window.j0.bus.schema['item:selected'] = { id: 'string' };

// 3. Subscribe & emit decoupled notifications
const off = window.j0.bus.on('item:selected', ({ id }) => {
    window.j0.logger.log('Selected item', id);
});
window.j0.bus.emit('item:selected', { id: 'node-42' });
off();
```

### 2. `j0-closest.php` — Upward Directory Traverser
Walks lexically upward from `$startDir` until any relative target exists, returning a bidirectional path map without resolving symlinks.

```php
require_once 'path/to/j0/core/j0-closest.php';

$map = j0_closest(__DIR__, ['_ai0/index.php', 'ai0/index.php']);
// ['root' => '...', 'match' => '...', 'base_dir' => '_ai0', 'target_abs' => '...', 'origin_abs' => '...', 'up_rel' => '../../', 'down_rel' => 'src/billing']
```

### 3. `j0-config.php` — 4-Level Configuration & Secret Loader
Resolves declarative JSON configs via recursive merge (`4 -> 3 -> 2 -> 1`) and guarded PHP secrets via first-match (`1 -> 2 -> 3 -> 4`).

```php
require_once 'path/to/j0/core/j0-config.php';

$domains  = j0_config_domains($projectRoot, $ai0Dir);
$prefixes = j0_config_json('prefixes', $domains);
$ftpCreds = j0_config_php('secrets/ftp-sync', $domains); // Requires J0_EXEC defined
```

### 4. `j0-prefix.php` — Semantic Prefix Classifier
Classifies basenames against `prefixes.json` (`discard` for `_🗑️`/`__`, `backup` for `_📦`, `disabled` for `_*`, `active` otherwise), normalizing macOS APFS `U+FE0F` variation selectors.

```php
require_once 'path/to/j0/core/j0-prefix.php';

$prefixCfg = j0_config_json('prefixes', $domains);
$info = j0_classify_prefix('_🗑️legacy.php', $prefixCfg);
// ['state' => 'discard', 'actor' => 'system', 'excluded_by_default' => true, 'purge_preselected' => true, 'marker' => '_🗑️']
```

### 5. `j0-scan.php` — Natural Recursive Scanner & Mtime
Recursively lists files filtered by extension and ignored prefixes (`_` and `.` by default), sorted with `SORT_NATURAL`.

```php
require_once 'path/to/j0/core/j0-scan.php';

$jsFiles  = j0_scan_files(__DIR__ . '/src', ['js']);
$maxMtime = j0_dir_max_mtime(__DIR__ . '/src', ['js', 'css']);
```

---

## Layer 1: `j0/tooling/` (Build, Atomic IO & Asset Utilities)

### 6. `j0-backup.php` — Timestamped `_📦` Backup Rotator
Copies or moves an existing file to `<dir>/_📦<filename>_<YYYY-MM-DDTHH-MM-SS>.<ext>`.

```php
require_once 'path/to/j0/tooling/j0-backup.php';

$backupPath = j0_backup_rotate('/path/to/app.js');
```

### 7. `j0-concat.php` — Source File Concatenator
Normalizes `\r\n` to `\n` and joins source files in memory with optional boundary comments.

```php
require_once 'path/to/j0/tooling/j0-concat.php';

$code = j0_concat_files($jsFiles, "\n;\n", "/*! App Bundle */", true);
```

### 8. `j0-minify.php` — Zero-Dependency CSS & JS Minifier
Compacts CSS and JS locally while preserving `/*! ... */` license banners and falling back safely to original code if output would be empty.

```php
require_once 'path/to/j0/tooling/j0-minify.php';

$minCss = j0_minify_css($rawCss);
$minJs  = j0_minify_js($rawJs);
```

### 9. `j0-save.php` — MD5-Checked Atomic Writer
Skips disk writes when MD5 matches the target file (`reason: 'unchanged'`), otherwise rotates a `_📦` backup (optional) and writes atomically via temp file + `rename()`.

```php
require_once 'path/to/j0/tooling/j0-save.php';

$res = j0_smart_save('/path/to/dist/app.js', $code, true);
```

### 10. `j0-build.php` — Bundle Orchestrator
Scans, concatenates, saves `<name>.<ext>`, minifies, and saves `<name>.min.<ext>`.

```php
require_once 'path/to/j0/tooling/j0-build.php';

$status = j0_build_bundle([
    'src_dir'  => __DIR__ . '/modules',
    'out_file' => __DIR__ . '/modules.js',
    'ext'      => 'js',
]);
```

### 11. `j0-asset-tags.php` — Smart HTML Tag Emitter
Compares source `mtime` against `.min.<ext>`, rebuilds when stale and writable, and emits either a single `.min` tag or individual source tags in `DEBUG` mode.

```php
require_once 'path/to/j0/tooling/j0-asset-tags.php';

echo j0_asset_tags(__DIR__ . '/modules', __DIR__ . '/modules', './ui/', 'js', $isDebug);
```

### 12. `j0-static-html.php` — PHP-to-HTML Static Cache
Serves a sibling `.html` file when its `mtime` is newer than the source `.php` and its dependencies; otherwise captures output, saves it atomically via `j0_smart_save()`, and prints it.

```php
require_once 'path/to/j0/tooling/j0-static-html.php';

j0_static_html(__FILE__, static function (): void {
    echo '<h1>Static Snapshot</h1>';
}, [__DIR__ . '/partials']);
```

---

## Layer 2: `j0/src/vfs/` & `j0/src/vfs--remote/` (Isomorphic Virtual File System)

Load the entire `j0/src/vfs/` module directory via `j0_asset_tags()` after `01-j0kernel.js`. Add `j0/src/vfs--remote/` only in applications that integrate `j0auth` and cloud document sync.

### 1. Synchronous Key-Value, TTL & SSR Cookie Sync (`j0010-vfs-core.js`)
```javascript
// Register a UI key to mirror automatically to the compact 'j0_ui' SSR cookie (zero FOUC)
window.j0.vfs.registerUiSync('ipax-theme', {
    shortKey: 't',
    encode: (v) => (v === 'light' ? 'l' : 'd'),
    decode: (v) => (v === 'l' ? 'light' : 'dark')
});

window.j0.vfs.set('ipax-theme', 'dark', { profile: 'ui-sync' });
const theme = window.j0.vfs.get('ipax-theme', 'light');

// Expiring client cache (TTL in milliseconds)
window.j0.vfs.set('draft_banner_dismissed', '1');
const dismissed = window.j0.vfs.get('draft_banner_dismissed', '0', 86400000);
```

### 2. Content-Addressable Blobs & Offline/Online Document CRUD (`j0020-vfs-idb.js` + `j0030-vfs-docs.js`)
```javascript
// Store binary blobs deduplicated by SHA-256 hash in IndexedDB
const hash = await window.j0.vfs.blobs.put(fileBlob);
const blob = await window.j0.vfs.blobs.get(hash);

// Save, list, load, and export documents (works offline in 'local' mode and syncs in 'hybrid-sync')
const saved = await window.j0.vfs.docs.save('projects', {
    title: 'Landing Composition',
    preview: 'Hero + 2 cards',
    blobs: [hash],
    data: { boxes: [] }
});

const allDocs = await window.j0.vfs.docs.list('projects');
const portableBundle = await window.j0.vfs.docs.exportJson('projects', saved.id);
```

### 3. PHP SSR Cookie Reader (`p0010-vfs-ssr.php`)
Reads the `j0_ui` cookie written by `j0010-vfs-core.js` so PHP outputs the exact theme attribute on first byte:

```php
require_once 'path/to/j0/src/vfs/p0010-vfs-ssr.php';

$theme = j0_vfs_ui_attr('theme', 'light');
echo "<html lang=\"en\" data-theme=\"{$theme}\">";
```

### 4. Cloud Capability Extension (`j0/src/vfs--remote/j0010-vfs-remote.js`)
- Listens automatically to `auth:login`, `auth:session-ready`, and `auth:logout` on `window.j0.bus`.
- Switches `window.j0.vfs.mode()` between `'local'` and `'hybrid-sync'`, queues dirty offline edits in IndexedDB, and flushes pending writes when `online` fires.
- Emits lifecycle events on `window.j0.bus`: `j0:vfs:mode-changed`, `j0:vfs:syncing`, `j0:vfs:synced`, `j0:vfs:sync-error`, `j0:vfs:changed`, and `j0:vfs:quota-exceeded`.