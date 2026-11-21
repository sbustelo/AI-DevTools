# Architecture & Design Specification (`j0 v2` & `ai0`)

## 1. Hybrid Design: Cherry-Pick Primitives + Full Web OS

`AI-DevTools` houses two synergistic systems designed around **zero external dependencies**, **filesystem sovereignty**, and **progressive composition**:

1. **`j0 v2` (Modular Runtime & Build Primitives):** Organized by **system layer** rather than by programming language (`core/`, `tooling/`, `src/`). Any external project can cherry-pick a single file (such as `01-j0kernel.js` or `j0-build.php`) without adopting the full stack, or assemble all three layers into a complete Web OS.
2. **`ai0` (Local AI Development Suite):** A drop-in developer control plane (`dumper`, `patcher`, `restore`, `purge`) built on top of `j0/core/` and `j0/tooling/`.

---

## 2. Three-Layer Topology of `j0 v2` (`ai0/j0/`)

```text
ai0/j0/
├── core/                       # Layer 0: BIOS & Micro-Kernel (indispensable boot & bus primitives)
│   ├── 01-j0kernel.js          # EventChannel + j0.bus + j0.registry + passive j0.logger
│   ├── j0-closest.php          # Lexical upward directory traverser (symlink-safe)
│   ├── j0-config.php           # 4-tier [_]ai0config resolver (.php guarded secrets + .json rules)
│   ├── j0-prefix.php           # Semantic leading-underscore classifier (_, __, _🗑️, _📦)
│   └── j0-scan.php             # Natural recursive filesystem scanner + max mtime
├── tooling/                    # Layer 1: System Utilities (IO, bundling, minification, SSR cache)
│   ├── j0-backup.php           # Timestamped _📦 backup rotator
│   ├── j0-concat.php           # In-memory LF-normalized concatenator
│   ├── j0-minify.php           # Local CSS/JS compactor preserving /*! ... */ banners
│   ├── j0-save.php             # MD5-checked atomic disk writer
│   ├── j0-build.php            # Bundle pipeline orchestrator (scan -> concat -> save -> minify)
│   ├── j0-asset-tags.php       # Smart <link>/<script> emitter (compiled .min vs. DEBUG files)
│   └── j0-static-html.php      # PHP-to-HTML mtime-invalidated static cache
└── src/                        # Layer 2: Vertical Polyglot OS Modules (macOS Library model)
    ├── vfs/                    # Root module: local-first KV, SSR cookie sync, IndexedDB blobs & docs
    │   ├── j0010-vfs-core.js
    │   ├── j0020-vfs-idb.js
    │   ├── j0030-vfs-docs.js
    │   └── p0010-vfs-ssr.php
    └── vfs--remote/            # Capability extension (--remote): j0auth + cloud hybrid-sync
        └── j0010-vfs-remote.js
```

### Layer 0 — `j0/core/` (The BIOS / Micro-Kernel)
- **Unified JS Kernel (`01-j0kernel.js`):** Combines `EventChannel`, `window.j0.bus` (with optional `window.j0.QA` contract validation), `window.j0.registry` (`register` / `get` / `has`), and a passive `window.j0.logger` (bounded circular buffer + `j0:log` bus event, with zero boot timeouts so standalone libraries never trigger false console errors).
- **Stateless PHP Foundation (`j0-closest`, `j0-config`, `j0-prefix`, `j0-scan`):** Pure functions accepting explicit paths and returning structured arrays without global mutable state.

### Layer 1 — `j0/tooling/` (Composable System Utilities)
- Follows Unix pipeline composition: `j0-build.php` orchestrates `j0-scan.php` $\rightarrow$ `j0-concat.php` $\rightarrow$ `j0-save.php` (`j0-backup.php`) $\rightarrow$ `j0-minify.php`.
- `j0-asset-tags.php` compiles bundles on demand when source files are newer and emits either a single `.min` tag or individual source tags when `DEBUG` is enabled.

### Layer 2 — `j0/src/` (Vertical Modules & macOS Library Shadowing)
- **Polyglot Vertical Modules:** Each directory inside `src/` encapsulates a complete domain feature across `.js`, `.css`, `.html`, `.php`, `.md`, and internal subdirectories. Recursive asset scanners traverse module directories in `SORT_NATURAL` order while ignoring `_` and `.` entries.
- **Capability Extensions (`[root]--[capability]`):** A double-hyphen suffix (`vfs--remote/`) declares an optional capability plugin that attaches to its root module (`vfs/`) via `window.j0.bus` and `window.j0.registry` when activated (for example, promoting `vfs` from `'local'` browser drafts to `'hybrid-sync'` cloud persistence upon `auth:login`).
- **macOS Library Shadowing (`j0/src/` vs. Project `src/`):** In full OS deployments, `j0/src/` acts as `/System/Library` (read-only, safely replaceable on framework upgrades), while the host project's `src/` acts as `~/Library` (file-level overrides take precedence when matching relative paths).

---

## 3. `ai0` Dual Entrypoint Model & Configuration Cascade

Every tool under `ai0/tools/*/` exposes an `index.php` entrypoint and supports two execution modes via `ai0_resolve_context()`:

1. **Direct Web / DevTools Access** (`/_ai0/tools/dumper/index.php`):
   - Traverses upward lexically from `SCRIPT_FILENAME` (without resolving symlinks) to locate the parent of `_ai0` (or `ai0`), setting `down_rel` to `""`.
2. **Portable Stub Access** (`src/billing/ai0dump.php`):
   - Stubs in `ai0/stubs/` embed a self-contained upward traversal loop (~25 lines) plus a configurable header (`repo_url` and `on_missing` mode, defaulting to `'strict'`).
   - Locates `_ai0` (or `ai0`) and computes `up_rel` and `down_rel`, scoping `dumper`, `restore`, and `purge` to the stub's subtree (while `ai0build.php` builds the stub's own directory and `ai0patch.php` applies project-root-relative paths).

### 4-Level Configuration Cascade (`[_]ai0config`) & `_j0-temp`
`j0_config_json()` and `j0_config_php()` resolve configuration across four precedence levels (`1 > 2 > 3 > 4`):
1. `<PROJECT_ROOT>/_ai0config/` *(project-local private overrides & secrets)*
2. `<PROJECT_ROOT>/ai0config/` *(project-shared declarative config, tracked in Git)*
3. `<AI0_DIR>/_ai0config/` *(contained/mounted private overrides)*
4. `<AI0_DIR>/ai0config/` *(engine factory defaults)*

- **Declarative JSON (`prefixes.json`, `dumper-categories.json`):** Merged bottom-up (`4 -> 3 -> 2 -> 1`) via `array_replace_recursive()`.
- **Guarded PHP Secrets (`secrets/*.php`):** Resolved top-down (`1 -> 2 -> 3 -> 4`) via first-match after verifying `defined('J0_EXEC')`.
- **Ephemeral Runtime (`_j0-temp/`):** Persists `ai0-state.json` and transient caches in `<PROJECT_ROOT>/_j0-temp/` (or `<AI0_DIR>/_j0-temp/` in single-folder contained mode).

---

## 4. `ai0` Tools & Stubs Overview

| Tool / Target | Portable Stub | Responsibility |
| :--- | :--- | :--- |
| `tools/dumper/` | `ai0dump.php` | Interactive checkbox tree, **Show excluded only** filter, regex exclusion categories from `dumper-categories.json`, binary summaries, and context dump generator. |
| `tools/patcher/` | `ai0patch.php` | Atomic applier for `CREATE`, `PATCH` (`1-Line First`), `MOVE`, `DISABLE` (`_`), and `DISCARD` (`_🗑️`), creating `_📦` backups on overwrite and running `php -l`. |
| `tools/restore/` | `ai0restore.php` | Scans `_📦<name>_<ISO-TS>.<ext>` files, groups them into timestamped batches, and restores previous versions in one click. |
| `tools/purge/` | `ai0purge.php` | Reclaims disk space by preselecting `discard` (`_🗑️`, `__`) and `backup` (`_📦`) items while keeping human `disabled` (`_*`) items unchecked by default. |
| `j0/tooling/j0-build.php` | `ai0build.php` | Compiles local modular CSS and JS bundles (`.ext` + `.min.ext`) inside any target directory via `j0_build_bundle()`. |

*(UI note: `ai0/ui/modules/` currently runs on provisional styles and DOM handlers and will be rebuilt directly on `IPAX-UI` once the minimal `IPAX-UI` core is finalized).*