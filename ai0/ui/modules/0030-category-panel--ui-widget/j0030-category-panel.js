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