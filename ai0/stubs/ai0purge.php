<?php
// PATH: ai0-0010/stubs/ai0purge.php
declare(strict_types=1);

$stubConfig = [
    'repo_url'   => 'https://github.com/sbustelo/AI-DevTools',
    'on_missing' => 'strict',
    'targets'    => ['_ai0/tools/purge/index.php', 'ai0/tools/purge/index.php'],
];

(static function (string $startDir, array $cfg): void {
    $bs = chr(92);
    $origin = rtrim(str_replace($bs, '/', $startDir), '/');
    $cursor = $origin;
    $down = [];

    for ($level = 0; $level <= 12; $level++) {
        foreach ($cfg['targets'] as $relTarget) {
            $candidate = $cursor . '/' . $relTarget;
            if (is_file($candidate)) {
                $GLOBALS['ai0_context'] = [
                    'root'       => $cursor,
                    'match'      => $relTarget,
                    'base_dir'   => explode('/', $relTarget)[0],
                    'target_abs' => $candidate,
                    'origin_abs' => $origin,
                    'up_rel'     => $level === 0 ? '' : str_repeat('../', $level),
                    'down_rel'   => $down === [] ? '' : implode('/', array_reverse($down)),
                ];
                require $candidate;
                return;
            }
        }
        $parent = dirname($cursor);
        if ($parent === $cursor) {
            break;
        }
        $down[] = basename($cursor);
        $cursor = $parent;
    }

    if ($cfg['on_missing'] === 'strict') {
        http_response_code(500);
        exit('This tool requires ai0 (' . $cfg['repo_url'] . '). Link ai0 as _ai0 in your project root.');
    }
})(__DIR__, $stubConfig);