<?php
// PATH: ai0/tools/i18n-batch/i18n-batch-engine.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

final class I18nBatchEngine
{
    /**
     * Discovers all directories containing an i18n/ folder under $projectRoot.
     *
     * @return array<int, string> Relative unit directory paths
     */
    public static function discoverUnits(string $projectRoot): array
    {
        $bs = chr(92);
        $rootClean = rtrim(str_replace($bs, '/', $projectRoot), '/');
        if (!is_dir($rootClean)) {
            return [];
        }

        $ignore = ['.git', 'node_modules', 'vendor', '_j0-temp'];
        $dirIter = new RecursiveDirectoryIterator($rootClean, FilesystemIterator::SKIP_DOTS);
        $filterIter = new RecursiveCallbackFilterIterator(
            $dirIter,
            static function (SplFileInfo $current) use ($ignore): bool {
                if ($current->isDir()) {
                    return !in_array($current->getFilename(), $ignore, true);
                }
                return false;
            }
        );

        $units = [];
        $baseLen = strlen($rootClean) + 1;
        $iter = new RecursiveIteratorIterator($filterIter, RecursiveIteratorIterator::SELF_FIRST);
        /** @var SplFileInfo $dir */
        foreach ($iter as $dir) {
            if ($dir->getFilename() !== 'i18n') {
                continue;
            }
            $unitAbs = str_replace($bs, '/', dirname($dir->getPathname()));
            $rel = $unitAbs === $rootClean ? '.' : substr($unitAbs, $baseLen);
            $units[] = $rel;
        }

        $units = array_values(array_unique($units));
        sort($units, SORT_NATURAL);
        return $units;
    }

    /**
     * Compares source catalog against target <lang>.json and validates {placeholder} integrity.
     *
     * @param array<string, mixed> $sourceCatalog
     * @param array<string, mixed> $targetDict
     * @return array{
     *   new: array<string, array<string, string>>,
     *   modified: array<string, array<string, string>>,
     *   active: array<int, string>,
     *   orphaned: array<int, string>,
     *   placeholder_warnings: array<int, string>
     * }
     */
    public static function diff(array $sourceCatalog, array $targetDict): array
    {
        $new = [];
        $modified = [];
        $active = [];
        $orphaned = [];
        $warnings = [];

        foreach ($sourceCatalog as $id => $rawEntry) {
            $entry = is_array($rawEntry) ? $rawEntry : ['source' => (string) $rawEntry];
            $srcText = (string) ($entry['source'] ?? $entry['text'] ?? '');
            $srcHash = (string) ($entry['source_hash'] ?? substr(sha1($srcText), 0, 12));

            if (!isset($targetDict[$id])) {
                $new[(string) $id] = ['source' => $srcText, 'source_hash' => $srcHash];
                continue;
            }

            $tRaw = $targetDict[$id];
            $tEntry = is_array($tRaw) ? $tRaw : ['text' => (string) $tRaw];
            $tHash = (string) ($tEntry['source_hash'] ?? '');
            $tText = (string) ($tEntry['text'] ?? $tEntry['rendered'] ?? '');

            $srcPh = self::extractPlaceholders($srcText);
            $tgtPh = self::extractPlaceholders($tText);
            if ($srcPh !== $tgtPh) {
                $warnings[] = "Placeholder mismatch on '{$id}': source=" . implode(',', $srcPh) . ' vs target=' . implode(',', $tgtPh);
            }

            if ($tHash !== $srcHash) {
                $modified[(string) $id] = [
                    'source'      => $srcText,
                    'source_hash' => $srcHash,
                    'prior_text'  => $tText,
                ];
            } else {
                $active[] = (string) $id;
            }
        }

        foreach (array_keys($targetDict) as $tId) {
            if (!isset($sourceCatalog[$tId])) {
                $orphaned[] = (string) $tId;
            }
        }

        return [
            'new'                  => $new,
            'modified'             => $modified,
            'active'               => $active,
            'orphaned'             => $orphaned,
            'placeholder_warnings' => $warnings,
        ];
    }

    /**
     * Builds a minimal delta prompt instructing an AI to emit an _ai0 block for new + modified keys.
     *
     * @param array<string, mixed> $diffResult
     */
    public static function buildDeltaPrompt(string $unitRel, string $targetLang, array $diffResult): string
    {
        $delta = array_merge($diffResult['new'], $diffResult['modified']);
        if ($delta === []) {
            return '';
        }

        $targetPath = ($unitRel === '.' ? '' : trim($unitRel, '/') . '/') . 'i18n/' . $targetLang . '.json';
        $jsonPayload = (string) json_encode($delta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $lines = [
            "Translate ONLY the following new/modified i18n keys into '{$targetLang}'.",
            "Rules:",
            "1. Preserve every {placeholder} token literally without translating placeholder names.",
            "2. Keep each entry's exact 'source' and 'source_hash' fields intact and output the translation in 'text'.",
            "3. Emit a single _ai0 block targeting '{$targetPath}'.",
            "",
            $jsonPayload,
        ];

        return implode("\n", $lines) . "\n";
    }

    /**
     * @return array<int, string>
     */
    private static function extractPlaceholders(string $text): array
    {
        if (!str_contains($text, '{')) {
            return [];
        }
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $text, $m);
        $list = array_values(array_unique($m[1] ?? []));
        sort($list, SORT_NATURAL);
        return $list;
    }
}