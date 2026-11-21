<?php
// PATH: ai0/stubs/ai0build.php
declare(strict_types=1);

$stubConfig = [
    'repo_url'    => 'https://github.com/sbustelo/AI-DevTools',
    'on_missing'  => 'strict',
    'bundle_name' => '',
    'targets'     => [
        '_ai0/j0/tooling/j0-build.php',
        'ai0/j0/tooling/j0-build.php',
        '_j0/tooling/j0-build.php',
        'j0/tooling/j0-build.php',
    ],
];

(static function (string $startDir, array $cfg): void {
    $bs = chr(92);
    $origin = rtrim(str_replace($bs, '/', $startDir), '/');
    $cursor = $origin;
    $builderFile = null;

    for ($level = 0; $level <= 12; $level++) {
        foreach ($cfg['targets'] as $relTarget) {
            $candidate = $cursor . '/' . $relTarget;
            if (is_file($candidate)) {
                $builderFile = $candidate;
                break 2;
            }
        }
        $parent = dirname($cursor);
        if ($parent === $cursor) {
            break;
        }
        $cursor = $parent;
    }

    if ($builderFile === null) {
        if ($cfg['on_missing'] === 'strict') {
            http_response_code(500);
            exit('This build stub requires j0/ai0 (' . $cfg['repo_url'] . '). Link ai0 as _ai0 or j0 as _j0 in your project root.');
        }
        return;
    }

    require_once $builderFile;

    $bundleName = $cfg['bundle_name'] !== '' ? $cfg['bundle_name'] : (basename($origin) ?: 'bundle');
    $results = [
        'dir' => $origin,
        'css' => j0_build_bundle([
            'src_dir'  => $origin,
            'out_file' => $origin . '/' . $bundleName . '.css',
            'ext'      => 'css',
            'minify'   => true,
            'backup'   => true,
        ]),
        'js'  => j0_build_bundle([
            'src_dir'  => $origin,
            'out_file' => $origin . '/' . $bundleName . '.js',
            'ext'      => 'js',
            'minify'   => true,
            'backup'   => true,
        ]),
    ];

    if (PHP_SAPI === 'cli') {
        echo "ai0build ({$bundleName}): CSS={$results['css']['reason']} ({$results['css']['files_count']} files), JS={$results['js']['reason']} ({$results['js']['files_count']} files)\n";
        return;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
})(__DIR__, $stubConfig);