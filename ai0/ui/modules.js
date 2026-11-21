/* === SOURCE: j0020-tree-browser.js === */
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
;
/* === SOURCE: j0030-category-panel.js === */
// PATH: ai0-003/ui/modules/0030-category-panel--ui-widget/j0030-category-panel.js
(function () {
    'use strict';

    document.addEventListener('change', function (event) {
        const catCheck = event.target.closest('[data-j0-cat-check]');
        if (!catCheck) {
            return;
        }
        const catId = catCheck.getAttribute('data-j0-cat-check');
        const includeCategory = catCheck.checked;
        const selector = '[data-j0-tree-node][data-j0-cat="' + catId + '"]';

        document.querySelectorAll(selector).forEach(function (node) {
            node.setAttribute('data-j0-excluded', includeCategory ? 'false' : 'true');
            node.querySelectorAll('[data-j0-tree-check]').forEach(function (cb) {
                cb.checked = includeCategory;
            });
        });

        document.dispatchEvent(new CustomEvent('j0:tree:changed'));
    });

    document.addEventListener('j0:tree:changed', function () {
        document.querySelectorAll('[data-j0-cat-count]').forEach(function (badge) {
            const catId = badge.getAttribute('data-j0-cat-count');
            const totalExcluded = document.querySelectorAll(
                '[data-j0-tree-node][data-j0-cat="' + catId + '"][data-j0-excluded="true"]'
            ).length;
            badge.textContent = String(totalExcluded);
        });
    });
})();
;
/* === SOURCE: j0040-clipboard-actions.js === */
// PATH: ai0-003/ui/modules/0040-clipboard-actions--ui-helper/j0040-clipboard-actions.js
(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        const copyBtn = event.target.closest('[data-j0-action="copy-target"]');
        if (copyBtn) {
            const targetKey = copyBtn.getAttribute('data-j0-target');
            const sourceEl = targetKey ? document.querySelector('[data-j0-copy-source="' + targetKey + '"]') : null;
            if (sourceEl && navigator.clipboard) {
                const text = typeof sourceEl.value === 'string' ? sourceEl.value : sourceEl.textContent;
                const origLabel = copyBtn.textContent;
                navigator.clipboard.writeText(text).then(function () {
                    copyBtn.textContent = 'Copiado';
                    setTimeout(function () {
                        copyBtn.textContent = origLabel;
                    }, 1500);
                });
            }
            return;
        }

        const dlBtn = event.target.closest('[data-j0-action="download-target"]');
        if (dlBtn) {
            const targetKey = dlBtn.getAttribute('data-j0-target');
            const fileName = dlBtn.getAttribute('data-j0-filename') || 'ai0-export.txt';
            const sourceEl = targetKey ? document.querySelector('[data-j0-copy-source="' + targetKey + '"]') : null;
            if (sourceEl) {
                const text = typeof sourceEl.value === 'string' ? sourceEl.value : sourceEl.textContent;
                const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                setTimeout(function () {
                    URL.revokeObjectURL(url);
                    a.remove();
                }, 100);
            }
            return;
        }

        const themeBtn = event.target.closest('[data-j0-action="theme-toggle"]');
        if (themeBtn) {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme') || 'dark';
            html.setAttribute('data-theme', current === 'dark' ? 'light' : 'dark');
        }
    });

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('[data-j0-confirm]');
        if (form) {
            const msg = form.getAttribute('data-j0-confirm');
            if (msg && !window.confirm(msg)) {
                event.preventDefault();
            }
        }
    });
})();
