<?php
// PATH: ai0/ai0src/actions/0130-open-in-editor/action.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

return [
    'run' => static function (array $target, array $ctx): array {
        $abs = (string) ($target['abs'] ?? '');
        $real = realpath($abs);
        if ($real === false || !file_exists($real)) {
            return ['ok' => false, 'message' => L('Target path does not exist.', 'La ruta objetivo no existe.')];
        }
        $cfg = j0_config_php('actions', (array) ($ctx['domains'] ?? [])) ?? [];
        $tpl = (string) ($cfg['editor_cmd'] ?? "open {file} -a '/Applications/Visual Studio Code.app'");
        $cmd = str_replace('{file}', escapeshellarg($real), $tpl) . ' 2>&1';
        $out = [];
        $code = 0;
        @exec($cmd, $out, $code);
        if ($code !== 0) {
            return ['ok' => false, 'message' => L('Failed to open in editor: {0}', 'No se pudo abrir en el editor: {0}', [implode(' ', $out)])];
        }
        return ['ok' => true, 'message' => L('Opened in editor: {0}', 'Abierto en el editor: {0}', [basename($real)])];
    },
];