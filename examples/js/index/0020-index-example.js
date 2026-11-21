// PATH: examples/js/index/0020-index-example.js
(function (global) {
	'use strict';

	const j0 = global.j0;
	const ipax = j0 && j0.get('ipax-backdrop');
	const root = document.querySelector('[data-ex-ref="root"]');
	if (!root) return;

	const ref = (name) => root.querySelector('[data-ex-ref="' + name + '"]');
	const stage = ref('stage');
	const report = ref('report');
	const status = ref('status');
	const hero = ref('hero');

	if (!ipax) {
		if (status) status.textContent = 'ipax-backdrop did not load. Check the script paths in index.html.';
		return;
	}

	let z = 20;
	let drag = null;
	const lines = {};
	const notes = [];
	const activeBlobs = new Map();

	function setBg(el, url, isBlob) {
		if (!el) return;
		const prev = activeBlobs.get(el);
		if (prev && prev !== url) URL.revokeObjectURL(prev);
		if (isBlob) activeBlobs.set(el, url);
		else activeBlobs.delete(el);
		el.style.setProperty('--ex-bg-img', 'url("' + url + '")');
		ipax.refresh();
	}

	function setBoxVar(box, prop, val) {
		box.style.setProperty(prop, val);
	}

	stage.querySelectorAll('[data-ex-box]').forEach((b) => {
		setBoxVar(b, '--ex-x', b.getAttribute('data-ex-x') + 'px');
		setBoxVar(b, '--ex-y', b.getAttribute('data-ex-y') + 'px');
		setBoxVar(b, '--ex-w', b.getAttribute('data-ex-w') + 'px');
		setBoxVar(b, '--ex-h', b.getAttribute('data-ex-h') + 'px');
		setBoxVar(b, '--ex-z', b.getAttribute('data-ex-z'));
	});

	stage.addEventListener('pointerdown', (e) => {
		const b = e.target.closest('[data-ex-box]');
		if (!b || !stage.contains(b)) return;
		if (!b.hasAttribute('data-ex-noraise')) setBoxVar(b, '--ex-z', String(++z));
		drag = {
			b,
			grip: !!e.target.closest('[data-ex-grip]'),
			x: e.clientX,
			y: e.clientY,
			l: b.offsetLeft,
			t: b.offsetTop,
			ww: b.offsetWidth,
			hh: b.offsetHeight
		};
		b.setPointerCapture(e.pointerId);
		ipax.refresh();
	});

	stage.addEventListener('pointermove', (e) => {
		if (!drag) return;
		const dx = e.clientX - drag.x;
		const dy = e.clientY - drag.y;
		const b = drag.b;
		if (drag.grip) {
			setBoxVar(b, '--ex-w', Math.max(70, drag.ww + dx) + 'px');
			setBoxVar(b, '--ex-h', Math.max(50, drag.hh + dy) + 'px');
		} else {
			const nx = Math.min(stage.clientWidth - 40, Math.max(40 - b.offsetWidth, drag.l + dx));
			const ny = Math.min(stage.clientHeight - 30, Math.max(30 - b.offsetHeight, drag.t + dy));
			setBoxVar(b, '--ex-x', nx + 'px');
			setBoxVar(b, '--ex-y', ny + 'px');
		}
		ipax.refresh();
	});

	const endDrag = () => { drag = null; };
	stage.addEventListener('pointerup', endDrag);
	stage.addEventListener('pointercancel', endDrag);

	root.addEventListener('change', (e) => {
		const sel = e.target.closest('[data-ex-ctl="imgTarget"]');
		if (sel) {
			if (sel.value) {
				const fInput = ref('file');
				if (fInput) {
					fInput.setAttribute('data-ex-target', sel.value);
					fInput.click();
				}
				sel.value = '';
			}
			return;
		}

		const f = e.target.closest('[data-ex-ref="file"]');
		if (!f || !f.files || !f.files[0]) return;
		const file = f.files[0];
		if (!file.type || !file.type.startsWith('image/')) return;

		const key = f.getAttribute('data-ex-target');
		const dst = key === 'hero' ? hero : (key === 'stage' ? stage : stage.querySelector('[data-ex-img="' + key + '"]'));
		const blobUrl = URL.createObjectURL(file);
		setBg(dst, blobUrl, true);
		f.value = '';
		if (key === 'hero' && hero) hero.scrollIntoView({ behavior: 'smooth', block: 'start' });
	});

	j0.bus.on('ex:refresh', () => ipax.refresh());

	j0.bus.on('ipax-backdrop:updated', (d) => {
		const host = d.element.closest('[data-ex-label]');
		if (!host || !report) return;
		const r = d.resolver;
		const s = d.stats;
		const b = d.budget;
		const lbl = host.getAttribute('data-ex-label');
		const unkPct = r.cells ? Math.round(100 * r.unknown / r.cells) : 0;
		const minStr = s
			? ', min ' + (s.min === null ? 'n/a' : s.min.toFixed(2) + ':1') + ' (' + s.under + ' px under target)' + (s.inf ? ', ratio unreachable' : '')
			: ', contrast stats: CPU backend only';
		lines[lbl] = lbl + ': ' + d.backend + ', ' + d.params.mode + ' ' + d.params.ratio + ':1'
			+ (b ? ' [Scale: ' + b.s.toFixed(2) + ']' : '') + ', unknown ' + unkPct + '%'
			+ minStr + (d.tainted ? ', cross-origin image' : '') + ', ' + d.ms.toFixed(0) + ' ms';
		report.textContent = Object.keys(lines).map((k) => lines[k]).join('\n');
	});

	const note = (text) => {
		notes.push(text);
		if (notes.length > 4) notes.shift();
		if (status) status.textContent = notes.join('\n');
	};
	j0.bus.on('ipax-backdrop:backend', (d) => note('Backend: ' + d.backend + ' (' + d.reason + ')'));
	j0.bus.on('ipax-backdrop:warning', (d) => note('Warning: ' + d.message));
	j0.bus.on('ipax-backdrop:error', (d) => note('Error: ' + d.message));

	global.addEventListener('pagehide', () => {
		activeBlobs.forEach((url) => URL.revokeObjectURL(url));
		activeBlobs.clear();
	});

	const images = j0.get('example:images');
	if (images) {
		const total = images.paletteCount();
		const idx1 = Math.floor(Math.random() * total);
		const idx2 = (idx1 + 1 + Math.floor(Math.random() * (total - 1))) % total;
		setBg(stage.querySelector('[data-ex-img="a"]'), images.generate(420, 380, idx1, { patternSeed: 'rand' }), false);
		setBg(stage, images.generate(700, 600, idx2, { patternSeed: 'rand' }), false);
	}
})(typeof globalThis !== 'undefined' ? globalThis : window);