// PATH: ai0/ui/modules/0025-miller-columns--ui-widget/j0025-miller-columns.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    if (typeof document === 'undefined') {
        return;
    }

    const VFS_VIEW_MODE_KEY = 'ai0_files_view_mode';

    function getDirectTreeChildren(ulEl) {
        if (!ulEl) {
            return [];
        }
        return Array.from(ulEl.children).filter(function (li) {
            return li.hasAttribute('data-j0-tree-node')
                && li.getAttribute('data-j0-view-hidden') !== 'true'
                && li.getAttribute('data-j0-hidden') !== 'true';
        });
    }

    function initMillerHost(workspaceEl) {
        const treeRoot = workspaceEl.querySelector('[data-j0-tree-root]');
        const millerHost = workspaceEl.querySelector('[data-j0-miller-host]');
        if (!treeRoot || !millerHost) {
            return;
        }

        const expandBtn = workspaceEl.querySelector('[data-j0-action="tree-toggle-all"]');
        const rootPane = workspaceEl.querySelector('[data-j0-root-pane]');

        let currentMode = 'tree';
        let activePath = [];

        function findTreeNodeByPath(relPath) {
            const nodes = treeRoot.querySelectorAll('[data-j0-tree-node]');
            for (let i = 0; i < nodes.length; i++) {
                if (nodes[i].getAttribute('data-j0-node-path') === relPath) {
                    return nodes[i];
                }
            }
            return null;
        }

        function buildActivePathFromNode(nodeEl) {
            const chain = [];
            let cursor = nodeEl;
            while (cursor && treeRoot.contains(cursor)) {
                const rel = cursor.getAttribute('data-j0-node-path') || '';
                if (rel) {
                    chain.unshift(rel);
                }
                const parentUl = cursor.parentElement;
                cursor = parentUl ? parentUl.closest('[data-j0-tree-node]') : null;
            }
            return chain;
        }

        function revealPathInTree(relPath) {
            const targetNode = findTreeNodeByPath(relPath);
            if (!targetNode) {
                return;
            }
            let cursor = targetNode;
            while (cursor && treeRoot.contains(cursor)) {
                if (cursor.getAttribute('data-j0-node-type') === 'dir') {
                    cursor.setAttribute('data-j0-collapsed', 'false');
                    const caret = cursor.querySelector(':scope > .j0ui-tree-row [data-j0-tree-toggle]');
                    if (caret) {
                        caret.textContent = '▾';
                    }
                }
                const parentUl = cursor.parentElement;
                cursor = parentUl ? parentUl.closest('[data-j0-tree-node]') : null;
            }
            document.querySelectorAll('.j0ui-tree-node--selected').forEach(function (el) {
                el.classList.remove('j0ui-tree-node--selected');
            });
            targetNode.classList.add('j0ui-tree-node--selected');
            const targetRow = targetNode.querySelector(':scope > .j0ui-tree-row') || targetNode;
            if (typeof targetRow.scrollIntoView === 'function') {
                targetRow.scrollIntoView({ block: 'center', inline: 'nearest' });
            }
        }

        function drillToRoot(relPath) {
            const rootInput = document.querySelector('[data-j0-root-select]');
            const baseRoot = rootInput ? rootInput.value : (document.querySelector('input[name="browse_root"]')?.value || '');
            if (!relPath || !baseRoot) {
                return;
            }
            const cleanBase = baseRoot.replace(/\/+$/, '');
            const nextRoot = (cleanBase === '' ? '' : cleanBase) + '/' + relPath.replace(/^\/+/, '');
            const url = new URL(root.location.href);
            url.searchParams.delete('scope');
            url.searchParams.delete('root');
            url.searchParams.delete('nav');
            url.searchParams.delete('from');
            url.searchParams.delete('q');
            url.searchParams.set('browse', nextRoot);
            root.location.href = url.toString();
        }

        function selectNode(colIdx, nodeEl) {
            const rel = nodeEl.getAttribute('data-j0-node-path') || '';
            const name = nodeEl.getAttribute('data-j0-node-name') || '';
            const type = nodeEl.getAttribute('data-j0-node-type') || 'file';

            activePath = activePath.slice(0, colIdx);
            activePath.push(rel);

            document.querySelectorAll('.j0ui-tree-node--selected').forEach(function (el) {
                el.classList.remove('j0ui-tree-node--selected');
            });
            nodeEl.classList.add('j0ui-tree-node--selected');

            renderColumns();

            const activeColEl = millerHost.querySelector('[data-j0-miller-col="' + colIdx + '"]');
            const restoredRow = activeColEl ? activeColEl.querySelector('.j0ui-miller-row--active') : null;
            if (restoredRow) {
                restoredRow.focus({ preventScroll: true });
            }

            if (root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
                root.j0.bus.emit('j0:tree:select', { path: rel, name: name, type: type, source: 'miller' });
            }

            const lastCol = millerHost.lastElementChild;
            if (lastCol && typeof lastCol.scrollIntoView === 'function') {
                lastCol.scrollIntoView({ inline: 'end', block: 'nearest', behavior: 'smooth' });
            }
        }

        function buildColumn(colIdx, ulEl, inheritedColor) {
            const colEl = document.createElement('div');
            colEl.className = 'j0ui-miller-col';
            colEl.setAttribute('data-j0-miller-col', String(colIdx));
            if (inheritedColor) {
                colEl.setAttribute('data-j0-label-color', inheritedColor);
            }

            colEl.addEventListener('click', function (ev) {
                if (ev.target.closest('.j0ui-miller-row')) {
                    return;
                }
                activePath = activePath.slice(0, colIdx);
                document.querySelectorAll('.j0ui-tree-node--selected').forEach(function (el) {
                    el.classList.remove('j0ui-tree-node--selected');
                });
                const parentRel = colIdx > 0 ? (activePath[colIdx - 1] || '') : '';
                const parentNode = parentRel ? findTreeNodeByPath(parentRel) : null;
                if (parentNode) {
                    parentNode.classList.add('j0ui-tree-node--selected');
                }
                renderColumns();
                if (root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
                    root.j0.bus.emit('j0:tree:select', {
                        path: parentRel,
                        name: parentNode ? (parentNode.getAttribute('data-j0-node-name') || '') : '',
                        type: 'dir',
                        source: 'miller'
                    });
                }
            });

            const items = getDirectTreeChildren(ulEl);
            if (items.length === 0) {
                const emptyEl = document.createElement('div');
                emptyEl.className = 'j0ui-miller-empty';
                emptyEl.textContent = 'Empty folder';
                colEl.appendChild(emptyEl);
                return colEl;
            }

            const selectedInCol = activePath[colIdx] || '';
            const isLeafCol = colIdx === activePath.length - 1;

            items.forEach(function (nodeEl) {
                const rel = nodeEl.getAttribute('data-j0-node-path') || '';
                const name = nodeEl.getAttribute('data-j0-node-name') || '';
                const type = nodeEl.getAttribute('data-j0-node-type') || 'file';
                const excluded = nodeEl.getAttribute('data-j0-excluded') === 'true';
                const nodeColor = nodeEl.getAttribute('data-j0-label-color') || '';
                const realCb = nodeEl.querySelector(':scope > .j0ui-tree-row [data-j0-tree-check]');
                const realIcon = nodeEl.querySelector(':scope > .j0ui-tree-row .j0ui-tree-label > :first-child');

                const rowEl = document.createElement('div');
                rowEl.className = 'j0ui-miller-row';
                rowEl.setAttribute('tabindex', '0');
                rowEl.setAttribute('data-j0-tree-node', '');
                rowEl.setAttribute('data-j0-node-type', type);
                rowEl.setAttribute('data-j0-node-path', rel);
                rowEl.setAttribute('data-j0-node-name', name);
                rowEl.setAttribute('data-j0-excluded', excluded ? 'true' : 'false');
                rowEl.setAttribute('data-j0-cat', nodeEl.getAttribute('data-j0-cat') || 'none');
                if (nodeColor) {
                    rowEl.setAttribute('data-j0-label-color', nodeColor);
                }
                if (selectedInCol && rel === selectedInCol) {
                    rowEl.classList.add(isLeafCol ? 'j0ui-miller-row--active' : 'j0ui-miller-row--in-path');
                }

                if (realCb) {
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.checked = realCb.checked;
                    cb.indeterminate = realCb.indeterminate;
                    cb.addEventListener('click', function (ev) {
                        ev.stopPropagation();
                    });
                    cb.addEventListener('change', function () {
                        realCb.checked = cb.checked;
                        realCb.indeterminate = false;
                        realCb.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    rowEl.appendChild(cb);
                }

                const iconWrap = document.createElement('span');
                iconWrap.className = 'j0ui-miller-icon';
                if (realIcon) {
                    iconWrap.appendChild(realIcon.cloneNode(true));
                } else {
                    iconWrap.textContent = type === 'dir' ? '📁' : '📄';
                }
                rowEl.appendChild(iconWrap);

                const nameEl = document.createElement('span');
                nameEl.className = 'j0ui-miller-name';
                if (name.startsWith('_') || name.startsWith('.')) {
                    nameEl.classList.add('j0ui-item-dimmed');
                }
                nameEl.textContent = name;
                nameEl.title = name;
                rowEl.appendChild(nameEl);

                if (type === 'dir') {
                    const arrowEl = document.createElement('span');
                    arrowEl.className = 'j0ui-miller-arrow';
                    arrowEl.setAttribute('aria-hidden', 'true');
                    arrowEl.textContent = '▸';
                    rowEl.appendChild(arrowEl);
                }

                const menuBtn = document.createElement('button');
                menuBtn.type = 'button';
                menuBtn.className = 'j0ui-row-menu-trigger';
                menuBtn.setAttribute('data-j0-row-menu-trigger', '');
                menuBtn.setAttribute('aria-label', 'Item actions');
                menuBtn.textContent = '▾';
                rowEl.appendChild(menuBtn);

                rowEl.addEventListener('click', function (ev) {
                    if (ev.target.closest('input[type="checkbox"], [data-j0-row-menu-trigger]')) {
                        return;
                    }
                    selectNode(colIdx, nodeEl);
                });

                rowEl.addEventListener('dblclick', function (ev) {
                    if (ev.target.closest('input, button, [data-j0-row-menu-trigger]')) {
                        return;
                    }
                    ev.preventDefault();
                    ev.stopPropagation();
                    if (type === 'dir') {
                        drillToRoot(rel);
                    } else if (rel !== '' && root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
                        root.j0.bus.emit('ai0:file:open-editor', { rel: rel });
                    }
                });

                colEl.appendChild(rowEl);
            });

            return colEl;
        }

        function renderColumns() {
            if (currentMode !== 'columns') {
                return;
            }
            millerHost.textContent = '';

            let currentUl = treeRoot;
            let inheritedColor = rootPane ? (rootPane.getAttribute('data-j0-label-color') || '') : '';
            millerHost.appendChild(buildColumn(0, currentUl, inheritedColor));

            for (let i = 0; i < activePath.length; i++) {
                const nodeEl = findTreeNodeByPath(activePath[i]);
                if (!nodeEl || nodeEl.getAttribute('data-j0-node-type') !== 'dir') {
                    break;
                }
                const nodeColor = nodeEl.getAttribute('data-j0-label-color') || '';
                if (nodeColor) {
                    inheritedColor = nodeColor;
                }
                const childUl = nodeEl.querySelector(':scope > .j0ui-tree-branch');
                millerHost.appendChild(buildColumn(i + 1, childUl, inheritedColor));
            }
        }

        function setViewMode(mode, persist) {
            const next = mode === 'columns' ? 'columns' : 'tree';
            currentMode = next;

            const isColumns = next === 'columns';
            treeRoot.setAttribute('data-j0-hidden', isColumns ? 'true' : 'false');
            millerHost.setAttribute('data-j0-hidden', isColumns ? 'false' : 'true');
            if (expandBtn) {
                expandBtn.setAttribute('data-j0-hidden', isColumns ? 'true' : 'false');
            }

            if (root.j0 && root.j0.ui && typeof root.j0.ui.setSegment === 'function') {
                root.j0.ui.setSegment('files-view-mode', next);
            }
            if (persist && root.j0 && root.j0.vfs && typeof root.j0.vfs.set === 'function') {
                root.j0.vfs.set(VFS_VIEW_MODE_KEY, next);
            }
            if (isColumns) {
                if (activePath.length === 0) {
                    const selectedTreeNode = treeRoot.querySelector('.j0ui-tree-node--selected');
                    if (selectedTreeNode) {
                        activePath = buildActivePathFromNode(selectedTreeNode);
                    }
                }
                renderColumns();
                const focusTarget = millerHost.querySelector('.j0ui-miller-row--active') || millerHost.lastElementChild;
                if (focusTarget && typeof focusTarget.scrollIntoView === 'function') {
                    focusTarget.scrollIntoView({ block: 'center', inline: 'center' });
                }
            } else if (activePath.length > 0) {
                revealPathInTree(activePath[activePath.length - 1]);
            }
        }

        document.addEventListener('keydown', function (event) {
            if (currentMode !== 'columns' || millerHost.getAttribute('data-j0-hidden') === 'true') {
                return;
            }
            const tag = event.target && event.target.tagName ? event.target.tagName : '';
            if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
                return;
            }
            if (event.target.closest && event.target.closest('[role="menu"], [role="listbox"], dialog[open]')) {
                return;
            }
            const navKeys = ['ArrowDown', 'ArrowUp', 'ArrowRight', 'ArrowLeft', 'Enter', ' '];
            if (!navKeys.includes(event.key)) {
                return;
            }
            let row = event.target.closest ? event.target.closest('.j0ui-miller-row') : null;
            if (!row) {
                row = millerHost.querySelector('.j0ui-miller-row--active');
                if (!row) {
                    const firstRow = millerHost.querySelector('[data-j0-miller-col="0"] .j0ui-miller-row');
                    if (firstRow && (event.key === 'ArrowDown' || event.key === 'ArrowUp' || event.key === 'ArrowRight')) {
                        event.preventDefault();
                        firstRow.click();
                    }
                    return;
                }
            }
            const colEl = row.closest('.j0ui-miller-col');
            const colIdx = parseInt(colEl.getAttribute('data-j0-miller-col') || '0', 10);
            const rows = Array.from(colEl.querySelectorAll('.j0ui-miller-row'));
            const idx = rows.indexOf(row);

            if (event.key === 'ArrowDown' && idx < rows.length - 1) {
                event.preventDefault();
                rows[idx + 1].click();
                const refreshedCol = millerHost.querySelector('[data-j0-miller-col="' + colIdx + '"]');
                const refreshedRow = refreshedCol ? refreshedCol.querySelectorAll('.j0ui-miller-row')[idx + 1] : null;
                if (refreshedRow) refreshedRow.focus({ preventScroll: true });
            } else if (event.key === 'ArrowUp' && idx > 0) {
                event.preventDefault();
                rows[idx - 1].click();
                const refreshedCol = millerHost.querySelector('[data-j0-miller-col="' + colIdx + '"]');
                const refreshedRow = refreshedCol ? refreshedCol.querySelectorAll('.j0ui-miller-row')[idx - 1] : null;
                if (refreshedRow) refreshedRow.focus({ preventScroll: true });
            } else if (event.key === 'ArrowRight') {
                const rowType = row.getAttribute('data-j0-node-type') || '';
                const rowRel = row.getAttribute('data-j0-node-path') || '';
                if (rowType === 'dir' && activePath[colIdx] !== rowRel) {
                    event.preventDefault();
                    row.click();
                }
                const nextCol = millerHost.querySelector('[data-j0-miller-col="' + (colIdx + 1) + '"]');
                const firstRow = nextCol ? nextCol.querySelector('.j0ui-miller-row') : null;
                if (firstRow) {
                    event.preventDefault();
                    firstRow.click();
                    const refreshedNext = millerHost.querySelector('[data-j0-miller-col="' + (colIdx + 1) + '"] .j0ui-miller-row');
                    if (refreshedNext) refreshedNext.focus({ preventScroll: true });
                }
            } else if (event.key === 'ArrowLeft' && colIdx > 0) {
                event.preventDefault();
                activePath = activePath.slice(0, colIdx);
                const parentRel = activePath[colIdx - 1] || '';
                const parentNode = parentRel ? findTreeNodeByPath(parentRel) : null;
                document.querySelectorAll('.j0ui-tree-node--selected').forEach(function (el) {
                    el.classList.remove('j0ui-tree-node--selected');
                });
                if (parentNode) {
                    parentNode.classList.add('j0ui-tree-node--selected');
                }
                renderColumns();
                if (root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
                    root.j0.bus.emit('j0:tree:select', {
                        path: parentRel,
                        name: parentNode ? (parentNode.getAttribute('data-j0-node-name') || '') : '',
                        type: parentNode ? (parentNode.getAttribute('data-j0-node-type') || 'dir') : 'dir',
                        source: 'miller'
                    });
                }
                const prevCol = millerHost.querySelector('[data-j0-miller-col="' + (colIdx - 1) + '"]');
                const activeRow = prevCol ? (prevCol.querySelector('.j0ui-miller-row--active') || prevCol.querySelector('.j0ui-miller-row')) : null;
                if (activeRow) activeRow.focus();
            } else if (event.key === 'Enter') {
                const rel = row.getAttribute('data-j0-node-path') || '';
                const type = row.getAttribute('data-j0-node-type') || 'file';
                if (!rel) {
                    return;
                }
                event.preventDefault();
                if (type === 'dir') {
                    drillToRoot(rel);
                } else if (root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
                    root.j0.bus.emit('ai0:file:open-editor', { rel: rel });
                }
            } else if (event.key === ' ' && event.target.tagName !== 'INPUT' && event.target.tagName !== 'BUTTON') {
                const cb = row.querySelector('input[type="checkbox"]');
                if (cb) {
                    event.preventDefault();
                    cb.checked = !cb.checked;
                    cb.indeterminate = false;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        });

        if (root.j0 && root.j0.bus && typeof root.j0.bus.on === 'function') {
            root.j0.bus.on('j0:segmented:change', function (payload) {
                if (payload && payload.group === 'files-view-mode') {
                    setViewMode(payload.value, true);
                }
            });
            root.j0.bus.on('j0:tree:changed', function () {
                if (currentMode === 'columns') {
                    renderColumns();
                }
            });
            root.j0.bus.on('ai0:tree:visual-changed', function () {
                if (currentMode === 'columns') {
                    renderColumns();
                }
            });
            root.j0.bus.on('j0:tree:select', function (payload) {
                if (!payload || payload.source === 'miller') {
                    return;
                }
                const rel = typeof payload.path === 'string' ? payload.path : '';
                if (rel === '') {
                    activePath = [];
                    if (currentMode === 'columns') {
                        renderColumns();
                    }
                    return;
                }
                const targetNode = findTreeNodeByPath(rel);
                if (targetNode) {
                    activePath = buildActivePathFromNode(targetNode);
                    if (currentMode === 'columns') {
                        renderColumns();
                    }
                }
            });
            root.j0.bus.on('ai0:files:search', function (payload) {
                const q = payload && typeof payload.query === 'string' ? payload.query.trim() : '';
                if (q !== '' && currentMode === 'columns') {
                    treeRoot.setAttribute('data-j0-hidden', 'false');
                    millerHost.setAttribute('data-j0-hidden', 'true');
                } else if (q === '' && currentMode === 'columns') {
                    treeRoot.setAttribute('data-j0-hidden', 'true');
                    millerHost.setAttribute('data-j0-hidden', 'false');
                    renderColumns();
                }
            });
        }

        document.addEventListener('change', function (event) {
            if (event.target.closest('[data-j0-view-toggle]') && currentMode === 'columns') {
                setTimeout(renderColumns, 0);
            }
        });

        const savedMode = (root.j0 && root.j0.vfs && typeof root.j0.vfs.get === 'function')
            ? root.j0.vfs.get(VFS_VIEW_MODE_KEY, 'tree')
            : 'tree';
        setViewMode(savedMode, false);
    }

    document.querySelectorAll('[data-j0-files-workspace]').forEach(initMillerHost);
})();