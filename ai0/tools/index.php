<?php
// PATH: ai0-0010/tools/index.php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/ai0-context.php';
require_once dirname(__DIR__) . '/ui/ui-layout.php';

$ctx = ai0_resolve_context(__DIR__);
$state = ai0_get_state($ctx);
$debug = (bool) ($state['debug'] ?? false);

ai0_render_head('ai0 // Tools Workspace', $ctx, 'devtools', $debug);
?>

<section class="j0ui-card">
    <h2>Tools Workspace (Column Browser Placeholder)</h2>
    <p class="j0ui-muted">Unified tools entrypoint. Select a standalone tool below or launch via portable stubs.</p>
    <div class="j0ui-actions">
        <a class="j0ui-btn j0ui-btn--primary" href="dumper/index.php">dumper</a>
        <a class="j0ui-btn j0ui-btn--primary" href="patcher/index.php">patcher</a>
        <a class="j0ui-btn j0ui-btn--primary" href="restore/index.php">restore</a>
        <a class="j0ui-btn j0ui-btn--primary" href="purge/index.php">purge</a>
    </div>
</section>

<?php
ai0_render_foot($ctx, $debug);