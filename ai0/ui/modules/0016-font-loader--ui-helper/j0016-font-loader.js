// PATH: ai0/ui/modules/0016-font-loader--ui-helper/j0016-font-loader.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    if (typeof document === 'undefined') {
        return;
    }

    const FALLBACK_SANS = "system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
    const FALLBACK_MONO = "ui-monospace, 'SF Mono', Menlo, Monaco, Consolas, monospace";
    const GENERICS = new Set(['system-ui', '-apple-system', 'sans-serif', 'serif', 'monospace', 'ui-sans-serif', 'ui-serif', 'ui-monospace', 'cursive', 'fantasy']);
    const loadedUrls = new Set();

    function googleEntry(name, kind, range) {
        const generic = kind === 'serif' ? 'serif' : (kind === 'mono' ? 'monospace' : 'sans-serif');
        return {
            name: name,
            kind: kind,
            family: "'" + name + "', " + generic,
            url: 'https://fonts.googleapis.com/css2?family=' + name.trim().replace(/\s+/g, '+') + ':wght@' + range + '&display=swap'
        };
    }

    const CATALOG = [
        {
            group: 'System',
            items: [
                { name: 'System UI', kind: 'sans', family: FALLBACK_SANS },
                { name: 'System Mono', kind: 'mono', family: FALLBACK_MONO },
                { name: 'Georgia', kind: 'serif', family: "Georgia, 'Times New Roman', serif" },
                { name: 'Verdana', kind: 'sans', family: 'Verdana, Geneva, sans-serif' }
            ]
        },
        {
            group: 'Google Fonts (variable)',
            items: [
                googleEntry('Inter', 'sans', '100..900'),
                googleEntry('Public Sans', 'sans', '100..900'),
                googleEntry('Roboto Flex', 'sans', '100..1000'),
                googleEntry('Open Sans', 'sans', '300..800'),
                googleEntry('Source Sans 3', 'sans', '200..900'),
                googleEntry('DM Sans', 'sans', '100..1000'),
                googleEntry('Manrope', 'sans', '200..800'),
                googleEntry('Plus Jakarta Sans', 'sans', '200..800'),
                googleEntry('Figtree', 'sans', '300..900'),
                googleEntry('Lexend', 'sans', '100..900'),
                googleEntry('Space Grotesk', 'sans', '300..700'),
                googleEntry('JetBrains Mono', 'mono', '100..800'),
                googleEntry('Fira Code', 'mono', '300..700'),
                googleEntry('IBM Plex Mono', 'mono', '300..700'),
                googleEntry('Merriweather', 'serif', '300..900')
            ]
        }
    ];

    function findInCatalog(name) {
        const clean = String(name || '').trim().toLowerCase();
        for (let i = 0; i < CATALOG.length; i++) {
            const items = CATALOG[i].items;
            for (let k = 0; k < items.length; k++) {
                if (items[k].name.toLowerCase() === clean) {
                    return items[k];
                }
            }
        }
        return null;
    }

    function isInstalled(family) {
        const clean = String(family || '').trim().replace(/^['"]|['"]$/g, '');
        if (!clean || GENERICS.has(clean.toLowerCase())) {
            return true;
        }
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) {
            return false;
        }
        const sample = 'abcdefghijklmnopqrstuvwxyz0123456789';
        ctx.font = '72px monospace';
        const baseMono = ctx.measureText(sample).width;
        ctx.font = "72px '" + clean + "', monospace";
        const testMono = ctx.measureText(sample).width;
        ctx.font = '72px sans-serif';
        const baseSans = ctx.measureText(sample).width;
        ctx.font = "72px '" + clean + "', sans-serif";
        const testSans = ctx.measureText(sample).width;
        return baseMono !== testMono || baseSans !== testSans;
    }

    function loadSheet(url, timeoutMs) {
        const ms = typeof timeoutMs === 'number' && timeoutMs > 0 ? timeoutMs : 8000;
        return new Promise(function (resolve) {
            if (!url) {
                resolve(false);
                return;
            }
            if (loadedUrls.has(url)) {
                resolve(true);
                return;
            }
            const existing = document.head.querySelector('link[rel="stylesheet"][href="' + url + '"]');
            if (existing) {
                loadedUrls.add(url);
                resolve(true);
                return;
            }
            const link = document.createElement('link');
            let timer = 0;
            function finish(ok) {
                clearTimeout(timer);
                if (ok) {
                    loadedUrls.add(url);
                } else {
                    link.remove();
                }
                resolve(ok);
            }
            timer = setTimeout(function () { finish(false); }, ms);
            link.rel = 'stylesheet';
            link.href = url;
            link.onload = function () { finish(true); };
            link.onerror = function () { finish(false); };
            document.head.appendChild(link);
        });
    }

    function hasLoadedFace(name) {
        if (!document.fonts || typeof document.fonts.load !== 'function') {
            return Promise.resolve(true);
        }
        return document.fonts.load('16px "' + name + '"').then(
            function (faces) { return Array.isArray(faces) && faces.length > 0; },
            function () { return false; }
        );
    }

    function parseCandidates(input) {
        if (Array.isArray(input)) {
            return input.map(function (s) { return String(s || '').trim().replace(/^['"]|['"]$/g, ''); }).filter(Boolean);
        }
        if (typeof input === 'string') {
            return input.split(',').map(function (s) { return s.trim().replace(/^['"]|['"]$/g, ''); }).filter(Boolean);
        }
        return [];
    }

    async function resolveSingle(name, fallbackStack) {
        const parts = name.trim().split(/\s+/);
        while (parts.length > 0) {
            const probe = parts.join(' ');
            if (GENERICS.has(probe.toLowerCase())) {
                return { ok: true, name: probe, family: probe, source: 'generic', url: null };
            }
            const cat = findInCatalog(probe);
            if (cat) {
                if (cat.url && !isInstalled(cat.name)) {
                    const sheetOk = await loadSheet(cat.url);
                    if (sheetOk) {
                        return { ok: true, name: cat.name, family: cat.family, source: 'google', url: cat.url };
                    }
                    parts.pop();
                    continue;
                }
                return { ok: true, name: cat.name, family: cat.family, source: 'local', url: null };
            }
            if (isInstalled(probe)) {
                return { ok: true, name: probe, family: "'" + probe + "', " + fallbackStack, source: 'local', url: null };
            }
            const url = 'https://fonts.googleapis.com/css2?family=' + probe.replace(/\s+/g, '+') + ':wght@300;400;500;600;700&display=swap';
            if (await loadSheet(url) && await hasLoadedFace(probe)) {
                return { ok: true, name: probe, family: "'" + probe + "', " + fallbackStack, source: 'google', url: url };
            }
            parts.pop();
        }
        return null;
    }

    /**
     * Resolves a font name, CSS font-family stack string, or candidate array.
     * Checks local installation first, then injects a Google Fonts <link> if needed.
     */
    async function load(input, options) {
        const opts = options && typeof options === 'object' ? options : {};
        const fallbackStack = opts.fallback === 'mono' ? FALLBACK_MONO : FALLBACK_SANS;
        const candidates = parseCandidates(input);
        for (let i = 0; i < candidates.length; i++) {
            const hit = await resolveSingle(candidates[i], fallbackStack);
            if (hit) {
                return hit;
            }
        }
        return { ok: false, name: '', family: '', source: 'none', url: null };
    }

    root.ai0 = root.ai0 || {};
    root.ai0.fonts = {
        CATALOG: CATALOG,
        load: load,
        loadSheet: loadSheet,
        isInstalled: isInstalled
    };
})();