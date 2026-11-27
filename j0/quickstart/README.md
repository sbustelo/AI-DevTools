# `j0` Quickstart & Bootstrap Guide

Download or link `j0` from **https://j0.bustelo.com.ar/** and place or symlink it anywhere in or above your project root (`_j0/`, `j0/`, `vendors/j0/`, or `_ai0/j0/`). No Composer or hardcoded absolute paths are required.

---

## 1. How `j0_boot()` Works

`j0-bootstrap.php` (and its compact sibling `j0-bootstrap-compact.php`) provides a single function:

    j0_boot(__DIR__);

1. **Cached Route (`.j0route.php`):** On the first run, `j0_boot()` walks upward lexically (preserving symlinks) until it finds `j0/api.php`, loads it, and writes a guarded `.j0route.php` associative cache next to the caller directory. Every subsequent request loads `api.php` in O(1) without directory traversal.
2. **Self-Healing:** If `j0` is ever moved or unlinked, `j0_boot()` detects that the cached path no longer exists, clears `'__j0_api__'`, and re-resolves the new location automatically.
3. **Zero Scanner Noise:** Because `.j0route.php` begins with `.`, `j0_scan_files()`, `ai0`, and `.*` `.gitignore` rules ignore it automatically.

---

## 2. Two Integration Patterns

### Pattern A — Standalone PHP Script (`examples/01-standalone-php/`)
Paste `j0_boot()` directly inside your PHP script (or `require_once` a copied `j0-bootstrap.php`), call `j0_boot(__DIR__)`, and load the domains you need:

    j0_boot(__DIR__);
    j0_use('bundler');

> **Symlink Rule:** You can symlink the `j0/` directory itself, but always **copy** `j0_boot()` into your project rather than symlinking the bootstrap file—otherwise PHP resolves `__DIR__` to the shared `j0` repository instead of your project folder.

### Pattern B — Centralized Project Bootstrap (`examples/02-project-bootstrap/`)
For multi-page applications, put `j0_boot(__DIR__)` and `j0_use('assets', 'cache')` inside **one** project file (e.g. `php/bootstrap.php`), and include that file from all your pages via a normal relative `__DIR__` path:

    require_once __DIR__ . '/php/bootstrap.php';

Only `php/bootstrap.php` ever searches for `j0` (and caches `.j0route.php` once); internal pages never repeat the lookup.

---

## 3. Customizing Search Locations

Pass custom relative paths as the second argument to `j0_boot()` (or edit `'targets'` in `j0-bootstrap.php`):

    j0_boot(__DIR__, [
        'targets' => ['vendors/j0/api.php', '_j0/api.php', 'j0/api.php'],
    ]);