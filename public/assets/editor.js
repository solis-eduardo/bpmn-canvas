/*
 * The server build's Editor page: wires the shared Editor core to the
 * Laravel endpoints (HttpDiagramStore) and to the Lock session.
 */
import { createEditor } from './editor-core.js';
import { createHttpDiagramStore, createJsonClient } from './http-store.js';
import { startLockSession } from './lock-session.js';

const body = document.getElementById('editor');
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// Localized UI strings, rendered on the page's data-* attributes.
const t = key => body.dataset[key];

// One JSON client for the page: the store and the Lock session both use it.
const request = createJsonClient({ csrfToken });

const store = createHttpDiagramStore({
  urls: {
    contentUrl: body.dataset.contentUrl,
    saveUrl: body.dataset.saveUrl,
    versionsUrl: body.dataset.versionsUrl,
    versionContentUrlTemplate: body.dataset.versionContentUrlTemplate,
    versionRestoreUrlTemplate: body.dataset.versionRestoreUrlTemplate,
    versionRenameUrlTemplate: body.dataset.versionRenameUrlTemplate,
    versionPromoteUrlTemplate: body.dataset.versionPromoteUrlTemplate,
    versionDeleteUrlTemplate: body.dataset.versionDeleteUrlTemplate,
  },
  request,
});

const editor = createEditor({ container: '#canvas', store, t });

// The other Editor scripts (versions.js, collaborators.js) listen for saves
// on the document.
editor.on('saved', () => document.dispatchEvent(new CustomEvent('diagram:saved')));

// Exposed so the Version history module (versions.js) can reuse this same
// live Modeler/canvas and DiagramStore instead of creating its own.
window.BpmnEditor = { modeler: editor.modeler, store };

startLockSession({
  editor,
  request,
  urls: {
    heartbeat: body.dataset.heartbeatUrl,
    unlock: body.dataset.unlockUrl,
    index: body.dataset.indexUrl,
  },
  isHolder: body.dataset.isLockHolder === '1',
  t,
  banner: document.getElementById('lock-request-banner'),
  releaseButton: document.getElementById('btn-release-lock'),
});
