<?php
// PATH: ai0/j0/examples/02-project-bootstrap/index.php
declare(strict_types=1);

require_once __DIR__ . '/php/bootstrap.php';

// Compute asset tags BEFORE app_render_page() so j0_static_html() tracks source mtimes.
$kernelJs = j0_asset_path('j0010-kernel.js');
$vfsDir = j0_asset_dir('vfs');
$sources = array_values(array_filter([$kernelJs, $vfsDir]));
$scriptTags = app_js_tags($sources, 'app-runtime');

app_render_page(__FILE__, static function () use ($scriptTags): void {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>j0 Project Bootstrap Example</title>
    <?= $scriptTags . "\n" ?>
</head>
<body>
    <h1>j0 v2 Project Bootstrap</h1>
    <p>Pass <code>?debug=1</code> to bypass static HTML caching and inspect individual source files.</p>
</body>
</html>
    <?php
});