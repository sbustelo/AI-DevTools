// PATH: j0/examples/05-tognazzini-menu/j0010-demo-tognazzini.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    if (typeof document === 'undefined') {
        return;
    }

    const statusEl = document.querySelector('[data-demo-status]');
    const safeToggle = document.querySelector('[data-demo-safezone-toggle]');
    const themeToggle = document.querySelector('[data-demo-theme-toggle]');
    const naiveMenu = document.querySelector('[data-demo-naive-menu]');
    const naiveSub = document.querySelector('[data-demo-naive-sub]');
    const ctxMenu = document.querySelector('[data-demo-ctx-menu]');

    function setStatus(text) {
        if (statusEl) {
            statusEl.textContent = text;
        }
    }

    if (safeToggle) {
        safeToggle.addEventListener('change', function () {
            document.documentElement.setAttribute(
                'data-demo-show-safezone',
                safeToggle.checked ? 'true' : 'false'
            );
        });
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', function () {
            const current = document.documentElement.getAttribute('data-ipax-theme') || 'dark';
            const next = current.startsWith('dark') ? 'light' : 'dark';
            document.documentElement.setAttribute('data-ipax-theme', next);
            setStatus('Theme switched to: ' + next);
        });
    }

    // Naive Hover Menu (NO Safe-Triangle): crossing any sibling immediately kills the submenu
    if (naiveMenu && naiveSub) {
        naiveMenu.addEventListener('pointerover', function (event) {
            const item = event.target.closest('[role="menuitem"]');
            if (!item) {
                return;
            }
            if (item.hasAttribute('data-demo-naive-trigger')) {
                naiveSub.setAttribute('data-j0-hidden', 'false');
            } else {
                naiveSub.setAttribute('data-j0-hidden', 'true');
            }
        });

        const naiveStage = naiveMenu.closest('[data-demo-naive-stage]');
        if (naiveStage) {
            naiveStage.addEventListener('pointerleave', function () {
                naiveSub.setAttribute('data-j0-hidden', 'true');
            });
        }
    }

    // Context menu anywhere on screen (Right-Click or Ctrl+Click)
    function openFloatingDemo(x, y) {
        if (!ctxMenu) {
            return;
        }
        if (root.j0 && root.j0.ui && typeof root.j0.ui.floatAt === 'function') {
            root.j0.ui.floatAt(ctxMenu, x, y);
        } else {
            ctxMenu.style.setProperty('--j0-float-x', Math.round(x) + 'px');
            ctxMenu.style.setProperty('--j0-float-y', Math.round(y) + 'px');
            ctxMenu.setAttribute('data-j0-hidden', 'false');
        }
        setStatus('Context menu opened at (' + Math.round(x) + ', ' + Math.round(y) + ')');
    }

    document.addEventListener('contextmenu', function (event) {
        event.preventDefault();
        openFloatingDemo(event.clientX, event.clientY);
    });

    document.addEventListener('click', function (event) {
        if (event.ctrlKey) {
            event.preventDefault();
            openFloatingDemo(event.clientX, event.clientY);
            return;
        }

        const leaf = event.target.closest('[data-demo-leaf]');
        if (leaf) {
            setStatus('Selected: ' + leaf.getAttribute('data-demo-leaf'));
            if (ctxMenu) {
                ctxMenu.setAttribute('data-j0-hidden', 'true');
            }
            if (root.j0 && root.j0.ui && root.j0.ui.menus) {
                root.j0.ui.menus.closeAll();
            }
            return;
        }

        if (ctxMenu && !ctxMenu.contains(event.target) && !event.target.closest('[data-j0-submenu]')) {
            ctxMenu.setAttribute('data-j0-hidden', 'true');
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && ctxMenu) {
            ctxMenu.setAttribute('data-j0-hidden', 'true');
        }
    });
})();