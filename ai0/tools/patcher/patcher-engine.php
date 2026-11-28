<?php
// PATH: ai0/tools/patcher/patcher-engine.php
declare(strict_types=1);

if (function_exists('j0_use')) {
    j0_use('fs-io');
}
require_once dirname(__DIR__) . '/files/dumper-formatter.php';
require_once __DIR__ . '/patcher-katex-guard.php';
require_once __DIR__ . '/patcher-staging.php';

final class PatcherEngine
{
    private string $projectRoot;
    private string $timestamp;
    private ?string $phpCli;
    private string $stagingDir;

    public function __construct(string $projectRoot, ?string $timestamp = null, ?string $tempDir = null)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->timestamp = $timestamp ?? date('Y-m-d\TH-i-s');
        $this->phpCli = $this->resolvePhpCli();
        $cleanTemp = ($tempDir !== null && trim($tempDir) !== '')
            ? rtrim(str_replace(chr(92), '/', $tempDir), '/')
            : ($this->projectRoot . '/_j0-temp');
        $this->stagingDir = $cleanTemp . '/patcher-staging';
    }

    /**
     * @param array<int, array<string, mixed>> $operations
     * @return array{timestamp: string, applied: array<int, string>, warnings: array<int, string>, errors: array<int, array{path: string, reason: string}>}
     */
    /**
     * Simulates all operations in memory (anchors, KaTeX guard, MOVE chains, and php -l)
     * without modifying any project files or creating _📦 backups.
     *
     * @param array<int, array<string, mixed>> $operations
     * @return array{dry_run: bool, timestamp: string, applied: array<int, string>, warnings: array<int, string>, errors: array<int, array{path: string, reason: string}>}
     */
    public function dryRun(array $operations): array
    {
        $planned = [];
        $warnings = [];
        $errors = [];
        $dumpFiles = [];
        $treeOutputs = [];
        $virtualBuffers = [];
        $virtualMoves = [];

        foreach ($operations as $op) {
            $opWarnings = PatcherKatexGuard::scanOperation($op);
            if ($opWarnings !== []) {
                $warnings = array_merge($warnings, $opWarnings);
            }

            $type = (string) ($op['type'] ?? '');
            $relPath = (string) ($op['path'] ?? '');

            if ($type === 'RESOLVE' || $type === 'RETRY_HUNK') {
                $sim = $this->simulateStagedOp($op);
                foreach ($sim['applied'] as $msg) {
                    $planned[] = $msg;
                }
                foreach ($sim['errors'] as $err) {
                    $errors[] = $err;
                }
                continue;
            }

            if ($type === 'MOVE') {
                $dstRel = (string) ($op['dest'] ?? '');
                $srcFull = $this->resolveSafePath($relPath);
                $dstFull = $this->resolveSafePath($dstRel);
                if ($srcFull === null || $dstFull === null) {
                    $errors[] = ['path' => $relPath, 'reason' => 'Invalid or blocked path in MOVE.'];
                    continue;
                }
                if (!file_exists($srcFull) && !isset($virtualBuffers[$srcFull])) {
                    $errors[] = ['path' => $relPath, 'reason' => 'Source path does not exist for MOVE.'];
                    continue;
                }
                if (file_exists($dstFull) && !isset($virtualMoves[$dstFull])) {
                    $errors[] = ['path' => $relPath, 'reason' => "Destination already exists: {$dstRel}"];
                    continue;
                }
                $virtualMoves[$dstFull] = $srcFull;
                if (isset($virtualBuffers[$srcFull])) {
                    $virtualBuffers[$dstFull] = $virtualBuffers[$srcFull];
                }
                $planned[] = "DRY-RUN MOVE: {$relPath} -> {$dstRel}";
                continue;
            }

            if ($type === 'DUMP' || $type === 'TREE') {
                $readRes = $this->collectInspection($type, $relPath, $dumpFiles, $treeOutputs);
                if ($readRes['ok']) {
                    $planned[] = $readRes['message'];
                } else {
                    $errors[] = ['path' => $relPath, 'reason' => $readRes['message']];
                }
                continue;
            }

            if ($type === 'DISABLE' || $type === 'DISCARD') {
                $fullPath = $this->resolveSafePath($relPath);
                if ($fullPath === null) {
                    $errors[] = ['path' => $relPath, 'reason' => "Invalid or blocked path for {$type}."];
                    continue;
                }
                if (!file_exists($fullPath) && !isset($virtualBuffers[$fullPath])) {
                    $errors[] = ['path' => $relPath, 'reason' => "Target does not exist for {$type}."];
                    continue;
                }
                $planned[] = "DRY-RUN {$type}: {$relPath}";
                continue;
            }

            $fullPath = $this->resolveSafePath($relPath);
            if ($fullPath === null) {
                $errors[] = ['path' => $relPath, 'reason' => 'Invalid or blocked path.'];
                continue;
            }

            $sourcePath = $virtualMoves[$fullPath] ?? $fullPath;
            $hasVirtual = array_key_exists($fullPath, $virtualBuffers);
            $existed = $hasVirtual || is_file($sourcePath);

            if ($type === 'CREATE') {
                $buffer = str_replace(["\r\n", "\r"], "\n", (string) ($op['content'] ?? ''));
            } elseif ($type === 'PATCH') {
                if (!$existed) {
                    $errors[] = ['path' => $relPath, 'reason' => 'Target file does not exist for PATCH.'];
                    continue;
                }
                $buffer = $hasVirtual
                    ? $virtualBuffers[$fullPath]
                    : str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($sourcePath));

                $hunkFailed = false;
                foreach ($op['hunks'] as $idx => $hunk) {
                    $patchRes = $this->applyHunk(
                        $buffer,
                        (string) $hunk['start'],
                        (string) $hunk['end'],
                        (string) $hunk['replace'],
                        $idx + 1,
                        (int) ($hunk['start_match'] ?? 0),
                        (int) ($hunk['end_match'] ?? 0)
                    );
                    if (!$patchRes['ok']) {
                        $errors[] = ['path' => $relPath, 'reason' => $patchRes['message']];
                        $hunkFailed = true;
                        break;
                    }
                    $buffer = $patchRes['content'];
                }
                if ($hunkFailed) {
                    continue;
                }
            } else {
                continue;
            }

            if (str_ends_with(strtolower($fullPath), '.php') && $this->phpCli !== null) {
                $lintErr = $this->lintPhpContent($buffer);
                if ($lintErr !== null) {
                    $errors[] = ['path' => $relPath, 'reason' => $lintErr];
                    continue;
                }
            }

            $virtualBuffers[$fullPath] = $buffer;
            $tag = $existed ? 'DRY-RUN UPDATE' : 'DRY-RUN CREATE';
            $planned[] = "{$tag}: {$relPath} (" . strlen($buffer) . ' bytes)';
        }

        return [
            'dry_run'     => true,
            'timestamp'   => $this->timestamp,
            'applied'     => $planned,
            'warnings'    => $warnings,
            'errors'      => $errors,
            'read_output' => $this->formatInspectionOutput($dumpFiles, $treeOutputs),
        ];
    }

    public function execute(array $operations): array
    {
        $applied = [];
        $warnings = [];
        $errors = [];
        $dumpFiles = [];
        $treeOutputs = [];
        $stagedFiles = [];

        foreach ($operations as $op) {
            if ((string) ($op['type'] ?? '') === 'RETRY_HUNK' && isset($op['hunk_def'])) {
                $scanTarget = ['type' => 'PATCH', 'path' => (string) ($op['path'] ?? ''), 'hunks' => [$op['hunk_def']]];
                $opWarnings = PatcherKatexGuard::scanOperation($scanTarget);
            } else {
                $opWarnings = PatcherKatexGuard::scanOperation($op);
            }
            if ($opWarnings !== []) {
                $warnings = array_merge($warnings, $opWarnings);
            }

            $type = (string) ($op['type'] ?? '');
            $relPath = (string) ($op['path'] ?? '');

            if ($type === 'RESOLVE') {
                $resBatch = $this->executeResolve($op);
                $applied = array_merge($applied, $resBatch['applied']);
                $errors = array_merge($errors, $resBatch['errors']);
                continue;
            }
            if ($type === 'RETRY_HUNK') {
                $resRetry = $this->executeRetryHunk($op);
                if ($resRetry['ok']) {
                    $applied[] = $resRetry['message'];
                } else {
                    $errors[] = ['path' => $relPath, 'reason' => $resRetry['message']];
                }
                continue;
            }

            if ($type === 'DUMP' || $type === 'TREE') {
                $res = $this->collectInspection($type, $relPath, $dumpFiles, $treeOutputs);
            } elseif ($type === 'MOVE') {
                $res = $this->executeMove($relPath, (string) ($op['dest'] ?? ''));
            } elseif ($type === 'DISABLE') {
                $res = $this->executePrefixRename($relPath, '_', 'DISABLED');
            } elseif ($type === 'DISCARD') {
                $res = $this->executePrefixRename($relPath, "_\u{1F5D1}", 'DISCARDED');
            } else {
                $res = $this->executeWriteOp($relPath, $op);
            }

            if ($res['ok']) {
                $applied[] = $res['message'];
            } else {
                $errors[] = ['path' => $relPath, 'reason' => $res['message']];
                if ($type === 'PATCH' && !empty($res['stageable'])) {
                    $fullPath = $this->resolveSafePath($relPath);
                    $sha1 = $fullPath !== null ? PatcherStaging::hashFile($fullPath) : null;
                    if ($sha1 !== null) {
                        $stagedFiles[$relPath] = [
                            'sha1'        => $sha1,
                            'hunks'       => $op['hunks'],
                            'failed_hunk' => (int) ($res['failed_hunk'] ?? 1),
                        ];
                    }
                }
            }
        }

        $stagedBatchId = null;
        if ($stagedFiles !== [] && PatcherStaging::saveBatch($this->stagingDir, $this->timestamp, $this->projectRoot, $stagedFiles)) {
            $stagedBatchId = $this->timestamp;
        }

        return [
            'timestamp'    => $this->timestamp,
            'applied'      => $applied,
            'warnings'     => $warnings,
            'errors'       => $errors,
            'staged_batch' => $stagedBatchId,
            'staged_files' => array_keys($stagedFiles),
            'read_output'  => $this->formatInspectionOutput($dumpFiles, $treeOutputs),
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function executeMove(string $srcRel, string $dstRel): array
    {
        $srcFull = $this->resolveSafePath($srcRel);
        $dstFull = $this->resolveSafePath($dstRel);

        if ($srcFull === null || $dstFull === null) {
            return ['ok' => false, 'message' => 'Invalid or blocked path in MOVE.'];
        }
        if (!file_exists($srcFull)) {
            return ['ok' => false, 'message' => 'Source path does not exist for MOVE.'];
        }
        if (file_exists($dstFull)) {
            return ['ok' => false, 'message' => "Destination already exists: {$dstRel}"];
        }

        $dstDir = dirname($dstFull);
        if (!is_dir($dstDir) && !mkdir($dstDir, 0775, true) && !is_dir($dstDir)) {
            return ['ok' => false, 'message' => 'Failed to create destination directory.'];
        }

        if (!rename($srcFull, $dstFull)) {
            return ['ok' => false, 'message' => 'Failed to execute rename() for MOVE.'];
        }

        return ['ok' => true, 'message' => "MOVED: {$srcRel} -> {$dstRel}"];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function executePrefixRename(string $relPath, string $prefix, string $verb): array
    {
        $fullPath = $this->resolveSafePath($relPath);
        if ($fullPath === null) {
            return ['ok' => false, 'message' => "Invalid or blocked path for {$verb}."];
        }
        if (!file_exists($fullPath)) {
            return ['ok' => false, 'message' => "Target does not exist for {$verb}."];
        }

        $targetPath = $this->buildPrefixedPath($fullPath, $prefix);
        if (!rename($fullPath, $targetPath)) {
            return ['ok' => false, 'message' => "Failed to rename target with {$prefix} prefix."];
        }

        return ['ok' => true, 'message' => "{$verb}: {$relPath} -> " . basename($targetPath)];
    }

    /**
     * @param array<string, mixed> $op
     * @return array{ok: bool, message: string}
     */
    private function executeWriteOp(string $relPath, array $op): array
    {
        $fullPath = $this->resolveSafePath($relPath);
        if ($fullPath === null) {
            return ['ok' => false, 'message' => 'Invalid or blocked path.'];
        }

        $existed = is_file($fullPath);
        $backupPath = null;

        if ($existed) {
            $bak = j0_backup_rotate($fullPath, "_\u{1F4E6}", $this->timestamp, false);
            if ($bak === false) {
                return ['ok' => false, 'message' => 'Failed to create _📦 backup before modification.'];
            }
            $backupPath = is_string($bak) ? $bak : null;
        }

        $buffer = $existed ? (string) file_get_contents($fullPath) : '';
        $buffer = str_replace(["\r\n", "\r"], "\n", $buffer);

        if ($op['type'] === 'CREATE') {
            $buffer = (string) $op['content'];
        } elseif ($op['type'] === 'PATCH') {
            if (!$existed && $buffer === '') {
                return ['ok' => false, 'message' => 'Target file does not exist for PATCH.'];
            }
            foreach ($op['hunks'] as $idx => $hunk) {
                $patchRes = $this->applyHunk(
                    $buffer,
                    (string) $hunk['start'],
                    (string) $hunk['end'],
                    (string) $hunk['replace'],
                    $idx + 1,
                    (int) ($hunk['start_match'] ?? 0),
                    (int) ($hunk['end_match'] ?? 0)
                );
                if (!$patchRes['ok']) {
                    $this->rollbackSingle($fullPath, $backupPath, $existed);
                    return [
                        'ok'          => false,
                        'message'     => $patchRes['message'],
                        'stageable'   => true,
                        'failed_hunk' => $idx + 1,
                    ];
                }
                $buffer = $patchRes['content'];
            }
        }

        $dir = dirname($fullPath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->rollbackSingle($fullPath, $backupPath, $existed);
            return ['ok' => false, 'message' => 'Failed to create target directory.'];
        }

        $tmpFile = $dir . '/.__tmp_' . uniqid('', true);
        if (file_put_contents($tmpFile, $buffer) === false || !rename($tmpFile, $fullPath)) {
            @unlink($tmpFile);
            $this->rollbackSingle($fullPath, $backupPath, $existed);
            return ['ok' => false, 'message' => 'Atomic disk write failed.'];
        }

        if (str_ends_with(strtolower($fullPath), '.php') && $this->phpCli !== null) {
            $lintOut = [];
            $lintCode = 0;
            exec(escapeshellarg($this->phpCli) . ' -l ' . escapeshellarg($fullPath) . ' 2>&1', $lintOut, $lintCode);
            if ($lintCode !== 0) {
                $this->rollbackSingle($fullPath, $backupPath, $existed);
                return ['ok' => false, 'message' => 'PHP syntax error: ' . implode(' ', $lintOut)];
            }
        }

        $tag = $existed ? 'UPDATED' : 'CREATED';
        return ['ok' => true, 'message' => "{$tag}: {$relPath}"];
    }

    /**
     * Resolves exact anchor spans first, falling back to unique line-trimmed spans
     * and reporting the closest line on disk if no match exists.
     *
     * @return array{ok: bool, content: string, message: string}
     */
    /**
     * @param array<string, mixed> $op
     * @return array{applied: array<int, string>, errors: array<int, array{path: string, reason: string}>}
     */
    private function executeResolve(array $op): array
    {
        $batchId = (string) ($op['batch_id'] ?? '');
        $entries = is_array($op['entries'] ?? null) ? $op['entries'] : [];
        $batch = PatcherStaging::loadBatch($this->stagingDir, $batchId);
        if ($batch === null) {
            return ['applied' => [], 'errors' => [['path' => $batchId, 'reason' => "Staged batch not found or expired: {$batchId}"]]];
        }

        $byFile = [];
        foreach ($entries as $entry) {
            $byFile[(string) $entry['path']][] = $entry;
        }

        $applied = [];
        $errors = [];
        foreach ($byFile as $relPath => $fileEntries) {
            if (!isset($batch['files'][$relPath])) {
                $errors[] = ['path' => $relPath, 'reason' => "File not found in staged batch {$batchId}."];
                continue;
            }
            $fullPath = $this->resolveSafePath($relPath);
            $curHash = $fullPath !== null ? PatcherStaging::hashFile($fullPath) : null;
            $expectedHash = (string) ($batch['files'][$relPath]['sha1'] ?? '');
            if ($curHash === null || $curHash !== $expectedHash) {
                $errors[] = ['path' => $relPath, 'reason' => "File changed on disk since batch {$batchId} (SHA-1 mismatch). Re-issue full PATCH."];
                continue;
            }

            $hunks = $batch['files'][$relPath]['hunks'];
            $invalidIdx = false;
            foreach ($fileEntries as $fe) {
                $hIdx = ((int) $fe['hunk']) - 1;
                if (!isset($hunks[$hIdx])) {
                    $errors[] = ['path' => $relPath, 'reason' => "Hunk #{$fe['hunk']} does not exist in staged batch {$batchId}."];
                    $invalidIdx = true;
                    break;
                }
                if ((int) ($fe['start_match'] ?? 0) > 0) {
                    $hunks[$hIdx]['start_match'] = (int) $fe['start_match'];
                }
                if ((int) ($fe['end_match'] ?? 0) > 0) {
                    $hunks[$hIdx]['end_match'] = (int) $fe['end_match'];
                }
            }
            if ($invalidIdx) {
                continue;
            }

            $res = $this->executeWriteOp($relPath, ['type' => 'PATCH', 'path' => $relPath, 'hunks' => $hunks]);
            if ($res['ok']) {
                $applied[] = $res['message'] . " (resolved {$batchId})";
                PatcherStaging::removeFile($this->stagingDir, $batchId, $relPath);
            } else {
                $batch['files'][$relPath]['hunks'] = $hunks;
                PatcherStaging::saveBatch($this->stagingDir, $batchId, $this->projectRoot, $batch['files']);
                $errors[] = ['path' => $relPath, 'reason' => $res['message']];
            }
        }

        return ['applied' => $applied, 'errors' => $errors];
    }

    /**
     * @param array<string, mixed> $op
     * @return array{ok: bool, message: string}
     */
    private function executeRetryHunk(array $op): array
    {
        $batchId = (string) ($op['batch_id'] ?? '');
        $relPath = (string) ($op['path'] ?? '');
        $hunkNum = (int) ($op['hunk'] ?? 0);
        $hunkDef = is_array($op['hunk_def'] ?? null) ? $op['hunk_def'] : null;

        $batch = PatcherStaging::loadBatch($this->stagingDir, $batchId);
        if ($batch === null) {
            return ['ok' => false, 'message' => "Staged batch not found or expired: {$batchId}"];
        }
        if (!isset($batch['files'][$relPath])) {
            return ['ok' => false, 'message' => "File not found in staged batch {$batchId}."];
        }
        $hIdx = $hunkNum - 1;
        if ($hunkDef === null || !isset($batch['files'][$relPath]['hunks'][$hIdx])) {
            return ['ok' => false, 'message' => "Hunk #{$hunkNum} does not exist in staged batch {$batchId}."];
        }

        $fullPath = $this->resolveSafePath($relPath);
        $curHash = $fullPath !== null ? PatcherStaging::hashFile($fullPath) : null;
        $expectedHash = (string) ($batch['files'][$relPath]['sha1'] ?? '');
        if ($curHash === null || $curHash !== $expectedHash) {
            return ['ok' => false, 'message' => "File changed on disk since batch {$batchId} (SHA-1 mismatch). Re-issue full PATCH."];
        }

        $hunks = $batch['files'][$relPath]['hunks'];
        $hunks[$hIdx] = $hunkDef;

        $res = $this->executeWriteOp($relPath, ['type' => 'PATCH', 'path' => $relPath, 'hunks' => $hunks]);
        if ($res['ok']) {
            PatcherStaging::removeFile($this->stagingDir, $batchId, $relPath);
            return ['ok' => true, 'message' => $res['message'] . " (retried {$batchId} #{$hunkNum})"];
        }

        $batch['files'][$relPath]['hunks'] = $hunks;
        PatcherStaging::saveBatch($this->stagingDir, $batchId, $this->projectRoot, $batch['files']);
        return ['ok' => false, 'message' => $res['message']];
    }

    /**
     * Simulates RESOLVE or RETRY_HUNK in memory without modifying disk or staging files.
     *
     * @param array<string, mixed> $op
     * @return array{applied: array<int, string>, errors: array<int, array{path: string, reason: string}>}
     */
    private function simulateStagedOp(array $op): array
    {
        $type = (string) ($op['type'] ?? '');
        $batchId = (string) ($op['batch_id'] ?? '');
        $batch = PatcherStaging::loadBatch($this->stagingDir, $batchId);
        if ($batch === null) {
            return ['applied' => [], 'errors' => [['path' => $batchId, 'reason' => "Staged batch not found or expired: {$batchId}"]]];
        }

        $targets = [];
        if ($type === 'RETRY_HUNK') {
            $rel = (string) ($op['path'] ?? '');
            $hIdx = ((int) ($op['hunk'] ?? 0)) - 1;
            if (!isset($batch['files'][$rel]['hunks'][$hIdx])) {
                return ['applied' => [], 'errors' => [['path' => $rel, 'reason' => "Hunk #{$op['hunk']} not found in staged batch {$batchId}."]]];
            }
            $hunks = $batch['files'][$rel]['hunks'];
            $hunks[$hIdx] = $op['hunk_def'];
            $targets[$rel] = $hunks;
        } else {
            foreach ((array) ($op['entries'] ?? []) as $fe) {
                $rel = (string) $fe['path'];
                if (!isset($targets[$rel])) {
                    if (!isset($batch['files'][$rel])) {
                        return ['applied' => [], 'errors' => [['path' => $rel, 'reason' => "File not found in staged batch {$batchId}."]]];
                    }
                    $targets[$rel] = $batch['files'][$rel]['hunks'];
                }
                $hIdx = ((int) $fe['hunk']) - 1;
                if (!isset($targets[$rel][$hIdx])) {
                    return ['applied' => [], 'errors' => [['path' => $rel, 'reason' => "Hunk #{$fe['hunk']} not found in staged batch {$batchId}."]]];
                }
                if ((int) ($fe['start_match'] ?? 0) > 0) {
                    $targets[$rel][$hIdx]['start_match'] = (int) $fe['start_match'];
                }
                if ((int) ($fe['end_match'] ?? 0) > 0) {
                    $targets[$rel][$hIdx]['end_match'] = (int) $fe['end_match'];
                }
            }
        }

        $applied = [];
        $errors = [];
        foreach ($targets as $relPath => $hunks) {
            $fullPath = $this->resolveSafePath($relPath);
            $curHash = $fullPath !== null ? PatcherStaging::hashFile($fullPath) : null;
            $expectedHash = (string) ($batch['files'][$relPath]['sha1'] ?? '');
            if ($fullPath === null || $curHash === null || $curHash !== $expectedHash) {
                $errors[] = ['path' => $relPath, 'reason' => "File changed on disk since batch {$batchId} (SHA-1 mismatch)."];
                continue;
            }
            $buffer = str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($fullPath));
            $failed = false;
            foreach ($hunks as $idx => $hunk) {
                $patchRes = $this->applyHunk(
                    $buffer,
                    (string) $hunk['start'],
                    (string) $hunk['end'],
                    (string) $hunk['replace'],
                    $idx + 1,
                    (int) ($hunk['start_match'] ?? 0),
                    (int) ($hunk['end_match'] ?? 0)
                );
                if (!$patchRes['ok']) {
                    $errors[] = ['path' => $relPath, 'reason' => $patchRes['message']];
                    $failed = true;
                    break;
                }
                $buffer = $patchRes['content'];
            }
            if (!$failed) {
                $applied[] = "DRY-RUN {$type}: {$relPath} (" . strlen($buffer) . ' bytes)';
            }
        }

        return ['applied' => $applied, 'errors' => $errors];
    }

    private function applyHunk(
        string $buffer,
        string $start,
        string $end,
        string $replace,
        int $num,
        int $startMatch = 0,
        int $endMatch = 0
    ): array {
        if ($start === '') {
            return ['ok' => false, 'content' => $buffer, 'message' => "Hunk #{$num}: empty START anchor."];
        }

        $startSpan = $this->resolveSpan($buffer, $start, "Hunk #{$num}: START", $startMatch, 0);
        if (!$startSpan['ok']) {
            return ['ok' => false, 'content' => $buffer, 'message' => $startSpan['message']];
        }

        $posStart = $startSpan['start'];
        $startEnd = $startSpan['end'];

        if ($end === '') {
            $newBuffer = substr($buffer, 0, $posStart) . $replace . substr($buffer, $startEnd);
            return ['ok' => true, 'content' => $newBuffer, 'message' => 'OK'];
        }

        $tail = substr($buffer, $startEnd);
        $lineOffset = substr_count(substr($buffer, 0, $startEnd), "\n");
        $endSpan = $this->resolveSpan($tail, $end, "Hunk #{$num}: END", $endMatch, $lineOffset);
        if (!$endSpan['ok']) {
            return ['ok' => false, 'content' => $buffer, 'message' => $endSpan['message']];
        }

        $endAbsoluteEnd = $startEnd + $endSpan['end'];
        $newBuffer = substr($buffer, 0, $posStart) . $replace . substr($buffer, $endAbsoluteEnd);

        return ['ok' => true, 'content' => $newBuffer, 'message' => 'OK'];
    }

    /**
     * @return array{ok: bool, start: int, end: int, message: string}
     */
    private function resolveSpan(
        string $haystack,
        string $needle,
        string $label,
        int $matchIndex = 0,
        int $lineOffset = 0
    ): array {
        $exactMatches = [];
        $cursor = 0;
        $needleLen = strlen($needle);
        if ($needleLen > 0) {
            while (($pos = strpos($haystack, $needle, $cursor)) !== false) {
                $lineNum = $lineOffset + substr_count(substr($haystack, 0, $pos), "\n") + 1;
                $exactMatches[] = [
                    'start' => $pos,
                    'end'   => $pos + $needleLen,
                    'line'  => $lineNum,
                ];
                $cursor = $pos + 1;
            }
        }

        $exactCount = count($exactMatches);
        if ($exactCount === 1 && $matchIndex <= 1) {
            return ['ok' => true, 'start' => $exactMatches[0]['start'], 'end' => $exactMatches[0]['end'], 'message' => 'OK'];
        }
        if ($exactCount > 1) {
            if ($matchIndex > 0) {
                if ($matchIndex <= $exactCount) {
                    $chosen = $exactMatches[$matchIndex - 1];
                    return ['ok' => true, 'start' => $chosen['start'], 'end' => $chosen['end'], 'message' => 'OK'];
                }
                return ['ok' => false, 'start' => 0, 'end' => 0, 'message' => "{$label} match #{$matchIndex} out of range (only {$exactCount} matches)."];
            }
            $bufLines = $this->indexLines($haystack, $lineOffset);
            $preview = $this->formatAmbiguousMatches($exactMatches, $bufLines);
            return ['ok' => false, 'start' => 0, 'end' => 0, 'message' => "{$label} anchor ambiguous ({$exactCount} matches: {$preview})."];
        }

        $cleanNeedle = rtrim($needle, "\n");
        $needleLines = explode("\n", $cleanNeedle);
        $needleCount = count($needleLines);
        if ($needleCount === 0) {
            return ['ok' => false, 'start' => 0, 'end' => 0, 'message' => "{$label} anchor not found."];
        }

        $bufLines = $this->indexLines($haystack, $lineOffset);
        $bufCount = count($bufLines);
        $matches = [];

        for ($i = 0; $i <= $bufCount - $needleCount; $i++) {
            $allMatch = true;
            for ($k = 0; $k < $needleCount; $k++) {
                if (trim($bufLines[$i + $k]['text']) !== trim($needleLines[$k])) {
                    $allMatch = false;
                    break;
                }
            }
            if ($allMatch) {
                $first = $bufLines[$i];
                $last = $bufLines[$i + $needleCount - 1];
                $matches[] = [
                    'start' => $first['offset'],
                    'end'   => $last['offset'] + $last['len'],
                    'line'  => $first['line'],
                ];
            }
        }

        $trimCount = count($matches);
        if ($trimCount === 1 && $matchIndex <= 1) {
            return ['ok' => true, 'start' => $matches[0]['start'], 'end' => $matches[0]['end'], 'message' => 'OK'];
        }
        if ($trimCount > 1) {
            if ($matchIndex > 0) {
                if ($matchIndex <= $trimCount) {
                    $chosen = $matches[$matchIndex - 1];
                    return ['ok' => true, 'start' => $chosen['start'], 'end' => $chosen['end'], 'message' => 'OK'];
                }
                return ['ok' => false, 'start' => 0, 'end' => 0, 'message' => "{$label} trimmed match #{$matchIndex} out of range (only {$trimCount} matches)."];
            }
            $preview = $this->formatAmbiguousMatches($matches, $bufLines);
            return ['ok' => false, 'start' => 0, 'end' => 0, 'message' => "{$label} anchor ambiguous ({$trimCount} trimmed matches: {$preview})."];
        }

        $hint = $this->findClosestLineHint($bufLines, $needleLines[0]);
        return ['ok' => false, 'start' => 0, 'end' => 0, 'message' => "{$label} anchor not found.{$hint}"];
    }

    /**
     * @param array<int, array{start: int, end: int, line: int}> $matches
     * @param array<int, array{line: int, offset: int, len: int, text: string}> $bufLines
     */
    private function formatAmbiguousMatches(array $matches, array $bufLines): string
    {
        $lineMap = [];
        foreach ($bufLines as $entry) {
            $lineMap[$entry['line']] = trim($entry['text']);
        }
        $parts = [];
        foreach (array_slice($matches, 0, 5) as $idx => $m) {
            $ln = $m['line'];
            $prevText = $lineMap[$ln - 1] ?? '';
            $curText = $lineMap[$ln] ?? '';
            $snippet = strlen($curText) > 64 ? (substr($curText, 0, 61) . '...') : $curText;
            $ctxPrev = $prevText !== '' ? (' [after: ' . (strlen($prevText) > 36 ? (substr($prevText, 0, 33) . '...') : $prevText) . ']') : '';
            $parts[] = '#' . ($idx + 1) . ' L' . $ln . ': ' . $snippet . $ctxPrev;
        }
        return implode(' | ', $parts);
    }

    /**
     * @return array<int, array{line: int, offset: int, len: int, text: string}>
     */
    private function indexLines(string $text, int $lineOffset = 0): array
    {
        $rawLines = explode("\n", $text);
        $indexed = [];
        $offset = 0;
        foreach ($rawLines as $idx => $line) {
            $len = strlen($line);
            $indexed[] = [
                'line'   => $lineOffset + $idx + 1,
                'offset' => $offset,
                'len'    => $len,
                'text'   => $line,
            ];
            $offset += $len + 1;
        }
        return $indexed;
    }

    /**
     * @param array<int, array{line: int, offset: int, len: int, text: string}> $bufLines
     */
    private function findClosestLineHint(array $bufLines, string $targetLine): string
    {
        $needle = trim($targetLine);
        if ($needle === '' || $bufLines === []) {
            return '';
        }

        $bestPct = 0.0;
        $bestIdx = -1;
        foreach ($bufLines as $idx => $entry) {
            $cand = trim($entry['text']);
            if ($cand === '') {
                continue;
            }
            $pct = 0.0;
            similar_text($needle, $cand, $pct);
            if ($pct > $bestPct) {
                $bestPct = $pct;
                $bestIdx = $idx;
            }
        }

        if ($bestIdx < 0 || $bestPct < 45.0) {
            return '';
        }

        $from = max(0, $bestIdx - 1);
        $to = min(count($bufLines) - 1, $bestIdx + 1);
        $ctxLines = [];
        for ($k = $from; $k <= $to; $k++) {
            $ctxLines[] = 'L' . $bufLines[$k]['line'] . ': ' . $bufLines[$k]['text'];
        }

        return ' Closest line ' . $bufLines[$bestIdx]['line'] . ":\n  " . implode("\n  ", $ctxLines);
    }

    private function rollbackSingle(string $fullPath, ?string $backupPath, bool $existed): void
    {
        if ($existed && $backupPath !== null && is_file($backupPath)) {
            copy($backupPath, $fullPath);
            unlink($backupPath);
        } elseif (!$existed && is_file($fullPath)) {
            unlink($fullPath);
        }
    }

    /**
     * Collects read-only DUMP files or TREE listings without touching disk.
     *
     * @param array<int, string> $dumpFiles
     * @param array<int, string> $treeOutputs
     * @return array{ok: bool, message: string}
     */
    private function collectInspection(string $type, string $relPath, array &$dumpFiles, array &$treeOutputs): array
    {
        $bs = chr(92);
        $trimmed = trim(str_replace($bs, '/', $relPath));
        if ($trimmed === '.' || $trimmed === './' || $trimmed === '/') {
            $clean = '';
            $fullPath = $this->projectRoot;
        } else {
            $clean = trim($trimmed, '/');
            $fullPath = $this->resolveSafePath($clean);
        }

        if ($fullPath === null || !file_exists($fullPath)) {
            return ['ok' => false, 'message' => "Target does not exist for {$type}: {$relPath}"];
        }

        if ($type === 'TREE') {
            $label = $clean === '' ? '.' : $clean;
            $lines = ["// ========== DIRECTORY TREE ({$label}) =========="];
            if (is_file($fullPath)) {
                $lines[] = '└── ' . basename($fullPath);
            } else {
                $this->appendAsciiTree($fullPath, '', $lines);
            }
            $treeOutputs[] = implode("\n", $lines);
            return ['ok' => true, 'message' => "TREE: {$label}"];
        }

        if (is_file($fullPath)) {
            $dumpFiles[$clean] = $clean;
            return ['ok' => true, 'message' => "DUMP: {$clean}"];
        }

        $countBefore = count($dumpFiles);
        $this->collectDirFilesForDump($fullPath, $dumpFiles);
        $added = count($dumpFiles) - $countBefore;
        $label = $clean === '' ? '.' : $clean;
        return ['ok' => true, 'message' => "DUMP DIR: {$label} ({$added} files)"];
    }

    /**
     * @param array<string, string> $dumpFiles
     */
    private function collectDirFilesForDump(string $dirAbs, array &$dumpFiles): void
    {
        $entries = @scandir($dirAbs);
        if (!is_array($entries)) {
            return;
        }
        natcasesort($entries);
        $bs = chr(92);
        $baseLen = strlen($this->projectRoot) + 1;
        $skipDirs = ['.git', 'node_modules', 'vendor', '_j0-temp'];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (str_starts_with($entry, "_\u{1F4E6}") || str_starts_with($entry, "_\u{1F5D1}")) {
                continue;
            }
            $full = $dirAbs . '/' . $entry;
            if (is_link($full)) {
                continue;
            }
            if (is_dir($full)) {
                if (in_array($entry, $skipDirs, true)) {
                    continue;
                }
                $this->collectDirFilesForDump($full, $dumpFiles);
            } elseif (is_file($full)) {
                $rel = substr(str_replace($bs, '/', $full), $baseLen);
                $dumpFiles[$rel] = $rel;
            }
        }
    }

    /**
     * @param array<int, string> $lines
     */
    private function appendAsciiTree(string $dirAbs, string $indent, array &$lines, int $depth = 0): void
    {
        if ($depth > 8) {
            return;
        }
        $entries = @scandir($dirAbs);
        if (!is_array($entries)) {
            return;
        }
        $skipDirs = ['.git', 'node_modules', 'vendor', '_j0-temp'];
        $filtered = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $skipDirs, true)) {
                continue;
            }
            $filtered[] = $entry;
        }
        natcasesort($filtered);
        $filtered = array_values($filtered);
        $total = count($filtered);

        foreach ($filtered as $idx => $entry) {
            $isLast = ($idx === $total - 1);
            $branch = $isLast ? '└── ' : '├── ';
            $full = $dirAbs . '/' . $entry;
            $lines[] = $indent . $branch . $entry;
            if (is_dir($full) && !is_link($full)) {
                $nextIndent = $indent . ($isLast ? '    ' : '│   ');
                $this->appendAsciiTree($full, $nextIndent, $lines, $depth + 1);
            }
        }
    }

    /**
     * @param array<string, string> $dumpFiles
     * @param array<int, string> $treeOutputs
     */
    private function formatInspectionOutput(array $dumpFiles, array $treeOutputs): string
    {
        $chunks = [];
        if ($treeOutputs !== []) {
            $chunks[] = implode("\n\n", $treeOutputs);
        }
        if ($dumpFiles !== []) {
            $binaryExts = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'pdf', 'zip', 'gz', 'tar', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'mp3', 'mp4', 'mov', 'sqlite', 'db'];
            $chunks[] = DumperFormatter::buildDump($this->projectRoot, array_values($dumpFiles), $binaryExts, false);
        }
        return implode("\n\n", $chunks);
    }

    private function resolveSafePath(string $relPath): ?string
    {
        $bs = chr(92);
        $clean = ltrim(str_replace($bs, '/', $relPath), '/');
        if ($clean === '' || str_contains($clean, '..')) {
            return null;
        }
        $firstSegment = explode('/', $clean)[0];
        if (in_array($firstSegment, ['.git', 'node_modules', 'vendor'], true)) {
            return null;
        }
        return $this->projectRoot . '/' . $clean;
    }

    private function buildPrefixedPath(string $fullPath, string $prefix): string
    {
        $dir = dirname($fullPath);
        $base = basename($fullPath);
        $candidate = "{$dir}/{$prefix}{$base}";
        if (file_exists($candidate)) {
            $info = pathinfo($fullPath);
            $ext = isset($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
            return "{$dir}/{$prefix}{$info['filename']}_{$this->timestamp}{$ext}";
        }
        return $candidate;
    }

    private function lintPhpContent(string $code): ?string
    {
        if ($this->phpCli === null) {
            return null;
        }
        $tmpFile = rtrim(sys_get_temp_dir(), '/\\') . '/ai0_dryrun_' . uniqid('', true) . '.php';
        if (@file_put_contents($tmpFile, $code) === false) {
            return null;
        }
        $lintOut = [];
        $lintCode = 0;
        exec(escapeshellarg($this->phpCli) . ' -l ' . escapeshellarg($tmpFile) . ' 2>&1', $lintOut, $lintCode);
        @unlink($tmpFile);
        if ($lintCode !== 0) {
            return 'PHP syntax error: ' . implode(' ', $lintOut);
        }
        return null;
    }

    private function resolvePhpCli(): ?string
    {
        $candidates = [
            str_replace('/sbin/php-fpm', '/bin/php', PHP_BINARY),
            '/opt/homebrew/bin/php',
            '/usr/local/bin/php',
            '/usr/bin/php',
        ];
        foreach ($candidates as $bin) {
            if (is_string($bin) && is_executable($bin) && !str_ends_with($bin, 'php-fpm')) {
                return $bin;
            }
        }
        return null;
    }
}
