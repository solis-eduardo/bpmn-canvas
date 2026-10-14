import 'fake-indexeddb/auto';
import { describe, expect, it } from 'vitest';
import { openDb } from '../../standalone/db.js';
import { createDiagram } from '../../standalone/library.js';
import { createLocalDiagramStore } from '../../standalone/local-store.js';
import { describeDiagramStoreContract } from './diagram-store-contract.js';

const t = (key, replacements = {}) => key + JSON.stringify(replacements);
let counter = 0;

async function makeStore(initialXml = '<definitions id="initial"/>') {
  const db = await openDb('contract-' + counter++);
  const diagramId = await createDiagram(db, { title: 'Test', xml: initialXml });

  return { db, diagramId, store: createLocalDiagramStore({ db, diagramId, t }) };
}

describeDiagramStoreContract('LocalDiagramStore', async () => (await makeStore()).store);

describe('LocalDiagramStore', () => {
  it('loads the initial content of a Diagram without any Version', async () => {
    const { store } = await makeStore('<definitions id="new"/>');

    expect(await store.load()).toBe('<definitions id="new"/>');
    expect(await store.versions()).toEqual([]);
  });

  it('names Versions "Version N" through t until they are renamed', async () => {
    const { store } = await makeStore();

    await store.save('<a/>');
    const [version] = await store.versions();

    expect(version.name).toBe('messages.version_number{"number":1}');
    expect(version.author).toBeNull();
    expect(version.provisional).toBe(false);
  });

  it('keeps the Versions of one Diagram apart from another', async () => {
    const { db, store } = await makeStore();
    const otherId = await createDiagram(db, { title: 'Other' });
    const other = createLocalDiagramStore({ db, diagramId: otherId, t });

    await store.save('<a/>');
    await other.save('<b/>');

    expect((await store.versions()).length).toBe(1);
    expect(await other.load()).toBe('<b/>');
    await expect(store.versionContent((await other.versions())[0].id)).rejects.toThrow();
  });

  it('survives reopening the database', async () => {
    const name = 'reopen-' + counter++;
    const db = await openDb(name);
    const diagramId = await createDiagram(db, { title: 'Kept' });
    await createLocalDiagramStore({ db, diagramId, t }).save('<a/>');
    db.close();

    const reopened = createLocalDiagramStore({ db: await openDb(name), diagramId, t });

    expect(await reopened.load()).toBe('<a/>');
    expect((await reopened.versions()).length).toBe(1);
  });
});
