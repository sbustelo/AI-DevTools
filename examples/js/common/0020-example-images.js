// PATH: examples/js/common/0020-example-images.js
(function (global) {
	'use strict';

	const j0 = global.j0;
	if (!j0 || !j0.register) return;

	const PALETTES = (j0.get && j0.get('example:palettes')) || [
		['#8E51FB', '#5A46B5', '#006277', '#00364C']
	];

	function hashString(str) {
		let h = 2166136261;
		for (let i = 0; i < str.length; i++) {
			h ^= str.charCodeAt(i);
			h = Math.imul(h, 16777619);
		}
		return h >>> 0;
	}

	function shuffleArray(arr) {
		for (let j = arr.length - 1; j > 0; j--) {
			const k = Math.floor(Math.random() * (j + 1));
			const tmp = arr[j];
			arr[j] = arr[k];
			arr[k] = tmp;
		}
		return arr;
	}

	function paletteCount() {
		return PALETTES.length;
	}

	function getPalette(index) {
		const n = PALETTES.length;
		const i = ((Math.floor(Number(index) || 0) % n) + n) % n;
		return PALETTES[i].slice();
	}

	function resolveColors(palette, options) {
		let cols;
		let defaultOrder = 'sequential';

		if (Array.isArray(palette)) {
			cols = palette.length ? palette.slice() : PALETTES[0].slice();
		} else if (typeof palette === 'number' && isFinite(palette)) {
			if (Number.isInteger(palette)) {
				cols = getPalette(palette);
			} else {
				const t = palette <= 0 ? 0 : (palette >= 1 ? 0.999999999 : palette);
				cols = getPalette(Math.floor(t * PALETTES.length));
				defaultOrder = 'random';
			}
		} else if (typeof palette === 'string' && palette !== '' && palette !== 'rand') {
			cols = getPalette(hashString(palette));
		} else {
			cols = getPalette(Math.floor(Math.random() * PALETTES.length));
			defaultOrder = 'random';
		}

		const order = options.colorOrder || defaultOrder;
		if (order === 'random') shuffleArray(cols);

		const limit = options.colors;
		if (Array.isArray(limit)) {
			const minC = Math.max(1, Math.floor(Number(limit[0]) || 1));
			const maxC = Math.max(minC, Math.floor(Number(limit[1]) || minC));
			const count = Math.min(cols.length, minC + Math.floor(Math.random() * (maxC - minC + 1)));
			cols = cols.slice(0, count);
		} else if (typeof limit === 'number' && isFinite(limit) && limit > 0) {
			cols = cols.slice(0, Math.min(cols.length, Math.floor(limit)));
		}

		return cols;
	}

	function resolvePatternSeed(patternSeed) {
		if (typeof patternSeed === 'number' && isFinite(patternSeed)) {
			if (Number.isInteger(patternSeed)) {
				const norm = Math.abs(Math.trunc(patternSeed));
				return norm === 0 ? 1 : ((norm - 1) % 2147483646) + 1;
			}
			const t = patternSeed <= 0 ? 0 : (patternSeed >= 1 ? 0.999999999 : patternSeed);
			return Math.floor(t * 2147483646) + 1;
		}
		if (typeof patternSeed === 'string' && patternSeed !== '' && patternSeed !== 'rand') {
			return (hashString(patternSeed) % 2147483646) + 1;
		}
		return Math.floor(Math.random() * 2147483646) + 1;
	}

	function hexToRgb(hex) {
		const n = parseInt(hex.slice(1), 16);
		return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
	}

	function renderCanvas(seed, w, h, cols) {
		const c = document.createElement('canvas');
		c.width = w;
		c.height = h;
		const g = c.getContext('2d');
		const gr = g.createLinearGradient(0, 0, w, h);
		cols.forEach((k, i) => gr.addColorStop(i / Math.max(1, cols.length - 1), k));
		g.fillStyle = gr;
		g.fillRect(0, 0, w, h);
		const rgbs = cols.map(hexToRgb);
		let sd = resolvePatternSeed(seed);
		const rnd = () => (sd = (sd * 16807) % 2147483647) / 2147483647;
		for (let i = 0; i < 90; i++) {
			const rgb = rgbs[Math.floor(rnd() * rgbs.length)];
			const alpha = (0.35 + rnd() * 0.45).toFixed(2);
			g.fillStyle = 'rgba(' + rgb[0] + ',' + rgb[1] + ',' + rgb[2] + ',' + alpha + ')';
			g.beginPath();
			g.arc(rnd() * w, rnd() * h, 6 + rnd() * Math.min(w, h) / 6, 0, 6.283);
			g.fill();
		}
		return c;
	}

	function paintImage(seed, w, h, cols, quality) {
		const c = renderCanvas(seed, w, h, cols);
		const q = (typeof quality === 'number' && quality > 0 && quality <= 1) ? quality : 0.82;
		return c.toDataURL('image/jpeg', q);
	}

	function generate(width, height, palette, options) {
		const opts = options || {};
		const w = Math.max(1, Math.round(Number(width) || 640));
		const h = Math.max(1, Math.round(Number(height) || 480));
		const cols = resolveColors(palette === undefined ? 'rand' : palette, opts);
		const seed = resolvePatternSeed(opts.patternSeed);
		return paintImage(seed, w, h, cols, opts.quality);
	}

	function generateBlob(width, height, palette, options) {
		const opts = options || {};
		const w = Math.max(1, Math.round(Number(width) || 640));
		const h = Math.max(1, Math.round(Number(height) || 480));
		const cols = resolveColors(palette === undefined ? 'rand' : palette, opts);
		const seed = resolvePatternSeed(opts.patternSeed);
		const c = renderCanvas(seed, w, h, cols);
		const q = (typeof opts.quality === 'number' && opts.quality > 0 && opts.quality <= 1) ? opts.quality : 0.82;
		return new Promise((resolve) => c.toBlob((blob) => resolve(blob), 'image/jpeg', q));
	}

	j0.register('example:images', Object.freeze({
		paletteCount,
		getPalette,
		generate,
		generateBlob,
		paintImage,
		PALETTES
	}));
})(typeof globalThis !== 'undefined' ? globalThis : window);