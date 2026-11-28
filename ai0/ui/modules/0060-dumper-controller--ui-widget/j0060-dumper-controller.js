// PATH: ai0/ui/modules/0060-dumper-controller--ui-widget/j0060-dumper-controller.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    if (typeof document === 'undefined') {
        return;
    }

    const dumperForm = document.querySelector('[data-j0-dumper-form]');
    if (!dumperForm) {
        return;
    }

    const VFS_PREF_KEY = 'ai0_dumper_cat_prefs';
    const VFS_TRACE_KEY = 'ai0_dumper_last_ui_trace';

    function emitBus(eventName, payload) {
        if (root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
            root.j0.bus.emit(eventName, payload);
        }
    }

    function loadPrefs() {
        if (!root.j0 || !root.j0.vfs || typeof root.j0.vfs.get !== 'function') {
            return null;
        }
        const raw = root.j0.vfs.get(VFS_PREF_KEY, null);
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (err) {
            emitBus('dumper:prefs:error', { error: String(err) });
            return null;
        }
    }

    function savePrefs() {
        if (!root.j0 || !root.j0.vfs || typeof root.j0.vfs.set !== 'function') {
            return;
        }
        const prefs = {};
        document.querySelectorAll('[data-j0-cat-check]').forEach(function (cb) {
            const key = cb.getAttribute('data-j0-cat-check');
            if (key) {
                prefs[key] = cb.checked;
            }
        });
        root.j0.vfs.set(VFS_PREF_KEY, JSON.stringify(prefs));
        emitBus('dumper:prefs:saved', { prefs: prefs });
    }

    function applySavedPrefs() {
        const prefs = loadPrefs();
        if (!prefs || typeof prefs !== 'object') {
            return;
        }
        let touched = false;
        Object.entries(prefs).forEach(function (entry) {
            const catId = entry[0];
            const shouldInclude = Boolean(entry[1]);
            const cb = document.querySelector('[data-j0-cat-check="' + catId + '"]');
            if (cb && cb.checked !== shouldInclude) {
                cb.checked = shouldInclude;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
                touched = true;
            }
        });
        if (!touched) {
            emitBus('j0:tree:reconcile', {});
        }
    }

    function recordUiTrace(formEl) {
        const checkedFiles = [];
        if (formEl) {
            formEl.querySelectorAll('input[type="checkbox"][name="selected_files[]"]:checked').forEach(function (cb) {
                checkedFiles.push(cb.value);
            });
        }
        const trace = {
            ts: new Date().toISOString(),
            checked_count: checkedFiles.length,
            sample_paths: checkedFiles.slice(0, 25)
        };
        if (root.j0 && root.j0.vfs && typeof root.j0.vfs.set === 'function') {
            root.j0.vfs.set(VFS_TRACE_KEY, JSON.stringify(trace));
        }
        emitBus('dumper:dump:submit', trace);
    }

    function navigateToRoot(targetRoot) {
        if (!targetRoot) {
            return;
        }
        const url = new URL(root.location.href);
        url.searchParams.delete('scope');
        url.searchParams.delete('scan');
        url.searchParams.delete('root');
        url.searchParams.delete('nav');
        url.searchParams.delete('from');
        url.searchParams.delete('q');
        url.searchParams.set('browse', targetRoot);
        emitBus('dumper:root:change', { root: targetRoot });
        root.location.href = url.toString();
    }

    function restoreActiveTab() {
        const tabGroup = document.querySelector('[data-j0-tab-group="explorer-workspace"]');
        if (!tabGroup || tabGroup.getAttribute('data-j0-server-tab') !== '') {
            return;
        }
        if (!root.j0 || !root.j0.vfs || !root.j0.ui) {
            return;
        }
        const savedTab = root.j0.vfs.get('ai0_explorer_active_tab', '');
        if (!savedTab || !['view', 'dump', 'purge', 'restore'].includes(savedTab)) {
            return;
        }
        const targetBtn = tabGroup.querySelector('[data-j0-target="' + savedTab + '"]');
        if (targetBtn && typeof root.j0.ui.activateItem === 'function' && typeof root.j0.ui.switchPane === 'function') {
            root.j0.ui.activateItem(tabGroup, targetBtn);
            root.j0.ui.switchPane('explorer-workspace', savedTab);
        }
    }

    if (root.j0 && root.j0.bus && typeof root.j0.bus.on === 'function') {
        root.j0.bus.on('j0:dropdown:change', function (payload) {
            if (payload && payload.id === 'ai0-files-root' && payload.value) {
                navigateToRoot(payload.value);
            }
        });
        root.j0.bus.on('system:ready', applySavedPrefs);
    }

    document.addEventListener('dblclick', function (event) {
        if (event.target.closest('input, button, a, [data-j0-row-menu-trigger], [data-j0-tree-toggle]')) {
            return;
        }
        const fileRow = event.target.closest('[data-j0-tree-node][data-j0-node-type="file"] > .j0ui-tree-row, .j0ui-shallow-item[data-j0-node-type="file"] > .j0ui-tree-row');
        if (fileRow) {
            const fileNode = fileRow.closest('[data-j0-node-path]');
            const fileRel = fileNode ? (fileNode.getAttribute('data-j0-node-path') || '') : '';
            if (fileRel !== '') {
                event.preventDefault();
                event.stopPropagation();
                emitBus('ai0:file:open-editor', { rel: fileRel });
            }
            return;
        }
        const shallowDir = event.target.closest('[data-j0-shallow-dir]');
        const row = shallowDir || event.target.closest('[data-j0-tree-node][data-j0-node-type="dir"] > .j0ui-tree-row');
        if (!row) {
            return;
        }
        const node = shallowDir || row.closest('[data-j0-tree-node]');
        const rel = node ? node.getAttribute('data-j0-node-path') : '';
        const rootInput = document.querySelector('[data-j0-root-select]');
        const baseRoot = rootInput ? rootInput.value : (document.querySelector('input[name="browse_root"]')?.value || '');
        if (!rel || !baseRoot) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        const cleanBase = baseRoot.replace(/\/+$/, '');
        const nextRoot = (cleanBase === '' ? '' : cleanBase) + '/' + rel.replace(/^\/+/, '');
        const url = new URL(root.location.href);
        url.searchParams.delete('scope');
        if (shallowDir) {
            url.searchParams.delete('scan');
        }
        url.searchParams.delete('root');
        url.searchParams.delete('nav');
        url.searchParams.delete('from');
        url.searchParams.delete('q');
        url.searchParams.set('browse', nextRoot);
        emitBus('dumper:root:drill', { root: nextRoot, rel: rel });
        root.location.href = url.toString();
    });

    document.addEventListener('change', function (event) {
        if (event.target.closest('[data-j0-cat-check]')) {
            savePrefs();
        }
    });

    document.addEventListener('click', function (event) {
        const dumpBtn = event.target.closest('[data-j0-action="submit-dump"]');
        if (dumpBtn) {
            const compactToggle = document.querySelector('[data-j0-compact-toggle]');
            const compactInput = dumperForm.querySelector('[data-j0-compact-input]');
            if (compactInput && compactToggle) {
                compactInput.value = compactToggle.checked ? '1' : '0';
            }
            recordUiTrace(dumperForm);
            dumperForm.submit();
            return;
        }

        const tabBtn = event.target.closest('[data-j0-tab-group="explorer-workspace"] [data-j0-target]');
        if (tabBtn && root.j0 && root.j0.vfs && typeof root.j0.vfs.set === 'function') {
            const nextTab = tabBtn.getAttribute('data-j0-target');
            if (nextTab) {
                root.j0.vfs.set('ai0_explorer_active_tab', nextTab);
                const url = new URL(root.location.href);
                if (url.searchParams.has('tab')) {
                    url.searchParams.delete('tab');
                    root.history.replaceState(null, '', url.toString());
                }
            }
        }
    });

    dumperForm.addEventListener('submit', function () {
        recordUiTrace(dumperForm);
    });

    restoreActiveTab();
    applySavedPrefs();
})();