/*
 * An in-memory stand-in for the Laravel endpoints behind the Editor
 * (DiagramContentController, VersionController), answering a `fetch` with
 * the same JSON shapes, statuses and CSRF check. Lets HttpDiagramStore be
 * exercised without a server.
 */

const BASE = 'http://bpmn.test/diagrams/d';

export const urls = {
  contentUrl: `${BASE}/content`,
  saveUrl: `${BASE}/content`,
  forkUrl: `${BASE}/fork`,
  versionsUrl: `${BASE}/versions`,
  versionContentUrlTemplate: `${BASE}/versions/VERSION_ID/content`,
  versionRestoreUrlTemplate: `${BASE}/versions/VERSION_ID/restore`,
  versionRenameUrlTemplate: `${BASE}/versions/VERSION_ID/rename`,
  versionPromoteUrlTemplate: `${BASE}/versions/VERSION_ID/promote`,
  versionDeleteUrlTemplate: `${BASE}/versions/VERSION_ID`,
};

export const CSRF_TOKEN = 'csrf-token';

const json = (status, body) =>
  new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

export function createFakeLaravelBackend({ initialXml = '<definitions/>' } = {}) {
  let content = initialXml;
  // Oldest first; `number` is the position, as VersionRepository::list() derives it.
  const versions = [];
  // What ForkController::store was asked to create, in order.
  const forks = [];
  let clock = Date.parse('2026-10-13T12:00:00Z');

  function saveVersion(xml, { provisional = false } = {}) {
    content = xml;
    clock += 60_000;
    versions.push({
      id: 'v' + (versions.length + 1),
      content: xml,
      savedAt: new Date(clock),
      author: 'Alice',
      name: null,
      provisional,
      hidden: false,
    });
  }

  // VersionController::versions: flat, newest first, hidden ones omitted.
  function currentId() {
    return versions.length ? versions[versions.length - 1].id : null;
  }

  function history() {
    const current = currentId();

    return versions
      .map((version, index) => ({ version, number: index + 1 }))
      .reverse()
      .filter(({ version }) => !version.hidden)
      .map(({ version, number }) => ({
        id: version.id,
        is_current: version.id === current,
        saved_at: version.savedAt.toISOString(),
        author: version.author,
        number,
        name: version.name ?? (version.provisional ? 'Provisional version' : 'Version ' + number),
        provisional: version.provisional,
      }));
  }

  // The provisional-only actions answer 409 for any other Version.
  function provisionalOnly(change) {
    return version => {
      if (!version.provisional) return json(409, { message: 'Conflict' });
      change(version);
      return json(200, { id: version.id });
    };
  }

  // One handler per "<METHOD> <action>" on /versions/{id}[/<action>].
  const versionActions = {
    'GET content': version => json(200, { id: version.id, content: version.content }),
    'POST restore': version => {
      saveVersion(version.content);
      return json(200, { restored: true });
    },
    'PUT rename': (version, body) => {
      version.name = body.name;
      return json(200, { id: version.id, name: body.name });
    },
    'POST promote': provisionalOnly(version => { version.provisional = false; }),
    'DELETE ': provisionalOnly(version => { version.hidden = true; }),
  };

  function route(method, path, body) {
    const local = path.replace(new URL(BASE).pathname, '');

    if (local === '/content' && method === 'GET') return json(200, { content, version: currentId() });
    if (local === '/content' && method === 'PUT') {
      // VersionController-like optimistic check: only when base_version is sent.
      if ('base_version' in body && body.base_version !== currentId()) {
        return json(409, { message: 'Conflict', current_version: currentId() });
      }
      saveVersion(body.content);
      return json(200, { saved: true, version: currentId() });
    }
    if (local === '/fork' && method === 'POST') {
      forks.push(body.content);
      return json(201, { url: `http://bpmn.test/diagrams/fork-${forks.length}` });
    }
    if (local === '/versions' && method === 'GET') return json(200, { versions: history() });

    const match = local.match(/^\/versions\/([^/]+)(?:\/(\w+))?$/);
    const handler = match && versionActions[`${method} ${match[2] || ''}`];
    const version = handler && versions.find(v => v.id === decodeURIComponent(match[1]));

    return version ? handler(version, body) : json(404, { message: 'Not Found' });
  }

  async function fetch(url, init = {}) {
    const method = init.method || 'GET';
    const headers = init.headers || {};
    const body = init.body !== undefined ? JSON.parse(init.body) : undefined;

    if (method !== 'GET' && headers['X-CSRF-TOKEN'] !== CSRF_TOKEN) {
      return json(419, { message: 'CSRF token mismatch.' });
    }

    return route(method, new URL(url).pathname, body);
  }

  return {
    forks,
    fetch,
    // A Version kept by an expiring Lock: only the server creates these.
    addProvisional: xml => saveVersion(xml, { provisional: true }),
  };
}
