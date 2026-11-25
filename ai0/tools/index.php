<?php
// PATH: ai0/tools/index.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/ai0-context.php';
require_once dirname(__DIR__) . '/ui/ui-layout.php';

$ctx = ai0_resolve_context(__DIR__);
$state = ai0_get_state($ctx);
$flash = null;
$flashType = 'j0ui-alert-success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'toggle_debug') {
        $state['debug'] = !((bool) ($state['debug'] ?? false));
        ai0_save_state($ctx, $state);
        $stateLabel = $state['debug'] ? 'ENABLED (individual unminified asset tags)' : 'DISABLED (compiled .min bundle)';
        $flash = "DEBUG mode {$stateLabel}.";
    } elseif ($action === 'zip_snapshot') {
        $zipRes = ai0_create_zip_snapshot($ctx['root']);
        $flash = $zipRes['message'];
        $flashType = $zipRes['ok'] ? 'j0ui-alert-success' : 'j0ui-alert-danger';
    } elseif ($action === 'rebuild_ui') {
        $modulesDir = dirname(__DIR__) . '/ui/modules';
        $jsSources = ai0_ui_js_sources();
        $buildPaths = ai0_ui_build_paths($ctx);
        $outBase = $buildPaths['out_base'];
        $resCss = j0_build_bundle(['src_dir' => $modulesDir, 'out_file' => $outBase . '.css', 'ext' => 'css']);
        $resJs = j0_build_bundle(['sources' => $jsSources, 'out_file' => $outBase . '.js', 'ext' => 'js']);
        $flash = "UI rebuilt in _j0-temp/ai0-ui (CSS: {$resCss['reason']}, JS: {$resJs['reason']}).";
    }
}

$debug = (bool) ($state['debug'] ?? false);
$webBase = ai0_ui_web_base($ctx);
$dumperUrl = htmlspecialchars($webBase . '../tools/dumper/index.php', ENT_QUOTES, 'UTF-8');
$patcherUrl = htmlspecialchars($webBase . '../tools/patcher/index.php', ENT_QUOTES, 'UTF-8');
$restoreUrl = htmlspecialchars($webBase . '../tools/restore/index.php', ENT_QUOTES, 'UTF-8');
$purgeUrl = htmlspecialchars($webBase . '../tools/purge/index.php', ENT_QUOTES, 'UTF-8');

ai0_render_head('ai0 // DevTools Control Panel', $ctx, 'devtools', $debug);
?>

<?php if ($flash !== null): ?>
    <div class="j0ui-alert-inline <?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="j0ui-grid-2">
    <article class="j0ui-card">
        <h2>dumper — Context Generator</h2>
        <p class="j0ui-muted">Interactive file tree with checkboxes, excluded-only filter, and regex exclusion categories.</p>
        <div class="j0ui-actions">
            <a class="j0ui-btn j0ui-btn--primary" href="<?= $dumperUrl ?>">Open dumper</a>
            <span class="j0ui-badge">Stub: ai0dump.php</span>
        </div>
    </article>

    <article class="j0ui-card">
        <h2>patcher — Atomic Code Applier</h2>
        <p class="j0ui-muted">Applies CREATE, PATCH (1-Line First), MOVE, DISABLE (_), and DISCARD (_🗑️) with _📦 backups and php -l.</p>
        <div class="j0ui-actions">
            <a class="j0ui-btn j0ui-btn--primary" href="<?= $patcherUrl ?>">Open patcher</a>
            <span class="j0ui-badge">Stub: ai0patch.php</span>
        </div>
    </article>

    <article class="j0ui-card">
        <h2>restore — Batch Recovery</h2>
        <p class="j0ui-muted">Scans timestamped _📦 backups across the project and restores previous versions in one click.</p>
        <div class="j0ui-actions">
            <a class="j0ui-btn j0ui-btn--primary" href="<?= $restoreUrl ?>">Open restore</a>
            <span class="j0ui-badge">Stub: ai0restore.php</span>
        </div>
    </article>

    <article class="j0ui-card">
        <h2>purge — Space Reclamation</h2>
        <p class="j0ui-muted">Cleans discarded (_🗑️, __) and backup (_📦) items while keeping human-disabled (_) files protected.</p>
        <div class="j0ui-actions">
            <a class="j0ui-btn j0ui-btn--primary" href="<?= $purgeUrl ?>">Open purge</a>
            <span class="j0ui-badge">Stub: ai0purge.php</span>
        </div>
    </article>
</section>

<section class="j0ui-card">
    <h2>Environment &amp; Snapshots</h2>
    <p class="j0ui-muted">Runtime state persisted in <code class="j0ui-mono"><?= htmlspecialchars($ctx['temp_dir'], ENT_QUOTES, 'UTF-8') ?>/ai0-state.json</code>.</p>
    <div class="j0ui-actions">
        <form method="post" class="j0ui-inline-form">
            <input type="hidden" name="action" value="zip_snapshot">
            <button type="submit" class="j0ui-btn">Create _📦 ZIP Snapshot</button>
        </form>
        <form method="post" class="j0ui-inline-form">
            <input type="hidden" name="action" value="rebuild_ui">
            <button type="submit" class="j0ui-btn">Rebuild UI</button>
        </form>
        <form method="post" class="j0ui-inline-form">
            <input type="hidden" name="action" value="toggle_debug">
            <button type="submit" class="j0ui-btn <?= $debug ? 'j0ui-btn--danger' : '' ?>">
                <?= $debug ? 'Disable DEBUG' : 'Enable DEBUG' ?>
            </button>
        </form>
        <span class="j0ui-mono">Engine: <?= htmlspecialchars($ctx['base_dir'], ENT_QUOTES, 'UTF-8') ?> | Scope: <?= htmlspecialchars($ctx['down_rel'] === '' ? '/' : $ctx['down_rel'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
</section>

<?php
ai0_render_foot($ctx, $debug);