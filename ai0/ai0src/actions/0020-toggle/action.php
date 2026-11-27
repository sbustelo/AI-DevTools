<?php
// PATH: ai0/ai0src/actions/0020-toggle/action.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

return [
    'run' => static function (array $target): array {
        $src = (string) ($target['abs'] ?? '');
        if ($src === '' || !file_exists($src)) {
            return ['ok' => false, 'message' => L('Target item not found.', 'Elemento objetivo no encontrado.')];
        }
        $base = basename($src);
        $nextBase = str_starts_with($base, '_') ? substr($base, 1) : ('_' . $base);
        $dst = dirname($src) . '/' . $nextBase;
        if ($nextBase === '' || file_exists($dst)) {
            return ['ok' => false, 'message' => L('Destination name already exists.', 'El nombre de destino ya existe.')];
        }
        if (!@rename($src, $dst)) {
            return ['ok' => false, 'message' => L('Failed to toggle _ prefix.', 'Error al alternar el prefijo _.')];
        }
        return ['ok' => true, 'reload' => true, 'message' => L('Toggled: {0} -> {1}', 'Alternado: {0} -> {1}', [$base, $nextBase])];
    },
];