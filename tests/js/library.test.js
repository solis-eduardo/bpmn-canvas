import 'fake-indexeddb/auto';
import { unzipSync, strFromU8 } from 'fflate';
import { describe, expect, it } from 'vitest';
import { openDb } from '../../standalone/db.js';
import {
  createDiagram, deleteDiagram, exportAll, exportDiagram, fileNameOf, importBpmn, listDiagrams, titleFromFileName,
} from '../../standalone/library.js';
import { createLocalDiagramStore } from '../../standalone/local-store.js';

let counter = 0;
const freshDb = () => openDb('library-' + counter++);
const at = iso => () => new Date(iso);

describe('library', () => {
  it('lists Diagrams, most recently changed first', async () => {
    const db = await freshDb();
    await createDiagram(db, { title: 'Old', now: at('2026-01-01T00:00:00Z') });
    const recentId = await createDiagram(db, { title: 'Recent', now: at('2026-02-01T00:00:00Z') });

    const list = await listDiagrams(db);

    expect(list.map(d => d.title)).toEqual(['Recent', 'Old']);
    expect(list[0].id).toBe(recentId);
    expect(list[0]).not.toHaveProperty('xml');
  });

  it('moves a Diagram up the list when it is saved', async () => {
    const db = await freshDb();
    const oldId = await createDiagram(db, { title: 'Old', now: at('2026-01-01T00:00:00Z') });
    await createDiagram(db, { title: 'Newer', now: at('2026-02-01T00:00:00Z') });

    await createLocalDiagramStore({ db, diagramId: oldId, t: k => k }).save('<a/>');

    expect((await listDiagrams(db)).map(d => d.title)).toEqual(['Old', 'Newer']);
  });

  it('deletes a Diagram with its Versions', async () => {
    const db = await freshDb();
    const id = await createDiagram(db, { title: 'Gone' });
    const store = createLocalDiagramStore({ db, diagramId: id, t: k => k });
    await store.save('<a/>');

    await deleteDiagram(db, id);

    expect(await listDiagrams(db)).toEqual([]);
    await expect(store.versions()).resolves.toEqual([]);
    await expect(store.load()).rejects.toThrow();
  });

  it('imports a .bpmn unchanged, as Version 1', async () => {
    const db = await freshDb();
    const xml = '<?xml version="1.0"?><bpmn:definitions xmlns:bpmn="x" id="d"/>';

    const id = await importBpmn(db, { title: titleFromFileName('Order approval.bpmn'), xml });
    const store = createLocalDiagramStore({ db, diagramId: id, t: k => k });

    expect((await listDiagrams(db))[0].title).toBe('Order approval');
    expect(await store.load()).toBe(xml);
    expect((await store.versions()).map(v => v.number)).toEqual([1]);
  });

  it('refuses to import something that is not BPMN', async () => {
    const db = await freshDb();

    await expect(importBpmn(db, { title: 'x', xml: '<html/>' })).rejects.toThrow();
    expect(await listDiagrams(db)).toEqual([]);
  });

  it('exports a Diagram as a .bpmn with its current content', async () => {
    const db = await freshDb();
    const id = await createDiagram(db, { title: 'A/B: plan', xml: '<x/>' });

    expect(await exportDiagram(db, id)).toEqual({ fileName: 'A_B_ plan.bpmn', xml: '<x/>' });
    expect(fileNameOf('  ')).toBe('diagram.bpmn');
  });

  it('exports all Diagrams as a .zip with one .bpmn each, even with equal titles', async () => {
    const db = await freshDb();
    await createDiagram(db, { title: 'Same', xml: '<one/>', now: at('2026-01-01T00:00:00Z') });
    await createDiagram(db, { title: 'Same', xml: '<two/>', now: at('2026-01-02T00:00:00Z') });
    await createDiagram(db, { title: 'Other', xml: '<three/>', now: at('2026-01-03T00:00:00Z') });

    const files = unzipSync(await exportAll(db));

    expect(Object.keys(files).sort()).toEqual(['Other.bpmn', 'Same (2).bpmn', 'Same.bpmn']);
    expect(strFromU8(files['Same.bpmn'])).toBe('<one/>');
    expect(strFromU8(files['Same (2).bpmn'])).toBe('<two/>');
  });
});
