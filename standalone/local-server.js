/*
 * What the server build's pages ask the server for in the background, answered
 * from this browser (ADR-0019). The pages are rendered for ORIGIN, so a request to
 * it can never reach a real server: it gets the local answer or a 404.
 *
 * Only what has a local counterpart so far: the notification bell, which has
 * nothing to show until the standalone build keeps notifications of its own.
 */

/**
 * @param {string} origin the origin the rendered pages' URLs point at
 * @returns {(url: string, init?: RequestInit) => Response | null} the answer for
 *   a request to `origin`, or null for any other URL
 */
export function createLocalServer(origin) {
  const json = (body, status = 200) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });

  return function answer(url, init = {}) {
    const target = new URL(url, origin);
    if (target.origin !== origin) return null;

    const method = (init.method || 'GET').toUpperCase();

    if (method === 'GET' && target.pathname === '/notifications/unread-count') return json({ count: 0 });
    if (method === 'GET' && target.pathname === '/notifications') return json([]);
    if (method === 'POST' && target.pathname === '/notifications/read-all') return json({});

    return json({ message: 'Not available in the standalone build.' }, 404);
  };
}

/** Routes the page's fetch() calls to the origin through `answer`, leaving every other URL alone. */
export function installLocalServer(answer, target = window) {
  const network = target.fetch.bind(target);

  target.fetch = (input, init) => {
    const url = typeof input === 'string' ? input : (input.url ?? String(input));

    return Promise.resolve(answer(url, init) ?? network(input, init));
  };
}
