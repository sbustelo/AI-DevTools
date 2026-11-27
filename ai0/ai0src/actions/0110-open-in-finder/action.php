<?php
// PATH: ai0/ai0src/actions/0110-open-in-finder/action.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

return [
    'match' => static function (): bool {
        return PHP_OS_FAMILY === 'Darwin';
    },
    'run' => static function (array $target): array {
        $abs = (string) ($target['abs'] ?? '');
        $real = realpath($abs);
        if ($real === false || !file_exists($real)) {
            return ['ok' => false, 'message' => L('Target path does not exist.', 'La ruta objetivo no existe.')];
        }
        $osaOut = [];
        $osaCode = 0;
        $osaCmd = '/usr/bin/osascript'
            . ' -e ' . escapeshellarg('on run argv')
            . ' -e ' . escapeshellarg('tell application "Finder"')
            . ' -e ' . escapeshellarg('reveal (POSIX file (item 1 of argv) as alias)')
            . ' -e ' . escapeshellarg('activate')
            . ' -e ' . escapeshellarg('end tell')
            . ' -e ' . escapeshellarg('end run')
            . ' -- ' . escapeshellarg($real) . ' 2>&1';
        @exec($osaCmd, $osaOut, $osaCode);

        $out = [];
        $code = $osaCode;
        if ($osaCode !== 0) {
            $cmd = '/usr/bin/open -R ' . escapeshellarg($real) . ' 2>&1';
            @exec($cmd, $out, $code);
        }

        $runUser = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
            ? (string) ((posix_getpwuid(posix_geteuid())['name'] ?? ''))
            : trim((string) @shell_exec('whoami'));
        $debug = [
            'user'     => $runUser,
            'open_code'=> $code,
            'open_out' => implode(' ', $out),
            'osa_code' => $osaCode,
            'osa_out'  => implode(' ', $osaOut),
        ];

        if ($code !== 0 && $osaCode !== 0) {
            return [
                'ok'      => false,
                'message' => L('Failed to reveal in Finder: {0}', 'No se pudo mostrar en Finder: {0}', [implode(' ', array_merge($out, $osaOut))]),
                'debug'   => $debug,
            ];
        }
        return [
            'ok'      => true,
            'message' => L('Revealed in Finder: {0}', 'Mostrado en Finder: {0}', [basename($real)]),
            'debug'   => $debug,
        ];
    },
];