// PATH: ai0-003/ui/modules/0020-tree-browser--ui-widget/j0020-tree-browser.js
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
        toggleAllBtn.textContent = anyExpanded ? 'Collapse all' : 'Expand all';
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
            statsEl.textContent = includedCount + ' / ' + fileNodes.length + ' files';
        }

        const filterBtn = host.querySelector('[data-j0-action="toggle-excluded-only"]');
        if (filterBtn && filterBtn.getAttribute('data-j0-active') === 'true') {
            updateExcludedFilter(treeRoot, true);
        }

        syncCollapseButton(host);
    }

    document.addEventListener('click', function (event) {
        const caret = event.target.closest('[data-j0-tree-toggle]');
        if (caret) {
            const node = caret.closest('[data-j0-tree-node]');
            const host = caret.closest('[data-j0-tree-host]') || document;
            if (node) {
                const isCollapsed = node.getAttribute('data-j0-collapsed') === 'true';
                node.setAttribute('data-j0-collapsed', isCollapsed ? 'false' : 'true');
                caret.textContent = isCollapsed ? '▾' : '▸';
                syncCollapseButton(host);
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
        }
    });

    document.addEventListener('change', function (event) {
        const masterCheck = event.target.closest('[data-j0-tree-check-all]');
        if (masterCheck) {
            const host = masterCheck.closest('[data-j0-tree-host]') || document;
            const checked = masterCheck.checked;
            masterCheck.indeterminate = false;
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
    }

    document.querySelectorAll('[data-j0-tree-host]').forEach(reconcileTree);
})();