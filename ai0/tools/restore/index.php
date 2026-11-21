<?php
// PATH: ai0-0010/tools/restore/index.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/ai0-context.php';
require_once dirname(__DIR__, 2) . '/ui/ui-layout.php';
require_once __DIR__ . '/restore-manager.php';
require_once __DIR__ . '/restore-view.php';

$ctx = ai0_resolve_context(__DIR__);
$state = ai0_get_state($ctx);
$debug = (bool) ($state['debug'] ?? false);
$scopeRel = $ctx['down_rel'] ?? '';
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'restore_batch') {
        $ts = (string) ($_POST['timestamp'] ?? '');
        $restored = RestoreManager::restoreBatch($ctx['root'], $ts);
        $flash = "Batch {$ts} restored (" . count($restored) . ' files).';
    } elseif ($action === 'restore_single') {
        $bak = (string) ($_POST['backup_rel'] ?? '');
        $restoredFile = RestoreManager::restoreSingle($ctx['root'], $bak);
        $flash = $restoredFile !== null ? "Restored: {$restoredFile}" : 'Failed to restore selected backup file.';
    }
}

$scan = RestoreManager::scan($ctx['root'], $scopeRel);
$purgeUrl = ai0_ui_web_base($ctx) . '../tools/purge/index.php';

ai0_render_head('ai0 // restore', $ctx, 'restore', $debug);
restore_render_view([
    'scan'     => $scan,
    'flash'    => $flash,
    'scopeRel' => $scopeRel,
    'purgeUrl' => $purgeUrl,
]);
ai0_render_foot($ctx, $debug);