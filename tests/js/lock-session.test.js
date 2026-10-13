import { describe, expect, it, vi } from 'vitest';
import { createHeartbeat } from '../../public/assets/lock-session.js';

// The slice of the Editor's public API a Heartbeat uses.
function fakeEditor(xml = '<a/>') {
  const handlers = {};
  const editor = {
    dirty: false,
    xml,
    isDirty: () => editor.dirty,
    currentXml: async () => editor.xml,
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
    editor.xml = '<b/>';
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

  it("returns who is asking for the Lock, from the server's answer", async () => {
    const { send, heartbeat } = setup();
    send.mockResolvedValueOnce({ lock_requested_by: 'Bob' });

    expect(await heartbeat.beat()).toEqual({ lockRequestedBy: 'Bob' });
  });
});
