/*
 * LocalDiagramStore: the standalone build's DiagramStore (ADR-0018), on
 * IndexedDB. It re-implements only the Version rules the core needs:
 * sequential numbers, the default name "Version N" (localized through `t`),
 * restore as Version N+1 and soft delete keeping numbers stable. There is no
 * author and no provisional Version here.
 */
import { get, inTransaction, newId, put, versionsOf } from './db.js';

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

  async function liveVersions(stores) {
    return (await versionsOf(stores.versions, diagramId)).filter(version => !version.deleted);
  }

  // Version numbers count deleted ones too, so a number is never reused.
  async function nextNumber(stores) {
    const all = await versionsOf(stores.versions, diagramId);

    return all.reduce((highest, version) => Math.max(highest, version.number), 0) + 1;
  }

  // Makes `xml` the Diagram's content and records it as a new Version.
  async function record(stores, diagram, xml) {
    const savedAt = now().toISOString();

    await put(stores.versions, { id: newId(), diagramId, number: await nextNumber(stores), name: null, xml, savedAt });
    await put(stores.diagrams, { ...diagram, xml, updatedAt: savedAt });
  }

  async function versionOf(stores, id) {
    const version = await get(stores.versions, id);
    if (!version || version.diagramId !== diagramId || version.deleted) throw new Error('Unknown version ' + id);

    return version;
  }

  return {
    async load() {
      return inTransaction(db, async stores => (await diagramOf(stores)).xml);
    },

    async save(xml) {
      await inTransaction(db, async stores => record(stores, await diagramOf(stores), xml), 'readwrite');
    },

    async versions() {
      return inTransaction(db, async stores => {
        const versions = (await liveVersions(stores)).sort((a, b) => b.number - a.number);

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
        await record(stores, await diagramOf(stores), version.xml);
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
