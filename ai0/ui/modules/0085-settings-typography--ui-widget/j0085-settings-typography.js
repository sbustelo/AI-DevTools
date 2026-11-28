// PATH: ai0/ui/modules/0085-settings-typography--ui-widget/j0085-settings-typography.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    if (typeof document === 'undefined') {
        return;
    }

    const docEl = document.documentElement;
    const KEYS = {
        ui: 'ai0_font_ui',
        listing: 'ai0_font_listing',
        size: 'ai0_root_size',
        weight: 'ai0_weight_normal'
    };

    function getLocalTs(key) {
        try {
            const raw = root.localStorage ? root.localStorage.getItem('__j0t_' + key) : null;
            return raw ? parseInt(raw, 10) || 0 : 0;
        } catch (e) {
            return 0;
        }
    }

    function readPref(key) {
        if (root.j0 && root.j0.vfs && typeof root.j0.vfs.get === 'function') {
            const raw = root.j0.vfs.get(key, null);
            if (raw === null || raw === '') {
                return null;
            }
            try {
                return JSON.parse(raw);
            } catch (e) {
                return raw;
            }
        }
        return null;
    }

    function writePrefLocal(key, value) {
        if (!root.j0 || !root.j0.vfs) {
            return;
        }
        if (value === null || value === '') {
            root.j0.vfs.remove(key);
        } else {
            const serialized = typeof value === 'object' ? JSON.stringify(value) : String(value);
            root.j0.vfs.set(key, serialized);
        }
    }

    function pushPrefsToDisk(patchMap) {
        const apiUrl = docEl.getAttribute('data-ai0-prefs-api') || '';
        if (!apiUrl || typeof fetch !== 'function') {
            return;
        }
        fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prefs: patchMap })
        }).catch(function () { /* non-blocking disk sync */ });
    }

    function applyFontVar(slot, entry) {
        const varName = slot === 'listing' ? '--ai0-font-listing' : '--ipax-font-ui';
        if (entry && entry.family) {
            if (entry.url && root.ai0 && root.ai0.fonts) {
                root.ai0.fonts.loadSheet(entry.url);
            }
            docEl.style.setProperty(varName, entry.family);
        } else {
            docEl.style.removeProperty(varName);
        }
        const combo = document.querySelector('[data-ai0-font-combo="' + slot + '"]');
        const input = combo ? combo.querySelector('[data-ai0-font-input]') : null;
        if (input) {
            input.value = entry && entry.name ? entry.name : '';
        }
    }

    function applySize(val) {
        const num = parseInt(String(val || ''), 10);
        if (num >= 12 && num <= 22) {
            docEl.style.setProperty('--ipax-0-root-size', num + 'px');
        } else {
            docEl.style.removeProperty('--ipax-0-root-size');
        }
        const slider = document.querySelector('[data-ai0-root-size]');
        const readout = document.querySelector('[data-ai0-size-readout]');
        const shown = (num >= 12 && num <= 22) ? num : 16;
        if (slider) slider.value = String(shown);
        if (readout) readout.textContent = shown + 'px';
    }

    function applyWeight(val) {
        const num = parseInt(String(val || ''), 10);
        if (num >= 200 && num <= 600) {
            docEl.style.setProperty('--ipax-0-weight-normal', String(num));
        } else {
            docEl.style.removeProperty('--ipax-0-weight-normal');
        }
        const slider = document.querySelector('[data-ai0-weight-normal]');
        const readout = document.querySelector('[data-ai0-weight-readout]');
        const shown = (num >= 200 && num <= 600) ? num : 400;
        if (slider) slider.value = String(shown);
        if (readout) readout.textContent = String(shown);
    }

    function applyAllFromLocal() {
        applyFontVar('ui', readPref(KEYS.ui));
        applyFontVar('listing', readPref(KEYS.listing));
        applySize(readPref(KEYS.size));
        applyWeight(readPref(KEYS.weight));
    }

    function reconcileWithDisk() {
        const apiUrl = docEl.getAttribute('data-ai0-prefs-api') || '';
        if (!apiUrl || typeof fetch !== 'function') {
            return;
        }
        fetch(apiUrl, { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || !data.ok || !data.prefs || typeof data.prefs !== 'object') {
                    return;
                }
                const toPush = {};
                let hasPush = false;
                Object.keys(KEYS).forEach(function (k) {
                    const key = KEYS[k];
                    const localVal = readPref(key);
                    const localTs = getLocalTs(key);
                    const remote = data.prefs[key];
                    const remoteTs = remote && typeof remote.updatedAt === 'number' ? remote.updatedAt : 0;
                    if (remote && remoteTs > localTs) {
                        writePrefLocal(key, remote.value);
                    } else if (localVal !== null && localTs > remoteTs) {
                        toPush[key] = { value: localVal, updatedAt: localTs };
                        hasPush = true;
                    }
                });
                applyAllFromLocal();
                if (hasPush) {
                    pushPrefsToDisk(toPush);
                }
            })
            .catch(function () { /* offline or unavailable */ });
    }

    function renderFontMenu(combo, query) {
        const menu = combo.querySelector('[data-ai0-font-menu]');
        if (!menu || !root.ai0 || !root.ai0.fonts) {
            return;
        }
        const q = String(query || '').trim().toLowerCase();
        menu.textContent = '';
        const addBtn = function (label, attr, val) {
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'j0ui-menu-item';
            btn.setAttribute(attr, val);
            btn.textContent = label;
            li.appendChild(btn);
            menu.appendChild(li);
        };
        if (!q) {
            addBtn('Default (reset)', 'data-ai0-font-reset', '');
        }
        let exact = false;
        root.ai0.fonts.CATALOG.forEach(function (group) {
            const matches = group.items.filter(function (it) {
                if (it.name.toLowerCase() === q) exact = true;
                return !q || it.name.toLowerCase().indexOf(q) >= 0;
            });
            if (matches.length === 0) {
                return;
            }
            const titleLi = document.createElement('li');
            titleLi.className = 'j0ui-font-menu-title';
            titleLi.textContent = group.group;
            menu.appendChild(titleLi);
            matches.forEach(function (it) { addBtn(it.name, 'data-ai0-font-pick', it.name); });
        });
        if (q && !exact) {
            addBtn('Load "' + query.trim() + '" (Local / Google Fonts)', 'data-ai0-font-pick', query.trim());
        }
        menu.setAttribute('data-j0-hidden', 'false');
    }

    async function pickFont(slot, query) {
        const statusEl = document.querySelector('[data-ai0-font-status="' + slot + '"]');
        if (statusEl) statusEl.textContent = 'Loading...';
        const res = await root.ai0.fonts.load(query, { fallback: slot === 'listing' ? 'mono' : 'sans' });
        if (!res.ok) {
            if (statusEl) statusEl.textContent = 'Font not found.';
            return;
        }
        const entry = { name: res.name, family: res.family, url: res.url };
        const key = slot === 'listing' ? KEYS.listing : KEYS.ui;
        writePrefLocal(key, entry);
        applyFontVar(slot, entry);
        const payload = {};
        payload[key] = { value: entry, updatedAt: Date.now() };
        pushPrefsToDisk(payload);
        if (statusEl) statusEl.textContent = 'Applied (' + res.source + ').';
    }

    document.addEventListener('click', function (ev) {
        const combo = ev.target.closest ? ev.target.closest('[data-ai0-font-combo]') : null;
        document.querySelectorAll('[data-ai0-font-menu]').forEach(function (m) {
            if (!combo || !combo.contains(m)) {
                m.setAttribute('data-j0-hidden', 'true');
            }
        });
        if (combo) {
            const slot = combo.getAttribute('data-ai0-font-combo') || 'ui';
            const pickBtn = ev.target.closest('[data-ai0-font-pick]');
            const resetBtn = ev.target.closest('[data-ai0-font-reset]');
            if (pickBtn) {
                combo.querySelector('[data-ai0-font-menu]')?.setAttribute('data-j0-hidden', 'true');
                pickFont(slot, pickBtn.getAttribute('data-ai0-font-pick') || '');
            } else if (resetBtn) {
                const key = slot === 'listing' ? KEYS.listing : KEYS.ui;
                writePrefLocal(key, null);
                applyFontVar(slot, null);
                const payload = {};
                payload[key] = { value: null, updatedAt: Date.now() };
                pushPrefsToDisk(payload);
                combo.querySelector('[data-ai0-font-menu]')?.setAttribute('data-j0-hidden', 'true');
            } else if (ev.target.closest('[data-ai0-font-input]')) {
                renderFontMenu(combo, '');
            }
            return;
        }
        if (ev.target.closest && ev.target.closest('[data-ai0-action="typography:reset"]')) {
            const now = Date.now();
            const payload = {};
            Object.keys(KEYS).forEach(function (k) {
                writePrefLocal(KEYS[k], null);
                payload[KEYS[k]] = { value: null, updatedAt: now };
            });
            applyAllFromLocal();
            pushPrefsToDisk(payload);
        }
    });

    document.addEventListener('input', function (ev) {
        const fontInput = ev.target.closest ? ev.target.closest('[data-ai0-font-input]') : null;
        if (fontInput) {
            const combo = fontInput.closest('[data-ai0-font-combo]');
            if (combo) renderFontMenu(combo, fontInput.value);
            return;
        }
        const sizeSlider = ev.target.closest ? ev.target.closest('[data-ai0-root-size]') : null;
        if (sizeSlider) {
            const val = parseInt(sizeSlider.value, 10);
            writePrefLocal(KEYS.size, val);
            applySize(val);
            const payload = {};
            payload[KEYS.size] = { value: val, updatedAt: Date.now() };
            pushPrefsToDisk(payload);
            return;
        }
        const weightSlider = ev.target.closest ? ev.target.closest('[data-ai0-weight-normal]') : null;
        if (weightSlider) {
            const val = parseInt(weightSlider.value, 10);
            writePrefLocal(KEYS.weight, val);
            applyWeight(val);
            const payload = {};
            payload[KEYS.weight] = { value: val, updatedAt: Date.now() };
            pushPrefsToDisk(payload);
        }
    });

    document.addEventListener('keydown', function (ev) {
        const fontInput = ev.target.closest ? ev.target.closest('[data-ai0-font-input]') : null;
        if (!fontInput) return;
        const combo = fontInput.closest('[data-ai0-font-combo]');
        if (ev.key === 'Escape' && combo) {
            combo.querySelector('[data-ai0-font-menu]')?.setAttribute('data-j0-hidden', 'true');
        } else if (ev.key === 'Enter' && combo && fontInput.value.trim()) {
            ev.preventDefault();
            combo.querySelector('[data-ai0-font-menu]')?.setAttribute('data-j0-hidden', 'true');
            pickFont(combo.getAttribute('data-ai0-font-combo') || 'ui', fontInput.value.trim());
        }
    });

    applyAllFromLocal();
    reconcileWithDisk();
})();