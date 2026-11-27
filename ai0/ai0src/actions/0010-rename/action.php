<?php
// PATH: ai0/ai0src/actions/0010-rename/action.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

return [
    'run' => static function (array $target, array $ctx, array $params): array {
        $src = (string) ($target['abs'] ?? '');
        $newName = trim((string) ($params['new_name'] ?? ''));
        if ($src === '' || !file_exists($src) || $newName === '' || str_contains($newName, '/') || str_contains($newName, chr(92)) || str_contains($newName, '..')) {
            return ['ok' => false, 'message' => L('Invalid item path or new name for rename.', 'Ruta o nombre nuevo inválido para renombrar.')];
        }
        $dst = dirname($src) . '/' . $newName;
        if (file_exists($dst)) {
            return ['ok' => false, 'message' => L('Destination name already exists.', 'El nombre de destino ya existe.')];
        }
        if (!@rename($src, $dst)) {
            return ['ok' => false, 'message' => L('Failed to rename item.', 'Error al renombrar el elemento.')];
        }
        return ['ok' => true, 'reload' => true, 'message' => L('Renamed to {0}.', 'Renombrado a {0}.', [$newName])];
    },
];