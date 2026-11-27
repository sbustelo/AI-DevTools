// PATH: ai0/ui/modules/0060-files-splitter--ui-widget/j0060-files-splitter.js
(function () {
    'use strict';

    const VFS_COL1_KEY = 'ai0_files_col1_px';
    const MIN_COL1 = 260;
    const MIN_COL2 = 360;
    const SPLITTER_W = 10;

    function clampCol1(gridEl, desiredPx) {
        const totalW = gridEl ? gridEl.getBoundingClientRect().width : 0;
        const maxAllowed = totalW > 0
            ? Math.max(MIN_COL1, totalW - MIN_COL2 - SPLITTER_W)
            : Math.max(MIN_COL1, desiredPx);
        return Math.max(MIN_COL1, Math.min(maxAllowed, desiredPx));
    }

    function applyCol1Width(gridEl, px, persist) {
        if (!gridEl) {
            return;
        }
        const safePx = Math.round(clampCol1(gridEl, px));
        gridEl.style.setProperty('--j0ui-col1-pref', safePx + 'px');
        if (persist && globalThis.j0 && globalThis.j0.vfs && typeof globalThis.j0.vfs.set === 'function') {
            globalThis.j0.vfs.set(VFS_COL1_KEY, String(safePx));
        }
        if (persist && globalThis.j0 && globalThis.j0.bus && typeof globalThis.j0.bus.emit === 'function') {
            globalThis.j0.bus.emit('files:splitter:resized', { width: safePx });
        }
    }

    function initSplitter(splitterEl) {
        const gridEl = splitterEl.closest('[data-j0-files-workspace]');
        if (!gridEl) {
            return;
        }

        if (globalThis.j0 && globalThis.j0.vfs && typeof globalThis.j0.vfs.get === 'function') {
            const saved = parseInt(String(globalThis.j0.vfs.get(VFS_COL1_KEY, '') || ''), 10);
            if (!Number.isNaN(saved) && saved > 0) {
                applyCol1Width(gridEl, saved, false);
            }
        }

        let dragging = false;
        let startX = 0;
        let startWidth = MIN_COL1;

        function onPointerMove(event) {
            if (!dragging) {
                return;
            }
            const deltaX = event.clientX - startX;
            applyCol1Width(gridEl, startWidth + deltaX, false);
        }

        function stopDrag(event) {
            if (!dragging) {
                return;
            }
            dragging = false;
            splitterEl.setAttribute('data-j0-dragging', 'false');
            document.documentElement.removeAttribute('data-j0-resizing');
            if (event && typeof event.pointerId === 'number' && splitterEl.hasPointerCapture(event.pointerId)) {
                splitterEl.releasePointerCapture(event.pointerId);
            }
            document.removeEventListener('pointermove', onPointerMove);
            document.removeEventListener('pointerup', stopDrag);
            document.removeEventListener('pointercancel', stopDrag);

            const firstChild = gridEl.firstElementChild;
            const finalW = firstChild ? firstChild.getBoundingClientRect().width : MIN_COL1;
            applyCol1Width(gridEl, finalW, true);
        }

        splitterEl.addEventListener('pointerdown', function (event) {
            if (event.button !== 0 && event.pointerType === 'mouse') {
                return;
            }
            event.preventDefault();
            const firstChild = gridEl.firstElementChild;
            startWidth = firstChild ? firstChild.getBoundingClientRect().width : MIN_COL1;
            startX = event.clientX;
            dragging = true;
            splitterEl.setAttribute('data-j0-dragging', 'true');
            document.documentElement.setAttribute('data-j0-resizing', 'col');
            if (typeof event.pointerId === 'number') {
                splitterEl.setPointerCapture(event.pointerId);
            }
            document.addEventListener('pointermove', onPointerMove);
            document.addEventListener('pointerup', stopDrag);
            document.addEventListener('pointercancel', stopDrag);
        });

        splitterEl.addEventListener('keydown', function (event) {
            const step = event.shiftKey ? 40 : 16;
            const firstChild = gridEl.firstElementChild;
            const currentW = firstChild ? firstChild.getBoundingClientRect().width : MIN_COL1;
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                applyCol1Width(gridEl, currentW - step, true);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                applyCol1Width(gridEl, currentW + step, true);
            }
        });
    }

    document.querySelectorAll('[data-j0-files-splitter]').forEach(initSplitter);

    globalThis.addEventListener('resize', function () {
        document.querySelectorAll('[data-j0-files-workspace]').forEach(function (gridEl) {
            const firstChild = gridEl.firstElementChild;
            const currentW = firstChild ? firstChild.getBoundingClientRect().width : 0;
            if (currentW > 0) {
                applyCol1Width(gridEl, currentW, false);
            }
        });
    });
})();