[Versión en español](LEAME.md)

# AI-DevTools & j0 Library 🛠️🤖

<p align="center">
  <img src="./docs/assets/sb-quality-or-paddlin.png" alt="Quality or That's a Paddlin'" width="512">
</p>

Protocols to keep AI in check and technical debt under control. Tools for AI-assisted development without being tied to expensive services, platforms, or infrastructure.

-   **Accessible models:** agentic development using chat models, including free and low-cost options, instead of relying on specialized coding services.
-   **No Vercel Tax:** projects that can be developed, built, and deployed on conventional infrastructure, from static hosting to shared PHP hosting.

---

## 🛠 Repository Contents

### 1. `ai0/` — Local AI Development Suite & `j0` Primitives
Drop-in PHP 8.2+ and Vanilla JS tooling (runs on macOS Valet, Apache, Nginx, or PHP built-in server with zero external dependencies):

- **`Files` (`ai0dump.php`):** Unified full-viewport file browser (`View` inspector, `Dump` context generator, `Purge`, and `Restore`) with custom `.ai0root.json` icons, cascading folder colors, modular context actions, live search, and the topbar **Omnibar**.
- **`patcher` (`ai0patch.php`):** Atomic multi-file applier for `ai0 commands` (`CREATE`, `PATCH`, `MOVE`, `DISABLE`, `DISCARD`, `DUMP`, `TREE`), with `=== ROOT: ===` mismatch confirmation, automatic `_📦` backups, Dry-Run preview, and `php -l` validation.
- **`restore` (`ai0restore.php`):** Discovers `_📦` timestamped backup batches across the project and restores previous versions in one click.
- **`purge` (`ai0purge.php`):** Reclaims disk space by preselecting discarded (`_🗑️`, `__`) and backup (`_📦`) items while keeping human-disabled (`_*`) files protected by default.
- **`i18n-batch`:** Compares unit `i18n/{lang}.json` dictionaries or `M_dump()` catalogs by `source_hash`, validates `{placeholder}` integrity, and generates minimal AI delta prompts.
- **`j0/` 2-Layer Modular Suite (`core/` + OS-layered `src/`):**
  - `j0/core/`: Pure PHP locator, config, prefix, scanner, and semantic domain loader (`j0-closest`, `j0-config`, `j0-locate`, `j0-prefix`, `j0-scan`, `j0-use`).
  - `j0/src/`: OS-layered vertical modules (`0010-kernel/j0010-kernel.js`, `0100-vfs` + `0100-vfs--remote`, `0110-i18n`, `0120-assets`, `0130-cache`, `0180-fs-io`, `0190-bundler`, `0500-ui-patterns`, `0510-ui-menus`, and `0520-ui-windows`).

#### Quick Start (`ai0`)
Copy or symlink `ai0/` into any project root as `_ai0` (natively excluded by `ai0` and `j0` scanners/builders; add `_*` to your project's `.gitignore` and FTP ignore rules):

```bash
cd /path/to/your-project
ln -s /path/to/AI-DevTools/ai0 _ai0
echo "_*" >> .gitignore
```

Open `http://your-project.test/_ai0/` or copy any portable stub from `ai0/stubs/` into any subdirectory: `ai0dump.php`, `ai0restore.php`, and `ai0purge.php` scope scans to that subtree, `ai0build.php` compiles local CSS/JS bundles in that folder, and `ai0patch.php` applies project-root-relative `_ai0` blocks.

---

### 2. `AI-PerkeleProtocols/` — LLM Behavioral & Epistemic Overrides ☠️🇫🇮
System prompts and skills engineered by **Santiago Bustelo** (inspired by Linus Torvalds' *Management by Perkele*) to counter RLHF token-saving shortcuts:

- **`ai0-perkele-architect.en.md`:** Primary coding & architecture skill (*Quality, or That's a Paddlin'*).
- **`perkele-session-handoff.en.json`:** Deterministic session continuity generator for saturated context windows.
- **`PERKELE-PHANTOM-WRITER-V2.02.es.md`:** Style-preserving ghostwriting protocol (Spanish).
- **`anti-bullshit protocol.en.txt` / `.es.txt`:** Epistemic anti-hallucination guardrails.

---

### 3. `docs/` — Technical Specifications
- [`docs/j0 & ai0 architecture.md`](docs/j0%20%26%20ai0%20architecture.md) — System architecture, 4-level `[_]ai0config` cascade, and `.php` vs. `.json` security rules.
- [`docs/j0-naming-conventions.md`](docs/j0-naming-conventions.md) — Prefix taxonomy (`_`, `__`, `_🗑️` *Discard*, `_📦`) and pseudo-BEM module conventions.
- [`docs/j0-primitives.md`](docs/j0-primitives.md) — API reference for standalone `j0` PHP and JS modules.
- [`docs/j0-tutorial.md`](docs/j0-tutorial.md) — Step-by-step adoption guide from standalone primitives to full framework.
- [`docs/ai0_architecture_workflow_modules_guide.md`](docs/ai0_architecture_workflow_modules_guide.md) — `ai0` modules, workflow, and `j0` integration guide.
- [`docs/ai0_quickstart_agentic_workflow_tutorial.md`](docs/ai0_quickstart_agentic_workflow_tutorial.md) — `ai0` setup and agentic development loop tutorial.
- [`docs/ai0 CHANGELOG.md`](docs/ai0%20CHANGELOG.md) — Full version history.

---

## ✳️ Credits & Articles
Created by **Santiago Bustelo** (https://www.bustelo.com.ar/).
- Perkele Coding: https://santiagobustelo.medium.com/perkele-coding-ed60e900e149
- The AI Perkele Suit: https://medium.com/santiago-bustelo-in-english/the-ai-perkele-suit-a-framework-for-measuring-real-world-ai-pain-03e3d64f1ff8
- Gemini's Technical Confession: https://santiagobustelo.medium.com/gemini-just-confirmed-the-effectiveness-of-perkele-prompting-a-technical-confession-c28458df9a2c

## ⚖️ License
[Apache 2.0 / CC BY 4.0 License](LICENSE)