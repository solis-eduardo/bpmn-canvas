/*
 * The standalone build's list of Diagrams (ADR-0019): create, delete and open
 * them, import a `.bpmn` as a new Diagram, export one as `.bpmn` and all of
 * them as a `.zip` backup. This is not part of DiagramStore: only the
 * standalone build needs it.
 */
import { strToU8, zipSync } from 'fflate';
import { get, getAll, inTransaction, newId, put, remove, versionsOf } from './db.js';
import { recordVersion } from './local-store.js';

export const EMPTY_DIAGRAM = `<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL" xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI" xmlns:dc="http://www.omg.org/spec/DD/20100524/DC" id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" />
  </bpmn:process>
  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">
      <bpmndi:BPMNShape id="StartEvent_1_di" bpmnElement="StartEvent_1">
        <dc:Bounds x="180" y="160" width="36" height="36" />
      </bpmndi:BPMNShape>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>
`;

/**
 * @param {IDBDatabase} db
 * @returns {Promise<{id: string, title: string, createdAt: string, updatedAt: string}[]>} newest change first
 */
export async function listDiagrams(db) {
  const diagrams = await inTransaction(db, ({ diagrams }) => getAll(diagrams));

  return diagrams
    .map(({ id, title, createdAt, updatedAt }) => ({ id, title, createdAt, updatedAt }))
    .sort((a, b) => b.updatedAt.localeCompare(a.updatedAt));
}

export function getDiagram(db, id) {
  return inTransaction(db, ({ diagrams }) => get(diagrams, id));
}

/**
 * Creates a Diagram holding `xml` (an empty process by default). Its history
 * starts empty: the first save is Version 1.
 *
 * @returns {Promise<string>} the new Diagram's id
 */
export async function createDiagram(db, { title, xml = EMPTY_DIAGRAM, now = () => new Date() }) {
  const at = now().toISOString();
  const id = newId();

  await inTransaction(db, ({ diagrams }) => put(diagrams, { id, title, xml, createdAt: at, updatedAt: at }), 'readwrite');

  return id;
}

/** Removes a Diagram together with all of its Versions. */
export async function deleteDiagram(db, id) {
  await inTransaction(db, async ({ diagrams, versions }) => {
    for (const version of await versionsOf(versions, id)) await remove(versions, version.id);
    await remove(diagrams, id);
  }, 'readwrite');
}

/** "Order approval.bpmn" -> "Order approval" */
export function titleFromFileName(fileName) {
  return fileName.replace(/\.(bpmn|xml)$/i, '').trim();
}

/**
 * Imports BPMN XML (e.g. a `.bpmn` exported by the server build) as a new
 * Diagram. The content is unchanged and becomes Version 1.
 *
 * @param {IDBDatabase} db
 * @param {{title: string, xml: string, now?: () => Date}} file
 * @returns {Promise<string>} the new Diagram's id
 * @throws when the text isn't a BPMN document
 */
export async function importBpmn(db, { title, xml, now = () => new Date() }) {
  if (!/<([\w-]+:)?definitions[\s>]/.test(xml)) throw new Error('Not a BPMN document');

  const at = now();
  const id = newId();
  const diagram = { id, title, xml, createdAt: at.toISOString(), updatedAt: at.toISOString() };

  // One transaction: a Diagram never exists without its Version 1.
  await inTransaction(db, async stores => {
    await put(stores.diagrams, diagram);
    await recordVersion(stores, diagram, xml, at);
  }, 'readwrite');

  return id;
}

/** A file name safe on every OS, from a Diagram's title. */
export function fileNameOf(title) {
  const base = title.replace(/[\\/:*?"<>|\u0000-\u001f]/g, '_').trim() || 'diagram';

  return base + '.bpmn';
}

/** @returns {Promise<{fileName: string, xml: string}>} */
export async function exportDiagram(db, id) {
  const diagram = await getDiagram(db, id);
  if (!diagram) throw new Error('Unknown diagram ' + id);

  return { fileName: fileNameOf(diagram.title), xml: diagram.xml };
}

/**
 * Every Diagram as a `.bpmn` in one `.zip`, the backup for data that lives in
 * the browser profile. Titles that collide get " (2)", " (3)", ...
 *
 * @returns {Promise<Uint8Array>}
 */
export async function exportAll(db) {
  const diagrams = await inTransaction(db, ({ diagrams }) => getAll(diagrams));
  const files = {};

  for (const diagram of diagrams.sort((a, b) => a.createdAt.localeCompare(b.createdAt))) {
    const fileName = fileNameOf(diagram.title);
    let candidate = fileName;

    for (let n = 2; candidate in files; n += 1) candidate = fileName.replace(/\.bpmn$/, ` (${n}).bpmn`);

    files[candidate] = strToU8(diagram.xml);
  }

  return zipSync(files);
}
