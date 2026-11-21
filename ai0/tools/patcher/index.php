<?php
// PATH: ai0-0010/tools/patcher/index.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/ai0-context.php';
require_once dirname(__DIR__, 2) . '/ui/ui-layout.php';
require_once __DIR__ . '/patcher-parser.php';
require_once __DIR__ . '/patcher-engine.php';
require_once __DIR__ . '/patcher-view.php';
require_once dirname(__DIR__) . '/restore/restore-manager.php';

$ctx = ai0_resolve_context(__DIR__);
$state = ai0_get_state($ctx);
$debug = (bool) ($state['debug'] ?? false);

$execResult = null;
$parseErrors = [];
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'apply') {
        $payload = (string) ($_POST['payload'] ?? '');
        $parsed = PatcherParser::parse($payload);
        $parseErrors = $parsed['errors'];

        if ($parsed['operations'] !== []) {
            $engine = new PatcherEngine($ctx['root']);
            $execResult = $engine->execute($parsed['operations']);
        } elseif ($parseErrors === []) {
            $parseErrors[] = 'No valid === CREATE / PATCH / MOVE / DISABLE / DISCARD === blocks detected.';
        }
    } elseif ($action === 'rollback_batch') {
        $ts = (string) ($_POST['timestamp'] ?? '');
        $restored = RestoreManager::restoreBatch($ctx['root'], $ts);
        $flash = "Batch {$ts} restored (" . count($restored) . ' files).';
    }
}

$restoreUrl = ai0_ui_web_base($ctx) . '../tools/restore/index.php';

ai0_render_head('ai0 // patcher', $ctx, 'patcher', $debug);
patcher_render_view([
    'parseErrors' => $parseErrors,
    'execResult'  => $execResult,
    'flash'       => $flash,
    'restoreUrl'  => $restoreUrl,
]);
ai0_render_foot($ctx, $debug);