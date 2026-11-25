<?php
// PATH: ai0-003/ui/ui-layout.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/j0/tooling/j0-asset-tags.php';
require_once dirname(__DIR__) . '/j0/src/0100-vfs/p0010-vfs-ssr.php';

/**
 * @return array<int, string>
 */
function ai0_ui_js_sources(): array
{
    return [
        dirname(__DIR__) . '/j0/core/01-j0kernel.js',
        dirname(__DIR__) . '/j0/src/0100-vfs/j0010-vfs-core.js',
        dirname(__DIR__) . '/j0/src/0500-ui-patterns/j0501-emitter.js',
        __DIR__ . '/modules',
    ];
}

function ai0_ui_web_base(array $ctx): string
{
    $up = $ctx['up_rel'] ?? '';
    $baseDir = $ctx['base_dir'] ?? 'ai0';
    return $up . $baseDir . '/ui/';
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

    $up = (string) ($ctx['up_rel'] ?? '');
    if ($root !== '' && str_starts_with($tempDir, $root . '/')) {
        $tempRel = substr($tempDir, strlen($root) + 1);
    } else {
        $tempRel = '_j0-temp';
    }

    return [
        'out_base' => $outDir . '/modules',
        'web_base' => $up . $tempRel . '/ai0-ui/',
    ];
}

function ai0_render_head(string $title, array $ctx, string $activeTool = '', bool $debug = false): void
{
    $modulesDir = __DIR__ . '/modules';
    $buildPaths = ai0_ui_build_paths($ctx);
    $cssTags = j0_asset_tags($modulesDir, $buildPaths['out_base'], $buildPaths['web_base'], 'css', $debug);

    $webBase = ai0_ui_web_base($ctx);
    $hubUrl = $webBase . '../tools/index.php';
    $dumperUrl = $webBase . '../tools/dumper/index.php';
    $patcherUrl = $webBase . '../tools/patcher/index.php';
    $restoreUrl = $webBase . '../tools/restore/index.php';
    $purgeUrl = $webBase . '../tools/purge/index.php';
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeRoot = htmlspecialchars($ctx['root'] ?? '', ENT_QUOTES, 'UTF-8');
    $rawTheme = j0_vfs_ui_attr('theme', 'dark');
    $theme = $rawTheme === 'light' ? 'light' : 'dark';
    ?>
<!DOCTYPE html>
<html lang="es" data-theme="<?= $theme ?>">
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
        <div class="j0ui-topbar-end">
            <nav class="j0ui-nav">
                <a class="j0ui-btn <?= $activeTool === 'devtools' ? 'j0ui-btn--active' : '' ?>" href="<?= htmlspecialchars($hubUrl, ENT_QUOTES, 'UTF-8') ?>">DevTools</a>
                <a class="j0ui-btn <?= $activeTool === 'dumper' ? 'j0ui-btn--active' : '' ?>" href="<?= htmlspecialchars($dumperUrl, ENT_QUOTES, 'UTF-8') ?>">dumper</a>
                <a class="j0ui-btn <?= $activeTool === 'patcher' ? 'j0ui-btn--active' : '' ?>" href="<?= htmlspecialchars($patcherUrl, ENT_QUOTES, 'UTF-8') ?>">patcher</a>
                <a class="j0ui-btn <?= $activeTool === 'restore' ? 'j0ui-btn--active' : '' ?>" href="<?= htmlspecialchars($restoreUrl, ENT_QUOTES, 'UTF-8') ?>">restore</a>
                <a class="j0ui-btn <?= $activeTool === 'purge' ? 'j0ui-btn--active' : '' ?>" href="<?= htmlspecialchars($purgeUrl, ENT_QUOTES, 'UTF-8') ?>">purge</a>
            </nav>
            <j0-theme-switch>
                <div class="theme-pill" role="switch" aria-checked="<?= $theme === 'dark' ? 'true' : 'false' ?>" aria-label="Toggle color theme" tabindex="0" data-j0-action="ui:toggle-theme">
                    <span class="pill-icon pill-sun" aria-hidden="true">☀️</span>
                    <span class="pill-indicator"></span>
                    <span class="pill-icon pill-moon" aria-hidden="true">🌙</span>
                </div>
            </j0-theme-switch>
        </div>
    </header>
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