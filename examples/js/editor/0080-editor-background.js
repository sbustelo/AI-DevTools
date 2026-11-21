// PATH: examples/js/editor/0080-editor-background.js
(function (global) {
	'use strict';

	const j0 = global.j0;
	const core = j0 && j0.get('editor:core');
	if (!core) return;

	const { state, qs, qsa, markDirty, hexToRgba, bindSliderPair, setSliderPair, refreshBackdrop } = core;

	const stageBgEl = qs('[data-ed-role="stage-bg"]');
	const targetLabel = qs('[data-ed-role="bg-target-label"]');
	const typeSel = qs('[data-ed-control="bg-type"]');
	const subImage = qs('[data-ed-subpanel="bg-image"]');
	const subColor = qs('[data-ed-subpanel="bg-color"]');
	const subGrad = qs('[data-ed-subpanel="bg-gradient"]');
	const imgPreview = qs('[data-ed-action="pick-bg-image"]');
	const imgFile = qs('[data-ed-control="bg-file"]');
	const imgFitSel = qs('[data-ed-control="bg-img-fit"]');
	const imgOpacityRow = qs('[data-ed-role="bg-img-opacity-row"]');
	const btnDelBg = qs('[data-ed-action="delete-bg"]');

	function getTargetBgState() {
		return state.activeBox ? state.activeBox._state.bg : state.stageBg;
	}

	function getTargetDomLayer() {
		return state.activeBox ? qs('.ed-box-bg', state.activeBox) : stageBgEl;
	}

	function applyBgToLayer(bgState, layerEl, isBoxLayer) {
		if (!layerEl) return;
		if (bgState.type === 'none') {
			layerEl.style.backgroundImage = '';
			layerEl.style.backgroundColor = 'transparent';
			layerEl.style.opacity = '1';
		} else if (bgState.type === 'image') {
			layerEl.style.backgroundColor = 'transparent';
			layerEl.style.backgroundImage = bgState.image ? 'url("' + bgState.image + '")' : '';
			layerEl.style.backgroundSize = bgState.imgFit || 'cover';
			layerEl.style.opacity = isBoxLayer ? String((bgState.imgOpacity ?? 100) / 100) : '1';
		} else if (bgState.type === 'color') {
			layerEl.style.backgroundImage = '';
			layerEl.style.backgroundColor = hexToRgba(bgState.color, bgState.colorAlpha);
			layerEl.style.opacity = '1';
		} else if (bgState.type === 'gradient') {
			const c1 = hexToRgba(bgState.gradC1, bgState.gradA1);
			const c2 = hexToRgba(bgState.gradC2, bgState.gradA2);
			layerEl.style.backgroundColor = 'transparent';
			layerEl.style.backgroundImage = 'linear-gradient(' + bgState.gradAngle + 'deg, ' + c1 + ', ' + c2 + ')';
			layerEl.style.opacity = '1';
		}
		refreshBackdrop();
	}

	function syncBackgroundPanel() {
		const isBox = Boolean(state.activeBox);
		const bg = getTargetBgState();
		targetLabel.textContent = isBox ? 'Selected Box' : 'Stage';
		typeSel.value = bg.type;

		subImage.hidden = bg.type !== 'image';
		subColor.hidden = bg.type !== 'color';
		subGrad.hidden = bg.type !== 'gradient';

		if (bg.type === 'image') {
			imgPreview.style.backgroundImage = bg.image ? 'url("' + bg.image + '")' : '';
			imgPreview.classList.toggle('has-image', Boolean(bg.image));
			btnDelBg.disabled = !bg.image;
			imgFitSel.value = bg.imgFit || 'cover';
			imgOpacityRow.hidden = !isBox;
			const opRange = qs('input[type="range"][data-ed-control="bg-img-opacity"]');
			if (opRange) opRange.hidden = !isBox;
			if (isBox) setSliderPair('bg-img-opacity', bg.imgOpacity ?? 100);
		} else if (bg.type === 'color') {
			qs('[data-ed-control="bg-color-val"]').value = bg.color;
			setSliderPair('bg-color-alpha', bg.colorAlpha);
		} else if (bg.type === 'gradient') {
			qs('[data-ed-control="bg-grad-c1"]').value = bg.gradC1;
			setSliderPair('bg-grad-a1', bg.gradA1);
			qs('[data-ed-control="bg-grad-c2"]').value = bg.gradC2;
			setSliderPair('bg-grad-a2', bg.gradA2);
			setSliderPair('bg-grad-angle', bg.gradAngle);
		}
	}

	function isBlobUrlStillReferenced(url, excludeBg) {
		if (!url || !url.startsWith('blob:')) return false;
		if (state.stageBg !== excludeBg && state.stageBg.image === url) return true;
		return qsa('.ed-box', qs('[data-ed-role="stage"]')).some((box) => {
			const boxBg = box._state && box._state.bg;
			return boxBg && boxBg !== excludeBg && boxBg.image === url;
		});
	}

	function releaseBgImage(bg) {
		if (!bg) return;
		const oldUrl = bg.image;
		bg.image = '';
		bg._blob = null;
		bg._fileName = '';
		if (oldUrl && oldUrl.startsWith('blob:') && !isBlobUrlStillReferenced(oldUrl, bg)) {
			URL.revokeObjectURL(oldUrl);
		}
	}

	function loadFileIntoTarget(file, explicitBox) {
		if (!file || !file.type.startsWith('image/')) return;
		if (explicitBox && state.activeBox !== explicitBox) j0.bus.emit('editor:box:request-select', explicitBox);
		const isBox = Boolean(state.activeBox);
		const bg = getTargetBgState();
		releaseBgImage(bg);
		bg.type = 'image';
		bg.image = URL.createObjectURL(file);
		bg._blob = file;
		bg._fileName = file.name || 'image.png';
		if (isBox && bg.imgOpacity === undefined) bg.imgOpacity = 100;
		if (!bg.imgFit) bg.imgFit = 'cover';
		applyBgToLayer(bg, getTargetDomLayer(), isBox);
		syncBackgroundPanel();
		markDirty();
	}

	function updateActiveBg() {
		applyBgToLayer(getTargetBgState(), getTargetDomLayer(), Boolean(state.activeBox));
		markDirty();
	}

	typeSel.addEventListener('change', () => {
		const bg = getTargetBgState();
		if (typeSel.value !== 'image') releaseBgImage(bg);
		bg.type = typeSel.value;
		updateActiveBg();
		syncBackgroundPanel();
	});
	imgFitSel.addEventListener('change', () => { getTargetBgState().imgFit = imgFitSel.value; updateActiveBg(); });
	imgPreview.addEventListener('click', () => imgFile.click());
	imgFile.addEventListener('change', (e) => { loadFileIntoTarget(e.target.files[0], null); imgFile.value = ''; });
	btnDelBg.addEventListener('click', () => { releaseBgImage(getTargetBgState()); updateActiveBg(); syncBackgroundPanel(); });

	['dragenter', 'dragover', 'dragleave', 'drop'].forEach((evt) => global.addEventListener(evt, (e) => e.preventDefault()));
	['dragenter', 'dragover'].forEach((evt) => imgPreview.addEventListener(evt, () => imgPreview.classList.add('is-dragover')));
	['dragleave', 'drop'].forEach((evt) => imgPreview.addEventListener(evt, () => imgPreview.classList.remove('is-dragover')));
	imgPreview.addEventListener('drop', (e) => loadFileIntoTarget(e.dataTransfer && e.dataTransfer.files[0], null));
	qs('[data-ed-role="stage"]').addEventListener('drop', (e) => {
		const dropBox = e.target.closest('.ed-box');
		if (!dropBox && state.activeBox) j0.bus.emit('editor:box:request-select', null);
		loadFileIntoTarget(e.dataTransfer && e.dataTransfer.files[0], dropBox);
	});

	bindSliderPair('bg-img-opacity', (v) => { getTargetBgState().imgOpacity = v; updateActiveBg(); });
	qs('[data-ed-control="bg-color-val"]').addEventListener('input', (e) => { getTargetBgState().color = e.target.value; updateActiveBg(); });
	bindSliderPair('bg-color-alpha', (v) => { getTargetBgState().colorAlpha = v; updateActiveBg(); });
	qs('[data-ed-control="bg-grad-c1"]').addEventListener('input', (e) => { getTargetBgState().gradC1 = e.target.value; updateActiveBg(); });
	bindSliderPair('bg-grad-a1', (v) => { getTargetBgState().gradA1 = v; updateActiveBg(); });
	qs('[data-ed-control="bg-grad-c2"]').addEventListener('input', (e) => { getTargetBgState().gradC2 = e.target.value; updateActiveBg(); });
	bindSliderPair('bg-grad-a2', (v) => { getTargetBgState().gradA2 = v; updateActiveBg(); });
	bindSliderPair('bg-grad-angle', (v) => { getTargetBgState().gradAngle = v; updateActiveBg(); });

	j0.bus.on('editor:selection:changed', syncBackgroundPanel);
	j0.register('editor:bg', Object.freeze({ applyBgToLayer, syncBackgroundPanel, releaseBgImage }));
})(typeof globalThis !== 'undefined' ? globalThis : window);