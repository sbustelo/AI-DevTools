// PATH: ai0/ui/modules/0040-clipboard-actions--ui-helper/j0040-clipboard-actions.js
(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        const copyBtn = event.target.closest('[data-j0-action="copy-target"]');
        if (copyBtn) {
            const targetKey = copyBtn.getAttribute('data-j0-target');
            const sourceEl = targetKey ? document.querySelector('[data-j0-copy-source="' + targetKey + '"]') : null;
            if (sourceEl && navigator.clipboard) {
                const text = typeof sourceEl.value === 'string' ? sourceEl.value : sourceEl.textContent;
                const origLabel = copyBtn.textContent;
                navigator.clipboard.writeText(text).then(function () {
                    copyBtn.textContent = 'Copied';
                    setTimeout(function () {
                        copyBtn.textContent = origLabel;
                    }, 1500);
                });
            }
            return;
        }

        const dlBtn = event.target.closest('[data-j0-action="download-target"]');
        if (dlBtn) {
            const targetKey = dlBtn.getAttribute('data-j0-target');
            const fileName = dlBtn.getAttribute('data-j0-filename') || 'ai0-export.txt';
            const sourceEl = targetKey ? document.querySelector('[data-j0-copy-source="' + targetKey + '"]') : null;
            if (sourceEl) {
                const text = typeof sourceEl.value === 'string' ? sourceEl.value : sourceEl.textContent;
                const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                setTimeout(function () {
                    URL.revokeObjectURL(url);
                    a.remove();
                }, 100);
            }
            return;
        }

    });

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('[data-j0-confirm]');
        if (!form) {
            return;
        }
        if (form.getAttribute('data-j0-confirm-bypass') === 'true') {
            form.removeAttribute('data-j0-confirm-bypass');
            return;
        }
        const msg = form.getAttribute('data-j0-confirm');
        if (!msg) {
            return;
        }
        if (globalThis.j0 && globalThis.j0.ui && typeof globalThis.j0.ui.confirm === 'function') {
            event.preventDefault();
            const submitter = event.submitter || null;
            const isDanger = form.hasAttribute('data-j0-purge-form')
                || form.getAttribute('data-j0-confirm-danger') === 'true';
            globalThis.j0.ui.confirm({
                title: form.getAttribute('data-j0-confirm-title') || (isDanger ? 'Confirm deletion' : 'Confirm'),
                icon: isDanger ? '🗑️' : '⚠️',
                message: msg,
                confirmLabel: form.getAttribute('data-j0-confirm-ok') || (isDanger ? 'Delete' : 'Confirm'),
                cancelLabel: form.getAttribute('data-j0-confirm-cancel') || 'Cancel',
                danger: isDanger
            }).then(function (confirmed) {
                if (!confirmed) {
                    return;
                }
                form.setAttribute('data-j0-confirm-bypass', 'true');
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter || undefined);
                } else {
                    form.submit();
                }
            });
            return;
        }
        if (!globalThis.confirm(msg)) {
            event.preventDefault();
        }
    });
})();