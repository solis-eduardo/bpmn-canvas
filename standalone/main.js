/*
 * The standalone build's entry (ADR-0019). The pages are the server build's own
 * routes, rendered at build time by `php artisan standalone:render` (one set per
 * language, in ./pages), so this file only
 *   - picks the page for the hash route (`#/diagrams`, `#/diagrams/<id>`, ...),
 *   - fills in what lives in the browser instead of the server: the Diagram
 *     list and the Editor's content,
 *   - turns the page's links and forms into hash navigation, and
 *   - wires the shared Editor core.
 * A change of route reloads the page, so each load wires one Editor to one Diagram.
 */
import BpmnModeler from 'bpmn-js/lib/Modeler';
import 'bpmn-js/dist/assets/diagram-js.css';
import 'bpmn-js/dist/assets/bpmn-js.css';
import 'bpmn-js/dist/assets/bpmn-font/css/bpmn-embedded.css';
import './standalone.css';

import { errorMessage, setupPanels, toast } from '../public/assets/common.js';
import { createEditor } from '../public/assets/editor-core.js';
import { createTranslator } from '../public/assets/translator.js';
import { createVersionHistory } from '../public/assets/versions.js';
import en from '../public/assets/lang/en.json';
import es from '../public/assets/lang/es.json';
import pt_BR from '../public/assets/lang/pt_BR.json';
import { createLinting } from '../resources/js/editor-lint.js';
import { openDb } from './db.js';
import { createLocalServer, installLocalServer } from './local-server.js';
import { createDiagram, getDiagram, listDiagrams } from './library.js';
import { createLocalDiagramStore } from './local-store.js';

const PAGES = import.meta.glob('./pages/*/*.html', { query: '?raw', import: 'default', eager: true });

const MESSAGES = { en, pt_BR, es };
const LOCALE_KEY = 'bpmn-canvas.locale';

// Where the rendered pages' links point, and the tokens standing for the Diagram they were rendered for.
const ORIGIN = 'http://standalone.invalid';
const SLUG_TOKEN = '__slug__';
const TITLE_TOKEN = '__title__';

// Server path -> page, in matching order.
const ROUTES = [
  [/^\/home$/, 'home'],
  [/^\/diagrams$/, 'library'],
  [/^\/diagrams\/create$/, 'create'],
  [/^\/diagrams\/([^/]+)\/edit$/, 'edit'],
  [/^\/diagrams\/([^/]+)$/, 'editor'],
  [/^\/workspaces$/, 'workspaces'],
  [/^\/admin\/users$/, 'users'],
  [/^\/account$/, 'account'],
  [/^\/notifications\/history$/, 'notifications'],
];

const $ = id => document.getElementById(id);
const escapeHtml = text => Object.assign(document.createElement('span'), { textContent: text }).innerHTML;

function pickLocale() {
  let stored = null;
  try {
    stored = localStorage.getItem(LOCALE_KEY);
  } catch (e) {
    // storage can be blocked
  }
  if (stored in MESSAGES) return stored;

  const browser = (navigator.language || 'en').toLowerCase();
  if (browser.startsWith('pt')) return 'pt_BR';

  return browser.startsWith('es') ? 'es' : 'en';
}

const locale = pickLocale();
const t = createTranslator(MESSAGES[locale]);
const htmlLocale = locale.replace('_', '-');

function resolve(path) {
  for (const [pattern, page] of ROUTES) {
    const match = path.match(pattern);
    if (match) return { page, id: match[1] ? decodeURIComponent(match[1]) : null };
  }

  return { page: 'library', id: null };
}

const { page, id } = resolve(location.hash.slice(1) || '/diagrams');

let db;
try {
  db = await openDb();
} catch (e) {
  // Private windows and blocked site data can refuse IndexedDB.
  console.error(e);
}

const diagram = id && db ? await getDiagram(db, id) : null;
if (id && !diagram) {
  location.hash = '#/diagrams';
  throw new Error('No such Diagram'); // the hash change reloads the page
}

// The server's own page for this route and language replaces the empty shell in index.html.
let html = PAGES[`./pages/${locale}/${page}.html`];
if (diagram) html = html.replaceAll(SLUG_TOKEN, encodeURIComponent(diagram.id)).replaceAll(TITLE_TOKEN, escapeHtml(diagram.title));

const rendered = new DOMParser().parseFromString(html, 'text/html');
document.documentElement.lang = htmlLocale;
document.documentElement.className = rendered.documentElement.className;
document.title = rendered.title.replaceAll(TITLE_TOKEN, diagram ? diagram.title : '');
document.body.className = rendered.body.className;
Object.assign(document.body.dataset, rendered.body.dataset);
document.body.replaceChildren(...rendered.body.childNodes);
// The server's scripts read the CSRF token from the head.
document.head.append(...rendered.head.querySelectorAll('meta[name="csrf-token"]'));

// Tom Select, for the pages' pickers, and the notification bell: the server build's own scripts.
// The bell asks the server for its count and list, which the local server answers.
installLocalServer(createLocalServer(ORIGIN));
await import('../resources/js/app.js');
await import('../public/assets/notifications.js');

// What the server would do on the paths the pages link to or post to.
function pathOf(url) {
  const target = new URL(url, location.href);

  return target.origin === ORIGIN ? target : null;
}

function go(path) {
  location.hash = '#' + path;
}

function unavailable() {
  toast(t('standalone.screen_unavailable'), true);
}

document.addEventListener('click', event => {
  const link = event.target.closest('a[href]');
  const target = link && pathOf(link.href);
  if (!target || event.defaultPrevented) return;

  event.preventDefault();
  if (link.dataset.sortColumn) return; // handled by the listing

  const known = ROUTES.some(([pattern]) => pattern.test(target.pathname));
  if (known) go(target.pathname);
  else unavailable();
});

document.addEventListener('submit', async event => {
  const form = event.target;
  const action = pathOf(form.action);
  if (!action || event.defaultPrevented) return;

  event.preventDefault();
  const language = action.pathname.match(/^\/locale\/([A-Za-z_]+)$/)?.[1];

  if (language in MESSAGES) {
    try {
      localStorage.setItem(LOCALE_KEY, language);
    } catch (e) {
      // not remembered, but the page still reloads in the chosen language
    }
    location.reload();
  } else if (form.method.toLowerCase() === 'post' && action.pathname === '/diagrams' && db) {
    const title = new FormData(form).get('title')?.toString().trim();
    go('/diagrams/' + encodeURIComponent(await createDiagram(db, { title: title || t('standalone.untitled') })));
  } else if (form.method.toLowerCase() === 'get' && action.pathname === '/diagrams') {
    // the listing's search, handled by startLibrary()
  } else {
    unavailable();
  }
});

window.addEventListener('hashchange', () => location.reload());

// The header link of the current screen, as the server marks it.
document.querySelectorAll('header nav a[href]').forEach(link => {
  const target = pathOf(link.href);
  const linked = target && resolve(target.pathname).page;
  const here = linked === page || (linked === 'library' && ['create', 'edit', 'editor'].includes(page));

  link.classList.toggle('bg-gray-100', here);
  link.classList.toggle('text-gray-900', here);
});

// The listing: the server's own table, with the Diagrams stored in this browser as its rows.
async function startLibrary() {
  const tbody = document.querySelector('#diagram-search-results tbody');
  const emptyRow = tbody.innerHTML; // the listing's own "no diagrams" row
  const withRow = new DOMParser().parseFromString(PAGES[`./pages/${locale}/library-row.html`], 'text/html');
  const rowTemplate = withRow.querySelector('#diagram-search-results tbody tr').outerHTML;
  const state = { query: '', column: 'title', direction: 'asc' };

  function rowFor(stored) {
    const holder = document.createElement('tbody');
    holder.innerHTML = rowTemplate
      .replaceAll(SLUG_TOKEN, encodeURIComponent(stored.id))
      .replaceAll(TITLE_TOKEN, escapeHtml(stored.title));
    const row = holder.firstElementChild;

    const date = new Date(stored.updatedAt);
    const time = row.querySelector('time');
    if (time) {
      time.dateTime = date.toISOString();
      time.removeAttribute('title');
      time.textContent = date.toLocaleString(htmlLocale, { dateStyle: 'short', timeStyle: 'short' });
    }

    return row;
  }

  async function render() {
    let stored;

    try {
      stored = await listDiagrams(db);
    } catch (e) {
      console.error(e);
      toast(errorMessage(e, t('standalone.diagrams_load_failed')), true);
      return;
    }

    const query = state.query.trim().toLowerCase();
    const shown = stored
      .filter(item => item.title.toLowerCase().includes(query))
      .sort((a, b) => {
        const order = state.column === 'title' ? a.title.localeCompare(b.title, htmlLocale) : new Date(a.updatedAt) - new Date(b.updatedAt);

        return state.direction === 'asc' ? order : -order;
      });

    if (shown.length === 0) {
      tbody.innerHTML = emptyRow;
      return;
    }

    tbody.replaceChildren(...shown.map(rowFor));
  }

  // The listing's search and sort links work on what is stored here.
  document.querySelector('form[action$="/diagrams"][method="GET"]')?.addEventListener('submit', event => {
    state.query = new FormData(event.target).get('q')?.toString() ?? '';
    render();
  });
  document.querySelectorAll('[data-sort-column]').forEach(link => link.addEventListener('click', () => {
    const column = link.dataset.sortColumn;
    state.direction = state.column === column && state.direction === 'asc' ? 'desc' : (column === 'updated_at' ? 'desc' : 'asc');
    state.column = column;
    render();
  }));

  await render();
}

// The Editor: the shared core on this browser's storage.
function startEditor() {
  const editor = createEditor({
    container: '#canvas',
    store: createLocalDiagramStore({ db, diagramId: diagram.id, t }),
    t,
    timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    Modeler: BpmnModeler,
    lint: createLinting(),
  });

  setupPanels();
  createVersionHistory({ editor });
}

if (db && page === 'library') {
  await startLibrary();
} else if (db && page === 'editor') {
  startEditor();
}
