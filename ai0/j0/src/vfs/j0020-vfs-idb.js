// PATH: ai0/j0/src/vfs/j0020-vfs-idb.js
(function () {
    'use strict';

    if (!window.j0 || !window.j0.vfs) {
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
            if (!window.indexedDB) {
                reject(new Error('IndexedDB unavailable'));
                return;
            }
            const req = window.indexedDB.open(DB_NAME, DB_VERSION);
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
        if (window.crypto && window.crypto.subtle) {
            const digest = await window.crypto.subtle.digest('SHA-256', buf);
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

    window.j0.vfs.registerDriver('idb', idbDriver);
})();