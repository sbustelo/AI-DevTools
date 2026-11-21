// PATH: examples/js/editor/0090-editor-boxes.js
(function (global) {
	'use strict';

	const j0 = global.j0;
	const core = j0 && j0.get('editor:core');
	if (!core) return;

	const { state, qs, markDirty, refreshBackdrop } = core;
	const stage = qs('[data-ed-role="stage"]');
	const boxTpl = document.querySelector('[data-ed-tpl="box"]');

	function cleanEmptyTextNode(textNode) {
		if (!textNode) return;
		if (textNode.textContent.trim() === '' && !textNode.querySelector('img')) textNode.textContent = '';
	}

	function removeBoxDom(box) {
		if (!box) return;
		const bgApi = j0.get('editor:bg');
		if (box._state && box._state.bg && bgApi && bgApi.releaseBgImage) {
			bgApi.releaseBgImage(box._state.bg);
		}
		const prev = box.previousElementSibling;
		if (prev && prev.tagName && prev.tagName.toLowerCase() === 'ipax-backdrop-layer') prev.remove();
		box.remove();
	}

	function setBoxGeom(box, patch) {
		if (!box) return;
		if (!box._state.geom) box._state.geom = {};
		Object.entries(patch).forEach(([prop, val]) => {
			box.style[prop] = val;
			box._state.geom[prop] = val;
		});
	}

	function applyIpaxProps(box) {
		const glass = qs('.ed-glass', box);
		const ip = box._state.ipax;
		glass.style.setProperty('--ipax-mode', ip.mode);

		if (ip.boxEnabled !== false) {
			glass.style.setProperty('--ipax-backdrop-contrast', String(ip.contrast));
			glass.style.setProperty('--ipax-backdrop-blur', ip.blur + 'px');
			glass.style.setProperty('--ipax-backdrop-feather', ip.feather.t + 'px ' + ip.feather.r + 'px ' + ip.feather.b + 'px ' + ip.feather.l + 'px');
		} else {
			glass.style.setProperty('--ipax-backdrop-contrast', '1');
			glass.style.setProperty('--ipax-backdrop-blur', '0px');
			glass.style.setProperty('--ipax-backdrop-feather', '0px');
		}

		const t = ip.text || { contrast: 7, halo: 12, spread: 35, blur: 0, x: 0, y: 0 };
		if (ip.textEnabled && (t.contrast > 1 || t.blur > 0)) {
			glass.style.setProperty('--ipax-text', t.x + 'px ' + t.y + 'px ' + t.halo + 'px ' + t.spread + '% ' + t.contrast + ':1 ' + t.blur + 'px');
		} else {
			glass.style.removeProperty('--ipax-text');
		}
		refreshBackdrop();
	}

	function selectBox(box) {
		if (state.activeBox && state.activeBox !== box) {
			state.activeBox.classList.remove('is-selected');
			cleanEmptyTextNode(qs('.ed-box-text', state.activeBox));
		}
		state.activeBox = box;
		if (box) {
			box.classList.add('is-selected');
			setBoxGeom(box, { zIndex: String(++state.zCounter) });
		}
		j0.bus.emit('editor:selection:changed', box);
	}

	function insertPlainText(textNode, plainText) {
		const sel = global.getSelection();
		if (sel && sel.rangeCount > 0 && textNode.contains(sel.anchorNode)) {
			const range = sel.getRangeAt(0);
			range.deleteContents();
			const node = document.createTextNode(plainText);
			range.insertNode(node);
			range.setStartAfter(node);
			range.collapse(true);
			sel.removeAllRanges();
			sel.addRange(range);
		} else {
			textNode.appendChild(document.createTextNode(plainText));
		}
	}

	function buildBoxElement(opts) {
		const box = boxTpl.content.firstElementChild.cloneNode(true);
		const fVal = opts.feather ?? 14;
		box._state = opts.clonedState || {
			fontInput: opts.font || 'system-ui, -apple-system, sans-serif',
			fontStatus: 'System font stack',
			fontError: false,
			padding: { t: 24, r: 24, b: 24, l: 24 },
			bg: { type: 'none', image: '', imgFit: 'cover', imgOpacity: 100, color: '#000000', colorAlpha: 40, gradC1: '#000000', gradA1: 60, gradC2: '#1a4fd6', gradA2: 30, gradAngle: 135 },
			ipax: {
				mode: 'default',
				boxEnabled: true,
				contrast: opts.contrast ?? 4.5,
				blur: opts.blur ?? 8,
				feather: { t: fVal, r: fVal, b: fVal, l: fVal },
				textEnabled: false,
				text: { contrast: 7, halo: 12, spread: 35, blur: 0, x: 0, y: 0 }
			}
		};

		setBoxGeom(box, {
			left: (opts.left ?? 80) + 'px',
			top: (opts.top ?? 80) + 'px',
			width: (opts.width ?? 360) + 'px',
			height: (opts.height ?? 140) + 'px',
			zIndex: box._state.geom ? (box._state.geom.zIndex || String(state.zCounter)) : String(state.zCounter)
		});

		const p = box._state.padding;
		const st = Object.assign({
			fontFamily: opts.fontFamily || box._state.fontInput,
			fontSize: (opts.fontSize ?? 32) + 'px',
			lineHeight: opts.lineHeight || '1.25',
			color: opts.color || '#ffffff',
			textAlign: opts.textAlign || 'left',
			justifyContent: opts.justifyContent || 'center',
			padding: p.t + 'px ' + p.r + 'px ' + p.b + 'px ' + p.l + 'px'
		}, box._state.styles || {});
		box._state.styles = st;

		const glass = qs('.ed-glass', box);
		Object.assign(glass.style, st);

		const textNode = qs('.ed-box-text', box);
		if (opts.html !== undefined) textNode.innerHTML = opts.html;
		else textNode.textContent = opts.text || '';

		textNode.addEventListener('paste', (e) => {
			e.preventDefault();
			const raw = (e.clipboardData || global.clipboardData).getData('text/plain');
			insertPlainText(textNode, raw);
			markDirty();
		});
		textNode.addEventListener('input', () => { cleanEmptyTextNode(textNode); refreshBackdrop(); markDirty(); });
		textNode.addEventListener('blur', () => cleanEmptyTextNode(textNode));

		stage.appendChild(box);
		const bgApi = j0.get('editor:bg');
		if (bgApi) bgApi.applyBgToLayer(box._state.bg, qs('.ed-box-bg', box), true);
		applyIpaxProps(box);
		return box;
	}

	function createBox(opts = {}) {
		const box = buildBoxElement(opts);
		selectBox(box);
		markDirty();
		if (opts.focusText) qs('.ed-box-text', box).focus();
		return box;
	}

	function cloneBox(sourceBox, overrideLeft, overrideTop, shouldSelect = true) {
		if (!sourceBox) return null;
		const clonedState = JSON.parse(JSON.stringify(sourceBox._state));
		if (sourceBox._state.bg && sourceBox._state.bg._blob) {
			clonedState.bg._blob = sourceBox._state.bg._blob;
			clonedState.bg._fileName = sourceBox._state.bg._fileName || '';
		}
		const clone = buildBoxElement({
			left: overrideLeft ?? sourceBox.offsetLeft + 24,
			top: overrideTop ?? sourceBox.offsetTop + 24,
			width: sourceBox.offsetWidth,
			height: sourceBox.offsetHeight,
			clonedState,
			html: qs('.ed-box-text', sourceBox).innerHTML
		});
		setBoxGeom(clone, { zIndex: String(++state.zCounter) });
		if (shouldSelect) selectBox(clone);
		markDirty();
		return clone;
	}

	function deleteActiveBox(box) {
		const target = (box && box.nodeType === 1) ? box : state.activeBox;
		if (!target) return;
		removeBoxDom(target);
		if (state.activeBox === target) selectBox(null);
		markDirty();
		refreshBackdrop();
	}

	j0.bus.on('editor:box:request-select', selectBox);
	j0.register('editor:boxes', Object.freeze({ createBox, cloneBox, selectBox, deleteActiveBox, applyIpaxProps, removeBoxDom, setBoxGeom }));
})(typeof globalThis !== 'undefined' ? globalThis : window);