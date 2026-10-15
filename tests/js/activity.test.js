import { afterEach, describe, expect, it, vi } from 'vitest';

const { startActivity } = await import('../../public/assets/activity.js');

// Just enough DOM for activity.js: elements are plain trees of text and children.
class FakeEl {
  constructor(tag) {
    this.tag = tag;
    this.children = [];
    this.listeners = {};
    this.dataset = {};
    this.textContent = '';
    this.hidden = false;
  }

  append(...nodes) { this.children.push(...nodes); }

  replaceChildren(...nodes) { this.children = nodes; }

  addEventListener(name, fn) { (this.listeners[name] ||= []).push(fn); }

  fire(name) { (this.listeners[name] || []).forEach(fn => fn({ preventDefault() {} })); }

  text() { return this.textContent + this.children.map(child => child.text()).join(' '); }
}

const labels = { empty: 'Nothing yet', noMatch: 'No match', loadFailed: 'Load failed', loadMore: 'More' };

const entry = (overrides = {}) => ({
  id: 1, created_at: '2026-10-15T12:00:00Z', actor: '<img src=x onerror=alert(1)>', action: 'Share created', subject: null, changes: [],
  ...overrides,
});

function setup(responses, filterValues = [], { editor = true } = {}) {
  const ids = [...(editor ? ['app'] : []), 'activity-panel', 'btn-activity-entry', 'activity-list', 'activity-filters', 'activity-more', 'activity-error'];
  const nodes = Object.fromEntries(ids.map(id => [id, new FakeEl('x')]));
  nodes['activity-panel'].dataset = { labels: JSON.stringify(labels), activityUrl: '/diagrams/d/activity' };
  nodes['activity-panel'].hidden = true;
  nodes['activity-panel'].querySelector = () => new FakeEl('button');

  vi.stubGlobal('document', {
    getElementById: id => nodes[id],
    querySelectorAll: () => [],
    querySelector: () => ({ content: 'csrf' }),
    createElement: tag => new FakeEl(tag),
    createTextNode: text => Object.assign(new FakeEl('#text'), { textContent: text }),
  });
  vi.stubGlobal('FormData', class { constructor() { return filterValues; } });
  const queue = [...responses];
  const fetchMock = vi.fn(async () => {
    const next = queue.shift();

    return next instanceof Error ? { ok: false, status: 500, json: async () => ({}) } : { ok: true, json: async () => next };
  });
  vi.stubGlobal('fetch', fetchMock);

  startActivity();

  const open = async () => {
    if (editor) nodes['activity-panel'].hidden = false; // the Editor's own toggle handler (common.js) does this
    nodes['btn-activity-entry'].fire('click');
    await new Promise(resolve => setTimeout(resolve, 0));
    await new Promise(resolve => setTimeout(resolve, 0));
  };

  return { nodes, fetchMock, open };
}

describe('startActivity', () => {
  afterEach(() => { vi.unstubAllGlobals(); });

  it('loads nothing until the panel is opened, then shows entries as text', async () => {
    const { nodes, fetchMock, open } = setup([{ entries: [entry({ subject: 'Bob', changes: [{ field: 'Role', before: 'a', after: 'b' }] })], next_page: null }]);
    expect(fetchMock).not.toHaveBeenCalled();

    await open();

    expect(fetchMock.mock.calls[0][0]).toBe('/diagrams/d/activity?page=1');
    const text = nodes['activity-list'].text();
    expect(text).toContain('<img src=x onerror=alert(1)>');
    expect(text).toContain('Share created');
    expect(text).toContain('Bob');
    expect(text).toContain('Role: a → b');
    expect(nodes['activity-more'].hidden).toBe(true);
  });

  it('shows the empty notice and offers more pages when there are any', async () => {
    const { nodes, open } = setup([{ entries: [], next_page: null }]);
    await open();
    expect(nodes['activity-list'].text()).toContain('Nothing yet');

    const paged = setup([{ entries: [entry()], next_page: 2 }, { entries: [entry({ id: 2, action: 'Share revoked' })], next_page: null }]);
    await paged.open();
    expect(paged.nodes['activity-more'].hidden).toBe(false);

    paged.nodes['activity-more'].fire('click');
    await new Promise(resolve => setTimeout(resolve, 0));
    await new Promise(resolve => setTimeout(resolve, 0));

    expect(paged.fetchMock.mock.calls[1][0]).toBe('/diagrams/d/activity?page=2');
    expect(paged.nodes['activity-list'].text()).toContain('Share created');
    expect(paged.nodes['activity-list'].text()).toContain('Share revoked');
  });

  it('sends the filters, and tells "no match" apart from "nothing yet"', async () => {
    const { nodes, fetchMock, open } = setup([{ entries: [], next_page: null }], [['actor', 'rita'], ['from', '']]);
    vi.stubGlobal('URLSearchParams', URLSearchParams);
    await open();

    expect(fetchMock.mock.calls[0][0]).toBe('/diagrams/d/activity?actor=rita&page=1');
    expect(nodes['activity-list'].text()).toContain('No match');
  });

  it('shows an error notice when loading fails', async () => {
    const { nodes, open } = setup([new Error('x')]);
    vi.spyOn(console, 'error').mockImplementation(() => {});
    await open();

    expect(nodes['activity-error'].textContent).toBe('Load failed');
    expect(nodes['activity-error'].hidden).toBe(false);
  });

  it('toggles itself open in the Viewer, where there is no side-panel layout', async () => {
    const { nodes, fetchMock, open } = setup([{ entries: [entry()], next_page: null }], [], { editor: false });
    await open();

    expect(nodes['activity-panel'].hidden).toBe(false);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
});
