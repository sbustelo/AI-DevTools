<?php
// PATH: ai0/j0/examples/03-locator-and-templates/demo-locate.php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/quickstart/j0-bootstrap.php';

j0_boot(__DIR__);
j0_use('core', 'tooling');

j0_locate_init(__DIR__);
j0_mount('tpl', __DIR__);

$logoUrl = S('tpl/h0010-greeting-card.html');
$selfPhp = j0_path('demo-locate.php');
$renderedCard = T('greeting-card', [
    'title'     => 'Location-Independent Templates',
    'user'      => 'Architect',
    'badgeHtml' => '<strong>j0 v2</strong>',
]);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>j0 Example 03 — Locator &amp; Templates (S / T)</title>
</head>
<body>
    <h1>j0 Locator &amp; Templates Demo</h1>
    <p><strong>Project Root:</strong> <code><?= htmlspecialchars(j0_project_root(), ENT_QUOTES, 'UTF-8') ?></code></p>
    <p><strong>S('tpl/h0010-greeting-card.html'):</strong> <code><?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?></code></p>
    <p><strong>j0_path('demo-locate.php'):</strong> <code><?= htmlspecialchars($selfPhp, ENT_QUOTES, 'UTF-8') ?></code></p>
    <hr>
    <?= $renderedCard . "\n" ?>
</body>
</html>