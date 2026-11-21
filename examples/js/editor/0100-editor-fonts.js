// PATH: examples/js/editor/0100-editor-fonts.js
(function (global) {
	'use strict';

	const j0 = global.j0;
	const core = j0 && j0.get('editor:core');
	if (!core) return;

	const { state, qs, markDirty, clearInlineTextOverrides, refreshBackdrop } = core;

	const PRESETS = Object.freeze([
		{ label: 'System UI Stack', value: 'system-ui, -apple-system, sans-serif', tag: 'Stack' },
		{ label: 'Neo-Grotesque Stack', value: "'Helvetica Neue', Arial, sans-serif", tag: 'Stack' },
		{ label: 'Classic Serif Stack', value: "Georgia, 'Times New Roman', serif", tag: 'Stack' },
		{ label: 'Monospace Stack', value: "ui-monospace, 'SFMono-Regular', monospace", tag: 'Stack' },
		{ label: 'Inter', value: 'Inter', tag: 'Google' },
		{ label: 'Roboto', value: 'Roboto', tag: 'Google' },
		{ label: 'Montserrat', value: 'Montserrat', tag: 'Google' },
		{ label: 'Playfair Display', value: 'Playfair Display', tag: 'Google' },
		{ label: 'Lora', value: 'Lora', tag: 'Google' },
		{ label: 'JetBrains Mono', value: 'JetBrains Mono', tag: 'Google' }
	]);

	const combo = qs('[data-ed-role="font-combo"]');
	const input = qs('[data-ed-control="char-font"]');
	const toggleBtn = qs('[data-ed-action="toggle-font-menu"]');
	const menu = qs('[data-ed-role="font-menu"]');
	const itemTpl = document.querySelector('[data-ed-tpl="font-item"]');

	const metricCtx = document.createElement('canvas').getContext('2d');
	const fontPromises = new Map();

	function isSystemFontInstalled(name) {
		const sample = 'mmmmmmmmmmlli10O@';
		metricCtx.font = '72px monospace';
		const monoW = metricCtx.measureText(sample).width;
		metricCtx.font = '72px serif';
		const serifW = metricCtx.measureText(sample).width;

		metricCtx.font = '72px "' + name + '", monospace';
		const testMono = metricCtx.measureText(sample).width;
		metricCtx.font = '72px "' + name + '", serif';
		const testSerif = metricCtx.measureText(sample).width;

		return testMono !== monoW || testSerif !== serifW;
	}

	function setStatus(box, msg, isError, source) {
		box._state.fontStatus = msg;
		box._state.fontError = Boolean(isError);
		box._state.fontSource = source || (isError ? 'error' : 'system');
		j0.bus.emit('editor:font:status', { box, msg, isError: Boolean(isError), source: box._state.fontSource });
	}

	function applyFamilyToBox(box, familyCss, silent) {
		const glass = qs('.ed-glass', box);
		glass.style.fontFamily = familyCss;
		if (box._state.styles) box._state.styles.fontFamily = familyCss;
		clearInlineTextOverrides(qs('.ed-box-text', box), 'fontFamily');
		refreshBackdrop();
		if (!silent) markDirty();
	}

	function ensureGoogleFont(clean) {
		const slug = clean.toLowerCase().replace(/[^a-z0-9]+/g, '-');
		if (fontPromises.has(slug)) return fontPromises.get(slug);

		const promise = new Promise((resolve, reject) => {
			const selector = 'link[data-ed-gfont="' + slug + '"]';
			let link = document.head.querySelector(selector);
			if (link && link.dataset.loaded === '1') return resolve();
			if (link && link.dataset.failed === '1') return reject(new Error('Failed'));
			if (!link) {
				link = document.createElement('link');
				link.rel = 'stylesheet';
				link.dataset.edGfont = slug;
				link.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(clean) + ':ital,wght@0,400;0,700;1,400;1,700&display=swap';
				document.head.appendChild(link);
			}
			link.addEventListener('load', () => { link.dataset.loaded = '1'; resolve(); }, { once: true });
			link.addEventListener('error', () => { link.dataset.failed = '1'; fontPromises.delete(slug); reject(new Error('404')); }, { once: true });
		}).then(async () => {
			await document.fonts.load('16px "' + clean + '"');
			if (!document.fonts.check('16px "' + clean + '"')) {
				fontPromises.delete(slug);
				throw new Error('Not available');
			}
		});

		fontPromises.set(slug, promise);
		return promise;
	}

	async function loadFontForBox(box, raw, opts = {}) {
		if (!box) return;
		const silent = Boolean(opts.silent);
		const clean = (raw || '').trim();
		if (!clean) return;

		box._state.fontInput = clean;
		if (state.activeBox === box) input.value = clean;
		if (!silent) markDirty();

		const generics = ['sans-serif', 'serif', 'monospace', 'cursive', 'fantasy', 'system-ui'];
		if (clean.includes(',') || generics.includes(clean.toLowerCase())) {
			applyFamilyToBox(box, clean, silent);
			setStatus(box, 'System font stack', false, 'system');
			return;
		}

		if (isSystemFontInstalled(clean)) {
			applyFamilyToBox(box, '"' + clean + '", sans-serif', silent);
			setStatus(box, 'Applied system font', false, 'system');
			return;
		}

		setStatus(box, 'Loading from Google Fonts...', false, 'google');
		try {
			await ensureGoogleFont(clean);
			applyFamilyToBox(box, '"' + clean + '", sans-serif', silent);
			setStatus(box, 'Loaded from Google Fonts', false, 'google');
		} catch (err) {
			applyFamilyToBox(box, 'sans-serif', silent);
			setStatus(box, 'Could not load requested system or Google font', true, 'error');
		}
	}

	function resolveAndApplyFont(raw) {
		return loadFontForBox(state.activeBox, raw, { silent: false });
	}

	if (menu && itemTpl) {
		const frag = document.createDocumentFragment();
		PRESETS.forEach((item) => {
			const li = itemTpl.content.firstElementChild.cloneNode(true);
			const lbl = li.querySelector('[data-ed-role="font-label"]');
			const tag = li.querySelector('[data-ed-role="font-tag"]');
			if (lbl) lbl.textContent = item.label;
			if (tag) tag.textContent = item.tag;
			li.addEventListener('mousedown', (e) => {
				e.preventDefault();
				menu.hidden = true;
				resolveAndApplyFont(item.value);
			});
			frag.appendChild(li);
		});
		menu.appendChild(frag);
	}

	toggleBtn.addEventListener('click', () => { menu.hidden = !menu.hidden; });
	input.addEventListener('focus', () => { menu.hidden = false; });
	input.addEventListener('change', () => { menu.hidden = true; resolveAndApplyFont(input.value); });
	input.addEventListener('keydown', (e) => {
		if (e.key === 'Enter') { e.preventDefault(); menu.hidden = true; resolveAndApplyFont(input.value); }
		if (e.key === 'Escape') menu.hidden = true;
	});
	document.addEventListener('pointerdown', (e) => {
		if (!combo.contains(e.target)) menu.hidden = true;
	});

	j0.bus.on('editor:selection:changed', (box) => {
		if (!box) return;
		input.value = box._state.fontInput;
		const isError = Boolean(box._state.fontError);
		const msg = box._state.fontStatus || 'System font stack';
		const source = box._state.fontSource || (isError ? 'error' : (/google/i.test(msg) ? 'google' : 'system'));
		j0.bus.emit('editor:font:status', { box, msg, isError, source });
	});

	j0.register('editor:fonts', Object.freeze({ loadFontForBox }));
})(typeof globalThis !== 'undefined' ? globalThis : window);