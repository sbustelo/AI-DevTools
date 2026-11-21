<?php
// PATH: ai0-0010/j0/php/j0-prefix.php
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