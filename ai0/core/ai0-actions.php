<?php
// PATH: ai0/core/ai0-actions.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit('Direct access forbidden.');
}

/**
 * Returns vendor and user action directories in priority order (vendor -> project -> _project).
 *
 * @param array<string, mixed> $ctx
 * @return array<int, string>
 */
function ai0_action_roots(array $ctx): array
{
    $bs = chr(92);
    $ai0Dir = rtrim(str_replace($bs, '/', (string) ($ctx['ai0_dir'] ?? dirname(__DIR__))), '/');
    $root = rtrim(str_replace($bs, '/', (string) ($ctx['root'] ?? '')), '/');

    $candidates = [
        $ai0Dir . '/ai0src/actions',
        $root !== '' ? ($root . '/ai0src/actions') : '',
        $root !== '' ? ($root . '/_ai0src/actions') : '',
    ];

    $dirs = [];
    foreach ($candidates as $dir) {
        if ($dir !== '' && is_dir($dir) && !in_array($dir, $dirs, true)) {
            $dirs[] = $dir;
        }
    }
    return $dirs;
}

/**
 * Checks whether an item matches an action's applies_to selectors (e.g. 'file', 'dir', 'file.discard', '.discard').
 *
 * @param array<int, string> $selectors
 */
function ai0_action_matches_applies(array $selectors, string $itemType, string $itemCat): bool
{
    if ($selectors === []) {
        return true;
    }

    foreach ($selectors as $raw) {
        $sel = strtolower(trim((string) $raw));
        if ($sel === '' || $sel === '*' || $sel === 'both') {
            return true;
        }
        if ($sel === $itemType || $sel === $itemType . 's') {
            return true;
        }
        if (str_starts_with($sel, '.')) {
            if ($itemCat !== '' && substr($sel, 1) === $itemCat) {
                return true;
            }
            continue;
        }
        if (str_contains($sel, '.')) {
            [$subj, $cat] = explode('.', $sel, 2);
            $subjMatch = ($subj === $itemType || $subj === $itemType . 's');
            if ($subjMatch && $itemCat !== '' && $cat === $itemCat) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Discovers modular actions and ___ separators in SORT_NATURAL order.
 *
 * @param array<string, mixed> $ctx
 * @param array<string, mixed>|null $targetInfo Optional target item for applies_to + secondary match() filtering
 * @return array<int, array<string, mixed>>
 */
function ai0_discover_actions(array $ctx, ?array $targetInfo = null): array
{
    $merged = [];

    foreach (ai0_action_roots($ctx) as $dir) {
        $entries = @scandir($dir);
        if (!is_array($entries)) {
            continue;
        }
        sort($entries, SORT_NATURAL);

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $parsed = j0_parse_entry($entry);
            if ($parsed['state'] !== 'active') {
                continue;
            }

            $full = $dir . '/' . $entry;
            if ($parsed['is_separator']) {
                $merged[$entry] = [
                    'type'    => 'separator',
                    'id'      => $entry,
                    'order'   => $parsed['order'] ?? 5000,
                    'comment' => $parsed['comment'],
                ];
                continue;
            }

            if (!is_dir($full)) {
                continue;
            }

            $metaFile = $full . '/metadata.json';
            $actionFile = $full . '/action.php';
            if (!is_file($actionFile)) {
                continue;
            }

            $meta = is_file($metaFile) ? (json_decode((string) file_get_contents($metaFile), true) ?: []) : [];
            $spec = require $actionFile;
            if (!is_array($spec)) {
                continue;
            }

            $id = (string) ($meta['id'] ?? $parsed['sem_id']);
            $applies = isset($meta['applies_to']) && is_array($meta['applies_to']) ? $meta['applies_to'] : [];

            if ($targetInfo !== null) {
                $itemType = (string) ($targetInfo['type'] ?? 'file');
                $itemCat = (string) ($targetInfo['cat'] ?? '');
                if (!ai0_action_matches_applies($applies, $itemType, $itemCat)) {
                    continue;
                }
                if (isset($spec['match']) && is_callable($spec['match'])) {
                    $ok = ($spec['match'])($targetInfo, $ctx, $full);
                    if (!$ok) {
                        continue;
                    }
                }
            }

            $rawIcon = (string) ($meta['icon'] ?? '');
            $iconUrl = '';
            if ($rawIcon !== '' && preg_match('/^[a-zA-Z0-9._\/-]+\.(svg|png|webp|jpg|jpeg|gif)$/i', $rawIcon) === 1) {
                $localAsset = $full . '/' . basename($rawIcon);
                if (is_file($localAsset) && function_exists('j0_path_to_web_url')) {
                    $iconUrl = (string) (j0_path_to_web_url($localAsset) ?? '');
                } elseif (function_exists('ai0_resolve_icon_url')) {
                    $iconUrl = (string) (ai0_resolve_icon_url($rawIcon, (string) ($ctx['root'] ?? ''), (string) ($ctx['ai0_dir'] ?? '')) ?? '');
                }
            }

            $merged[$entry] = [
                'type'       => 'action',
                'id'         => $id,
                'order'      => $parsed['order'] ?? 5000,
                'dir'        => $full,
                'label'      => $meta['label'] ?? $id,
                'icon'       => $rawIcon,
                'icon_url'   => $iconUrl,
                'class'      => (string) ($meta['class'] ?? ''),
                'danger'     => !empty($meta['danger']),
                'prompt'     => $meta['prompt'] ?? null,
                'confirm'    => $meta['confirm'] ?? null,
                'submenu'    => isset($meta['submenu']) && is_array($meta['submenu']) ? $meta['submenu'] : null,
                'applies_to' => $applies,
                'multi'             => !empty($meta['multi']),
                'prune_descendants' => !empty($meta['prune_descendants']),
                'confirm_multi'     => $meta['confirm_multi'] ?? null,
                'match'             => $spec['match'] ?? null,
                'run'               => $spec['run'] ?? null,
                'run_batch'         => $spec['run_batch'] ?? null,
            ];
        }
    }

    uksort($merged, static fn(string $a, string $b): int => strnatcasecmp($a, $b));
    return array_values($merged);
}

/**
 * Executes a modular action by ID.
 *
 * @param array<string, mixed> $ctx
 * @param array<string, mixed> $targetInfo
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
require_once __DIR__ . '/ai0-actions-batch.php';

function ai0_run_action(string $actionId, array $ctx, array $targetInfo, array $params = []): array
{
    foreach (ai0_discover_actions($ctx, $targetInfo) as $item) {
        if (($item['type'] ?? '') === 'action' && ($item['id'] ?? '') === $actionId) {
            if (isset($item['run']) && is_callable($item['run'])) {
                return ($item['run'])($targetInfo, $ctx, $params);
            }
        }
    }

    return ['ok' => false, 'message' => "Action not found or not applicable: {$actionId}"];
}