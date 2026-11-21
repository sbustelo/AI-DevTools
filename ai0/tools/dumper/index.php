<?php
// PATH: ai0-0010/tools/dumper/index.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/ai0-context.php';
require_once dirname(__DIR__, 2) . '/ui/ui-layout.php';
require_once __DIR__ . '/dumper-config.php';
require_once __DIR__ . '/dumper-scanner.php';
require_once __DIR__ . '/dumper-formatter.php';
require_once __DIR__ . '/dumper-view.php';

$ctx = ai0_resolve_context(__DIR__);
$state = ai0_get_state($ctx);
$debug = (bool) ($state['debug'] ?? false);
$dumperConfig = DumperConfig::load($ctx['domains']);

$scopeRel = $ctx['down_rel'] ?? '';
if (isset($_GET['scope']) && $_GET['scope'] === 'root') {
    $scopeRel = '';
}

$dumpOutput = null;
$compact = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_dump') {
    $selected = isset($_POST['selected_files']) && is_array($_POST['selected_files']) ? $_POST['selected_files'] : [];
    $compact = isset($_POST['compact']) && $_POST['compact'] === '1';
    $dumpOutput = DumperFormatter::buildDump($ctx['root'], $selected, $dumperConfig['binary_extensions'], $compact);
}

$scan = DumperScanner::scan($ctx['root'], $dumperConfig, $scopeRel);
$projectName = basename($ctx['root']) ?: 'project';
$downloadName = "_{$projectName}-" . date('Y-m-d--H-i-s') . '.dump.txt';
$fullProjectUrl = $scopeRel !== '' ? '?scope=root' : null;

ai0_render_head('ai0 // dumper', $ctx, 'dumper', $debug);
dumper_render_view([
    'scan'           => $scan,
    'categories'     => $dumperConfig['categories'],
    'dumpOutput'     => $dumpOutput,
    'downloadName'   => $downloadName,
    'compact'        => $compact,
    'scopeRel'       => $scopeRel,
    'fullProjectUrl' => $fullProjectUrl,
]);
ai0_render_foot($ctx, $debug);