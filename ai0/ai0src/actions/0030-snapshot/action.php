<?php
// PATH: ai0/ai0src/actions/0030-snapshot/action.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

return [
    'run' => static function (array $target): array {
        $abs = (string) ($target['abs'] ?? '');
        if ($abs === '' || !file_exists($abs)) {
            return ['ok' => false, 'message' => L('Target path does not exist.', 'La ruta objetivo no existe.')];
        }
        if (is_dir($abs)) {
            $zipRes = ai0_create_zip_snapshot($abs);
            return ['ok' => $zipRes['ok'], 'reload' => $zipRes['ok'], 'message' => $zipRes['message']];
        }
        j0_use('fs-io');
        $bakPath = j0_backup_rotate($abs, "_\u{1F4E6}", date('Y-m-d\TH-i-s'), false);
        if (is_string($bakPath) && is_file($bakPath)) {
            return ['ok' => true, 'reload' => true, 'message' => L('Snapshot created: {0}', 'Snapshot creado: {0}', [basename($bakPath)])];
        }
        return ['ok' => false, 'message' => L('Failed to create snapshot.', 'Error al crear el snapshot.')];
    },
];