// PATH: ai0-003/ui/modules/0030-category-panel--ui-widget/j0030-category-panel.js
(function () {
    'use strict';

    function syncCategoryControls() {
        document.querySelectorAll('[data-j0-cat-count]').forEach(function (badge) {
            const catId = badge.getAttribute('data-j0-cat-count');
            const catNodes = Array.from(document.querySelectorAll('[data-j0-tree-node][data-j0-cat="' + catId + '"]'));
            const totalExcluded = catNodes.filter(function (node) {
                return node.getAttribute('data-j0-excluded') === 'true';
            }).length;
            const totalIncluded = catNodes.length - totalExcluded;
            badge.textContent = String(totalExcluded);

            const catCheck = document.querySelector('[data-j0-cat-check="' + catId + '"]');
            if (catCheck) {
                catCheck.checked = catNodes.length > 0 && totalExcluded === 0;
                catCheck.indeterminate = totalIncluded > 0 && totalExcluded > 0;
            }
        });
    }

    document.addEventListener('change', function (event) {
        const catCheck = event.target.closest('[data-j0-cat-check]');
        if (!catCheck) {
            return;
        }
        const catId = catCheck.getAttribute('data-j0-cat-check');
        const includeCategory = catCheck.checked;
        catCheck.indeterminate = false;
        const selector = '[data-j0-tree-node][data-j0-cat="' + catId + '"]';

        document.querySelectorAll(selector).forEach(function (node) {
            node.setAttribute('data-j0-excluded', includeCategory ? 'false' : 'true');
            node.querySelectorAll('[data-j0-tree-node]').forEach(function (descNode) {
                descNode.setAttribute('data-j0-excluded', includeCategory ? 'false' : 'true');
            });
            node.querySelectorAll('[data-j0-tree-check]').forEach(function (cb) {
                cb.checked = includeCategory;
                cb.indeterminate = false;
            });
        });

        if (globalThis.j0 && globalThis.j0.bus) {
            globalThis.j0.bus.emit('j0:tree:reconcile', {});
        } else {
            syncCategoryControls();
        }
    });

    if (globalThis.j0 && globalThis.j0.bus) {
        globalThis.j0.bus.on('j0:tree:changed', syncCategoryControls);
    }

    syncCategoryControls();
})();