// PATH: examples/js/editor/0120-editor-inspector.js
(function (global) {
	'use strict';

	const j0 = global.j0;
	const core = j0 && j0.get('editor:core');
	const boxesApi = j0 && j0.get('editor:boxes');
	if (!core || !boxesApi) return;

	const { state, qs, qsa, markDirty, bindSliderPair, setSliderPair, refreshBackdrop } = core;
	const { createBox, cloneBox, deleteActiveBox, applyIpaxProps, setBoxGeom } = boxesApi;

	const stage = qs('[data-ed-role="stage"]');
	const panelProject = qs('[data-ed-panel="project"]');
	const boxPanels = qs('[data-ed-role="box-panels"]');
	const btnPrimary = qs('[data-ed-action="new-box"]');
	const btnDel = qs('[data-ed-action="delete-box"]');
	const btnEffect = qs('[data-ed-action="toggle-effect"]');
	const fontStatusIcon = qs('[data-ed-role="font-status-icon"]');
	const modeSel = qs('[data-ed-control="ipax-mode"]');
	const chkBoxOn = qs('[data-ed-control="ipax-box-on"]');
	const chkTextOn = qs('[data-ed-control="ipax-text-on"]');
	const subBoxGroup = qs('[data-ed-subgroup="ipax-box"]');
	const subTextGroup = qs('[data-ed-subgroup="ipax-text"]');

	j0.bus.on('editor:font:status', (d) => {
		if (!fontStatusIcon || !d || d.box !== state.activeBox) return;
		fontStatusIcon.textContent = d.isError ? 'error' : (d.source === 'google' ? 'cloud_done' : 'computer');
		fontStatusIcon.classList.toggle('is-error', Boolean(d.isError));
		fontStatusIcon.classList.toggle('is-google', !d.isError && d.source === 'google');
		fontStatusIcon.setAttribute('data-ed-tip', d.msg || 'System font stack');
	});

	function bindDisclosure(btn, targetEl) {
		if (!btn || !targetEl) return;
		btn.addEventListener('click', () => {
			const expanded = btn.getAttribute('aria-expanded') === 'true';
			btn.setAttribute('aria-expanded', String(!expanded));
			targetEl.hidden = expanded;
		});
	}

	qsa('[data-ed-action="toggle-panel"]').forEach((btn) => {
		bindDisclosure(btn, qs('[data-ed-role="panel-body"]', btn.closest('.ed-panel')));
	});
	qsa('[data-ed-action="toggle-subpanel"]').forEach((btn) => {
		bindDisclosure(btn, qs('[data-ed-role="sub-body"]', btn.closest('.ed-sub-group')));
	});
	bindDisclosure(qs('[data-ed-action="toggle-padding"]'), qs('[data-ed-subpanel="padding-trbl"]'));
	bindDisclosure(qs('[data-ed-action="toggle-feather"]'), qs('[data-ed-subpanel="feather-trbl"]'));

	qs('[data-ed-action="save-json"]').addEventListener('click', () => j0.bus.emit('editor:action:save-json', {}));
	qs('[data-ed-action="save-png"]').addEventListener('click', () => j0.bus.emit('editor:action:save-png', {}));
	qs('[data-ed-action="reset-project"]').addEventListener('click', () => j0.bus.emit('editor:action:reset', {}));
	const fileProject = qs('[data-ed-control="project-file"]');
	qs('[data-ed-action="load-json"]').addEventListener('click', () => fileProject.click());
	fileProject.addEventListener('change', (e) => { if (e.target.files[0]) j0.bus.emit('editor:action:load-json', e.target.files[0]); fileProject.value = ''; });

	btnPrimary.addEventListener('click', () => {
		if (state.activeBox) { cloneBox(state.activeBox); return; }
		const offset = (qsa('.ed-box', stage).length % 6) * 26;
		createBox({ left: 64 + offset, top: 64 + offset, width: 340, height: 130, contrast: 4.5, blur: 8, feather: 14, fontSize: 28, text: '', focusText: true });
	});
	btnDel.addEventListener('click', () => deleteActiveBox());
	btnEffect.addEventListener('click', () => {
		state.showEffect = !state.showEffect;
		stage.classList.toggle('is-raw', !state.showEffect);
		btnEffect.classList.toggle('is-active', state.showEffect);
		btnEffect.setAttribute('aria-pressed', String(state.showEffect));
	});

	function syncGeomInputs(box) {
		if (!box) return;
		qs('[data-ed-num="box-x"]').value = String(Math.round(box.offsetLeft));
		qs('[data-ed-num="box-y"]').value = String(Math.round(box.offsetTop));
		qs('[data-ed-num="box-w"]').value = String(Math.round(box.offsetWidth));
		qs('[data-ed-num="box-h"]').value = String(Math.round(box.offsetHeight));
	}

	[['box-x', 'left', -Infinity], ['box-y', 'top', -Infinity], ['box-w', 'width', 80], ['box-h', 'height', 36]].forEach(([key, prop, minVal]) => {
		const el = qs('[data-ed-num="' + key + '"]');
		if (!el) return;
		el.addEventListener('input', () => {
			const raw = parseFloat(el.value);
			if (!state.activeBox || isNaN(raw)) return;
			setBoxGeom(state.activeBox, { [prop]: Math.max(minVal, Math.round(raw)) + 'px' });
			refreshBackdrop();
			markDirty();
		});
	});
	j0.bus.on('editor:box:geom', (box) => { if (box === state.activeBox) syncGeomInputs(box); });

	const applyPadding = () => {
		if (!state.activeBox) return;
		const p = state.activeBox._state.padding;
		const padCss = p.t + 'px ' + p.r + 'px ' + p.b + 'px ' + p.l + 'px';
		qs('.ed-glass', state.activeBox).style.padding = padCss;
		if (state.activeBox._state.styles) state.activeBox._state.styles.padding = padCss;
		refreshBackdrop();
	};
	bindSliderPair('box-padding', (v) => {
		if (!state.activeBox) return;
		const p = state.activeBox._state.padding;
		p.t = p.r = p.b = p.l = v;
		['t', 'r', 'b', 'l'].forEach((side) => { qs('[data-ed-num="box-pad-' + side + '"]').value = String(v); });
		applyPadding();
	});
	['t', 'r', 'b', 'l'].forEach((side) => {
		qs('[data-ed-num="box-pad-' + side + '"]').addEventListener('input', (e) => {
			if (!state.activeBox) return;
			const p = state.activeBox._state.padding;
			p[side] = Math.max(0, Math.min(128, parseFloat(e.target.value) || 0));
			setSliderPair('box-padding', Math.round((p.t + p.r + p.b + p.l) / 4));
			applyPadding();
			markDirty();
		});
	});

	function setSubgroupEnabled(groupEl, enabled) {
		groupEl.classList.toggle('is-disabled', !enabled);
		qsa('input, select', qs('[data-ed-role="sub-body"]', groupEl)).forEach((el) => { el.disabled = !enabled; });
	}
	chkBoxOn.addEventListener('change', () => { if (state.activeBox) { state.activeBox._state.ipax.boxEnabled = chkBoxOn.checked; setSubgroupEnabled(subBoxGroup, chkBoxOn.checked); applyIpaxProps(state.activeBox); markDirty(); } });
	chkTextOn.addEventListener('change', () => { if (state.activeBox) { state.activeBox._state.ipax.textEnabled = chkTextOn.checked; setSubgroupEnabled(subTextGroup, chkTextOn.checked); applyIpaxProps(state.activeBox); markDirty(); } });
	modeSel.addEventListener('change', () => { if (state.activeBox) { state.activeBox._state.ipax.mode = modeSel.value; applyIpaxProps(state.activeBox); markDirty(); } });

	bindSliderPair('ipax-contrast', (v) => { if (state.activeBox) { state.activeBox._state.ipax.contrast = v; applyIpaxProps(state.activeBox); } });
	bindSliderPair('ipax-blur', (v) => { if (state.activeBox) { state.activeBox._state.ipax.blur = v; applyIpaxProps(state.activeBox); } });
	bindSliderPair('ipax-feather', (v) => {
		if (!state.activeBox) return;
		const f = state.activeBox._state.ipax.feather;
		f.t = f.r = f.b = f.l = v;
		['t', 'r', 'b', 'l'].forEach((side) => { qs('[data-ed-num="ipax-feather-' + side + '"]').value = String(v); });
		applyIpaxProps(state.activeBox);
	});
	['t', 'r', 'b', 'l'].forEach((side) => {
		qs('[data-ed-num="ipax-feather-' + side + '"]').addEventListener('input', (e) => {
			if (!state.activeBox) return;
			const f = state.activeBox._state.ipax.feather;
			f[side] = Math.max(0, Math.min(128, parseFloat(e.target.value) || 0));
			setSliderPair('ipax-feather', Math.round((f.t + f.r + f.b + f.l) / 4));
			applyIpaxProps(state.activeBox);
			markDirty();
		});
	});
	['contrast', 'halo', 'spread', 'blur', 'x', 'y'].forEach((prop) => {
		bindSliderPair('ipax-text-' + prop, (v) => {
			if (!state.activeBox) return;
			if (!state.activeBox._state.ipax.text) state.activeBox._state.ipax.text = { contrast: 7, halo: 12, spread: 35, blur: 0, x: 0, y: 0 };
			state.activeBox._state.ipax.text[prop] = v;
			applyIpaxProps(state.activeBox);
		});
	});

	j0.bus.on('editor:selection:changed', (box) => {
		boxPanels.hidden = !box;
		panelProject.hidden = !!box;
		btnDel.disabled = !box;
		btnPrimary.textContent = box ? 'Duplicate' : '+ New box';
		btnPrimary.setAttribute('data-ed-tip', box ? 'Duplicate selected box' : 'Create new text box');
		if (!box) return;
		syncGeomInputs(box);
		const ip = box._state.ipax;
		chkBoxOn.checked = ip.boxEnabled !== false;
		chkTextOn.checked = !!ip.textEnabled;
		setSubgroupEnabled(subBoxGroup, chkBoxOn.checked);
		setSubgroupEnabled(subTextGroup, chkTextOn.checked);
		modeSel.value = ip.mode;
		setSliderPair('ipax-contrast', ip.contrast);
		setSliderPair('ipax-blur', ip.blur);
		setSliderPair('ipax-feather', Math.round((ip.feather.t + ip.feather.r + ip.feather.b + ip.feather.l) / 4));
		['t', 'r', 'b', 'l'].forEach((side) => { qs('[data-ed-num="ipax-feather-' + side + '"]').value = String(ip.feather[side]); });
		const t = ip.text || { contrast: 7, halo: 12, spread: 35, blur: 0, x: 0, y: 0 };
		['contrast', 'halo', 'spread', 'blur', 'x', 'y'].forEach((prop) => setSliderPair('ipax-text-' + prop, t[prop]));
		const pad = box._state.padding;
		setSliderPair('box-padding', Math.round((pad.t + pad.r + pad.b + pad.l) / 4));
		['t', 'r', 'b', 'l'].forEach((side) => { qs('[data-ed-num="box-pad-' + side + '"]').value = String(pad[side]); });
	});
})(typeof globalThis !== 'undefined' ? globalThis : window);