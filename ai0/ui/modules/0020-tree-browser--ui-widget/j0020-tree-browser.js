// PATH: ai0-003/ui/modules/0020-tree-browser--ui-widget/j0020-tree-browser.js
(function () {
    'use strict';

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
                }
            } else {
                node.setAttribute('data-j0-hidden', 'true');
            }
        });
    }

    document.addEventListener('click', function (event) {
        const caret = event.target.closest('[data-j0-tree-toggle]');
        if (caret) {
            const node = caret.closest('[data-j0-tree-node]');
            if (node) {
                const isCollapsed = node.getAttribute('data-j0-collapsed') === 'true';
                node.setAttribute('data-j0-collapsed', isCollapsed ? 'false' : 'true');
                caret.textContent = isCollapsed ? '▾' : '▸';
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
            }
            return;
        }

        const expandBtn = event.target.closest('[data-j0-action="tree-expand-all"]');
        if (expandBtn) {
            const host = expandBtn.closest('[data-j0-tree-host]') || document;
            host.querySelectorAll('[data-j0-tree-node]').forEach(function (n) {
                n.setAttribute('data-j0-collapsed', 'false');
                const c = n.querySelector(':scope > .j0ui-tree-row [data-j0-tree-toggle]');
                if (c) {
                    c.textContent = '▾';
                }
            });
        }
    });

    document.addEventListener('change', function (event) {
        const checkbox = event.target.closest('[data-j0-tree-check]');
        if (!checkbox) {
            return;
        }
        const node = checkbox.closest('[data-j0-tree-node]');
        if (node) {
            const checked = checkbox.checked;
            node.querySelectorAll('[data-j0-tree-check]').forEach(function (childCb) {
                childCb.checked = checked;
            });
            node.setAttribute('data-j0-excluded', checked ? 'false' : 'true');
        }
        document.dispatchEvent(new CustomEvent('j0:tree:changed'));
    });
})();