<?php
// PATH: ai0/tools/index.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/ai0-context.php';
require_once dirname(__DIR__) . '/ui/ui-layout.php';

$ctx = ai0_resolve_context(__DIR__);
$target = ai0_url($ctx, 'tools/files/index.php');

$query = (string) ($_SERVER['QUERY_STRING'] ?? '');
if ($query !== '') {
    $sep = str_contains($target, '?') ? '&' : '?';
    $target .= $sep . $query;
}

header('Location: ' . $target, true, 302);
exit;