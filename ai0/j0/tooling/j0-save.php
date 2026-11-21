<?php
// PATH: ai0-0010/j0/php/j0-save.php
declare(strict_types=1);

require_once __DIR__ . '/j0-backup.php';

if (!function_exists('j0_smart_save')) {
    /**
     * Atomically writes $content to $targetFile only if its MD5 differs from disk.
     *
     * @return array{written: bool, backup: string|null, reason: string}
     */
    function j0_smart_save(
        string $targetFile,
        string $content,
        bool $backup = true,
        string $backupPrefix = "_\u{1F4E6}"
    ): array {
        if (is_file($targetFile)) {
            $existingHash = md5_file($targetFile);
            if ($existingHash !== false && $existingHash === md5($content)) {
                return ['written' => false, 'backup' => null, 'reason' => 'unchanged'];
            }
        }

        $dir = dirname($targetFile);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['written' => false, 'backup' => null, 'reason' => 'mkdir_failed'];
        }

        $backupPath = null;
        if ($backup && is_file($targetFile)) {
            $rotated = j0_backup_rotate($targetFile, $backupPrefix, null, false);
            $backupPath = is_string($rotated) ? $rotated : null;
        }

        $tmpFile = $dir . '/.__tmp_' . uniqid('', true);
        if (file_put_contents($tmpFile, $content) === false) {
            @unlink($tmpFile);
            return ['written' => false, 'backup' => $backupPath, 'reason' => 'write_failed'];
        }
        if (!rename($tmpFile, $targetFile)) {
            @unlink($tmpFile);
            return ['written' => false, 'backup' => $backupPath, 'reason' => 'rename_failed'];
        }

        return ['written' => true, 'backup' => $backupPath, 'reason' => 'saved'];
    }
}