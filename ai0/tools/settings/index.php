<?php
// PATH: ai0/tools/settings/index.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    define('J0_EXEC', true);
}

require_once dirname(__DIR__, 2) . '/core/ai0-context.php';
require_once dirname(__DIR__, 2) . '/ui/ui-layout.php';

$ctx = ai0_resolve_context(__DIR__);
$state = ai0_get_state($ctx);
$flash = null;
$flashType = 'j0ui-alert-success';

$bs = chr(92);
$defaultRoot = rtrim(str_replace($bs, '/', (string) ($ctx['project_root'] ?? $ctx['root'] ?? __DIR__)), '/');
if ($defaultRoot === '') {
    $defaultRoot = '/';
}
$ctx['active_root'] = $defaultRoot;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'toggle_debug') {
        $state['debug'] = !((bool) ($state['debug'] ?? false));
        ai0_save_state($ctx, $state);
        $stateLabel = $state['debug']
            ? L('ENABLED (individual unminified asset tags)', 'ACTIVADO (archivos individuales sin minificar)')
            : L('DISABLED (compiled .min bundle)', 'DESACTIVADO (bundle .min compilado)');
        $flash = L('DEBUG mode {0}.', 'Modo DEBUG {0}.', [$stateLabel]);
    } elseif ($action === 'rebuild_ui') {
        j0_use('bundler');
        $cssSources = ai0_ui_css_sources();
        $jsSources = ai0_ui_js_sources();
        $buildPaths = ai0_ui_build_paths($ctx);
        $outBase = $buildPaths['out_base'];
        $sigDir = dirname($outBase);
        $sigBase = basename($outBase);
        @unlink($sigDir . '/.' . $sigBase . '.css.sig');
        @unlink($sigDir . '/.' . $sigBase . '.js.sig');
        $resCss = j0_build_bundle(['sources' => $cssSources, 'out_file' => $outBase . '.css', 'ext' => 'css']);
        $resJs = j0_build_bundle(['sources' => $jsSources, 'out_file' => $outBase . '.js', 'ext' => 'js']);
        $flash = L(
            'UI rebuilt in _j0-temp/ai0-ui (CSS: {0}, JS: {1}).',
            'UI recompilada en _j0-temp/ai0-ui (CSS: {0}, JS: {1}).',
            [$resCss['reason'], $resJs['reason']]
        );
    }
}

$debug = (bool) ($state['debug'] ?? false);

ai0_render_head(L('ai0 // Settings', 'ai0 // Configuración'), $ctx, 'settings', $debug);
?>

<?php if ($flash !== null): ?>
    <div class="j0ui-alert-inline <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="j0ui-card" data-ai0-typography-settings>
    <h2><?= htmlspecialchars(L('Appearance & Typography', 'Apariencia y Tipografía'), ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="j0ui-muted"><?= htmlspecialchars(L('Customize interface font, file listing font, base size, and base weight. Synced between browser VFS and disk.', 'Personalizá la fuente de interfaz, fuente de listados, tamaño base y peso base. Sincronizado entre VFS del navegador y disco.'), ENT_QUOTES, 'UTF-8') ?></p>

    <div class="j0ui-settings-grid">
        <div class="j0ui-subbox">
            <label class="j0ui-settings-label"><?= htmlspecialchars(L('Interface font (--ipax-font-ui)', 'Fuente de interfaz (--ipax-font-ui)'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="j0ui-font-combo" data-ai0-font-combo="ui">
                <input type="text" class="j0ui-input-text" autocomplete="off" spellcheck="false" placeholder="<?= htmlspecialchars(L('System UI, Inter, Public Sans...', 'System UI, Inter, Public Sans...'), ENT_QUOTES, 'UTF-8') ?>" data-ai0-font-input>
                <ul class="j0ui-font-menu" role="listbox" data-j0-hidden="true" data-ai0-font-menu></ul>
            </div>
            <span class="j0ui-muted" data-ai0-font-status="ui"></span>
        </div>

        <div class="j0ui-subbox">
            <label class="j0ui-settings-label"><?= htmlspecialchars(L('File listing font (--ai0-font-listing)', 'Fuente de listados (--ai0-font-listing)'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="j0ui-font-combo" data-ai0-font-combo="listing">
                <input type="text" class="j0ui-input-text" autocomplete="off" spellcheck="false" placeholder="<?= htmlspecialchars(L('Default monospace, JetBrains Mono, Inter...', 'Monospace por defecto, JetBrains Mono, Inter...'), ENT_QUOTES, 'UTF-8') ?>" data-ai0-font-input>
                <ul class="j0ui-font-menu" role="listbox" data-j0-hidden="true" data-ai0-font-menu></ul>
            </div>
            <span class="j0ui-muted" data-ai0-font-status="listing"></span>
        </div>

        <div class="j0ui-subbox">
            <label class="j0ui-settings-label">
                <?= htmlspecialchars(L('Base font size (--ipax-0-root-size)', 'Tamaño base (--ipax-0-root-size)'), ENT_QUOTES, 'UTF-8') ?>
                <span class="j0ui-badge" data-ai0-size-readout>16px</span>
            </label>
            <input type="range" min="12" max="22" step="1" value="16" class="j0ui-range" data-ai0-root-size>
        </div>

        <div class="j0ui-subbox">
            <label class="j0ui-settings-label">
                <?= htmlspecialchars(L('Base font weight (--ipax-0-weight-normal)', 'Peso base (--ipax-0-weight-normal)'), ENT_QUOTES, 'UTF-8') ?>
                <span class="j0ui-badge" data-ai0-weight-readout>400</span>
            </label>
            <input type="range" min="200" max="600" step="50" value="400" class="j0ui-range" data-ai0-weight-normal>
        </div>
    </div>

    <div class="j0ui-actions">
        <button type="button" class="j0ui-btn" data-ai0-action="typography:reset"><?= htmlspecialchars(L('Reset Typography Defaults', 'Restablecer Tipografía'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
</section>

<section class="j0ui-card">
    <h2><?= htmlspecialchars(L('Environment', 'Entorno'), ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="j0ui-muted"><?= htmlspecialchars(L('Runtime state persisted in', 'Estado de ejecución persistido en'), ENT_QUOTES, 'UTF-8') ?> <code class="j0ui-mono"><?= htmlspecialchars($ctx['temp_dir'], ENT_QUOTES, 'UTF-8') ?>/ai0-state.json</code>.</p>
    <div class="j0ui-actions">
        <form method="post" class="j0ui-inline-form">
            <input type="hidden" name="action" value="rebuild_ui">
            <button type="submit" class="j0ui-btn"><?= htmlspecialchars(L('Rebuild UI', 'Recompilar UI'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
        <form method="post" class="j0ui-inline-form">
            <input type="hidden" name="action" value="toggle_debug">
            <button type="submit" class="j0ui-btn <?= $debug ? 'j0ui-btn--danger' : '' ?>">
                <?= htmlspecialchars($debug ? L('Disable DEBUG', 'Desactivar DEBUG') : L('Enable DEBUG', 'Activar DEBUG'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </form>
        <span class="j0ui-mono"><?= htmlspecialchars(L('Engine', 'Motor'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($ctx['base_dir'], ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars(L('Scope', 'Alcance'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($ctx['down_rel'] === '' ? '/' : $ctx['down_rel'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
</section>

<?php
ai0_render_foot($ctx, $debug);