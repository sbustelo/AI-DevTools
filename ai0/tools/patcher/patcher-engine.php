<?php
// PATH: ai0/tools/patcher/patcher-engine.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/j0/tooling/j0-backup.php';
require_once __DIR__ . '/patcher-katex-guard.php';

final class PatcherEngine
{
    private string $projectRoot;
    private string $timestamp;
    private ?string $phpCli;

    public function __construct(string $projectRoot, ?string $timestamp = null)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->timestamp = $timestamp ?? date('Y-m-d\TH-i-s');
        $this->phpCli = $this->resolvePhpCli();
    }

    /**
     * @param array<int, array<string, mixed>> $operations
     * @return array{timestamp: string, applied: array<int, string>, warnings: array<int, string>, errors: array<int, array{path: string, reason: string}>}
     */
    public function execute(array $operations): array
    {
        $applied = [];
        $warnings = [];
        $errors = [];

        foreach ($operations as $op) {
            $opWarnings = PatcherKatexGuard::scanOperation($op);
            if ($opWarnings !== []) {
                $warnings = array_merge($warnings, $opWarnings);
            }

            $type = (string) ($op['type'] ?? '');
            $relPath = (string) ($op['path'] ?? '');

            if ($type === 'MOVE') {
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
            }
        }

        return [
            'timestamp' => $this->timestamp,
            'applied'   => $applied,
            'warnings'  => $warnings,
            'errors'    => $errors,
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
                $patchRes = $this->applyHunk($buffer, $hunk['start'], $hunk['end'], $hunk['replace'], $idx + 1);
                if (!$patchRes['ok']) {
                    $this->rollbackSingle($fullPath, $backupPath, $existed);
                    return ['ok' => false, 'message' => $patchRes['message']];
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
    private function applyHunk(string $buffer, string $start, string $end, string $replace, int $num): array
    {
        if ($start === '') {
            return ['ok' => false, 'content' => $buffer, 'message' => "Hunk #{$num}: empty START anchor."];
        }

        $startSpan = $this->resolveSpan($buffer, $start, "Hunk #{$num}: START");
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
        $endSpan = $this->resolveSpan($tail, $end, "Hunk #{$num}: END");
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
    private function resolveSpan(string $haystack, string $needle, string $label): array
    {
        $exactCount = substr_count($haystack, $needle);
        if ($exactCount === 1) {
            $pos = (int) strpos($haystack, $needle);
            return ['ok' => true, 'start' => $pos, 'end' => $pos + strlen($needle), 'message' => 'OK'];
        }
        if ($exactCount > 1) {
            return ['ok' => false, 'start' => 0, 'end' => 0, 'message' => "{$label} anchor ambiguous ({$exactCount} matches)."];
        }

        $cleanNeedle = rtrim($needle, "\n");
        $needleLines = explode("\n", $cleanNeedle);
        $needleCount = count($needleLines);
        if ($needleCount === 0) {
            return ['ok' => false, 'start' => 0, 'end' => 0, 'message' => "{$label} anchor not found."];
        }

        $bufLines = $this->indexLines($haystack);
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
                ];
            }
        }

        if (count($matches) === 1) {
            return ['ok' => true, 'start' => $matches[0]['start'], 'end' => $matches[0]['end'], 'message' => 'OK'];
        }
        if (count($matches) > 1) {
            $found = count($matches);
            return ['ok' => false, 'start' => 0, 'end' => 0, 'message' => "{$label} anchor ambiguous ({$found} trimmed matches)."];
        }

        $hint = $this->findClosestLineHint($bufLines, $needleLines[0]);
        return ['ok' => false, 'start' => 0, 'end' => 0, 'message' => "{$label} anchor not found.{$hint}"];
    }

    /**
     * @return array<int, array{line: int, offset: int, len: int, text: string}>
     */
    private function indexLines(string $text): array
    {
        $rawLines = explode("\n", $text);
        $indexed = [];
        $offset = 0;
        foreach ($rawLines as $idx => $line) {
            $len = strlen($line);
            $indexed[] = [
                'line'   => $idx + 1,
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
        $bestLine = null;
        foreach ($bufLines as $entry) {
            $cand = trim($entry['text']);
            if ($cand === '') {
                continue;
            }
            $pct = 0.0;
            similar_text($needle, $cand, $pct);
            if ($pct > $bestPct) {
                $bestPct = $pct;
                $bestLine = $entry;
            }
        }

        if ($bestLine === null || $bestPct < 45.0) {
            return '';
        }

        return ' Closest line ' . $bestLine['line'] . ': ' . trim($bestLine['text']);
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
