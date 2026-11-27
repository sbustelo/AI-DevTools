// PATH: ai0/ui/modules/0020-tree-browser--ui-widget/j0020-tree-browser.js
(function () {
    'use strict';

    function emitTreeChanged() {
        if (globalThis.j0 && globalThis.j0.bus) {
            globalThis.j0.bus.emit('j0:tree:changed', {});
        }
    }

    function updateExcludedFilter(treeContainer, onlyExcluded) {
        const nodes = Array.from(treeContainer.querySelectorAll('[data-j0-tree-node]'));
        if (!onlyExcluded) {
            nodes.forEach(function (node) {
                node.removeAttribute('data-j0-hidden');
            });
            return;
        }

        nodes.forEach(function (node) {
            const isSelfExcluded = node.getAttribute('data-j0-excluded') === 'true';
            const hasExcludedChild = node.querySelector('[data-j0-excluded="true"]') !== null;
            if (isSelfExcluded || hasExcludedChild) {
                node.removeAttribute('data-j0-hidden');
                if (hasExcludedChild) {
                    node.setAttribute('data-j0-collapsed', 'false');
                    const caret = node.querySelector(':scope > .j0ui-tree-row [data-j0-tree-toggle]');
                    if (caret) {
                        caret.textContent = '▾';
                    }
                }
            } else {
                node.setAttribute('data-j0-hidden', 'true');
            }
        });
    }

    function getExpandedVfsKey(host) {
        const rootKey = host && host.getAttribute ? (host.getAttribute('data-j0-tree-root-key') || 'default') : 'default';
        return 'ai0_tree_expanded::' + rootKey;
    }

    function saveExpandedState(host) {
        if (!globalThis.j0 || !globalThis.j0.vfs || typeof globalThis.j0.vfs.set !== 'function') {
            return;
        }
        const expanded = [];
        host.querySelectorAll('[data-j0-tree-node][data-j0-node-type="dir"][data-j0-collapsed="false"]').forEach(function (node) {
            const rel = node.getAttribute('data-j0-node-path');
            if (rel) {
                expanded.push(rel);
            }
        });
        globalThis.j0.vfs.set(getExpandedVfsKey(host), JSON.stringify(expanded));
    }

    function restoreExpandedState(host) {
        if (!globalThis.j0 || !globalThis.j0.vfs || typeof globalThis.j0.vfs.get !== 'function') {
            return;
        }
        const raw = globalThis.j0.vfs.get(getExpandedVfsKey(host), null);
        if (!raw) {
            return;
        }
        let expandedList;
        try {
            expandedList = JSON.parse(raw);
        } catch (err) {
            return;
        }
        if (!Array.isArray(expandedList)) {
            return;
        }
        const expandedSet = new Set(expandedList);
        host.querySelectorAll('[data-j0-tree-node][data-j0-node-type="dir"]').forEach(function (node) {
            const caretEl = node.querySelector(':scope > .j0ui-tree-row [data-j0-tree-toggle]');
            if (!caretEl) {
                return;
            }
            const rel = node.getAttribute('data-j0-node-path') || '';
            const isOpen = expandedSet.has(rel);
            node.setAttribute('data-j0-collapsed', isOpen ? 'false' : 'true');
            caretEl.textContent = isOpen ? '▾' : '▸';
        });
        syncCollapseButton(host);
    }

    function syncSearchCheckboxes(host) {
        const isSearching = host.getAttribute('data-j0-search-active') === 'true';
        const bannerCb = host.querySelector('[data-j0-search-check-all]');
        const ddCb = document.querySelector('[data-j0-search-dd-check]');
        const masterCb = host.querySelector('[data-j0-tree-check-all]');
        if (!isSearching) {
            return;
        }
        const hitBoxes = Array.from(host.querySelectorAll('[data-j0-tree-node][data-j0-search-hit="true"] > .j0ui-tree-row [data-j0-tree-check]'));
        const allChecked = hitBoxes.length > 0 && hitBoxes.every(function (c) {
            return c.checked && !c.indeterminate;
        });
        const noneChecked = hitBoxes.length === 0 || hitBoxes.every(function (c) {
            return !c.checked && !c.indeterminate;
        });
        [bannerCb, ddCb, masterCb].forEach(function (cb) {
            if (!cb) {
                return;
            }
            cb.checked = allChecked;
            cb.indeterminate = !allChecked && !noneChecked;
        });
    }

    function setSearchMatchesChecked(host, checked) {
        host.querySelectorAll('[data-j0-tree-node][data-j0-search-hit="true"]').forEach(function (node) {
            node.setAttribute('data-j0-excluded', checked ? 'false' : 'true');
            const cb = node.querySelector(':scope > .j0ui-tree-row [data-j0-tree-check]');
            if (cb) {
                cb.checked = checked;
                cb.indeterminate = false;
            }
            node.querySelectorAll('[data-j0-tree-node]').forEach(function (descNode) {
                descNode.setAttribute('data-j0-excluded', checked ? 'false' : 'true');
                const descCb = descNode.querySelector(':scope > .j0ui-tree-row [data-j0-tree-check]');
                if (descCb) {
                    descCb.checked = checked;
                    descCb.indeterminate = false;
                }
            });
        });
        reconcileTree(host);
        emitTreeChanged();
    }

    function syncSearchDropdownItem(host, isSearching) {
        const ddSep = document.querySelector('[data-j0-search-dd-sep]');
        const ddItem = document.querySelector('[data-j0-search-dd-item]');
        const ddLabel = document.querySelector('[data-j0-search-dd-label]');
        const triggerLabel = host.querySelector('[data-j0-dropdown-trigger="ai0-files-root"] .j0-dropdown-label');

        if (!ddItem) {
            return;
        }
        const prefix = ddItem.getAttribute('data-j0-search-prefix') || '🔍 Search results in';
        const rootName = ddItem.getAttribute('data-j0-root-label') || '/';
        const searchTitle = prefix + ' ' + rootName;

        if (ddSep) {
            ddSep.setAttribute('data-j0-hidden', isSearching ? 'false' : 'true');
        }
        ddItem.setAttribute('data-j0-hidden', isSearching ? 'false' : 'true');
        if (ddLabel) {
            ddLabel.textContent = searchTitle;
        }
        if (triggerLabel) {
            if (!triggerLabel.hasAttribute('data-j0-orig-label')) {
                triggerLabel.setAttribute('data-j0-orig-label', triggerLabel.textContent || rootName);
            }
            triggerLabel.textContent = isSearching
                ? searchTitle
                : (triggerLabel.getAttribute('data-j0-orig-label') || rootName);
        }
    }

    function applyTreeSearch(host, rawQuery) {
        const needle = String(rawQuery || '').trim().toLowerCase();
        const bannerEl = host.querySelector('[data-j0-search-banner]');
        const bannerTextEl = host.querySelector('[data-j0-search-banner-text]');
        const treeRoot = host.querySelector('[data-j0-tree-root]');
        const shallowList = host.querySelector('.j0ui-shallow-list');

        if (needle === '') {
            host.removeAttribute('data-j0-search-active');
            host.querySelectorAll('[data-j0-search-hidden], [data-j0-search-hit]').forEach(function (el) {
                el.removeAttribute('data-j0-search-hidden');
                el.removeAttribute('data-j0-search-hit');
            });
            if (bannerEl) {
                bannerEl.setAttribute('data-j0-hidden', 'true');
            }
            syncSearchDropdownItem(host, false);
            restoreExpandedState(host);
            reconcileTree(host);
            return;
        }
        host.setAttribute('data-j0-search-active', 'true');

        let hitCount = 0;

        if (treeRoot) {
            const evalBranch = function (ulEl) {
                let anyVisibleInBranch = false;
                Array.from(ulEl.children).forEach(function (li) {
                    if (!li.hasAttribute('data-j0-tree-node')) {
                        return;
                    }
                    const name = (li.getAttribute('data-j0-node-name') || '').toLowerCase();
                    const rel = (li.getAttribute('data-j0-node-path') || '').toLowerCase();
                    const selfHit = name.includes(needle) || rel.includes(needle);
                    const childUl = li.querySelector(':scope > .j0ui-tree-branch');
                    const childHit = childUl ? evalBranch(childUl) : false;

                    if (selfHit) {
                        hitCount++;
                        li.setAttribute('data-j0-search-hit', 'true');
                    } else {
                        li.removeAttribute('data-j0-search-hit');
                    }

                    if (selfHit || childHit) {
                        li.removeAttribute('data-j0-search-hidden');
                        if (childHit) {
                            li.setAttribute('data-j0-collapsed', 'false');
                            const caret = li.querySelector(':scope > .j0ui-tree-row [data-j0-tree-toggle]');
                            if (caret) {
                                caret.textContent = '▾';
                            }
                        }
                        anyVisibleInBranch = true;
                    } else {
                        li.setAttribute('data-j0-search-hidden', 'true');
                    }
                });
                return anyVisibleInBranch;
            };
            evalBranch(treeRoot);
            syncCollapseButton(host);
        } else if (shallowList) {
            shallowList.querySelectorAll('.j0ui-shallow-item').forEach(function (li) {
                const name = (li.getAttribute('data-j0-node-name') || '').toLowerCase();
                if (name.includes(needle)) {
                    hitCount++;
                    li.setAttribute('data-j0-search-hit', 'true');
                    li.removeAttribute('data-j0-search-hidden');
                } else {
                    li.removeAttribute('data-j0-search-hit');
                    li.setAttribute('data-j0-search-hidden', 'true');
                }
            });
        }

        if (bannerEl && bannerTextEl) {
            const tplMatches = bannerEl.getAttribute('data-j0-label-matches') || 'matches for';
            bannerTextEl.textContent = hitCount + ' ' + tplMatches + ' "' + String(rawQuery).trim() + '"';
            bannerEl.setAttribute('data-j0-hidden', 'false');
        }
        syncSearchDropdownItem(host, true);
        syncSearchCheckboxes(host);
    }

    function syncCollapseButton(host) {
        const toggleAllBtn = host.querySelector('[data-j0-action="tree-toggle-all"]');
        if (!toggleAllBtn) {
            return;
        }
        const collapsibleNodes = Array.from(host.querySelectorAll('[data-j0-tree-node]')).filter(function (node) {
            return node.querySelector(':scope > .j0ui-tree-branch') !== null;
        });
        const anyExpanded = collapsibleNodes.some(function (node) {
            return node.getAttribute('data-j0-collapsed') !== 'true';
        });
        toggleAllBtn.setAttribute('data-j0-collapsed-state', anyExpanded ? 'false' : 'true');
        const labelCollapse = toggleAllBtn.getAttribute('data-j0-label-collapse') || 'Collapse all';
        const labelExpand = toggleAllBtn.getAttribute('data-j0-label-expand') || 'Expand all';
        const nextLabel = anyExpanded ? labelCollapse : labelExpand;
        toggleAllBtn.textContent = nextLabel;
        toggleAllBtn.setAttribute('title', nextLabel);
        toggleAllBtn.setAttribute('aria-label', nextLabel);
    }

    function reconcileTree(host) {
        const treeRoot = host.querySelector('[data-j0-tree-root]');
        if (!treeRoot) {
            return;
        }

        const allNodes = Array.from(treeRoot.querySelectorAll('[data-j0-tree-node]')).reverse();
        allNodes.forEach(function (node) {
            const cb = node.querySelector(':scope > .j0ui-tree-row [data-j0-tree-check]');
            if (!cb) {
                return;
            }
            const branch = node.querySelector(':scope > .j0ui-tree-branch');
            if (!branch) {
                cb.indeterminate = false;
                node.setAttribute('data-j0-excluded', cb.checked ? 'false' : 'true');
                return;
            }

            const childBoxes = Array.from(
                branch.querySelectorAll(':scope > .j0ui-tree-node > .j0ui-tree-row [data-j0-tree-check]')
            );
            if (childBoxes.length === 0) {
                cb.indeterminate = false;
                node.setAttribute('data-j0-excluded', cb.checked ? 'false' : 'true');
                return;
            }

            const allChecked = childBoxes.every(function (c) {
                return c.checked && !c.indeterminate;
            });
            const noneChecked = childBoxes.every(function (c) {
                return !c.checked && !c.indeterminate;
            });

            if (allChecked) {
                cb.checked = true;
                cb.indeterminate = false;
                node.setAttribute('data-j0-excluded', 'false');
            } else if (noneChecked) {
                cb.checked = false;
                cb.indeterminate = false;
                node.setAttribute('data-j0-excluded', 'true');
            } else {
                cb.checked = false;
                cb.indeterminate = true;
                node.setAttribute('data-j0-excluded', 'false');
            }
        });

        const masterCheck = host.querySelector('[data-j0-tree-check-all]');
        if (masterCheck) {
            const topBoxes = Array.from(
                treeRoot.querySelectorAll(':scope > .j0ui-tree-node > .j0ui-tree-row [data-j0-tree-check]')
            );
            if (topBoxes.length === 0) {
                masterCheck.checked = false;
                masterCheck.indeterminate = false;
            } else {
                const allTop = topBoxes.every(function (c) {
                    return c.checked && !c.indeterminate;
                });
                const noneTop = topBoxes.every(function (c) {
                    return !c.checked && !c.indeterminate;
                });
                masterCheck.checked = allTop;
                masterCheck.indeterminate = !allTop && !noneTop;
            }
        }

        const statsEl = host.querySelector('[data-j0-tree-stats]');
        if (statsEl) {
            const fileNodes = Array.from(treeRoot.querySelectorAll('[data-j0-tree-node][data-j0-node-type="file"]'));
            const includedCount = fileNodes.filter(function (node) {
                const cb = node.querySelector(':scope > .j0ui-tree-row [data-j0-tree-check]');
                return cb ? cb.checked : false;
            }).length;
            const filesWord = statsEl.getAttribute('data-j0-label-files') || 'files';
            statsEl.textContent = includedCount + ' / ' + fileNodes.length + ' ' + filesWord;
        }

        const filterBtn = host.querySelector('[data-j0-action="toggle-excluded-only"]');
        if (filterBtn && filterBtn.getAttribute('data-j0-active') === 'true') {
            updateExcludedFilter(treeRoot, true);
        }

        syncSearchCheckboxes(host);
        syncCollapseButton(host);
    }

    const lastCheckedByHost = new WeakMap();

    document.addEventListener('click', function (event) {
        const cb = event.target.closest('[data-j0-tree-check]');
        if (cb) {
            const host = cb.closest('[data-j0-tree-host]') || document;
            const treeRoot = host.querySelector('[data-j0-tree-root]');
            const prev = lastCheckedByHost.get(host);

            if (event.shiftKey && prev && prev !== cb && treeRoot) {
                const allBoxes = Array.from(treeRoot.querySelectorAll('[data-j0-tree-check]'));
                const startIdx = allBoxes.indexOf(prev);
                const endIdx = allBoxes.indexOf(cb);
                if (startIdx !== -1 && endIdx !== -1) {
                    const targetState = prev.checked;
                    const lo = Math.min(startIdx, endIdx);
                    const hi = Math.max(startIdx, endIdx);
                    for (let i = lo; i <= hi; i++) {
                        const box = allBoxes[i];
                        box.checked = targetState;
                        box.indeterminate = false;
                        const n = box.closest('[data-j0-tree-node]');
                        if (n) {
                            n.setAttribute('data-j0-excluded', targetState ? 'false' : 'true');
                        }
                    }
                    reconcileTree(host);
                    emitTreeChanged();
                    return;
                }
            }
            lastCheckedByHost.set(host, cb);
            return;
        }

        const rowSelect = event.target.closest('[data-j0-tree-select]');
        if (rowSelect) {
            const node = rowSelect.closest('[data-j0-tree-node]');
            if (node) {
                document.querySelectorAll('.j0ui-tree-node--selected').forEach(function (el) {
                    el.classList.remove('j0ui-tree-node--selected');
                });
                node.classList.add('j0ui-tree-node--selected');
                if (globalThis.j0 && globalThis.j0.bus && typeof globalThis.j0.bus.emit === 'function') {
                    globalThis.j0.bus.emit('j0:tree:select', {
                        path: node.getAttribute('data-j0-node-path') || '',
                        name: node.getAttribute('data-j0-node-name') || '',
                        type: node.getAttribute('data-j0-node-type') || 'file'
                    });
                }
            }
            return;
        }

        const caret = event.target.closest('[data-j0-tree-toggle]');
        if (caret) {
            const node = caret.closest('[data-j0-tree-node]');
            const host = caret.closest('[data-j0-tree-host]') || document;
            if (node) {
                const isCollapsed = node.getAttribute('data-j0-collapsed') === 'true';
                node.setAttribute('data-j0-collapsed', isCollapsed ? 'false' : 'true');
                caret.textContent = isCollapsed ? '▾' : '▸';
                syncCollapseButton(host);
                saveExpandedState(host);
            }
            return;
        }

        const filterBtn = event.target.closest('[data-j0-action="toggle-excluded-only"]');
        if (filterBtn) {
            const host = filterBtn.closest('[data-j0-tree-host]') || document;
            const tree = host.querySelector('[data-j0-tree-root]');
            if (tree) {
                const active = filterBtn.getAttribute('data-j0-active') === 'true';
                const nextState = !active;
                filterBtn.setAttribute('data-j0-active', nextState ? 'true' : 'false');
                filterBtn.classList.toggle('j0ui-btn--active', nextState);
                updateExcludedFilter(tree, nextState);
                syncCollapseButton(host);
            }
            return;
        }

        const clearSearchBtn = event.target.closest('[data-j0-action="clear-tree-search"]');
        if (clearSearchBtn) {
            const host = clearSearchBtn.closest('[data-j0-tree-host]') || document;
            applyTreeSearch(host, '');
            const url = new URL(globalThis.location.href);
            if (url.searchParams.has('q')) {
                url.searchParams.delete('q');
                globalThis.history.replaceState(null, '', url.toString());
            }
            if (globalThis.j0 && globalThis.j0.bus && typeof globalThis.j0.bus.emit === 'function') {
                globalThis.j0.bus.emit('ai0:omnibar:clear', {});
            }
            return;
        }

        const toggleAllBtn = event.target.closest('[data-j0-action="tree-toggle-all"]');
        if (toggleAllBtn) {
            const host = toggleAllBtn.closest('[data-j0-tree-host]') || document;
            const shouldCollapse = toggleAllBtn.getAttribute('data-j0-collapsed-state') !== 'true';
            host.querySelectorAll('[data-j0-tree-node]').forEach(function (node) {
                const caretEl = node.querySelector(':scope > .j0ui-tree-row [data-j0-tree-toggle]');
                if (caretEl) {
                    node.setAttribute('data-j0-collapsed', shouldCollapse ? 'true' : 'false');
                    caretEl.textContent = shouldCollapse ? '▸' : '▾';
                }
            });
            syncCollapseButton(host);
            saveExpandedState(host);
        }
    });

    document.addEventListener('change', function (event) {
        const searchCheck = event.target.closest('[data-j0-search-check-all], [data-j0-search-dd-check]');
        if (searchCheck) {
            const host = searchCheck.closest('[data-j0-tree-host]') || document.querySelector('[data-j0-tree-host]') || document;
            setSearchMatchesChecked(host, searchCheck.checked);
            return;
        }

        const masterCheck = event.target.closest('[data-j0-tree-check-all]');
        if (masterCheck) {
            const host = masterCheck.closest('[data-j0-tree-host]') || document;
            const checked = masterCheck.checked;
            masterCheck.indeterminate = false;
            if (host.getAttribute('data-j0-search-active') === 'true') {
                setSearchMatchesChecked(host, checked);
                return;
            }
            host.querySelectorAll('[data-j0-tree-node]').forEach(function (node) {
                node.setAttribute('data-j0-excluded', checked ? 'false' : 'true');
                const cb = node.querySelector(':scope > .j0ui-tree-row [data-j0-tree-check]');
                if (cb) {
                    cb.checked = checked;
                    cb.indeterminate = false;
                }
            });
            reconcileTree(host);
            emitTreeChanged();
            return;
        }

        const checkbox = event.target.closest('[data-j0-tree-check]');
        if (!checkbox) {
            return;
        }
        const host = checkbox.closest('[data-j0-tree-host]') || document;
        const node = checkbox.closest('[data-j0-tree-node]');
        if (node) {
            const checked = checkbox.checked;
            checkbox.indeterminate = false;
            node.setAttribute('data-j0-excluded', checked ? 'false' : 'true');
            node.querySelectorAll('[data-j0-tree-node]').forEach(function (descNode) {
                descNode.setAttribute('data-j0-excluded', checked ? 'false' : 'true');
                const descCb = descNode.querySelector(':scope > .j0ui-tree-row [data-j0-tree-check]');
                if (descCb) {
                    descCb.checked = checked;
                    descCb.indeterminate = false;
                }
            });
        }
        reconcileTree(host);
        emitTreeChanged();
    });

    if (globalThis.j0 && globalThis.j0.bus) {
        globalThis.j0.bus.on('j0:tree:reconcile', function () {
            document.querySelectorAll('[data-j0-tree-host]').forEach(reconcileTree);
            emitTreeChanged();
        });
        globalThis.j0.bus.on('ai0:files:search', function (payload) {
            const q = payload && typeof payload.query === 'string' ? payload.query : '';
            document.querySelectorAll('[data-j0-tree-host]').forEach(function (host) {
                applyTreeSearch(host, q);
            });
        });
    }

    const bootQuery = (typeof URLSearchParams !== 'undefined' && globalThis.location)
        ? (new URLSearchParams(globalThis.location.search).get('q') || '')
        : '';

    document.querySelectorAll('[data-j0-tree-host]').forEach(function (host) {
        restoreExpandedState(host);
        reconcileTree(host);
        if (bootQuery !== '') {
            applyTreeSearch(host, bootQuery);
        }
    });
})();