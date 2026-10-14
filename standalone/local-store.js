/*
 * LocalDiagramStore: the standalone build's DiagramStore (ADR-0019), on
 * IndexedDB. It re-implements only the Version rules the core needs:
 * sequential numbers, the default name "Version N" (localized through `t`),
 * restore as Version N+1 and numbers that are never reused. There is no author
 * and no provisional Version here, so nothing can be deleted.
 */
import { get, inTransaction, newId, put, versionsOf } from './db.js';

// Version numbers never repeat: the next one is one past the highest recorded.
async function nextNumber(stores, diagramId) {
  const all = await versionsOf(stores.versions, diagramId);

  return all.reduce((highest, version) => Math.max(highest, version.number), 0) + 1;
}

/**
 * Makes `xml` the Diagram's content and records it as its next Version, inside
 * the caller's transaction.
 *
 * @param {{diagrams: IDBObjectStore, versions: IDBObjectStore}} stores
 * @param {import('./db.js').DiagramRecord} diagram
 * @param {string} xml
 * @param {Date} at
 */
export async function recordVersion(stores, diagram, xml, at) {
  const savedAt = at.toISOString();

  await put(stores.versions, { id: newId(), diagramId: diagram.id, number: await nextNumber(stores, diagram.id), name: null, xml, savedAt });
  await put(stores.diagrams, { ...diagram, xml, updatedAt: savedAt });
}

/**
 * @param {Object} options
 * @param {IDBDatabase} options.db  from openDb()
 * @param {string} options.diagramId
 * @param {(key: string, replacements?: Object) => string} options.t
 * @param {() => Date} [options.now]
 */
export function createLocalDiagramStore({ db, diagramId, t, now = () => new Date() }) {
  async function diagramOf(stores) {
    const diagram = await get(stores.diagrams, diagramId);
    if (!diagram) throw new Error('Unknown diagram ' + diagramId);

    return diagram;
  }

  async function versionOf(stores, id) {
    const version = await get(stores.versions, id);
    if (!version || version.diagramId !== diagramId) throw new Error('Unknown version ' + id);

    return version;
  }

  return {
    async load() {
      return inTransaction(db, async stores => (await diagramOf(stores)).xml);
    },

    async save(xml) {
      await inTransaction(db, async stores => recordVersion(stores, await diagramOf(stores), xml, now()), 'readwrite');
    },

    async versions() {
      return inTransaction(db, async stores => {
        const versions = (await versionsOf(stores.versions, diagramId)).sort((a, b) => b.number - a.number);

        return versions.map((version, index) => ({
          id: version.id,
          number: version.number,
          name: version.name ?? t('messages.version_number', { number: version.number }),
          savedAt: version.savedAt,
          author: null,
          provisional: false,
          current: index === 0,
        }));
      });
    },

    async versionContent(id) {
      return inTransaction(db, async stores => (await versionOf(stores, id)).xml);
    },

    async restoreVersion(id) {
      await inTransaction(db, async stores => {
        const version = await versionOf(stores, id);
        await recordVersion(stores, await diagramOf(stores), version.xml, now());
      }, 'readwrite');
    },

    async renameVersion(id, name) {
      await inTransaction(db, async stores => {
        await put(stores.versions, { ...(await versionOf(stores, id)), name });
      }, 'readwrite');
    },

    // Nothing here is provisional, so these only exist to honour the contract.
    async promoteVersion(id) {
      throw new Error('Version ' + id + ' is not provisional');
    },

    async deleteVersion(id) {
      throw new Error('Version ' + id + ' is not provisional');
    },
  };
}
