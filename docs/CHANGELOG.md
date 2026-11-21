# Changelog

All notable changes to **AI-DevTools** (`ai0` and `AI-PerkeleProtocols`) are documented in this file.

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