<?php
// PATH: ai0/tools/restore/index.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/ai0-context.php';
require_once dirname(__DIR__, 2) . '/ui/ui-layout.php';
require_once __DIR__ . '/restore-manager.php';
require_once __DIR__ . '/restore-view.php';

$ctx = ai0_resolve_context(__DIR__);
$bs = chr(92);
$defaultRoot = rtrim(str_replace($bs, '/', (string) ($ctx['root'] ?? __DIR__)), '/');
if ($defaultRoot === '') {
    $defaultRoot = '/';
}
$requestedRoot = (string) ($_POST['browse_root'] ?? $_GET['root'] ?? '');
$cleanReqRoot = rtrim(str_replace($bs, '/', trim($requestedRoot)), '/');
if ($cleanReqRoot === '') {
    $cleanReqRoot = '/';
}
$activeRoot = ($requestedRoot !== '' && !str_contains($cleanReqRoot, '..') && is_dir($cleanReqRoot))
    ? $cleanReqRoot
    : $defaultRoot;
$query = '?tab=restore' . (($activeRoot !== $defaultRoot) ? ('&root=' . rawurlencode($activeRoot)) : '');
header('Location: ' . ai0_ui_web_base($ctx) . '../tools/files/index.php' . $query, true, 302);
exit;