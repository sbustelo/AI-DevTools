// PATH: ai0/j0/src/vfs--remote/j0010-vfs-remote.js
(function () {
    'use strict';

    if (!globalThis.j0 || !globalThis.j0.vfs || !globalThis.j0.bus) {
        return;
    }

    const j0 = globalThis.j0;
    const vfs = j0.vfs;

    const remoteConfig = {
        endpoint: '/api/vfs',
        headers: {}
    };

    let sessionContext = null;

    j0.bus.schema['j0:vfs:syncing'] = { collection: 'string' };
    j0.bus.schema['j0:vfs:synced'] = { collection: 'string', count: 'number' };
    j0.bus.schema['j0:vfs:sync-error'] = { message: 'string' };

    async function apiRequest(path, options = {}) {
        const headers = Object.assign(
            { 'Content-Type': 'application/json' },
            remoteConfig.headers,
            sessionContext && sessionContext.token ? { Authorization: `Bearer ${sessionContext.token}` } : {},
            options.headers || {}
        );
        const res = await fetch(remoteConfig.endpoint.replace(/\/+$/, '') + path, Object.assign({}, options, {
            credentials: 'same-origin',
            headers
        }));
        if (!res.ok) {
            throw new Error(`VFS remote HTTP ${res.status}`);
        }
        return res.json();
    }

    const remoteDriver = {
        async mergeList(collection, localList) {
            if (!navigator.onLine) {
                return localList;
            }
            try {
                const payload = await apiRequest(`/${encodeURIComponent(collection)}`);
                const remoteDocs = Array.isArray(payload.docs) ? payload.docs : [];
                const byId = new Map();
                remoteDocs.forEach((doc) => byId.set(doc.id, Object.assign({}, doc, { dirty: false })));
                localList.forEach((localDoc) => {
                    const existing = byId.get(localDoc.id);
                    if (!existing || localDoc.dirty || (localDoc.updatedAt || 0) > (existing.updatedAt || 0)) {
                        byId.set(localDoc.id, localDoc);
                    }
                });
                return Array.from(byId.values()).sort((a, b) => (b.updatedAt || 0) - (a.updatedAt || 0));
            } catch (err) {
                j0.bus.emit('j0:vfs:sync-error', { message: String(err.message || err) });
                return localList;
            }
        },

        async get(collection, id) {
            if (!navigator.onLine) {
                return null;
            }
            try {
                const payload = await apiRequest(`/${encodeURIComponent(collection)}/${encodeURIComponent(id)}`);
                return payload.doc || null;
            } catch (err) {
                j0.bus.emit('j0:vfs:sync-error', { message: String(err.message || err) });
                return null;
            }
        },

        async push(collection, doc) {
            if (!navigator.onLine) {
                return null;
            }
            j0.bus.emit('j0:vfs:syncing', { collection });
            try {
                const payload = await apiRequest(`/${encodeURIComponent(collection)}/${encodeURIComponent(doc.id)}`, {
                    method: 'PUT',
                    body: JSON.stringify({ doc })
                });
                const idb = vfs.getDriver('idb');
                if (idb) {
                    await idb.saveDoc(collection, Object.assign({}, doc, { dirty: false }));
                }
                j0.bus.emit('j0:vfs:synced', { collection, count: 1 });
                return payload.doc || doc;
            } catch (err) {
                j0.bus.emit('j0:vfs:sync-error', { message: String(err.message || err) });
                throw err;
            }
        },

        async remove(collection, id) {
            if (!navigator.onLine) {
                return false;
            }
            try {
                await apiRequest(`/${encodeURIComponent(collection)}/${encodeURIComponent(id)}`, {
                    method: 'DELETE'
                });
                return true;
            } catch (err) {
                j0.bus.emit('j0:vfs:sync-error', { message: String(err.message || err) });
                return false;
            }
        },

        async syncDirty(collection = 'default') {
            const idb = vfs.getDriver('idb');
            if (!idb || !navigator.onLine || vfs.mode() !== 'hybrid-sync') {
                return 0;
            }
            const dirtyDocs = (await idb.listDocs(collection)).filter((d) => d.dirty);
            if (dirtyDocs.length === 0) {
                return 0;
            }
            j0.bus.emit('j0:vfs:syncing', { collection });
            let synced = 0;
            for (const doc of dirtyDocs) {
                try {
                    await remoteDriver.push(collection, doc);
                    synced++;
                } catch (e) {
                    break;
                }
            }
            j0.bus.emit('j0:vfs:synced', { collection, count: synced });
            return synced;
        }
    };

    vfs.configureRemote = (opts = {}) => {
        if (opts.endpoint) {
            remoteConfig.endpoint = String(opts.endpoint);
        }
        if (opts.headers && typeof opts.headers === 'object') {
            remoteConfig.headers = Object.assign({}, opts.headers);
        }
    };

    vfs.registerDriver('remote', remoteDriver);

    const activateCloud = (payload = {}) => {
        sessionContext = payload;
        vfs.setMode('hybrid-sync');
        remoteDriver.syncDirty('default');
    };

    j0.bus.on('auth:login', activateCloud);
    j0.bus.on('auth:session-ready', activateCloud);
    j0.bus.on('auth:logout', () => {
        sessionContext = null;
        vfs.setMode('local');
    });

    globalThis.addEventListener('online', () => {
        if (vfs.mode() === 'hybrid-sync') {
            remoteDriver.syncDirty('default');
        }
    });
})();