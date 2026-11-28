<?php
// PATH: ai0/tools/settings/api.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    define('J0_EXEC', true);
}

require_once dirname(__DIR__, 2) . '/core/ai0-context.php';

header('Content-Type: application/json; charset=utf-8');

$ctx = ai0_resolve_context(__DIR__);
$tempDir = rtrim(str_replace(chr(92), '/', (string) ($ctx['temp_dir'] ?? '')), '/');
if ($tempDir === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'temp_dir unavailable']);
    exit;
}

$prefsFile = $tempDir . '/ai0-prefs.json';
$allowedKeys = ['ai0_font_ui', 'ai0_font_listing', 'ai0_root_size', 'ai0_weight_normal'];

$loadPrefs = static function () use ($prefsFile): array {
    if (!is_file($prefsFile) || !is_readable($prefsFile)) {
        return [];
    }
    $decoded = json_decode((string) @file_get_contents($prefsFile), true);
    return is_array($decoded) ? $decoded : [];
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $raw = (string) file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body) || !isset($body['prefs']) || !is_array($body['prefs'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
        exit;
    }

    $current = $loadPrefs();
    foreach ($body['prefs'] as $key => $entry) {
        if (!in_array((string) $key, $allowedKeys, true) || !is_array($entry)) {
            continue;
        }
        $incomingTs = (int) ($entry['updatedAt'] ?? 0);
        $existingTs = (int) ($current[$key]['updatedAt'] ?? 0);
        if ($incomingTs >= $existingTs) {
            if (array_key_exists('value', $entry) && $entry['value'] !== null && $entry['value'] !== '') {
                $current[$key] = [
                    'value'     => $entry['value'],
                    'updatedAt' => $incomingTs > 0 ? $incomingTs : (int) round(microtime(true) * 1000),
                ];
            } else {
                $current[$key] = [
                    'value'     => null,
                    'updatedAt' => $incomingTs > 0 ? $incomingTs : (int) round(microtime(true) * 1000),
                ];
            }
        }
    }

    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0775, true);
    }
    $tmp = $tempDir . '/.__prefs_' . uniqid('', true);
    $json = json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    if (@file_put_contents($tmp, $json) === false || !@rename($tmp, $prefsFile)) {
        @unlink($tmp);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Failed to persist prefs']);
        exit;
    }

    echo json_encode(['ok' => true, 'prefs' => $current], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => true, 'prefs' => $loadPrefs()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);