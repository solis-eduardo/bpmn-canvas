/*
 * The standalone build's entry (ADR-0019): the page around the shared Editor
 * core. A hash route picks the view, and a change of route reloads the page,
 * so each page load wires exactly one Editor to one Diagram.
 *
 *   #/                 the list of Diagrams (library.js)
 *   #/diagram/<id>     the Editor, on a LocalDiagramStore
 */
import BpmnModeler from 'bpmn-js/lib/Modeler';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn-embedded.css';
import './standalone.css';

import { errorMessage, exportBpmn, exportPng, exportSvg, setupDropdowns, setupPanels, toast, download } from '../public/assets/common.js';
import { createEditor } from '../public/assets/editor-core.js';
import { createTranslator } from '../public/assets/translator.js';
import { createVersionHistory } from '../public/assets/versions.js';
import en from '../public/assets/lang/en.json';
import es from '../public/assets/lang/es.json';
import pt_BR from '../public/assets/lang/pt_BR.json';
import { openDb } from './db.js';
import {
  createDiagram, deleteDiagram, exportAll, exportDiagram, fileNameOf, getDiagram, importBpmn, listDiagrams, titleFromFileName,
} from './library.js';
import { createLocalDiagramStore } from './local-store.js';

const LOCALES = { en: { messages: en, name: 'English' }, pt_BR: { messages: pt_BR, name: 'Português (Brasil)' }, es: { messages: es, name: 'Español' } };
const LOCALE_KEY = 'bpmn-canvas.locale';

const $ = id => document.getElementById(id);

function readStoredLocale() {
  try {
    return localStorage.getItem(LOCALE_KEY);
  } catch (e) {
    return null; // storage can be blocked
  }
}

function pickLocale() {
  const stored = readStoredLocale();
  if (stored in LOCALES) return stored;

  const browser = (navigator.language || 'en').toLowerCase();
  if (browser.startsWith('pt')) return 'pt_BR';

  return browser.startsWith('es') ? 'es' : 'en';
}

const locale = pickLocale();
const t = createTranslator(LOCALES[locale].messages);
const htmlLocale = locale.replace('_', '-');

// versions.js formats dates in the page's language.
document.documentElement.lang = locale;
document.title = t('standalone.app_title');

// Static text of index.html: data-i18n sets the text, data-i18n-<attribute> an attribute.
document.querySelectorAll('*').forEach(element => {
  for (const { name, value } of [...element.attributes]) {
    if (name === 'data-i18n') {
      element.textContent = t(value);
    } else if (name.startsWith('data-i18n-')) {
      element.setAttribute(name.slice('data-i18n-'.length), t(value));
    }
  }
});

const SCREENS = ['home', 'workspaces', 'users', 'account'];

function route() {
  const match = location.hash.match(/^#\/diagram\/([^/]+)$/);
  if (match) return { diagramId: decodeURIComponent(match[1]) };

  const screen = location.hash.match(/^#\/([a-z]+)$/)?.[1];

  return SCREENS.includes(screen) ? { screen } : {};
}

// Marks the header link of the current screen, as the server build does.
function markCurrentNav(name) {
  document.querySelector(`[data-nav="${name}"]`)?.classList.add('bg-gray-100', 'text-gray-900');
}

// A screen of the server build that the standalone page does not have yet.
function showPlaceholder(screen) {
  const link = document.querySelector(`[data-nav="${screen}"]`);

  $('placeholder-title').textContent = link.textContent.trim();
  $('placeholder').hidden = false;
  markCurrentNav(screen);
}

// The same classes as the server build's <x-button size="sm">.
const BUTTON_CLASSES = {
  secondary: 'inline-flex items-center justify-center gap-1 whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium border border-gray-300 bg-white text-gray-700 shadow-sm hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500',
  destructive: 'inline-flex items-center justify-center gap-1 whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium bg-red-600 text-white shadow-sm hover:bg-red-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600',
};

function button(text, variant = 'secondary') {
  return Object.assign(document.createElement('button'), { type: 'button', textContent: text, className: BUTTON_CLASSES[variant] });
}

// The language picker lives in the page header, so it works on both screens.
function setupLanguagePicker() {
  const select = $('language');

  Object.entries(LOCALES).forEach(([code, { name }]) => select.add(new Option(name, code, false, code === locale)));
  select.addEventListener('change', () => {
    try {
      localStorage.setItem(LOCALE_KEY, select.value);
    } catch (e) {
      // not remembered, but the page still reloads in the chosen language
    }
    location.reload();
  });
}

async function startLibrary(db) {
  const list = $('library-list');
  $('library').hidden = false;

  function goToDiagram(id) {
    location.hash = '#/diagram/' + encodeURIComponent(id);
  }

  async function render() {
    let diagrams;

    try {
      diagrams = await listDiagrams(db);
    } catch (e) {
      console.error(e);
      toast(errorMessage(e, t('standalone.diagrams_load_failed')), true);
      return;
    }

    list.replaceChildren();
    $('library-empty').hidden = diagrams.length > 0;

    diagrams.forEach(diagram => {
      const row = list.insertRow();
      const title = row.insertCell();
      title.className = 'px-4 py-3 font-medium text-gray-900';
      title.textContent = diagram.title;
      const modified = row.insertCell();
      modified.className = 'px-4 py-3 text-gray-500';
      modified.textContent = new Date(diagram.updatedAt).toLocaleString(htmlLocale, { dateStyle: 'medium', timeStyle: 'short' });

      const actions = row.insertCell();
      actions.className = 'w-px whitespace-nowrap px-4 py-3';
      const openButton = button(t('messages.open_diagram'));
      const deleteButton = button(t('messages.delete'), 'destructive');
      const exportButton = button(t('standalone.export'));
      openButton.addEventListener('click', () => goToDiagram(diagram.id));
      exportButton.addEventListener('click', async () => {
        try {
          const { fileName, xml } = await exportDiagram(db, diagram.id);
          download(new Blob([xml], { type: 'application/xml' }), fileName);
        } catch (e) {
          console.error(e);
          toast(t('standalone.export_failed'), true);
        }
      });
      deleteButton.addEventListener('click', async () => {
        if (!confirm(t('standalone.delete_diagram_confirm', { title: diagram.title }))) return;

        try {
          await deleteDiagram(db, diagram.id);
        } catch (e) {
          console.error(e);
          toast(errorMessage(e, t('standalone.delete_failed')), true);
        }
        await render();
      });
      actions.append(openButton, ' ', exportButton, ' ', deleteButton);
    });
  }

  $('btn-new').addEventListener('click', async () => {
    const title = prompt(t('standalone.new_diagram_title_prompt'), t('standalone.untitled'));
    if (title === null) return;

    goToDiagram(await createDiagram(db, { title: title.trim() || t('standalone.untitled') }));
  });

  $('btn-import').addEventListener('click', () => $('import-file').click());
  $('import-file').addEventListener('change', async event => {
    const [file] = event.target.files;
    event.target.value = '';
    if (!file) return;

    try {
      goToDiagram(await importBpmn(db, { title: titleFromFileName(file.name) || t('standalone.untitled'), xml: await file.text() }));
    } catch (e) {
      console.error(e);
      toast(t('standalone.import_failed'), true);
    }
  });

  $('btn-export-all').addEventListener('click', async () => {
    try {
      download(new Blob([await exportAll(db)], { type: 'application/zip' }), 'bpmn-canvas-backup.zip');
    } catch (e) {
      console.error(e);
      toast(t('standalone.export_failed'), true);
    }
  });

  await render();
}

async function startEditor(db, diagramId) {
  const diagram = await getDiagram(db, diagramId);
  if (!diagram) {
    location.hash = '#/';
    return;
  }

  $('editor-view').hidden = false;
  $('editor-brand').textContent = diagram.title;
  document.title = diagram.title + ' | ' + t('standalone.app_title');

  const editor = createEditor({
    container: '#canvas',
    store: createLocalDiagramStore({ db, diagramId, t }),
    t,
    timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    Modeler: BpmnModeler,
  });

  setupPanels();
  setupDropdowns();
  createVersionHistory({ editor });

  const exporters = { png: exportPng, svg: exportSvg, bpmn: exportBpmn };
  document.querySelectorAll('[data-export]').forEach(button => button.addEventListener('click', async () => {
    try {
      await exporters[button.dataset.export](editor.modeler, diagram.title);
    } catch (e) {
      console.error(e);
      toast(t('standalone.export_failed'), true);
    }
  }));

  // Chromium can save to a chosen file and keep writing to it on every save;
  // elsewhere the button downloads the .bpmn instead.
  let fileHandle = null;

  async function writeToFile() {
    const writable = await fileHandle.createWritable();
    await writable.write(await editor.currentXml());
    await writable.close();
    toast(t('standalone.saved_to_file', { name: fileHandle.name }));
  }

  $('btn-save-to-file').addEventListener('click', async () => {
    try {
      if (!window.showSaveFilePicker) {
        await exportBpmn(editor.modeler, diagram.title);
        return;
      }

      fileHandle = await window.showSaveFilePicker({
        suggestedName: fileNameOf(diagram.title),
        types: [{ description: t('messages.bpmn_file'), accept: { 'application/xml': ['.bpmn'] } }],
      });
      await writeToFile();
    } catch (e) {
      if (e && e.name === 'AbortError') return; // the picker was dismissed

      console.error(e);
      toast(t('standalone.save_to_file_failed'), true);
    }
  });

  editor.on('saved', () => {
    if (!fileHandle) return;

    writeToFile().catch(e => {
      console.error(e);
      toast(t('standalone.save_to_file_failed'), true);
    });
  });
}

window.addEventListener('hashchange', () => location.reload());

const { diagramId, screen } = route();
setupLanguagePicker();
let db;

try {
  db = await openDb();
} catch (e) {
  // Private windows and blocked site data can refuse IndexedDB.
  console.error(e);
  toast(t('standalone.diagrams_load_failed'), true);
}

if (screen) {
  showPlaceholder(screen);
} else if (db && diagramId) {
  markCurrentNav('diagrams');
  await startEditor(db, diagramId);
} else if (db) {
  markCurrentNav('diagrams');
  await startLibrary(db);
}
