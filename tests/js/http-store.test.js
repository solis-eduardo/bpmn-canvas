import { describe, expect, it } from 'vitest';
import { createHttpDiagramStore } from '../../public/assets/http-store.js';
import { describeDiagramStoreContract } from './diagram-store-contract.js';
import { errorMessage } from '../../public/assets/common.js';
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
    await store.load();
    await store.save('<c/>');
    const provisional = (await store.versions()).find(v => v.provisional);

    await store.deleteVersion(provisional.id);

    expect((await store.versions()).map(v => v.number)).toEqual([3, 1]);
  });

  it('refuses a save over a Version someone else saved after the load (issue #243)', async () => {
    const { store, backend } = makeStore();
    await store.save('<a/>');
    await store.load();
    const otherSession = createHttpDiagramStore({ urls, csrfToken: CSRF_TOKEN, fetch: backend.fetch });
    await otherSession.load();
    await otherSession.save('<other/>');

    const error = await store.save('<mine/>').catch(e => e);

    expect(error.conflict).toBe(true);
    expect(error.status).toBe(409);
    expect(await store.load()).toBe('<other/>');
  });

  it('names the newer Version on the conflict, for the compare option', async () => {
    const { store, backend } = makeStore();
    await store.save('<a/>');
    await store.load();
    const otherSession = createHttpDiagramStore({ urls, csrfToken: CSRF_TOKEN, fetch: backend.fetch });
    await otherSession.save('<other/>');
    const newest = (await store.versions())[0].id;

    const error = await store.save('<mine/>').catch(e => e);

    expect(error.currentVersion).toBe(newest);
  });

  it('forks the given unsaved content without touching this Diagram', async () => {
    const { store, backend } = makeStore();
    await store.save('<a/>');
    await store.load();

    const fork = await store.fork('<unsaved/>');

    expect(fork.url).toMatch(/\/diagrams\/fork-1$/);
    expect(backend.forks).toEqual(['<unsaved/>']);
    expect(await store.load()).toBe('<a/>');
    expect((await store.versions()).map(v => v.number)).toEqual([1]);
  });

  it('saves on top of a newer Version when asked to overwrite it', async () => {
    const { store, backend } = makeStore();
    await store.save('<a/>');
    await store.load();
    const otherSession = createHttpDiagramStore({ urls, csrfToken: CSRF_TOKEN, fetch: backend.fetch });
    await otherSession.save('<other/>');

    await store.save('<mine/>', { overwrite: true });

    expect(await store.load()).toBe('<mine/>');
    expect((await store.versions()).map(v => v.number)).toEqual([3, 2, 1]);
  });

  it("rejects with the HTTP status and the server's own message", async () => {
    const { store } = makeStore({ csrfToken: 'stale' });

    const error = await store.save('<a/>').catch(e => e);

    expect(error.status).toBe(419);
    expect(error.serverMessage).toBe('CSRF token mismatch.');
  });
});
