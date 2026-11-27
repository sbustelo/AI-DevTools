/*! j0 compiled */
;
/* === SOURCE: j0010-kernel.js === */
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined'
        ? globalThis
        : (typeof window !== 'undefined' ? window : (typeof self !== 'undefined' ? self : {}));

    root.j0 = root.j0 || {};
    const j0 = root.j0;

    if (j0.bus && j0.registry) {
        return;
    }

    class EventChannel {
        constructor(name = 'global') {
            this.name = name;
            this._handlers = new Map();
        }

        emit(action, payload = {}) {
            if (j0.QA && j0.bus && j0.bus.schema) {
                const contract = j0.bus.schema[action];
                if (contract) {
                    for (const [key, expectedType] of Object.entries(contract)) {
                        const actualType = typeof payload[key];
                        if (actualType !== expectedType) {
                            const msg = '[j0:bus:schema] Invalid payload on \'' + action + '\': \'' + key + '\' must be \'' + expectedType + '\', got \'' + actualType + '\'.';
                            j0.logger.error(msg);
                            throw new Error(msg);
                        }
                    }
                }
            }

            const set = this._handlers.get(action);
            if (!set || set.size === 0) {
                return;
            }

            new Set(set).forEach((fn) => {
                try {
                    fn(payload);
                } catch (err) {
                    const preview = fn.toString().substring(0, 60) + '...';
                    j0.logger.error('[j0:bus] Handler crash on \'' + this.name + ':' + action + '\' (' + preview + ')', err);
                }
            });
        }

        on(action, handler) {
            if (typeof handler !== 'function') {
                return () => {};
            }
            if (!this._handlers.has(action)) {
                this._handlers.set(action, new Set());
            }
            this._handlers.get(action).add(handler);
            return () => {
                this.off(action, handler);
            };
        }

        off(action, handler) {
            const set = this._handlers.get(action);
            if (!set) {
                return;
            }
            set.delete(handler);
            if (set.size === 0) {
                this._handlers.delete(action);
            }
        }

        once(action, handler) {
            const wrapper = (payload) => {
                this.off(action, wrapper);
                handler(payload);
            };
            return this.on(action, wrapper);
        }
    }

    const MAX_LOG_ENTRIES = 200;
    const logEntries = [];

    function recordLog(level, msg, data) {
        const entry = { level, msg: String(msg), data: data ?? null, ts: Date.now() };
        logEntries.push(entry);
        if (logEntries.length > MAX_LOG_ENTRIES) {
            logEntries.shift();
        }
        if (j0.bus && level !== 'silent') {
            j0.bus.emit('j0:log', { level, msg: entry.msg });
        }
        return entry;
    }

    j0.EventChannel = EventChannel;
    j0.bus = j0.bus || new EventChannel('global');
    j0.bus.schema = j0.bus.schema || {};
    j0.registry = j0.registry || new Map();

    j0.logger = j0.logger || {
        log(msg, data) {
            recordLog('info', msg, data);
            if (j0.QA || j0.env === 'local') {
                console.log('[j0] ' + msg, data ?? '');
            }
        },
        warn(msg, data) {
            recordLog('warn', msg, data);
            console.warn('[j0:warn] ' + msg, data ?? '');
        },
        error(msg, data) {
            recordLog('error', msg, data);
            console.error('[j0:error] ' + msg, data ?? '');
        },
        history() {
            return logEntries.slice();
        },
        clear() {
            logEntries.length = 0;
        }
    };

    j0.register = (name, instance) => {
        j0.registry.set(name, instance);
        j0.bus.emit('j0:module:registered', { name });
        return instance;
    };

    j0.get = (name) => j0.registry.get(name) ?? null;
    j0.has = (name) => j0.registry.has(name);

    j0.bus.schema['j0:module:registered'] = { name: 'string' };
    j0.bus.schema['j0:log'] = { level: 'string', msg: 'string' };
})();
;
/* === SOURCE: j0506-floater.js === */
// PATH: ai0/j0/src/0500-ui-patterns/j0506-floater.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    if (!root.j0 || !root.j0.bus) {
        return;
    }

    const j0 = root.j0;
    j0.ui = j0.ui || {};

    const activeFloaters = new Set();
    const VIEWPORT_MARGIN = 8;

    function clampPosition(el, rawX, rawY) {
        const vw = typeof window !== 'undefined' ? window.innerWidth : 1024;
        const vh = typeof window !== 'undefined' ? window.innerHeight : 768;
        const rect = el.getBoundingClientRect();
        const width = rect.width || 180;
        const height = rect.height || 80;

        const maxX = Math.max(VIEWPORT_MARGIN, vw - width - VIEWPORT_MARGIN);
        const maxY = Math.max(VIEWPORT_MARGIN, vh - height - VIEWPORT_MARGIN);

        return {
            x: Math.max(VIEWPORT_MARGIN, Math.min(rawX, maxX)),
            y: Math.max(VIEWPORT_MARGIN, Math.min(rawY, maxY))
        };
    }

    function teleportToBody(el) {
        if (typeof document !== 'undefined' && document.body && el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
    }

    function closeAll(exceptEl) {
        activeFloaters.forEach(function (el) {
            if (exceptEl && el === exceptEl) {
                return;
            }
            el.setAttribute('data-j0-hidden', 'true');
            el.setAttribute('data-j0-floater', 'closed');
            activeFloaters.delete(el);
        });
    }

    function openAt(el, x, y) {
        if (!el) {
            return;
        }
        j0.bus.emit('ui:dismiss', { source: el });
        teleportToBody(el);
        el.setAttribute('data-j0-hidden', 'false');
        el.setAttribute('data-j0-floater', 'open');

        const pos = clampPosition(el, x, y);
        el.style.setProperty('--j0-float-x', pos.x + 'px');
        el.style.setProperty('--j0-float-y', pos.y + 'px');
        activeFloaters.add(el);
        j0.bus.emit('ui:floater:open', { target: el, x: pos.x, y: pos.y });
    }

    function openForTrigger(trigger, el) {
        if (!trigger || !el) {
            return;
        }
        const rect = trigger.getBoundingClientRect();
        openAt(el, rect.left, rect.bottom + 4);
    }

    j0.ui.floatAt = openAt;
    j0.ui.floatOpen = openForTrigger;
    j0.ui.floatCloseAll = function () {
        closeAll(null);
    };

    j0.bus.on('ui:dismiss', function (payload) {
        const exceptEl = payload && payload.source ? payload.source : null;
        closeAll(exceptEl);
    });

    if (typeof document !== 'undefined') {
        document.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-j0-float]');
            if (trigger) {
                const targetId = trigger.getAttribute('data-j0-float');
                const panel = targetId ? document.querySelector('[data-j0-float-id="' + targetId + '"]') : null;
                if (panel) {
                    event.preventDefault();
                    if (activeFloaters.has(panel)) {
                        closeAll(null);
                    } else {
                        openForTrigger(trigger, panel);
                    }
                }
                return;
            }

            if (activeFloaters.size === 0) {
                return;
            }
            const insideFloater = event.target.closest('[data-j0-floater="open"]');
            if (!insideFloater) {
                j0.bus.emit('ui:dismiss', {});
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && activeFloaters.size > 0) {
                j0.bus.emit('ui:dismiss', {});
            }
        });
    }

    if (j0.logger && typeof j0.logger.log === 'function') {
        j0.logger.log('[j0ui:Floater] initialized.');
    }
})();
;
/* === SOURCE: j0010-j0-menus.js === */
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
;
/* === SOURCE: j0010-demo-tognazzini.js === */
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
