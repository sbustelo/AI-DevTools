<?php
// PATH: ai0/tools/i18n-batch/i18n-batch-view.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit;
}

/**
 * @param array<string, mixed> $vm
 */
function i18n_batch_render_view(array $vm): void
{
    $units = is_array($vm['units'] ?? null) ? $vm['units'] : [];
    $selectedUnit = (string) ($vm['selectedUnit'] ?? '');
    $langs = is_array($vm['langs'] ?? null) ? $vm['langs'] : ['en', 'es'];
    $primaryLang = (string) ($vm['primaryLang'] ?? 'en');
    $targetLang = (string) ($vm['targetLang'] ?? 'es');
    $dumpInput = (string) ($vm['dumpInput'] ?? '');
    $diff = is_array($vm['diff'] ?? null) ? $vm['diff'] : null;
    $deltaPrompt = (string) ($vm['deltaPrompt'] ?? '');
    $patcherUrl = htmlspecialchars((string) ($vm['patcherUrl'] ?? ''), ENT_QUOTES, 'UTF-8');
    $activeRoot = htmlspecialchars((string) ($vm['activeRoot'] ?? ''), ENT_QUOTES, 'UTF-8');
    ?>
    <section class="j0ui-card">
        <h2><?= htmlspecialchars(L('i18n-batch — Unit Dictionary Diff & Delta Prompt', 'i18n-batch — Diferencias de Diccionarios y Prompt Delta'), ENT_QUOTES, 'UTF-8') ?></h2>
        <form method="post">
            <input type="hidden" name="action" value="analyze">
            <input type="hidden" name="browse_root" value="<?= $activeRoot ?>">
            <div class="j0ui-actions">
                <label class="j0ui-cat-main">
                    <span><?= htmlspecialchars(L('Unit:', 'Unidad:'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select name="unit" class="j0ui-btn">
                        <?php foreach ($units as $u): ?>
                            <?php $safeU = htmlspecialchars((string) $u, ENT_QUOTES, 'UTF-8'); ?>
                            <option value="<?= $safeU ?>" <?= ($u === $selectedUnit) ? 'selected' : '' ?>><?= $safeU ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="j0ui-cat-main">
                    <span><?= htmlspecialchars(L('Target lang:', 'Idioma destino:'), ENT_QUOTES, 'UTF-8') ?></span>
                    <select name="target_lang" class="j0ui-btn">
                        <?php foreach ($langs as $l): ?>
                            <?php if ($l === $primaryLang) { continue; } ?>
                            <?php $safeL = htmlspecialchars((string) $l, ENT_QUOTES, 'UTF-8'); ?>
                            <option value="<?= $safeL ?>" <?= ($l === $targetLang) ? 'selected' : '' ?>><?= strtoupper($safeL) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="j0ui-btn j0ui-btn--primary"><?= htmlspecialchars(L('Analyze Delta', 'Analizar Delta'), ENT_QUOTES, 'UTF-8') ?></button>
                <a class="j0ui-btn" href="<?= $patcherUrl ?>"><?= htmlspecialchars(L('Open patcher', 'Abrir patcher'), ENT_QUOTES, 'UTF-8') ?></a>
            </div>
            <p class="j0ui-muted"><?= htmlspecialchars(L('Optional: paste passive M_dump() JSON below to compare against disk i18n/{lang}.json.', 'Opcional: pegá abajo el JSON de M_dump() para compararlo contra i18n/{lang}.json en disco.'), ENT_QUOTES, 'UTF-8') ?></p>
            <textarea name="m_dump_json" class="j0ui-textarea" rows="5" placeholder="<?= htmlspecialchars(L('Optional M_dump() JSON...', 'JSON opcional de M_dump()...'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dumpInput, ENT_QUOTES, 'UTF-8') ?></textarea>
        </form>
    </section>

    <?php if ($diff !== null): ?>
        <section class="j0ui-card">
            <h2><?= htmlspecialchars(L('Diff Summary', 'Resumen de Diferencias'), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($selectedUnit, ENT_QUOTES, 'UTF-8') ?> → <?= htmlspecialchars(strtoupper($targetLang), ENT_QUOTES, 'UTF-8') ?>)</h2>
            <div class="j0ui-actions">
                <span class="j0ui-badge j0ui-badge-accent"><?= htmlspecialchars(L('New:', 'Nuevas:'), ENT_QUOTES, 'UTF-8') ?> <?= count($diff['new']) ?></span>
                <span class="j0ui-badge j0ui-badge-warning"><?= htmlspecialchars(L('Modified:', 'Modificadas:'), ENT_QUOTES, 'UTF-8') ?> <?= count($diff['modified']) ?></span>
                <span class="j0ui-badge"><?= htmlspecialchars(L('Active:', 'Activas:'), ENT_QUOTES, 'UTF-8') ?> <?= count($diff['active']) ?></span>
                <span class="j0ui-badge"><?= htmlspecialchars(L('Orphaned:', 'Huérfanas:'), ENT_QUOTES, 'UTF-8') ?> <?= count($diff['orphaned']) ?></span>
            </div>

            <?php if ($diff['placeholder_warnings'] !== []): ?>
                <div class="j0ui-alert-inline j0ui-alert-warning">
                    <strong><?= htmlspecialchars(L('Placeholder Integrity Warnings:', 'Advertencias de integridad en placeholders:'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <ul class="j0ui-mono">
                        <?php foreach ($diff['placeholder_warnings'] as $w): ?>
                            <li><?= htmlspecialchars((string) $w, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($deltaPrompt !== ''): ?>
                <h3><?= htmlspecialchars(L('Minimal AI Delta Prompt', 'Prompt Delta Mínimo para IA'), ENT_QUOTES, 'UTF-8') ?></h3>
                <textarea class="j0ui-textarea" rows="12" readonly data-j0-copy-source="i18n-delta-out"><?= htmlspecialchars($deltaPrompt, ENT_QUOTES, 'UTF-8') ?></textarea>
                <div class="j0ui-actions">
                    <button type="button" class="j0ui-btn j0ui-btn--primary" data-j0-action="copy-target" data-j0-target="i18n-delta-out"><?= htmlspecialchars(L('Copy Delta Prompt', 'Copiar Prompt Delta'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
            <?php else: ?>
                <p class="j0ui-muted"><?= htmlspecialchars(L('All keys are up to date. Zero pending translations.', 'Todas las claves están al día. Cero traducciones pendientes.'), ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <?php
}