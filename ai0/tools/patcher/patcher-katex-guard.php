<?php
// PATH: ai0/tools/patcher/patcher-katex-guard.php
declare(strict_types=1);

final class PatcherKatexGuard
{
    /**
     * Scans a parsed operation for KaTeX math-parser corruption signatures.
     * Skips Markdown and LaTeX documentation files.
     *
     * @param array<string, mixed> $op
     * @return array<int, string>
     */
    public static function scanOperation(array $op): array
    {
        $type = (string) ($op['type'] ?? '');
        if ($type !== 'CREATE' && $type !== 'PATCH') {
            return [];
        }

        $relPath = (string) ($op['path'] ?? '');
        $ext = strtolower((string) pathinfo($relPath, PATHINFO_EXTENSION));
        if (in_array($ext, ['md', 'markdown', 'tex', 'latex'], true)) {
            return [];
        }

        if ($type === 'CREATE') {
            return self::scanText((string) ($op['content'] ?? ''), $relPath, 'CREATE');
        }

        $warnings = [];
        $hunks = isset($op['hunks']) && is_array($op['hunks']) ? $op['hunks'] : [];
        foreach ($hunks as $idx => $hunk) {
            $num = $idx + 1;
            $warnings = array_merge(
                $warnings,
                self::scanText((string) ($hunk['start'] ?? ''), $relPath, 'PATCH hunk #' . $num . ' START'),
                self::scanText((string) ($hunk['end'] ?? ''), $relPath, 'PATCH hunk #' . $num . ' END'),
                self::scanText((string) ($hunk['replace'] ?? ''), $relPath, 'PATCH hunk #' . $num . ' REPLACE')
            );
        }

        return $warnings;
    }

    /**
     * @return array<int, string>
     */
    public static function scanText(string $text, string $relPath, string $scope): array
    {
        if ($text === '') {
            return [];
        }

        $bs = chr(92);
        $patterns = [
            '/' . $bs . $bs . '(?:lVert|rVert|vert|mid)(?:\{\}|\b)/',
            '/(?<!' . $bs . $bs . ')' . $bs . $bs . '%/',
            '/(?<!' . $bs . $bs . ')' . $bs . $bs . '[&#_]/',
            '/' . $bs . $bs . '(?:text|mathrm|mathbf|mathit|operatorname|frac)\{/',
            '/' . $bs . $bs . '(?:cdot|times|leq|geq|neq)\b/',
        ];

        $warnings = [];
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
        foreach ($lines as $lineIdx => $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $m) === 1) {
                    $lineNum = $lineIdx + 1;
                    $warnings[] = 'WARNING [KaTeX corruption]: found "' . $m[0] . '" in provided code for ' . $relPath . ' (' . $scope . ', line ' . $lineNum . ').';
                    break;
                }
            }
        }

        return $warnings;
    }
}