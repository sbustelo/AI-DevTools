// PATH: ai0/ui/modules/0050-disk-explorer--ui-widget/j0050-disk-explorer.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};

    function emitBus(name, payload) {
        if (root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
            root.j0.bus.emit(name, payload);
        }
    }

    function buildIconElement(iconVal, iconUrl, extraClass) {
        const cls = ('j0ui-icon ' + (extraClass || '')).trim();
        if (iconUrl) {
            const img = document.createElement('img');
            img.className = cls;
            img.src = iconUrl;
            img.alt = '';
            img.setAttribute('aria-hidden', 'true');
            return img;
        }
        const span = document.createElement('span');
        span.className = cls;
        span.setAttribute('aria-hidden', 'true');
        span.textContent = iconVal || '📁';
        return span;
    }

    function applyLiveIconUpdate(info) {
        if (!info || typeof info !== 'object') {
            return;
        }
        const rel = typeof info.rel === 'string' ? info.rel : '';
        const absPath = typeof info.absPath === 'string' ? info.absPath : '';
        const iconVal = info.icon || '📁';
        const iconUrl = info.iconUrl || '';

        if (rel === '') {
            const rootTriggerIcon = document.querySelector('[data-j0-dropdown-trigger="ai0-files-root"] .j0ui-icon');
            if (rootTriggerIcon) {
                rootTriggerIcon.replaceWith(buildIconElement(iconVal, iconUrl, 'j0ui-toolbar-icon'));
            }
        } else {
            document.querySelectorAll('[data-j0-tree-node], .j0ui-shallow-item').forEach(function (node) {
                if ((node.getAttribute('data-j0-node-path') || '') !== rel) {
                    return;
                }
                const row = node.querySelector(':scope > .j0ui-tree-row');
                const oldIcon = row ? row.querySelector('.j0ui-icon, .j0ui-shallow-icon') : null;
                if (oldIcon) {
                    oldIcon.replaceWith(buildIconElement(iconVal, iconUrl, 'j0ui-tree-icon'));
                }
            });
        }

        if (absPath !== '') {
            document.querySelectorAll('[data-j0-dropdown-menu="ai0-files-root"] [data-j0-dropdown-option]').forEach(function (opt) {
                if ((opt.getAttribute('data-j0-value') || '') !== absPath) {
                    return;
                }
                const optIcon = opt.querySelector('.j0ui-icon');
                if (optIcon) {
                    optIcon.replaceWith(buildIconElement(iconVal, iconUrl, 'j0ui-tree-icon'));
                }
            });
        }
    }

    function navigateToRoot(nextRoot, vfsKey) {
        if (!nextRoot) {
            return;
        }
        if (vfsKey && root.j0 && root.j0.vfs && typeof root.j0.vfs.set === 'function') {
            root.j0.vfs.set(vfsKey, nextRoot);
        }
        emitBus('ai0:disk:root-change', { root: nextRoot, key: vfsKey || '' });
        const url = new URL(root.location.href);
        url.searchParams.delete('scope');
        url.searchParams.set('root', nextRoot);
        root.location.href = url.toString();
    }

    function postAction(fields) {
        const form = document.createElement('form');
        form.method = 'post';
        Object.entries(fields).forEach(function (pair) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = pair[0];
            input.value = String(pair[1]);
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }

    function initDiskExplorer() {
        const rootSelect = document.querySelector('[data-j0-root-select][data-j0-vfs-root-key]');
        const subdirSelect = document.querySelector('[data-j0-subdir-select]');

        if (rootSelect) {
            const vfsKey = rootSelect.getAttribute('data-j0-vfs-root-key') || '';
            const url = new URL(root.location.href);
            const hasExplicitRoot = url.searchParams.has('root');

            if (hasExplicitRoot && vfsKey && root.j0 && root.j0.vfs && typeof root.j0.vfs.set === 'function') {
                root.j0.vfs.set(vfsKey, rootSelect.value);
            } else if (!hasExplicitRoot && vfsKey && root.j0 && root.j0.vfs && typeof root.j0.vfs.get === 'function') {
                const savedRoot = root.j0.vfs.get(vfsKey, null);
                if (savedRoot && savedRoot !== rootSelect.value) {
                    navigateToRoot(savedRoot, vfsKey);
                    return;
                }
            }

            rootSelect.addEventListener('change', function () {
                navigateToRoot(rootSelect.value, vfsKey);
            });

            if (subdirSelect) {
                subdirSelect.addEventListener('change', function () {
                    if (subdirSelect.value) {
                        navigateToRoot(subdirSelect.value, vfsKey);
                    }
                });
            }
        }

        const menuEl = document.querySelector('[data-j0-context-menu]');
        if (!menuEl) {
            return;
        }

        let activeItem = null;

        function hideMenu() {
            if (root.j0 && root.j0.ui && typeof root.j0.ui.floatCloseAll === 'function') {
                root.j0.ui.floatCloseAll();
            } else {
                menuEl.setAttribute('data-j0-hidden', 'true');
            }
            activeItem = null;
        }

        const apiHost = document.querySelector('[data-j0-api-url]');
        const dynamicApiUrl = apiHost ? (apiHost.getAttribute('data-j0-api-url') || '') : '';

        function clearDynamicSubmenus() {
            document.querySelectorAll('[data-j0-submenu^="ai0-dyn-"]').forEach(function (el) {
                el.remove();
            });
            if (root.j0 && root.j0.ui && root.j0.ui.menus) {
                root.j0.ui.menus.closeAll();
            }
        }

        function renderDynamicActions(actions) {
            clearDynamicSubmenus();
            while (menuEl.firstChild) {
                menuEl.removeChild(menuEl.firstChild);
            }
            actions.forEach(function (act) {
                if (act.type === 'separator') {
                    const div = document.createElement('div');
                    div.className = 'j0ui-menu-divider';
                    div.setAttribute('role', 'separator');
                    menuEl.appendChild(div);
                    return;
                }
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.setAttribute('role', 'menuitem');
                btn.className = 'j0ui-menu-item' + (act.danger ? ' j0ui-menu-item--danger' : '');
                btn.setAttribute('data-j0-dyn-action', act.id);
                if (act.prompt) {
                    btn.setAttribute('data-j0-dyn-prompt', act.prompt);
                }
                if (act.confirm) {
                    btn.setAttribute('data-j0-dyn-confirm', act.confirm);
                }
                const ico = document.createElement('span');
                ico.className = ('j0ui-menu-icon ' + (act.icon_url ? '' : (act.class || ''))).trim();
                ico.setAttribute('aria-hidden', 'true');
                if (act.icon_url) {
                    const img = document.createElement('img');
                    img.className = 'j0ui-tree-icon';
                    img.src = act.icon_url;
                    img.alt = '';
                    ico.appendChild(img);
                } else {
                    ico.textContent = act.icon || '';
                }
                const lbl = document.createElement('span');
                lbl.textContent = act.label || act.id;
                btn.appendChild(ico);
                btn.appendChild(lbl);

                if (Array.isArray(act.submenu) && act.submenu.length > 0) {
                    const subId = 'ai0-dyn-' + act.id;
                    btn.classList.add('j0-menu-item--has-submenu');
                    btn.removeAttribute('data-j0-dyn-action');
                    btn.setAttribute('data-j0-submenu-trigger', subId);
                    const caret = document.createElement('span');
                    caret.className = 'j0-submenu-caret';
                    caret.textContent = '▸';
                    btn.appendChild(caret);

                    const subEl = document.createElement('div');
                    subEl.className = 'j0-submenu';
                    subEl.setAttribute('role', 'menu');
                    subEl.setAttribute('data-j0-submenu', subId);
                    subEl.setAttribute('data-j0-hidden', 'true');

                    act.submenu.forEach(function (subItem) {
                        const sBtn = document.createElement('button');
                        sBtn.type = 'button';
                        sBtn.setAttribute('role', 'menuitem');
                        sBtn.className = 'j0ui-menu-item';
                        sBtn.setAttribute('data-j0-dyn-action', act.id);
                        sBtn.setAttribute('data-j0-dyn-param-key', 'color');
                        sBtn.setAttribute('data-j0-dyn-param-val', subItem.value || '');

                        const sIco = document.createElement('span');
                        sIco.className = 'j0ui-menu-icon';
                        sIco.textContent = subItem.icon || '';
                        const sLbl = document.createElement('span');
                        sLbl.textContent = subItem.label || '';
                        sBtn.appendChild(sIco);
                        sBtn.appendChild(sLbl);

                        if (subItem.checked) {
                            const chk = document.createElement('span');
                            chk.className = 'j0-submenu-check';
                            chk.textContent = '✓';
                            sBtn.appendChild(chk);
                        }
                        subEl.appendChild(sBtn);
                    });

                    subEl.addEventListener('click', function (ev) {
                        const chosenBtn = ev.target.closest('[data-j0-dyn-action]');
                        if (!chosenBtn || !activeItem || dynamicApiUrl === '') {
                            return;
                        }
                        ev.preventDefault();
                        ev.stopPropagation();

                        const actionId = chosenBtn.getAttribute('data-j0-dyn-action') || '';
                        const paramKey = chosenBtn.getAttribute('data-j0-dyn-param-key') || '';
                        const paramVal = chosenBtn.getAttribute('data-j0-dyn-param-val') || '';
                        const relPath = activeItem.getAttribute('data-j0-node-path') || '';
                        const anyRootSelect = document.querySelector('[data-j0-root-select]');
                        const browseRoot = anyRootSelect ? anyRootSelect.value : (document.querySelector('input[name="browse_root"]')?.value || '');
                        const params = {};
                        if (paramKey !== '') {
                            params[paramKey] = paramVal;
                        }

                        const targetNode = activeItem;
                        const colorHost = (relPath === '' && targetNode.closest)
                            ? (targetNode.closest('[data-j0-root-pane]') || targetNode)
                            : targetNode;
                        const prevColor = colorHost.getAttribute('data-j0-label-color');
                        if (paramKey === 'color') {
                            if (paramVal !== '') {
                                colorHost.setAttribute('data-j0-label-color', paramVal);
                            } else {
                                colorHost.removeAttribute('data-j0-label-color');
                            }
                        }

                        clearDynamicSubmenus();
                        hideMenu();

                        const reqBody = { op: 'run_action', browse_root: browseRoot, rel_path: relPath, action_id: actionId, params: params };
                        fetch(dynamicApiUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify(reqBody)
                        }).then(function (r) {
                            return r.json();
                        }).then(function (res) {
                            if (res && !res.ok) {
                                if (paramKey === 'color') {
                                    if (prevColor !== null) {
                                        colorHost.setAttribute('data-j0-label-color', prevColor);
                                    } else {
                                        colorHost.removeAttribute('data-j0-label-color');
                                    }
                                }
                                if (res.message) {
                                    root.alert(res.message);
                                }
                                return;
                            }
                            if (paramKey === 'color') {
                                emitBus('ai0:node:color-changed', { rel: relPath, color: paramVal });
                            }
                            if (res && res.ok && res.reload) {
                                root.location.reload();
                            }
                        }).catch(function (err) {
                            if (paramKey === 'color') {
                                if (prevColor !== null) {
                                    colorHost.setAttribute('data-j0-label-color', prevColor);
                                } else {
                                    colorHost.removeAttribute('data-j0-label-color');
                                }
                            }
                            root.alert('Action error: ' + String(err));
                        });
                    });

                    document.body.appendChild(subEl);
                }

                menuEl.appendChild(btn);
            });
        }

        async function openMenuFor(node, clientX, clientY) {
            if (!node) {
                return;
            }
            document.querySelectorAll('.j0ui-tree-node--selected').forEach(function (el) {
                el.classList.remove('j0ui-tree-node--selected');
            });
            node.classList.add('j0ui-tree-node--selected');
            activeItem = node;
            const relPath = node.getAttribute('data-j0-node-path') || '';

            if (dynamicApiUrl !== '') {
                const anyRootSelect = document.querySelector('[data-j0-root-select]');
                const browseRoot = anyRootSelect ? anyRootSelect.value : (document.querySelector('input[name="browse_root"]')?.value || '');
                try {
                    const res = await fetch(dynamicApiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ op: 'inspect', browse_root: browseRoot, rel_path: relPath })
                    });
                    const data = await res.json();
                    console.log('[ai0:menu] inspect:', { browseRoot: browseRoot, relPath: relPath, ok: data && data.ok, type: data && data.type, actions: data && data.actions });
                    if (data && data.ok && Array.isArray(data.actions) && data.actions.length > 0) {
                        renderDynamicActions(data.actions);
                    }
                } catch (err) {
                    console.error('[ai0:menu] inspect failed:', err);
                }
            }

            if (root.j0 && root.j0.ui && typeof root.j0.ui.floatAt === 'function') {
                root.j0.ui.floatAt(menuEl, clientX, clientY);
            } else {
                menuEl.setAttribute('data-j0-hidden', 'false');
            }
            emitBus('ai0:disk:context-open', { rel: relPath });
        }

        function resolveNode(target) {
            return target.closest('[data-j0-tree-node], [data-j0-disk-item], .j0ui-shallow-item');
        }

        document.addEventListener('contextmenu', function (event) {
            const node = resolveNode(event.target);
            if (!node) {
                return;
            }
            event.preventDefault();
            openMenuFor(node, event.clientX, event.clientY);
        });

        document.addEventListener('click', function (event) {
            const rowTrigger = event.target.closest('[data-j0-row-menu-trigger]');
            if (rowTrigger) {
                const node = resolveNode(rowTrigger);
                if (node) {
                    event.preventDefault();
                    event.stopPropagation();
                    const rect = rowTrigger.getBoundingClientRect();
                    openMenuFor(node, rect.right, rect.bottom + 2);
                    return;
                }
            }

            if (menuEl.getAttribute('data-j0-hidden') === 'true') {
                return;
            }

            const dynBtn = event.target.closest('[data-j0-dyn-action]');
            if (dynBtn && activeItem && dynamicApiUrl !== '') {
                const actionId = dynBtn.getAttribute('data-j0-dyn-action') || '';
                const promptMsg = dynBtn.getAttribute('data-j0-dyn-prompt') || '';
                const confirmMsg = dynBtn.getAttribute('data-j0-dyn-confirm') || '';
                const paramKey = dynBtn.getAttribute('data-j0-dyn-param-key') || '';
                const paramVal = dynBtn.getAttribute('data-j0-dyn-param-val') || '';
                const relPath = activeItem.getAttribute('data-j0-node-path') || '';
                const curName = activeItem.getAttribute('data-j0-node-name') || '';
                const anyRootSelect = document.querySelector('[data-j0-root-select]');
                const browseRoot = anyRootSelect ? anyRootSelect.value : (document.querySelector('input[name="browse_root"]')?.value || '');
                const params = {};
                if (paramKey !== '') {
                    params[paramKey] = paramVal;
                }
                clearDynamicSubmenus();

                if (promptMsg !== '') {
                    const nextVal = root.prompt(promptMsg, curName);
                    if (!nextVal || nextVal.trim() === '' || nextVal.trim() === curName) {
                        hideMenu();
                        return;
                    }
                    params.new_name = nextVal.trim();
                }
                if (confirmMsg !== '' && !root.confirm(confirmMsg)) {
                    hideMenu();
                    return;
                }
                hideMenu();
                const reqBody = { op: 'run_action', browse_root: browseRoot, rel_path: relPath, action_id: actionId, params: params };
                console.log('[ai0:menu] 1. Dispatching dynamic action:', actionId, 'url:', dynamicApiUrl, 'payload:', reqBody);
                fetch(dynamicApiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(reqBody)
                }).then(function (r) {
                    console.log('[ai0:menu] 2. HTTP status:', r.status, r.statusText);
                    return r.text().then(function (txt) {
                        console.log('[ai0:menu] 3. Raw response:', txt);
                        try {
                            return JSON.parse(txt);
                        } catch (parseErr) {
                            console.error('[ai0:menu] 3b. JSON parse error:', parseErr);
                            return { ok: false, message: 'Invalid JSON response: ' + txt.slice(0, 200) };
                        }
                    });
                }).then(function (res) {
                    console.log('[ai0:menu] 4. Parsed result:', res);
                    if (res && !res.ok && res.message) {
                        root.alert(res.message);
                        return;
                    }
                    if (res && res.ok && res.open_url) {
                        root.open(res.open_url, '_blank', 'noopener');
                    }
                    if (res && res.ok && res.reload) {
                        root.location.reload();
                    }
                }).catch(function (err) {
                    console.error('[ai0:menu] 5. Fetch error:', err);
                    root.alert('Action error: ' + String(err));
                });
                return;
            }

            const btn = event.target.closest('[data-j0-ctx-action]');
            if (btn && activeItem) {
                const act = btn.getAttribute('data-j0-ctx-action');
                const relPath = activeItem.getAttribute('data-j0-node-path') || '';
                const curName = activeItem.getAttribute('data-j0-node-name') || '';
                const rootField = menuEl.getAttribute('data-j0-root-field') || 'browse_root';
                const anyRootSelect = document.querySelector('[data-j0-root-select]');
                const rootVal = anyRootSelect
                    ? anyRootSelect.value
                    : (document.querySelector('input[name="' + rootField + '"]')?.value || '');
                const activeTabBtn = document.querySelector('[data-j0-tab-group="explorer-workspace"] .j0ui-active');
                const curTab = activeTabBtn ? (activeTabBtn.getAttribute('data-j0-target') || '') : '';

                const buildFields = function (extra) {
                    const fields = Object.assign({ rel_path: relPath }, extra);
                    fields[rootField] = rootVal;
                    if (curTab !== '') {
                        fields.active_tab = curTab;
                    }
                    return fields;
                };

                if (act === 'rename') {
                    const promptLabel = menuEl.getAttribute('data-j0-prompt-rename') || 'New name:';
                    const nextName = root.prompt(promptLabel, curName);
                    if (nextName !== null && nextName.trim() !== '' && nextName.trim() !== curName) {
                        postAction(buildFields({ action: 'rename_item', new_name: nextName.trim() }));
                    }
                } else if (act === 'toggle_disable') {
                    postAction(buildFields({ action: 'toggle_disable' }));
                } else if (act === 'reveal') {
                    postAction(buildFields({ action: 'reveal_finder' }));
                } else if (act === 'snapshot') {
                    postAction(buildFields({ action: 'snapshot_item' }));
                } else if (act === 'discard') {
                    const confirmMsg = menuEl.getAttribute('data-j0-confirm-discard') || 'Mark item as discarded (_🗑️)?';
                    if (root.confirm(confirmMsg)) {
                        postAction(buildFields({ action: 'discard_item' }));
                    }
                }

                hideMenu();
                return;
            }

            if (!event.target.closest('[data-j0-context-menu]')) {
                hideMenu();
            }
        });

        if (root.j0 && root.j0.bus && typeof root.j0.bus.on === 'function') {
            root.j0.bus.on('ai0:node:icon-changed', applyLiveIconUpdate);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                hideMenu();
            }
        });
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDiskExplorer);
        } else {
            initDiskExplorer();
        }
    }
})();