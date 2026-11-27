<?php
// PATH: ai0-0010/j0/php/j0-closest.php
declare(strict_types=1);

if (!function_exists('j0_closest')) {
    /**
     * Traverses upward from $startDir until any relative path in $targets exists.
     * Operates lexically without resolving symlinks so host project roots remain intact.
     *
     * @param array<int, string> $targets
     * @return array{root: string, match: string, base_dir: string, target_abs: string, origin_abs: string, up_rel: string, down_rel: string}|null
     */
    function j0_closest(string $startDir, array $targets, int $maxLevels = 12): ?array
    {
        $bs = chr(92);
        $origin = rtrim(str_replace($bs, '/', $startDir), '/');
        $cursor = $origin;
        $downSegments = [];

        for ($level = 0; $level <= $maxLevels; $level++) {
            foreach ($targets as $relTarget) {
                $cleanTarget = ltrim(str_replace($bs, '/', $relTarget), '/');
                $candidate = $cursor . '/' . $cleanTarget;
                if (file_exists($candidate)) {
                    $upRel = $level === 0 ? '' : str_repeat('../', $level);
                    $downRel = $downSegments === [] ? '' : implode('/', array_reverse($downSegments));
                    $baseDir = explode('/', $cleanTarget)[0];

                    return [
                        'root'       => $cursor,
                        'match'      => $cleanTarget,
                        'base_dir'   => $baseDir,
                        'target_abs' => $candidate,
                        'origin_abs' => $origin,
                        'up_rel'     => $upRel,
                        'down_rel'   => $downRel,
                    ];
                }
            }

            $parent = dirname($cursor);
            if ($parent === $cursor) {
                break;
            }
            $downSegments[] = basename($cursor);
            $cursor = $parent;
        }

        return null;
    }
}