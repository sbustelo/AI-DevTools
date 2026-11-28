<?php
// PATH: ai0/ai0src/actions/0130-open-in-editor/action.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

$openEditorPaths = static function (array $absPaths, array $ctx): array {
    $escaped = [];
    $names = [];
    foreach ($absPaths as $abs) {
        $real = realpath((string) $abs);
        if ($real !== false && file_exists($real)) {
            $escaped[] = escapeshellarg($real);
            $names[] = basename($real);
        }
    }
    if ($escaped === []) {
        return ['ok' => false, 'message' => L('Target path does not exist.', 'La ruta objetivo no existe.')];
    }
    $cfg = j0_config_php('actions', (array) ($ctx['domains'] ?? [])) ?? [];
    $tpl = (string) ($cfg['editor_cmd'] ?? "open {file} -a '/Applications/Visual Studio Code.app'");
    $cmd = str_replace('{file}', implode(' ', $escaped), $tpl) . ' 2>&1';
    $out = [];
    $code = 0;
    @exec($cmd, $out, $code);
    if ($code !== 0) {
        return ['ok' => false, 'message' => L('Failed to open in editor: {0}', 'No se pudo abrir en el editor: {0}', [implode(' ', $out)])];
    }
    $label = count($names) === 1
        ? $names[0]
        : L('{0} items', '{0} elementos', [count($names)]);
    return ['ok' => true, 'message' => L('Opened in editor: {0}', 'Abierto en el editor: {0}', [$label])];
};

return [
    'run' => static function (array $target, array $ctx) use ($openEditorPaths): array {
        return $openEditorPaths([(string) ($target['abs'] ?? '')], $ctx);
    },
    'run_batch' => static function (array $targets, array $ctx) use ($openEditorPaths): array {
        $paths = array_map(static fn(array $t): string => (string) ($t['abs'] ?? ''), $targets);
        return $openEditorPaths($paths, $ctx);
    },
];