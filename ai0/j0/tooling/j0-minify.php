<?php
// PATH: ai0/j0/tooling/j0-minify.php
declare(strict_types=1);

if (!function_exists('j0_minify_extract_banner')) {
    function j0_minify_extract_banner(string $code): string
    {
        $trimmed = ltrim($code);
        if (strncmp($trimmed, '/*!', 3) !== 0) {
            return '';
        }
        $endPos = strpos($trimmed, '*/');
        if ($endPos === false) {
            return '';
        }
        return substr($trimmed, 0, $endPos + 2);
    }
}

if (!function_exists('j0_minify_remote')) {
    /**
     * @param array<string, mixed> $options
     */
    function j0_minify_remote(string $code, string $type, array $options = []): ?string
    {
        $defaultUrl = $type === 'css'
            ? 'https://www.toptal.com/developers/cssminifier/api/raw'
            : 'https://www.toptal.com/developers/javascript-minifier/api/raw';

        $apiUrl = isset($options['api_url']) && is_string($options['api_url']) && $options['api_url'] !== ''
            ? $options['api_url']
            : $defaultUrl;
        $timeout = isset($options['timeout']) ? max(1, (int) $options['timeout']) : 8;
        $payload = http_build_query(['input' => $code]);

        if (function_exists('curl_init')) {
            $ch = curl_init($apiUrl);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(4, $timeout),
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (PHP_VERSION_ID < 80000 && is_resource($ch)) {
                curl_close($ch);
            }

            if (is_string($response) && $httpCode === 200 && trim($response) !== '') {
                return $response;
            }
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => $timeout,
            ],
        ]);

        $response = @file_get_contents($apiUrl, false, $context);
        if (is_string($response) && trim($response) !== '') {
            return $response;
        }

        return null;
    }
}

if (!function_exists('j0_minify_dispatch')) {
    /**
     * Cascade:
     * 1. Optional local minifier plugin (callable or tooling/plugins/minify-local.php)
     * 2. Remote Toptal minifier API (preserving leading license banner comments)
     * 3. Unminified source fallback with explicit warning header
     *
     * @param array<string, mixed> $options
     */
    function j0_minify_dispatch(string $code, string $type, array $options = []): string
    {
        if (trim($code) === '') {
            return '';
        }

        if (isset($options['local_plugin']) && is_callable($options['local_plugin'])) {
            $res = ($options['local_plugin'])($code, $type, $options);
            if (is_string($res) && trim($res) !== '') {
                return $res;
            }
        }

        $pluginFile = __DIR__ . '/plugins/minify-local.php';
        if (is_file($pluginFile)) {
            require_once $pluginFile;
            if (function_exists('j0_minify_local')) {
                $res = j0_minify_local($code, $type, $options);
                if (is_string($res) && trim($res) !== '') {
                    return $res;
                }
            }
        }

        $allowRemote = (bool) ($options['allow_remote'] ?? true);
        if (!$allowRemote) {
            $stamp = gmdate('Y-m-d\TH:i:s\Z');
            $warning = "/*! [j0-minify] REMOTE DISABLED ({$type}): local plugin absent and remote minification disabled for protected code at {$stamp}. Serving uncompacted source. */\n";
            return $warning . $code;
        }

        $banner = j0_minify_extract_banner($code);
        $minified = j0_minify_remote($code, $type, $options);
        if ($minified !== null) {
            if ($banner !== '' && strpos($minified, $banner) === false) {
                return $banner . "\n" . $minified;
            }
            return $minified;
        }

        $stamp = gmdate('Y-m-d\TH:i:s\Z');
        $warning = "/*! [j0-minify] UNMINIFIED FALLBACK ({$type}): local plugin absent and remote minifier unavailable at {$stamp}. Serving uncompacted source. */\n";
        return $warning . $code;
    }
}

if (!function_exists('j0_minify_css')) {
    /**
     * @param array<string, mixed> $options
     */
    function j0_minify_css(string $code, array $options = []): string
    {
        return j0_minify_dispatch($code, 'css', $options);
    }
}

if (!function_exists('j0_minify_js')) {
    /**
     * @param array<string, mixed> $options
     */
    function j0_minify_js(string $code, array $options = []): string
    {
        return j0_minify_dispatch($code, 'js', $options);
    }
}