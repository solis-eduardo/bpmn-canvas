// Service Worker: forwards every request under <scope>app/ to the page (the shell), which owns
// the php-wasm instance, and turns the answer into a Response.
const SCOPE = new URL(self.registration.scope).pathname; // e.g. /bpmn-canvas/
const APP = SCOPE + 'app/';

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));

async function shellClient() {
  const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  return clients.find((client) => {
    const path = new URL(client.url).pathname;
    return path === SCOPE || path === SCOPE + 'index.html';
  });
}

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin || !url.pathname.startsWith(APP)) return;
  event.respondWith(handle(event.request, url));
});

async function handle(request, url) {
  const shell = await shellClient();
  if (!shell) {
    // The app URL was opened without the shell (e.g. a bookmark): send the visitor to the shell.
    return Response.redirect(SCOPE, 302);
  }
  const headers = {};
  request.headers.forEach((value, key) => (headers[key] = value));
  // Browsers forbid page JS from setting Sec-Fetch-*. Laravel 13's PreventRequestForgery trusts
  // `Sec-Fetch-Site: same-origin`; everything routed here is same-origin by construction.
  headers['sec-fetch-site'] = 'same-origin';
  const body = ['GET', 'HEAD'].includes(request.method) ? undefined : await request.arrayBuffer();

  const channel = new MessageChannel();
  const answer = new Promise((resolve) => (channel.port1.onmessage = (message) => resolve(message.data)));
  shell.postMessage(
    { type: 'php-request', method: request.method, url: url.pathname + url.search, headers, body },
    body ? [channel.port2, body] : [channel.port2]
  );
  const result = await answer;
  if (result.error) return new Response(result.error, { status: 500, headers: { 'content-type': 'text/plain' } });

  const responseHeaders = new Headers();
  for (const [key, values] of Object.entries(result.headers)) {
    if (key === 'set-cookie') continue; // the cookie jar lives in the shell
    for (const value of values) responseHeaders.append(key, value);
  }
  const nullBody = [101, 204, 205, 304].includes(result.status);
  return new Response(nullBody ? null : result.body, { status: result.status, headers: responseHeaders });
}
