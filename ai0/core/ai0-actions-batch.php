<?php
// PATH: ai0/core/ai0-actions-batch.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit('Direct access forbidden.');
}

/**
 * Removes leading, trailing, and consecutive duplicate menu separators.
 *
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array<string, mixed>>
 */
function ai0_prune_action_separators(array $items): array
{
    $out = [];
    foreach ($items as $item) {
        $isSep = ($item['type'] ?? '') === 'separator';
        if ($isSep) {
            if ($out === [] || ($out[count($out) - 1]['type'] ?? '') === 'separator') {
                continue;
            }
        }
        $out[] = $item;
    }
    while ($out !== [] && ($out[count($out) - 1]['type'] ?? '') === 'separator') {
        array_pop($out);
    }
    return $out;
}

/**
 * Filters out descendant items whose ancestor directory is already present in the batch.
 *
 * @param array<int, array<string, mixed>> $targets
 * @return array<int, array<string, mixed>>
 */
function ai0_prune_descendant_targets(array $targets): array
{
    $dirPrefixes = [];
    foreach ($targets as $t) {
        $rel = trim((string) ($t['rel'] ?? ''), '/');
        if (($t['type'] ?? '') === 'dir' && $rel !== '') {
            $dirPrefixes[] = $rel . '/';
        }
    }
    if ($dirPrefixes === []) {
        return $targets;
    }

    $filtered = [];
    foreach ($targets as $t) {
        $rel = trim((string) ($t['rel'] ?? ''), '/');
        $hasSelectedAncestor = false;
        foreach ($dirPrefixes as $prefix) {
            if (str_starts_with($rel, $prefix)) {
                $hasSelectedAncestor = true;
                break;
            }
        }
        if (!$hasSelectedAncestor) {
            $filtered[] = $t;
        }
    }
    return $filtered;
}

/**
 * Discovers actions supporting multi-selection that apply to ALL selected targets.
 *
 * @param array<string, mixed> $ctx
 * @param array<int, array<string, mixed>> $targets
 * @return array<int, array<string, mixed>>
 */
function ai0_discover_multi_actions(array $ctx, array $targets): array
{
    if ($targets === []) {
        return [];
    }

    $all = ai0_discover_actions($ctx, null);
    $kept = [];

    foreach ($all as $item) {
        if (($item['type'] ?? '') === 'separator') {
            $kept[] = $item;
            continue;
        }
        if (empty($item['multi'])) {
            continue;
        }
        $applies = is_array($item['applies_to'] ?? null) ? $item['applies_to'] : [];
        $matchesAll = true;
        foreach ($targets as $t) {
            $tType = (string) ($t['type'] ?? 'file');
            $tCat = (string) ($t['cat'] ?? '');
            if (!ai0_action_matches_applies($applies, $tType, $tCat)) {
                $matchesAll = false;
                break;
            }
            if (isset($item['match']) && is_callable($item['match'])) {
                if (!($item['match'])($t, $ctx, (string) ($item['dir'] ?? ''))) {
                    $matchesAll = false;
                    break;
                }
            }
        }
        if ($matchesAll) {
            $kept[] = $item;
        }
    }

    return ai0_prune_action_separators($kept);
}

/**
 * Executes a multi-item action across a batch of validated targets.
 *
 * @param array<string, mixed> $ctx
 * @param array<int, array<string, mixed>> $targets
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function ai0_run_action_batch(string $actionId, array $ctx, array $targets, array $params = []): array
{
    if ($targets === []) {
        return ['ok' => false, 'message' => L('No items selected.', 'No hay elementos seleccionados.')];
    }

    foreach (ai0_discover_multi_actions($ctx, $targets) as $act) {
        if (($act['type'] ?? '') !== 'action' || ($act['id'] ?? '') !== $actionId) {
            continue;
        }

        $effective = !empty($act['prune_descendants'])
            ? ai0_prune_descendant_targets($targets)
            : $targets;

        if (isset($act['run_batch']) && is_callable($act['run_batch'])) {
            return ($act['run_batch'])($effective, $ctx, $params);
        }

        if (!isset($act['run']) || !is_callable($act['run'])) {
            break;
        }

        $okCount = 0;
        $errors = [];
        $reload = false;
        foreach ($effective as $t) {
            $res = ($act['run'])($t, $ctx, $params);
            if (!empty($res['ok'])) {
                $okCount++;
                if (!empty($res['reload'])) {
                    $reload = true;
                }
            } elseif (!empty($res['message'])) {
                $errors[] = (string) $res['message'];
            }
        }

        if ($okCount === 0) {
            return [
                'ok'      => false,
                'message' => $errors !== [] ? implode(' | ', array_slice($errors, 0, 3)) : L('Batch action failed.', 'Falló la acción en lote.'),
            ];
        }

        return [
            'ok'      => true,
            'reload'  => $reload,
            'count'   => $okCount,
            'errors'  => $errors,
            'message' => L('{0} items processed.', '{0} elementos procesados.', [$okCount]),
        ];
    }

    return ['ok' => false, 'message' => "Batch action not found or not applicable: {$actionId}"];
}