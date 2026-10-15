import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
  acquireLock,
  createHeartbeat,
  createHeartbeatLoop,
  IDLE_AFTER_MS,
  handleSaveFailure,
  reacquireLock,
  releaseLockAfterFork,
} from '../../public/assets/lock-session.js';

// The slice of the Editor's public API a Heartbeat uses.
function fakeEditor(xml = '<a/>') {
  const handlers = {};
  const editor = {
    dirty: false,
    xml,
    edits: 0,
    isDirty: () => editor.dirty,
    revision: () => editor.edits,
    currentXml: async () => editor.xml,
    edit(next) {
      editor.xml = next;
      editor.edits += 1;
      editor.dirty = true;
    },
    on: (event, handler) => { (handlers[event] ||= []).push(handler); },
    emit: (event, ...args) => (handlers[event] || []).forEach(h => h(...args)),
  };
  return editor;
}

function setup({ idleAfterMs = 1000 } = {}) {
  let clock = 0;
  const editor = fakeEditor();
  const send = vi.fn(async () => ({ lock_requested_by: null }));
  const heartbeat = createHeartbeat({ editor, send, idleAfterMs, now: () => clock });

  return { editor, send, heartbeat, advance: ms => { clock += ms; } };
}

describe('createHeartbeat', () => {
  it('renews the Lock without a draft while there are no unsaved changes', async () => {
    const { send, heartbeat } = setup();

    await heartbeat.beat();

    expect(send).toHaveBeenCalledWith(undefined);
  });

  it('carries the unsaved content as a draft, once per change', async () => {
    const { editor, send, heartbeat } = setup();
    editor.dirty = true;

    await heartbeat.beat();
    await heartbeat.beat();
    editor.edit('<b/>');
    await heartbeat.beat();

    expect(send.mock.calls.map(([payload]) => payload)).toEqual([{ draft: '<a/>' }, undefined, { draft: '<b/>' }]);
  });

  it('sends the draft again after a save settled it on the server', async () => {
    const { editor, send, heartbeat } = setup();
    editor.dirty = true;
    await heartbeat.beat();

    editor.emit('dirty', false);
    editor.emit('dirty', true);
    await heartbeat.beat();

    expect(send.mock.calls.map(([payload]) => payload)).toEqual([{ draft: '<a/>' }, { draft: '<a/>' }]);
  });

  it('resends a draft whose Heartbeat failed', async () => {
    const { editor, send, heartbeat } = setup();
    editor.dirty = true;
    send.mockRejectedValueOnce(new Error('offline'));

    await expect(heartbeat.beat()).rejects.toThrow('offline');
    await heartbeat.beat();

    expect(send).toHaveBeenLastCalledWith({ draft: '<a/>' });
  });

  it('stops renewing while the holder is idle, and resumes on activity', async () => {
    const { send, heartbeat, advance } = setup({ idleAfterMs: 1000 });

    advance(1001);
    expect(await heartbeat.beat()).toBeNull();
    heartbeat.touch();
    await heartbeat.beat();

    expect(send).toHaveBeenCalledTimes(1);
  });

  it('goes idle after 2 minutes without activity by default', async () => {
    const send = vi.fn(async () => ({}));
    let clock = 0;
    const heartbeat = createHeartbeat({ editor: { isDirty: () => false, on: () => {} }, send, now: () => clock });

    expect(IDLE_AFTER_MS).toBe(2 * 60 * 1000);

    clock = IDLE_AFTER_MS;
    expect(await heartbeat.beat()).not.toBeNull();

    clock = IDLE_AFTER_MS + 1;
    expect(await heartbeat.beat()).toBeNull();

    heartbeat.touch();
    expect(await heartbeat.beat()).not.toBeNull();
  });

  it("returns who is asking for the Lock, from the server's answer", async () => {
    const { send, heartbeat } = setup();
    send.mockResolvedValueOnce({ lock_requested_by: 'Bob' });

    expect(await heartbeat.beat()).toEqual({ lockRequestedBy: 'Bob' });
  });
});

describe('createHeartbeatLoop', () => {
  const INTERVAL = 30000;

  function setupLoop() {
    const heartbeat = { beat: vi.fn(async () => ({ lockRequestedBy: null })) };
    const onRequested = vi.fn();
    const onLost = vi.fn();
    const loop = createHeartbeatLoop({ heartbeat, onRequested, onLost, intervalMs: INTERVAL });

    return { heartbeat, onRequested, onLost, loop };
  }

  beforeEach(() => { vi.useFakeTimers(); });
  afterEach(() => { vi.useRealTimers(); });

  it('beats on every interval once started', async () => {
    const { heartbeat, loop } = setupLoop();
    loop.start();

    await vi.advanceTimersByTimeAsync(INTERVAL * 2);

    expect(heartbeat.beat).toHaveBeenCalledTimes(2);
    loop.stop();
  });

  it('reports who requested the Lock', async () => {
    const { heartbeat, onRequested, loop } = setupLoop();
    heartbeat.beat.mockResolvedValueOnce({ lockRequestedBy: 'Bob' });

    await loop.beatNow();

    expect(onRequested).toHaveBeenCalledWith('Bob');
  });

  it('stops and reports a lost Lock once when the server answers 403 or 423', async () => {
    const { heartbeat, onLost, loop } = setupLoop();
    heartbeat.beat.mockRejectedValue(Object.assign(new Error('HTTP 403'), { status: 403 }));
    loop.start();

    await vi.advanceTimersByTimeAsync(INTERVAL * 3);

    expect(onLost).toHaveBeenCalledTimes(1);
    expect(heartbeat.beat).toHaveBeenCalledTimes(1);
  });

  it('keeps beating through other failures', async () => {
    const { heartbeat, onLost, loop } = setupLoop();
    heartbeat.beat.mockRejectedValue(Object.assign(new Error('HTTP 500'), { status: 500 }));
    loop.start();

    await vi.advanceTimersByTimeAsync(INTERVAL * 2);

    expect(onLost).not.toHaveBeenCalled();
    expect(heartbeat.beat).toHaveBeenCalledTimes(2);
    loop.stop();
  });

  it('resumes after a lost Lock is taken back', async () => {
    const { heartbeat, onLost, loop } = setupLoop();
    heartbeat.beat.mockRejectedValueOnce(Object.assign(new Error('HTTP 403'), { status: 403 }));
    loop.start();
    await loop.beatNow();

    loop.resume();
    await vi.advanceTimersByTimeAsync(INTERVAL);

    expect(onLost).toHaveBeenCalledTimes(1);
    expect(heartbeat.beat).toHaveBeenCalledTimes(2);
    loop.stop();
  });

  it('ignores a 403 from a beat sent before the Lock was taken back', async () => {
    const { heartbeat, onLost, loop } = setupLoop();
    let refuse;
    heartbeat.beat.mockImplementationOnce(() => new Promise((_, reject) => { refuse = reject; }));
    const pending = loop.beatNow();

    loop.resume();
    refuse(Object.assign(new Error('HTTP 403'), { status: 403 }));
    await pending;

    expect(onLost).not.toHaveBeenCalled();
  });
});

describe('handleSaveFailure', () => {
  it('handles a save refused with 423 by stopping the Heartbeat and showing the lost notice', () => {
    const stopHeartbeat = vi.fn();
    const showLockLost = vi.fn();

    const handled = handleSaveFailure(Object.assign(new Error('HTTP 423'), { status: 423 }), { stopHeartbeat, showLockLost });

    expect(handled).toBe(true);
    expect(stopHeartbeat).toHaveBeenCalledTimes(1);
    expect(showLockLost).toHaveBeenCalledTimes(1);
  });

  it('leaves other save failures to the generic notice', () => {
    const stopHeartbeat = vi.fn();
    const showLockLost = vi.fn();

    const handled = handleSaveFailure(Object.assign(new Error('HTTP 500'), { status: 500 }), { stopHeartbeat, showLockLost });

    expect(handled).toBe(false);
    expect(stopHeartbeat).not.toHaveBeenCalled();
    expect(showLockLost).not.toHaveBeenCalled();
  });
});

describe('reacquireLock', () => {
  const INTERVAL = 30000;

  beforeEach(() => { vi.useFakeTimers(); });
  afterEach(() => { vi.useRealTimers(); });

  function lostLoop() {
    const heartbeat = { beat: vi.fn(async () => ({ lockRequestedBy: null })) };
    const onLost = vi.fn();
    const loop = createHeartbeatLoop({ heartbeat, onRequested: vi.fn(), onLost, intervalMs: INTERVAL });

    heartbeat.beat.mockRejectedValueOnce(Object.assign(new Error('HTTP 403'), { status: 403 }));
    loop.start();
    return { heartbeat, onLost, loop };
  }

  it('keeps the lost notice and the Heartbeat stopped while another User holds the Lock', async () => {
    const { heartbeat, onLost, loop } = lostLoop();
    await loop.beatNow();
    expect(onLost).toHaveBeenCalledTimes(1);

    const request = vi.fn(async () => ({ state: 'waiting', requested_at: '2026-10-14T12:00:00+00:00' }));
    const onAcquired = vi.fn();

    const acquired = await reacquireLock({ request, url: '/lock', loop, onAcquired });
    await vi.advanceTimersByTimeAsync(INTERVAL * 3);

    expect(acquired).toBe(false);
    expect(onAcquired).not.toHaveBeenCalled();
    expect(heartbeat.beat).toHaveBeenCalledTimes(1);
    expect(onLost).toHaveBeenCalledTimes(1);
    loop.stop();
  });

  it('hides the lost notice and resumes the Heartbeat once the Lock is acquired', async () => {
    const { heartbeat, loop } = lostLoop();
    await loop.beatNow();

    const request = vi.fn(async () => ({ state: 'acquired' }));
    const calls = [];
    const onAcquired = vi.fn(() => calls.push('hide'));
    heartbeat.beat.mockImplementation(async () => { calls.push('beat'); return { lockRequestedBy: null }; });

    const acquired = await reacquireLock({ request, url: '/lock', loop, onAcquired });
    await vi.advanceTimersByTimeAsync(INTERVAL);

    expect(acquired).toBe(true);
    expect(request).toHaveBeenCalledWith('POST', '/lock');
    expect(calls).toEqual(['hide', 'beat', 'beat']);
    loop.stop();
  });
});

describe('releaseLockAfterFork', () => {
  it('releases the Lock without asking to keep the draft', async () => {
    const request = vi.fn(async () => ({ unlocked: true }));

    await releaseLockAfterFork({ request, url: '/unlock' });

    expect(request).toHaveBeenCalledWith('DELETE', '/unlock');
  });

  it('only logs a failed release, so the page can still move to the Fork', async () => {
    const request = vi.fn(async () => { throw new Error('offline'); });
    const log = vi.spyOn(console, 'error').mockImplementation(() => {});

    await expect(releaseLockAfterFork({ request, url: '/unlock' })).resolves.toBeUndefined();
    expect(log).toHaveBeenCalled();
    log.mockRestore();
  });
});

describe('acquireLock', () => {
  const url = '/diagrams/a/lock';

  it('posts to the acquire endpoint and reports the Lock as acquired', async () => {
    const request = vi.fn(async () => ({ state: 'acquired' }));

    expect(await acquireLock({ request, url })).toBe(true);
    expect(request).toHaveBeenCalledWith('POST', url);
  });

  it('reports a refusal (423, someone else holds the Lock) as not acquired', async () => {
    const request = vi.fn(async () => { throw Object.assign(new Error('locked'), { status: 423 }); });

    expect(await acquireLock({ request, url })).toBe(false);
  });

  it('reports a refusal (403, the User cannot edit) as not acquired', async () => {
    const request = vi.fn(async () => { throw Object.assign(new Error('forbidden'), { status: 403 }); });

    expect(await acquireLock({ request, url })).toBe(false);
  });

  it('rethrows any other failure instead of treating it as a refusal', async () => {
    const error = Object.assign(new Error('server error'), { status: 500 });
    const request = vi.fn(async () => { throw error; });

    await expect(acquireLock({ request, url })).rejects.toBe(error);
  });
});
