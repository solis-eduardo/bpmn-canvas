import { describe, expect, it, vi } from 'vitest';
import { resolveSaveConflict } from '../../public/assets/save-conflict.js';

const t = key => key;

function setup({ pick, compare = true, fork = true } = {}) {
  const hooks = {
    reload: vi.fn(async () => {}),
    overwrite: vi.fn(async () => true),
    compare: compare ? vi.fn(async () => {}) : null,
    fork: fork ? vi.fn(async () => {}) : null,
  };
  const choose = vi.fn(async () => pick);

  return { hooks, choose, run: () => resolveSaveConflict({ t, choose, ...hooks }) };
}

const values = choose => choose.mock.calls[0][0].choices.map(c => c.value);

describe('resolveSaveConflict', () => {
  it('offers every way out of the conflict, the safe one first', async () => {
    const { choose, run } = setup({ pick: 'cancel' });
    await run();

    expect(values(choose)).toEqual(['cancel', 'compare', 'fork', 'overwrite', 'reload']);
    expect(choose.mock.calls[0][0].message).toBe('messages.diagram_conflict');
  });

  it('leaves out compare and fork when the page cannot do them', async () => {
    const { choose, run } = setup({ pick: 'cancel', compare: false, fork: false });
    await run();

    expect(values(choose)).toEqual(['cancel', 'overwrite', 'reload']);
  });

  it('keeps editing without touching anything', async () => {
    const { hooks, run } = setup({ pick: 'cancel' });

    expect(await run()).toBe(false);
    expect(hooks.reload).not.toHaveBeenCalled();
    expect(hooks.overwrite).not.toHaveBeenCalled();
    expect(hooks.compare).not.toHaveBeenCalled();
    expect(hooks.fork).not.toHaveBeenCalled();
  });

  it.each(['reload', 'compare', 'fork'])('runs only %s and reports nothing stored', async pick => {
    const { hooks, run } = setup({ pick });

    expect(await run()).toBe(false);
    expect(hooks[pick]).toHaveBeenCalledTimes(1);
    expect(hooks.overwrite).not.toHaveBeenCalled();
  });

  it('saves anyway and reports whether it was stored', async () => {
    const { hooks, run } = setup({ pick: 'overwrite' });
    expect(await run()).toBe(true);

    hooks.overwrite.mockResolvedValueOnce(false);
    expect(await run()).toBe(false);
    expect(hooks.reload).not.toHaveBeenCalled();
  });
});
