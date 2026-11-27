<?php
// PATH: ai0/tools/index.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/ai0-context.php';
require_once dirname(__DIR__) . '/ui/ui-layout.php';

$ctx = ai0_resolve_context(__DIR__);
$state = ai0_get_state($ctx);
$flash = null;
$flashType = 'j0ui-alert-success';

$bs = chr(92);
$defaultRoot = rtrim(str_replace($bs, '/', (string) ($ctx['root'] ?? __DIR__)), '/');
if ($defaultRoot === '') {
    $defaultRoot = '/';
}

$iconsCfg = j0_config_json('icons', $ctx['domains']);
$requestedRoot = (string) ($_POST['browse_root'] ?? $_GET['root'] ?? '');
$cleanReqRoot = ai0_parse_input_path($requestedRoot, $defaultRoot, $iconsCfg);
if ($cleanReqRoot === '') {
    $cleanReqRoot = '/';
}

$activeRoot = ($requestedRoot !== '' && !str_contains($cleanReqRoot, '..') && is_dir($cleanReqRoot))
    ? $cleanReqRoot
    : $defaultRoot;
$ctx['active_root'] = $activeRoot;

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
        $modulesDir = dirname(__DIR__) . '/ui/modules';
        $jsSources = ai0_ui_js_sources();
        $buildPaths = ai0_ui_build_paths($ctx);
        $outBase = $buildPaths['out_base'];
        $resCss = j0_build_bundle(['src_dir' => $modulesDir, 'out_file' => $outBase . '.css', 'ext' => 'css']);
        $resJs = j0_build_bundle(['sources' => $jsSources, 'out_file' => $outBase . '.js', 'ext' => 'js']);
        $flash = L(
            'UI rebuilt in _j0-temp/ai0-ui (CSS: {0}, JS: {1}).',
            'UI recompilada en _j0-temp/ai0-ui (CSS: {0}, JS: {1}).',
            [$resCss['reason'], $resJs['reason']]
        );
    }
}

$debug = (bool) ($state['debug'] ?? false);
$webBase = ai0_ui_web_base($ctx);
$rootQuery = ($activeRoot !== $defaultRoot) ? ('?root=' . rawurlencode($activeRoot)) : '';
$safeActiveRoot = htmlspecialchars($activeRoot, ENT_QUOTES, 'UTF-8');

$filesBaseUrl = $webBase . '../tools/files/index.php';
$sep = str_contains($filesBaseUrl, '?') ? '&' : '?';
$rootAmp = ($activeRoot !== $defaultRoot) ? ('&root=' . rawurlencode($activeRoot)) : '';
$filesUrl = htmlspecialchars($filesBaseUrl . ($activeRoot !== $defaultRoot ? ('?root=' . rawurlencode($activeRoot)) : ''), ENT_QUOTES, 'UTF-8');
$dumpTabUrl = htmlspecialchars($filesBaseUrl . $sep . 'tab=dump' . $rootAmp, ENT_QUOTES, 'UTF-8');
$purgeTabUrl = htmlspecialchars($filesBaseUrl . $sep . 'tab=purge' . $rootAmp, ENT_QUOTES, 'UTF-8');
$restoreTabUrl = htmlspecialchars($filesBaseUrl . $sep . 'tab=restore' . $rootAmp, ENT_QUOTES, 'UTF-8');
$patcherUrl = htmlspecialchars($webBase . '../tools/patcher/index.php' . $rootQuery, ENT_QUOTES, 'UTF-8');
$i18nBatchUrl = htmlspecialchars($webBase . '../tools/i18n-batch/index.php' . $rootQuery, ENT_QUOTES, 'UTF-8');

ai0_render_head(L('ai0 // DevTools Control Panel', 'ai0 // Panel de Control DevTools'), $ctx, 'devtools', $debug);
?>

<?php if ($flash !== null): ?>
    <div class="j0ui-alert-inline <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="j0ui-card">
    <h2><?= htmlspecialchars(L('Files — Explorer, Dump, Purge & Restore', 'Files — Explorador, Dump, Purge y Restore'), ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="j0ui-muted"><?= htmlspecialchars(L('Unified workspace reading from the active directory with Dump, Purge, and Restore tabs, shallow directory navigation, and context actions.', 'Espacio de trabajo unificado sobre el directorio activo con pestañas Dump, Purge y Restore, exploración plana fuera de raíz y acciones contextuales.'), ENT_QUOTES, 'UTF-8') ?></p>
    <div class="j0ui-grid-3">
        <div class="j0ui-subbox">
            <strong>Dump</strong>
            <p class="j0ui-muted"><?= htmlspecialchars(L('Select files and category filters to generate a structured project dump.', 'Seleccioná archivos y categorías para generar un dump estructurado.'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="j0ui-actions">
                <a class="j0ui-btn j0ui-btn--primary" href="<?= $dumpTabUrl ?>"><?= htmlspecialchars(L('Open Dump', 'Abrir Dump'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>
        <div class="j0ui-subbox">
            <strong>Purge</strong>
            <p class="j0ui-muted"><?= htmlspecialchars(L('Reclaim discarded (_🗑️, __), backup (_📦), and OS junk items while protecting (_).', 'Limpia descartados (_🗑️, __), respaldos (_📦) y basura del sistema protegiendo (_).'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="j0ui-actions">
                <a class="j0ui-btn" href="<?= $purgeTabUrl ?>"><?= htmlspecialchars(L('Open Purge', 'Abrir Purge'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>
        <div class="j0ui-subbox">
            <strong>Restore</strong>
            <p class="j0ui-muted"><?= htmlspecialchars(L('Inspect timestamped _📦 batches and restore individual files or full batches.', 'Inspeccioná lotes _📦 con marca de tiempo y restaurá archivos o lotes completos.'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="j0ui-actions">
                <a class="j0ui-btn" href="<?= $restoreTabUrl ?>"><?= htmlspecialchars(L('Open Restore', 'Abrir Restore'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
        </div>
    </div>
</section>

<section class="j0ui-grid-2">
    <article class="j0ui-card">
        <h2><?= htmlspecialchars(L('patcher — Atomic Code Applier', 'patcher — Aplicador Atómico de Código'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="j0ui-muted"><?= htmlspecialchars(L('Two-column Input/Output engine for CREATE, PATCH, MOVE, DISABLE (_), DISCARD (_🗑️), DUMP, and TREE with _📦 backups and php -l.', 'Motor de dos columnas Entrada/Salida para CREATE, PATCH, MOVE, DISABLE (_), DISCARD (_🗑️), DUMP y TREE con respaldos _📦 y php -l.'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="j0ui-actions">
            <a class="j0ui-btn j0ui-btn--primary" href="<?= $patcherUrl ?>"><?= htmlspecialchars(L('Open patcher', 'Abrir patcher'), ENT_QUOTES, 'UTF-8') ?></a>
            <span class="j0ui-badge">Stub: ai0patch.php</span>
        </div>
    </article>

    <article class="j0ui-card">
        <h2><?= htmlspecialchars(L('i18n-batch — Translation Delta', 'i18n-batch — Delta de Traducción'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="j0ui-muted"><?= htmlspecialchars(L('Compares unit i18n JSONs or M_dump() catalogs by source_hash, validates placeholders, and emits minimal AI delta prompts.', 'Compara diccionarios unitarios i18n o catálogos M_dump() por source_hash, valida placeholders y genera prompts delta mínimos.'), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="j0ui-actions">
            <a class="j0ui-btn j0ui-btn--primary" href="<?= $i18nBatchUrl ?>"><?= htmlspecialchars(L('Open i18n-batch', 'Abrir i18n-batch'), ENT_QUOTES, 'UTF-8') ?></a>
            <span class="j0ui-badge">0110-i18n</span>
        </div>
    </article>
</section>

<section class="j0ui-card">
    <h2><?= htmlspecialchars(L('Environment', 'Entorno'), ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="j0ui-muted"><?= htmlspecialchars(L('Runtime state persisted in', 'Estado de ejecución persistido en'), ENT_QUOTES, 'UTF-8') ?> <code class="j0ui-mono"><?= htmlspecialchars($ctx['temp_dir'], ENT_QUOTES, 'UTF-8') ?>/ai0-state.json</code>.</p>
    <div class="j0ui-actions">
        <form method="post" class="j0ui-inline-form">
            <input type="hidden" name="action" value="rebuild_ui">
            <input type="hidden" name="browse_root" value="<?= $safeActiveRoot ?>">
            <button type="submit" class="j0ui-btn"><?= htmlspecialchars(L('Rebuild UI', 'Recompilar UI'), ENT_QUOTES, 'UTF-8') ?></button>
        </form>
        <form method="post" class="j0ui-inline-form">
            <input type="hidden" name="action" value="toggle_debug">
            <input type="hidden" name="browse_root" value="<?= $safeActiveRoot ?>">
            <button type="submit" class="j0ui-btn <?= $debug ? 'j0ui-btn--danger' : '' ?>">
                <?= htmlspecialchars($debug ? L('Disable DEBUG', 'Desactivar DEBUG') : L('Enable DEBUG', 'Activar DEBUG'), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </form>
        <span class="j0ui-mono"><?= htmlspecialchars(L('Engine', 'Motor'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($ctx['base_dir'], ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars(L('Scope', 'Alcance'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($ctx['down_rel'] === '' ? '/' : $ctx['down_rel'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
</section>

<?php
ai0_render_foot($ctx, $debug);