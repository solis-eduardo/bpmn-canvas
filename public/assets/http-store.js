/*
 * HttpDiagramStore: the server build's DiagramStore (ADR-0018). Everything
 * the Editor knows about where a Diagram lives goes through this module,
 * which talks to DiagramContentController and VersionController.
 *
 * @typedef {Object} Version
 * @property {string}  id
 * @property {number}  number       sequential within the Diagram
 * @property {string}  name         "Version N" or a custom name
 * @property {string}  savedAt      ISO-8601
 * @property {?string} author
 * @property {boolean} provisional
 * @property {boolean} current
 */

// Builds the Error for a failed response, carrying the HTTP status and the
// localized `message` the server sent (when it sent one). Same shape as
// BpmnCommon.httpError, which the classic scripts still use until they
// become modules (ADR-0018).
export async function httpError(res) {
  const error = new Error('HTTP ' + res.status);
  error.status = res.status;
  try {
    error.body = await res.json();
    if (error.body && typeof error.body.message === 'string' && error.body.message) {
      error.serverMessage = error.body.message;
    }
  } catch (e) {
    // no JSON body
  }
  return error;
}

/**
 * A JSON request function for the Laravel endpoints: sends the CSRF token on
 * every change and rejects non-2xx answers with httpError's Error.
 *
 * @param {Object} options
 * @param {string} options.csrfToken
 * @param {typeof fetch} [options.fetch]
 * @returns {(method: string, url: string, payload?: Object) => Promise<any>}
 *   resolves to the parsed JSON answer
 */
export function createJsonClient({ csrfToken, fetch = globalThis.fetch.bind(globalThis) }) {
  return async function request(method, url, payload) {
    const headers = { Accept: 'application/json' };
    if (method !== 'GET') headers['X-CSRF-TOKEN'] = csrfToken;
    if (payload !== undefined) headers['Content-Type'] = 'application/json';

    const res = await fetch(url, {
      method,
      credentials: 'same-origin',
      headers,
      body: payload !== undefined ? JSON.stringify(payload) : undefined,
    });
    if (!res.ok) throw await httpError(res);

    return res.json();
  };
}

/**
 * @param {Object} options
 * @param {Object} options.urls  contentUrl, saveUrl, versionsUrl and the
 *   version*UrlTemplate URLs (with a VERSION_ID placeholder), as rendered on
 *   the Editor's data-* attributes.
 * @param {string} options.csrfToken
 * @param {typeof fetch} [options.fetch]
 */
export function createHttpDiagramStore({ urls, csrfToken, fetch }) {
  const request = createJsonClient({ csrfToken, fetch });

  function versionUrl(templateKey, id) {
    return urls[templateKey].replace('VERSION_ID', encodeURIComponent(id));
  }

  function toVersions(items) {
    return items.map(item => ({
      id: item.id,
      number: item.number,
      name: item.name,
      savedAt: item.saved_at,
      author: item.author ?? null,
      provisional: !!item.provisional,
      current: !!item.is_current,
    }));
  }

  return {
    /** @returns {Promise<string>} */
    async load() {
      return (await request('GET', urls.contentUrl)).content;
    },

    /** Saves the content as a new Version. */
    async save(xml) {
      await request('PUT', urls.saveUrl, { content: xml });
    },

    /** @returns {Promise<Version[]>} newest first, deleted Versions omitted */
    async versions() {
      return toVersions((await request('GET', urls.versionsUrl)).versions || []);
    },

    /** @returns {Promise<string>} */
    async versionContent(id) {
      return (await request('GET', versionUrl('versionContentUrlTemplate', id))).content;
    },

    /** Creates a new current Version with the given Version's content. */
    async restoreVersion(id) {
      await request('POST', versionUrl('versionRestoreUrlTemplate', id));
    },

    async renameVersion(id, name) {
      await request('PUT', versionUrl('versionRenameUrlTemplate', id), { name });
    },

    /** Makes a provisional Version permanent; rejects for any other Version. */
    async promoteVersion(id) {
      await request('POST', versionUrl('versionPromoteUrlTemplate', id));
    },

    /** Soft-deletes a provisional Version; rejects for any other Version. */
    async deleteVersion(id) {
      await request('DELETE', versionUrl('versionDeleteUrlTemplate', id));
    },
  };
}
