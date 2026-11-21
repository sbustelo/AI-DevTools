// PATH: examples/js/editor/0095-editor-drag.js
(function (global) {
	'use strict';

	const j0 = global.j0;
	const core = j0 && j0.get('editor:core');
	const boxesApi = j0 && j0.get('editor:boxes');
	if (!core || !boxesApi) return;

	const { state, qs, markDirty, refreshBackdrop } = core;
	const { selectBox, cloneBox, removeBoxDom, setBoxGeom } = boxesApi;

	const workspace = qs('[data-ed-role="workspace"]');
	if (!workspace) return;

	workspace.addEventListener('pointerdown', (e) => {
		const handle = e.target.closest('.ed-handle');
		const box = e.target.closest('.ed-box');

		if (!box) {
			selectBox(null);
			return;
		}

		selectBox(box);

		if (handle) {
			e.preventDefault();
			const dir = handle.dataset.dir;
			const sx = e.clientX;
			const sy = e.clientY;
			const sl = box.offsetLeft;
			const st = box.offsetTop;
			const sw = box.offsetWidth;
			const sh = box.offsetHeight;

			const onMove = (me) => {
				const dx = me.clientX - sx;
				const dy = me.clientY - sy;
				let nl = sl, nt = st, nw = sw, nh = sh;
				if (dir.includes('e')) nw = Math.max(80, sw + dx);
				if (dir.includes('s')) nh = Math.max(36, sh + dy);
				if (dir.includes('w')) { const cdx = Math.min(sw - 80, dx); nl = sl + cdx; nw = sw - cdx; }
				if (dir.includes('n')) { const cdy = Math.min(sh - 36, dy); nt = st + cdy; nh = sh - cdy; }
				setBoxGeom(box, { left: nl + 'px', top: nt + 'px', width: nw + 'px', height: nh + 'px' });
				j0.bus.emit('editor:box:geom', box);
				refreshBackdrop();
				markDirty();
			};
			const onUp = () => {
				global.removeEventListener('pointermove', onMove);
				global.removeEventListener('pointerup', onUp);
			};
			global.addEventListener('pointermove', onMove);
			global.addEventListener('pointerup', onUp);
			return;
		}

		if (e.target.closest('.ed-box-text')) return;
		e.preventDefault();
		const sx = e.clientX;
		const sy = e.clientY;
		const originL = box.offsetLeft;
		const originT = box.offsetTop;
		let altOriginClone = null;
		let hasMoved = false;

		const syncAltClone = (isAltDown) => {
			if (isAltDown && hasMoved && !altOriginClone) {
				altOriginClone = cloneBox(box, originL, originT, false);
				setBoxGeom(box, { zIndex: String(++state.zCounter) });
				refreshBackdrop();
			} else if (!isAltDown && altOriginClone) {
				removeBoxDom(altOriginClone);
				altOriginClone = null;
				refreshBackdrop();
			}
		};

		const onKey = (ke) => {
			if (ke.key === 'Alt') { ke.preventDefault(); syncAltClone(ke.type === 'keydown'); }
		};
		const onDrag = (me) => {
			const dx = me.clientX - sx;
			const dy = me.clientY - sy;
			if (!hasMoved && (Math.abs(dx) > 1 || Math.abs(dy) > 1)) hasMoved = true;
			syncAltClone(me.altKey);
			setBoxGeom(box, { left: (originL + dx) + 'px', top: (originT + dy) + 'px' });
			j0.bus.emit('editor:box:geom', box);
			refreshBackdrop();
			markDirty();
		};
		const onUp = () => {
			global.removeEventListener('pointermove', onDrag);
			global.removeEventListener('pointerup', onUp);
			global.removeEventListener('keydown', onKey);
			global.removeEventListener('keyup', onKey);
			refreshBackdrop();
		};
		global.addEventListener('keydown', onKey);
		global.addEventListener('keyup', onKey);
		global.addEventListener('pointermove', onDrag);
		global.addEventListener('pointerup', onUp);
	});
})(typeof globalThis !== 'undefined' ? globalThis : window);