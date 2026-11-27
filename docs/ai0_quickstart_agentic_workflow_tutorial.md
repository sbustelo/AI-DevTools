# `ai0` — Quickstart & Agentic Workflow Tutorial

This tutorial walks through installing `ai0` into any project, understanding how directory discovery and underscore prefixes work, and running a complete AI-assisted development loop—from capturing project context in **Files** to applying multi-file patches in **patcher**, verifying with **restore**, and sweeping up with **purge**.

---

## 1. Setup: Linking `ai0` and Copying a Stub

`ai0` is designed to sit alongside or inside your project without polluting your production codebase or requiring complex configuration files.

### Step 1: Make `ai0` Discoverable in Your Project
Place or symlink the `ai0` repository folder in your project root (or any parent directory up to 12 levels above your working folder). By convention, name the link or folder **`_ai0`** (with a leading underscore):

```text
my-project/
├── _ai0/                  # Symlink or copy of AI-DevTools/ai0
├── _ai0dump.php           # Optional portable stub (see Step 2)
├── src/
└── index.php
```

> **Why `_ai0` with a leading underscore?**
> In the `j0` / `ai0` ecosystem, a leading underscore (`_`) marks files and directories as **disabled / private / tooling-only**. Both `j0` scanners and `ai0`'s own **Files** dumper automatically recognize leading underscores and exclude them by default. Naming the folder `_ai0` and your copied stubs `_ai0dump.php` or `_ai0patch.php` guarantees that **`ai0` always ignores itself and its stubs** when generating dumps or building bundles—so you never accidentally send the tooling code to your AI or ship it to production.

### Step 2: Copy a Stub (or Run `ai0/` Directly)

You have two ways to launch `ai0` over HTTP in your local development server:

#### Option A: Copy a Portable Stub Into Your Project (Recommended)
Copy one of the stub files from `ai0/stubs/` (for example, `ai0dump.php` or `ai0patch.php`) into your project root—or into any subdirectory where you want to focus your work—and prefix it with `_` (e.g., `_ai0dump.php`):

```text
my-project/
├── _ai0/
├── _ai0dump.php           # Copied from _ai0/stubs/ai0dump.php
└── modules/
    └── billing/
        └── _ai0dump.php   # Scoped directly to modules/billing/
```

When you open `http://localhost/my-project/_ai0dump.php` in your browser:
1. The stub walks upward (up to 12 parent levels) looking for `_ai0/` or `ai0/`.
2. Once found, it caches the resolved path in a local `.j0route.php` file for instant subsequent loads.
3. It opens **`ai0` already scoped to the exact directory where the stub lives**, ready to work.

#### Option B: Run `ai0/` Directly
You can also mount `ai0` directly inside your project or web root and visit `http://localhost/my-project/ai0/` (or `http://localhost/my-project/_ai0/`). Use the topbar **Omnibar** to navigate to any path (resolved via Postel's Principle), search files, or paste `ai0 commands` directly.

---

## 2. Step-by-Step: The Agentic Development Loop

Once `ai0` is open in your browser, you have a complete, repeatable workflow for building and maintaining software with AI.

### Step 1: Load the `ai0` Skill Into Your AI Chat
Before asking the AI to write or modify code, provide it with the **`ai0` skill / operational protocol** (from `AI-PerkeleProtocols/`). This instructs the AI to:
- Keep files small and modular (preventing large-file breakage).
- Follow `j0` architectural rules (`IIFE`, `EventBus`, semantic tokens).
- Return changes in `ai0`'s deterministic `_ai0` block format (`=== CREATE ===`, `=== PATCH ===`, `=== MOVE ===`, `=== DISABLE ===`, `=== DISCARD ===`) rather than asking you to manually copy-paste snippets across files.

### Step 2: Capture Project Context in **Files** (`Dump`)
The first module you use is **Files** (opened by default via `_ai0dump.php` or from the topbar):
1. Review the directory tree on the left. Files and folders excluded by rule (such as `_ai0/`, `.git/`, `node_modules/`, backups, and compiled bundles) are automatically unchecked.
2. Adjust individual checkboxes or toggle exclusion categories on the right if needed.
3. Click **Generate Dump** and then **Copy Dump** (or **Download .txt**).

### Step 3: Prompt Your AI and Receive a Patch
Paste the dump into your AI chat along with your feature request, bug report, or refactor goal. Because the AI has the `ai0` skill loaded, it will respond with a single structured `_ai0` patch block covering one or many files at once.

### Step 4: Apply Changes in **patcher** (Automatic `_📦` Restore Point)
Switch to **patcher** in the topbar (or paste the `ai0 commands` block straight into the topbar **Omnibar** and press `Enter`):
1. Paste the AI's response into the **Input** column on the left. If the block declares a `=== ROOT: ... ===` path that differs from your active Project Root, `patcher` pauses with a confirmation modal before touching disk.
2. *(Optional)* Click **Dry-Run Preview** to validate anchor matches and run `php -l` syntax checks without touching disk.
3. Click **Run / Apply**.
   - Before modifying or overwriting any file, **patcher** automatically creates a timestamped `_📦` backup right next to the original file (e.g., `_📦index_2026-11-26T03-30-00.php`).
   - The **Output** column on the right displays an `OK` summary—or, if an anchor failed, a copy-ready `ERROR REPORT _ai0` you can paste straight back to the AI to regenerate only the failed block.

### Step 5: Verify, Roll Back if Needed, and Clean Up in **Purge**
1. Test your changes in the browser:
   - If something went wrong, click **Rollback batch** in **patcher** or open the **Restore** tab in **Files** to revert the entire batch or individual files from their `_📦` backups.
2. Once everything works as expected, open the **Purge** tab in **Files**:
   - Instead of hiding deleted files in a separate trash folder, `ai0` keeps discarded items (`_🗑️`, `__`) and backups (`_📦`) in place so nothing is ever lost silently.
   - Review the preselected items in **Purge** and click **Delete selected items** to sweep them all at once—acting as your explicit **Empty Trash** step.

---

## 3. Multi-Chat Agentic Parallelism

Because your project is split into small, focused modules and **Files** lets you scope dumps to specific directories or file selections, **nothing limits you to a single AI chat**:

- **Parallel feature tracks:** Open Chat A with a dump of `modules/auth/` and Chat B with a dump of `modules/billing/`. Each chat operates on its own clean slice of context.
- **Sequential patch application:** When each chat returns its `_ai0` patch block, apply them one after another in **patcher**. Each batch creates its own timestamped `_📦` restore point, keeping every agentic thread isolated, auditable, and reversible.
- **On-demand mini-dumps from `patcher`:** If an AI chat asks to inspect one or two extra files mid-conversation, you do not even have to leave **patcher**—paste `=== DUMP: path/to/file.php ===` into the left column, run it, and copy the resulting mini-dump from the right column.