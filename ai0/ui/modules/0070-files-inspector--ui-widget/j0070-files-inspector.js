// PATH: ai0/ui/modules/0070-files-inspector--ui-widget/j0070-files-inspector.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    const VFS_SHOW_UNDERSCORE = 'ai0_view_show_underscore';
    const VFS_SHOW_DOT = 'ai0_view_show_dot';

    function formatBytes(bytes) {
        if (!bytes || bytes <= 0) {
            return '0 B';
        }
        if (bytes < 1024) {
            return bytes + ' B';
        }
        if (bytes < 1048576) {
            return (bytes / 1024).toFixed(1) + ' KB (' + bytes + ' B)';
        }
        return (bytes / 1048576).toFixed(2) + ' MB (' + bytes + ' B)';
    }

    function applyVisibilityFilters() {
        const showUnderscoreCb = document.querySelector('[data-j0-view-toggle="underscore"]');
        const showDotCb = document.querySelector('[data-j0-view-toggle="dot"]');
        const showUnderscore = showUnderscoreCb ? showUnderscoreCb.checked : true;
        const showDot = showDotCb ? showDotCb.checked : true;

        document.querySelectorAll('[data-j0-tree-root] [data-j0-tree-node]').forEach(function (node) {
            const name = node.getAttribute('data-j0-node-name') || '';
            const hideUnderscore = !showUnderscore && name.startsWith('_');
            const hideDot = !showDot && name.startsWith('.');
            if (hideUnderscore || hideDot) {
                node.setAttribute('data-j0-view-hidden', 'true');
            } else {
                node.removeAttribute('data-j0-view-hidden');
            }
        });
    }

    function initInspector(hostEl) {
        const apiUrl = hostEl.getAttribute('data-j0-api-url') || '';
        if (!apiUrl) {
            return;
        }

        const getBrowseRoot = function () {
            const sel = document.querySelector('[data-j0-root-select]');
            return sel ? sel.value : (document.querySelector('input[name="browse_root"]')?.value || '');
        };

        let currentRel = '';
        let currentData = null;

        const statusEl = hostEl.querySelector('[data-j0-insp-status]');
        const bigIconEl = hostEl.querySelector('[data-j0-insp-big-icon]');
        const nameTextEl = hostEl.querySelector('[data-j0-insp-name]');
        const kindEl = hostEl.querySelector('[data-j0-insp-kind]');
        const pathEl = hostEl.querySelector('[data-j0-insp-path]');
        const sizeEl = hostEl.querySelector('[data-j0-insp-size]');
        const mtimeEl = hostEl.querySelector('[data-j0-insp-mtime]');
        const itemsRowEl = hostEl.querySelector('[data-j0-insp-items-row]');
        const itemsEl = hostEl.querySelector('[data-j0-insp-items]');
        const catRowEl = hostEl.querySelector('[data-j0-insp-cat-row]');
        const catValEl = hostEl.querySelector('[data-j0-insp-cat-val]');
        const catBadgeEl = hostEl.querySelector('[data-j0-insp-cat-badge]');
        const rootRowEl = hostEl.querySelector('[data-j0-insp-root-row]');
        const rootValEl = hostEl.querySelector('[data-j0-insp-root-val]');
        const rootToggleEl = hostEl.querySelector('[data-j0-insp-root-toggle]');
        const permOwnerEl = hostEl.querySelector('[data-j0-perm="owner"]');
        const permGroupEl = hostEl.querySelector('[data-j0-perm="group"]');
        const permEveryoneEl = hostEl.querySelector('[data-j0-perm="everyone"]');
        const permLockBtn = hostEl.querySelector('[data-j0-perm-lock]');
        const permSaveBtn = hostEl.querySelector('[data-j0-perm-save]');
        const previewSecEl = hostEl.querySelector('[data-j0-insp-preview-section]');
        const previewBoxEl = hostEl.querySelector('[data-j0-insp-preview]');
        const bigIconBtn = hostEl.querySelector('[data-j0-insp-edit-root-icon]');
        const editNameBtnEl = hostEl.querySelector('[data-j0-insp-edit-name]');
        const backRootBtnEl = hostEl.querySelector('[data-j0-insp-back-root]');

        function setStatus(msg, isError) {
            if (!statusEl) {
                return;
            }
            statusEl.textContent = msg || '';
            statusEl.setAttribute('data-j0-hidden', msg ? 'false' : 'true');
            statusEl.className = 'j0ui-alert-inline ' + (isError ? 'j0ui-alert-danger' : 'j0ui-alert-success');
        }

        async function apiCall(payload) {
            const res = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(Object.assign({ browse_root: getBrowseRoot(), rel_path: currentRel }, payload))
            });
            return res.json();
        }

        function setPermsLocked(locked) {
            [permOwnerEl, permGroupEl, permEveryoneEl].forEach(function (el) {
                if (el) {
                    el.disabled = locked;
                }
            });
            if (permSaveBtn) {
                permSaveBtn.setAttribute('data-j0-hidden', locked ? 'true' : 'false');
            }
            if (permLockBtn) {
                permLockBtn.textContent = locked ? '🔒' : '🔓';
                permLockBtn.setAttribute('data-j0-locked', locked ? 'true' : 'false');
            }
        }

        function renderData(data) {
            currentData = data;
            const isDirEditable = Boolean(data.type === 'dir');
            if (bigIconBtn) {
                bigIconBtn.setAttribute('data-j0-editable', isDirEditable ? 'true' : 'false');
            }
            if (editNameBtnEl) {
                editNameBtnEl.setAttribute('data-j0-hidden', data.is_root_dir ? 'true' : 'false');
            }
            if (backRootBtnEl) {
                backRootBtnEl.setAttribute('data-j0-hidden', data.is_root_dir ? 'true' : 'false');
            }
            if (bigIconEl) {
                bigIconEl.setAttribute('data-j0-project-root', (isDirEditable && data.is_project_root) ? 'true' : 'false');
                while (bigIconEl.firstChild) {
                    bigIconEl.removeChild(bigIconEl.firstChild);
                }
                if (isDirEditable && data.root_icon_url) {
                    const img = document.createElement('img');
                    img.src = data.root_icon_url;
                    img.alt = '';
                    bigIconEl.appendChild(img);
                } else {
                    bigIconEl.textContent = isDirEditable
                        ? (data.root_icon || '📁')
                        : '📄';
                }
            }
            if (nameTextEl) {
                nameTextEl.textContent = data.name || '/';
            }
            if (kindEl) {
                kindEl.textContent = data.is_symlink ? 'Symlink' : (data.type === 'dir' ? 'Folder' : 'File');
            }
            if (pathEl) {
                while (pathEl.firstChild) {
                    pathEl.removeChild(pathEl.firstChild);
                }
                if (data.marker_tag && data.marker_dir) {
                    const tagBtn = document.createElement('button');
                    tagBtn.type = 'button';
                    tagBtn.className = 'j0ui-root-tag';
                    tagBtn.setAttribute('data-j0-where-toggle', 'collapsed');
                    tagBtn.setAttribute('data-j0-marker-tag', data.marker_tag);
                    tagBtn.setAttribute('data-j0-marker-dir', data.marker_dir);
                    if (data.marker_icon_url) {
                        tagBtn.setAttribute('data-j0-marker-icon-url', data.marker_icon_url);
                        const img = document.createElement('img');
                        img.className = 'j0ui-tree-icon';
                        img.src = data.marker_icon_url;
                        img.alt = '';
                        tagBtn.appendChild(img);
                        tagBtn.appendChild(document.createTextNode(' ▸'));
                    } else {
                        tagBtn.textContent = data.marker_tag + ' ▸';
                    }
                    pathEl.appendChild(tagBtn);
                    if (data.rel_from_marker) {
                        const restSpan = document.createElement('span');
                        restSpan.textContent = '/' + data.rel_from_marker;
                        pathEl.appendChild(restSpan);
                    }
                } else {
                    pathEl.textContent = data.display_path || '/';
                }
            }
            if (sizeEl) {
                sizeEl.textContent = data.type === 'dir' ? '—' : formatBytes(data.size);
            }
            if (mtimeEl) {
                mtimeEl.textContent = data.mtime_human || '—';
            }
            if (itemsRowEl && itemsEl) {
                const isDir = data.type === 'dir';
                itemsRowEl.setAttribute('data-j0-hidden', isDir ? 'false' : 'true');
                itemsEl.setAttribute('data-j0-hidden', isDir ? 'false' : 'true');
                itemsEl.textContent = String(data.direct_items || 0);
            }
            if (catRowEl && catValEl && catBadgeEl) {
                const hasCat = Boolean(data.cat && data.cat !== 'none');
                catRowEl.setAttribute('data-j0-hidden', hasCat ? 'false' : 'true');
                catValEl.setAttribute('data-j0-hidden', hasCat ? 'false' : 'true');
                catBadgeEl.textContent = hasCat ? (data.cat_label || data.cat) : '';
            }
            if (rootRowEl && rootValEl && rootToggleEl) {
                const isDir = data.type === 'dir';
                rootRowEl.setAttribute('data-j0-hidden', isDir ? 'false' : 'true');
                rootValEl.setAttribute('data-j0-hidden', isDir ? 'false' : 'true');
                rootToggleEl.checked = Boolean(data.is_project_root);
            }
            if (data.permissions) {
                if (permOwnerEl) permOwnerEl.value = data.permissions.owner || 'rw';
                if (permGroupEl) permGroupEl.value = data.permissions.group || 'r';
                if (permEveryoneEl) permEveryoneEl.value = data.permissions.everyone || 'r';
            }
            setPermsLocked(true);

            if (previewSecEl && previewBoxEl) {
                if (typeof data.preview === 'string') {
                    previewSecEl.setAttribute('data-j0-hidden', 'false');
                    previewBoxEl.textContent = data.preview + (data.truncated ? '\n…' : '');
                } else {
                    previewSecEl.setAttribute('data-j0-hidden', 'true');
                    previewBoxEl.textContent = '';
                }
            }

        }

        async function loadItem(relPath) {
            currentRel = relPath || '';
            setStatus('', false);
            try {
                const data = await apiCall({ op: 'inspect' });
                if (data && data.ok) {
                    renderData(data);
                } else {
                    setStatus((data && data.message) || 'Inspect failed.', true);
                }
            } catch (err) {
                setStatus(String(err), true);
            }
        }

        function returnToContainer() {
            document.querySelectorAll('.j0ui-tree-node--selected').forEach(function (el) {
                el.classList.remove('j0ui-tree-node--selected');
            });
            loadItem('');
            if (root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
                root.j0.bus.emit('j0:tree:select', { path: '', name: '', type: 'dir', source: 'inspector' });
            }
        }

        hostEl.addEventListener('click', async function (event) {
            if (event.target.closest('[data-j0-insp-back-root]')) {
                returnToContainer();
                return;
            }

            const editNameBtn = event.target.closest('[data-j0-insp-edit-name]');
            if (editNameBtn && currentData && currentRel !== '') {
                const nextName = root.prompt('New name:', currentData.name || '');
                if (nextName && nextName.trim() !== '' && nextName.trim() !== currentData.name) {
                    const res = await apiCall({ op: 'update_attr', field: 'name', value: nextName.trim() });
                    setStatus(res.message || '', !res.ok);
                    if (res.ok && res.reload) {
                        root.location.reload();
                    }
                }
                return;
            }

            const whereToggle = event.target.closest('[data-j0-where-toggle]');
            if (whereToggle) {
                const isExpanded = whereToggle.getAttribute('data-j0-where-toggle') === 'expanded';
                const tag = whereToggle.getAttribute('data-j0-marker-tag') || '';
                const dir = whereToggle.getAttribute('data-j0-marker-dir') || '';
                const iconUrl = whereToggle.getAttribute('data-j0-marker-icon-url') || '';
                whereToggle.setAttribute('data-j0-where-toggle', isExpanded ? 'collapsed' : 'expanded');
                while (whereToggle.firstChild) {
                    whereToggle.removeChild(whereToggle.firstChild);
                }
                if (isExpanded && iconUrl) {
                    const img = document.createElement('img');
                    img.className = 'j0ui-tree-icon';
                    img.src = iconUrl;
                    img.alt = '';
                    whereToggle.appendChild(img);
                    whereToggle.appendChild(document.createTextNode(' ▸'));
                } else {
                    whereToggle.textContent = isExpanded ? (tag + ' ▸') : (dir + ' ▾');
                }
                return;
            }

            const editRootBtn = event.target.closest('[data-j0-insp-edit-root-icon]');
            if (editRootBtn && currentData && currentData.type === 'dir') {
                if (root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
                    root.j0.bus.emit('ai0:icon-picker:open', {
                        apiUrl: apiUrl,
                        browseRoot: getBrowseRoot(),
                        current: currentData.root_icon || '🏠'
                    });
                }
                return;
            }

            if (event.target.closest('[data-j0-perm-lock]')) {
                const isLocked = permLockBtn && permLockBtn.getAttribute('data-j0-locked') !== 'false';
                setPermsLocked(!isLocked);
                return;
            }

            if (event.target.closest('[data-j0-perm-save]')) {
                const res = await apiCall({
                    op: 'update_attr',
                    field: 'permissions',
                    owner: permOwnerEl ? permOwnerEl.value : 'rw',
                    group: permGroupEl ? permGroupEl.value : 'r',
                    everyone: permEveryoneEl ? permEveryoneEl.value : 'r'
                });
                setStatus(res.message || '', !res.ok);
                if (res.ok) {
                    setPermsLocked(true);
                }
                return;
            }

        });

        if (root.j0 && root.j0.bus && typeof root.j0.bus.on === 'function') {
            root.j0.bus.on('j0:tree:select', function (payload) {
                if (payload && payload.source === 'inspector') {
                    return;
                }
                loadItem(payload && typeof payload.path === 'string' ? payload.path : '');
            });
            root.j0.bus.on('ai0:icon-picker:selected', async function (payload) {
                if (!payload || typeof payload.value !== 'string') {
                    return;
                }
                const res = await apiCall({ op: 'update_attr', field: 'root_icon', value: payload.value });
                if (!res || !res.ok) {
                    setStatus((res && res.message) || 'Failed to update icon.', true);
                    return;
                }
                const fresh = await apiCall({ op: 'inspect' });
                if (fresh && fresh.ok) {
                    renderData(fresh);
                }
                setStatus(res.message || '', false);
                root.j0.bus.emit('ai0:node:icon-changed', {
                    rel: typeof res.rel === 'string' ? res.rel : currentRel,
                    absPath: res.abs_path || '',
                    icon: res.icon || '📁',
                    iconUrl: res.icon_url || ''
                });
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape' || currentRel === '') {
                return;
            }
            const activeTag = document.activeElement ? document.activeElement.tagName : '';
            if (activeTag === 'INPUT' || activeTag === 'TEXTAREA' || activeTag === 'SELECT') {
                return;
            }
            if (document.querySelector('.j0ui-modal-backdrop[data-j0-hidden="false"]')) {
                return;
            }
            returnToContainer();
        });

        hostEl.addEventListener('change', async function (event) {
            const toggle = event.target.closest('[data-j0-insp-root-toggle]');
            if (!toggle || !currentData || currentData.type !== 'dir') {
                return;
            }
            const nextState = toggle.checked;
            const res = await apiCall({ op: 'update_attr', field: 'is_project_root', value: nextState });
            if (!res || !res.ok) {
                toggle.checked = !nextState;
                setStatus((res && res.message) || 'Failed to update Project Root.', true);
                return;
            }
            currentData.is_project_root = nextState;
            const flag = nextState ? 'true' : 'false';
            if (bigIconEl) {
                bigIconEl.setAttribute('data-j0-project-root', flag);
            }
            if (currentRel === '') {
                const rootSlot = document.querySelector('[data-j0-dropdown-trigger="ai0-files-root"] .j0ui-icon-slot');
                if (rootSlot) {
                    rootSlot.setAttribute('data-j0-project-root', flag);
                }
            } else {
                document.querySelectorAll('[data-j0-tree-root] [data-j0-tree-node], .j0ui-shallow-item').forEach(function (n) {
                    if ((n.getAttribute('data-j0-node-path') || '') === currentRel) {
                        const slot = n.querySelector(':scope > .j0ui-tree-row .j0ui-icon-slot');
                        if (slot) {
                            slot.setAttribute('data-j0-project-root', flag);
                        }
                    }
                });
            }
            if (res.abs_path) {
                document.querySelectorAll('[data-j0-dropdown-menu="ai0-files-root"] [data-j0-dropdown-option]').forEach(function (opt) {
                    if ((opt.getAttribute('data-j0-value') || '') === res.abs_path) {
                        const slot = opt.querySelector('.j0ui-icon-slot');
                        if (slot) {
                            slot.setAttribute('data-j0-project-root', flag);
                        }
                    }
                });
            }
            setStatus(res.message || '', false);
            if (root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
                root.j0.bus.emit('ai0:tree:visual-changed', { kind: 'project-root', rel: currentRel, value: nextState });
            }
        });

        loadItem('');
    }

    function initViewToggles() {
        const showUnderscoreCb = document.querySelector('[data-j0-view-toggle="underscore"]');
        const showDotCb = document.querySelector('[data-j0-view-toggle="dot"]');

        if (root.j0 && root.j0.vfs && typeof root.j0.vfs.get === 'function') {
            if (showUnderscoreCb) {
                const savedU = root.j0.vfs.get(VFS_SHOW_UNDERSCORE, '1');
                showUnderscoreCb.checked = savedU !== '0';
            }
            if (showDotCb) {
                const savedD = root.j0.vfs.get(VFS_SHOW_DOT, '1');
                showDotCb.checked = savedD !== '0';
            }
        }

        document.addEventListener('change', function (event) {
            const toggle = event.target.closest('[data-j0-view-toggle]');
            if (!toggle) {
                return;
            }
            const kind = toggle.getAttribute('data-j0-view-toggle');
            if (root.j0 && root.j0.vfs && typeof root.j0.vfs.set === 'function') {
                root.j0.vfs.set(kind === 'dot' ? VFS_SHOW_DOT : VFS_SHOW_UNDERSCORE, toggle.checked ? '1' : '0');
            }
            applyVisibilityFilters();
        });

        applyVisibilityFilters();
    }

    function boot() {
        initViewToggles();
        document.querySelectorAll('[data-j0-inspector-host]').forEach(initInspector);
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', boot);
        } else {
            boot();
        }
    }
})();