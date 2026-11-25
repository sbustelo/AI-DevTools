// PATH: ai0-003/ui/modules/0040-clipboard-actions--ui-helper/j0040-clipboard-actions.js
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
        if (form) {
            const msg = form.getAttribute('data-j0-confirm');
            if (msg && !globalThis.confirm(msg)) {
                event.preventDefault();
            }
        }
    });
})();