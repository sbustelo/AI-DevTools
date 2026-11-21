<?php
// PATH: ai0-003/ui/ui-layout.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/j0/tooling/j0-asset-tags.php';

function ai0_ui_web_base(array $ctx): string
{
    $up = $ctx['up_rel'] ?? '';
    $baseDir = $ctx['base_dir'] ?? 'ai0-003';
    return $up . $baseDir . '/ui/';
}

function ai0_render_head(string $title, array $ctx, string $activeTool = '', bool $debug = false): void
{
    $modulesDir = __DIR__ . '/modules';
    $outBase = __DIR__ . '/modules';
    $webBase = ai0_ui_web_base($ctx);
    $cssTags = j0_asset_tags($modulesDir, $outBase, $webBase, 'css', $debug);

    $hubUrl = $webBase . '../index.php';
    $dumperUrl = $webBase . '../tools/dumper/index.php';
    $patcherUrl = $webBase . '../tools/patcher/index.php';
    $restoreUrl = $webBase . '../tools/restore/index.php';
    $purgeUrl = $webBase . '../tools/purge/index.php';
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeRoot = htmlspecialchars($ctx['root'] ?? '', ENT_QUOTES, 'UTF-8');
    ?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $safeTitle ?></title>
    <?= $cssTags . "\n" ?>
</head>
<body>
<main class="j0ui-shell">
    <header class="j0ui-topbar">
        <div class="j0ui-brand">
            <h1 class="j0ui-brand-title">ai0</h1>
            <span class="j0ui-muted j0ui-mono"><?= $safeRoot ?></span>
            <?php if ($debug): ?>
                <span class="j0ui-badge j0ui-badge-warning">DEBUG</span>
            <?php endif; ?>
        </div>
        <nav class="j0ui-nav">
            <a class="j0ui-btn <?= $activeTool === 'devtools' ? 'j0ui-btn--active' : '' ?>" href="<?= htmlspecialchars($hubUrl, ENT_QUOTES, 'UTF-8') ?>">DevTools</a>
            <a class="j0ui-btn <?= $activeTool === 'dumper' ? 'j0ui-btn--active' : '' ?>" href="<?= htmlspecialchars($dumperUrl, ENT_QUOTES, 'UTF-8') ?>">dumper</a>
            <a class="j0ui-btn <?= $activeTool === 'patcher' ? 'j0ui-btn--active' : '' ?>" href="<?= htmlspecialchars($patcherUrl, ENT_QUOTES, 'UTF-8') ?>">patcher</a>
            <a class="j0ui-btn <?= $activeTool === 'restore' ? 'j0ui-btn--active' : '' ?>" href="<?= htmlspecialchars($restoreUrl, ENT_QUOTES, 'UTF-8') ?>">restore</a>
            <a class="j0ui-btn <?= $activeTool === 'purge' ? 'j0ui-btn--active' : '' ?>" href="<?= htmlspecialchars($purgeUrl, ENT_QUOTES, 'UTF-8') ?>">purge</a>
            <button type="button" class="j0ui-btn" data-j0-action="theme-toggle">Theme</button>
        </nav>
    </header>
    <?php
}

function ai0_render_foot(array $ctx, bool $debug = false): void
{
    $modulesDir = __DIR__ . '/modules';
    $outBase = __DIR__ . '/modules';
    $webBase = ai0_ui_web_base($ctx);
    $jsTags = j0_asset_tags($modulesDir, $outBase, $webBase, 'js', $debug);
    ?>
</main>
<?= $jsTags . "\n" ?>
</body>
</html>
    <?php
}