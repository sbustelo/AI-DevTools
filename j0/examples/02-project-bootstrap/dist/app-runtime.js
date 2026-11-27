/*! j0 compiled */
;
/* === SOURCE: j0010-kernel.js === */
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
;
/* === SOURCE: j0010-vfs-core.js === */
// PATH: ai0/j0/src/vfs/j0010-vfs-core.js
(function () {
    'use strict';

    if (!globalThis.j0 || !globalThis.j0.register || !globalThis.j0.bus) {
        return;
    }
    if (globalThis.j0.vfs && globalThis.j0.vfs.kv) {
        return;
    }

    const j0 = globalThis.j0;
    const drivers = new Map();
    const uiSyncMap = new Map();
    const memoryFallback = new Map();
    let activeMode = 'local';

    function readUiCookie() {
        if (typeof document === 'undefined') {
            return {};
        }
        const match = document.cookie.match(/(?:^|;\s*)j0_ui=([^;]+)/);
        if (!match) {
            return {};
        }
        const decoded = decodeURIComponent(match[1]);
        const state = {};
        decoded.split('|').forEach((pair) => {
            const idx = pair.indexOf(':');
            if (idx <= 0) {
                return;
            }
            const shortKey = pair.slice(0, idx);
            const rawVal = pair.slice(idx + 1);
            uiSyncMap.forEach((spec, fullKey) => {
                if (spec.shortKey === shortKey) {
                    state[fullKey] = spec.decode(rawVal);
                }
            });
        });
        return state;
    }

    function writeUiCookie() {
        if (typeof document === 'undefined') {
            return;
        }
        const parts = [];
        uiSyncMap.forEach((spec, fullKey) => {
            const val = kv.get(fullKey, null);
            if (val !== null) {
                const encoded = spec.encode(val);
                if (encoded !== null && encoded !== undefined && encoded !== '') {
                    parts.push(`${spec.shortKey}:${encoded}`);
                }
            }
        });
        const base = j0.basePath || '/';
        const maxAge = parts.length > 0 ? 31536000 : 0;
        document.cookie = `j0_ui=${encodeURIComponent(parts.join('|'))}; path=${base}; max-age=${maxAge}; SameSite=Lax`;
    }

    function reconcileUiSync() {
        const cookieValues = readUiCookie();
        Object.entries(cookieValues).forEach(([fullKey, val]) => {
            if (kv.get(fullKey, null) !== val) {
                try {
                    globalThis.localStorage.setItem(fullKey, String(val));
                } catch (e) {
                    memoryFallback.set(fullKey, String(val));
                }
            }
        });
    }

    const kv = {
        get(key, defaultValue = null, maxAgeMs = 0) {
            try {
                if (maxAgeMs > 0) {
                    const tsRaw = globalThis.localStorage.getItem('__j0t_' + key);
                    if (tsRaw && Date.now() - parseInt(tsRaw, 10) > maxAgeMs) {
                        kv.remove(key);
                        return defaultValue;
                    }
                }
                const val = globalThis.localStorage.getItem(key);
                return val !== null ? val : defaultValue;
            } catch (e) {
                return memoryFallback.has(key) ? memoryFallback.get(key) : defaultValue;
            }
        },

        set(key, value, options = {}) {
            const strVal = String(value);
            try {
                globalThis.localStorage.setItem(key, strVal);
                globalThis.localStorage.setItem('__j0t_' + key, String(Date.now()));
            } catch (e) {
                memoryFallback.set(key, strVal);
                if (e && (e.name === 'QuotaExceededError' || e.code === 22)) {
                    j0.bus.emit('j0:vfs:quota-exceeded', { key });
                }
            }
            if (options.profile === 'ui-sync' || uiSyncMap.has(key)) {
                writeUiCookie();
            }
            j0.bus.emit('j0:vfs:changed', { scope: 'kv', key });
            return true;
        },

        remove(key) {
            try {
                globalThis.localStorage.removeItem(key);
                globalThis.localStorage.removeItem('__j0t_' + key);
            } catch (e) {
                memoryFallback.delete(key);
            }
            if (uiSyncMap.has(key)) {
                writeUiCookie();
            }
            j0.bus.emit('j0:vfs:changed', { scope: 'kv', key });
        }
    };

    const vfs = {
        kv,
        get: (key, def = null, maxAgeMs = 0) => kv.get(key, def, maxAgeMs),
        set: (key, val, opts = {}) => kv.set(key, val, opts),
        remove: (key) => kv.remove(key),

        registerUiSync(key, spec) {
            if (!key || !spec || !spec.shortKey) {
                return;
            }
            uiSyncMap.set(key, {
                shortKey: String(spec.shortKey),
                encode: typeof spec.encode === 'function' ? spec.encode : (v) => String(v),
                decode: typeof spec.decode === 'function' ? spec.decode : (v) => String(v)
            });
            reconcileUiSync();
        },

        registerDriver(name, driver) {
            drivers.set(name, driver);
            j0.bus.emit('j0:vfs:driver-registered', { name });
        },

        getDriver(name) {
            return drivers.get(name) ?? null;
        },

        mode() {
            return activeMode;
        },

        setMode(nextMode) {
            if (activeMode === nextMode) {
                return;
            }
            activeMode = nextMode;
            j0.bus.emit('j0:vfs:mode-changed', { mode: activeMode });
        }
    };

    j0.bus.schema['j0:vfs:changed'] = { scope: 'string', key: 'string' };
    j0.bus.schema['j0:vfs:quota-exceeded'] = { key: 'string' };
    j0.bus.schema['j0:vfs:driver-registered'] = { name: 'string' };
    j0.bus.schema['j0:vfs:mode-changed'] = { mode: 'string' };

    j0.vfs = vfs;
    j0.register('vfs', vfs);
})();
;
/* === SOURCE: j0020-vfs-idb.js === */
// PATH: ai0/j0/src/vfs/j0020-vfs-idb.js
(function () {
    'use strict';

    if (!globalThis.j0 || !globalThis.j0.vfs) {
        return;
    }

    const DB_NAME = 'j0_vfs_store_v1';
    const DB_VERSION = 1;
    const STORE_BLOBS = 'blobs';
    const STORE_DOCS = 'docs';
    let dbPromise = null;

    function openDb() {
        if (dbPromise) {
            return dbPromise;
        }
        dbPromise = new Promise((resolve, reject) => {
            if (!globalThis.indexedDB) {
                reject(new Error('IndexedDB unavailable'));
                return;
            }
            const req = globalThis.indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = (ev) => {
                const db = ev.target.result;
                if (!db.objectStoreNames.contains(STORE_BLOBS)) {
                    db.createObjectStore(STORE_BLOBS, { keyPath: 'hash' });
                }
                if (!db.objectStoreNames.contains(STORE_DOCS)) {
                    const docsStore = db.createObjectStore(STORE_DOCS, { keyPath: 'pk' });
                    docsStore.createIndex('by_collection', 'collection', { unique: false });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
        return dbPromise;
    }

    async function hashBlob(blob) {
        const buf = await blob.arrayBuffer();
        if (globalThis.crypto && globalThis.crypto.subtle) {
            const digest = await globalThis.crypto.subtle.digest('SHA-256', buf);
            return Array.from(new Uint8Array(digest))
                .map((b) => b.toString(16).padStart(2, '0'))
                .join('');
        }
        const bytes = new Uint8Array(buf);
        let h = 2166136261;
        for (let i = 0; i < bytes.length; i++) {
            h ^= bytes[i];
            h = Math.imul(h, 16777619);
        }
        return 'fnv1a_' + (h >>> 0).toString(16).padStart(8, '0') + '_' + bytes.length;
    }

    const idbDriver = {
        async putBlob(blob) {
            const hash = await hashBlob(blob);
            const db = await openDb();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(STORE_BLOBS, 'readwrite');
                const store = tx.objectStore(STORE_BLOBS);
                const check = store.get(hash);
                check.onsuccess = () => {
                    if (!check.result) {
                        store.put({
                            hash,
                            blob,
                            type: blob.type || 'application/octet-stream',
                            size: blob.size || 0,
                            createdAt: Date.now()
                        });
                    }
                };
                tx.oncomplete = () => resolve(hash);
                tx.onerror = () => reject(tx.error);
            });
        },

        async getBlob(hash) {
            const db = await openDb();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(STORE_BLOBS, 'readonly');
                const req = tx.objectStore(STORE_BLOBS).get(hash);
                req.onsuccess = () => resolve(req.result ? req.result.blob : null);
                req.onerror = () => reject(req.error);
            });
        },

        async removeBlob(hash) {
            const db = await openDb();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(STORE_BLOBS, 'readwrite');
                tx.objectStore(STORE_BLOBS).delete(hash);
                tx.oncomplete = () => resolve(true);
                tx.onerror = () => reject(tx.error);
            });
        },

        async listBlobs() {
            const db = await openDb();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(STORE_BLOBS, 'readonly');
                const req = tx.objectStore(STORE_BLOBS).getAllKeys();
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            });
        },

        async listDocs(collection) {
            const db = await openDb();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(STORE_DOCS, 'readonly');
                const idx = tx.objectStore(STORE_DOCS).index('by_collection');
                const req = idx.getAll(IDBKeyRange.only(collection));
                req.onsuccess = () => {
                    const rows = (req.result || []).sort((a, b) => (b.updatedAt || 0) - (a.updatedAt || 0));
                    resolve(rows);
                };
                req.onerror = () => reject(req.error);
            });
        },

        async getDoc(collection, id) {
            const db = await openDb();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(STORE_DOCS, 'readonly');
                const req = tx.objectStore(STORE_DOCS).get(`${collection}:${id}`);
                req.onsuccess = () => resolve(req.result || null);
                req.onerror = () => reject(req.error);
            });
        },

        async saveDoc(collection, doc) {
            const db = await openDb();
            const record = {
                pk: `${collection}:${doc.id}`,
                collection,
                id: doc.id,
                title: doc.title || 'Untitled',
                preview: doc.preview || '',
                meta: doc.meta || {},
                data: doc.data ?? null,
                blobs: Array.isArray(doc.blobs) ? doc.blobs : [],
                updatedAt: doc.updatedAt || Date.now(),
                dirty: Boolean(doc.dirty)
            };
            return new Promise((resolve, reject) => {
                const tx = db.transaction(STORE_DOCS, 'readwrite');
                tx.objectStore(STORE_DOCS).put(record);
                tx.oncomplete = () => resolve(record);
                tx.onerror = () => reject(tx.error);
            });
        },

        async removeDoc(collection, id) {
            const db = await openDb();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(STORE_DOCS, 'readwrite');
                tx.objectStore(STORE_DOCS).delete(`${collection}:${id}`);
                tx.oncomplete = () => resolve(true);
                tx.onerror = () => reject(tx.error);
            });
        }
    };

    globalThis.j0.vfs.registerDriver('idb', idbDriver);
})();
;
/* === SOURCE: j0030-vfs-docs.js === */
// PATH: ai0/j0/src/vfs/j0030-vfs-docs.js
(function () {
    'use strict';

    if (!globalThis.j0 || !globalThis.j0.vfs || !globalThis.j0.bus) {
        return;
    }

    const j0 = globalThis.j0;
    const vfs = j0.vfs;
    const memBlobs = new Map();
    const memDocs = new Map();

    function localDriver() {
        return vfs.getDriver('idb');
    }

    function remoteDriver() {
        return vfs.mode() === 'hybrid-sync' ? vfs.getDriver('remote') : null;
    }

    function generateId() {
        if (globalThis.crypto && typeof globalThis.crypto.randomUUID === 'function') {
            return globalThis.crypto.randomUUID();
        }
        return 'doc_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    }

    function blobToDataUrl(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(String(reader.result));
            reader.onerror = () => reject(reader.error);
            reader.readAsDataURL(blob);
        });
    }

    vfs.blobs = {
        async put(blob) {
            const idb = localDriver();
            const hash = idb ? await idb.putBlob(blob) : ('mem_' + Date.now() + '_' + memBlobs.size);
            if (!idb) {
                memBlobs.set(hash, blob);
            }
            j0.bus.emit('j0:vfs:changed', { scope: 'blobs', key: hash });
            return hash;
        },

        async get(hash) {
            const idb = localDriver();
            if (idb) {
                return idb.getBlob(hash);
            }
            return memBlobs.get(hash) ?? null;
        },

        async has(hash) {
            return (await vfs.blobs.get(hash)) !== null;
        },

        async remove(hash) {
            const idb = localDriver();
            const ok = idb ? await idb.removeBlob(hash) : memBlobs.delete(hash);
            j0.bus.emit('j0:vfs:changed', { scope: 'blobs', key: hash });
            return ok;
        },

        async list() {
            const idb = localDriver();
            return idb ? idb.listBlobs() : Array.from(memBlobs.keys());
        }
    };

    vfs.docs = {
        async list(collection = 'default') {
            const idb = localDriver();
            const localList = idb
                ? await idb.listDocs(collection)
                : Array.from(memDocs.values()).filter((d) => d.collection === collection);

            const remote = remoteDriver();
            if (!remote) {
                return localList;
            }
            return remote.mergeList(collection, localList);
        },

        async get(collection, id) {
            const idb = localDriver();
            const localDoc = idb ? await idb.getDoc(collection, id) : (memDocs.get(`${collection}:${id}`) ?? null);
            const remote = remoteDriver();
            if (localDoc || !remote) {
                return localDoc;
            }
            const fetched = await remote.get(collection, id);
            if (fetched && idb) {
                await idb.saveDoc(collection, Object.assign({}, fetched, { dirty: false }));
            }
            return fetched;
        },

        async save(collection, doc) {
            const id = doc.id || generateId();
            const remote = remoteDriver();
            const draft = Object.assign({}, doc, {
                id,
                updatedAt: Date.now(),
                dirty: Boolean(remote)
            });

            const idb = localDriver();
            const saved = idb
                ? await idb.saveDoc(collection, draft)
                : (() => {
                    memDocs.set(`${collection}:${id}`, Object.assign({ collection }, draft));
                    return memDocs.get(`${collection}:${id}`);
                })();

            j0.bus.emit('j0:vfs:changed', { scope: 'docs', key: `${collection}:${id}` });

            if (remote) {
                remote.push(collection, saved).catch(() => {});
            }
            return saved;
        },

        async remove(collection, id) {
            const idb = localDriver();
            if (idb) {
                await idb.removeDoc(collection, id);
            } else {
                memDocs.delete(`${collection}:${id}`);
            }
            j0.bus.emit('j0:vfs:changed', { scope: 'docs', key: `${collection}:${id}` });

            const remote = remoteDriver();
            if (remote) {
                await remote.remove(collection, id);
            }
            return true;
        },

        async exportJson(collection, id) {
            const doc = await vfs.docs.get(collection, id);
            if (!doc) {
                return null;
            }
            const embeddedBlobs = {};
            for (const hash of (doc.blobs || [])) {
                const blob = await vfs.blobs.get(hash);
                if (blob) {
                    embeddedBlobs[hash] = await blobToDataUrl(blob);
                }
            }
            return { version: 1, collection, doc, blobs: embeddedBlobs };
        },

        async importJson(collection, payload) {
            const parsed = typeof payload === 'string' ? JSON.parse(payload) : payload;
            if (!parsed || !parsed.doc) {
                throw new Error('Invalid VFS document payload.');
            }
            const hashMap = {};
            if (parsed.blobs && typeof parsed.blobs === 'object') {
                for (const [oldHash, dataUrl] of Object.entries(parsed.blobs)) {
                    const res = await fetch(String(dataUrl));
                    const blob = await res.blob();
                    hashMap[oldHash] = await vfs.blobs.put(blob);
                }
            }
            const remappedBlobs = (parsed.doc.blobs || []).map((h) => hashMap[h] || h);
            return vfs.docs.save(collection, Object.assign({}, parsed.doc, {
                id: parsed.doc.id || generateId(),
                blobs: remappedBlobs
            }));
        },

        async syncOrphans(collection = 'default') {
            const allHashes = await vfs.blobs.list();
            const docs = await vfs.docs.list(collection);
            const used = new Set();
            docs.forEach((d) => (d.blobs || []).forEach((h) => used.add(h)));
            return allHashes.filter((h) => !used.has(h));
        }
    };
})();
