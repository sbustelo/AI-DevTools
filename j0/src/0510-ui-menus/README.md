# `0510-ui-menus` — Safe-Triangle Submenus & Body-Ported Dropdowns

Declarative menu engine for **`j0 v2`** (UI OS Zone `0500`). Provides two zero-ID UI primitives:

1. **Hierarchical Submenus** protected by **Bruce Tognazzini's Safe-Triangle pattern (1986)**.
2. **Body-Ported Dropdowns** immune to ancestor `overflow: hidden` and `backdrop-filter` stacking contexts.

---

## 1. Files & Dependencies

- `c0010-j0-menus.css` — Structural styles, visibility hooks (`[data-j0-hidden="true"]`), and safe-zone layer styling.
- `j0010-j0-menus.js` — Self-contained IIFE controller exposing `globalThis.j0.ui.menus.closeAll()`.
- **Required Token SSOT:** `j0/src/0500-ipax/` (`c0000-ipax-tokens.css`, `c0010-ipax-dimensions.css`, `c0100-ipax-engine.css`).
- **Live Interactive Demo:** `j0/examples/05-tognazzini-menu/index.php` (side-by-side Naive Hover vs. Tognazzini Safe-Triangle + Light/Dark switch + anywhere-on-screen context menu).

---

## 2. Bruce Tognazzini's Safe-Triangle Pattern

### The Problem
When a user hovers a parent menu item (`Export ▸`) to open a submenu and then moves the pointer diagonally toward an item near the bottom of that submenu, the cursor naturally crosses neighboring rows in the parent menu. In naive hover implementations, crossing a sibling row immediately closes or switches the submenu.

### How `0510-ui-menus` Implements It
Instead of polling geometry math on every `mousemove` frame with intention timers, `0510-ui-menus` delegates hit-testing natively to the browser:

- When a submenu opens, `j0010-j0-menus.js` projects a full-viewport overlay (`.j0-submenu-safezone`) clipped via CSS `clip-path: polygon(...)` from the current pointer vertex to the two near corners of the open submenu.
- As long as the pointer travels diagonally inside that polygon, `.j0-submenu-safezone` intercepts hover above the parent menu so sibling items never steal focus.
- If the submenu flips horizontally near the right edge of the viewport, the polygon automatically re-orients to the submenu's right-side corners.
- **Depth Scope:** Intentionally scoped to **2 levels** (Parent Menu → Submenu) to keep state deterministic and free of nested-stack race conditions.

### Visual Debugging Token
`c0010-j0-menus.css` exposes `--submenu-hover-area` (default: `transparent`). Override it in any demo or debug stylesheet to inspect the live triangle geometry:

```css
:root {
    --submenu-hover-area: color-mix(in srgb, var(--ipax-1-accent-p) 28%, transparent);
}
```

---

## 3. Mandatory Z-Index Layer Contract (`0500-ipax`)

The safe-triangle mechanism **strictly depends** on the canonical stacking order defined in `j0/src/0500-ipax/c0010-ipax-dimensions.css`:

| Layer Token | Value | Role |
| :--- | :---: | :--- |
| `--ipax-z-modal` | `80000` | Modals and backdrops (below menus so dropdowns inside modals remain reachable) |
| `--ipax-z-menu` / `--ipax-z-dropdown` | `90000` | Parent context menus and dropdown lists |
| `--ipax-z-safezone` | `90010` | `.j0-submenu-safezone` triangle (MUST sit above the parent menu) |
| `--ipax-z-submenu` | `90020` | `.j0-submenu` panel (MUST sit above the safe-zone triangle) |

> **Rule:** Never place a parent menu on a layer higher than `--ipax-z-menu`. If a parent menu renders above `--ipax-z-safezone`, the triangle cannot intercept pointer events and the menu degrades into naive hover behavior.

---

## 4. Declarative HTML Contract

### Submenu Trigger & Panel
```html
<div role="menu">
    <button type="button"
            role="menuitem"
            class="j0-menu-item--has-submenu"
            data-j0-submenu-trigger="demo-export">
        <span>Export</span>
        <span class="j0-submenu-caret">▸</span>
    </button>
</div>

<div class="j0-submenu" role="menu" data-j0-submenu="demo-export" data-j0-hidden="true">
    <button type="button" role="menuitem">PNG Image</button>
    <button type="button" role="menuitem">SVG Vector</button>
</div>
```

### Body-Ported Dropdown
When opened, `.j0-dropdown-menu` is automatically teleported to `document.body` so ancestor `overflow: hidden` or `backdrop-filter` rules cannot clip or trap its `position: fixed` coordinates.

```html
<div class="j0-dropdown">
    <button type="button"
            class="j0-dropdown-trigger"
            data-j0-dropdown-trigger="root-picker"
            aria-haspopup="listbox"
            aria-expanded="false">
        <span class="j0-dropdown-label">Project Root</span>
        <span class="j0-dropdown-caret" aria-hidden="true">▾</span>
    </button>

    <div class="j0-dropdown-menu"
         role="listbox"
         data-j0-dropdown-menu="root-picker"
         data-j0-hidden="true">
        <button type="button"
                role="option"
                class="j0-dropdown-option"
                style="--j0-dd-depth: 0;"
                data-j0-dropdown-option
                data-j0-value="/path/to/root"
                data-j0-selected="true">
            <span class="j0-dropdown-label">Project Root</span>
        </button>
    </div>
</div>
```

---

## 5. Programmatic API

- `globalThis.j0.ui.menus.closeAll()` — Closes any open submenu, hides the active safe-zone polygon, and closes any open dropdown.