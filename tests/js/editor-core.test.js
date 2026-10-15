import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

// The dialog and the toast need a real DOM; the rest of common.js is used as is.
vi.mock('../../public/assets/common.js', async importOriginal => ({
  ...(await importOriginal()),
  chooseOption: vi.fn(),
  toast: vi.fn(),
}));

const { chooseOption, toast } = await import('../../public/assets/common.js');
const { createEditor } = await import('../../public/assets/editor-core.js');

const conflict = () => Object.assign(new Error('HTTP 409'), { status: 409, conflict: true, currentVersion: 'v9' });

function setup({ withFork = true } = {}) {
  const canvas = { zoom: vi.fn() };
  const modeler = {
    get: () => canvas,
    on: vi.fn(),
    importXML: vi.fn(async () => ({ warnings: [] })),
    saveXML: vi.fn(async () => ({ xml: '<local/>' })),
  };
  const store = {
    load: vi.fn(async () => '<newer/>'),
    save: vi.fn(async (xml, options) => { if (!options?.overwrite) throw conflict(); }),
  };
  if (withFork) store.fork = vi.fn(async () => ({ url: '/diagrams/copy' }));

  const editor = createEditor({
    container: '#canvas',
    store,
    t: key => key,
    Modeler: function Modeler() { return modeler; },
  });

  return { editor, store, modeler };
}

const offered = () => chooseOption.mock.calls[0][0].choices.map(c => c.value);
const flush = () => new Promise(resolve => setTimeout(resolve, 0));

describe('createEditor save conflicts', () => {
  beforeEach(() => {
    const element = { classList: { toggle: vi.fn() }, addEventListener: vi.fn() };
    vi.stubGlobal('document', { getElementById: () => element, addEventListener: vi.fn() });
    vi.stubGlobal('window', { addEventListener: vi.fn() });
    vi.stubGlobal('location', { assign: vi.fn() });
    chooseOption.mockReset();
    toast.mockReset();
  });

  afterEach(() => { vi.unstubAllGlobals(); });

  it('offers compare only when a page registered a handler, and Fork only when the store can', async () => {
    chooseOption.mockResolvedValue('cancel');
    const bare = setup({ withFork: false });
    await flush();
    await bare.editor.save();
    expect(offered()).toEqual(['cancel', 'overwrite', 'reload']);

    chooseOption.mockClear();
    const full = setup();
    full.editor.on('compare', () => {});
    await flush();
    await full.editor.save();
    expect(offered()).toEqual(['cancel', 'compare', 'fork', 'overwrite', 'reload']);
  });

  it('hands the newer Version id to the compare handlers and keeps the local edits', async () => {
    chooseOption.mockResolvedValue('compare');
    const { editor, modeler, store } = setup();
    const compare = vi.fn(async () => {});
    editor.on('compare', compare);
    await flush();
    modeler.importXML.mockClear();

    await editor.save();

    expect(compare).toHaveBeenCalledWith('v9');
    expect(modeler.importXML).not.toHaveBeenCalled();
    expect(store.save).toHaveBeenCalledTimes(1);
    expect(toast).toHaveBeenCalledWith('messages.conflict_compare_hint');
  });

  it('reports a failed comparison instead of the hint', async () => {
    chooseOption.mockResolvedValue('compare');
    const { editor } = setup();
    editor.on('compare', async () => { throw new Error('boom'); });
    vi.spyOn(console, 'error').mockImplementation(() => {});
    await flush();

    await editor.save();

    expect(toast).toHaveBeenCalledWith('messages.version_compare_failed', true);
    expect(toast).not.toHaveBeenCalledWith('messages.conflict_compare_hint');
  });

  it('forks what is on the canvas, marks it clean, tells the page and moves to the Fork', async () => {
    chooseOption.mockResolvedValue('fork');
    const { editor, store } = setup();
    const forked = vi.fn();
    editor.on('forked', forked);
    await flush();

    await editor.save();

    expect(store.fork).toHaveBeenCalledWith('<local/>');
    expect(forked).toHaveBeenCalledWith({ url: '/diagrams/copy' });
    expect(location.assign).toHaveBeenCalledWith('/diagrams/copy');
    expect(editor.isDirty()).toBe(false);
  });

  it('stays on the page and keeps the edits when the Fork fails', async () => {
    chooseOption.mockResolvedValue('fork');
    const { editor, store } = setup();
    store.fork.mockRejectedValue(new Error('boom'));
    vi.spyOn(console, 'error').mockImplementation(() => {});
    await flush();

    await editor.save();

    expect(toast).toHaveBeenCalledWith('messages.conflict_fork_failed', true);
    expect(location.assign).not.toHaveBeenCalled();
  });

  it('still moves to the Fork when a page handler fails', async () => {
    chooseOption.mockResolvedValue('fork');
    const { editor } = setup();
    editor.on('forked', () => { throw new Error('boom'); });
    vi.spyOn(console, 'error').mockImplementation(() => {});
    await flush();

    await editor.save();

    expect(location.assign).toHaveBeenCalledWith('/diagrams/copy');
  });

  it('reloads the newer Version on request, and saves anyway on request', async () => {
    chooseOption.mockResolvedValueOnce('reload');
    const { editor, store } = setup();
    await flush();
    await editor.save();
    expect(store.load).toHaveBeenCalledTimes(2);

    chooseOption.mockResolvedValueOnce('overwrite');
    await editor.save();
    expect(store.save).toHaveBeenLastCalledWith('<local/>', { overwrite: true });
    expect(toast).toHaveBeenCalledWith('messages.saved');
  });
});

describe('createEditor modeling checks', () => {
  function lintSetup({ lint, stored = null }) {
    const element = { classList: { toggle: vi.fn() }, addEventListener: vi.fn(), setAttribute: vi.fn(), hidden: true };
    vi.stubGlobal('document', { getElementById: () => element, addEventListener: vi.fn() });
    vi.stubGlobal('window', {
      addEventListener: vi.fn(),
      localStorage: { getItem: () => stored, setItem: vi.fn() },
    });
    const linting = { isActive: () => true, toggle: vi.fn() };
    const canvas = { zoom: vi.fn() };
    const Modeler = vi.fn(function Modeler() {
      return {
        get: name => (name === 'linting' ? linting : canvas),
        on: vi.fn(),
        importXML: vi.fn(async () => ({ warnings: [] })),
        saveXML: vi.fn(async () => ({ xml: '<x/>' })),
      };
    });
    createEditor({ container: '#canvas', store: { load: vi.fn(async () => '<x/>') }, t: key => key, Modeler, lint });

    return { Modeler, element };
  }

  afterEach(() => { vi.unstubAllGlobals(); });

  const lint = { modules: ['lintModule'], options: { linting: { bpmnlint: 'rules', active: true } } };

  it('starts a plain Modeler, and keeps the toggle hidden, without the checks', () => {
    const { Modeler, element } = lintSetup({ lint: undefined });

    expect(Modeler).toHaveBeenCalledWith({ container: '#canvas' });
    expect(element.hidden).toBe(true);
  });

  it('adds the lint module to the Modeler and shows the toggle', () => {
    const { Modeler, element } = lintSetup({ lint });

    expect(Modeler).toHaveBeenCalledWith({
      container: '#canvas',
      additionalModules: ['lintModule'],
      linting: { bpmnlint: 'rules', active: true },
    });
    expect(element.hidden).toBe(false);
  });

  it('starts the checks hidden when the User turned them off', () => {
    const { Modeler } = lintSetup({ lint, stored: '0' });

    expect(Modeler.mock.calls[0][0].linting.active).toBe(false);
  });
});
