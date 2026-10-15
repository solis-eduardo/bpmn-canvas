/*
 * HttpDiagramStore: the server build's DiagramStore (ADR-0019). Everything
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

import { httpError } from './common.js';

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
 * @param {Object} options.urls  contentUrl, saveUrl, forkUrl, versionsUrl and the
 *   version*UrlTemplate URLs (with a VERSION_ID placeholder), as rendered on
 *   the Editor's data-* attributes.
 * @param {string} options.csrfToken
 * @param {typeof fetch} [options.fetch]
 * @param {ReturnType<typeof createJsonClient>} [options.request]  a client
 *   shared with the rest of the page; built from csrfToken when omitted
 */
export function createHttpDiagramStore({ urls, csrfToken, fetch, request = createJsonClient({ csrfToken, fetch }) }) {

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

  // The Version the loaded content is at, sent back on save so the server can
  // refuse a save made over a newer Version. undefined until the first load,
  // when there is nothing to compare against.
  let baseVersion;

  return {
    /** @returns {Promise<string>} */
    async load() {
      const answer = await request('GET', urls.contentUrl);
      baseVersion = answer.version ?? null;

      return answer.content;
    },

    /**
     * Saves the content as a new Version. Unless `overwrite` is set, the save
     * names the Version the content was loaded from: when someone saved a newer
     * one since, the server refuses it and the rejection has `conflict` set.
     * `overwrite` saves on top of the newer Version (the history keeps both).
     */
    async save(xml, { overwrite = false } = {}) {
      const payload = { content: xml };
      if (!overwrite && baseVersion !== undefined) payload.base_version = baseVersion;

      try {
        const answer = await request('PUT', urls.saveUrl, payload);
        baseVersion = answer.version ?? null;
      } catch (error) {
        if (error.status === 409) {
          error.conflict = true;
          error.currentVersion = error.body?.current_version ?? null;
        }

        throw error;
      }
    },

    /**
     * Saves the given content (the unsaved edits on the canvas) as a Fork: a
     * new Diagram, leaving this one untouched. Resolves to where it lives.
     *
     * @returns {Promise<{url: string}>}
     */
    async fork(xml) {
      return request('POST', urls.forkUrl, { content: xml });
    },

    /**
     * Forks from one Version of the history instead of the current content,
     * optionally copying this Diagram's Tags. Resolves to where it lives.
     *
     * @returns {Promise<{url: string}>}
     */
    async forkVersion(versionId, { copyTags = false } = {}) {
      return request('POST', urls.forkUrl, { version: versionId, copy_tags: copyTags });
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
