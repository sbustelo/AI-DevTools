// PATH: j0/src/0520-ui-windows/j0010-j0-flip.js
(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined' ? globalThis : {};
    if (!root.j0 || !root.j0.bus || typeof root.j0.bus.on !== 'function') {
        return;
    }

    function resolveFlippable(payload) {
        if (!payload) {
            return null;
        }
        if (payload.target && payload.target.closest) {
            return payload.target.closest('.is-flippable, .j0ui-flip-container');
        }
        if (payload.trigger && payload.trigger.closest) {
            return payload.trigger.closest('.is-flippable, .j0ui-flip-container');
        }
        return null;
    }

    root.j0.bus.on('ui:flip-turn', function (payload) {
        const flippable = resolveFlippable(payload);
        if (!flippable) {
            return;
        }
        flippable.classList.add('is-flipping');
        flippable.classList.add('is-flipped');
        setTimeout(function () {
            flippable.classList.remove('is-flipping');
        }, 400);
    });

    root.j0.bus.on('ui:flip-back', function (payload) {
        const flippable = resolveFlippable(payload);
        if (!flippable) {
            return;
        }
        flippable.classList.add('is-flipping');
        flippable.classList.remove('is-flipped');
        setTimeout(function () {
            flippable.classList.remove('is-flipping');
        }, 400);
    });
})();