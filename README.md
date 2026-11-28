# ai0 & j0 (AI-DevTools)

Local-first developer tooling (`ai0`) and a zero-dependency PHP/JS modular library (`j0`) built for real day-to-day AI-assisted development on conventional infrastructure—from static hosting to shared PHP—without paid coding platforms or framework lock-in.

The protocol and the `ai0` suite have been shaped and iterated across real projects with a clear target: zero hallucinations, zero code or service dependencies, and zero technical debt. The project's motto is:

<p align="center">
  <img src="./docs/assets/sb-quality-or-paddlin.png" alt="Quality or That's a Paddlin'" width="512">
</p>

**AI-DevTools** is organized around two core pieces:
- **`ai0/`**: A drop-in local workspace (`Files`, `patcher`, `i18n-batch`, and `Settings`) that lets you browse projects, generate clean context dumps, and apply atomic multi-file AI patches with automatic backups and rollback.
- **`j0/`**: A lightweight 2-layer PHP 8.2+ and Vanilla JS library (`core/` + OS-layered `src/`) providing location-independent asset/template resolution, declarative UI engines, isomorphic VFS, i18n, and zero-build bundling.

## Why

Most AI coding workflows lock you into proprietary IDEs, expensive API subscriptions, or heavy cloud build pipelines. `ai0` and `j0` take the opposite route:
- **Accessible models:** Work effectively with standard chat models—including free and low-cost tiers—using deterministic context dumps (`Files`) and atomic patch blocks (`patcher`).
- **No Vercel Tax:** Develop, bundle, and deploy on standard servers (macOS Valet, Apache, Nginx, shared PHP hosting, or static hosts) with zero Node/npm build chains required.

## Core Tools (`ai0/`)

| Tool | Purpose |
|---|---|
| **`Files`** (`ai0view.php`) | Full-viewport file browser with **Tree** and **Miller Column** views, `View` inspector, `Dump` context generator, `Purge`, `Restore`, hierarchical folder color labels, custom `.ai0root.json` icons, live search, and the topbar **Omnibar**. |
| **`patcher`** (`ai0patch.php`) | Atomic multi-file applier for `ai0` commands (`CREATE`, `PATCH`, `MOVE`, `DISABLE`, `DISCARD`, `DUMP`, `TREE`) with staged hunk recovery (`RETRY_HUNK` / `RESOLVE`), `=== ROOT: ===` safety checks, automatic `_📦` backups, Dry-Run preview, and `php -l` validation. |
| **`i18n-batch`** | Compares `i18n/{lang}.json` dictionaries or `M_dump()` catalogs by `source_hash`, validates `{placeholder}` integrity, and generates minimal AI translation delta prompts. |
| **`Settings`** (gear icon) | Interface and file-listing typography (local system fonts or Google Fonts), base font size/weight, UI bundle rebuilds, and `DEBUG` mode. |

## Quick Start

Copy or symlink `ai0/` into any project root as `_ai0` (natively ignored by `ai0` and `j0` scanners/builders; add `_*` to your project's `.gitignore` and FTP ignore rules):

```bash
cd /path/to/your-project
ln -s /path/to/AI-DevTools/ai0 _ai0
echo "_*" >> .gitignore
```

Open `http://your-project.test/_ai0/` (it opens `Files` directly) or copy a portable stub from `ai0/stubs/` into any subdirectory:
- `ai0view.php`: opens `Files` scoped to that subtree.
- `ai0patch.php`: applies project-root-relative `ai0` command blocks.
- `ai0build.php`: compiles local CSS/JS bundles in that folder.

## Website

Project page: http://bustelo.com.ar/lab/ai0

## Documentation

- [docs/ai0_quickstart_agentic_workflow_tutorial.md](docs/ai0_quickstart_agentic_workflow_tutorial.md): Installation and agentic development loop with `ai0`.
- [docs/customizing.md](docs/customizing.md): Custom folder icons (`_ai0src`), preset emojis (`icons.json`), folder colors, and typography.
- [docs/j0 & ai0 architecture.md](docs/j0%20%26%20ai0%20architecture.md): System architecture, 4-level `[_]ai0config` cascade, and `.php` vs. `.json` rules.
- [docs/j0-primitives.md](docs/j0-primitives.md): API reference for standalone `j0` PHP and JS modules.
- [docs/j0-tutorial.md](docs/j0-tutorial.md): Step-by-step adoption guide from standalone primitives to full framework.
- [docs/j0-naming-conventions.md](docs/j0-naming-conventions.md): Prefix taxonomy (`_`, `__`, `_🗑️`, `_📦`) and pseudo-BEM module naming.
- [docs/ai0_architecture_workflow_modules_guide.md](docs/ai0_architecture_workflow_modules_guide.md): `ai0` modules, workflow, and `j0` integration guide.
- [docs/ai0 CHANGELOG.md](docs/ai0%20CHANGELOG.md): Release history.

## Repository layout

```
ai0/                  local AI development suite (Files, patcher, i18n-batch, Settings, stubs)
j0/                   2-layer PHP/JS library (core/ primitives + OS-layered src/ modules)
AI-PerkeleProtocols/  critical ai0 protocol (ai0-perkele-architect) plus optional LLM guardrails
docs/                 architecture, customization, tutorials, and changelog
```

`AI-PerkeleProtocols/` starts with the core `ai0` protocol required for reliable patch and command workflows, alongside optional behavioral and style guardrails—named with a nod to Linus Torvalds' *management by perkele*.

## Status

Version `5.0.11`. Tested on PHP 8.2+ (macOS Valet, Apache, Nginx, PHP built-in server) and modern desktop browsers (Chrome, Firefox, Safari).

## License

Source code (`ai0/`, `j0/`) is released under Apache 2.0; documentation, protocols, and declarative data are [licensed under CC BY 4.0](LICENSE).

## ☕ Say thanks

`ai0` and `j0` are developed and maintained independently as open-source projects.

If you find them useful, you can [buy me a coffee](https://cafecito.bustelo.com.ar/).

Entirely optional, never required.

---

_ai0 & j0 (AI-DevTools) v5.0.11 • 2026-11-27 • Santiago Bustelo • [Apache 2.0 / CC BY 4.0 License](LICENSE)_