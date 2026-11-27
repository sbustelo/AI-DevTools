<?php
// PATH: ai0/ai0src/actions/9900-discard/action.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

return [
    'run' => static function (array $target): array {
        $abs = (string) ($target['abs'] ?? '');
        $real = realpath($abs);
        if ($real === false || !file_exists($real)) {
            return ['ok' => false, 'message' => L('Target path does not exist.', 'La ruta objetivo no existe.')];
        }
        $base = basename($real);
        $prefix = "_\u{1F5D1}";
        if (str_starts_with($base, $prefix)) {
            return ['ok' => true, 'message' => L('Already discarded: {0}', 'Ya descartado: {0}', [$base])];
        }
        $dst = dirname($real) . '/' . $prefix . $base;
        if (file_exists($dst)) {
            $info = pathinfo($real);
            $ext = isset($info['extension']) && $info['extension'] !== '' ? ('.' . $info['extension']) : '';
            $dst = dirname($real) . '/' . $prefix . $info['filename'] . '_' . date('Y-m-d\TH-i-s') . $ext;
        }
        if (!@rename($real, $dst)) {
            return ['ok' => false, 'message' => L('Failed to discard item.', 'Error al descartar el elemento.')];
        }
        return ['ok' => true, 'reload' => true, 'message' => L('Discarded: {0}', 'Descartado: {0}', [basename($dst)])];
    },
];