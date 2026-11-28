# Customizing `ai0`

You can personalize folder icons, folder color labels, and typography in `ai0` without modifying the `ai0/` engine directory—so updates and symlinks stay clean.

---

## 1. Custom Folder Icons (Outside `ai0/`)

`ai0` looks for custom icon files (`.svg`, `.png`, `.webp`) in the following order:

1. `<PROJECT_ROOT>/_ai0src/assets/icons/` *(recommended for private/local icon packs — ignored by Git via `_*`)*
2. `<PROJECT_ROOT>/ai0src/assets/icons/` *(for icons shared with the repository)*
3. `<AI0_DIR>/ai0src/assets/icons/` *(built-in defaults)*

### How to use your own icons
- **Drop files into `_ai0src/assets/icons/`:** Create `_ai0src/assets/icons/` next to your `_ai0` symlink (or in your project root) and place any `.svg`, `.png`, or `.webp` files there. They appear automatically in the **Icon Picker** when you click a folder's icon in the Inspector.
- **Upload from the UI:** Inside the **Icon Picker**, click **Upload SVG/PNG** (up to 256 KB). `ai0` saves the file into `<PROJECT_ROOT>/_ai0src/assets/icons/` automatically.
- **Use any emoji:** Type or paste any emoji into the Icon Picker search bar and click **Use Icon**.

### Customizing Preset Emojis (`icons.json`)
The Icon Picker presets live in `ai0/ai0config/icons.json` under `"picker_emojis"` (the same file also defines report and context-menu icons). Because `j0_config_json()` merges JSON configs additively across the 4-level `[_]ai0config` cascade (`<PROJECT_ROOT>/_ai0config/` → `ai0config/` → `<AI0_DIR>/_ai0config/` → `ai0config/`), you can add your own presets without touching `ai0/` or losing the built-in defaults:

```json
{
    "picker_emojis": {
        "🎯": "target / goals",
        "🧾": "billing / invoices"
    }
}
```

Save that as `<PROJECT_ROOT>/_ai0config/icons.json` (local/private) or `<PROJECT_ROOT>/ai0config/icons.json` (shared in Git).

Folder icons and colors are stored per directory inside `.ai0root.json`.

---

## 2. Folder Color Labels

Right-click any folder (or use the row menu) and choose **Color** to assign one of 8 high-contrast folder labels.

- Colors are inherited visually by child rows in **Tree View** and child columns in **Miller Columns**, helping you spot hierarchy and boundaries at a glance.
- All color tokens (OKLCH triads for Light and Dark modes) and visual containment rules live in a single file if you want to tune the palette:
  `ai0/ui/modules/0026-fslabels--ui-widget/c0026-fslabels.css`

---

## 3. Typography & Environment Settings

Click the **gear icon** in the top navigation bar to open **Settings**:

- **Interface font & File listing font:** Pick from the built-in catalog or type any font name. `ai0` checks whether the font is installed locally on your system first, and falls back to loading it from Google Fonts if needed.
- **Base font size & Base weight:** Adjust the global UI scale (`12px`–`22px`) and base font weight (`200`–`600`).
- **Sync:** Typography preferences apply immediately from browser storage (`j0.vfs`) and sync automatically with `<PROJECT_ROOT>/_j0-temp/ai0-prefs.json` on disk.
- **Environment:** Rebuild compiled UI bundles (`Rebuild UI`) or toggle `DEBUG` mode to load individual unminified assets.

---

_ai0 & j0 (AI-DevTools) v5.0.11 • 2026-11-27 • Santiago Bustelo • [Apache 2.0 / CC BY 4.0 License](../LICENSE)_