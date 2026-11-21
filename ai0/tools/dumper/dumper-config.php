<?php
// PATH: ai0-0010/tools/dumper/dumper-config.php
declare(strict_types=1);

final class DumperConfig
{
    /**
     * Loads resolved dumper categories and prefix taxonomy across the 4-tier [_]ai0config cascade.
     *
     * @param array<int, string> $domains
     * @return array{categories: array<string, array<string, mixed>>, prefixes: array<string, mixed>, binary_extensions: array<int, string>}
     */
    public static function load(array $domains): array
    {
        $dumperCfg = j0_config_json('dumper-categories', $domains);
        $prefixCfg = j0_config_json('prefixes', $domains);
        if ($prefixCfg === []) {
            $prefixCfg = j0_default_prefix_config();
        }

        $categories = isset($dumperCfg['categories']) && is_array($dumperCfg['categories'])
            ? $dumperCfg['categories']
            : [];
        $binaryExts = isset($dumperCfg['binary_extensions']) && is_array($dumperCfg['binary_extensions'])
            ? $dumperCfg['binary_extensions']
            : ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'zip', 'sqlite', 'db'];

        return [
            'categories'        => $categories,
            'prefixes'          => $prefixCfg,
            'binary_extensions' => $binaryExts,
        ];
    }
}