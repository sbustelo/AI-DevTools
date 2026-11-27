<?php
// PATH: j0/core/j0-prefix.php
declare(strict_types=1);

if (!function_exists('j0_normalize_prefix_text')) {
    /**
     * Strips Unicode Variation Selector-16 (U+FE0F) so macOS APFS emoji filenames match consistently.
     */
    function j0_normalize_prefix_text(string $text): string
    {
        return str_replace("\u{FE0F}", '', $text);
    }
}

if (!function_exists('j0_default_prefix_config')) {
    /**
     * @return array<string, mixed>
     */
    function j0_default_prefix_config(): array
    {
        return [
            'system_markers' => [
                "_\u{1F5D1}" => [
                    'state'               => 'discard',
                    'actor'               => 'system',
                    'excluded_by_default' => true,
                    'purge_preselected'   => true,
                    'label'               => "Discarded (_\u{1F5D1})",
                ],
                "_\u{1F4E6}" => [
                    'state'               => 'backup',
                    'actor'               => 'system',
                    'excluded_by_default' => true,
                    'purge_preselected'   => true,
                    'label'               => "Backups & Archives (_\u{1F4E6})",
                ],
            ],
            'human_markers' => [
                '__' => [
                    'state'               => 'discard',
                    'actor'               => 'human',
                    'excluded_by_default' => true,
                    'purge_preselected'   => true,
                    'label'               => 'Quick-Discarded (__)',
                ],
                '_' => [
                    'state'               => 'disabled',
                    'actor'               => 'human',
                    'excluded_by_default' => true,
                    'purge_preselected'   => false,
                    'label'               => 'Disabled / Private (_)',
                ],
            ],
            'os_junk' => [
                '.DS_Store'         => 'macOS',
                '._*'               => 'AppleDouble',
                '.AppleDouble'      => 'AppleDouble',
                '.LSOverride'       => 'macOS',
                'Thumbs.db'         => 'Windows',
                'ehthumbs.db'       => 'Windows',
                'ehthumbs_vista.db' => 'Windows',
                'Desktop.ini'       => 'Windows',
                '.directory'        => 'Linux',
            ],
        ];
    }
}

if (!function_exists('j0_is_temp_dirname')) {
    /**
     * Checks whether a single directory segment represents a regenerable temp/cache directory.
     */
    function j0_is_temp_dirname(string $segment): bool
    {
        $lower = strtolower(trim($segment));
        return in_array($lower, [
            '_j0-temp',
            '_temp',
            '.temp',
            'temp',
            '_tmp',
            '.tmp',
            'tmp',
            '_cache',
            '.cache',
            'cache',
        ], true);
    }
}

if (!function_exists('j0_is_temp_path')) {
    /**
     * Checks whether any directory segment in $path is a regenerable temp/cache directory.
     */
    function j0_is_temp_path(string $path): bool
    {
        $norm = trim(str_replace(chr(92), '/', $path), '/');
        if ($norm === '') {
            return false;
        }
        foreach (explode('/', $norm) as $seg) {
            if (j0_is_temp_dirname($seg)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('j0_match_os_junk')) {
    /**
     * SSOT matcher for OS metadata junk files (.DS_Store, ._* AppleDouble, Thumbs.db, etc.).
     *
     * @param array<string, mixed> $prefixConfig
     */
    function j0_match_os_junk(string $name, array $prefixConfig = []): ?string
    {
        $defaults = j0_default_prefix_config();
        $rules = (isset($prefixConfig['os_junk']) && is_array($prefixConfig['os_junk']))
            ? $prefixConfig['os_junk']
            : (array) ($defaults['os_junk'] ?? []);

        $lowerName = strtolower($name);
        foreach ($rules as $pattern => $label) {
            if (!is_string($label) || $label === '') {
                continue;
            }
            $pat = (string) $pattern;
            if ($pat === '') {
                continue;
            }
            if (str_ends_with($pat, '*')) {
                $prefix = substr($pat, 0, -1);
                if ($prefix !== '' && str_starts_with($lowerName, strtolower($prefix))) {
                    return $label;
                }
                continue;
            }
            if ($lowerName === strtolower($pat)) {
                return $label;
            }
        }

        return null;
    }
}

if (!function_exists('j0_classify_purge_item')) {
    /**
     * SSOT classifier for Space Reclamation (Purge).
     * Inside temp directories, only mechanical backups (_📦), discards (_🗑️ / __), and OS junk qualify.
     *
     * @param array<string, mixed> $prefixConfig
     * @return array{bucket: string, marker: string, preselected: bool}|null
     */
    function j0_classify_purge_item(string $name, array $prefixConfig = [], bool $insideTemp = false): ?array
    {
        if ($name === '' || j0_is_temp_dirname($name)) {
            return null;
        }

        if (str_starts_with($name, '_')) {
            $info = j0_classify_prefix($name, $prefixConfig);
            $state = $info['state'];
            if ($insideTemp && $state !== 'backup' && $state !== 'discard') {
                return null;
            }
            $bucket = in_array($state, ['discard', 'backup', 'disabled'], true) ? $state : 'disabled';
            return [
                'bucket'      => $bucket,
                'marker'      => $info['marker'],
                'preselected' => $info['purge_preselected'],
            ];
        }

        $junkLabel = j0_match_os_junk($name, $prefixConfig);
        if ($junkLabel !== null) {
            return [
                'bucket'      => 'os_junk',
                'marker'      => $junkLabel,
                'preselected' => true,
            ];
        }

        return null;
    }
}

if (!function_exists('j0_parse_entry')) {
    /**
     * Universal j0 naming convention parser (FS = SSOT).
     * Extracts lifecycle state, 4-digit order, ___ separator, semantic ID, --tags,
     * human comment after space, and file asset prefix (p/j/c/h).
     *
     * @param array<string, mixed> $prefixConfig
     * @return array{
     *   raw: string,
     *   state: string,
     *   marker: string,
     *   is_hidden: bool,
     *   is_separator: bool,
     *   order: int|null,
     *   asset_type: string,
     *   sem_id: string,
     *   tags: array<int, string>,
     *   comment: string
     * }
     */
    function j0_parse_entry(string $name, array $prefixConfig = []): array
    {
        $isHidden = str_starts_with($name, '.');
        $prefixMeta = j0_classify_prefix($name, $prefixConfig);
        $marker = $prefixMeta['marker'];
        $clean = $marker !== '' ? substr(j0_normalize_prefix_text($name), strlen($marker)) : $name;
        if ($isHidden) {
            $clean = ltrim($clean, '.');
        }

        $partsSpace = explode(' ', $clean, 2);
        $machine = $partsSpace[0];
        $comment = isset($partsSpace[1]) ? trim($partsSpace[1]) : '';

        $ext = pathinfo($machine, PATHINFO_EXTENSION);
        $stem = $ext !== '' ? substr($machine, 0, -(strlen($ext) + 1)) : $machine;

        $tagSplit = explode('--', $stem, 2);
        $head = $tagSplit[0];
        $tags = isset($tagSplit[1]) && $tagSplit[1] !== ''
            ? array_values(array_filter(explode('-', strtolower($tagSplit[1]))))
            : [];

        $order = null;
        $assetType = '';
        $isSeparator = false;
        $semId = $head;

        if (preg_match('/^([pjch])?(\d{2,4})-(.*)$/i', $head, $m) === 1) {
            $assetType = strtolower($m[1]);
            $order = (int) $m[2];
            $rest = $m[3];
            if (str_starts_with($rest, '___')) {
                $isSeparator = true;
                $semId = trim(substr($rest, 3), '-');
            } else {
                $semId = strtolower(trim($rest));
            }
        } elseif (str_starts_with($head, '___')) {
            $isSeparator = true;
            $semId = trim(substr($head, 3), '-');
        } else {
            $semId = strtolower(trim($head));
        }

        return [
            'raw'          => $name,
            'state'        => $isHidden ? 'hidden' : $prefixMeta['state'],
            'marker'       => $isHidden ? '.' : $marker,
            'is_hidden'    => $isHidden,
            'is_separator' => $isSeparator,
            'order'        => $order,
            'asset_type'   => $assetType,
            'sem_id'       => $semId,
            'tags'         => $tags,
            'comment'      => $comment,
        ];
    }
}

if (!function_exists('j0_classify_prefix')) {
    /**
     * Classifies a file or directory basename according to the leading underscore semantic taxonomy.
     *
     * @param array<string, mixed> $prefixConfig
     * @return array{state: string, actor: string, excluded_by_default: bool, purge_preselected: bool, marker: string}
     */
    function j0_classify_prefix(string $name, array $prefixConfig = []): array
    {
        if (!str_starts_with($name, '_')) {
            return [
                'state'               => 'active',
                'actor'               => 'none',
                'excluded_by_default' => false,
                'purge_preselected'   => false,
                'marker'              => '',
            ];
        }

        $cfg = $prefixConfig !== [] ? $prefixConfig : j0_default_prefix_config();
        $normName = j0_normalize_prefix_text($name);

        $groups = [
            $cfg['system_markers'] ?? [],
            $cfg['human_markers'] ?? [],
        ];

        foreach ($groups as $markers) {
            if (!is_array($markers)) {
                continue;
            }
            uksort($markers, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
            foreach ($markers as $rawMarker => $meta) {
                $normMarker = j0_normalize_prefix_text((string) $rawMarker);
                if ($normMarker !== '' && str_starts_with($normName, $normMarker)) {
                    return [
                        'state'               => (string) ($meta['state'] ?? 'disabled'),
                        'actor'               => (string) ($meta['actor'] ?? 'human'),
                        'excluded_by_default' => (bool) ($meta['excluded_by_default'] ?? true),
                        'purge_preselected'   => (bool) ($meta['purge_preselected'] ?? false),
                        'marker'              => $normMarker,
                    ];
                }
            }
        }

        return [
            'state'               => 'disabled',
            'actor'               => 'human',
            'excluded_by_default' => true,
            'purge_preselected'   => false,
            'marker'              => '_',
        ];
    }
}