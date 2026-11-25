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