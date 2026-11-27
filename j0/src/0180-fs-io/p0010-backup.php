<?php
// PATH: ai0-0010/j0/php/j0-backup.php
declare(strict_types=1);

if (!function_exists('j0_backup_rotate')) {
    /**
     * Creates a timestamped mechanical backup using the _📦 prefix:
     * <dir>/_📦<filename>_<YYYY-MM-DDTHH-MM-SS>.<ext>
     */
    function j0_backup_rotate(
        string $filePath,
        string $prefix = "_\u{1F4E6}",
        ?string $timestamp = null,
        bool $move = false
    ): string|false {
        if (!is_file($filePath)) {
            return false;
        }

        $ts = $timestamp ?? date('Y-m-d\TH-i-s');
        $info = pathinfo($filePath);
        $dir = $info['dirname'] ?? '.';
        $base = $info['filename'] ?? 'file';
        $ext = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';

        $target = "{$dir}/{$prefix}{$base}_{$ts}{$ext}";
        $counter = 1;
        while (file_exists($target)) {
            $target = "{$dir}/{$prefix}{$base}_{$ts}_{$counter}{$ext}";
            $counter++;
        }

        $ok = $move ? @rename($filePath, $target) : @copy($filePath, $target);
        return $ok ? $target : false;
    }
}