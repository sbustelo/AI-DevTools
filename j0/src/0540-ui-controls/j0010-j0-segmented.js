// PATH: j0/src/0540-ui-controls/j0010-j0-segmented.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    if (typeof document === 'undefined') {
        return;
    }

    function activateSegment(groupEl, btnEl, emit) {
        if (!groupEl || !btnEl) {
            return;
        }
        groupEl.querySelectorAll('[data-j0-segment]').forEach(function (btn) {
            const isTarget = btn === btnEl;
            btn.classList.toggle('j0-active', isTarget);
            btn.setAttribute('aria-pressed', isTarget ? 'true' : 'false');
        });
        if (emit && root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
            root.j0.bus.emit('j0:segmented:change', {
                group: groupEl.getAttribute('data-j0-segmented') || '',
                value: btnEl.getAttribute('data-j0-segment') || '',
                trigger: btnEl
            });
        }
    }

    document.addEventListener('click', function (event) {
        const btn = event.target.closest('[data-j0-segmented] [data-j0-segment]');
        if (!btn) {
            return;
        }
        const groupEl = btn.closest('[data-j0-segmented]');
        activateSegment(groupEl, btn, true);
    });

    root.j0 = root.j0 || {};
    root.j0.ui = root.j0.ui || {};
    root.j0.ui.setSegment = function (groupName, value) {
        const groupEl = document.querySelector('[data-j0-segmented="' + groupName + '"]');
        if (!groupEl) {
            return;
        }
        const btn = groupEl.querySelector('[data-j0-segment="' + value + '"]');
        if (btn) {
            activateSegment(groupEl, btn, false);
        }
    };
})();