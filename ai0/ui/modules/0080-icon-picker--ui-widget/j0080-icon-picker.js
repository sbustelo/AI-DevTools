// PATH: ai0/ui/modules/0080-icon-picker--ui-widget/j0080-icon-picker.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    if (!root.j0 || !root.j0.bus) {
        return;
    }

    let modalEl = null;
    let activeCtx = null;
    let selectedValue = null;
    let allItems = [];

    function ensureModal() {
        if (modalEl) {
            return modalEl;
        }
        const wrap = document.createElement('div');
        wrap.className = 'j0ui-modal-backdrop';
        wrap.setAttribute('data-j0-icon-modal', '');
        wrap.setAttribute('data-j0-hidden', 'true');
        wrap.innerHTML = ''
            + '<div class="j0ui-modal" role="dialog" aria-modal="true">'
            + '  <div class="j0ui-modal-head">'
            + '    <h3>Select Icon</h3>'
            + '    <label class="j0ui-btn">'
            + '      <span>Upload SVG/PNG</span>'
            + '      <input type="file" accept=".svg,.png,.webp" data-j0-icon-upload hidden>'
            + '    </label>'
            + '  </div>'
            + '  <div class="j0ui-icon-picker-bar">'
            + '    <input type="search" class="j0ui-input-text" placeholder="Filter options or type an emoji..." data-j0-icon-filter>'
            + '  </div>'
            + '  <div class="j0ui-icon-grid" data-j0-icon-grid></div>'
            + '  <div class="j0ui-modal-foot">'
            + '    <button type="button" class="j0ui-btn j0ui-btn--ghost-danger" data-j0-icon-cancel>Cancel</button>'
            + '    <button type="button" class="j0ui-btn j0ui-btn--primary" data-j0-icon-apply disabled>Use Icon</button>'
            + '  </div>'
            + '</div>';
        document.body.appendChild(wrap);
        modalEl = wrap;
        bindModalEvents(wrap);
        return wrap;
    }

    function renderGrid(query) {
        if (!modalEl) {
            return;
        }
        const grid = modalEl.querySelector('[data-j0-icon-grid]');
        const applyBtn = modalEl.querySelector('[data-j0-icon-apply]');
        const needle = (query || '').trim().toLowerCase();

        while (grid.firstChild) {
            grid.removeChild(grid.firstChild);
        }

        allItems.forEach(function (item) {
            const label = String(item.label || item.value || '');
            if (needle !== '' && !item.pinned && !label.toLowerCase().includes(needle) && !String(item.value).toLowerCase().includes(needle)) {
                return;
            }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'j0ui-icon-tile' + (item.value === selectedValue ? ' j0ui-icon-tile--selected' : '');
            btn.setAttribute('data-j0-icon-tile', item.value);

            const glyph = document.createElement('span');
            glyph.className = 'j0ui-icon-tile-glyph';
            if (item.isNone) {
                const slash = document.createElement('span');
                slash.className = 'j0ui-icon-none-glyph';
                glyph.appendChild(slash);
            } else if (item.url) {
                const img = document.createElement('img');
                img.src = item.url;
                img.alt = '';
                glyph.appendChild(img);
            } else {
                glyph.textContent = item.value;
            }

            const cap = document.createElement('span');
            cap.className = 'j0ui-icon-tile-label';
            cap.textContent = label;

            btn.appendChild(glyph);
            btn.appendChild(cap);
            grid.appendChild(btn);
        });

        const rawTyped = (query || '').trim();
        const isCustomEmoji = rawTyped !== '' && !/^[a-zA-Z0-9._\s-]+$/.test(rawTyped);
        if (isCustomEmoji) {
            selectedValue = rawTyped;
        }
        if (applyBtn) {
            applyBtn.disabled = selectedValue === null;
        }
    }

    function closeModal() {
        if (modalEl) {
            modalEl.setAttribute('data-j0-hidden', 'true');
        }
        activeCtx = null;
        selectedValue = null;
    }

    function bindModalEvents(wrap) {
        const filterInput = wrap.querySelector('[data-j0-icon-filter]');
        const uploadInput = wrap.querySelector('[data-j0-icon-upload]');

        if (filterInput) {
            filterInput.addEventListener('keyup', function () {
                renderGrid(filterInput.value);
            });
            filterInput.addEventListener('input', function () {
                renderGrid(filterInput.value);
            });
        }

        wrap.addEventListener('click', function (event) {
            if (event.target === wrap || event.target.closest('[data-j0-icon-cancel]')) {
                closeModal();
                return;
            }

            const tile = event.target.closest('[data-j0-icon-tile]');
            if (tile) {
                selectedValue = tile.getAttribute('data-j0-icon-tile') || '';
                wrap.querySelectorAll('.j0ui-icon-tile--selected').forEach(function (el) {
                    el.classList.remove('j0ui-icon-tile--selected');
                });
                tile.classList.add('j0ui-icon-tile--selected');
                const applyBtn = wrap.querySelector('[data-j0-icon-apply]');
                if (applyBtn) {
                    applyBtn.disabled = selectedValue === null;
                }
                return;
            }

            if (event.target.closest('[data-j0-icon-apply]') && selectedValue !== null) {
                const chosen = selectedValue;
                const ctxCopy = activeCtx;
                closeModal();
                root.j0.bus.emit('ai0:icon-picker:selected', { value: chosen, context: ctxCopy });
            }
        });

        if (uploadInput) {
            uploadInput.addEventListener('change', async function () {
                const file = uploadInput.files && uploadInput.files[0];
                if (!file || !activeCtx || !activeCtx.apiUrl) {
                    return;
                }
                const reader = new FileReader();
                reader.onload = async function () {
                    const dataUrl = String(reader.result || '');
                    const res = await fetch(activeCtx.apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            op: 'upload_icon',
                            browse_root: activeCtx.browseRoot || '',
                            filename: file.name,
                            data_url: dataUrl
                        })
                    });
                    const json = await res.json();
                    if (json && json.ok && json.item) {
                        allItems.unshift(json.item);
                        selectedValue = json.item.value;
                        renderGrid(filterInput ? filterInput.value : '');
                    }
                    uploadInput.value = '';
                };
                reader.readAsDataURL(file);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modalEl && modalEl.getAttribute('data-j0-hidden') === 'false') {
                closeModal();
            }
        });
    }

    root.j0.bus.on('ai0:icon-picker:open', async function (payload) {
        const wrap = ensureModal();
        activeCtx = payload || {};
        selectedValue = String(activeCtx.current || '');
        const filterInput = wrap.querySelector('[data-j0-icon-filter]');
        if (filterInput) {
            filterInput.value = '';
        }

        allItems = [];
        if (activeCtx.apiUrl) {
            try {
                const res = await fetch(activeCtx.apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ op: 'list_icons', browse_root: activeCtx.browseRoot || '' })
                });
                const json = await res.json();
                if (json && json.ok && Array.isArray(json.items)) {
                    allItems = json.items;
                }
            } catch (err) {
                allItems = [];
            }
        }

        const pinned = [{ value: '', label: 'No icon', isNone: true, pinned: true }];
        const curVal = String(activeCtx.current || '');
        if (curVal !== '' && curVal !== '📁') {
            const match = allItems.find(function (it) { return it.value === curVal; });
            pinned.push({
                value: curVal,
                label: 'Current',
                url: match ? (match.url || '') : '',
                pinned: true
            });
        }
        allItems = pinned.concat(allItems);

        renderGrid('');
        wrap.setAttribute('data-j0-hidden', 'false');
        if (filterInput) {
            filterInput.focus();
        }
    });
})();