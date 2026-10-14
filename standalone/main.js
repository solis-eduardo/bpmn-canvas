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
import enPage from './pages/en.html?raw';
import esPage from './pages/es.html?raw';
import ptPage from './pages/pt_BR.html?raw';
import en from '../public/assets/lang/en.json';
import es from '../public/assets/lang/es.json';
import pt_BR from '../public/assets/lang/pt_BR.json';
import { openDb } from './db.js';
import {
  createDiagram, deleteDiagram, exportAll, exportDiagram, fileNameOf, getDiagram, importBpmn, listDiagrams, titleFromFileName,
} from './library.js';
import { createLocalDiagramStore } from './local-store.js';

// `page` is what `php artisan standalone:render` rendered from the shared Blade views, in that language.
const LOCALES = {
  en: { messages: en, page: enPage, name: 'English' },
  pt_BR: { messages: pt_BR, page: ptPage, name: 'Português (Brasil)' },
  es: { messages: es, page: esPage, name: 'Español' },
};
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

// The page rendered for this language replaces the empty shell in index.html.
const rendered = new DOMParser().parseFromString(LOCALES[locale].page, 'text/html');
document.body.className = rendered.body.className;
Object.assign(document.body.dataset, rendered.body.dataset);
document.body.replaceChildren(...rendered.body.childNodes);

// Tom Select, for the listing's filters: the server build's own script.
await import('../resources/js/app.js');

const SCREENS = ['home', 'workspaces', 'users', 'account'];

function route() {
  const match = location.hash.match(/^#\/diagram\/([^/]+)$/);
  if (match) return { diagramId: decodeURIComponent(match[1]) };

  if (/^#\/diagram\/[^/]+\/edit$/.test(location.hash)) return { screen: 'diagram-edit' };
  if (location.hash === '#/diagrams/new') return { screen: 'diagram-create' };

  const screen = location.hash.match(/^#\/([a-z]+)$/)?.[1];

  return SCREENS.includes(screen) ? { screen } : {};
}

// Marks the header link of the current screen, as the server build does.
function markCurrentNav(name) {
  document.querySelector(`[data-nav="${name}"]`)?.classList.add('bg-gray-100', 'text-gray-900');
}

// A screen of the server build that the standalone page does not have yet.
function showPlaceholder(screen) {
  const titles = { 'diagram-create': t('messages.create_diagram'), 'diagram-edit': t('messages.edit') };
  const current = screen in titles ? 'diagrams' : screen;

  $('placeholder-title').textContent = titles[screen] ?? document.querySelector(`[data-nav="${screen}"]`).textContent.trim();
  $('placeholder').hidden = false;
  markCurrentNav(current);
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

const escapeHtml = text => Object.assign(document.createElement('span'), { textContent: text }).innerHTML;

async function startLibrary(db) {
  const tbody = document.querySelector('#diagram-search-results tbody');
  const emptyRow = tbody.innerHTML; // the listing's own "no diagrams" row
  const rowTemplate = $('diagram-row').innerHTML;
  const state = { query: '', column: 'title', direction: 'asc' };
  $('library').hidden = false;

  function goToDiagram(id) {
    location.hash = '#/diagram/' + encodeURIComponent(id);
  }

  // The listing's row, from the server build's markup, filled in for one Diagram.
  function rowFor(diagram) {
    const holder = document.createElement('tbody');
    holder.innerHTML = rowTemplate
      .replaceAll('__slug__', encodeURIComponent(diagram.id))
      .replaceAll('__title__', escapeHtml(diagram.title));
    const row = holder.firstElementChild;

    const date = new Date(diagram.updatedAt);
    const time = row.querySelector('time[data-timestamp]');
    time.dateTime = date.toISOString();
    time.textContent = date.toLocaleString(htmlLocale, { dateStyle: 'short', timeStyle: 'short' });

    // Export and delete, which the server build's listing doesn't have.
    const exportButton = button(t('standalone.export'));
    const deleteButton = button(t('messages.delete'), 'destructive');
    row.querySelector('td:last-child > div').append(exportButton, deleteButton);

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

    return row;
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

    const query = state.query.trim().toLowerCase();
    const shown = diagrams
      .filter(diagram => diagram.title.toLowerCase().includes(query))
      .sort((a, b) => {
        const order = state.column === 'title' ? a.title.localeCompare(b.title, htmlLocale) : new Date(a.updatedAt) - new Date(b.updatedAt);

        return state.direction === 'asc' ? order : -order;
      });

    if (shown.length === 0) {
      tbody.innerHTML = emptyRow;
      if (query) tbody.querySelector('td').textContent = t('messages.no_diagrams_match_search');
      return;
    }

    tbody.replaceChildren(...shown.map(rowFor));
  }

  // The listing's search and sort links work on what is stored here.
  document.querySelector('form[action="#/"]').addEventListener('submit', event => {
    event.preventDefault();
    state.query = new FormData(event.target).get('q') ?? '';
    render();
  });
  document.querySelectorAll('[data-sort-column]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault();
    const column = link.dataset.sortColumn;
    state.direction = state.column === column && state.direction === 'asc' ? 'desc' : (column === 'updated_at' ? 'desc' : 'asc');
    state.column = column;
    render();
  }));

  // Creating a Diagram has no screen of its own here yet: ask for the title.
  document.querySelector('a[href="#/diagrams/new"]').addEventListener('click', async event => {
    event.preventDefault();
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
