import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('../../public/assets/common.js', async importOriginal => ({
  ...(await importOriginal()),
  toast: vi.fn(),
  togglePanel: vi.fn(),
}));

const { startComments } = await import('../../public/assets/comments.js');

// Just enough DOM for comments.js: elements are plain trees of text and children.
class FakeEl {
  constructor(tag) {
    this.tag = tag;
    this.children = [];
    this.listeners = {};
    this.dataset = {};
    this.classList = { add() {}, toggle() {} };
    this.textContent = '';
    this.hidden = false;
    this.value = '';
  }

  append(...nodes) { this.children.push(...nodes); }

  replaceChildren(...nodes) { this.children = nodes; }

  addEventListener(name, fn) { (this.listeners[name] ||= []).push(fn); }

  querySelector() { return null; }

  querySelectorAll() { return []; }

  fire(name, event = {}) { (this.listeners[name] || []).forEach(fn => fn({ preventDefault() {}, ...event })); }

  text() { return this.textContent + this.children.map(child => child.text()).join(''); }

  find(predicate, found = []) {
    if (predicate(this)) found.push(this);
    this.children.forEach(child => child.find(predicate, found));

    return found;
  }
}

const labels = {
  placeholder: 'Write', post: 'Comment', reply: 'Reply', resolve: 'Resolve', reopen: 'Reopen', resolved: 'Resolved',
  resolvedBy: 'Resolved by :name', orphaned: 'Orphaned', onDiagram: 'On the Diagram', onElement: 'On :element',
  hint: 'hint', empty: 'None', loadFailed: 'load failed', postFailed: 'post failed', resolveFailed: 'resolve failed',
  showOnCanvas: 'Show',
};

function setup(threads, presentIds) {
  const ids = ['app', 'comments-panel', 'comments-list', 'comment-form', 'comment-body', 'comment-submit', 'comment-target', 'comment-error'];
  const nodes = Object.fromEntries(ids.map(id => [id, new FakeEl('x')]));
  nodes['comments-panel'].dataset = { labels: JSON.stringify(labels), commentsUrl: '/diagrams/d/comment-threads' };

  vi.stubGlobal('document', {
    getElementById: id => nodes[id] ?? null,
    querySelector: () => ({ content: 'csrf' }),
    querySelectorAll: () => [],
    createElement: tag => new FakeEl(tag),
    createTextNode: text => Object.assign(new FakeEl('#text'), { textContent: text }),
  });
  vi.stubGlobal('window', { location: { search: '' } });
  vi.stubGlobal('location', { search: '' });
  const fetchMock = vi.fn(async (url, options = {}) => ({
    ok: true,
    json: async () => (options.method === 'POST' ? { id: 9 } : { threads }),
  }));
  vi.stubGlobal('fetch', fetchMock);

  const added = [];
  const handlers = {};
  const services = {
    overlays: { add: (id, type, config) => added.push({ id, type, config }), remove: vi.fn() },
    elementRegistry: { get: id => (presentIds.includes(id) ? { id, parent: {}, businessObject: { name: 'Approve' } } : undefined) },
    canvas: {},
  };
  const modeler = {
    get: name => services[name],
    on: (event, fn) => { handlers[event] = fn; },
  };

  startComments({ modeler });

  return { nodes, added, handlers, fetchMock, flush: () => new Promise(resolve => setTimeout(resolve, 0)) };
}

const thread = (overrides = {}) => ({
  id: 1, element_id: 'Task_1', orphaned: false, resolved: false, resolved_by: null,
  comments: [{ id: 1, author: 'Rita', body: '<img src=x onerror=alert(1)>', created_at: '2026-10-15T12:00:00Z' }],
  ...overrides,
});

describe('startComments', () => {
  afterEach(() => { vi.unstubAllGlobals(); });

  it('shows a Comment body as text and marks the commented element on the canvas', async () => {
    const { nodes, added, flush } = setup([thread()], ['Task_1']);
    await flush();

    expect(nodes['comments-list'].text()).toContain('<img src=x onerror=alert(1)>');
    expect(added).toHaveLength(1);
    expect(added[0]).toMatchObject({ id: 'Task_1', type: 'comment-marker' });
  });

  it('lists a Thread of a deleted element as orphaned, without a marker', async () => {
    const { nodes, added, flush } = setup([thread({ element_id: 'Task_9', orphaned: true })], ['Task_1']);
    await flush();

    expect(added).toHaveLength(0);
    expect(nodes['comments-list'].text()).toContain('Orphaned');
    expect(nodes['comments-list'].text()).toContain('<img src=x');
  });

  it('does not call an element orphaned while it is still on the canvas (unsaved)', async () => {
    const { nodes, flush } = setup([thread({ orphaned: true })], ['Task_1']);
    await flush();

    expect(nodes['comments-list'].text()).not.toContain('Orphaned');
  });

  it('opens a new Thread on the clicked element, or on the Diagram for the empty canvas', async () => {
    const { nodes, handlers, fetchMock, flush } = setup([], ['Task_1']);
    await flush();

    handlers['element.click']({ element: { id: 'Task_1', parent: {} } });
    nodes['comment-body'].value = 'Is this right?';
    nodes['comment-form'].fire('submit');
    await flush();

    const post = fetchMock.mock.calls.find(([, options]) => options?.method === 'POST');
    expect(post[0]).toBe('/diagrams/d/comment-threads');
    expect(JSON.parse(post[1].body)).toEqual({ body: 'Is this right?', element_id: 'Task_1' });

    handlers['element.click']({ element: { id: 'Process_1', parent: null } });
    nodes['comment-body'].value = 'General';
    nodes['comment-form'].fire('submit');
    await flush();

    const last = fetchMock.mock.calls.filter(([, options]) => options?.method === 'POST').at(-1);
    expect(JSON.parse(last[1].body).element_id).toBeNull();
  });
});
