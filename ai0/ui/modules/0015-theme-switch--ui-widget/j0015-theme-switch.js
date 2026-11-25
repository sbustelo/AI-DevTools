// PATH: ai0/ui/modules/0015-theme-switch--ui-widget/j0015-theme-switch.js
(function () {
    'use strict';

    const STORAGE_KEY = 'theme';
    const DEFAULT_THEME = 'dark';

    function normalizeTheme(value) {
        return value === 'light' ? 'light' : 'dark';
    }

    function syncSwitchAria(theme) {
        const isDark = theme === 'dark';
        document.querySelectorAll('[data-j0-action="ui:toggle-theme"]').forEach(function (el) {
            el.setAttribute('aria-checked', isDark ? 'true' : 'false');
        });
    }

    function applyTheme(rawTheme, persist) {
        const theme = normalizeTheme(rawTheme);
        document.documentElement.setAttribute('data-theme', theme);
        syncSwitchAria(theme);

        if (persist && globalThis.j0 && globalThis.j0.vfs) {
            globalThis.j0.vfs.set(STORAGE_KEY, theme, { profile: 'ui-sync' });
        }
    }

    if (globalThis.j0 && globalThis.j0.vfs) {
        globalThis.j0.vfs.registerUiSync(STORAGE_KEY, {
            shortKey: 't',
            encode: function (val) {
                return val === 'light' ? 'l' : 'd';
            },
            decode: function (val) {
                return val === 'l' ? 'light' : 'dark';
            }
        });

        const storedTheme = globalThis.j0.vfs.get(STORAGE_KEY, null);
        const domTheme = document.documentElement.getAttribute('data-theme') || DEFAULT_THEME;
        applyTheme(storedTheme !== null ? storedTheme : domTheme, false);
    } else {
        applyTheme(document.documentElement.getAttribute('data-theme') || DEFAULT_THEME, false);
    }

    if (globalThis.j0 && globalThis.j0.bus) {
        globalThis.j0.bus.on('ui:toggle-theme', function () {
            const current = normalizeTheme(document.documentElement.getAttribute('data-theme') || DEFAULT_THEME);
            const next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next, true);
        });

        globalThis.j0.bus.on('j0:vfs:changed', function (payload) {
            if (payload && payload.scope === 'kv' && payload.key === STORAGE_KEY && globalThis.j0.vfs) {
                const next = globalThis.j0.vfs.get(STORAGE_KEY, DEFAULT_THEME);
                applyTheme(next, false);
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        const trigger = event.target.closest('[role="switch"][data-j0-action]');
        if (!trigger) {
            return;
        }
        event.preventDefault();
        const action = trigger.getAttribute('data-j0-action');
        if (action && globalThis.j0 && globalThis.j0.bus) {
            globalThis.j0.bus.emit(action, { trigger: trigger, originalEvent: event });
        }
    });
})();