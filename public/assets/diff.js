/*
 * Generic BPMN diff helper, built on top of the vendored bpmn-js-differ
 * (see Dockerfile "vendor" stage — no CDN). Deliberately not tied to
 * "old version vs current": it compares two arbitrary BPMN XML sources, so
 * it can be reused to compare any two Versions (ticket 36), not just an old
 * Version against the current canvas content (ticket 35).
 *
 * Loaded as a native ES module. Its one non-relative import ("bpmn-js-differ",
 * which itself imports "min-dash") is resolved through the import map
 * declared in resources/views/diagrams/show.blade.php.
 */
import { diff } from 'bpmn-js-differ';

/**
 * Parses BPMN XML into the "definitions" object bpmn-js-differ expects, by
 * importing it into a throwaway, never-attached bpmn-js Viewer. Reusing a
 * Viewer (rather than vendoring bpmn-moddle separately) keeps this to a
 * single vendored diff dependency.
 *
 * @param {string} xml
 * @returns {Promise<object>}
 */
async function parseDefinitions(xml) {
  const container = document.createElement('div');
  const viewer = new BpmnJS({ container });

  try {
    await viewer.importXML(xml);

    return viewer.getDefinitions();
  } finally {
    viewer.destroy();
  }
}

/**
 * Compares two BPMN XML documents.
 *
 * @param {string} xmlA
 * @param {string} xmlB
 * @returns {Promise<{added: object, removed: object, changed: object, layoutChanged: object}>}
 */
export async function compareBpmnXml(xmlA, xmlB) {
  const [definitionsA, definitionsB] = await Promise.all([
    parseDefinitions(xmlA),
    parseDefinitions(xmlB),
  ]);

  const changes = diff(definitionsA, definitionsB);

  return {
    added: changes._added || {},
    removed: changes._removed || {},
    changed: changes._changed || {},
    layoutChanged: changes._layoutChanged || {},
  };
}

/**
 * Highlights a diff result on a live bpmn-js instance (typically the
 * Modeler already showing the "new" side of the comparison). Elements only
 * present in the "old" side (removed) can't be marked on this canvas — the
 * caller is expected to list them separately.
 *
 * @returns {() => void} a function that clears every marker this call added.
 */
export function highlightDiff(bpmnJS, result) {
  const canvas = bpmnJS.get('canvas');
  const elementRegistry = bpmnJS.get('elementRegistry');
  const marked = [];

  function mark(ids, className) {
    Object.keys(ids).forEach(id => {
      if (!elementRegistry.get(id)) return; // not rendered on this canvas
      canvas.addMarker(id, className);
      marked.push([id, className]);
    });
  }

  mark(result.added, 'diff-added');
  mark(result.changed, 'diff-changed');

  return function clear() {
    marked.forEach(([id, className]) => {
      if (elementRegistry.get(id)) canvas.removeMarker(id, className);
    });
  };
}

window.BpmnDiff = { compareBpmnXml, highlightDiff };
