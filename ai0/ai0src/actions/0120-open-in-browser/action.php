<?php
// PATH: ai0/ai0src/actions/0120-open-in-browser/action.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

return [
    'match' => static function (array $target): bool {
        $abs = (string) ($target['abs'] ?? '');
        return $abs !== '' && file_exists($abs);
    },
    'run' => static function (array $target): array {
        $bs = chr(92);
        $abs = rtrim(str_replace($bs, '/', (string) ($target['abs'] ?? '')), '/');
        $isDir = is_dir($abs);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');

        $scriptFile = str_replace($bs, '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $scriptName = str_replace($bs, '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $siteWebroot = '';
        if ($scriptFile !== '' && $scriptName !== '' && str_ends_with($scriptFile, $scriptName)) {
            $siteWebroot = rtrim(substr($scriptFile, 0, -strlen($scriptName)), '/');
        }

        if ($host !== '' && $siteWebroot !== '' && ($abs === $siteWebroot || str_starts_with($abs . '/', $siteWebroot . '/'))) {
            $rel = $abs === $siteWebroot ? '' : ltrim(substr($abs, strlen($siteWebroot)), '/');
            $encoded = $rel === '' ? '' : implode('/', array_map('rawurlencode', explode('/', $rel)));
            $url = $scheme . '://' . $host . '/' . $encoded;
            if ($isDir && !str_ends_with($url, '/')) {
                $url .= '/';
            }
            return ['ok' => true, 'open_url' => $url, 'message' => $url];
        }

        $marker = ai0_find_root_marker($abs);
        if ($marker === null) {
            return ['ok' => false, 'message' => L('Target is outside the active webroot and has no .ai0root.json.', 'El elemento está fuera del webroot activo y no tiene .ai0root.json.')];
        }

        $mDir = rtrim($marker['dir'], '/');
        $data = $marker['data'];
        $rel = $abs === $mDir ? '' : ltrim(substr($abs, strlen($mDir)), '/');
        $mode = (string) ($data['server_mode'] ?? 'path');

        if ($mode === 'vhost' && $rel !== '') {
            $parts = explode('/', $rel);
            $site = array_shift($parts);
            $tld = (string) ($data['tld'] ?? '.test');
            $encodedRest = implode('/', array_map('rawurlencode', $parts));
            $url = $scheme . '://' . $site . $tld . '/' . $encodedRest;
            if ($isDir && !str_ends_with($url, '/')) {
                $url .= '/';
            }
            return ['ok' => true, 'open_url' => $url, 'message' => $url];
        }

        $baseUrl = rtrim((string) ($data['base_url'] ?? ($scheme . '://' . ($host !== '' ? $host : 'localhost'))), '/');
        $encodedRel = $rel === '' ? '' : implode('/', array_map('rawurlencode', explode('/', $rel)));
        $url = $baseUrl . '/' . $encodedRel;
        if ($isDir && !str_ends_with($url, '/')) {
            $url .= '/';
        }
        return ['ok' => true, 'open_url' => $url, 'message' => $url];
    },
];