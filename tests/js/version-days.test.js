import { describe, expect, it } from 'vitest';
import { groupVersionsByDay } from '../../public/assets/version-days.js';

const version = (id, savedAt) => ({ id, savedAt });

describe('groupVersionsByDay', () => {
  it('groups consecutive Versions by calendar day, keeping their order', () => {
    const days = groupVersionsByDay([
      version('c', '2026-10-13T15:00:00Z'),
      version('b', '2026-10-13T09:00:00Z'),
      version('a', '2026-10-12T09:00:00Z'),
    ], 'UTC');

    expect(days).toEqual([
      { day: '2026-10-13', items: [expect.objectContaining({ id: 'c' }), expect.objectContaining({ id: 'b' })] },
      { day: '2026-10-12', items: [expect.objectContaining({ id: 'a' })] },
    ]);
  });

  it("splits days at midnight in the given time zone, not UTC's", () => {
    // 02:00 UTC on the 13th is still the 12th in São Paulo (UTC-3).
    const days = groupVersionsByDay([
      version('b', '2026-10-13T02:00:00Z'),
      version('a', '2026-10-12T20:00:00Z'),
    ], 'America/Sao_Paulo');

    expect(days.map(d => [d.day, d.items.map(v => v.id)])).toEqual([['2026-10-12', ['b', 'a']]]);
  });

  it('returns no days for no Versions', () => {
    expect(groupVersionsByDay([], 'UTC')).toEqual([]);
  });
});
