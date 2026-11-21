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
        $bs2 = str_repeat(chr(92), 2);
        $patterns = [
            '/' . $bs2 . '(?:lVert|rVert|vert|mid)(?:\{\}|\b)/',
            '/(?<!' . $bs2 . ')' . $bs2 . '%/',
            '/(?<!' . $bs2 . ')' . $bs2 . '[&#_]/',
            '/' . $bs2 . '(?:text|mathrm|mathbf|mathit|operatorname|frac)\{/',
            '/' . $bs2 . '(?:cdot|times|leq|geq|neq)\b/',
        ];

        $warnings = [];
        $normalized = str_replace(
            ["\r\n", "\r"],
            "\n",
            $text
        );
        $lines = explode(
            "\n",
            $normalized
        );
        $lineCount = count($lines);
        $lineIdx = 0;
        while ($lineIdx < $lineCount) {
            $line = $lines[
                $lineIdx
            ];
            foreach (
                $patterns
                as $pattern
            ) {
                $matched = preg_match(
                    $pattern,
                    $line,
                    $m
                );
                if ($matched === 1) {
                    $lineNum = $lineIdx + 1;
                    $warnings[] = 'WARNING [KaTeX corruption]: found "'
                        . $m[0]
                        . '" in provided code for '
                        . $relPath
                        . ' ('
                        . $scope
                        . ', line '
                        . $lineNum
                        . ').';
                    break;
                }
            }
            $lineIdx++;
        }

        $ext = strtolower((string) pathinfo(
            $relPath,
            PATHINFO_EXTENSION
        ));
        if (in_array($ext, ['php', 'phtml', 'inc'], true)) {
            $tokenWarnings = self::scanPhpTokens(
                $normalized,
                $relPath,
                $scope
            );
            $warnings = array_merge(
                $warnings,
                $tokenWarnings
            );
        }

        return $warnings;
    }

    /**
     * Detects glued dollar variables and collapsed multi-statement lines caused by KaTeX inline math.
     *
     * @return array<int, string>
     */
    private static function scanPhpTokens(
        string $code,
        string $relPath,
        string $scope
    ): array {
        $hasOpenTag = preg_match(
            '/<\?(?:php|=|\s|\z)/i',
            $code
        ) === 1;
        $source = $hasOpenTag
            ? $code
            : "<?php\n" . $code;
        $lineOffset = $hasOpenTag ? 0 : 1;
        $tokens = @token_get_all($source);

        $allowedPrev = [
            chr(36), '&', '@', '(', '[', '{', '+', '-', '!', '~', '*', '/', '%', '"',
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
            T_DOUBLE_COLON,
            T_ELLIPSIS,
            T_CURLY_OPEN,
            T_DOLLAR_OPEN_CURLY_BRACES,
            T_INT_CAST,
            T_DOUBLE_CAST,
            T_STRING_CAST,
            T_ARRAY_CAST,
            T_OBJECT_CAST,
            T_BOOL_CAST,
            T_UNSET_CAST,
        ];
        if (defined('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG')) {
            array_push(
                $allowedPrev,
                constant('T_AMPERSAND_FOLLOWED_BY_VAR_OR_VARARG')
            );
        }
        if (defined('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG')) {
            array_push(
                $allowedPrev,
                constant('T_AMPERSAND_NOT_FOLLOWED_BY_VAR_OR_VARARG')
            );
        }

        $warnings = [];
        $flaggedGlued = [];
        $stmtCounts = [];
        $openBraceLines = [];
        $closeBraceLines = [];
        $parenDepth = 0;
        $prevSig = null;
        $currentLine = 1;

        foreach (
            $tokens
            as $tok
        ) {
            if (is_array($tok)) {
                $id = current(
                    $tok
                );
                $tokText = next(
                    $tok
                );
                $rawLine = next(
                    $tok
                );
                $currentLine = $rawLine
                    - $lineOffset;

                if (in_array($id, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG], true)) {
                    $prevSig = null;
                    continue;
                }

                if (
                    $id === T_VARIABLE
                    && $prevSig !== null
                    && !in_array($prevSig, $allowedPrev, true)
                    && $currentLine >= 1
                    && !isset($flaggedGlued[$currentLine])
                ) {
                    $flaggedGlued[
                        $currentLine
                    ] = true;
                    $warnings[] = 'WARNING [KaTeX formatting]: glued variable "'
                        . $tokText
                        . '" without leading space in '
                        . $relPath
                        . ' ('
                        . $scope
                        . ', line '
                        . $currentLine
                        . ').';
                }

                $prevSig =
                    $id;
                continue;
            }

            if ($tok === '(') {
                $parenDepth++;
            } elseif ($tok === ')') {
                $parenDepth = max(0, $parenDepth - 1);
            } elseif ($tok === '{') {
                if ($currentLine >= 1) {
                    $openBraceLines[
                        $currentLine
                    ] = true;
                }
            } elseif ($tok === '}') {
                if ($currentLine >= 1) {
                    $closeBraceLines[
                        $currentLine
                    ] = true;
                }
            } elseif ($tok === ';' && $parenDepth === 0) {
                if ($currentLine >= 1) {
                    $prevCount = $stmtCounts[
                        $currentLine
                    ] ?? 0;
                    $stmtCounts[
                        $currentLine
                    ] = $prevCount + 1;
                }
            }

            $prevSig =
                $tok;
        }

        $stmtLines = array_keys(
            $stmtCounts
        );
        foreach (
            $stmtLines
            as $lineNum
        ) {
            $count = $stmtCounts[
                $lineNum
            ];
            $hasOpen = isset(
                $openBraceLines[$lineNum]
            );
            $hasClose = isset(
                $closeBraceLines[$lineNum]
            );
            $isCollapsed = $count > 1;
            if (
                !$isCollapsed
                && $hasOpen
                && !$hasClose
            ) {
                $isCollapsed = true;
            }
            if ($isCollapsed) {
                $warnings[] = 'WARNING [KaTeX formatting]: collapsed multi-statement line in '
                    . $relPath
                    . ' ('
                    . $scope
                    . ', line '
                    . $lineNum
                    . ').';
            }
        }

        return $warnings;
    }
}