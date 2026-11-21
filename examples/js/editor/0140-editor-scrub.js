// PATH: examples/js/editor/0140-editor-scrub.js
(function (global) {
	'use strict';

	const j0 = global.j0;
	const core = j0 && j0.get('editor:core');
	if (!core) return;

	const { qs } = core;

	document.addEventListener('pointerdown', (e) => {
		const scrub = e.target.closest('[data-ed-scrub]');
		if (!scrub || e.button !== 0) return;

		const key = scrub.dataset.edScrub;
		const input = qs('[data-ed-num="' + key + '"]') || qs('[data-ed-control="' + key + '"]');
		if (!input || input.disabled) return;

		e.preventDefault();
		const startX = e.clientX;
		const startVal = parseFloat(input.value) || 0;
		const step = parseFloat(input.step) || 1;
		const min = input.min !== '' ? parseFloat(input.min) : -Infinity;
		const max = input.max !== '' ? parseFloat(input.max) : Infinity;
		const decimals = (String(step).split('.')[1] || '').length;

		const onMove = (me) => {
			const delta = Math.round((me.clientX - startX) / 3) * step;
			const next = Math.min(max, Math.max(min, +(startVal + delta).toFixed(decimals)));
			if (parseFloat(input.value) === next) return;
			input.value = String(next);
			input.dispatchEvent(new Event('input', { bubbles: true }));
			input.dispatchEvent(new Event('change', { bubbles: true }));
		};

		const onUp = () => {
			global.removeEventListener('pointermove', onMove);
			global.removeEventListener('pointerup', onUp);
		};

		global.addEventListener('pointermove', onMove);
		global.addEventListener('pointerup', onUp);
	});
})(typeof globalThis !== 'undefined' ? globalThis : window);