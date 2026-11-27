// PATH: ai0/ui/modules/0035-purge-counter--ui-widget/j0035-purge-counter.js
(function () {
    'use strict';

    function syncPurgeForm(formEl) {
        if (!formEl) {
            return;
        }
        let totalChecked = 0;

        formEl.querySelectorAll('[data-j0-purge-group]').forEach(function (groupEl) {
            const boxes = Array.from(groupEl.querySelectorAll('input[type="checkbox"][name="items[]"]'));
            const checked = boxes.filter(function (cb) {
                return cb.checked;
            }).length;
            totalChecked += checked;

            const counterEl = groupEl.querySelector('[data-j0-purge-counter]');
            if (counterEl) {
                const ofWord = counterEl.getAttribute('data-j0-label-of') || 'of';
                counterEl.textContent = checked + ' ' + ofWord + ' ' + boxes.length;
            }
        });

        const totalEl = formEl.querySelector('[data-j0-purge-total]');
        if (totalEl) {
            totalEl.textContent = String(totalChecked);
        }

        const submitBtn = formEl.querySelector('[data-j0-purge-submit]');
        if (submitBtn) {
            submitBtn.disabled = totalChecked === 0;
        }

        if (globalThis.j0 && globalThis.j0.bus && typeof globalThis.j0.bus.emit === 'function') {
            globalThis.j0.bus.emit('purge:selection:changed', { selected: totalChecked });
        }
    }

    document.addEventListener('change', function (event) {
        const cb = event.target.closest('[data-j0-purge-form] input[type="checkbox"][name="items[]"]');
        if (!cb) {
            return;
        }
        syncPurgeForm(cb.closest('[data-j0-purge-form]'));
    });

    document.addEventListener('click', function (event) {
        const btn = event.target.closest('[data-j0-purge-select]');
        if (!btn) {
            return;
        }
        const groupEl = btn.closest('[data-j0-purge-group]');
        const formEl = btn.closest('[data-j0-purge-form]');
        if (!groupEl || !formEl) {
            return;
        }
        const checkAll = btn.getAttribute('data-j0-purge-select') === 'all';
        groupEl.querySelectorAll('input[type="checkbox"][name="items[]"]').forEach(function (cb) {
            cb.checked = checkAll;
        });
        syncPurgeForm(formEl);
    });

    document.querySelectorAll('[data-j0-purge-form]').forEach(syncPurgeForm);
})();