import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// The diff engine needs bpmn-js; what matters here is which Versions get compared.
vi.mock('../../public/assets/diff.js', () => ({
  compareBpmnXml: vi.fn(async () => ({ added: {}, removed: {}, changed: {}, layoutChanged: {} })),
  highlightDiff: vi.fn(() => () => {}),
  renderRemovedGhosts: vi.fn(async () => Object.assign(() => {}, { bounds: null })),
}));
vi.mock('../../public/assets/common.js', async importOriginal => ({
  ...(await importOriginal()),
  toast: vi.fn(),
}));

const { compareBpmnXml } = await import('../../public/assets/diff.js');
const { toast } = await import('../../public/assets/common.js');
const { createVersionHistory } = await import('../../public/assets/versions.js');

// Just enough of an element for the history panel to render into.
function fakeElement() {
  const children = [];

  return {
    children,
    dataset: {},
    style: {},
    hidden: false,
    textContent: '',
    classList: { toggle: vi.fn(), add: vi.fn() },
    addEventListener: vi.fn(),
    appendChild: child => children.push(child),
    append: (...nodes) => children.push(...nodes),
    querySelectorAll: () => [],
    get childElementCount() { return children.length; },
    set innerHTML(value) { children.length = 0; },
  };
}

const version = (id, number, extra = {}) => ({
  id, number, name: 'Version ' + number, savedAt: '2026-10-14T12:00:00Z', author: 'Ana',
  provisional: false, current: false, ...extra,
});

function setup(versions) {
  const elements = {};
  vi.stubGlobal('document', {
    documentElement: { lang: 'en' },
    getElementById: id => (elements[id] ??= fakeElement()),
    createElement: fakeElement,
  });

  const editorHandlers = {};
  const store = {
    versions: vi.fn(async () => versions),
    versionContent: vi.fn(async id => '<' + id + '/>'),
  };
  const canvas = { scrollToElement: vi.fn(), viewbox: vi.fn(() => ({ inner: {}, outer: { width: 1000, height: 500 } })), zoom: vi.fn(() => 1) };
  const selection = { select: vi.fn() };
  const registry = { get: vi.fn(id => (id === 'T1' ? { id } : undefined)) };
  const services = { canvas, selection, elementRegistry: registry };
  const modeler = { saveXML: vi.fn(async () => ({ xml: '<canvas/>' })), get: name => services[name], canvas, selection };
  const editor = {
    modeler, store, Modeler: function Modeler() {}, timeZone: 'UTC', t: key => key,
    on: (event, handler) => { editorHandlers[event] = handler; },
  };

  createVersionHistory({ editor, canEdit: true });

  return { editorHandlers, store, elements, modeler };
}

describe('the compare handler of the Version history', () => {
  beforeEach(() => { compareBpmnXml.mockClear(); toast.mockReset(); });
  afterEach(() => { vi.unstubAllGlobals(); });

  it('compares the newer Version with what is on the canvas', async () => {
    const { editorHandlers, store, elements } = setup([version('v2', 2, { current: true }), version('v1', 1)]);

    await editorHandlers.compare('v2');

    expect(store.versionContent).toHaveBeenCalledWith('v2');
    expect(compareBpmnXml).toHaveBeenCalledWith('<v2/>', '<canvas/>', expect.anything());
    expect(elements['diff-banner'].hidden).toBe(false);
    expect(toast).not.toHaveBeenCalled();
  });

  it('still compares when the newer Version is a deleted one, absent from the history', async () => {
    const { editorHandlers, store } = setup([version('v1', 1, { current: true })]);

    await editorHandlers.compare('gone');

    expect(store.versionContent).toHaveBeenCalledWith('gone');
    expect(compareBpmnXml).toHaveBeenCalledWith('<gone/>', '<canvas/>', expect.anything());
  });

  it('rejects, so the Editor can report it, when the comparison fails', async () => {
    const { editorHandlers, store } = setup([version('v1', 1, { current: true })]);
    store.versionContent.mockRejectedValue(new Error('offline'));
    vi.spyOn(console, 'error').mockImplementation(() => {});

    await expect(editorHandlers.compare('v1')).rejects.toThrow('offline');
  });

  describe('summary panel', () => {
    const changes = {
      added: { T1: { id: 'T1', $type: 'bpmn:Task', name: 'Review' } },
      removed: { R1: { id: 'R1', $type: 'bpmn:EndEvent' } },
      changed: {},
      layoutChanged: {},
    };

    // Every clickable item of the panel, in display order.
    const buttonsOf = elements => {
      const buttons = [];
      const walk = node => (node.children || []).forEach(child => {
        if (child.addEventListener.mock.calls.length) buttons.push(child);
        walk(child);
      });
      walk(elements['diff-summary-body']);

      return buttons;
    };

    it('lists the changes of the comparison, each one focusing its element when clicked', async () => {
      compareBpmnXml.mockResolvedValueOnce(changes);
      const { editorHandlers, elements, modeler } = setup([version('v1', 1, { current: true })]);

      await editorHandlers.compare('v1');

      expect(elements['diff-summary-panel'].hidden).toBe(false);
      const buttons = buttonsOf(elements);
      expect(buttons.map(button => button.children[1].textContent)).toEqual(['Review', 'R1']);

      buttons[0].addEventListener.mock.calls[0][1]();

      expect(modeler.selection.select).toHaveBeenCalledWith({ id: 'T1' });
      expect(modeler.canvas.scrollToElement).toHaveBeenCalledWith({ id: 'T1' });
    });

    it('says so when the Versions do not differ', async () => {
      const { editorHandlers, elements } = setup([version('v1', 1, { current: true })]);

      await editorHandlers.compare('v1');

      expect(elements['diff-summary-body'].children[0].textContent).toBe('messages.diff_no_changes');
    });
  });
});
