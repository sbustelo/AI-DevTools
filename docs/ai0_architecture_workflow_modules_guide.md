# `ai0` — Architecture, Workflow & Modules Guide

**`ai0`** is the development tooling suite and operational control panel for projects built with **`j0`**. Its purpose is to enable rapid, AI-assisted iteration while keeping files modular and small (preventing monolithic files that AI models tend to break or truncate), preserving reversible backups, and running smoothly on modest infrastructure (free tiers, shared hosting, or ~$2/month servers) without the technical debt or bloated patterns of over-engineered enterprise architectures.

---

## 1. Philosophy and Why `ai0` Exists

When iterating with AI on growing codebases, large files (over ~150 lines) are prone to context drift, partial edits, and silent regressions. The natural solution is to split code into many small, single-responsibility modules and directories.

However, working with dozens of small files introduces two practical challenges:
1. **Context fragmentation:** Manually copying and pasting 10, 40, or 100 files into an AI chat is slow and error-prone.
2. **Production delivery overhead:** Loading dozens of unbundled files in the browser degrades load times unless they are consolidated and minified.

`ai0` solves the full lifecycle around that trade-off:
- **Unified AI context:** Generate a structured project or sub-tree *dump*, hand it to the AI, and receive a deterministic patch block in return.
- **Atomic patching with a safety net:** The *patcher* applies changes while creating timestamped backups (`_📦`) that act as restore points before overwriting or modifying files.
- **In-place deferred cleanup ("Empty Trash" model):** Instead of moving deleted items into an external hidden trash folder, discarded or backed-up files remain marked in place (`_🗑️`, `__`, `_📦`) until you sweep them all at once with *purge*.
- **Lean production output via `j0`:** `j0` compiles and minifies JS and CSS modules JIT (or on demand) for production, and `ai0` understands these conventions so it never floods the AI context with redundant build artifacts.

---

## 2. The `ai0` Skill & Workflow Protocol

The standard `ai0` workflow follows a disciplined round-trip loop between your repository, the AI, and the browser:

1. **Skill / Operational Protocol:**
   Defines the working contract with the AI (modular scope, bounded patches, strict adherence to `j0` conventions, and a parseable output format) so every response is deterministic and ready to apply without blind full-file rewrites.

2. **Dumper (Context Capture):**
   The first step with `ai0` as a tool is running the **dumper** (now integrated inside **Files**). You select the target tree or subdirectory, apply exclusion filters, and generate a clean project dump to pass to the AI.

3. **AI Response & Patcher (Atomic Apply + Restore Point):**
   The AI returns a structured block of operations (`CREATE`, `PATCH`, `MOVE`, `DISABLE`, `DISCARD`, or read-only inspection commands `DUMP` / `TREE`). You paste it into the **patcher** and apply it:
   - Before overwriting existing files, the engine creates `_📦` backups that serve as an automatic **restore point**.
   - If anything breaks or needs to be undone, you can roll back the batch or restore individual files from **restore**.

4. **Purge (Batch Sweep — "Empty Trash"):**
   Once the patch is applied and verified, you go to **purge**. Rather than using a separate trashcan directory, `ai0` marks discarded items (`_🗑️` / `__`) and backups (`_📦`) right where they live so you can sweep them all together in one explicit **purge** step—acting as an **Empty Trash** action once you are confident in the changes.

---

## 3. What Each `ai0` Module Does

### 3.1 Core & Runtime Context (`ai0/core/`)
- **`ai0-context.php`:** Resolves the runtime environment (`root`, active `ai0` directory, 4-tier configuration domains, `_j0-temp` vault, persisted state, and relative/web paths), enforces localhost-only access, and provides state and ZIP snapshot utilities (`_📦...zip`).

### 3.2 Visual Shell & UI Modules (`ai0/ui/`)
- **`ui-layout.php`:** Renders the shared application shell (topbar, active **Project Root** chip, **Omnibar** for paths/commands/search, tool navigation, light/dark theme toggle, language selector, and CSS/JS asset tags in normal or `DEBUG` mode).
- **`ai0/ui/modules/`:** Self-contained UI widgets and styles (CSS token SSOT, theme switch, Omnibar, collapsible tree browser with VFS-persisted state and live search, category filter panels, clipboard/download actions, live purge counters, resizable column splitters, `View` inspector, and Icon Picker modal).

### 3.3 Tools Hub & Portable Stubs (`ai0/tools/index.php` and `ai0/stubs/`)
- **`ai0/tools/index.php` (DevTools Control Panel):** Central dashboard linking to `Files` (`Dump`, `Purge`, `Restore`), `patcher`, and `i18n-batch`, plus environment controls such as `Rebuild UI` and toggling `DEBUG` mode.
- **`ai0/stubs/` (`ai0dump.php`, `ai0patch.php`, `ai0restore.php`, `ai0purge.php`, `ai0build.php`, `.j0route.php`):** Lightweight entrypoints that can be dropped or linked into any project to dynamically locate the `j0`/`ai0` engine without hardcoded paths.

### 3.4 `Files` (`ai0/tools/files/`)
- **Unified Full-Viewport Explorer:** Combines a collapsible directory tree (with custom `.ai0root.json` icons, opaque Finder-style cascading folder colors, modular `ai0src/actions/` context menu, and live search with bulk match selection) with 4 integrated right-pane tabs: **`View`** (file/folder inspector, permissions, rename, preview), **`Dump`** (exclusion categories + copy/download-ready dumps stamped with `// Project root:`), **`Purge`**, and **`Restore`**.

### 3.5 `patcher` (`ai0/tools/patcher/`)
- **Atomic `ai0 commands` Applier & Read Inspector:** Operates strictly against the active **Project Root** (halting with a confirmation modal if a `=== ROOT: ===` pragma targets a different directory), executes `CREATE`, `PATCH`, `MOVE`, `DISABLE` (`_`), `DISCARD` (`_🗑️`), `TREE`, and `DUMP`, creates `_📦` backups prior to writes, runs `php -l` syntax checks, supports `Dry-Run` previews and batch rollback, and auto-clears the input textarea after error-free execution.

### 3.6 `restore` (`ai0/tools/restore/`)
- **Backup Recovery (`_📦`):** Scans timestamped `_📦` backup files across the active scope and restores either individual files or entire batches in one click.

### 3.7 `purge` (`ai0/tools/purge/`)
- **Space Reclamation ("Empty Trash"):** Classifies marked items across the project—separating discarded items (`_🗑️` / `__`), backups (`_📦`), and OS metadata junk (`.DS_Store`, `Thumbs.db`, etc.) from human-disabled (`_`) files—so you can permanently delete unwanted artifacts in bulk.

### 3.8 `i18n-batch` (`ai0/tools/i18n-batch/`)
- **Translation Delta Tool:** Compares unit-level `i18n/{lang}.json` dictionaries or passive `M_dump()` catalogs by `source_hash`, detects new, modified, or orphaned keys, validates `{placeholder}` integrity, and generates minimal AI delta translation prompts.

---

## 4. Chapter: `j0` – `ai0` Integration

`ai0` is not merely an isolated UI built on top of `j0`: **it is engineered to understand `j0`'s architecture and serve as its native companion whenever you adopt `j0` as a library or framework.**

### 4.1 JIT Compilation and Minification of JS and CSS
Splitting frontend code into small, granular files and directories keeps AI edits accurate and prevents large-file breakage, but increases file count. `j0`'s bundler compiles and minifies JS and CSS **JIT (or on demand)** into production-ready bundles without requiring heavy build pipelines or expensive infrastructure.

### 4.2 `j0` Build Artifact Awareness in the `dumper`
`ai0`'s `dumper` recognizes `j0` build conventions and artifacts (compiled bundles, `.min.js` / `.min.css` files, and `j0` compilation pragmas). As a result, **the `dumper` avoids feeding duplicate compiled or minified files to the AI**, keeping the prompt focused strictly on source code and well within token budgets.

### 4.3 `ai0` as Native Tooling for `j0` Projects (`i18n` & Modular Workflow)
When a project adopts `j0`, `ai0` provides the operational tooling around the core runtime:
- **First-class `i18n` workflow:** `j0` supplies the isomorphic localization primitives (`L()`, `M()`, placeholder interpolation, unit JSON dictionaries, and `M_dump()`), while `ai0`—via `i18n-batch` and built-in multi-language UI support—keeps translations synchronized as the codebase evolves with AI.
- **Zero-debt modular lifecycle:** Short, modular files for AI + `dumper` & `patcher` with `_📦` restore points + deferred `purge` + `j0` JIT bundling makes it practical to build and maintain real production applications on **free tiers, free hosting, or ~$2/month servers**, free from enterprise pattern bloat and unnecessary technical debt.

*(Additional `j0`–`ai0` integration patterns will be documented as they are added to the suite.)*

---

_ai0 & j0 (AI-DevTools) v5.0.11 • 2026-11-27 • Santiago Bustelo • [Apache 2.0 / CC BY 4.0 License](../LICENSE)_