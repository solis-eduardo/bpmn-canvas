/*
 * Generic BPMN diff helper, built on top of the vendored bpmn-js-differ
 * (see Dockerfile "vendor" stage — no CDN). Deliberately not tied to
 * "old version vs current": it compares two arbitrary BPMN XML sources, so
 * it can be reused to compare any two Versions (ticket 36), not just an old
 * Version against the current canvas content (ticket 35).
 *
 * An ES module. Its one non-relative import ("bpmn-js-differ", which itself
 * imports "min-dash") is resolved through the import map declared in
 * resources/views/diagrams/show.blade.php, or by the bundler in the standalone
 * build. The bpmn-js constructor is passed in, so this module reads no globals.
 */
import { diff } from 'bpmn-js-differ';

/**
 * Parses BPMN XML into the "definitions" object bpmn-js-differ expects, by
 * importing it into a throwaway, never-attached bpmn-js Viewer. Reusing a
 * Viewer (rather than vendoring bpmn-moddle separately) keeps this to a
 * single vendored diff dependency.
 *
 * @param {string} xml
 * @param {Function} Modeler  the bpmn-js constructor the page uses
 * @returns {Promise<object>}
 */
async function parseDefinitions(xml, Modeler) {
  const container = document.createElement('div');
  const viewer = new Modeler({ container });

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
 * @param {Function} Modeler  the bpmn-js constructor the page uses
 * @returns {Promise<{added: object, removed: object, changed: object, layoutChanged: object}>}
 */
export async function compareBpmnXml(xmlA, xmlB, Modeler) {
  const [definitionsA, definitionsB] = await Promise.all([
    parseDefinitions(xmlA, Modeler),
    parseDefinitions(xmlB, Modeler),
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

// Containers have no shape of their own worth drawing as a "ghost".
const GHOST_SKIPPED_TYPES = ['bpmn:Process', 'bpmn:Collaboration', 'bpmn:Participant', 'bpmn:Lane'];

/**
 * Draws the elements removed in a comparison on the live canvas, as red
 * dashed "ghosts" at the position they had in the old Version. The old XML is
 * rendered in a throwaway, detached bpmn-js Viewer and the SVG of each removed
 * element (plus its labels) is cloned into a layer of the target canvas, so
 * the target diagram itself is never touched. Both sides share the diagram
 * coordinate system, which keeps every ghost where it used to be.
 *
 * @param {object} bpmnJS  the instance showing the "new" side
 * @param {string} oldXml  the "old" side
 * @param {{removed: object}} result  from compareBpmnXml()
 * @param {Function} Modeler  the bpmn-js constructor the page uses
 * @returns {Promise<() => void>} a function that removes every ghost.
 */
export async function renderRemovedGhosts(bpmnJS, oldXml, result, Modeler) {
  const ids = Object.keys(result.removed);

  if (!ids.length) return () => {};

  const oldViewer = new Modeler({ container: document.createElement('div') });
  const added = [];
  let bounds = null;

  try {
    await oldViewer.importXML(oldXml);

    const oldRegistry = oldViewer.get('elementRegistry');
    const canvas = bpmnJS.get('canvas');
    const svg = canvas.getContainer().querySelector('svg');
    // The active (base) layer, not a layer of its own: the canvas frames
    // "fit to viewport" from that layer's bounds, and the ghosts must count.
    const layer = canvas.getActiveLayer();

    // The arrowheads of connections are <marker>s kept in the old SVG's <defs>.
    const oldDefs = oldViewer.get('canvas').getContainer().querySelector('svg defs');
    let defs = svg.querySelector('defs');
    if (!defs) {
      defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
      svg.insertBefore(defs, svg.firstChild);
    }
    if (oldDefs) {
      Array.from(oldDefs.childNodes).forEach(node => {
        const copy = node.cloneNode(true);
        defs.appendChild(copy);
        added.push(copy);
      });
    }

    function grow(element) {
      const points = element.waypoints
        ? element.waypoints.map(({ x, y }) => ({ x, y, width: 0, height: 0 }))
        : [{ x: element.x, y: element.y, width: element.width, height: element.height }];

      points.forEach(({ x, y, width, height }) => {
        bounds = bounds
          ? {
              left: Math.min(bounds.left, x),
              top: Math.min(bounds.top, y),
              right: Math.max(bounds.right, x + width),
              bottom: Math.max(bounds.bottom, y + height),
            }
          : { left: x, top: y, right: x + width, bottom: y + height };
      });
    }

    function ghost(element) {
      const gfx = oldRegistry.getGraphics(element);
      if (!gfx) return;

      grow(element);

      const copy = gfx.cloneNode(true);
      copy.classList.add('diff-removed-ghost');
      copy.querySelectorAll('.djs-hit, .djs-outline, .djs-resizer').forEach(node => node.remove());
      layer.appendChild(copy);
      added.push(copy);
    }

    ids.forEach(id => {
      const element = oldRegistry.get(id);

      if (!element || GHOST_SKIPPED_TYPES.includes(element.type)) return;

      ghost(element);
      (element.labels || []).forEach(ghost);
    });
  } finally {
    oldViewer.destroy();
  }

  const clear = function clear() {
    added.forEach(node => node.remove());
  };
  // Diagram-coordinate bounds of what was drawn ({left, top, right, bottom}), or null.
  clear.bounds = bounds;

  return clear;
}
