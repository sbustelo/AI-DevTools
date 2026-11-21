[Versión en español](LEAME.md)

# AI-DevTools 🛠️🤖

<p align="center">
  <img src="./docs/assets/sb-quality-or-paddlin.png" alt="Quality or That's a Paddlin'" width="512">
</p>

A zero-dependency suite of surgical tools and operational protocols for AI-assisted software development—designed to eliminate LLM laziness, prevent context drift, and enforce zero technical debt.

---

## 🛠 Repository Contents

### 1. `ai0/` — Local AI Development Suite & `j0` Primitives
Drop-in PHP 8.2+ and Vanilla JS tooling (runs on macOS Valet, Apache, Nginx, or PHP built-in server with zero external dependencies):

- **`dumper` (`ai0dump.php`):** Interactive checkbox tree with regex exclusion categories, **Show excluded only** filter, binary summaries, and one-click copy/download context generation.
- **`patcher` (`ai0patch.php`):** Atomic multi-file applier supporting `CREATE`, `PATCH` (`1-Line First` anchoring), `MOVE`, `DISABLE` (`_`), and `DISCARD` (`_🗑️`), with automatic `_📦` backups and `php -l` syntax validation.
- **`restore` (`ai0restore.php`):** Discovers `_📦` timestamped backup batches across the project and restores previous versions in one click.
- **`purge` (`ai0purge.php`):** Reclaims disk space by preselecting discarded (`_🗑️`, `__`) and backup (`_📦`) items while keeping human-disabled (`_*`) files protected by default.
- **`j0/` 3-Layer Modular Suite (`core/`, `tooling/`, `src/`):**
  - `j0/core/`: Unified `01-j0kernel.js` (EventBus + Service Registry + passive Logger) and foundational PHP primitives (`closest`, `config`, `prefix`, `scan`).
  - `j0/tooling/`: Composable build, atomic save, backup, asset tag, and static HTML utilities (`ai0build.php` stub).
  - `j0/src/`: Vertical polyglot OS modules (`vfs/` for KV + SSR cookie sync + IndexedDB blobs/docs, and `vfs--remote/` capability plugin for `j0auth` cloud sync).

#### Quick Start (`ai0`)
Link `ai0/` into any project root as `_ai0` (natively excluded by `ai0` and `j0` scanners/builders; add `_*` to your project's `.gitignore` and FTP ignore rules):

```bash
cd /path/to/your-project
ln -s /path/to/AI-DevTools/ai0 _ai0
echo "_*" >> .gitignore
```

Open `http://your-project.test/_ai0/` or copy any portable stub from `ai0/stubs/` into any subdirectory: `ai0dump.php`, `ai0restore.php`, and `ai0purge.php` scope scans to that subtree, `ai0build.php` compiles local CSS/JS bundles in that folder, and `ai0patch.php` applies project-root-relative `_ai0` blocks.

---

### 2. `AI-PerkeleProtocols/` — LLM Behavioral & Epistemic Overrides ☠️🇫🇮
System prompts and skills engineered by **Santiago Bustelo** (inspired by Linus Torvalds' *Management by Perkele*) to counter RLHF token-saving shortcuts:

- **`perkele-architect.en.md`:** Primary coding & architecture skill (*Quality, or That's a Paddlin'*).
- **`perkele-session-handoff.en.json`:** Deterministic session continuity generator for saturated context windows.
- **`PERKELE-PHANTOM-WRITER-V2.02.es.md`:** Style-preserving ghostwriting protocol (Spanish).
- **`anti-bullshit protocol.en.txt` / `.es.txt`:** Epistemic anti-hallucination guardrails.

---

### 3. `docs/` — Technical Specifications
- [`docs/architecture.md`](docs/architecture.md) — System architecture, 4-level `[_]ai0config` cascade, and `.php` vs. `.json` security rules.
- [`docs/j0-naming-conventions.md`](docs/j0-naming-conventions.md) — Prefix taxonomy (`_`, `__`, `_🗑️` *Discard*, `_📦`) and pseudo-BEM module conventions.
- [`docs/j0-primitives.md`](docs/j0-primitives.md) — API reference for standalone `j0` PHP and JS modules.
- [`docs/CHANGELOG.md`](docs/CHANGELOG.md) — Full version history reconstructed from Git.

---

## ✳️ Credits & Articles
Created and conceptualized by **Santiago Bustelo** (https://www.bustelo.com.ar/).
- Perkele Coding: https://santiagobustelo.medium.com/perkele-coding-ed60e900e149
- The AI Perkele Suit: https://medium.com/santiago-bustelo-in-english/the-ai-perkele-suit-a-framework-for-measuring-real-world-ai-pain-03e3d64f1ff8
- Gemini's Technical Confession: https://santiagobustelo.medium.com/gemini-just-confirmed-the-effectiveness-of-perkele-prompting-a-technical-confession-c28458df9a2c

## ⚖️ License
[Apache 2.0 / CC BY 4.0 License](LICENSE)