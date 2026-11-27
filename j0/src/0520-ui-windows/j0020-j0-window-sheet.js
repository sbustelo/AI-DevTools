// PATH: j0/src/0520-ui-windows/j0020-j0-window-sheet.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    if (typeof document === 'undefined' || !root.j0 || !root.j0.bus || typeof root.j0.bus.on !== 'function') {
        return;
    }

    function findTemplate(templateId) {
        if (!templateId) {
            return null;
        }
        return document.querySelector('[data-j0-template="' + templateId + '"]');
    }

    root.j0.bus.on('ui:sheet-open', function (payload) {
        const opts = payload || {};
        const templateId = opts.templateId || '';
        const trigger = opts.trigger || null;
        const win = opts.targetWindow
            || (trigger && trigger.closest ? trigger.closest('j0-window') : null)
            || document.querySelector('j0-window');

        if (!win || !templateId) {
            if (root.j0.logger && typeof root.j0.logger.error === 'function') {
                root.j0.logger.error('[j0ui:Sheet] Missing target window or templateId.');
            }
            return;
        }

        root.j0.bus.emit('ui:dismiss', {});

        const tpl = findTemplate(templateId);
        if (!tpl || !tpl.content) {
            return;
        }

        win.classList.add('has-modal-overlay');

        const clone = tpl.content.cloneNode(true);
        if (typeof root.j0.hydrateFragment === 'function') {
            root.j0.hydrateFragment(clone, opts.props || {}, './');
        }

        let sheetNode = clone.querySelector('.j0ui-window-sheet');
        if (!sheetNode) {
            sheetNode = document.createElement('div');
            sheetNode.className = 'j0ui-window-sheet is-open';
            sheetNode.appendChild(clone);
        } else {
            sheetNode.classList.add('is-open');
        }

        sheetNode.setAttribute('data-sheet-instance', templateId);
        win.appendChild(sheetNode);

        sheetNode.addEventListener('click', function (ev) {
            const actionTarget = ev.target.closest('[data-j0-action]');
            if (!actionTarget) {
                return;
            }
            const action = actionTarget.getAttribute('data-j0-action');
            if (!action) {
                return;
            }
            let actionPayload = {};
            const rawPayload = actionTarget.getAttribute('data-j0-payload');
            if (rawPayload) {
                try {
                    actionPayload = JSON.parse(rawPayload);
                } catch (err) {
                    actionPayload = {};
                }
            }
            actionPayload.trigger = actionTarget;
            root.j0.bus.emit(action, actionPayload);
        });
    });

    root.j0.bus.on('ui:sheet-close', function (payload) {
        const opts = payload || {};
        const trigger = opts.trigger || null;
        const templateId = opts.templateId || '';
        const win = opts.targetWindow
            || (trigger && trigger.closest ? trigger.closest('j0-window.has-modal-overlay') : null)
            || document.querySelector('j0-window.has-modal-overlay');

        if (!win) {
            return;
        }

        win.classList.remove('has-modal-overlay');

        const selector = templateId
            ? '.j0ui-window-sheet[data-sheet-instance="' + templateId + '"]'
            : '.j0ui-window-sheet';
        const sheet = win.querySelector(selector);
        if (sheet) {
            sheet.classList.remove('is-open');
            setTimeout(function () {
                if (sheet.parentNode) {
                    sheet.parentNode.removeChild(sheet);
                }
            }, 200);
        }
    });
})();