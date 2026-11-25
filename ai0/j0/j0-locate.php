<?php
// PATH: j0/j0-locate.php
declare(strict_types=1);

if (!defined('J0_EXEC')) {
    exit('Direct access forbidden.');
}

require_once __DIR__ . '/core/j0-scan.php';
require_once __DIR__ . '/core/j0-config.php';

if (!function_exists('j0_locate_store')) {
    /**
     * Central in-memory state for project root, mounted roots, and .j0route.php cache.
     *
     * @return array<string, mixed>
     */
    function &j0_locate_store(): array
    {
        static $store = [
            'project_root' => null,
            'route_file'   => null,
            'routes'       => null,
            'mounted_dirs' => [],
            'index'        => null,
            'index_hash'   => '',
        ];
        return $store;
    }
}

if (!function_exists('j0_locate_init')) {
    /**
     * Initializes or overrides the project root and .j0route.php cache path.
     */
    function j0_locate_init(string $projectRoot, ?string $routeFile = null): void
    {
        $bs = chr(92);
        $store = &j0_locate_store();
        $cleanRoot = rtrim(str_replace($bs, '/', $projectRoot), '/');
        $store['project_root'] = $cleanRoot;
        $store['route_file'] = $routeFile !== null
            ? str_replace($bs, '/', $routeFile)
            : $cleanRoot . '/.j0route.php';
        $store['routes'] = null;
        $store['index'] = null;
    }
}

if (!function_exists('j0_project_root')) {
    /**
     * Resolves the active host project root lexically without breaking symlinks.
     */
    function j0_project_root(): string
    {
        $store = &j0_locate_store();
        if (is_string($store['project_root']) && $store['project_root'] !== '') {
            return $store['project_root'];
        }

        $bs = chr(92);
        if (defined('J0_ROOT_PATH') && is_string(J0_ROOT_PATH) && J0_ROOT_PATH !== '') {
            $root = rtrim(str_replace($bs, '/', J0_ROOT_PATH), '/');
        } elseif (!empty($_SERVER['SCRIPT_FILENAME'])) {
            $cursor = dirname(str_replace($bs, '/', (string) $_SERVER['SCRIPT_FILENAME']));
            $root = $cursor;
            for ($i = 0; $i <= 12; $i++) {
                if (
                    is_file($cursor . '/.j0route.php')
                    || is_file($cursor . '/_j0route.php')
                    || is_dir($cursor . '/src')
                    || is_dir($cursor . '/j0')
                    || is_dir($cursor . '/_j0')
                ) {
                    $root = $cursor;
                    break;
                }
                $parent = dirname($cursor);
                if ($parent === $cursor) {
                    break;
                }
                $cursor = $parent;
            }
        } else {
            $root = rtrim(str_replace($bs, '/', (string) getcwd()), '/');
        }

        j0_locate_init($root);
        return $root;
    }
}

if (!function_exists('j0_mount')) {
    /**
     * Registers host directories (Library Mode) into the Union Mount resolution table.
     *
     * @param string|array<int, string> $dirs
     */
    function j0_mount(string|array $dirs, ?string $projectRoot = null): void
    {
        if ($projectRoot !== null && $projectRoot !== '') {
            j0_locate_init($projectRoot);
        }
        $root = j0_project_root();
        $bs = chr(92);
        $store = &j0_locate_store();

        foreach ((array) $dirs as $dir) {
            $clean = rtrim(str_replace($bs, '/', trim((string) $dir)), '/');
            if ($clean === '') {
                continue;
            }
            $abs = (str_starts_with($clean, '/') || preg_match('/^[a-zA-Z]:\//', $clean) === 1)
                ? $clean
                : $root . '/' . ltrim($clean, '/');
            if (is_dir($abs) && !in_array($abs, $store['mounted_dirs'], true)) {
                $store['mounted_dirs'][] = $abs;
                $store['index'] = null;
            }
        }
    }
}

if (!function_exists('j0_route_cache_read')) {
    /**
     * Reads .j0route.php in O(1) via OPcache and auto-upgrades legacy string route files.
     *
     * @return array<string, string>
     */
    function j0_route_cache_read(): array
    {
        $store = &j0_locate_store();
        if (is_array($store['routes'])) {
            return $store['routes'];
        }

        j0_project_root();
        $routeFile = (string) $store['route_file'];
        $routes = [];

        if (is_file($routeFile)) {
            $loaded = @include $routeFile;
            if (is_array($loaded)) {
                $routes = $loaded;
            } elseif (is_string($loaded) && $loaded !== '' && is_file($loaded)) {
                $routes = ['__j0_api__' => $loaded];
                j0_route_cache_write($routes);
            }
        }

        $store['routes'] = $routes;
        return $routes;
    }
}

if (!function_exists('j0_route_cache_write')) {
    /**
     * Atomically writes the guarded associative .j0route.php cache using flock + rename + opcache_invalidate.
     *
     * @param array<string, string> $routes
     */
    function j0_route_cache_write(array $routes): bool
    {
        $store = &j0_locate_store();
        j0_project_root();
        $routeFile = (string) $store['route_file'];
        $dir = dirname($routeFile);

        if (!is_dir($dir) || !is_writable($dir)) {
            $store['routes'] = $routes;
            return false;
        }

        ksort($routes, SORT_NATURAL);
        $payload = "<?php\n// Auto-generated by j0-locate.php. Safe to delete if files move.\nif (!defined('J0_EXEC')) {\n    exit;\n}\nreturn "
            . var_export($routes, true)
            . ";\n";

        $lockHandle = @fopen($dir . '/.__j0route.lock', 'c');
        if (is_resource($lockHandle)) {
            @flock($lockHandle, LOCK_EX);
        }

        $tmp = $dir . '/.__j0route_' . uniqid('', true);
        $ok = @file_put_contents($tmp, $payload) !== false && @rename($tmp, $routeFile);
        if (!$ok) {
            @unlink($tmp);
        } elseif (function_exists('opcache_invalidate')) {
            @opcache_invalidate($routeFile, true);
        }

        if (is_resource($lockHandle)) {
            @flock($lockHandle, LOCK_UN);
            @fclose($lockHandle);
        }

        $store['routes'] = $routes;
        return $ok;
    }
}

if (!function_exists('j0_locate_parse_module')) {
    /**
     * Extracts semantic ID and tier visibility from a directory name (NNNN-sem-id--tags note).
     *
     * @return array{sem_id: string, tier: string, tags: array<int, string>}
     */
    function j0_locate_parse_module(string $dirName): array
    {
        $machine = explode(' ', $dirName, 2)[0];
        $parts = explode('--', $machine, 2);
        $semId = strtolower(trim((string) preg_replace('/^\d+\s*-?\s*/', '', $parts[0])));
        $tags = isset($parts[1]) && $parts[1] !== ''
            ? array_values(array_filter(explode('-', strtolower($parts[1]))))
            : [];

        $tier = 'public';
        foreach (['isprotected', 'pro', 'plus', 'registered'] as $candidate) {
            if (in_array($candidate, $tags, true)) {
                $tier = $candidate;
                break;
            }
        }

        return ['sem_id' => $semId, 'tier' => $tier, 'tags' => $tags];
    }
}

if (!function_exists('j0_locate_index')) {
    /**
     * Builds the unified Union Mount index (j0/src -> project/src -> mounted host dirs) with shadowing.
     *
     * @return array<string, array{path: string, tier: string, sem_id: string}>
     */
    function j0_locate_index(): array
    {
        $store = &j0_locate_store();
        $root = j0_project_root();
        $j0Dir = defined('J0_DIR') ? rtrim(str_replace(chr(92), '/', (string) J0_DIR), '/') : str_replace(chr(92), '/', __DIR__);

        $layers = [];
        if (is_dir($j0Dir . '/src')) {
            $layers[] = ['dir' => $j0Dir . '/src', 'modular' => true];
        }
        if (is_dir($root . '/src')) {
            $layers[] = ['dir' => $root . '/src', 'modular' => true];
        }
        foreach ($store['mounted_dirs'] as $mDir) {
            $layers[] = ['dir' => $mDir, 'modular' => false];
        }

        $sig = sha1(json_encode($layers) ?: '');
        if (is_array($store['index']) && $store['index_hash'] === $sig) {
            return $store['index'];
        }

        $index = [];
        foreach ($layers as $layer) {
            $baseDir = $layer['dir'];
            $isModular = $layer['modular'];
            $files = j0_scan_files($baseDir);

            foreach ($files as $absPath) {
                $rel = ltrim(substr($absPath, strlen($baseDir)), '/');
                if ($rel === '') {
                    continue;
                }
                $segments = explode('/', $rel);
                $filename = basename($absPath);
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if ($isModular && count($segments) >= 2) {
                    $modDir = array_shift($segments);
                    $meta = j0_locate_parse_module($modDir);
                    $semId = $meta['sem_id'];
                    $tier = $meta['tier'];

                    if (count($segments) >= 2 && strtolower($segments[0]) === 'src' && strtolower($segments[1]) === $semId) {
                        array_shift($segments);
                        array_shift($segments);
                    }
                    $internal = implode('/', $segments);
                } else {
                    $semId = strtolower(basename($baseDir));
                    $tier = 'public';
                    $internal = $rel;
                }

                $record = ['path' => $absPath, 'tier' => $tier, 'sem_id' => $semId];
                $index[$filename] = $record;
                if ($semId !== '') {
                    $index[$semId . ':' . $filename] = $record;
                }
                if ($internal !== '' && $internal !== $filename) {
                    $index[$internal] = $record;
                    if ($semId !== '') {
                        $index[$semId . ':' . $internal] = $record;
                    }
                }

                if ($ext === 'html') {
                    $tplBase = (string) preg_replace('/^[a-z]?\d+-/i', '', basename($filename, '.html'));
                    $tplId = strtolower(explode('--', $tplBase, 2)[0]);
                    if ($tplId !== '') {
                        $index['tpl:' . $tplId] = $record;
                        if ($semId !== '') {
                            $index['tpl:' . $semId . ':' . $tplId] = $record;
                        }
                    }
                }
            }
        }

        $store['index'] = $index;
        $store['index_hash'] = $sig;
        return $index;
    }
}

if (!function_exists('j0_locate_ring_search')) {
    /**
     * Opt-in pruned expanding-ring search from project root (executed only when deep = true).
     */
    function j0_locate_ring_search(string $targetName, int $maxDepth = 4): ?string
    {
        $root = j0_project_root();
        $skip = ['.git', 'node_modules', 'vendor', '_j0-temp', '_ai0', '_ai0config'];
        $queue = [[$root, 0]];

        while ($queue !== []) {
            [$dir, $depth] = array_shift($queue);
            $entries = @scandir($dir);
            if (!is_array($entries)) {
                continue;
            }
            sort($entries, SORT_NATURAL);
            foreach ($entries as $entry) {
                if ($entry === '' || $entry[0] === '.' || $entry[0] === '_' || in_array($entry, $skip, true)) {
                    continue;
                }
                $full = $dir . '/' . $entry;
                if (is_file($full) && $entry === $targetName) {
                    return $full;
                }
                if (is_dir($full) && $depth < $maxDepth) {
                    $queue[] = [$full, $depth + 1];
                }
            }
        }

        return null;
    }
}

if (!function_exists('j0_locate')) {
    /**
     * Resolves a resource record using .j0route.php -> Union Mount -> optional deep ring search.
     *
     * @param array{deep?: bool, cache?: bool} $options
     * @return array{path: string, tier: string, sem_id: string}|null
     */
    function j0_locate(string $id, string $kind = 'any', array $options = []): ?array
    {
        $bs = chr(92);
        $clean = ltrim(str_replace($bs, '/', trim($id)), '/');
        if ($clean === '' || str_contains($clean, '..')) {
            return null;
        }

        $cacheKey = $kind . ':' . $clean;
        $routes = j0_route_cache_read();
        $useCache = $options['cache'] ?? true;

        if ($useCache && isset($routes[$cacheKey])) {
            $cachedPath = $routes[$cacheKey];
            if (is_file($cachedPath)) {
                $modDir = basename(dirname($cachedPath));
                $meta = j0_locate_parse_module($modDir);
                return ['path' => $cachedPath, 'tier' => $meta['tier'], 'sem_id' => $meta['sem_id']];
            }
            unset($routes[$cacheKey]);
            j0_route_cache_write($routes);
        }

        $index = j0_locate_index();
        $candidates = $kind === 'tpl' ? ['tpl:' . $clean, $clean] : [$clean];
        $hit = null;

        foreach ($candidates as $cand) {
            if (isset($index[$cand]) && is_file($index[$cand]['path'])) {
                $hit = $index[$cand];
                break;
            }
        }

        if ($hit === null && !empty($options['deep'])) {
            $deepPath = j0_locate_ring_search(basename($clean));
            if ($deepPath !== null && is_file($deepPath)) {
                $hit = ['path' => $deepPath, 'tier' => 'public', 'sem_id' => ''];
            }
        }

        if ($hit !== null && $useCache) {
            $routes[$cacheKey] = $hit['path'];
            j0_route_cache_write($routes);
        }

        return $hit;
    }
}

if (!function_exists('j0_path')) {
    /**
     * Resolves an absolute filesystem path for server-side resources (PHP, JSON, templates).
     */
    function j0_path(string $id, string $legacyFilename = '', array $options = []): string
    {
        $query = $legacyFilename !== '' ? ($id !== '' ? $id . ':' . $legacyFilename : $legacyFilename) : $id;
        $hit = j0_locate($query, 'php', $options);
        if ($hit !== null) {
            return $hit['path'];
        }
        if ($legacyFilename !== '') {
            $fallback = j0_locate($legacyFilename, 'php', $options);
            if ($fallback !== null) {
                return $fallback['path'];
            }
        }
        return '';
    }
}

if (!function_exists('j0_path_to_web_url')) {
    /**
     * Converts an in-webroot physical path into a public HTTP URL (handling lexical j0 symlinks).
     */
    function j0_path_to_web_url(string $absPath): ?string
    {
        $bs = chr(92);
        $normFile = str_replace($bs, '/', $absPath);
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim(str_replace($bs, '/', (string) $_SERVER['DOCUMENT_ROOT']), '/') : '';
        if ($docRoot === '') {
            return null;
        }

        $j0Dir = defined('J0_DIR') ? rtrim(str_replace($bs, '/', (string) J0_DIR), '/') : str_replace($bs, '/', __DIR__);
        $root = j0_project_root();
        if (str_starts_with($normFile, $j0Dir . '/')) {
            foreach (['j0', '_j0', 'ai0/j0', '_ai0/j0'] as $alias) {
                if (is_dir($root . '/' . $alias)) {
                    $normFile = $root . '/' . $alias . substr($normFile, strlen($j0Dir));
                    break;
                }
            }
        }

        if (str_starts_with($normFile, $docRoot . '/')) {
            $relUrl = substr($normFile, strlen($docRoot));
            if (defined('J0_BASE_URL') && is_string(J0_BASE_URL) && J0_BASE_URL !== '') {
                return rtrim(J0_BASE_URL, '/') . '/' . ltrim($relUrl, '/');
            }
            return $relUrl;
        }

        return null;
    }
}

if (!function_exists('j0_src')) {
    /**
     * Source ("Where"): returns a public HTTP URL for web assets, or an absolute disk path for .php files.
     * Never returns file contents.
     *
     * @param array{deep?: bool, cache?: bool} $options
     */
    function j0_src(string $id, array $options = []): string
    {
        $clean = ltrim(str_replace(chr(92), '/', trim($id)), '/');
        if ($clean === '') {
            return '';
        }

        if (str_ends_with(strtolower($clean), '.php')) {
            return j0_path($clean, '', $options);
        }

        $baseUrl = defined('J0_BASE_URL') ? rtrim((string) J0_BASE_URL, '/') . '/' : '/';
        $isDev = !defined('J0_MODE') || J0_MODE === 'dev';

        if (!$isDev && defined('J0_PROTECTED_PATH')) {
            static $prodAssets = null;
            if ($prodAssets === null) {
                $mapFile = rtrim((string) J0_PROTECTED_PATH, '/\\') . '/assets-map.json';
                $prodAssets = is_file($mapFile) ? (json_decode((string) file_get_contents($mapFile), true) ?: []) : [];
            }
            if (isset($prodAssets[$clean])) {
                $entry = $prodAssets[$clean];
                if (is_array($entry)) {
                    $tier = (string) ($entry['tier'] ?? 'public');
                    if ($tier !== 'public') {
                        return $baseUrl . 'api/serve.php?s=' . rawurlencode($clean);
                    }
                    return $baseUrl . ltrim((string) ($entry['url'] ?? ''), '/');
                }
                return $baseUrl . ltrim((string) $entry, '/');
            }
        }

        $hit = j0_locate($clean, 'asset', $options);
        if ($hit === null) {
            return $baseUrl . 'ERROR_ASSET_NOT_FOUND/' . $clean;
        }

        if ($hit['tier'] !== 'public') {
            return $baseUrl . 'api/serve.php?s=' . rawurlencode($clean);
        }

        $webUrl = j0_path_to_web_url($hit['path']);
        if ($webUrl !== null) {
            return $webUrl;
        }

        return $baseUrl . 'api/serve.php?s=' . rawurlencode($clean);
    }
}

if (!function_exists('S')) {
    /**
     * Shortcut for j0_src(): returns the source URL or server path for $id.
     *
     * @param array{deep?: bool, cache?: bool} $options
     */
    function S(string $id, array $options = []): string
    {
        return j0_src($id, $options);
    }
}

if (!function_exists('j0_text')) {
    /**
     * Text / Template ("What"): reads and renders an HTML template, inline SVG, or JSON resource.
     *
     * @param array<string, mixed> $vars
     * @param array{deep?: bool, cache?: bool} $options
     */
    function j0_text(string $id, array $vars = [], array $options = []): mixed
    {
        static $tplCache = [];
        $clean = ltrim(str_replace(chr(92), '/', trim($id)), '/');
        if ($clean === '') {
            return '';
        }

        $isDev = !defined('J0_MODE') || J0_MODE === 'dev';
        if (!$isDev && defined('J0_PROTECTED_PATH')) {
            static $manifest = null;
            if ($manifest === null) {
                $mFile = rtrim((string) J0_PROTECTED_PATH, '/\\') . '/_j0manifest.php';
                $manifest = is_file($mFile) ? (@include $mFile ?: []) : [];
            }
            if (isset($manifest['tangible'][$clean])) {
                $compiled = $manifest['tangible'][$clean];
                return is_string($compiled) ? j0_interpolate_text($compiled, $vars) : $compiled;
            }
        }

        $hit = j0_locate($clean, 'tpl', $options);
        if ($hit === null) {
            return '';
        }

        $absPath = $hit['path'];
        $ext = strtolower(pathinfo($absPath, PATHINFO_EXTENSION));

        if ($ext === 'json') {
            $decoded = json_decode((string) file_get_contents($absPath), true);
            return is_array($decoded) ? $decoded : [];
        }

        if (!isset($tplCache[$absPath])) {
            $raw = (string) file_get_contents($absPath);
            if ($ext === 'svg') {
                $raw = (string) preg_replace('/^\s*<\?xml[^?]*\?>\s*/i', '', $raw);
                $tplCache[$absPath] = trim($raw);
            } else {
                $html = (string) preg_replace('/<j0-ifjs>[\s\S]*?<\/j0-ifjs>/i', '', $raw);
                $html = str_ireplace(['<j0-ifphp>', '</j0-ifphp>'], '', $html);
                $html = (string) preg_replace('/data-php-action=["\'](.*?)["\']/i', 'action="$1" method="POST"', $html);
                $html = (string) preg_replace('/\s+data-j0-meta=([\'"]).*?\1/s', '', $html);
                $html = j0_resolve_text_macros($html);
                $tplCache[$absPath] = $html;
            }
        }

        return j0_interpolate_text($tplCache[$absPath], $vars);
    }
}

if (!function_exists('j0_tpl')) {
    /**
     * Alias for j0_text().
     *
     * @param array<string, mixed> $vars
     * @param array{deep?: bool, cache?: bool} $options
     */
    function j0_tpl(string $id, array $vars = [], array $options = []): mixed
    {
        return j0_text($id, $vars, $options);
    }
}

if (!function_exists('T')) {
    /**
     * Shortcut for j0_text() / j0_tpl(): returns rendered template or file text content.
     *
     * @param array<string, mixed> $vars
     * @param array{deep?: bool, cache?: bool} $options
     */
    function T(string $id, array $vars = [], array $options = []): mixed
    {
        return j0_text($id, $vars, $options);
    }
}

if (!function_exists('j0_resolve_text_macros')) {
    /**
     * Evaluates whitelisted inline macros {S(...)}, {L(...)}, {M(...)} inside templates.
     */
    function j0_resolve_text_macros(string $html): string
    {
        return (string) preg_replace_callback('/\{([a-zA-Z0-9_]+)\(([\s\S]*?)\)\}/', static function (array $m): string {
            $fn = $m[1];
            if (!in_array($fn, ['S', 'L', 'M'], true) || !function_exists($fn)) {
                return $m[0];
            }

            preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\'|-?\d+(?:\.\d+)?|true|false/', $m[2], $argMatches);
            $args = [];
            foreach ($argMatches[0] as $tok) {
                if ($tok === 'true') {
                    $args[] = true;
                } elseif ($tok === 'false') {
                    $args[] = false;
                } elseif (is_numeric($tok)) {
                    $args[] = str_contains($tok, '.') ? (float) $tok : (int) $tok;
                } else {
                    $unquoted = substr($tok, 1, -1);
                    $args[] = str_replace(['\\"', "\\'"], ['"', "'"], $unquoted);
                }
            }

            return (string) call_user_func_array($fn, $args);
        }, $html);
    }
}

if (!function_exists('j0_interpolate_text')) {
    /**
     * Applies {{{raw}}} and HTML-escaped {{var}} bindings and strips unreplaced tokens.
     *
     * @param array<string, mixed> $vars
     */
    function j0_interpolate_text(string $tpl, array $vars): string
    {
        $out = $tpl;
        foreach ($vars as $key => $val) {
            if (is_scalar($val) || $val === null) {
                $out = str_replace('{{{' . $key . '}}}', (string) $val, $out);
            }
        }
        foreach ($vars as $key => $val) {
            if (is_scalar($val) || $val === null) {
                $out = str_replace('{{' . $key . '}}', htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8'), $out);
            }
        }
        return (string) preg_replace('/\{\{\s*[a-zA-Z0-9_.-]+\s*\}\}/', '', $out);
    }
}