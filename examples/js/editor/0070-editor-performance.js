// PATH: examples/js/editor/0070-editor-performance.js
(function (global) {
	'use strict';

	const j0 = global.j0;
	const core = j0 && j0.get('editor:core');
	if (!core) return;

	const { state, qs, markDirty, bindSliderPair, setSliderPair, refreshBackdrop } = core;

	const panelPerf = qs('[data-ed-panel="performance"]');
	const presetSel = qs('[data-ed-control="perf-preset"]');
	const customSubpanel = qs('[data-ed-subpanel="perf-custom"]');
	const backendSel = qs('[data-ed-control="perf-backend"]');
	const telemetryEl = qs('[data-ed-role="perf-telemetry"]');

	function getPresets() {
		return j0.get('editor:presets') || global.EdPresets || {
			DEFAULT_PRESET: 'balanced',
			PROFILES: { balanced: { backend: 'auto', performance: 60, minBudget: 65536, cellSize: 12, maxPixelScale: 2 } }
		};
	}

	function applyConfig() {
		const p = state.performance;
		if (!p) return;
		const ipax = j0.get('ipax-backdrop');
		if (ipax && ipax.config) {
			ipax.config.backend = p.backend;
			ipax.config.cellSize = Number(p.cellSize);
			ipax.config.performance = Number(p.performance);
			ipax.config.minBudget = Number(p.minBudget);
			ipax.config.maxPixelScale = Number(p.maxPixelScale);
		}
		refreshBackdrop();
	}

	function syncPanel() {
		const p = state.performance;
		const presets = getPresets();
		if (!p || !presetSel) return;
		presetSel.value = p.preset || presets.DEFAULT_PRESET;
		if (customSubpanel) customSubpanel.hidden = (presetSel.value !== 'custom');
		if (backendSel) backendSel.value = p.backend;
		setSliderPair('perf-cellsize', p.cellSize);
		setSliderPair('perf-fps', p.performance);
		setSliderPair('perf-budget', p.minBudget);
		setSliderPair('perf-scale', p.maxPixelScale);
	}

	function applyPreset(presetKey) {
		const presets = getPresets();
		if (presetKey === 'custom') {
			if (!state.performance) state.performance = Object.assign({ preset: 'custom' }, presets.PROFILES[presets.DEFAULT_PRESET]);
			else state.performance.preset = 'custom';
			syncPanel();
			markDirty();
			return;
		}
		const resolved = presets.PROFILES[presetKey] ? presetKey : presets.DEFAULT_PRESET;
		state.performance = Object.assign({ preset: resolved }, presets.PROFILES[resolved]);
		applyConfig();
		syncPanel();
		markDirty();
	}

	function switchToCustom() {
		if (!state.performance) return;
		state.performance.preset = 'custom';
		if (presetSel) presetSel.value = 'custom';
		if (customSubpanel) customSubpanel.hidden = false;
	}

	if (presetSel) presetSel.addEventListener('change', () => applyPreset(presetSel.value));

	if (backendSel) {
		backendSel.addEventListener('change', () => {
			if (!state.performance) return;
			state.performance.backend = backendSel.value;
			switchToCustom();
			applyConfig();
			markDirty();
		});
	}

	bindSliderPair('perf-cellsize', (v) => { if (state.performance) { state.performance.cellSize = v; switchToCustom(); applyConfig(); } });
	bindSliderPair('perf-fps', (v) => { if (state.performance) { state.performance.performance = v; switchToCustom(); applyConfig(); } });
	bindSliderPair('perf-budget', (v) => { if (state.performance) { state.performance.minBudget = v; switchToCustom(); applyConfig(); } });
	bindSliderPair('perf-scale', (v) => { if (state.performance) { state.performance.maxPixelScale = v; switchToCustom(); applyConfig(); } });

	j0.bus.on('editor:selection:changed', (box) => {
		if (panelPerf) panelPerf.hidden = !!box;
		if (!box) syncPanel();
	});

	j0.bus.on('ipax-backdrop:updated', (d) => {
		if (!telemetryEl || !d) return;
		const backend = (d.backend || 'unknown').toUpperCase();
		telemetryEl.textContent = 'Engine: ' + backend + ' \u2022 ' + Number(d.ms).toFixed(1) + ' ms';
	});
	j0.bus.on('ipax-backdrop:backend', (d) => {
		if (!telemetryEl || !d) return;
		telemetryEl.textContent = 'Engine: ' + (d.backend || 'unknown').toUpperCase();
	});

	const initialIpax = j0.get('ipax-backdrop');
	if (telemetryEl && initialIpax && typeof initialIpax.backend === 'function' && !initialIpax.backend()) {
		telemetryEl.textContent = 'Engine: idle (0 layers)';
	}

	j0.register('editor:perf', Object.freeze({ applyPreset, applyConfig, syncPanel }));
})(typeof globalThis !== 'undefined' ? globalThis : window);