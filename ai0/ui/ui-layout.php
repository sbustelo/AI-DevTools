<?php
// PATH: ai0/ui/ui-layout.php
declare(strict_types=1);

if (function_exists('j0_use')) {
    j0_use('assets', 'vfs', 'i18n');
}

/**
 * @return array<int, string>
 */
function ai0_ui_modules_dir(): string
{
    $phys = __DIR__ . '/modules';
    return function_exists('j0_logical_path') ? j0_logical_path($phys) : $phys;
}

function ai0_ui_js_sources(): array
{
    $sources = [
        j0_asset_path('j0010-kernel.js'),
        j0_asset_path('j0010-vfs-core.js'),
        j0_asset_path('j0010-i18n.js'),
        j0_asset_path('j0501-emitter.js'),
        j0_asset_path('j0502-switcher.js'),
        j0_asset_path('j0506-floater.js'),
        j0_asset_path('j0010-j0-menus.js'),
        j0_asset_path('j0010-j0-flip.js'),
        j0_asset_path('j0020-j0-window-sheet.js'),
        j0_asset_path('j0010-j0-dialogs.js'),
        j0_asset_path('j0010-j0-segmented.js'),
        ai0_ui_modules_dir(),
    ];
    return array_values(array_filter($sources, static fn(mixed $p): bool => is_string($p) && $p !== ''));
}

/**
 * @return array<int, string>
 */
function ai0_ui_css_sources(): array
{
    $sources = [
        j0_asset_dir('ipax'),
        j0_asset_path('c0010-j0-menus.css'),
        j0_asset_path('c0010-j0-windows.css'),
        j0_asset_path('c0020-j0-window-sheet.css'),
        j0_asset_path('c0010-j0-dialogs.css'),
        j0_asset_path('c0010-j0-segmented.css'),
        ai0_ui_modules_dir(),
    ];
    return array_values(array_filter($sources, static fn(mixed $p): bool => is_string($p) && $p !== ''));
}

/**
 * Returns a canonical root-anchored web URL inside the mounted ai0 directory (zero ../ traversal).
 */
function ai0_url(array $ctx, string $subPath = ''): string
{
    $base = (string) ($ctx['ai0_web_root'] ?? '');
    if ($base === '') {
        $up = (string) ($ctx['up_rel'] ?? '');
        $baseDir = (string) ($ctx['base_dir'] ?? 'ai0');
        $base = $up . $baseDir . '/';
    }
    $cleanSub = ltrim($subPath, '/');
    return rtrim($base, '/') . '/' . $cleanSub;
}

function ai0_ui_web_base(array $ctx): string
{
    return ai0_url($ctx, 'ui/');
}

/**
 * Resolves writable build output base and matching relative web URL inside <temp_dir>/ai0-ui/.
 *
 * @return array{out_base: string, web_base: string}
 */
function ai0_ui_build_paths(array $ctx): array
{
    $bs = chr(92);
    $root = rtrim(str_replace($bs, '/', (string) ($ctx['root'] ?? '')), '/');
    $tempDir = rtrim(str_replace($bs, '/', (string) ($ctx['temp_dir'] ?? ($root . '/_j0-temp'))), '/');
    $outDir = $tempDir . '/ai0-ui';

    if (!is_dir($outDir)) {
        @mkdir($outDir, 0775, true);
    }

    $mappedWeb = function_exists('j0_path_to_web_url') ? j0_path_to_web_url($outDir) : null;
    if ($mappedWeb !== null && $mappedWeb !== '') {
        $webBase = rtrim($mappedWeb, '/') . '/';
    } else {
        $up = (string) ($ctx['up_rel'] ?? '');
        $tempRel = ($root !== '' && str_starts_with($tempDir, $root . '/'))
            ? substr($tempDir, strlen($root) + 1)
            : '_j0-temp';
        $webBase = $up . $tempRel . '/ai0-ui/';
    }

    return [
        'out_base' => $outDir . '/modules',
        'web_base' => $webBase,
    ];
}

/**
 * Unified icon renderer (SVG inline, asset filename via j0 S(), or emoji/text).
 *
 * @param array<string, mixed>|string $spec
 */
function ai0_render_icon(array|string $spec, string $extraClass = ''): string
{
    $rawIcon = is_array($spec) ? (string) ($spec['icon'] ?? '') : $spec;
    $specClass = is_array($spec) ? trim((string) ($spec['class'] ?? '')) : '';
    $cls = trim('j0ui-icon ' . $specClass . ' ' . $extraClass);
    $safeCls = htmlspecialchars($cls, ENT_QUOTES, 'UTF-8');

    if ($rawIcon === '') {
        return '';
    }

    $trimmed = ltrim($rawIcon);
    if (str_starts_with(strtolower($trimmed), '<svg')) {
        return '<span class="' . $safeCls . '" aria-hidden="true">' . $trimmed . '</span>';
    }

    if (preg_match('/^[a-zA-Z0-9._\/-]+\.(svg|png|webp|jpg|jpeg|gif)$/i', $trimmed) === 1) {
        $src = function_exists('S') ? S($trimmed) : $trimmed;
        return '<img class="' . $safeCls . '" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="" aria-hidden="true">';
    }

    return '<span class="' . $safeCls . '" aria-hidden="true">' . htmlspecialchars($rawIcon, ENT_QUOTES, 'UTF-8') . '</span>';
}

function ai0_render_head(string $title, array $ctx, string $activeTool = '', bool $debug = false): void
{
    $cssSources = ai0_ui_css_sources();
    $buildPaths = ai0_ui_build_paths($ctx);
    $cssTags = j0_asset_tags($cssSources, $buildPaths['out_base'], $buildPaths['web_base'], 'css', $debug);

    $webBase = ai0_ui_web_base($ctx);
    $bs = chr(92);
    $projectRoot = rtrim(str_replace($bs, '/', (string) ($ctx['project_root'] ?? $ctx['active_root'] ?? $ctx['root'] ?? '')), '/');
    if ($projectRoot === '') {
        $projectRoot = '/';
    }

    $filesUrl = ai0_url($ctx, 'tools/files/index.php');
    $patcherUrl = ai0_url($ctx, 'tools/patcher/index.php');
    $i18nBatchUrl = ai0_url($ctx, 'tools/i18n-batch/index.php');
    $settingsUrl = ai0_url($ctx, 'tools/settings/index.php');
    $prefsApiUrl = ai0_url($ctx, 'tools/settings/api.php');
    $projectFilesUrl = $filesUrl . '?browse=' . rawurlencode($projectRoot);

    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeAbsRoot = htmlspecialchars($projectRoot, ENT_QUOTES, 'UTF-8');
    $projectBaseName = basename($projectRoot) ?: '/';
    $safeProjectName = htmlspecialchars($projectBaseName, ENT_QUOTES, 'UTF-8');
    $projectRootIcon = function_exists('ai0_resolve_icon')
        ? ai0_resolve_icon($projectRoot, ['root' => $projectRoot, 'ai0_dir' => (string) ($ctx['ai0_dir'] ?? dirname(__DIR__))], 'dir', 'j0ui-tree-icon')
        : ['html' => '<span aria-hidden="true">📁</span>'];
    $initialSearch = trim((string) ($_GET['q'] ?? ''));
    $safeInitialSearch = htmlspecialchars($initialSearch, ENT_QUOTES, 'UTF-8');

    $rawTheme = j0_vfs_ui_attr('theme', 'dark');
    $theme = $rawTheme === 'light' ? 'light' : 'dark';
    $i18nCfg = (function_exists('j0_config_json') && isset($ctx['domains']) && is_array($ctx['domains']))
        ? j0_config_json('i18n', $ctx['domains'])
        : [];
    $langs = (isset($i18nCfg['langs']) && is_array($i18nCfg['langs']) && $i18nCfg['langs'] !== [])
        ? $i18nCfg['langs']
        : ['en', 'es'];
    j0_i18n_config(['langs' => $langs]);
    $lang = j0_get_userlang();
    $safeLang = htmlspecialchars($lang, ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<?php $isViewportApp = in_array($activeTool, ['files', 'patcher'], true); ?>
<html lang="<?= $safeLang ?>" data-ipax-theme="<?= $theme ?>" data-ai0-prefs-api="<?= htmlspecialchars($prefsApiUrl, ENT_QUOTES, 'UTF-8') ?>"<?= $isViewportApp ? ' data-j0-viewport="app"' : '' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,400,0,0">
    <title="<?= $safeTitle ?></title>
    <?= $cssTags . "\n" ?>
</head>
<body>
<main class="j0ui-shell">
    <header class="j0ui-topbar">
        <?php $brandIcon = function_exists('ai0_resolve_icon') ? ai0_resolve_icon('', $ctx, 'brand', 'j0ui-brand-logo') : ['html' => '']; ?>
        <div class="j0ui-brand">
            <?= $brandIcon['html'] ?>
            <h1 class="j0ui-brand-title">ai0</h1>
            <a class="j0ui-root-chip"
               href="<?= htmlspecialchars($projectFilesUrl, ENT_QUOTES, 'UTF-8') ?>"
               title="<?= htmlspecialchars(L('Active Project Root: {0}', 'Project Root activo: {0}', [$projectRoot]), ENT_QUOTES, 'UTF-8') ?>"
               data-j0-project-root-chip="<?= $safeAbsRoot ?>">
                <?= $projectRootIcon['html'] ?>
                <span class="j0ui-root-chip-name"><?= $safeProjectName ?></span>
            </a>
            <form class="j0ui-omnibar"
                  role="search"
                  data-j0-omnibar-form
                  data-j0-files-url="<?= htmlspecialchars($filesUrl, ENT_QUOTES, 'UTF-8') ?>"
                  data-j0-patcher-url="<?= htmlspecialchars($patcherUrl, ENT_QUOTES, 'UTF-8') ?>"
                  data-j0-active-tool="<?= htmlspecialchars($activeTool, ENT_QUOTES, 'UTF-8') ?>">
                <input type="text"
                       class="j0ui-input-text j0ui-omnibar-input"
                       value="<?= $safeInitialSearch ?>"
                       autocomplete="off"
                       spellcheck="false"
                       placeholder="<?= htmlspecialchars(L('Path, ai0 commands, or terms to search for...', 'Ruta, comandos ai0 o términos a buscar...'), ENT_QUOTES, 'UTF-8') ?>"
                       aria-label="<?= htmlspecialchars(L('Omnibar: path, ai0 commands, or search', 'Omnibar: ruta, comandos ai0 o búsqueda'), ENT_QUOTES, 'UTF-8') ?>"
                       data-j0-omnibar-input>
                <span class="j0ui-badge j0ui-badge-accent j0ui-omnibar-badge" data-j0-omnibar-badge data-j0-hidden="true"></span>
                <button type="button"
                        class="j0ui-omnibar-clear"
                        title="<?= htmlspecialchars(L('Clear (Esc)', 'Limpiar (Esc)'), ENT_QUOTES, 'UTF-8') ?>"
                        aria-label="<?= htmlspecialchars(L('Clear omnibar', 'Limpiar omnibar'), ENT_QUOTES, 'UTF-8') ?>"
                        data-j0-omnibar-clear
                        data-j0-hidden="<?= $initialSearch === '' ? 'true' : 'false' ?>">×</button>
            </form>
            <?php if ($debug): ?>
                <span class="j0ui-badge j0ui-badge-warning">DEBUG</span>
            <?php endif; ?>
        </div>
        <div class="j0ui-topbar-end">
            <nav class="j0ui-nav">
                <a class="j0ui-btn <?= $activeTool === 'files' ? 'j0ui-btn--active' : '' ?>" href="<?= htmlspecialchars($filesUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(L('Files', 'Archivos'), ENT_QUOTES, 'UTF-8') ?></a>
                <a class="j0ui-btn <?= $activeTool === 'patcher' ? 'j0ui-btn--active' : '' ?>" href="<?= htmlspecialchars($patcherUrl, ENT_QUOTES, 'UTF-8') ?>">patcher</a>
                <a class="j0ui-btn <?= $activeTool === 'i18n-batch' ? 'j0ui-btn--active' : '' ?>" href="<?= htmlspecialchars($i18nBatchUrl, ENT_QUOTES, 'UTF-8') ?>">i18n</a>
                <a class="j0ui-btn j0ui-btn--icon <?= $activeTool === 'settings' ? 'j0ui-btn--active' : '' ?>"
                   href="<?= htmlspecialchars($settingsUrl, ENT_QUOTES, 'UTF-8') ?>"
                   title="<?= htmlspecialchars(L('Settings & Environment', 'Configuración y Entorno'), ENT_QUOTES, 'UTF-8') ?>"
                   aria-label="<?= htmlspecialchars(L('Settings', 'Configuración'), ENT_QUOTES, 'UTF-8') ?>"><span class="material-symbols-outlined j0ui-gear-icon" aria-hidden="true">settings</span></a>
            </nav>
            <j0-theme-switch>
                <div class="theme-pill" role="switch" aria-checked="<?= $theme === 'dark' ? 'true' : 'false' ?>" aria-label="<?= htmlspecialchars(L('Toggle color theme', 'Alternar tema de color'), ENT_QUOTES, 'UTF-8') ?>" tabindex="0" data-j0-action="ui:toggle-theme">
                    <span class="pill-icon pill-sun" aria-hidden="true">☀️</span>
                    <span class="pill-indicator"></span>
                    <span class="pill-icon pill-moon" aria-hidden="true">🌙</span>
                </div>
            </j0-theme-switch>
            <select class="j0ui-btn" aria-label="<?= htmlspecialchars(L('Language', 'Idioma'), ENT_QUOTES, 'UTF-8') ?>" data-j0-lang-select>
                <?php foreach ($langs as $code): ?>
                    <?php $safeCode = htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8'); ?>
                    <option value="<?= $safeCode ?>" <?= ((string) $code === $lang) ? 'selected' : '' ?>><?= strtoupper($safeCode) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </header>
    <?php
}

/**
 * Renders the unified floating context menu shared by files and patcher.
 *
 * @param array<string, mixed> $iconsCfg
 * @param array<string, mixed> $opts
 */
function ai0_render_context_menu(array $iconsCfg, array $opts = []): void
{
    $menuIcons = is_array($iconsCfg['context_menu'] ?? null) ? $iconsCfg['context_menu'] : [];
    $rootField = htmlspecialchars((string) ($opts['root_field'] ?? 'browse_root'), ENT_QUOTES, 'UTF-8');
    $canReveal = !empty($opts['can_reveal']);

    $itemIcon = static function (string $key, string $fallbackIcon, string $fallbackClass) use ($menuIcons): array {
        $entry = is_array($menuIcons[$key] ?? null) ? $menuIcons[$key] : [];
        $ico = isset($entry['icon']) && is_string($entry['icon']) && $entry['icon'] !== '' ? $entry['icon'] : $fallbackIcon;
        $cls = isset($entry['class']) && is_string($entry['class']) ? trim($entry['class']) : $fallbackClass;
        return ['icon' => $ico, 'class' => $cls];
    };

    $icoRename = $itemIcon('rename', '✏️', '');
    $icoToggle = $itemIcon('toggle_disable', '🫥', '');
    $icoReveal = $itemIcon('reveal', '🔍', '');
    $icoSnap = $itemIcon('snapshot', '📦', '');
    $icoDiscard = $itemIcon('discard', '🗑️', '');
    ?>
    <div class="j0ui-context-menu"
         role="menu"
         data-j0-context-menu
         data-j0-root-field="<?= $rootField ?>"
         data-j0-hidden="true"
         data-j0-prompt-rename="<?= htmlspecialchars(L('New name:', 'Nuevo nombre:'), ENT_QUOTES, 'UTF-8') ?>"
         data-j0-confirm-discard="<?= htmlspecialchars(L('Mark item as discarded (_🗑️)?', '¿Marcar elemento como descartado (_🗑️)?'), ENT_QUOTES, 'UTF-8') ?>">
        <button type="button" role="menuitem" class="j0ui-menu-item" data-j0-ctx-action="rename">
            <span class="j0ui-menu-icon <?= htmlspecialchars($icoRename['class'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"><?= $icoRename['icon'] ?></span>
            <span><?= htmlspecialchars(L('Rename', 'Renombrar'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button type="button" role="menuitem" class="j0ui-menu-item" data-j0-ctx-action="toggle_disable">
            <span class="j0ui-menu-icon <?= htmlspecialchars($icoToggle['class'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"><?= $icoToggle['icon'] ?></span>
            <span><?= htmlspecialchars(L('Toggle _', 'Alternar _'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <?php if ($canReveal): ?>
            <button type="button" role="menuitem" class="j0ui-menu-item" data-j0-ctx-action="reveal">
                <span class="j0ui-menu-icon <?= htmlspecialchars($icoReveal['class'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"><?= $icoReveal['icon'] ?></span>
                <span><?= htmlspecialchars(L('Reveal in Finder', 'Mostrar en Finder'), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
        <?php endif; ?>
        <button type="button" role="menuitem" class="j0ui-menu-item" data-j0-ctx-action="snapshot">
            <span class="j0ui-menu-icon <?= htmlspecialchars($icoSnap['class'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"><?= $icoSnap['icon'] ?></span>
            <span><?= htmlspecialchars(L('Snapshot', 'Snapshot'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <div class="j0ui-menu-divider" role="separator"></div>
        <button type="button" role="menuitem" class="j0ui-menu-item j0ui-menu-item--danger" data-j0-ctx-action="discard">
            <span class="j0ui-menu-icon <?= htmlspecialchars($icoDiscard['class'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"><?= $icoDiscard['icon'] ?></span>
            <span><?= htmlspecialchars(L('Discard', 'Descartar'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
    </div>
    <?php
}

function ai0_render_foot(array $ctx, bool $debug = false): void
{
    $jsSources = ai0_ui_js_sources();
    $buildPaths = ai0_ui_build_paths($ctx);
    $jsTags = j0_asset_tags($jsSources, $buildPaths['out_base'], $buildPaths['web_base'], 'js', $debug);
    ?>
</main>
<?= $jsTags . "\n" ?>
</body>
</html>
    <?php
}