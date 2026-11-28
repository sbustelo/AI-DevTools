# `j0` & `ai0` Filesystem Naming Conventions

> **The filesystem is the architecture.** Directory structure, numeric prefixes, and leading symbols carry explicit semantic meaning without central manifests or build databases.

---

## 1. Leading Underscore Taxonomy (`_` State & Visibility Protocol)

A leading underscore (`_`) declares **operational invisibility**: items starting with `_` are excluded natively by `ai0` and `j0` builders, scanners, config loaders, and context dumps (and can be ignored by Git or FTP deployment tools by adding `_*` to `.gitignore` and sync ignore lists).

The **second character** distinguishes human intent from mechanical/AI state:

| Prefix | Actor | State | Semantics & Tool Behavior |
| :--- | :--- | :--- | :--- |
| **`_🗑️`** (`U+1F5D1`) | **System / AI** | `discard` | Marked for deferred deletion via `=== DISCARD: path ===` (implementing the **Discard** model conceived by Santiago Bustelo in 2005: hidden from active workflows, unlimited *undiscard* by removing the prefix, and preselected in `purge` to reclaim space). |
| **`_📦`** (`U+1F4E6`) | **System** | `backup` | Mechanical timestamped backup (`_📦file_2026-11-20T18-11-03.ext`) or frozen archive snapshot (`_📦ai0-0010`, `_📦project 2026 11 20 1830.zip`). Grouped by batch in `restore` and preselected in `purge`. |
| **`__`** (double `_`) | **Human** | `discard` | Fast human shorthand for marking an item for deletion without typing an emoji (`__old-test.php`). Preselected in `purge` and excluded by default in `dumper`. |
| **`_*`** (any other char) | **Human** | `disabled` | Commented-out module, disabled file (`=== DISABLE: path ===`), scratch notes, or local runtime vault (`_ai0`, `_j0-temp`, `_ai0config`). **Protected (unchecked by default)** in `purge`. |

### Unicode APFS Normalization Rule
On macOS APFS, emojis carrying Variation Selector-16 (`🗑️` = `U+1F5D1 U+FE0F`) may lose `U+FE0F` depending on the renaming tool. `j0-prefix.php` matches against the base Unicode codepoint (`_\u{1F5D1}`, `_\u{1F4E6}`) so classification remains deterministic across Finder, Terminal, and PHP.

---

## 2. Pseudo-BEM Modular Directory Naming

Modules follow a deterministic folder naming pattern that controls load order via `SORT_NATURAL` while declaring architectural role via semantic tags:

```text
[NNNN]-[semantic-id]--[tag1]-[tag2] [optional human note]
```

- **`[NNNN]` (4-digit BASIC-style index):** Increments of `10` (`0010`, `0020`, `0030`) govern exact concatenation and execution order. Never encode module type into numeric ranges; use tags instead.
- **`[semantic-id]`:** Lowercase `kebab-case` identifier (`j0ui-tokens`, `tree-browser`, `category-panel`).
- **`--[tags]` / `--[capability]`:** Double-hyphen modifier declaring module layer/target (`--core-css`, `--ui-widget`, `--ui-helper`) or a capability extension attached to a root module (`vfs/` as root module vs. `vfs--remote/` as an optional cloud-sync capability plugin).
- **` [optional human note]`:** Anything after the first space is treated as a human annotation and ignored by mechanical parsers.

### Example
```text
ui/modules/
├── 0010-j0ui-tokens--core-css/
├── 0020-tree-browser--ui-widget/
├── 0030-category-panel--ui-widget/
└── 0040-clipboard-actions--ui-helper/
```

---

## 3. File Naming & Polyglot Structure Inside Modules

A module directory may contain `.js`, `.css`, `.html`, `.php`, `.md`, and nested subdirectories. Recursive asset loaders scan the directory in `SORT_NATURAL` order and ignore entries starting with `_` or `.`.

- **Strict Lowercase:** All filenames and directories inside `j0` and `ai0` modules must be `lowercase` (`kebab-case`). Uppercase, PascalCase, or camelCase filenames are forbidden.
- **Globally Unique Names:** Never use generic names (`index.js`, `style.css`, `script.js`) inside modules so browser DevTools stack traces remain unambiguous.
- **Type + Order Prefix:**

| Prefix | Asset Type | Example |
| :--- | :--- | :--- |
| `c` | Stylesheet (`.css`) | `c0010-j0ui-core-tokens.css` |
| `j` | JavaScript IIFE module (`.js`) | `j0020-tree-browser.js` |
| `h` | HTML template / partial (`.html`) | `h0020-tree-node.html` |
| `p` | PHP module (`.php`) | `p0020-tree-renderer.php` |

---

## 4. Configuration Cascade (`[_]ai0config`) & Security Split (`.php` vs. `.json`)

`ai0` resolves configuration across four precedence levels (`1 > 2 > 3 > 4`):

1. `<PROJECT_ROOT>/_ai0config/` *(project-local private overrides & secrets)*
2. `<PROJECT_ROOT>/ai0config/` *(project-shared declarative config, tracked in Git)*
3. `<AI0_DIR>/_ai0config/` *(contained/mounted private overrides)*
4. `<AI0_DIR>/ai0config/` *(engine factory defaults)*

### Container vs. File Underscore Rule
- At the **root level**, both `ai0config` (shared) and `_ai0config` (private/local) are active configuration containers, with `_ai0config` taking higher priority.
- **Inside** any `[_]ai0config/` folder, standard file prefix rules apply: any file starting with `_` (`_prefixes.json`, `_🗑️old.json`) is ignored by the config loader.

### Format & Security Invariant
- **Sensitive Configs (`.php` with Execution Guard):** Credentials, API keys, FTP logins, and tokens MUST be stored in `.php` files guarded by `if (!defined('J0_EXEC')) { exit; }` returning an associative array, preventing plain-text exposure over HTTP.
- **Declarative Configs (`.json`):** Non-sensitive taxonomies, regex rules, and UI flags use `.json` so both PHP and JavaScript can consume them directly.

---

_ai0 & j0 (AI-DevTools) v5.0.11 • 2026-11-27 • Santiago Bustelo • [Apache 2.0 / CC BY 4.0 License](../LICENSE)_