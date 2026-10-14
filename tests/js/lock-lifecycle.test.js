import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { chooseRelease, toast } from '../../public/assets/common.js';
import { startLockSession } from '../../public/assets/lock-session.js';

// The Lock lifecycle of the server-build Editor page (ADR-0003), driven through
// startLockSession with a fake Editor, a fake server and fake timers. The
// browser parts it touches (document listeners, the release dialog, the toast,
// location) are stubbed, so the test runs in vitest's node environment.

vi.mock('../../public/assets/common.js', async importOriginal => ({
  ...(await importOriginal()),
  toast: vi.fn(),
  chooseRelease: vi.fn(),
}));

const HEARTBEAT_EVERY_MS = 30000;
const IDLE_AFTER_MS = 3 * 60 * 1000;
const URLS = { heartbeat: '/heartbeat', unlock: '/unlock', index: '/diagrams' };

function fakeEditor() {
  const handlers = {};
  const editor = {
    dirty: false,
    xml: '<a/>',
    edits: 0,
    markClean: vi.fn(() => { editor.dirty = false; editor.emit('dirty', false); }),
    isDirty: () => editor.dirty,
    revision: () => editor.edits,
    currentXml: async () => editor.xml,
    edit(next) {
      editor.xml = next;
      editor.edits += 1;
      editor.dirty = true;
      editor.emit('dirty', true);
    },
    // A save settles the content on the server and clears the unsaved state.
    save() {
      editor.dirty = false;
      editor.emit('dirty', false);
    },
    on: (event, handler) => { (handlers[event] ||= []).push(handler); },
    emit: (event, ...args) => (handlers[event] || []).forEach(h => h(...args)),
  };
  return editor;
}

async function settle() {
  for (let i = 0; i < 20; i++) await Promise.resolve();
}

describe('Lock lifecycle of the Editor page', () => {
  let activityListeners;
  let releaseButton;
  let request;
  let editor;
  let banner;

  // Simulates the holder using the page: any of the activity events.
  function activity() {
    activityListeners.forEach(listener => listener());
  }

  function open({ isHolder = true } = {}) {
    startLockSession({
      editor,
      request,
      urls: URLS,
      isHolder,
      t: key => ({ lockRequestedByNotice: ':name wants the Lock' })[key] ?? key,
      banner,
      releaseButton,
    });
  }

  beforeEach(() => {
    vi.useFakeTimers();
    activityListeners = [];
    globalThis.document = {
      addEventListener: (name, listener) => { if (name === 'pointerdown') activityListeners.push(listener); },
    };
    globalThis.location = { href: '' };

    releaseButton = { disabled: false, listeners: [], addEventListener(name, fn) { this.listeners.push(fn); } };
    banner = { hidden: true, textContent: '' };
    request = vi.fn(async () => ({ lock_requested_by: null }));
    editor = fakeEditor();
    vi.mocked(chooseRelease).mockReset();
    vi.mocked(toast).mockReset();
  });

  afterEach(() => {
    vi.useRealTimers();
    delete globalThis.document;
    delete globalThis.location;
  });

  const heartbeats = () => request.mock.calls.filter(([method, url]) => method === 'PATCH' && url === URLS.heartbeat);

  it('renews the Lock with the unsaved draft while the holder is editing', async () => {
    open();

    editor.edit('<b/>');
    await vi.advanceTimersByTimeAsync(HEARTBEAT_EVERY_MS);

    expect(request).toHaveBeenLastCalledWith('PATCH', URLS.heartbeat, { draft: '<b/>' });
  });

  it('stops renewing once the holder has been idle, and resumes on activity', async () => {
    open();

    await vi.advanceTimersByTimeAsync(IDLE_AFTER_MS + HEARTBEAT_EVERY_MS);
    const whileIdle = heartbeats().length;
    await vi.advanceTimersByTimeAsync(HEARTBEAT_EVERY_MS * 2);
    expect(heartbeats().length).toBe(whileIdle);

    activity();
    await vi.advanceTimersByTimeAsync(HEARTBEAT_EVERY_MS);
    expect(heartbeats().length).toBe(whileIdle + 1);
  });

  it('keeps the Lock alive across a save: the next Heartbeat carries no draft', async () => {
    open();

    editor.edit('<b/>');
    await vi.advanceTimersByTimeAsync(HEARTBEAT_EVERY_MS);
    editor.save();
    activity();
    await vi.advanceTimersByTimeAsync(HEARTBEAT_EVERY_MS);

    expect(request).toHaveBeenLastCalledWith('PATCH', URLS.heartbeat, undefined);
  });

  it('shows the lock-request banner from the Heartbeat answer', async () => {
    request.mockResolvedValueOnce({ lock_requested_by: 'Bob' });
    open();

    await vi.advanceTimersByTimeAsync(HEARTBEAT_EVERY_MS);

    expect(banner.hidden).toBe(false);
    expect(banner.textContent).toBe('Bob wants the Lock');
  });

  it('stops renewing and tells the holder when the server has taken the Lock away', async () => {
    open();
    request.mockRejectedValueOnce(Object.assign(new Error('HTTP 403'), { status: 403 }));

    await vi.advanceTimersByTimeAsync(HEARTBEAT_EVERY_MS);
    await vi.advanceTimersByTimeAsync(HEARTBEAT_EVERY_MS * 3);

    expect(heartbeats().length).toBe(1);
    expect(toast).toHaveBeenCalledWith(expect.any(String), true);
  });

  it('releases the Lock and leaves the Editor when the draft is discarded', async () => {
    open();
    editor.edit('<b/>');
    vi.mocked(chooseRelease).mockResolvedValueOnce('discard');
    releaseButton.listeners[0]();
    await settle();

    expect(request).toHaveBeenCalledWith('DELETE', URLS.unlock, undefined);
    expect(editor.markClean).toHaveBeenCalled();
    expect(location.href).toBe(URLS.index);
  });

  it('keeps the Lock when the release is cancelled', async () => {
    open();
    editor.edit('<b/>');
    vi.mocked(chooseRelease).mockResolvedValueOnce('cancel');
    releaseButton.listeners[0]();
    await settle();

    expect(request).not.toHaveBeenCalledWith('DELETE', URLS.unlock, expect.anything());
    expect(location.href).toBe('');
    expect(releaseButton.disabled).toBe(false);
  });

  it('releases the Lock with the draft when it is saved as a provisional Version', async () => {
    open();
    editor.edit('<b/>');
    vi.mocked(chooseRelease).mockResolvedValueOnce('save');
    releaseButton.listeners[0]();
    await settle();

    expect(request).toHaveBeenCalledWith('DELETE', URLS.unlock, { save_draft: true, draft: '<b/>' });
    expect(location.href).toBe(URLS.index);
  });

  it('releases without asking when there is no unsaved content', async () => {
    open();
    releaseButton.listeners[0]();
    await settle();

    expect(chooseRelease).not.toHaveBeenCalled();
    expect(request).toHaveBeenCalledWith('DELETE', URLS.unlock, undefined);
  });
});
