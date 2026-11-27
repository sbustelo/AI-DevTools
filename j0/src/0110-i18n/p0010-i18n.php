<?php
// PATH: ai0/j0/src/0110-i18n/p0010-i18n.php
declare(strict_types=1);

if (!function_exists('j0_i18n_store')) {
    /**
     * @return array<string, mixed>
     */
    function &j0_i18n_store(): array
    {
        static $store = [
            'langs'           => ['en'],
            'provider'        => null,
            'override'        => null,
            'unit_dirs'       => [],
            'dicts'           => [],
            'collector'       => [],
            'collect_enabled' => false,
            'md_plugin'       => null,
        ];
        return $store;
    }
}

if (!function_exists('j0_i18n_config')) {
    /**
     * Configures supported languages, optional language provider, unit JSON directories,
     * passive M_dump() collection, and optional Markdown renderer plugin.
     *
     * @param array<string, mixed> $options
     */
    function j0_i18n_config(array $options): void
    {
        $store = &j0_i18n_store();
        if (isset($options['langs']) && is_array($options['langs']) && $options['langs'] !== []) {
            $store['langs'] = array_values(array_map(static fn(mixed $l): string => strtolower(trim((string) $l)), $options['langs']));
        }
        if (array_key_exists('provider', $options)) {
            $store['provider'] = is_callable($options['provider']) ? $options['provider'] : null;
        }
        if (array_key_exists('override', $options)) {
            $store['override'] = is_string($options['override']) && $options['override'] !== '' ? strtolower(trim($options['override'])) : null;
        }
        if (isset($options['unit_dirs']) && is_array($options['unit_dirs'])) {
            foreach ($options['unit_dirs'] as $dir) {
                $clean = rtrim(str_replace(chr(92), '/', (string) $dir), '/');
                if ($clean !== '' && !in_array($clean, $store['unit_dirs'], true)) {
                    $store['unit_dirs'][] = $clean;
                    $store['dicts'] = [];
                }
            }
        }
        if (array_key_exists('collect_enabled', $options)) {
            $store['collect_enabled'] = (bool) $options['collect_enabled'];
        }
        if (array_key_exists('md_plugin', $options)) {
            $store['md_plugin'] = is_callable($options['md_plugin']) ? $options['md_plugin'] : null;
        }
    }
}

if (!function_exists('j0_get_userlang')) {
    /**
     * Resolves active language: provider -> override -> j0_ui cookie -> Accept-Language -> primary lang.
     */
    function j0_get_userlang(): string
    {
        $store = &j0_i18n_store();
        $supported = $store['langs'];
        $primary = $supported[0] ?? 'en';

        if (is_callable($store['provider'])) {
            $cand = strtolower(trim((string) ($store['provider'])()));
            if ($cand !== '' && in_array($cand, $supported, true)) {
                return $cand;
            }
        }

        if (is_string($store['override']) && in_array($store['override'], $supported, true)) {
            return $store['override'];
        }

        if (function_exists('j0_vfs_read_ui_cookie')) {
            $ui = j0_vfs_read_ui_cookie(['l' => ['key' => 'lang']]);
            $cookieLang = strtolower(trim((string) ($ui['lang'] ?? '')));
            if ($cookieLang !== '' && in_array($cookieLang, $supported, true)) {
                return $cookieLang;
            }
        }

        $header = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        if ($header !== '') {
            foreach (explode(',', $header) as $part) {
                $code = trim(explode(';', $part, 2)[0]);
                $short = explode('-', $code, 2)[0];
                if (in_array($code, $supported, true)) {
                    return $code;
                }
                if (in_array($short, $supported, true)) {
                    return $short;
                }
            }
        }

        return $primary;
    }
}

if (!function_exists('j0_i18n_interpolate')) {
    /**
     * Isomorphic placeholder interpolation supporting {name} and {0}.
     * Unresolved placeholders remain literal.
     *
     * @param array<int|string, mixed> $vars
     */
    function j0_i18n_interpolate(string $text, array $vars = []): string
    {
        if ($vars === [] || !str_contains($text, '{')) {
            return $text;
        }
        return (string) preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', static function (array $m) use ($vars): string {
            $key = $m[1];
            if (array_key_exists($key, $vars)) {
                return (string) $vars[$key];
            }
            if (ctype_digit($key) && array_key_exists((int) $key, $vars)) {
                return (string) $vars[(int) $key];
            }
            return $m[0];
        }, $text);
    }
}

if (!function_exists('L')) {
    /**
     * Short UI string translator supporting positional L('Save', 'Guardar') and associative L(['en' => 'Save']).
     *
     * @param string|array<string, string> $primary
     */
    function L(string|array $primary, mixed ...$args): string
    {
        $store = &j0_i18n_store();
        $supported = $store['langs'];
        $primaryLang = $supported[0] ?? 'en';

        if (is_array($primary)) {
            $vars = (isset($args[0]) && is_array($args[0])) ? $args[0] : [];
            $lang = (isset($args[1]) && is_string($args[1]) && $args[1] !== '') ? strtolower($args[1]) : j0_get_userlang();
            $text = $primary[$lang] ?? $primary[$primaryLang] ?? (string) (reset($primary) ?: '');
            return j0_i18n_interpolate((string) $text, $vars);
        }

        $vars = [];
        if ($args !== [] && is_array(end($args))) {
            $vars = (array) array_pop($args);
        }
        $values = array_merge([$primary], $args);
        $lang = j0_get_userlang();
        $idx = array_search($lang, $supported, true);
        $selected = ($idx !== false && isset($values[$idx]) && is_string($values[$idx])) ? $values[$idx] : $primary;

        return j0_i18n_interpolate($selected, $vars);
    }
}

if (!function_exists('j0_i18n_load_dict')) {
    /**
     * @return array<string, mixed>
     */
    function j0_i18n_load_dict(string $lang): array
    {
        $store = &j0_i18n_store();
        if (isset($store['dicts'][$lang])) {
            return $store['dicts'][$lang];
        }

        $merged = [];
        foreach ($store['unit_dirs'] as $dir) {
            $file = $dir . '/i18n/' . $lang . '.json';
            if (!is_file($file) || !is_readable($file)) {
                continue;
            }
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $merged = array_replace($merged, $decoded);
            }
        }

        $store['dicts'][$lang] = $merged;
        return $merged;
    }
}

if (!function_exists('M')) {
    /**
     * Long/structured content translator backed by unit-colocated JSON dictionaries (id + source_hash).
     *
     * @param array<int|string, mixed> $vars
     * @param array<string, mixed> $options
     */
    function M(string $id, string $source = '', array $vars = [], array $options = []): string
    {
        $store = &j0_i18n_store();
        $lang = isset($options['lang']) && is_string($options['lang']) ? strtolower($options['lang']) : j0_get_userlang();
        $primaryLang = $store['langs'][0] ?? 'en';
        $format = (string) ($options['format'] ?? 'text');

        if ($store['collect_enabled']) {
            $store['collector'][$id] = [
                'id'          => $id,
                'source'      => $source,
                'source_hash' => substr(sha1($source), 0, 12),
                'format'      => $format,
            ];
        }

        $dict = j0_i18n_load_dict($lang);
        $entry = $dict[$id] ?? null;
        if ($entry === null && $lang !== $primaryLang) {
            $primaryDict = j0_i18n_load_dict($primaryLang);
            $entry = $primaryDict[$id] ?? null;
        }

        if (is_string($entry)) {
            return j0_i18n_interpolate($entry, $vars);
        }
        if (is_array($entry)) {
            $raw = (string) ($entry['rendered'] ?? $entry['text'] ?? $entry['source'] ?? ($source !== '' ? $source : $id));
            return j0_i18n_interpolate($raw, $vars);
        }

        $fallback = $source !== '' ? $source : $id;
        if ($format === 'markdown' && is_callable($store['md_plugin'])) {
            $fallback = (string) ($store['md_plugin'])($fallback);
        }

        return j0_i18n_interpolate($fallback, $vars);
    }
}

if (!function_exists('M_dump')) {
    /**
     * Returns passively collected M() entries for zero-tooling dictionary extraction.
     *
     * @return string|array<string, array<string, string>>
     */
    function M_dump(bool $asJson = true): string|array
    {
        $store = &j0_i18n_store();
        if (!$asJson) {
            return $store['collector'];
        }
        return (string) json_encode($store['collector'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}