/*
 * The Editor core (ADR-0018), shared by the server and standalone builds:
 * the bpmn-js Modeler, loading and saving through a DiagramStore, and the
 * unsaved-changes state. It knows nothing about Locks, URLs or CSRF; the
 * page around it (e.g. the Lock session) works only through the API that
 * createEditor() returns.
 */

/**
 * @param {Object} options
 * @param {string} options.container  selector of the canvas element
 * @param {Object} options.store  a DiagramStore
 * @param {{savedNotice: string, saveFailedNotice: string, loadFailedNotice: string}} options.notices
 * @returns {{
 *   modeler: Object,
 *   save: () => Promise<void>,
 *   isDirty: () => boolean,
 *   currentXml: () => Promise<string>,
 *   markClean: () => void,
 *   on: (event: 'dirty'|'saved', handler: Function) => void,
 * }}
 *   `dirty` handlers get the new state whenever it changes; `saved` handlers
 *   run after each successful save. markClean() drops the unsaved state
 *   without saving, once the content was kept or discarded some other way.
 */
export function createEditor({ container, store, notices }) {
  const { toast, setupZoom, errorMessage } = window.BpmnCommon;

  const modeler = new BpmnJS({ container });
  const handlers = { dirty: [], saved: [] };
  const dirtyMarker = document.getElementById('dirty');
  let dirty = false;

  setupZoom(modeler);

  function setDirty(value) {
    if (value === dirty) return;

    dirty = value;
    dirtyMarker.classList.toggle('on', value);
    handlers.dirty.forEach(handler => handler(value));
  }

  async function currentXml() {
    return (await modeler.saveXML({ format: true })).xml;
  }

  async function load() {
    try {
      const { warnings } = await modeler.importXML(await store.load());
      if (warnings && warnings.length) console.warn('Import warnings:', warnings);
      modeler.get('canvas').zoom('fit-viewport', 'auto');
      setDirty(false);
    } catch (e) {
      console.error(e);
      toast(errorMessage(e, notices.loadFailedNotice), true);
    }
  }

  async function save() {
    try {
      await store.save(await currentXml());
      setDirty(false);
      handlers.saved.forEach(handler => handler());
      toast(notices.savedNotice);
    } catch (e) {
      console.error(e);
      toast(errorMessage(e, notices.saveFailedNotice), true);
    }
  }

  document.getElementById('btn-save').addEventListener('click', save);
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      save();
    }
  });

  modeler.on('commandStack.changed', () => setDirty(true));
  window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  load();

  return {
    modeler,
    save,
    isDirty: () => dirty,
    currentXml,
    markClean: () => setDirty(false),
    on(event, handler) {
      handlers[event].push(handler);
    },
  };
}
