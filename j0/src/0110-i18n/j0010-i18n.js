// PATH: ai0/j0/src/0110-i18n/j0010-i18n.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    root.j0 = root.j0 || {};
    const j0 = root.j0;

    if (j0.i18n && typeof j0.L === 'function') {
        return;
    }

    const state = {
        langs: ['en'],
        lang: null,
        dicts: Object.create(null),
        collector: Object.create(null),
        collectEnabled: false
    };

    function getUserLang(el) {
        if (el && typeof el.closest === 'function') {
            const scoped = el.closest('[lang]');
            const scopedLang = scoped ? scoped.getAttribute('lang') : '';
            if (scopedLang) {
                return scopedLang.trim().toLowerCase();
            }
        }
        if (typeof document !== 'undefined' && document.documentElement) {
            const htmlLang = document.documentElement.getAttribute('lang');
            if (htmlLang) {
                return htmlLang.trim().toLowerCase();
            }
        }
        return state.lang || state.langs[0] || 'en';
    }

    function interpolate(text, vars) {
        const str = String(text ?? '');
        if (!vars || typeof vars !== 'object' || str.indexOf('{') === -1) {
            return str;
        }
        return str.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, key) {
            if (Object.prototype.hasOwnProperty.call(vars, key)) {
                return String(vars[key]);
            }
            return match;
        });
    }

    function L(primary) {
        const args = Array.prototype.slice.call(arguments, 1);
        const primaryLang = state.langs[0] || 'en';

        if (primary && typeof primary === 'object' && !Array.isArray(primary)) {
            const vars = (args[0] && typeof args[0] === 'object') ? args[0] : null;
            const ctx = args[1];
            const lang = typeof ctx === 'string' && ctx !== '' ? ctx.toLowerCase() : getUserLang(ctx);
            const firstKey = Object.keys(primary)[0];
            const val = primary[lang] ?? primary[primaryLang] ?? (firstKey ? primary[firstKey] : '');
            return interpolate(val, vars);
        }

        let vars = null;
        if (args.length > 0 && args[args.length - 1] && typeof args[args.length - 1] === 'object') {
            vars = args.pop();
        }
        const values = [primary].concat(args);
        const lang = getUserLang(null);
        const idx = state.langs.indexOf(lang);
        const selected = (idx !== -1 && typeof values[idx] === 'string') ? values[idx] : String(primary ?? '');
        return interpolate(selected, vars);
    }

    function M(id, source, vars, opts) {
        const options = opts && typeof opts === 'object' ? opts : {};
        const src = typeof source === 'string' ? source : '';
        const lang = typeof options.lang === 'string' && options.lang !== ''
            ? options.lang.toLowerCase()
            : getUserLang(options.el || null);
        const primaryLang = state.langs[0] || 'en';

        if (state.collectEnabled) {
            state.collector[id] = { id: id, source: src, format: options.format || 'text' };
        }

        const langDict = state.dicts[lang];
        const primaryDict = state.dicts[primaryLang];
        const entry = (langDict && langDict[id] !== undefined)
            ? langDict[id]
            : (primaryDict && primaryDict[id] !== undefined ? primaryDict[id] : null);

        if (typeof entry === 'string') {
            return interpolate(entry, vars);
        }
        if (entry && typeof entry === 'object') {
            const text = entry.rendered ?? entry.text ?? entry.source ?? (src || id);
            return interpolate(text, vars);
        }

        return interpolate(src || id, vars);
    }

    const api = {
        configure: function (opts) {
            if (!opts || typeof opts !== 'object') {
                return;
            }
            if (Array.isArray(opts.langs) && opts.langs.length > 0) {
                state.langs = opts.langs.map(function (l) {
                    return String(l).trim().toLowerCase();
                });
            }
            if (typeof opts.lang === 'string' && opts.lang !== '') {
                state.lang = opts.lang.trim().toLowerCase();
            }
            if (typeof opts.collectEnabled === 'boolean') {
                state.collectEnabled = opts.collectEnabled;
            }
        },
        loadDict: function (lang, dict) {
            if (!lang || !dict || typeof dict !== 'object') {
                return;
            }
            const key = String(lang).trim().toLowerCase();
            state.dicts[key] = Object.assign(state.dicts[key] || {}, dict);
            if (j0.bus && typeof j0.bus.emit === 'function') {
                j0.bus.emit('j0:i18n:loaded', { lang: key });
            }
        },
        getUserLang: getUserLang,
        interpolate: interpolate,
        L: L,
        M: M,
        M_dump: function () {
            return JSON.stringify(state.collector, null, 2);
        }
    };

    j0.i18n = api;
    j0.L = L;
    j0.M = M;
    j0.getUserLang = getUserLang;

    if (typeof j0.register === 'function') {
        j0.register('i18n', api);
    }
})();