<?php
// PATH: ai0/tools/patcher/index.php
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

$bs = chr(92);
$projectRoot = rtrim(str_replace($bs, '/', (string) ($ctx['project_root'] ?? $ctx['root'] ?? __DIR__)), '/');
if ($projectRoot === '') {
    $projectRoot = '/';
}

$iconsCfg = j0_config_json('icons', $ctx['domains']);
$ctx['active_root'] = $projectRoot;

$execResult = null;
$parseErrors = [];
$flash = null;
$commands = '';
$rootMismatch = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'apply' || $action === 'dry_run') {
        $commands = (string) ($_POST['commands'] ?? $_POST['payload'] ?? '');
        $parsed = PatcherParser::parse($commands);
        $parseErrors = $parsed['errors'];

        $declaredRaw = isset($parsed['declared_root']) && is_string($parsed['declared_root'])
            ? $parsed['declared_root']
            : '';
        $declaredClean = $declaredRaw !== ''
            ? ai0_parse_input_path($declaredRaw, $projectRoot, $iconsCfg)
            : '';
        $confirmedSwitch = ((string) ($_POST['confirm_root_switch'] ?? '')) === '1';

        if ($declaredClean !== '' && $declaredClean !== $projectRoot) {
            if (!is_dir($declaredClean) || str_contains($declaredClean, '..')) {
                $parseErrors[] = L(
                    'Declared ROOT path does not exist locally: {0}',
                    'La ruta ROOT declarada no existe localmente: {0}',
                    [$declaredRaw]
                );
            } elseif ($confirmedSwitch) {
                ai0_set_project_root($ctx, $declaredClean);
                $projectRoot = $declaredClean;
                $ctx['active_root'] = $projectRoot;
            } else {
                $rootMismatch = [
                    'current'  => $projectRoot,
                    'declared' => $declaredClean,
                    'action'   => $action,
                ];
            }
        }

        if ($rootMismatch === null && $parseErrors === []) {
            if ($parsed['operations'] !== []) {
                $engine = new PatcherEngine($projectRoot, null, (string) ($ctx['temp_dir'] ?? ''));
                $execResult = $action === 'dry_run'
                    ? $engine->dryRun($parsed['operations'])
                    : $engine->execute($parsed['operations']);
                if ($action === 'apply' && $execResult['errors'] === []) {
                    $commands = '';
                }
            } else {
                $parseErrors[] = L(
                    'No valid === CREATE / PATCH / MOVE / DISABLE / DISCARD / DUMP / TREE === commands detected.',
                    'No se detectaron comandos válidos === CREATE / PATCH / MOVE / DISABLE / DISCARD / DUMP / TREE ===.'
                );
            }
        }
    } elseif ($action === 'rollback_batch') {
        $ts = (string) ($_POST['timestamp'] ?? '');
        $restored = RestoreManager::restoreBatch($projectRoot, $ts);
        $flash = L('Batch {0} restored ({1} files).', 'Lote {0} restaurado ({1} archivos).', [$ts, count($restored)]);
    }
}

j0_i18n_config(['unit_dirs' => [__DIR__]]);
ai0_render_head('ai0 // patcher', $ctx, 'patcher', $debug);
patcher_render_view([
    'parseErrors'  => $parseErrors,
    'execResult'   => $execResult,
    'flash'        => $flash,
    'commands'     => $commands,
    'projectRoot'  => $projectRoot,
    'rootMismatch' => $rootMismatch,
    'icons'        => $iconsCfg,
    'rootIconHtml' => function_exists('ai0_resolve_icon') ? ai0_resolve_icon($projectRoot, $ctx, 'dir', 'j0ui-toolbar-icon')['html'] : '',
]);
ai0_render_foot($ctx, $debug);