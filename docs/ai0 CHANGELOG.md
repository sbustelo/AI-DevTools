# Changelog

All notable changes to **AI-DevTools** (`ai0` and `AI-PerkeleProtocols`) are documented in this file.

---

## 5.0.02 • 24/nov/2026

- **Resilient Resource Locator & Location-Independent Primitives (`S()`, `T()`):**
  - Added lexical Union Mount indexing across host-mounted directories (`j0_mount()`), project `src/`, and `j0/src/` with project-over-system shadowing and an `O(1)` self-healing `.j0route.php` OPcache map (`flock` + atomic `rename()`).
  - `S(id)` resolves direct public URLs for web assets, gatekeeper routes (`api/serve.php`) for restricted tiers (`--isprotected`, `--registered`, `--plus`, `--pro`), and disk paths for PHP files; `T(id, vars)` renders HTML templates (with `<j0-ifphp>` / `<j0-ifjs>` filtering, inline `{S()}` / `{L()}` / `{M()}` macros, and `{{{raw}}}` / `{{escaped}}` interpolation), inline SVGs, and JSON resources without hardcoded paths.
  - Added copyable Layer 0 bootstrappers (`j0/quickstart/j0-bootstrap.php` and `j0-bootstrap-compact.php`) plus standalone and project-bootstrap examples, and removed the unused legacy `H()` alias.
- **OS-Layered Module Topology, Declarative UI Engines & Isomorphic VFS (`00xx`–`1xxx`):**
  - Structured `j0/src/` into numbered load zones (`0000` BIOS, `0100` Base OS, `0500` UI OS, and `1000+` reserved exclusively for userland applications).
  - Added zero-ID declarative UI engines under `0500-ui-patterns`: **Emitter** (`data-j0-action`), **Switcher** (tab/pane groups), **Binder** (two-way control synchronization with scaling and transforms), **Conditioner** (`show-when` / `hide-when` / `enable-when`), and **Dragger** (pointer and keyboard dragging with bounds, snapping, collision modes, and momentum).
  - Anchored the JS kernel (`01-j0kernel.js`) and VFS (`0100-vfs`, `0100-vfs--remote`) on `globalThis` so shared logic and storage run identically in browser windows, Web Workers, and sandboxed plugin threads, paired with PHP SSR cookie sync (`p0010-vfs-ssr.php`) to eliminate theme FOUC on first byte.
- **Deletion-Safe Build Signatures & Automatic Dependency Tracking:**
  - Replaced `mtime`-only checks with deterministic SHA-1 tree signatures (`path|mtime|size`) so deleted, renamed, or replaced files always invalidate cached bundles.
  - Added multi-bundle discovery (`j0-bundles.php`) and automatic dependency propagation between `j0-asset-tags.php` and `j0-static-html.php` so static HTML snapshots rebuild automatically whenever any underlying JS or CSS source changes.
- **Read-Only Engine Isolation & `ai0` Tooling Upgrades (`dumper`, `patcher`, `purge`, UI):**
  - Routed all runtime state (`ai0-state.json`) and compiled UI bundles into `<PROJECT_ROOT>/_j0-temp/ai0-ui/`, keeping `ai0/` and `j0/` strictly read-only when symlinked across projects.
  - **`dumper`:** Added a root master checkbox with bidirectional tri-state propagation (allowing an entire directory to be unchecked and a single inner file re-checked and exported cleanly), live included-file counts, a stateful `Collapse all` / `Expand all` toggle, and symlinked directory traversal during export.
  - **`patcher`:** Added KaTeX clipboard corruption bypass (`$` to `$` normalization), PHP token-level detection for glued variables and collapsed single-line statements, nested-fence tracking in `CREATE` blocks, and closest-line diagnostics when an anchor fails to match.
  - **`purge`:** Added preselected cleanup for OS metadata junk (`.DS_Store`, `Thumbs.db`, `._*`, `Desktop.ini`, `.directory`) alongside `_🗑️`, `__`, and `_📦` items.
  - **Persistent Theme Switch & Semantic CSS Tokens:** Replaced the transient theme button with `<j0-theme-switch>` backed by `j0.vfs` + SSR cookie persistence and strict semantic CSS token reuse (zero hardcoded colors).
- **Protocol & Documentation:**
  - Updated `AI-PerkeleProtocols/ai0-perkele-architect.en.md` to `v4.1.9`, enforcing CSS token SSOT and banning hardcoded color literals.
  - Added `docs/j0-tutorial.md` and consolidated `docs/j0 & ai0 architecture.md`.

---

## 5.0.01 • 21/nov/2026

- **KaTeX guards:** PHP guards for Gemini 4 brand new KaTeX code corruption quirk. Perkele Architect Protocol — Quality, or That's a Paddlin' updated to v4.1.8.
- **Decoupled Public Entry Point & Lazy Domain Loader (`j0/api.php`, `j0/core/j0-use.php`):**
  - Added `j0/api.php` (`j0_api()`) as a single public boundary façade so consumer projects locate one file and resolve framework capabilities without hardcoding internal `j0` directory paths (`core/`, `tooling/`, `src/`).
  - Added `j0/core/j0-use.php` (`j0_use()`, `j0_root()`, `j0_asset_path()`, `j0_asset_dir()`) to lazily load module domains (`core`, `tooling`, `vfs`, `all`) and resolve logical client assets while keeping every individual `j0` PHP module 100% standalone-includable via `require_once`.
- **Patcher KaTeX Corruption Scanner (`ai0/tools/patcher/patcher-katex-guard.php`, `patcher-engine.php`, `patcher-view.php`):**
  - Added `PatcherKatexGuard` to audit incoming `CREATE` and `PATCH` (`START`, `END`, `REPLACE`) payloads on code files for chat clipboard math-parser artifacts (`\vert{}`, `\%`, `\&`, `\#`, `\_`, and LaTeX math commands), skipping `.md` and `.tex` documentation files.
  - Wired non-blocking `warnings` into `PatcherEngine::execute()` and rendered them in `patcher-view.php` under `Batch Result`.
- **Protocol Output Guardrail (`AI-PerkeleProtocols/perkele-architect.en.md` v4.1.3):**
  - Enforced single-condition `if` splits in PHP blocks and pure string concatenation in JS/CSS/HTML blocks to prevent chat KaTeX inline-math collisions on copy.


---

## [5.0] — `ai0` Integration & Perkele Architect Protocol (2026-11-21)

### Added
- **`ai0/` Suite (`v0.11`):** Replaced legacy single-file `project-dumper` with a modular, zero-dependency developer tooling suite (`dumper`, `patcher`, `restore`, `purge`, `DevTools` Control Panel, and portable single-file stubs).
- **3-Layer `j0 v2` Topology (`core/`, `tooling/`, `src/`):** Organized `ai0/j0/` by architectural layer instead of file extension: unified `01-j0kernel.js` (EventBus + Registry + passive Logger) and core PHP functions in `j0/core/`, build/IO utilities in `j0/tooling/`, and vertical polyglot modules (`vfs/` + `vfs--remote/` capability extension) in `j0/src/`.
- **4-Level Configuration Cascade (`[_]ai0config`):** Added `j0-config.php` supporting `<PROJECT_ROOT>/_ai0config` > `<PROJECT_ROOT>/ai0config` > `<AI0_DIR>/_ai0config` > `<AI0_DIR>/ai0config`, enforcing `.php` files with `J0_EXEC` guards for secrets and `.json` for declarative rules.
- **Semantic Prefix Taxonomy (`j0-prefix.php` & `docs/j0-naming-conventions.md`):** Formalized `_` (human disabled/private), `__` (human discard / mark for deletion), `_🗑️` (mechanical/AI discard / mark for deletion), and `_📦` (mechanical backup/archive), plus pseudo-BEM module naming (`NNNN-name--tags`).
- **Deterministic Patch Operations:** Added `=== MOVE: src -> dst ===` and `=== DISCARD: path ===` alongside `CREATE`, `PATCH` (`1-Line First` anchoring), and `DISABLE`.
- **Architecture & Primitives Documentation:** Added `docs/architecture.md` and `docs/j0-primitives.md`.

### Changed
- **Dual License (`Apache 2.0` + `CC BY 4.0`):** Replaced MIT with Apache License 2.0 for all source code (`ai0/`) and Creative Commons Attribution 4.0 International for documentation, protocols, and declarative data (`AI-PerkeleProtocols/`, `docs/`, `README.md`, `LEAME.md`, `ai0/ai0config/`).
- **`AI-PerkeleProtocols/`:** Published the English architect skill (`perkele-architect.en.md` v4.1.1) and deterministic continuity handoff spec (`perkele-session-handoff.en.json`), standardized `PERKELE`, `PADDLIN`, and `TABLA` as interchangeable triggers for the 5-step Forced Audit & Self-Evolution Protocol, and refined `PERKELE-PHANTOM-WRITER-V2.02.es.md`.

### Deprecated / Discarded
- Deleted legacy `project-dumper/` docs (`USAGE.md`, `USO.md`) and superseded `CALIDAD O TABLA` v4.0.15 / v3.510 files.

---

## [4.0.11 – 4.0.15] — 2026-10-23
- Migrated `CALIDAD O TABLA` system prompt from JSON to Markdown, later refined locally to `V4.0.15.md` with strict internal `PATH:` header rules and Markdown fence protections.

## [3.520 + Anti-Bullshit r.2026-09-03] — 2026-09-03
- Added standalone bilingual epistemic control specifications (`anti-bullshit protocol.en.txt` and `anti-bullshit protocol.es.txt`) enforcing `UNKNOWN DATA` / `DATO DESCONOCIDO` thresholds.

## [3.510 Handoff] — 2026-08-07
- Added `FILE PAYLOAD` mirror-copy terminal instructions to `PEDIDO DE CIERRE V3.510.es.json` for deterministic cross-session continuity.

## [3.517 – 3.520] — 2026-05-01 to 2026-06-09
- Added biological precedence rule (`v3.517`), model tier reminder (`v3.518`), HTML comment syntax rules (`v3.519`), and anti-rush safeguards (`v3.520`).

## [3.508 – 3.513 & Phantom Writer v2.01] — 2026-04-03 to 2026-04-08
- Consolidated repository under `AI-PerkeleProtocols/`, retiring `AI-First-Directive/` and early `STYLE-WRITER` drafts.
- Introduced `PERKELE-PHANTOM-WRITER-V2.01.es.md` with Phase 0–4 style absorption and anti-cliché rules.

## [3.504 – 3.507] — 2026-03-14 to 2026-03-18
- Released bilingual `CALIDAD O TABLA` / `QUALITY OR PADDLIN'` v3.504, URL-friendly directory naming, and iterative anti-regression patches through v3.507.

## [Project Dumper v2.11.0 & Coding Kernel v3.019] — 2026-02-24
- Released `_project-dump2.11.0.php` featuring smart `default` vs `all` profiles, interface-only pruning (`interface-*.js`), `vendor`/`node_modules` summaries, compact mode, binary detection, and web UI with clipboard/download actions.
- Added `ANTI-BULLSHIT CODING KERNEL V3.019.json`.

## [AI-Perkele-Protocol v1.0] — 2025-12-09
- Introduced the initial `PERKELE-PROTOCOL.txt` override mechanism and updated bilingual READMEs.

## [AI-First-Directive v1.0 – v2.111] — 2025-08-21 to 2025-10-21
- **2025-10-21:** Expanded `AI-First-Directive` to `v2.109` and `v2.111` in Spanish and English with dedicated documentation.
- **2025-08-22 to 2025-08-23:** Added continuity summary rule, Jef Raskin's Three Laws of Interaction Design adapted to AI-Human interaction, and stability principles.
- **2025-08-21:** Initial release of **AI-DevTools** with `project-dumper/_project-dump.php` (v1.0) and `AI-First-Directive`.