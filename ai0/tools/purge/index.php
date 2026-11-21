<?php
// PATH: ai0-0010/tools/purge/index.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/ai0-context.php';
require_once dirname(__DIR__, 2) . '/ui/ui-layout.php';
require_once __DIR__ . '/purge-manager.php';
require_once __DIR__ . '/purge-view.php';

$ctx = ai0_resolve_context(__DIR__);
$state = ai0_get_state($ctx);
$debug = (bool) ($state['debug'] ?? false);
$scopeRel = $ctx['down_rel'] ?? '';
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'purge_selected') {
    $items = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : [];
    $count = PurgeManager::deleteSelected($ctx['root'], $items);
    $flash = "Purge completed: {$count} item(s) permanently deleted.";
}

$prefixCfg = j0_config_json('prefixes', $ctx['domains']);
$scan = PurgeManager::scan($ctx['root'], $prefixCfg, $scopeRel);
$restoreUrl = ai0_ui_web_base($ctx) . '../tools/restore/index.php';

ai0_render_head('ai0 // purge', $ctx, 'purge', $debug);
purge_render_view([
    'scan'       => $scan,
    'flash'      => $flash,
    'scopeRel'   => $scopeRel,
    'restoreUrl' => $restoreUrl,
]);
ai0_render_foot($ctx, $debug);