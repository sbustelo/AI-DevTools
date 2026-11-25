# j0 — Step-by-Step Tutorial: Agentic Development Without the "Vercel Tax"

*AI-assisted development tools and protocols to keep LLMs in check, eradicate technical debt, and build without expensive platforms or vendor lock-in.*

---

## 0. Why Does `j0` Exist? (The Real Problem)

If you have built complex applications assisted by AI (Cursor, Lovable, Copilot), you have surely experienced a breaking point where **the AI can no longer maintain its own code**. It starts by breaking existing functionality, triggering a refactor. That buys some time, until the next issue, which takes longer to stabilize. Up to the point where `timeRefactoring > timeWorkingNewFeatures`. At which point you _know_ the project has become a derailed train asking to be scrapped, accelerating in a death spiral to the station: `timeRefactoring > timeAvailable`.

This happens because generative AIs were trained on billions of lines of "Enterprise" code (designed for, and written by, 500-person Java/React teams). The AI replicates this bureaucratic structure automatically: dependency spaghetti, circular imports, massive class hierarchies, and mutating global states. But as AI doesn't have enough context to understand architectural decisions' impact in the long run, all those patterns end up being piles of incoherent, undebuggable bloat.

### The Trap (The "Vercel Tax")
The industry's solution to synthetic incompetence is to offer an _upgrade_ to a _more expensive AI model_ to throw _brute-force context tokens_ at a problem _it created_. And as a cherry on top, to lock you into expensive serverless hosting (e.g., Vercel) to run it. Once your project is held hostage on that train, its next station will be a `USD 6,000 peak surprise` (or your project being paused and taken offline the moment it crosses free limits). And that is how, in the AI gold rush, the ones selling pickaxes are becoming billionaires.

### `j0`: Zero Technical Debt, Zero Surprises
`j0` is the counter-offensive: a modular, zero-configuration toolkit designed to tame cognitive load for both humans and machines, aiming to eradicate technical debt from day one. It's easier to keep weeds in line when they are still seedlings than when they have deep roots, invade the foundations, embrace the walls, and crack the stone.

By solving the architecture up front, `j0` frees you from the "Vercel Tax":
- `j0` compiles your project into **pure HTML and JS**. You can host your application for free on Cloudflare Pages or GitHub Pages with unlimited static bandwidth and zero surprises.
- If you need a backend (Authentication, APIs), `j0` uses **Vanilla PHP**. You can run it on any standard shared hosting plan for **~USD 2/month**—flat, predictable cost with zero lock-in.

`j0` functions can be used separately in any existing project, and their adoption can evolve progressively until they become the framework shaping your entire application.

Below is a reasonable adoption path, based on how I applied, re-applied, and evolved `j0` in my own projects.

---

## 1. First Adoption: The JS Kernel (EventBus)

The first piece of `j0` is the **JavaScript Kernel** (`01-j0kernel.js`). It is a tiny 4 KB file (2 KB minified) that eliminates frontend complexity and fragile `import`/`export` trees.

In my experiments, I found that LLMs stop breaking files (e.g., deleting or breaking working parts when adding a new one) if they do not exceed **100–150 lines**. `j0`'s survival rule is to always keep code separated into small files that do not rigidly depend on each other. To achieve this, `j0` uses a centralized **EventBus** and self-executing functions (**IIFEs**).

### The Isolation Pattern (IIFE)
Each JS file isolates its own scope and communicates through the in-memory bus (`globalThis.j0.bus`). You can delete a module and the application will not crash.

```javascript
// file: my-module.js
(function () {
    'use strict';
    globalThis.j0.bus.on('system:ready', function () {
        globalThis.j0.bus.on('user:login', function (payload) {
            console.log('Logged in:', payload.email);
        });
    });
})();
```

---

## 2. Second Adoption: Bundler (Just-In-Time Compilation and Minification)

Splitting features into many small files solves the AI context problem, but creates a network latency problem in production (loading dozens of JS files over HTTP is slow).

You could concatenate and minify manually, but `j0` includes a **transparent Just-In-Time (JIT) Bundler written in PHP**.

### The Filesystem as SSOT (Single Source of Truth)
There are no hidden `manifest.json` files. If you put three `.js` files inside a folder, those three files are a module. In your HTML, you point to the whole folder:

```php
<?php
require_once __DIR__ . '/j0/quickstart/j0-bootstrap.php';
j0_boot(__DIR__);

// Generates script tags for the entire "0500-ui-patterns" folder
echo j0_asset_tags(__DIR__ . '/src/0500-ui-patterns', __DIR__ . '/dist/bundle', '/dist', 'js');
?>
```

1. If nothing changed, it returns `<script src="/dist/bundle.min.js">`.
2. If you changed a single line, it recompiles the folder, minifies it, and updates the tag automatically on reload.

**In Production:** Upload only the compiled HTML and the `.min.js` bundles. You do not upload the source module folders and you do not use PHP—serving static assets at zero cost.

---

## 3. Third Adoption: UI Patterns (Zero Manual JS, Repeated Code, or Spaghetti Patterns)

Instead of writing `document.getElementById('btn').addEventListener(...)` for every control, `j0` resolves 6 universal interface patterns in `O(1)` by delegating behavior to HTML attributes. These behavioral engines live in Zone `0500-ui-patterns`:

- **EMITTER (Trigger Actions):** Dispatches events to the bus without writing JS listeners.
  ```html
  <button data-j0-action="user:login" data-j0-payload='{"email":"a@b.com"}'>Sign In</button>
  ```
- **BINDER (Synchronize Controls):** Keeps multiple inputs synchronized automatically within a scope.
  ```html
  <div data-j0-bind-scope>
      <input type="range" data-j0-bind="opacity">
      <input type="number" data-j0-bind="opacity">
  </div>
  ```
- **CONDITIONER (Declarative Visibility):** Shows, hides, or enables elements by state.
  ```html
  <div data-j0-show-when="loading:active">Loading...</div>
  <!-- In JS, simply call: globalThis.j0.ui.setState('loading', 'active'); -->
  ```
- **SWITCHER (Navigate Views):** Tabs and panels without custom JS.
  ```html
  <div data-j0-tab-group="menu">
      <button data-j0-target="tab1">Tab 1</button>
  </div>
  <div data-j0-pane-group="menu">
      <div data-j0-panel="tab1">Content</div>
  </div>
  ```
- **DRAGGER (Pointer & Keyboard Manipulation):** Handles bounded dragging, snapping, and multi-thumb collision groups (`data-j0-draggable`, `data-j0-drag-group-container`).

### Quiescence & Ephemeral Components (Floater — Architectural Specification)
The 6th declarative UI pattern (**Floater**) specifies global quiescence (`ui:dismiss` on `globalThis.j0.bus` before opening modals or on outside clicks/Escape) and physical teleportation of dropdowns and popovers to `<body>` to escape `overflow: hidden` containers. Following YAGNI, `Floater` remains specified until required by an active consumer.

---

## 4. Fourth Adoption: Location-Independent Resolution (`S()` & `T()`)

`j0` functions share a common filesystem model so you can start in Library Mode (calling `j0_boot()` inside an existing folder layout) and scale into a structured application (`src/0010-home/`) without rewriting paths:

- **`S(id)` (Source):** Resolves public URLs for web assets, gatekeeper endpoints for protected tiers, and disk paths for PHP files.
- **`T(id, vars)` (Template / Text):** Resolves and renders HTML templates, inline SVGs, and JSON resources across the Union Mount (`j0_mount()` -> `project/src` -> `j0/src`) cached in `.j0route.php`.

Moving a file between directories never breaks links or requires manual path refactoring.

---

## 5. Fifth Adoption: The Virtual File System (VFS)

`j0` avoids monolithic, mutating global state stores (such as Redux) that bloat working memory. Data storage and persistence (Zone `0100-vfs`) are handled through the **VFS**.

The VFS decentralizes state across clean, composable layers:
- `vfs-core`: Synchronous Key-Value (KV) storage with optional Time-To-Live (TTL) expiration.
- `vfs-idb`: Native IndexedDB storage for structured documents and content-addressed binary blobs.
- `vfs-ssr`: Mirrors critical UI state (such as Dark/Light polarity) to the server via compact cookies (`j0_ui`), eliminating Flash of Unstyled Content (FOUC) on first byte.
- `vfs--remote` *(optional capability extension)*: Upgrades the VFS into a hybrid storage engine (`local` ↔ `hybrid-sync`) without changing a single line of UI code. When a user authenticates (`auth:login` / `auth:session-ready` on `globalThis.j0.bus`), dirty local documents sync automatically against a server-side API (`/api/vfs`) protected by an authentication and security layer (`j0auth`, session cookies, or `Bearer` tokens).

---

## 6. Projects Built with `j0` (The Library ↔ Framework Spectrum)

`j0` is validated across real projects spanning both ends of the adoption spectrum—from single-file kernel usage to multi-target production web apps:

- **[AI-DevTools (`ai0`)](https://github.com/sbustelo/AI-DevTools):** The modular developer tooling suite (`dumper`, `patcher`, `restore`, `purge`) that helped shape `j0 v2` as a standalone, zero-dependency library. Represents the lightweight entry point of the spectrum.

- **[ipax-scoring](https://github.com/sbustelo/ipax-scoring):** Uses the standalone `j0` JavaScript kernel (`01-j0kernel.js`).

- **[ipax-backdrop](https://github.com/sbustelo/ipax-backdrop):** Uses the standalone `j0` JavaScript kernel (`01-j0kernel.js`).

- **[FlowStyler](https://flowstyler.com/):** The flagship `j0 v1` application—a Unicode text-styling processor whose UI is resolved cleanly with a single editing area and a tabbed palette, backed by multilingual content, multiple build targets (web and browser extension), asset bundlers, and blue/green deployments. Sitting at the opposite pole from `ai0`, FlowStyler exposed where `j0 v1` introduced coupling and rigidity—prompting the inside-out redesign of `j0 v2`. **The core architectural challenge of `j0 v2` is making it possible to start as lightweight as `ai0` on Day 1 and scale all the way to `FlowStyler` without rewriting your architecture.**

- **[IPAX DesignLab](https://ipax.bustelo.com.ar/designlab/):** Built on `K1` (the direct precursor to `j0`); the interactive color-contrast lab where `K1` began evolving the declarative UI patterns—Zero-ID DOM delegation, bidirectional slider binding, dropdown teleportation, window management, and local persistence—that directly gave rise to `j0`.

- **[ChatCGT-74](https://www.bustelo.com.ar/apps/chatcgt/):** The oldest and least serious ancestor—an experimental proof-of-concept built on early `K1`, where scaling an app by adding independent module files to an in-memory EventBus (`K1.bus`) paired with an automatic PHP directory bundler/minifier (`k1-builder.php`) was first tested across 25+ JS modules and 10 CSS files with zero Node/NPM tooling. Dressed up as a satirical "Museum of Classic AI (1906–1974)," it puts Markov chains, Webster-Hopkins decision trees, Rosenblatt's Perceptron, ELIZA, and MYCIN to the test to expose how many modern customer-service chatbots still rely on rigid pre-1974 rules and stochastic text generation.
