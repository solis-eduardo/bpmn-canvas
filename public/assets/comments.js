/*
 * The Comments panel (CONTEXT.md, "Comment Thread"; ADR-0024): lists a
 * Diagram's Threads, writes Comments, resolves Threads, and marks the
 * commented elements on the canvas. Server-only: it works on whatever bpmn-js
 * instance the page hands it (the Editor's Modeler or the Viewer), so it needs
 * no Lock. Its strings come from the panel's data-labels attribute; Comment
 * text is only ever inserted with textContent.
 */
import { errorMessage, togglePanel, toast } from './common.js';
import { createJsonClient } from './http-store.js';

const MARKER_TYPE = 'comment-marker';

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text !== undefined) node.textContent = text;

  return node;
}

/**
 * @param {Object} options
 * @param {Object} options.modeler  a bpmn-js Modeler or (Navigated)Viewer, with its diagram imported or about to be
 */
export function startComments({ modeler }) {
  const panel = document.getElementById('comments-panel');
  if (!panel) return;

  const labels = JSON.parse(panel.dataset.labels);
  const baseUrl = panel.dataset.commentsUrl;
  const request = createJsonClient({ csrfToken: document.querySelector('meta[name="csrf-token"]').content });
  const formatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'short', timeStyle: 'short', timeZone: panel.dataset.timeZone || undefined });
  const overlays = modeler.get('overlays');
  const registry = modeler.get('elementRegistry');
  const canvas = modeler.get('canvas');
  const list = document.getElementById('comments-list');
  const form = document.getElementById('comment-form');
  const body = document.getElementById('comment-body');
  const submit = document.getElementById('comment-submit');
  const target = document.getElementById('comment-target');
  const errorBox = document.getElementById('comment-error');
  const insideEditor = !!document.getElementById('app');
  let threads = [];
  let selectedId = null; // the element the next new Thread is about; null means the whole Diagram
  let focusThreadId = null;
  const drafts = new Map(); // unsent replies by Thread id, kept across list reloads

  body.placeholder = labels.placeholder;
  submit.textContent = labels.post;

  function showError(message) {
    errorBox.textContent = message || '';
    errorBox.hidden = !message;
  }

  function nameOf(id) {
    const element = id && registry.get(id);

    return (element && element.businessObject && element.businessObject.name) || id;
  }

  function renderTarget() {
    // An element deleted on the canvas can't anchor a new Thread any more.
    if (selectedId && !registry.get(selectedId)) selectedId = null;
    target.textContent = selectedId ? labels.onElement.replace(':element', nameOf(selectedId)) : labels.onDiagram;
    target.title = selectedId ? '' : labels.hint;
  }

  // Not on the canvas any more AND not in the saved content: the discussion stays, the marker can't.
  const isOrphaned = thread => thread.orphaned && !(thread.element_id && registry.get(thread.element_id));

  function renderMarkers() {
    overlays.remove({ type: MARKER_TYPE });

    const byElement = new Map();
    threads.filter(thread => thread.element_id && registry.get(thread.element_id)).forEach(thread => {
      byElement.set(thread.element_id, [...(byElement.get(thread.element_id) || []), thread]);
    });

    byElement.forEach((elementThreads, elementId) => {
      const open = elementThreads.filter(thread => !thread.resolved);
      const marker = el('button', 'cmt-marker' + (open.length ? '' : ' resolved'), String(open.length || elementThreads.length));
      marker.type = 'button';
      marker.title = labels.showOnCanvas;
      marker.addEventListener('click', () => {
        focusThreadId = (open[0] || elementThreads[0]).id;
        openPanel();
        render();
      });
      overlays.add(elementId, MARKER_TYPE, { position: { top: -12, right: 12 }, html: marker });
    });
  }

  function renderThread(thread) {
    const item = el('li', 'cmt-thread' + (thread.resolved ? ' resolved' : '') + (thread.id === focusThreadId ? ' focus' : ''));
    item.dataset.threadId = String(thread.id);

    const head = el('div', 'cmt-thread-head');
    const where = thread.element_id ? nameOf(thread.element_id) : labels.onDiagram;
    if (thread.element_id && registry.get(thread.element_id)) {
      const link = el('button', 'cmt-link', where);
      link.type = 'button';
      link.title = labels.showOnCanvas;
      link.addEventListener('click', () => reveal(thread.element_id));
      head.append(link);
    } else {
      head.append(el('span', 'cmt-where', where));
    }
    if (isOrphaned(thread)) head.append(el('span', 'cmt-badge orphaned', labels.orphaned));
    if (thread.resolved) head.append(el('span', 'cmt-badge', thread.resolved_by ? labels.resolvedBy.replace(':name', thread.resolved_by) : labels.resolved));
    item.append(head);

    thread.comments.forEach(comment => {
      const entry = el('div', 'cmt-comment');
      const meta = el('div', 'cmt-meta');
      meta.append(el('strong', '', comment.author), document.createTextNode(' '), el('time', '', formatter.format(new Date(comment.created_at))));
      entry.append(meta, el('p', 'cmt-text', comment.body));
      item.append(entry);
    });

    item.append(replyForm(thread));

    return item;
  }

  function replyForm(thread) {
    const wrapper = el('form', 'cmt-reply');
    const input = el('input');
    input.type = 'text';
    input.maxLength = Number(panel.dataset.maxLength);
    input.value = drafts.get(thread.id) || '';
    input.addEventListener('input', () => drafts.set(thread.id, input.value));
    input.required = true;
    input.placeholder = labels.placeholder;
    const reply = el('button', '', labels.reply);
    reply.type = 'submit';
    const toggle = el('button', '', thread.resolved ? labels.reopen : labels.resolve);
    toggle.type = 'button';
    toggle.addEventListener('click', () => guarded(toggle, async () => {
      await request('PUT', `${baseUrl}/${thread.id}/resolution`, { resolved: !thread.resolved });
      await load();
    }, labels.resolveFailed));
    wrapper.addEventListener('submit', event => {
      event.preventDefault();
      guarded(reply, async () => {
        await request('POST', `${baseUrl}/${thread.id}/comments`, { body: input.value });
        drafts.delete(thread.id);
        focusThreadId = thread.id;
        await load();
      }, labels.postFailed);
    });
    wrapper.append(input, reply, toggle);

    return wrapper;
  }

  // Runs one request with its button disabled; shows the server's reason (e.g. validation) on failure.
  async function guarded(button, action, failure) {
    button.disabled = true;
    showError('');
    try {
      await action();
    } catch (e) {
      console.error(e);
      const validation = e && e.body && e.body.errors && Object.values(e.body.errors)[0];
      showError((validation && validation[0]) || errorMessage(e, failure));
    } finally {
      button.disabled = false;
    }
  }

  function render() {
    list.replaceChildren();
    if (!threads.length) list.append(el('li', 'empty', labels.empty));
    threads.forEach(thread => list.append(renderThread(thread)));
    renderMarkers();
    renderTarget();

    const focused = list.querySelector('.cmt-thread.focus');
    if (focused && focused.scrollIntoView) focused.scrollIntoView({ block: 'nearest' });
  }

  async function load() {
    try {
      threads = (await request('GET', baseUrl)).threads;
      render();
    } catch (e) {
      console.error(e);
      showError(errorMessage(e, labels.loadFailed));
    }
  }

  function reveal(elementId) {
    const element = registry.get(elementId);
    if (!element) return;

    if (typeof canvas.scrollToElement === 'function') canvas.scrollToElement(element);
    const selection = modeler.get('selection', false);
    if (selection) selection.select(element);
    selectedId = elementId;
    renderTarget();
  }

  function openPanel() {
    if (insideEditor) togglePanel('comments-panel', true);
    else panel.hidden = false;
  }

  form.addEventListener('submit', event => {
    event.preventDefault();
    guarded(submit, async () => {
      const created = await request('POST', baseUrl, { body: body.value, element_id: selectedId });
      body.value = '';
      focusThreadId = created.id;
      await load();
    }, labels.postFailed);
  });

  // Clicking an element makes it the target of the next new Thread; the empty canvas means the whole Diagram.
  modeler.on('element.click', ({ element }) => {
    selectedId = element.parent ? element.id : null;
    renderTarget();
  });
  // The canvas changes as it is edited, loaded or restored: markers follow what is on it now.
  modeler.on('import.done', renderMarkers);
  modeler.on('commandStack.changed', renderMarkers);

  if (!insideEditor) {
    document.querySelectorAll('[data-comments-toggle]').forEach(button =>
      button.addEventListener('click', () => { panel.hidden = !panel.hidden; }));
    panel.querySelector('[data-panel-close]').addEventListener('click', () => { panel.hidden = true; });
  }

  // A notification lands here with ?open-panel=comments&thread=<id>.
  const params = new URLSearchParams(window.location.search);
  if (params.get('open-panel') === 'comments') {
    focusThreadId = Number(params.get('thread')) || null;
    openPanel();
  }

  renderTarget();
  load().catch(() => toast(labels.loadFailed, true));
}
