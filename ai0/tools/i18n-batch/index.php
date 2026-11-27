<?php
// PATH: ai0/tools/i18n-batch/index.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    define('J0_EXEC', true);
}

require_once dirname(__DIR__, 2) . '/core/ai0-context.php';
require_once dirname(__DIR__, 2) . '/ui/ui-layout.php';
require_once __DIR__ . '/i18n-batch-engine.php';
require_once __DIR__ . '/i18n-batch-view.php';

$ctx = ai0_resolve_context(__DIR__);
$state = ai0_get_state($ctx);
$debug = (bool) ($state['debug'] ?? false);

$i18nCfg = j0_config_json('i18n', $ctx['domains']);
$langs = (isset($i18nCfg['langs']) && is_array($i18nCfg['langs']) && $i18nCfg['langs'] !== []) ? $i18nCfg['langs'] : ['en', 'es'];
$primaryLang = (string) ($langs[0] ?? 'en');
$defaultTarget = (string) ($langs[1] ?? $primaryLang);

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

$units = I18nBatchEngine::discoverUnits($activeRoot);
$selectedUnit = (string) ($_POST['unit'] ?? ($units[0] ?? ''));
$targetLang = strtolower(trim((string) ($_POST['target_lang'] ?? $defaultTarget)));
$dumpInput = trim((string) ($_POST['m_dump_json'] ?? ''));

$diff = null;
$deltaPrompt = '';

if ($selectedUnit !== '' && in_array($selectedUnit, $units, true)) {
    $unitDir = $selectedUnit === '.' ? $activeRoot : (rtrim($activeRoot, '/') . '/' . $selectedUnit);
    $sourceFile = $unitDir . '/i18n/' . $primaryLang . '.json';
    $targetFile = $unitDir . '/i18n/' . $targetLang . '.json';

    $sourceCatalog = [];
    if ($dumpInput !== '') {
        $decodedDump = json_decode($dumpInput, true);
        if (is_array($decodedDump)) {
            $sourceCatalog = $decodedDump;
        }
    } elseif (is_file($sourceFile)) {
        $decodedSrc = json_decode((string) file_get_contents($sourceFile), true);
        if (is_array($decodedSrc)) {
            $sourceCatalog = $decodedSrc;
        }
    }

    $targetDict = [];
    if (is_file($targetFile)) {
        $decodedTgt = json_decode((string) file_get_contents($targetFile), true);
        if (is_array($decodedTgt)) {
            $targetDict = $decodedTgt;
        }
    }

    $diff = I18nBatchEngine::diff($sourceCatalog, $targetDict);
    $deltaPrompt = I18nBatchEngine::buildDeltaPrompt($selectedUnit, $targetLang, $diff);
}

$rootQuery = ($activeRoot !== $defaultRoot) ? ('?root=' . rawurlencode($activeRoot)) : '';
$patcherUrl = ai0_ui_web_base($ctx) . '../tools/patcher/index.php' . $rootQuery;

ai0_render_head('ai0 // i18n-batch', $ctx, 'i18n-batch', $debug);
i18n_batch_render_view([
    'units'        => $units,
    'selectedUnit' => $selectedUnit,
    'langs'        => $langs,
    'primaryLang'  => $primaryLang,
    'targetLang'   => $targetLang,
    'dumpInput'    => $dumpInput,
    'diff'         => $diff,
    'deltaPrompt'  => $deltaPrompt,
    'patcherUrl'   => $patcherUrl,
    'activeRoot'   => $activeRoot,
]);
ai0_render_foot($ctx, $debug);