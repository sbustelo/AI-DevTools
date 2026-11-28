<?php
// PATH: ai0/core/ai0-icons.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit('Direct access forbidden.');
}

/**
 * Single Source of Truth (SSOT) icon orchestrator for ai0.
 * Resolves .ai0root.json overrides, asset files (.svg/.png/.webp), inline SVGs, or emojis.
 *
 * @param array<string, mixed> $ctx
 * @return array{kind: string, value: string, url: string, html: string, text: string}
 */
function ai0_resolve_icon(string $targetPath, array $ctx, string $role = 'dir', string $extraClass = ''): array
{
    $bs = chr(92);
    $cleanPath = rtrim(str_replace($bs, '/', $targetPath), '/');
    $projectRoot = rtrim(str_replace($bs, '/', (string) ($ctx['root'] ?? '')), '/');
    $ai0Dir = rtrim(str_replace($bs, '/', (string) ($ctx['ai0_dir'] ?? dirname(__DIR__))), '/');

    $rawValue = '';
    $isProjectRoot = false;

    if ($role === 'brand') {
        $rawValue = 'ai0-icn32.svg';
    } elseif ($cleanPath !== '' && is_dir($cleanPath)) {
        $markerFile = $cleanPath . '/.ai0root.json';
        if (is_file($markerFile) && is_readable($markerFile)) {
            $decoded = json_decode((string) file_get_contents($markerFile), true);
            if (is_array($decoded)) {
                $isProjectRoot = !isset($decoded['is_project_root']) || $decoded['is_project_root'] !== false;
                if (isset($decoded['icon']) && is_string($decoded['icon'])) {
                    $rawValue = trim($decoded['icon']);
                }
            }
        }
    }

    if ($rawValue === '') {
        $rawValue = $role === 'file' ? '📄' : '📁';
    }

    $cls = trim('j0ui-icon ' . $extraClass);
    $safeCls = htmlspecialchars($cls, ENT_QUOTES, 'UTF-8');
    $wrapSlot = static function (string $innerHtml) use ($role, $isProjectRoot): string {
        if ($role === 'brand') {
            return $innerHtml;
        }
        $flag = $isProjectRoot ? 'true' : 'false';
        return '<span class="j0ui-icon-slot" data-j0-project-root="' . $flag . '">' . $innerHtml . '</span>';
    };

    if (str_starts_with(strtolower(ltrim($rawValue)), '<svg')) {
        return [
            'kind'  => 'svg',
            'value' => $rawValue,
            'url'   => '',
            'html'  => $wrapSlot('<span class="' . $safeCls . '" aria-hidden="true">' . $rawValue . '</span>'),
            'text'  => '📁',
        ];
    }

    if (preg_match('/^[a-zA-Z0-9._\/-]+\.(svg|png|webp|jpg|jpeg|gif)\z/i', $rawValue) === 1) {
        $url = function_exists('ai0_resolve_icon_url')
            ? ai0_resolve_icon_url($rawValue, $projectRoot, $ai0Dir)
            : null;
        if ($url !== null && $url !== '') {
            $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            return [
                'kind'  => 'img',
                'value' => $rawValue,
                'url'   => $url,
                'html'  => $wrapSlot('<img class="' . $safeCls . '" src="' . $safeUrl . '" alt="" aria-hidden="true">'),
                'text'  => '📁',
            ];
        }
        $rawValue = $role === 'file' ? '📄' : '📁';
    }

    return [
        'kind'  => 'emoji',
        'value' => $rawValue,
        'url'   => '',
        'html'  => $wrapSlot('<span class="' . $safeCls . '" aria-hidden="true">' . htmlspecialchars($rawValue, ENT_QUOTES, 'UTF-8') . '</span>'),
        'text'  => $rawValue,
    ];
}