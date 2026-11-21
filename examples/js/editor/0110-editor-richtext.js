// PATH: examples/js/editor/0110-editor-richtext.js
(function (global) {
	'use strict';

	const j0 = global.j0;
	const core = j0 && j0.get('editor:core');
	if (!core) return;

	const { state, qs, qsa, rgbToHex, markDirty, clearInlineTextOverrides, refreshBackdrop } = core;

	const sizeInput = qs('[data-ed-control="char-size"]');
	const leadingInput = qs('[data-ed-control="char-leading"]');
	const colorInput = qs('[data-ed-control="char-color"]');
	const formatBtns = qsa('[data-ed-format]');
	const alignHSel = qs('[data-ed-control="char-align-h"]');
	const alignVSel = qs('[data-ed-control="char-align-v"]');

	let savedRange = null;

	function getSelectionRangeIn(textNode) {
		const sel = global.getSelection();
		if (sel && sel.rangeCount > 0) {
			const r = sel.getRangeAt(0);
			if (!r.collapsed && textNode.contains(r.commonAncestorContainer)) {
				savedRange = r.cloneRange();
				return r;
			}
		}
		if (savedRange && !savedRange.collapsed && textNode.contains(savedRange.commonAncestorContainer)) {
			return savedRange;
		}
		return null;
	}

	function toggleInlineCommand(cmd) {
		if (!state.activeBox) return;
		const textNode = qs('.ed-box-text', state.activeBox);
		const existingRange = getSelectionRangeIn(textNode);

		textNode.focus();
		const sel = global.getSelection();
		sel.removeAllRanges();

		if (existingRange) {
			sel.addRange(existingRange);
			document.execCommand(cmd, false, null);
			if (sel.rangeCount > 0) savedRange = sel.getRangeAt(0).cloneRange();
		} else {
			if (textNode.textContent.trim() === '') return;
			const fullRange = document.createRange();
			fullRange.selectNodeContents(textNode);
			sel.addRange(fullRange);
			document.execCommand(cmd, false, null);
			sel.removeAllRanges();
			savedRange = null;
		}

		syncFormatButtons();
		refreshBackdrop();
		markDirty();
	}

	function syncFormatButtons() {
		if (!state.activeBox) return;
		const textNode = qs('.ed-box-text', state.activeBox);
		const sel = global.getSelection();
		const activeInBox = sel && sel.rangeCount > 0 && textNode.contains(sel.anchorNode);

		const tagForCmd = (cmd) => {
			if (cmd === 'bold') return 'b, strong';
			if (cmd === 'italic') return 'i, em';
			if (cmd === 'underline') return 'u';
			if (cmd === 'strikeThrough') return 's, strike';
			return '';
		};

		formatBtns.forEach((btn) => {
			const cmd = btn.dataset.edFormat;
			const isOn = activeInBox ? document.queryCommandState(cmd) : Boolean(textNode.querySelector(tagForCmd(cmd)));
			btn.classList.toggle('is-active', isOn);
		});
	}

	function syncCharacterPanel(box) {
		savedRange = null;
		if (!box) return;
		const glass = qs('.ed-glass', box);
		const cs = getComputedStyle(glass);
		sizeInput.value = String(Math.round(parseFloat(cs.fontSize)) || 32);
		leadingInput.value = glass.style.lineHeight || '1.25';
		colorInput.value = rgbToHex(cs.color);

		alignHSel.value = glass.style.textAlign || 'left';
		alignVSel.value = glass.style.justifyContent || 'center';
		syncFormatButtons();
	}

	function setBoxStyle(prop, value) {
		if (!state.activeBox) return;
		qs('.ed-glass', state.activeBox).style[prop] = value;
		if (state.activeBox._state.styles) state.activeBox._state.styles[prop] = value;
	}

	sizeInput.addEventListener('input', () => {
		if (!state.activeBox) return;
		const val = Math.max(8, Number(sizeInput.value) || 16);
		setBoxStyle('fontSize', val + 'px');
		clearInlineTextOverrides(qs('.ed-box-text', state.activeBox), 'fontSize');
		refreshBackdrop();
		markDirty();
	});

	leadingInput.addEventListener('input', () => {
		if (!state.activeBox) return;
		const val = Math.max(0.5, Number(leadingInput.value) || 1.2);
		setBoxStyle('lineHeight', String(val));
		clearInlineTextOverrides(qs('.ed-box-text', state.activeBox), 'lineHeight');
		refreshBackdrop();
		markDirty();
	});

	colorInput.addEventListener('input', () => {
		if (!state.activeBox) return;
		setBoxStyle('color', colorInput.value);
		clearInlineTextOverrides(qs('.ed-box-text', state.activeBox), 'color');
		refreshBackdrop();
		markDirty();
	});

	formatBtns.forEach((btn) => {
		btn.addEventListener('mousedown', (e) => e.preventDefault());
		btn.addEventListener('click', () => toggleInlineCommand(btn.dataset.edFormat));
	});

	alignHSel.addEventListener('change', () => {
		setBoxStyle('textAlign', alignHSel.value);
		refreshBackdrop();
		markDirty();
	});

	alignVSel.addEventListener('change', () => {
		setBoxStyle('justifyContent', alignVSel.value);
		refreshBackdrop();
		markDirty();
	});

	document.addEventListener('selectionchange', () => {
		if (!state.activeBox) return;
		const textNode = qs('.ed-box-text', state.activeBox);
		const sel = global.getSelection();
		if (sel && sel.rangeCount > 0 && textNode.contains(sel.anchorNode)) {
			const r = sel.getRangeAt(0);
			savedRange = r.collapsed ? null : r.cloneRange();
			syncFormatButtons();
		}
	});

	j0.bus.on('editor:selection:changed', syncCharacterPanel);
})(typeof globalThis !== 'undefined' ? globalThis : window);