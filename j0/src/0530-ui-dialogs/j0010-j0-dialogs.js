// PATH: j0/src/0530-ui-dialogs/j0010-j0-dialogs.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    if (typeof document === 'undefined') {
        return;
    }

    root.j0 = root.j0 || {};
    root.j0.ui = root.j0.ui || {};

    let dialogEl = null;
    let titleEl = null;
    let iconEl = null;
    let contentEl = null;
    let footerEl = null;
    let activeCancelHandler = null;

    function ensureDialog() {
        if (dialogEl && document.body.contains(dialogEl)) {
            return dialogEl;
        }

        dialogEl = document.createElement('dialog');
        dialogEl.className = 'j0-dialog';
        dialogEl.setAttribute('data-j0-sys-dialog', 'true');

        const inner = document.createElement('div');
        inner.className = 'j0-dialog-inner';

        const header = document.createElement('header');
        header.className = 'j0-dialog-header';

        iconEl = document.createElement('span');
        iconEl.className = 'j0-dialog-icon';
        iconEl.setAttribute('aria-hidden', 'true');
        iconEl.setAttribute('data-j0-hidden', 'true');

        titleEl = document.createElement('h3');
        titleEl.className = 'j0-dialog-title';

        header.appendChild(iconEl);
        header.appendChild(titleEl);

        contentEl = document.createElement('div');
        contentEl.className = 'j0-dialog-content';

        footerEl = document.createElement('footer');
        footerEl.className = 'j0-dialog-footer';

        inner.appendChild(header);
        inner.appendChild(contentEl);
        inner.appendChild(footerEl);
        dialogEl.appendChild(inner);
        document.body.appendChild(dialogEl);

        dialogEl.addEventListener('click', function (event) {
            if (event.target === dialogEl) {
                dialogEl.close('backdrop');
            }
        });

        dialogEl.addEventListener('close', function () {
            if (typeof activeCancelHandler === 'function') {
                const fn = activeCancelHandler;
                activeCancelHandler = null;
                fn();
            }
        });

        return dialogEl;
    }

    function openDialog(payload) {
        const opts = payload || {};
        const dialog = ensureDialog();

        if (root.j0.bus) {
            root.j0.bus.emit('ui:dismiss', { source: 'j0-dialog' });
        }

        titleEl.textContent = opts.title || '';
        if (opts.icon) {
            iconEl.textContent = opts.icon;
            iconEl.setAttribute('data-j0-hidden', 'false');
        } else {
            iconEl.textContent = '';
            iconEl.setAttribute('data-j0-hidden', 'true');
        }

        contentEl.textContent = opts.message || '';
        footerEl.textContent = '';
        activeCancelHandler = typeof opts.onCancel === 'function' ? opts.onCancel : null;

        const actions = Array.isArray(opts.actions) ? opts.actions : [];
        actions.forEach(function (action) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = action.label || 'OK';
            btn.className = 'j0-dialog-btn' + (action.style ? ' j0-dialog-btn--' + action.style : '');

            btn.addEventListener('click', function () {
                activeCancelHandler = null;
                dialog.close(action.value || 'action');
                if (typeof action.onClick === 'function') {
                    action.onClick();
                }
                if (action.emit && root.j0.bus) {
                    root.j0.bus.emit(action.emit, action.payload || {});
                }
            });

            footerEl.appendChild(btn);
        });

        if (dialog.open) {
            dialog.close();
        }
        if (opts.modal !== false && typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else if (typeof dialog.show === 'function') {
            dialog.show();
        }
    }

    function confirmDialog(options) {
        const opts = options || {};
        return new Promise(function (resolve) {
            openDialog({
                title: opts.title || 'Confirm',
                icon: opts.icon || '⚠️',
                message: opts.message || '',
                onCancel: function () {
                    resolve(false);
                },
                actions: [
                    {
                        label: opts.cancelLabel || 'Cancel',
                        value: 'cancel',
                        onClick: function () {
                            resolve(false);
                        }
                    },
                    {
                        label: opts.confirmLabel || 'Confirm',
                        style: opts.danger ? 'danger' : 'primary',
                        value: 'confirm',
                        onClick: function () {
                            resolve(true);
                        }
                    }
                ]
            });
        });
    }

    root.j0.ui.dialog = openDialog;
    root.j0.ui.confirm = confirmDialog;

    if (root.j0.bus) {
        root.j0.bus.on('ui:dialog', openDialog);
    }
})();