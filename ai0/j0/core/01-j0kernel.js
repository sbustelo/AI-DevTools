(function () {
    'use strict';

    const root = typeof globalThis !== 'undefined'
        ? globalThis
        : (typeof window !== 'undefined' ? window : (typeof self !== 'undefined' ? self : {}));

    root.j0 = root.j0 || {};
    const j0 = root.j0;

    if (j0.bus && j0.registry) {
        return;
    }

    class EventChannel {
        constructor(name = 'global') {
            this.name = name;
            this._handlers = new Map();
        }

        emit(action, payload = {}) {
            if (j0.QA && j0.bus && j0.bus.schema) {
                const contract = j0.bus.schema[action];
                if (contract) {
                    for (const [key, expectedType] of Object.entries(contract)) {
                        const actualType = typeof payload[key];
                        if (actualType !== expectedType) {
                            const msg = '[j0:bus:schema] Invalid payload on \'' + action + '\': \'' + key + '\' must be \'' + expectedType + '\', got \'' + actualType + '\'.';
                            j0.logger.error(msg);
                            throw new Error(msg);
                        }
                    }
                }
            }

            const set = this._handlers.get(action);
            if (!set || set.size === 0) {
                return;
            }

            new Set(set).forEach((fn) => {
                try {
                    fn(payload);
                } catch (err) {
                    const preview = fn.toString().substring(0, 60) + '...';
                    j0.logger.error('[j0:bus] Handler crash on \'' + this.name + ':' + action + '\' (' + preview + ')', err);
                }
            });
        }

        on(action, handler) {
            if (typeof handler !== 'function') {
                return () => {};
            }
            if (!this._handlers.has(action)) {
                this._handlers.set(action, new Set());
            }
            this._handlers.get(action).add(handler);
            return () => {
                this.off(action, handler);
            };
        }

        off(action, handler) {
            const set = this._handlers.get(action);
            if (!set) {
                return;
            }
            set.delete(handler);
            if (set.size === 0) {
                this._handlers.delete(action);
            }
        }

        once(action, handler) {
            const wrapper = (payload) => {
                this.off(action, wrapper);
                handler(payload);
            };
            return this.on(action, wrapper);
        }
    }

    const MAX_LOG_ENTRIES = 200;
    const logEntries = [];

    function recordLog(level, msg, data) {
        const entry = { level, msg: String(msg), data: data ?? null, ts: Date.now() };
        logEntries.push(entry);
        if (logEntries.length > MAX_LOG_ENTRIES) {
            logEntries.shift();
        }
        if (j0.bus && level !== 'silent') {
            j0.bus.emit('j0:log', { level, msg: entry.msg });
        }
        return entry;
    }

    j0.EventChannel = EventChannel;
    j0.bus = j0.bus || new EventChannel('global');
    j0.bus.schema = j0.bus.schema || {};
    j0.registry = j0.registry || new Map();

    j0.logger = j0.logger || {
        log(msg, data) {
            recordLog('info', msg, data);
            if (j0.QA || j0.env === 'local') {
                console.log('[j0] ' + msg, data ?? '');
            }
        },
        warn(msg, data) {
            recordLog('warn', msg, data);
            console.warn('[j0:warn] ' + msg, data ?? '');
        },
        error(msg, data) {
            recordLog('error', msg, data);
            console.error('[j0:error] ' + msg, data ?? '');
        },
        history() {
            return logEntries.slice();
        },
        clear() {
            logEntries.length = 0;
        }
    };

    j0.register = (name, instance) => {
        j0.registry.set(name, instance);
        j0.bus.emit('j0:module:registered', { name });
        return instance;
    };

    j0.get = (name) => j0.registry.get(name) ?? null;
    j0.has = (name) => j0.registry.has(name);

    j0.bus.schema['j0:module:registered'] = { name: 'string' };
    j0.bus.schema['j0:log'] = { level: 'string', msg: 'string' };
})();