import { describe, expect, it } from 'vitest';
import { createHttpDiagramStore } from '../../public/assets/http-store.js';
import { describeDiagramStoreContract } from './diagram-store-contract.js';
import { createFakeLaravelBackend, CSRF_TOKEN, urls } from './fake-laravel-backend.js';

function makeStore({ csrfToken = CSRF_TOKEN } = {}) {
  const backend = createFakeLaravelBackend();
  const store = createHttpDiagramStore({ urls, csrfToken, fetch: backend.fetch });

  return { store, backend };
}

describeDiagramStoreContract('HttpDiagramStore', () => makeStore().store);

describe('HttpDiagramStore', () => {
  it('maps the history to Versions carrying author and provisional state', async () => {
    const { store, backend } = makeStore();

    await store.save('<a/>');
    backend.addProvisional('<b/>');

    expect(await store.versions()).toEqual([
      expect.objectContaining({ number: 2, current: true, provisional: true, author: 'Alice' }),
      expect.objectContaining({ number: 1, current: false, provisional: false, author: 'Alice', name: 'Version 1' }),
    ]);
  });

  it('promotes a provisional Version to an ordinary one', async () => {
    const { store, backend } = makeStore();
    backend.addProvisional('<a/>');
    const [version] = await store.versions();

    await store.promoteVersion(version.id);

    expect((await store.versions())[0].provisional).toBe(false);
  });

  it('deletes a provisional Version from the history, keeping the others numbered', async () => {
    const { store, backend } = makeStore();
    await store.save('<a/>');
    backend.addProvisional('<b/>');
    await store.save('<c/>');
    const provisional = (await store.versions()).find(v => v.provisional);

    await store.deleteVersion(provisional.id);

    expect((await store.versions()).map(v => v.number)).toEqual([3, 1]);
  });

  it("rejects with the HTTP status and the server's own message", async () => {
    const { store } = makeStore({ csrfToken: 'stale' });

    const error = await store.save('<a/>').catch(e => e);

    expect(error.status).toBe(419);
    expect(error.serverMessage).toBe('CSRF token mismatch.');
  });
});
