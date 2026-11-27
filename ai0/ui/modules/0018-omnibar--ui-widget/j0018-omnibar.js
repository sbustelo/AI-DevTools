// PATH: ai0/ui/modules/0018-omnibar--ui-widget/j0018-omnibar.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    if (typeof document === 'undefined') {
        return;
    }

    const COMMAND_HEADER_RE = /(?:^|\n)\s*===\s*(?:AI0(?:\s*:|\s*===)|ROOT:|PATCH:|CREATE:|MOVE:|DISABLE:|DISCARD:|DUMP:|TREE:)/i;

    function initLangSwitcher() {
        const langSelect = document.querySelector('[data-j0-lang-select]');
        if (!langSelect) {
            return;
        }
        if (root.j0 && root.j0.vfs && typeof root.j0.vfs.registerUiSync === 'function') {
            root.j0.vfs.registerUiSync('lang', {
                shortKey: 'l',
                encode: function (v) { return String(v); },
                decode: function (v) { return String(v); }
            });
        }
        langSelect.addEventListener('change', function () {
            const nextLang = langSelect.value;
            if (!nextLang) {
                return;
            }
            if (root.j0 && root.j0.vfs && typeof root.j0.vfs.set === 'function') {
                root.j0.vfs.set('lang', nextLang, { profile: 'ui-sync' });
            }
            const nextUrl = new URL(root.location.href);
            nextUrl.searchParams.delete('tab');
            root.location.assign(nextUrl.toString());
        });
    }

    function detectIntent(rawText) {
        const text = String(rawText || '').trim();
        if (text === '') {
            return { kind: 'empty', value: '' };
        }
        if (COMMAND_HEADER_RE.test(text)) {
            return { kind: 'commands', value: text };
        }
        if (
            text.includes('/') ||
            text.includes('\\') ||
            text === '~' ||
            text === '.' ||
            text === '..'
        ) {
            return { kind: 'path', value: text };
        }
        return { kind: 'search', value: text };
    }

    function countCommandBlocks(rawCommands) {
        const matches = String(rawCommands || '').match(/(?:^|\n)\s*===\s*(?:PATCH|CREATE|MOVE|DISABLE|DISCARD|DUMP|TREE):/gi);
        return matches ? matches.length : 1;
    }

    function submitCommandsToPatcher(patcherUrl, rawCommands) {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = patcherUrl;
        form.setAttribute('data-j0-hidden', 'true');

        const cmdField = document.createElement('textarea');
        cmdField.name = 'commands';
        cmdField.value = rawCommands;
        form.appendChild(cmdField);

        const actField = document.createElement('input');
        actField.type = 'hidden';
        actField.name = 'action';
        actField.value = 'apply';
        form.appendChild(actField);

        document.body.appendChild(form);
        form.submit();
    }

    function initOmnibar() {
        const formEl = document.querySelector('[data-j0-omnibar-form]');
        const inputEl = formEl ? formEl.querySelector('[data-j0-omnibar-input]') : null;
        if (!formEl || !inputEl) {
            return;
        }

        const badgeEl = formEl.querySelector('[data-j0-omnibar-badge]');
        const clearBtn = formEl.querySelector('[data-j0-omnibar-clear]');
        const filesUrl = formEl.getAttribute('data-j0-files-url') || '';
        const patcherUrl = formEl.getAttribute('data-j0-patcher-url') || '';
        const activeTool = formEl.getAttribute('data-j0-active-tool') || '';

        let stagedCommands = null;

        function syncUiState() {
            const val = inputEl.value;
            if (clearBtn) {
                clearBtn.setAttribute('data-j0-hidden', (val === '' && stagedCommands === null) ? 'true' : 'false');
            }
            if (stagedCommands !== null) {
                const count = countCommandBlocks(stagedCommands);
                formEl.setAttribute('data-j0-mode', 'commands');
                if (badgeEl) {
                    badgeEl.textContent = 'ai0 (' + count + ') ↵';
                    badgeEl.setAttribute('data-j0-hidden', 'false');
                }
                return;
            }
            const intent = detectIntent(val);
            if (intent.kind === 'commands') {
                formEl.setAttribute('data-j0-mode', 'commands');
                if (badgeEl) {
                    badgeEl.textContent = 'ai0 ↵';
                    badgeEl.setAttribute('data-j0-hidden', 'false');
                }
            } else {
                formEl.removeAttribute('data-j0-mode');
                if (badgeEl) {
                    badgeEl.textContent = '';
                    badgeEl.setAttribute('data-j0-hidden', 'true');
                }
            }
        }

        function clearOmnibar(notifyTree) {
            stagedCommands = null;
            inputEl.value = '';
            syncUiState();
            if (notifyTree && activeTool === 'files' && root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
                root.j0.bus.emit('ai0:files:search', { query: '' });
            }
        }

        inputEl.addEventListener('paste', function (event) {
            const clip = event.clipboardData ? event.clipboardData.getData('text/plain') : '';
            if (!clip || !COMMAND_HEADER_RE.test(clip)) {
                stagedCommands = null;
                return;
            }
            event.preventDefault();
            stagedCommands = clip;
            const firstHeader = clip.split(/\r?\n/).find(function (line) {
                return line.trim().startsWith('===');
            }) || '=== AI0 ===';
            inputEl.value = firstHeader.trim();
            syncUiState();
        });

        inputEl.addEventListener('input', function () {
            if (stagedCommands !== null) {
                stagedCommands = null;
            }
            syncUiState();
        });

        inputEl.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                clearOmnibar(true);
            }
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                clearOmnibar(true);
                inputEl.focus();
            });
        }

        formEl.addEventListener('submit', function (event) {
            event.preventDefault();
            const rawPayload = stagedCommands !== null ? stagedCommands : inputEl.value;
            const intent = detectIntent(rawPayload);

            if (intent.kind === 'empty') {
                if (activeTool === 'files' && root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
                    root.j0.bus.emit('ai0:files:search', { query: '' });
                }
                return;
            }

            if (intent.kind === 'commands' && patcherUrl !== '') {
                submitCommandsToPatcher(patcherUrl, intent.value);
                return;
            }

            if (intent.kind === 'path' && filesUrl !== '') {
                const targetUrl = new URL(filesUrl, root.location.href);
                targetUrl.searchParams.set('nav', intent.value);
                const curBrowse = document.querySelector('input[name="browse_root"]')?.value || '';
                if (curBrowse !== '') {
                    targetUrl.searchParams.set('from', curBrowse);
                }
                root.location.href = targetUrl.toString();
                return;
            }

            const hasLiveTree = activeTool === 'files' && document.querySelector('[data-j0-tree-root], .j0ui-shallow-list') !== null;
            if (hasLiveTree && root.j0 && root.j0.bus && typeof root.j0.bus.emit === 'function') {
                const url = new URL(root.location.href);
                url.searchParams.set('q', intent.value);
                root.history.replaceState(null, '', url.toString());
                root.j0.bus.emit('ai0:files:search', { query: intent.value });
                return;
            }

            if (filesUrl !== '') {
                const searchUrl = new URL(filesUrl, root.location.href);
                searchUrl.searchParams.set('q', intent.value);
                root.location.href = searchUrl.toString();
            }
        });

        if (root.j0 && root.j0.bus && typeof root.j0.bus.on === 'function') {
            root.j0.bus.on('ai0:omnibar:clear', function () {
                stagedCommands = null;
                inputEl.value = '';
                syncUiState();
            });
        }

        syncUiState();
    }

    initLangSwitcher();
    initOmnibar();
})();