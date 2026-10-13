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
  let clock = Date.parse('2026-10-13T12:00:00Z');
  const requests = [];

  function commit(xml, { provisional = false } = {}) {
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

  function find(id) {
    return versions.find(v => v.id === id);
  }

  // VersionController::versions: grouped by day, newest first, hidden ones omitted.
  function history() {
    const currentId = versions.length ? versions[versions.length - 1].id : null;
    const days = new Map();

    versions
      .map((version, index) => ({ version, number: index + 1 }))
      .reverse()
      .filter(({ version }) => !version.hidden)
      .forEach(({ version, number }) => {
        const day = version.savedAt.toISOString().slice(0, 10);
        if (!days.has(day)) days.set(day, []);
        days.get(day).push({
          id: version.id,
          is_current: version.id === currentId,
          saved_at: version.savedAt.toISOString(),
          author: version.author,
          number,
          name: version.name ?? (version.provisional ? 'Provisional version' : 'Version ' + number),
          renamed: version.name !== null,
          provisional: version.provisional,
        });
      });

    return [...days].map(([day, items]) => ({ day, items }));
  }

  function route(method, path, body) {
    const p = path.replace(new URL(BASE).pathname, '');
    let m;

    if (p === '/content' && method === 'GET') return json(200, { content });
    if (p === '/content' && method === 'PUT') {
      commit(body.content);
      return json(200, { saved: true });
    }
    if (p === '/versions' && method === 'GET') return json(200, { versions: history() });

    if ((m = p.match(/^\/versions\/([^/]+)\/content$/)) && method === 'GET') {
      const version = find(decodeURIComponent(m[1]));
      return version ? json(200, { id: version.id, content: version.content }) : json(404, { message: 'Not Found' });
    }
    if ((m = p.match(/^\/versions\/([^/]+)\/restore$/)) && method === 'POST') {
      const version = find(decodeURIComponent(m[1]));
      if (!version) return json(404, { message: 'Not Found' });
      commit(version.content);
      return json(200, { restored: true });
    }
    if ((m = p.match(/^\/versions\/([^/]+)\/rename$/)) && method === 'PUT') {
      const version = find(decodeURIComponent(m[1]));
      if (!version) return json(404, { message: 'Not Found' });
      version.name = body.name;
      return json(200, { id: version.id, name: body.name, renamed: true });
    }
    if ((m = p.match(/^\/versions\/([^/]+)\/promote$/)) && method === 'POST') {
      const version = find(decodeURIComponent(m[1]));
      if (!version) return json(404, { message: 'Not Found' });
      if (!version.provisional) return json(409, { message: 'Conflict' });
      version.provisional = false;
      return json(200, { id: version.id });
    }
    if ((m = p.match(/^\/versions\/([^/]+)$/)) && method === 'DELETE') {
      const version = find(decodeURIComponent(m[1]));
      if (!version) return json(404, { message: 'Not Found' });
      if (!version.provisional) return json(409, { message: 'Conflict' });
      version.hidden = true;
      return json(200, { id: version.id });
    }

    return json(404, { message: 'Not Found' });
  }

  async function fetch(url, init = {}) {
    const method = init.method || 'GET';
    const headers = init.headers || {};
    const body = init.body !== undefined ? JSON.parse(init.body) : undefined;
    requests.push({ url, method, headers, body });

    if (method !== 'GET' && headers['X-CSRF-TOKEN'] !== CSRF_TOKEN) {
      return json(419, { message: 'CSRF token mismatch.' });
    }

    return route(method, new URL(url).pathname, body);
  }

  return {
    fetch,
    requests,
    // A Version kept by an expiring Lock: only the server creates these.
    addProvisional: xml => commit(xml, { provisional: true }),
  };
}
