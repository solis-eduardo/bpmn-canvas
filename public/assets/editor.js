/*
 * The server build's Editor page: wires the shared Editor core to the
 * Laravel endpoints (HttpDiagramStore) and to the Lock session.
 */
import { setupPanels } from './common.js';
import { createEditor } from './editor-core.js';
import { createHttpDiagramStore, createJsonClient } from './http-store.js';
import { startLockSession } from './lock-session.js';
import { createTranslator } from './translator.js';
import { createVersionHistory } from './versions.js';

const body = document.getElementById('editor');
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// One JSON client for the page: the translations, the store and the Lock
// session all use it.
const request = createJsonClient({ csrfToken });

// The core's UI strings: the JSON `php artisan lang:export` writes from lang/,
// for the page's locale. Without it the Editor still starts, showing the keys
// instead of the strings.
async function loadMessages(url) {
  try {
    return await request('GET', url);
  } catch (e) {
    console.error('Could not load ' + url + ' (run "php artisan lang:export")', e);

    return {};
  }
}

const messages = await loadMessages(body.dataset.langUrl);
const t = createTranslator(messages);

// The Lock session is server-only and keeps its strings on data-* attributes.
const lockT = key => body.dataset[key];

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

setupPanels();

const editor = createEditor({
  container: '#canvas',
  store,
  t,
  timeZone: body.dataset.timeZone,
  Modeler: window.BpmnJS,
});

// The server-only scripts (collaborators.js) listen for saves on the document.
editor.on('saved', () => document.dispatchEvent(new CustomEvent('diagram:saved')));

createVersionHistory({ editor, canEdit: body.dataset.canEditVersions === 'true' });

startLockSession({
  editor,
  request,
  urls: {
    heartbeat: body.dataset.heartbeatUrl,
    lock: body.dataset.lockUrl,
    unlock: body.dataset.unlockUrl,
    index: body.dataset.indexUrl,
  },
  isHolder: body.dataset.isLockHolder === '1',
  t: lockT,
  banner: document.getElementById('lock-request-banner'),
  releaseButton: document.getElementById('btn-release-lock'),
  lostBanner: document.getElementById('lock-lost-banner'),
});
