// Service Worker: forwards every request under <scope>app/ to the page ("shell"),
// which owns the php-wasm instance, and turns the answer into a Response.
const SCOPE = new URL(self.registration.scope).pathname; // e.g. /bpmn-canvas/
const APP = SCOPE + 'app/';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

async function shellClient() {
  const all = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  return all.find((c) => new URL(c.url).pathname === SCOPE || new URL(c.url).pathname === SCOPE + 'index.html');
}

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin || !url.pathname.startsWith(APP)) return;
  event.respondWith(handle(event.request, url));
});

async function handle(request, url) {
  const shell = await shellClient();
  if (!shell) {
    // App URL opened without the shell (e.g. a bookmark): bounce to the shell.
    return Response.redirect(SCOPE, 302);
  }
  const headers = {};
  request.headers.forEach((v, k) => (headers[k] = v));
  // Browsers forbid page JS from setting Sec-Fetch-*, and a SW cannot read them reliably.
  // Laravel 13's PreventRequestForgery trusts `Sec-Fetch-Site: same-origin`; everything routed
  // here is same-origin by construction.
  headers['sec-fetch-site'] = 'same-origin';
  const body = ['GET', 'HEAD'].includes(request.method) ? undefined : await request.arrayBuffer();

  const channel = new MessageChannel();
  const answer = new Promise((resolve) => (channel.port1.onmessage = (m) => resolve(m.data)));
  shell.postMessage(
    { type: 'php-request', method: request.method, url: url.pathname + url.search, headers, body },
    body ? [channel.port2, body] : [channel.port2]
  );
  const r = await answer;
  if (r.error) return new Response(r.error, { status: 500, headers: { 'content-type': 'text/plain' } });
  const h = new Headers();
  for (const [k, vs] of Object.entries(r.headers)) {
    if (k === 'set-cookie') continue; // jar lives in the shell
    for (const v of vs) h.append(k, v);
  }
  return new Response([101, 204, 205, 304].includes(r.status) ? null : r.body, { status: r.status, headers: h });
}
