/*
 * The Editor core (ADR-0019), shared by the server and standalone builds:
 * the bpmn-js Modeler, loading and saving through a DiagramStore, and the
 * unsaved-changes state. It knows nothing about Locks, URLs or CSRF; the
 * page around it (e.g. the Lock session) works only through the API that
 * createEditor() returns.
 */
import { toast, setupZoom, errorMessage } from './common.js';

/**
 * @param {Object} options
 * @param {string} options.container  selector of the canvas element
 * @param {Object} options.store  a DiagramStore
 * @param {(key: string, replacements?: Object) => string} options.t  localized
 *   UI string by lang/ key (see translator.js)
 * @param {string} [options.timeZone]  IANA name Version times are shown in;
 *   the browser's when omitted
 * @param {Function} options.Modeler  the bpmn-js Modeler constructor
 * @returns {{
 *   modeler: Object,
 *   Modeler: Function,
 *   store: Object,
 *   t: Function,
 *   timeZone: ?string,
 *   save: () => Promise<void>,
 *   isDirty: () => boolean,
 *   revision: () => number,
 *   currentXml: () => Promise<string>,
 *   markClean: () => void,
 *   on: (event: 'dirty'|'saved', handler: Function) => void,
 * }}
 *   `dirty` handlers get the new state whenever it changes; `saved` handlers
 *   run after each successful save. revision() counts edits, so a caller can
 *   tell whether the content changed without serializing it. markClean()
 *   drops the unsaved state without saving, once the content was kept or
 *   discarded some other way.
 */
export function createEditor({ container, store, t, timeZone, Modeler }) {
  const modeler = new Modeler({ container });
  const handlers = { dirty: [], saved: [] };
  const dirtyMarker = document.getElementById('dirty');
  let dirty = false;
  let edits = 0;

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

  // Runs one action; on failure logs it and shows the localized notice.
  async function attempt(action, failureKey) {
    try {
      await action();
      return true;
    } catch (e) {
      console.error(e);
      toast(errorMessage(e, t(failureKey)), true);
      return false;
    }
  }

  function load() {
    return attempt(async () => {
      const { warnings } = await modeler.importXML(await store.load());
      if (warnings && warnings.length) console.warn('Import warnings:', warnings);
      modeler.get('canvas').zoom('fit-viewport', 'auto');
      setDirty(false);
    }, 'messages.diagram_load_failed');
  }

  async function save() {
    const saved = await attempt(async () => store.save(await currentXml()), 'messages.diagram_save_failed');
    if (!saved) return;

    setDirty(false);
    // A failing listener must not turn a successful save into a failed one.
    handlers.saved.forEach(handler => {
      try {
        handler();
      } catch (e) {
        console.error(e);
      }
    });
    toast(t('messages.saved'));
  }

  document.getElementById('btn-save').addEventListener('click', save);
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      save();
    }
  });

  modeler.on('commandStack.changed', () => {
    edits += 1;
    setDirty(true);
  });
  window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  load();

  return {
    modeler,
    Modeler,
    store,
    t,
    timeZone,
    save,
    isDirty: () => dirty,
    revision: () => edits,
    currentXml,
    markClean: () => setDirty(false),
    on(event, handler) {
      handlers[event].push(handler);
    },
  };
}
