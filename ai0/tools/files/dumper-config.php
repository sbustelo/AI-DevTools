<?php
// PATH: ai0/tools/dumper/dumper-config.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

/**
 * Loads dumper configuration from [_]ai0config / j0 prefix helpers.
 *
 * @param array<string, mixed> $ctx
 * @return array<string, mixed>
 */
function dumper_load_config(array $ctx): array
{
    $domains = (isset($ctx['domains']) && is_array($ctx['domains'])) ? $ctx['domains'] : [];
    $taxonomy = function_exists('j0_config_json') ? j0_config_json('taxonomy', $domains) : [];
    $legacyDumper = (empty($taxonomy['categories']) && function_exists('j0_config_json'))
        ? j0_config_json('dumper-categories', $domains)
        : [];
    $legacyPrefixes = (empty($taxonomy['system_markers']) && function_exists('j0_config_json'))
        ? j0_config_json('prefixes', $domains)
        : [];

    $dumperCfg = array_replace_recursive($legacyDumper, $taxonomy);
    $categories = isset($dumperCfg['categories']) && is_array($dumperCfg['categories'])
        ? $dumperCfg['categories']
        : [];
    $prefixCfg = array_replace_recursive($legacyPrefixes, [
        'system_markers' => $taxonomy['system_markers'] ?? [],
        'human_markers'  => $taxonomy['human_markers'] ?? [],
        'os_junk'        => $taxonomy['os_junk'] ?? [],
    ]);
    $binaryExts = isset($dumperCfg['binary_extensions']) && is_array($dumperCfg['binary_extensions'])
        ? $dumperCfg['binary_extensions']
        : ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'zip', 'sqlite', 'db'];
    $j0Pragmas = isset($dumperCfg['j0_pragmas']) && is_array($dumperCfg['j0_pragmas'])
        ? $dumperCfg['j0_pragmas']
        : [
            'compiled' => ['/*! j0 compiled', '/*! j0 c*/'],
            'minified' => ['/*! j0 minified', '/*! j0 m*/'],
        ];

    return [
        'categories'        => $categories,
        'prefixes'          => $prefixCfg,
        'binary_extensions' => $binaryExts,
        'j0_pragmas'        => $j0Pragmas,
    ];
}
