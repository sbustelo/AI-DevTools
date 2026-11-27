<?php
// PATH: ai0/tools/patcher/patcher-parser.php
declare(strict_types=1);

final class PatcherParser
{
    /**
     * Parses CREATE, PATCH (1-Line First), MOVE, DISABLE, DISCARD, DUMP, and TREE blocks.
     * Normalizes KaTeX-safe section markers (§) to dollar signs and tracks Markdown fences.
     *
     * @return array{operations: array<int, array<string, mixed>>, errors: array<int, string>}
     */
    public static function parse(string $rawInput): array
    {
        $withDollars = str_replace("\u{00A7}", chr(36), $rawInput);
        $normalized = str_replace(["\r\n", "\r"], "\n", $withDollars);
        $lines = explode("\n", $normalized);
        $operations = [];
        $errors = [];
        $declaredRoot = null;
        $total = count($lines);
        $i = 0;

        while ($i < $total) {
            $trimProbe = trim($lines[$i]);
            if (self::matchRootPragma($trimProbe) !== null || self::isAi0Pragma($trimProbe) || self::isOperationHeader($trimProbe)) {
                break;
            }
            $i++;
        }

        while ($i < $total) {
            $line = trim($lines[$i]);

            if ($line === '' || preg_match('/^`{3,}\z/', $line) === 1 || self::isAi0Pragma($line)) {
                $i++;
                continue;
            }

            $rootCandidate = self::matchRootPragma($line);
            if ($rootCandidate !== null) {
                $declaredRoot = $rootCandidate;
                $i++;
                continue;
            }

            if (preg_match('/^===\s*MOVE:\s*(.+?)\s*->\s*(.+?)\s*===\z/', $line, $mm)) {
                $operations[] = [
                    'type' => 'MOVE',
                    'path' => trim($mm[1]),
                    'dest' => trim($mm[2]),
                ];
                $i++;
                continue;
            }

            if (preg_match('/^===\s*(PATCH|CREATE|DISABLE|DISCARD|DUMP|TREE):\s*(.+?)\s*===\z/', $line, $m)) {
                $type = $m[1];
                $path = trim($m[2]);

                if ($type === 'DISABLE' || $type === 'DISCARD' || $type === 'DUMP' || $type === 'TREE') {
                    $operations[] = ['type' => $type, 'path' => $path];
                    $i++;
                    continue;
                }

                $i++;
                $blockLines = [];
                $closed = false;
                $inFence = false;
                $fenceLen = 0;

                while ($i < $total) {
                    $trimmedLine = trim($lines[$i]);
                    if ($type === 'CREATE' && preg_match('/^(`{3,})/', $trimmedLine, $fm)) {
                        $ticks = strlen($fm[1]);
                        if (!$inFence) {
                            $inFence = true;
                            $fenceLen = $ticks;
                        } elseif ($ticks >= $fenceLen && $trimmedLine === str_repeat('`', $ticks)) {
                            $inFence = false;
                            $fenceLen = 0;
                        }
                    }

                    if (!$inFence && $trimmedLine === '=== END ===') {
                        $closed = true;
                        $i++;
                        break;
                    }
                    $blockLines[] = $lines[$i];
                    $i++;
                }

                if (!$closed) {
                    $errors[] = "Block {$type} for '{$path}' missing closing '=== END ==='.";
                    continue;
                }

                if ($type === 'CREATE') {
                    $operations[] = [
                        'type'    => 'CREATE',
                        'path'    => $path,
                        'content' => implode("\n", $blockLines),
                    ];
                    continue;
                }

                $hunks = self::parseHunks($blockLines, $path, $errors);
                if ($hunks !== []) {
                    $operations[] = [
                        'type'  => 'PATCH',
                        'path'  => $path,
                        'hunks' => $hunks,
                    ];
                }
                continue;
            }

            $errors[] = 'Unexpected line outside _ai0 block (line ' . ($i + 1) . '): ' . $line;
            $i++;
        }

        return [
            'operations'    => $operations,
            'errors'        => $errors,
            'declared_root' => $declaredRoot,
        ];
    }

    /**
     * @param array<int, string> $lines
     * @param array<int, string> $errors
     * @return array<int, array{start: string, end: string, replace: string}>
     */
    private static function parseHunks(array $lines, string $path, array &$errors): array
    {
        $hunks = [];
        $state = 'IDLE';
        $buf = ['start' => [], 'end' => [], 'replace' => []];
        $total = count($lines);

        for ($idx = 0; $idx < $total; $idx++) {
            $line = $lines[$idx];
            $trimmed = trim($line);

            if ($trimmed === '<<< START') {
                if ($state === 'REPLACE') {
                    $hunks[] = self::flushHunk($buf);
                    $buf = ['start' => [], 'end' => [], 'replace' => []];
                } elseif ($state !== 'IDLE') {
                    $errors[] = "Incomplete hunk before new START in '{$path}' (state: {$state}).";
                    $buf = ['start' => [], 'end' => [], 'replace' => []];
                }
                $state = 'START';
                continue;
            }
            if ($trimmed === '<<< END') {
                if ($state === 'REPLACE') {
                    $nextTrimmed = ($idx + 1 < $total) ? trim($lines[$idx + 1]) : '';
                    if ($nextTrimmed === '' || $nextTrimmed === '<<< START') {
                        continue;
                    }
                }
                if ($state !== 'START') {
                    $errors[] = "Unexpected <<< END in '{$path}' outside START state.";
                }
                $state = 'END';
                continue;
            }
            if ($trimmed === '<<< REPLACE') {
                if ($state !== 'START' && $state !== 'END') {
                    $errors[] = "Unexpected <<< REPLACE in '{$path}' (state: {$state}).";
                }
                $state = 'REPLACE';
                continue;
            }
            if ($state === 'START') {
                $buf['start'][] = $line;
            } elseif ($state === 'END') {
                $buf['end'][] = $line;
            } elseif ($state === 'REPLACE') {
                $buf['replace'][] = $line;
            }
        }

        if ($state === 'REPLACE') {
            $hunks[] = self::flushHunk($buf);
        } elseif ($state !== 'IDLE') {
            $errors[] = "Incomplete hunk in PATCH for '{$path}' (final state: {$state}).";
        }

        return $hunks;
    }

    private static function isAi0Pragma(string $line): bool
    {
        return preg_match('/^===\s*AI0\s*===\z/i', $line) === 1;
    }

    private static function matchRootPragma(string $line): ?string
    {
        if (preg_match('/^===\s*(?:AI0:\s*)?ROOT:\s*(.+?)\s*===\z/i', $line, $rm) === 1) {
            $val = trim($rm[1]);
            return $val !== '' ? $val : null;
        }
        return null;
    }

    private static function isOperationHeader(string $line): bool
    {
        if (preg_match('/^===\s*MOVE:\s*(.+?)\s*->\s*(.+?)\s*===\z/', $line) === 1) {
            return true;
        }
        return preg_match('/^===\s*(PATCH|CREATE|DISABLE|DISCARD|DUMP|TREE):\s*(.+?)\s*===\z/', $line) === 1;
    }

    /**
     * @param array{start: array<int, string>, end: array<int, string>, replace: array<int, string>} $buf
     * @return array{start: string, end: string, replace: string}
     */
    private static function flushHunk(array $buf): array
    {
        return [
            'start'   => implode("\n", $buf['start']),
            'end'     => implode("\n", $buf['end']),
            'replace' => implode("\n", $buf['replace']),
        ];
    }
}
