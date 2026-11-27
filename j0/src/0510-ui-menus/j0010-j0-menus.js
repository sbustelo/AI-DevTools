// PATH: ai0/j0/src/0510-ui-menus/j0010-j0-menus.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    if (typeof document === 'undefined') {
        return;
    }

    root.j0 = root.j0 || {};
    root.j0.ui = root.j0.ui || {};

    let safeZoneEl = null;
    let activeTrigger = null;
    let activeSubmenu = null;
    let activeDropdownTrigger = null;
    let activeDropdownMenu = null;
    let openTimer = null;
    let closeTimer = null;
    let lastPointer = { x: 0, y: 0 };

    function ensureSafeZone() {
        if (safeZoneEl) {
            return safeZoneEl;
        }
        const el = document.createElement('div');
        el.className = 'j0-submenu-safezone';
        el.setAttribute('data-j0-submenu-safezone', '');
        el.setAttribute('data-j0-hidden', 'true');
        document.body.appendChild(el);
        safeZoneEl = el;
        return el;
    }

    function clearTimers() {
        if (openTimer) {
            clearTimeout(openTimer);
            openTimer = null;
        }
        if (closeTimer) {
            clearTimeout(closeTimer);
            closeTimer = null;
        }
    }

    function hideSafeZone() {
        if (safeZoneEl) {
            safeZoneEl.setAttribute('data-j0-hidden', 'true');
            safeZoneEl.style.clipPath = '';
        }
    }

    function closeActiveDropdown() {
        if (activeDropdownMenu) {
            activeDropdownMenu.setAttribute('data-j0-hidden', 'true');
        }
        if (activeDropdownTrigger) {
            activeDropdownTrigger.setAttribute('aria-expanded', 'false');
        }
        activeDropdownTrigger = null;
        activeDropdownMenu = null;
    }

    function openDropdown(triggerEl, menuEl) {
        if (activeDropdownMenu && activeDropdownMenu !== menuEl) {
            closeActiveDropdown();
        }
        if (menuEl.parentNode !== document.body) {
            document.body.appendChild(menuEl);
        }
        const tRect = triggerEl.getBoundingClientRect();
        menuEl.style.minWidth = Math.round(tRect.width) + 'px';
        menuEl.setAttribute('data-j0-hidden', 'false');
        triggerEl.setAttribute('aria-expanded', 'true');

        const mRect = menuEl.getBoundingClientRect();
        const vw = root.innerWidth || document.documentElement.clientWidth;
        const vh = root.innerHeight || document.documentElement.clientHeight;

        let left = tRect.left;
        if (left + mRect.width > vw - 8) {
            left = Math.max(8, vw - mRect.width - 8);
        }
        let top = tRect.bottom + 4;
        if (top + mRect.height > vh - 8 && tRect.top - mRect.height - 4 > 8) {
            top = tRect.top - mRect.height - 4;
        }

        menuEl.style.left = Math.round(left) + 'px';
        menuEl.style.top = Math.round(top) + 'px';
        activeDropdownTrigger = triggerEl;
        activeDropdownMenu = menuEl;
    }

    function closeActiveSubmenu() {
        clearTimers();
        if (activeSubmenu) {
            activeSubmenu.setAttribute('data-j0-hidden', 'true');
        }
        activeTrigger = null;
        activeSubmenu = null;
        hideSafeZone();
    }

    function updateTriangle(px, py, subRect) {
        const zone = ensureSafeZone();
        const isRight = subRect.left >= px;
        const cornerTopX = isRight ? subRect.left : subRect.right;
        const cornerTopY = subRect.top;
        const cornerBotX = isRight ? subRect.left : subRect.right;
        const cornerBotY = subRect.bottom;

        const pad = 4;
        const p1 = Math.round(px) + 'px ' + Math.round(py) + 'px';
        const p2 = Math.round(cornerTopX) + 'px ' + Math.round(cornerTopY - pad) + 'px';
        const p3 = Math.round(cornerBotX) + 'px ' + Math.round(cornerBotY + pad) + 'px';

        zone.style.clipPath = 'polygon(' + p1 + ', ' + p2 + ', ' + p3 + ')';
        zone.setAttribute('data-j0-hidden', 'false');
    }

    function positionSubmenu(triggerEl, submenuEl) {
        const tRect = triggerEl.getBoundingClientRect();
        submenuEl.setAttribute('data-j0-hidden', 'false');
        const sRect = submenuEl.getBoundingClientRect();
        const vw = root.innerWidth || document.documentElement.clientWidth;
        const vh = root.innerHeight || document.documentElement.clientHeight;

        let left = tRect.right - 2;
        if (left + sRect.width > vw - 8) {
            left = Math.max(8, tRect.left - sRect.width + 2);
        }
        let top = tRect.top - 4;
        if (top + sRect.height > vh - 8) {
            top = Math.max(8, vh - sRect.height - 8);
        }

        submenuEl.style.left = Math.round(left) + 'px';
        submenuEl.style.top = Math.round(top) + 'px';
        return submenuEl.getBoundingClientRect();
    }

    function openSubmenu(triggerEl, submenuEl) {
        console.log('[j0:menus] 3. openSubmenu:', triggerEl.getAttribute('data-j0-submenu-trigger'), submenuEl);
        if (activeSubmenu && activeSubmenu !== submenuEl) {
            activeSubmenu.setAttribute('data-j0-hidden', 'true');
        }
        activeTrigger = triggerEl;
        activeSubmenu = submenuEl;
        const subRect = positionSubmenu(triggerEl, submenuEl);
        console.log('[j0:menus] 4. positioned subRect:', subRect, 'pointer:', lastPointer);
        updateTriangle(lastPointer.x, lastPointer.y, subRect);
    }

    document.addEventListener('pointermove', function (event) {
        lastPointer.x = event.clientX;
        lastPointer.y = event.clientY;
        if (activeSubmenu && activeTrigger && activeSubmenu.getAttribute('data-j0-hidden') === 'false') {
            const overTrigger = activeTrigger.contains(event.target);
            const overSubmenu = activeSubmenu.contains(event.target);
            const overZone = safeZoneEl && event.target === safeZoneEl;
            if (overTrigger) {
                updateTriangle(event.clientX, event.clientY, activeSubmenu.getBoundingClientRect());
            } else if (!overSubmenu && !overZone && !closeTimer) {
                closeTimer = setTimeout(closeActiveSubmenu, 180);
            } else if (overSubmenu || overZone) {
                if (closeTimer) {
                    clearTimeout(closeTimer);
                    closeTimer = null;
                }
            }
        }
    }, true);

    document.addEventListener('pointerover', function (event) {
        const trigger = event.target.closest('[data-j0-submenu-trigger]');
        if (trigger) {
            const subId = trigger.getAttribute('data-j0-submenu-trigger') || '';
            const submenu = document.querySelector('[data-j0-submenu="' + subId + '"]');
            if (activeTrigger === trigger && activeSubmenu === submenu && submenu && submenu.getAttribute('data-j0-hidden') === 'false') {
                if (closeTimer) {
                    clearTimeout(closeTimer);
                    closeTimer = null;
                }
                return;
            }
            clearTimers();
            console.log('[j0:menus] 1. pointerover trigger:', subId, 'submenu found:', Boolean(submenu));
            if (submenu) {
                openTimer = setTimeout(function () {
                    openSubmenu(trigger, submenu);
                }, 75);
            }
            return;
        }

        if (activeSubmenu && !activeSubmenu.contains(event.target) && event.target !== safeZoneEl) {
            const otherItem = event.target.closest('[role="menuitem"]');
            if (otherItem && otherItem !== activeTrigger) {
                clearTimers();
                closeTimer = setTimeout(closeActiveSubmenu, 120);
            }
        }
    });

    document.addEventListener('click', function (event) {
        const ddTrigger = event.target.closest('[data-j0-dropdown-trigger]');
        if (ddTrigger) {
            event.preventDefault();
            event.stopPropagation();
            const ddId = ddTrigger.getAttribute('data-j0-dropdown-trigger') || '';
            const ddMenu = document.querySelector('[data-j0-dropdown-menu="' + ddId + '"]');
            if (ddMenu) {
                if (activeDropdownMenu === ddMenu && ddMenu.getAttribute('data-j0-hidden') === 'false') {
                    closeActiveDropdown();
                } else {
                    openDropdown(ddTrigger, ddMenu);
                }
            }
            return;
        }

        const ddOption = event.target.closest('[data-j0-dropdown-option]');
        if (ddOption) {
            const val = ddOption.getAttribute('data-j0-value') || '';
            const href = ddOption.getAttribute('data-j0-href') || '';
            const menuWrap = ddOption.closest('[data-j0-dropdown-menu]');
            const ddId = menuWrap ? (menuWrap.getAttribute('data-j0-dropdown-menu') || '') : '';
            closeActiveDropdown();
            if (root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
                root.j0.bus.emit('j0:dropdown:change', { id: ddId, value: val, href: href });
            }
            if (href !== '') {
                root.location.href = href;
            }
            return;
        }

        if (activeDropdownMenu && !activeDropdownMenu.contains(event.target) && (!activeDropdownTrigger || !activeDropdownTrigger.contains(event.target))) {
            closeActiveDropdown();
        }

        const trigger = event.target.closest('[data-j0-submenu-trigger]');
        if (trigger) {
            event.preventDefault();
            event.stopPropagation();
            clearTimers();
            const subId = trigger.getAttribute('data-j0-submenu-trigger') || '';
            const submenu = document.querySelector('[data-j0-submenu="' + subId + '"]');
            console.log('[j0:menus] 2. click trigger:', subId, 'submenu found:', Boolean(submenu));
            if (submenu) {
                openSubmenu(trigger, submenu);
            }
            return;
        }
        if (activeSubmenu && !activeSubmenu.contains(event.target) && (!activeTrigger || !activeTrigger.contains(event.target))) {
            closeActiveSubmenu();
        }
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeActiveSubmenu();
            closeActiveDropdown();
        }
    });

    root.addEventListener('resize', closeActiveDropdown);

    root.j0.ui.menus = {
        closeAll: function () {
            closeActiveSubmenu();
            closeActiveDropdown();
        }
    };
    console.log('[j0:menus] 0. booted');
})();