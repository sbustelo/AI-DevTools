<?php
// PATH: ai0-0010/tools/dumper/dumper-formatter.php
declare(strict_types=1);

final class DumperFormatter
{
    /**
     * Generates a single copy-ready context dump from selected relative paths.
     *
     * @param array<int, string> $selectedRelPaths
     * @param array<int, string> $binaryExts
     */
    public static function buildDump(string $projectRoot, array $selectedRelPaths, array $binaryExts, bool $compact = false): string
    {
        $rootClean = rtrim($projectRoot, '/');
        $validFiles = [];

        foreach ($selectedRelPaths as $rel) {
            $clean = ltrim(str_replace(chr(92), '/', $rel), '/');
            if ($clean === '' || str_contains($clean, '..')) {
                continue;
            }
            $abs = $rootClean . '/' . $clean;
            if (is_file($abs)) {
                $validFiles[] = $clean;
            } elseif (is_dir($abs)) {
                $iter = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                $baseLen = strlen($rootClean) + 1;
                /** @var SplFileInfo $f */
                foreach ($iter as $f) {
                    if ($f->isFile()) {
                        $validFiles[] = substr($f->getPathname(), $baseLen);
                    }
                }
            }
        }

        $validFiles = array_values(array_unique($validFiles));
        sort($validFiles, SORT_NATURAL);

        $out = [];
        $out[] = '// ********************';
        $out[] = '// PROJECT DUMP START';
        $out[] = '// Included files: ' . count($validFiles);
        if ($compact) {
            $out[] = '// Compact mode: active';
        }
        $out[] = '// ********************';
        $out[] = '';
        $out[] = '// ========== DIRECTORY TREE ==========';
        $out[] = '';
        $out[] = self::renderAsciiTree($validFiles);
        $out[] = '';
        $out[] = '// ========== FILE CONTENTS ==========';
        $out[] = '';

        foreach ($validFiles as $rel) {
            $abs = $rootClean . '/' . $rel;
            $out[] = '// PATH: ./' . $rel;
            $out[] = '';

            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            if (in_array($ext, $binaryExts, true)) {
                $out[] = self::binarySummary($abs);
            } else {
                $raw = (string) file_get_contents($abs);
                $out[] = $compact ? self::compactCode($raw) : rtrim($raw);
            }

            $out[] = '';
            $out[] = '---';
            $out[] = '';
        }

        $out[] = '// ********************';
        $out[] = '// PROJECT DUMP END';
        $out[] = '// ********************';

        return implode("\n", $out) . "\n";
    }

    /**
     * @param array<int, string> $relPaths
     */
    private static function renderAsciiTree(array $relPaths): string
    {
        $tree = [];
        foreach ($relPaths as $path) {
            $parts = explode('/', $path);
            $cursor = &$tree;
            foreach ($parts as $part) {
                if (!isset($cursor[$part])) {
                    $cursor[$part] = [];
                }
                $cursor = &$cursor[$part];
            }
            unset($cursor);
        }

        return self::drawBranch($tree, '');
    }

    /**
     * @param array<string, mixed> $branch
     */
    private static function drawBranch(array $branch, string $prefix): string
    {
        $keys = array_keys($branch);
        $total = count($keys);
        $lines = [];

        foreach ($keys as $idx => $name) {
            $isLast = ($idx === $total - 1);
            $connector = $isLast ? '└── ' : '├── ';
            $lines[] = $prefix . $connector . $name;

            if ($branch[$name] !== []) {
                $nextPrefix = $prefix . ($isLast ? '    ' : '│   ');
                $lines[] = self::drawBranch($branch[$name], $nextPrefix);
            }
        }

        return implode("\n", $lines);
    }

    private static function binarySummary(string $absPath): string
    {
        $size = (int) @filesize($absPath);
        $sizeLabel = $size > 1048576
            ? round($size / 1048576, 1) . 'MB'
            : ($size > 1024 ? round($size / 1024) . 'KB' : $size . 'B');
        $mime = function_exists('mime_content_type') ? (string) @mime_content_type($absPath) : '';
        $prefix = ($mime !== '' && $mime !== 'application/octet-stream') ? $mime . ', ' : '';
        return "[binary file ({$prefix}{$sizeLabel})]";
    }

    private static function compactCode(string $code): string
    {
        $clean = preg_replace('/\n{3,}/', "\n\n", str_replace(["\r\n", "\r"], "\n", $code));
        return trim((string) $clean);
    }
}