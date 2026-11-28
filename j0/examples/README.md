# j0 Progressive Examples (Day 1 to Full Self-Contained App)

Each directory in `j0/examples/` is portable and demonstrates one progressive step in adopting `j0`—from a single standalone PHP script up to a self-contained UI application that compiles its own `dist/` bundles and static `index.html` snapshot.

## Progression

1. **[`01-standalone-php/`](01-standalone-php/hello-j0.php)** — **Layer 0 Locator & Core Scanner:** Drop `j0_boot()` into a single PHP script with zero hardcoded paths or Composer. Locates `j0/api.php` upward and caches `.j0route.php` bound to its current directory (`__j0_origin__`), healing automatically if moved.
2. **[`02-project-bootstrap/`](02-project-bootstrap/index.php)** — **Centralized Bootstrap, Bundler & Static HTML Cache:** Centralizes `j0_boot()` in `php/bootstrap.php`, compiles `j0010-kernel.js` + `0100-vfs` into `dist/app-runtime.min.js`, and persists a static `index.html` snapshot (`?debug=1` bypasses cache).
3. **[`03-locator-and-templates/`](03-locator-and-templates/demo-locate.php)** — **Union Mount Locator (`S` / `T`):** Resolves URLs (`S()`) and renders HTML templates (`T()`) without hardcoded filesystem paths.
4. **[`04-vfs-and-ui-patterns/`](04-vfs-and-ui-patterns/index.php)** — **Isomorphic VFS + Declarative UI Engines:** Combines SSR theme cookies (`j0_vfs_ui_attr`) with client-side Emitter, Binder, Conditioner, and Switcher patterns (`dist/j0-runtime.min.{css,js}`).
5. **[`05-tognazzini-menu/`](05-tognazzini-menu/index.php)** — **Full Self-Contained UI Pattern (Bruce Tognazzini's Safe-Triangle Submenu):** Combines IPAX tokens, `0510-ui-menus` diagonal safe-zone polygons, self-contained `dist/demo-menu.min.{css,js}` bundles, and automatic `index.html` generation.

## Portability Guarantee

- **Move with `j0/` (sibling or ancestor):** `.j0route.php` detects directory moves automatically, binds to the nearest `j0/`, updates `dist/` when sources change, and serves `dist/*.css` safely even when `j0/` sits outside `DOCUMENT_ROOT`.
- **Copy standalone (without `j0/` or without PHP):** Examples `02`, `04`, and `05` run out of the box from their pre-built `dist/` bundles and static `index.html`—ready to drop onto any static host or shared server.

---

_ai0 & j0 (AI-DevTools) v5.0.11 • 2026-11-27 • Santiago Bustelo • [Apache 2.0 / CC BY 4.0 License](../../LICENSE)_