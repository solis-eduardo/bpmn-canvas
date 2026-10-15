/*
 * The Activity panel (CONTEXT.md, "Activity Log"; ADR-0025): lists the log
 * entries about the open Diagram, newest first, with filters and "load more".
 * Server-only and read-only, so it needs no Lock. Its strings come from the
 * panel's data-labels attribute; every value is inserted with textContent.
 */
import { errorMessage, toggleFloating } from './common.js';
import { createJsonClient } from './http-store.js';

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;

  return node;
}

export function startActivity() {
  const panel = document.getElementById('activity-panel');
  const opener = document.getElementById('btn-activity-entry');
  if (!panel || !opener) return;

  const labels = JSON.parse(panel.dataset.labels);
  const request = createJsonClient({ csrfToken: document.querySelector('meta[name="csrf-token"]').content });
  const formatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'short', timeStyle: 'short', timeZone: panel.dataset.timeZone || undefined });
  const list = document.getElementById('activity-list');
  const filters = document.getElementById('activity-filters');
  const more = document.getElementById('activity-more');
  const errorBox = document.getElementById('activity-error');
  const insideEditor = !!document.getElementById('app');
  let nextPage = null;
  let loading = false;

  more.textContent = labels.loadMore;

  function showError(message) {
    errorBox.textContent = message || '';
    errorBox.hidden = !message;
  }

  function query(page) {
    const params = new URLSearchParams(new FormData(filters));
    [...params.keys()].forEach(key => { if (!params.get(key)) params.delete(key); });
    params.set('page', String(page));

    return params;
  }

  function renderEntry(entry) {
    const item = el('li', 'act-entry');
    const meta = el('div', 'act-meta');
    meta.append(el('strong', '', entry.actor), document.createTextNode(' '), el('time', '', formatter.format(new Date(entry.created_at))));
    item.append(meta, el('div', 'act-action', entry.action));
    if (entry.subject) item.append(el('div', 'act-subject', entry.subject));
    entry.changes.forEach(change => {
      const parts = [change.before, change.after].filter(value => value !== null);
      item.append(el('div', 'act-change', `${change.field}: ${parts.join(' → ')}`));
    });

    return item;
  }

  // Loads one page; page 1 replaces the list, later pages append to it.
  async function load(page) {
    if (loading) return;
    loading = true;
    showError('');
    try {
      const result = await request('GET', `${panel.dataset.activityUrl}?${query(page)}`);
      if (page === 1) list.replaceChildren();
      result.entries.forEach(entry => list.append(renderEntry(entry)));
      if (!list.children.length) {
        const filtered = [...new FormData(filters).values()].some(Boolean);
        list.append(el('li', 'empty', filtered ? labels.noMatch : labels.empty));
      }
      nextPage = result.next_page;
      more.hidden = nextPage === null;
    } catch (e) {
      console.error(e);
      showError(errorMessage(e, labels.loadFailed));
    } finally {
      loading = false;
    }
  }

  filters.addEventListener('submit', event => {
    event.preventDefault();
    load(1);
  });
  more.addEventListener('click', () => load(nextPage));
  // Opening the panel shows the log as it is now. Deferred: the Editor's own toggle handler (common.js) may run after this one.
  opener.addEventListener('click', () => {
    if (!insideEditor) toggleFloating(panel);
    setTimeout(() => { if (!panel.hidden) load(1); });
  });
  if (!insideEditor) panel.querySelector('[data-panel-close]').addEventListener('click', () => { panel.hidden = true; });
}

if (typeof document !== 'undefined') startActivity();
