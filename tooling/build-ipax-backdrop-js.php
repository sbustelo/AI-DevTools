<?php
// PATH: tooling/build-ipax-backdrop-js.php
declare(strict_types=1);

if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

$rootDir = dirname(__DIR__);
$j0ApiFile = null;
$cursor = __DIR__;
for ($level = 0; $level <= 6; $level++) {
    foreach (['j0/api.php', 'ai0/j0/api.php'] as $relApi) {
        $cand = $cursor . '/' . $relApi;
        if (is_file($cand)) {
            $j0ApiFile = $cand;
            break 2;
        }
    }
    $parent = dirname($cursor);
    if ($parent === $cursor) {
        break;
    }
    $cursor = $parent;
}

if ($j0ApiFile === null) {
    echo "Critical error: j0/api.php not found.\n";
    exit(1);
}

require_once $j0ApiFile;
$j0 = j0_api();
$j0->use('tooling');

$defaultConfig = [
    'output_file'       => 'dist/ipax-backdrop.js',
    'echo'              => true,
    'include_j0_kernel' => true,
    'sources'           => ['src'],
    'minify'            => [
        'enabled' => true,
        'api_url' => 'https://www.toptal.com/developers/javascript-minifier/api/raw',
        'timeout' => 10,
    ],
];

$config = $defaultConfig;
$customConfigFile = __DIR__ . '/config/_build-config.php';
if (is_file($customConfigFile)) {
    $loadedConfig = require $customConfigFile;
    if (is_array($loadedConfig)) {
        $config = array_replace_recursive($defaultConfig, $loadedConfig);
    }
}

if (php_sapi_name() === 'cli') {
    global $argv;
    if (isset($argv) && is_array($argv)) {
        foreach ($argv as $arg) {
            if (in_array($arg, ['--quiet', '--silent', '--echo=false', '--echo=0'], true)) {
                $config['echo'] = false;
            } elseif (in_array($arg, ['--verbose', '--echo=true', '--echo=1'], true)) {
                $config['echo'] = true;
            }
        }
    }
} elseif (isset($_GET['echo'])) {
    $getEcho = strtolower(trim((string)$_GET['echo']));
    if (in_array($getEcho, ['false', '0', 'no', 'off'], true)) {
        $config['echo'] = false;
    } elseif (in_array($getEcho, ['true', '1', 'yes', 'on'], true)) {
        $config['echo'] = true;
    }
}

$filesToBundle = [];
if (!empty($config['include_j0_kernel'])) {
    $kernelPath = $j0->assetPath('kernel.js');
    if ($kernelPath === null) {
        echo "Critical error: j0 kernel asset not found.\n";
        exit(1);
    }
    $filesToBundle[] = $kernelPath;
}

$bs = chr(92);
foreach ((array) $config['sources'] as $sourceDirName) {
    $cleanRel = trim(str_replace($bs, '/', (string) $sourceDirName), '/');
    $absSourceDir = $rootDir . '/' . $cleanRel;
    $filesToBundle = array_merge($filesToBundle, j0_scan_files($absSourceDir, ['js']));
}

if ($filesToBundle === []) {
    if ($config['echo']) {
        echo "Error: No valid JS files found to bundle.\n";
    }
    exit(1);
}

$banner = '/*! IPAX Backdrop • ipax.bustelo.com.ar • Build: ' . date('Y-m-d') . ' */';
$buffer = j0_concat_files($filesToBundle, "\n;\n\n", $banner, true);

$buffer = preg_replace_callback('/\/\* glsl-minifier-start \*\/(.*?)\/\* glsl-minifier-end \*\//s', static function (array $matches): string {
    $block = $matches[1];
    if (!preg_match('/`([^`]*)`/', $block, $btMatches)) {
        return $matches[0];
    }
    $glsl = preg_replace('!//.*!', '', $btMatches[1]);
    $lines = explode("\n", (string) $glsl);
    $minified = '';
    foreach ($lines as $line) {
        $line = trim((string) preg_replace('/\s+/', ' ', $line));
        if ($line === '') {
            continue;
        }
        if ($line[0] === '#') {
            if ($minified !== '' && substr($minified, -1) !== "\n") {
                $minified = rtrim($minified) . "\n";
            }
            $minified .= $line . "\n";
        } else {
            $minified .= $line . ' ';
        }
    }
    return str_replace($btMatches[0], '`' . trim($minified) . '`', $matches[0]);
}, $buffer);

$targetRelPath = ltrim(str_replace($bs, '/', (string) $config['output_file']), '/');
$targetAbsPath = $rootDir . '/' . $targetRelPath;
$saveRaw = j0_smart_save($targetAbsPath, (string) $buffer, true);

if (!$saveRaw['written'] && $saveRaw['reason'] !== 'unchanged') {
    if ($config['echo']) {
        echo 'Critical error: Failed writing ' . $targetRelPath . ' (' . $saveRaw['reason'] . ")\n";
    }
    exit(1);
}

if ($config['echo']) {
    echo $targetRelPath . ' (' . $saveRaw['reason'] . ")\n";
}

if (!empty($config['minify']['enabled'])) {
    $info = pathinfo($targetRelPath);
    $dirPart = (isset($info['dirname']) && $info['dirname'] !== '.') ? $info['dirname'] . '/' : '';
    $minRelPath = $dirPart . $info['filename'] . '.min.js';
    $minAbsPath = $rootDir . '/' . $minRelPath;

    $minifiedContent = j0_minify_js((string) $buffer, (array) $config['minify']);
    if (strpos((string) $minifiedContent, '/*! IPAX Backdrop') === false) {
        $minifiedContent = $banner . "\n" . $minifiedContent;
    }

    $saveMin = j0_smart_save($minAbsPath, $minifiedContent, true);
    if (!$saveMin['written'] && $saveMin['reason'] !== 'unchanged') {
        if ($config['echo']) {
            echo 'Critical error: Failed writing ' . $minRelPath . ' (' . $saveMin['reason'] . ")\n";
        }
        exit(1);
    }

    if ($config['echo']) {
        echo $minRelPath . ' (' . $saveMin['reason'] . ")\n";
    }
}