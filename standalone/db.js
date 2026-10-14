/*
 * The standalone build's IndexedDB: one record per Diagram plus one snapshot
 * per Version (ADR-0019). Only the helpers the local store and the library
 * need, promisified.
 */

const DIAGRAMS = 'diagrams';
const VERSIONS = 'versions';

/**
 * @typedef {Object} DiagramRecord
 * @property {string} id
 * @property {string} title
 * @property {string} xml        current content
 * @property {string} createdAt  ISO-8601
 * @property {string} updatedAt  ISO-8601
 *
 * @typedef {Object} VersionRecord
 * @property {string}  id
 * @property {string}  diagramId
 * @property {number}  number    sequential within the Diagram, never reused
 * @property {?string} name      null until renamed: the default "Version N" is localized when read
 * @property {string}  xml
 * @property {string}  savedAt   ISO-8601
 */

/** @returns {Promise<IDBDatabase>} */
export function openDb(name = 'bpmn-canvas') {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(name, 1);

    request.onupgradeneeded = () => {
      const db = request.result;
      db.createObjectStore(DIAGRAMS, { keyPath: 'id' });
      db.createObjectStore(VERSIONS, { keyPath: 'id' }).createIndex('byDiagram', 'diagramId');
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

/** A unique id; crypto.randomUUID needs a secure context, which file:// is not everywhere. */
export function newId() {
  if (typeof crypto !== 'undefined' && crypto.randomUUID) {
    try {
      return crypto.randomUUID();
    } catch (e) {
      // not available in this context
    }
  }

  return Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
}

function promisify(request) {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

/**
 * Runs `work` in one transaction over both stores and resolves with its
 * result once the transaction committed. An error thrown by `work` aborts it.
 *
 * @template T
 * @param {IDBDatabase} db
 * @param {(stores: {diagrams: IDBObjectStore, versions: IDBObjectStore}) => Promise<T>} work
 * @param {'readonly'|'readwrite'} [mode]
 * @returns {Promise<T>}
 */
export function inTransaction(db, work, mode = 'readonly') {
  return new Promise((resolve, reject) => {
    const tx = db.transaction([DIAGRAMS, VERSIONS], mode);
    let result;
    let failure;

    tx.oncomplete = () => (failure ? reject(failure) : resolve(result));
    tx.onerror = () => reject(failure || tx.error);
    tx.onabort = () => reject(failure || tx.error || new Error('Transaction aborted'));

    work({ diagrams: tx.objectStore(DIAGRAMS), versions: tx.objectStore(VERSIONS) })
      .then(value => { result = value; })
      .catch(error => {
        failure = error;
        try { tx.abort(); } catch (e) { /* already finished */ }
      });
  });
}

export const get = (store, key) => promisify(store.get(key));
export const put = (store, value) => promisify(store.put(value));
export const remove = (store, key) => promisify(store.delete(key));
export const getAll = store => promisify(store.getAll());
export const versionsOf = (versions, diagramId) => promisify(versions.index('byDiagram').getAll(diagramId));
