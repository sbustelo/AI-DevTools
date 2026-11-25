// PATH: ai0/j0/src/vfs/j0010-vfs-core.js
(function () {
    'use strict';

    if (!globalThis.j0 || !globalThis.j0.register || !globalThis.j0.bus) {
        return;
    }
    if (globalThis.j0.vfs && globalThis.j0.vfs.kv) {
        return;
    }

    const j0 = globalThis.j0;
    const drivers = new Map();
    const uiSyncMap = new Map();
    const memoryFallback = new Map();
    let activeMode = 'local';

    function readUiCookie() {
        if (typeof document === 'undefined') {
            return {};
        }
        const match = document.cookie.match(/(?:^|;\s*)j0_ui=([^;]+)/);
        if (!match) {
            return {};
        }
        const decoded = decodeURIComponent(match[1]);
        const state = {};
        decoded.split('|').forEach((pair) => {
            const idx = pair.indexOf(':');
            if (idx <= 0) {
                return;
            }
            const shortKey = pair.slice(0, idx);
            const rawVal = pair.slice(idx + 1);
            uiSyncMap.forEach((spec, fullKey) => {
                if (spec.shortKey === shortKey) {
                    state[fullKey] = spec.decode(rawVal);
                }
            });
        });
        return state;
    }

    function writeUiCookie() {
        if (typeof document === 'undefined') {
            return;
        }
        const parts = [];
        uiSyncMap.forEach((spec, fullKey) => {
            const val = kv.get(fullKey, null);
            if (val !== null) {
                const encoded = spec.encode(val);
                if (encoded !== null && encoded !== undefined && encoded !== '') {
                    parts.push(`${spec.shortKey}:${encoded}`);
                }
            }
        });
        const base = j0.basePath || '/';
        const maxAge = parts.length > 0 ? 31536000 : 0;
        document.cookie = `j0_ui=${encodeURIComponent(parts.join('|'))}; path=${base}; max-age=${maxAge}; SameSite=Lax`;
    }

    function reconcileUiSync() {
        const cookieValues = readUiCookie();
        Object.entries(cookieValues).forEach(([fullKey, val]) => {
            if (kv.get(fullKey, null) !== val) {
                try {
                    globalThis.localStorage.setItem(fullKey, String(val));
                } catch (e) {
                    memoryFallback.set(fullKey, String(val));
                }
            }
        });
    }

    const kv = {
        get(key, defaultValue = null, maxAgeMs = 0) {
            try {
                if (maxAgeMs > 0) {
                    const tsRaw = globalThis.localStorage.getItem('__j0t_' + key);
                    if (tsRaw && Date.now() - parseInt(tsRaw, 10) > maxAgeMs) {
                        kv.remove(key);
                        return defaultValue;
                    }
                }
                const val = globalThis.localStorage.getItem(key);
                return val !== null ? val : defaultValue;
            } catch (e) {
                return memoryFallback.has(key) ? memoryFallback.get(key) : defaultValue;
            }
        },

        set(key, value, options = {}) {
            const strVal = String(value);
            try {
                globalThis.localStorage.setItem(key, strVal);
                globalThis.localStorage.setItem('__j0t_' + key, String(Date.now()));
            } catch (e) {
                memoryFallback.set(key, strVal);
                if (e && (e.name === 'QuotaExceededError' || e.code === 22)) {
                    j0.bus.emit('j0:vfs:quota-exceeded', { key });
                }
            }
            if (options.profile === 'ui-sync' || uiSyncMap.has(key)) {
                writeUiCookie();
            }
            j0.bus.emit('j0:vfs:changed', { scope: 'kv', key });
            return true;
        },

        remove(key) {
            try {
                globalThis.localStorage.removeItem(key);
                globalThis.localStorage.removeItem('__j0t_' + key);
            } catch (e) {
                memoryFallback.delete(key);
            }
            if (uiSyncMap.has(key)) {
                writeUiCookie();
            }
            j0.bus.emit('j0:vfs:changed', { scope: 'kv', key });
        }
    };

    const vfs = {
        kv,
        get: (key, def = null, maxAgeMs = 0) => kv.get(key, def, maxAgeMs),
        set: (key, val, opts = {}) => kv.set(key, val, opts),
        remove: (key) => kv.remove(key),

        registerUiSync(key, spec) {
            if (!key || !spec || !spec.shortKey) {
                return;
            }
            uiSyncMap.set(key, {
                shortKey: String(spec.shortKey),
                encode: typeof spec.encode === 'function' ? spec.encode : (v) => String(v),
                decode: typeof spec.decode === 'function' ? spec.decode : (v) => String(v)
            });
            reconcileUiSync();
        },

        registerDriver(name, driver) {
            drivers.set(name, driver);
            j0.bus.emit('j0:vfs:driver-registered', { name });
        },

        getDriver(name) {
            return drivers.get(name) ?? null;
        },

        mode() {
            return activeMode;
        },

        setMode(nextMode) {
            if (activeMode === nextMode) {
                return;
            }
            activeMode = nextMode;
            j0.bus.emit('j0:vfs:mode-changed', { mode: activeMode });
        }
    };

    j0.bus.schema['j0:vfs:changed'] = { scope: 'string', key: 'string' };
    j0.bus.schema['j0:vfs:quota-exceeded'] = { key: 'string' };
    j0.bus.schema['j0:vfs:driver-registered'] = { name: 'string' };
    j0.bus.schema['j0:vfs:mode-changed'] = { mode: 'string' };

    j0.vfs = vfs;
    j0.register('vfs', vfs);
})();