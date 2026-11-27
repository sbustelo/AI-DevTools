<?php
// PATH: ai0/ai0src/actions/0040-color/action.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

return [
    'run' => static function (array $target, array $ctx, array $params): array {
        $abs = (string) ($target['abs'] ?? '');
        if ($abs === '' || !is_dir($abs)) {
            return ['ok' => false, 'message' => L('Color labels apply to folders only.', 'Las etiquetas de color aplican solo a carpetas.')];
        }

        $allowed = ['', 'red', 'orange', 'yellow', 'green', 'blue', 'violet', 'brown', 'black'];
        $color = strtolower(trim((string) ($params['color'] ?? '')));
        if (!in_array($color, $allowed, true)) {
            return ['ok' => false, 'message' => L('Invalid color label.', 'Etiqueta de color inválida.')];
        }

        $overrides = ['color' => $color];
        if (!is_file($abs . '/.ai0root.json')) {
            $overrides['is_project_root'] = false;
        }

        $ok = ai0_write_root_marker($abs, $overrides, (string) ($ctx['temp_dir'] ?? ''));
        return [
            'ok'      => $ok,
            'reload'  => false,
            'color'   => $color,
            'message' => $ok
                ? L('Color label updated.', 'Etiqueta de color actualizada.')
                : L('Failed to write folder metadata.', 'Error al escribir la metadata de la carpeta.'),
        ];
    },
];