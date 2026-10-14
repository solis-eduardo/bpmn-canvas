/*
 * The Editor core (ADR-0019), shared by the server and standalone builds:
 * the bpmn-js Modeler, loading and saving through a DiagramStore, and the
 * unsaved-changes state. It knows nothing about Locks, URLs or CSRF; the
 * page around it (e.g. the Lock session) works only through the API that
 * createEditor() returns.
 */
import { toast, setupZoom, errorMessage, chooseOption } from './common.js';
import { resolveSaveConflict } from './save-conflict.js';

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
 *   on: (event: 'dirty'|'saved'|'saveFailed'|'compare'|'forked', handler: Function) => void,
 * }}
 *   `dirty` handlers get the new state whenever it changes; `saved` handlers
 *   run after each successful save; `saveFailed` handlers get the error and
 *   return true when they handled it, which replaces the generic save-failure
 *   notice. `compare` handlers get the id of the newer Version a conflicting
 *   save ran into and show how the canvas differs from it (the dialog offers
 *   "compare" only when one is registered); `forked` handlers get the Fork
 *   ({url}) the canvas was saved as, before the page moves to it. revision()
 *   counts edits, so a caller can tell whether the content changed without serializing it. markClean() drops the unsaved state
 *   without saving, once the content was kept or discarded some other way.
 */
export function createEditor({ container, store, t, timeZone, Modeler }) {
  const modeler = new Modeler({ container });
  const handlers = { dirty: [], saved: [], saveFailed: [], compare: [], forked: [] };
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

  // Stores the canvas as a new Version; resolves to whether it was stored.
  async function persist(options) {
    try {
      await store.save(await currentXml(), options);

      return true;
    } catch (e) {
      if (e.conflict) return resolveConflict(e);

      console.error(e);
      // A listener that handles the failure (a lost Lock) returns true and
      // takes over the notice; a failing listener counts as not handled.
      const handled = handlers.saveFailed.some(handler => {
        try {
          return handler(e) === true;
        } catch (listenerError) {
          console.error(listenerError);
          return false;
        }
      });
      if (!handled) toast(errorMessage(e, t('messages.diagram_save_failed')), true);

      return false;
    }
  }

  // Someone saved a newer Version after this canvas was loaded: the User
  // settles it (see resolveSaveConflict). Resolves to whether it was stored.
  function resolveConflict(conflict) {
    return resolveSaveConflict({
      t,
      choose: chooseOption,
      reload: () => load(),
      overwrite: () => persist({ overwrite: true }),
      compare: handlers.compare.length
        ? () => compareWithNewer(conflict.currentVersion)
        : null,
      fork: store.fork ? forkLocalEdits : null,
    });
  }

  // Shows how the local edits differ from the newer Version; the canvas keeps
  // them, and saving again raises the conflict again.
  async function compareWithNewer(versionId) {
    const done = await attempt(
      () => Promise.all(handlers.compare.map(handler => handler(versionId))),
      'messages.version_compare_failed',
    );
    if (done) toast(t('messages.conflict_compare_hint'));
  }

  // The Fork starts from what is on the canvas, not from the saved copy, so
  // the local edits are not lost. They now live in the Fork: leaving is clean.
  async function forkLocalEdits() {
    let fork;
    const done = await attempt(async () => { fork = await store.fork(await currentXml()); }, 'messages.conflict_fork_failed');
    if (!done) return;

    setDirty(false);
    await Promise.all(handlers.forked.map(async handler => {
      try {
        await handler(fork);
      } catch (e) {
        console.error(e);
      }
    }));
    location.assign(fork.url);
  }

  async function save() {
    if (!(await persist())) return;

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
