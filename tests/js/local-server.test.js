import { describe, expect, it } from 'vitest';
import { createLocalServer, installLocalServer } from '../../standalone/local-server.js';

const ORIGIN = 'http://standalone.invalid';
const answer = createLocalServer(ORIGIN);

describe('local server', () => {
  it('has no unread notifications and an empty list', async () => {
    expect(await answer(`${ORIGIN}/notifications/unread-count`).json()).toEqual({ count: 0 });
    expect(await answer(`${ORIGIN}/notifications`).json()).toEqual([]);
    expect(answer(`${ORIGIN}/notifications/read-all`, { method: 'POST' }).status).toBe(200);
  });

  it('answers 404 for anything else on the origin', () => {
    expect(answer(`${ORIGIN}/diagrams/x/content`).status).toBe(404);
  });

  it('leaves other URLs to the network', () => {
    expect(answer('https://example.com/notifications')).toBeNull();
  });

  it('answers the page\'s fetch() calls to the origin and passes the rest on', async () => {
    const target = { fetch: url => Promise.resolve(`network:${url}`) };
    installLocalServer(answer, target);

    expect((await target.fetch(`${ORIGIN}/notifications/unread-count`)).status).toBe(200);
    expect(await target.fetch('https://example.com/a')).toBe('network:https://example.com/a');
  });
});
