// Headless sanity run of the Laravel app inside php-wasm (Node build, same wasm as the browser).
// Run: node node-flow.mjs   (needs ./stage from build-stage.sh)
import { PHP, PHPRequestHandler } from '@php-wasm/universal';
import { loadNodeRuntime, createNodeFsMountHandler } from '@php-wasm/node';
import { fileURLToPath } from 'node:url';
import { cp, rm, readFile } from 'node:fs/promises';
import path from 'node:path';

const here = path.dirname(fileURLToPath(import.meta.url));
const work = path.join(here, '.node-work');
await rm(work, { recursive: true, force: true });
const t0 = performance.now();
const php = new PHP(await loadNodeRuntime('8.4', { emscriptenOptions: { processId: 1 } }));
console.log('php runtime ready ms', Math.round(performance.now() - t0));
await php.mkdir('/www');
if (process.env.BUNDLE) {
  // Same path as the browser shell: unzip bundle/app.zip into the in-memory FS (MEMFS) with ZipArchive.
  const zip = await readFile(path.join(here, 'bundle/app.zip'));
  await php.writeFile('/tmp/app.zip', new Uint8Array(zip));
  const t1 = performance.now();
  const un = await php.run({ code: `<?php $z = new ZipArchive; $z->open('/tmp/app.zip') === true or exit('open failed');
    $z->extractTo('/www'); $z->close(); unlink('/tmp/app.zip'); rename('/www/db.sqlite', '/seed.sqlite');
    @mkdir('/www/storage/app/diagrams', 0777, true); @mkdir('/persist'); copy('/seed.sqlite', '/persist/db.sqlite'); echo 'ok';` });
  console.log('unzip', un.text, Math.round(performance.now() - t1), 'ms');
} else {
  await cp(path.join(here, 'stage'), work, { recursive: true });
  await php.mount('/www', createNodeFsMountHandler(work));
  // Mirror the browser layout: the SQLite file lives in /persist (OPFS there, a Node dir here).
  await rm(path.join(here, '.node-persist'), { recursive: true, force: true });
  await cp(path.join(work, 'db.sqlite'), path.join(here, '.node-persist/db.sqlite'), { recursive: true });
  await php.mkdir('/persist');
  await php.mount('/persist', createNodeFsMountHandler(path.join(here, '.node-persist')));
}
// BASE=/bpmn-canvas/app simulates the GitHub Pages sub-path the Service Worker uses in the browser.
const BASE = (process.env.BASE ?? '').replace(/\/$/, '');
const handler = new PHPRequestHandler({
  php,
  cookieStore: {
    jar: {},
    rememberCookiesFromResponseHeaders(h) {
      for (const c of h?.['set-cookie'] ?? []) {
        const i = c.indexOf('=');
        this.jar[c.slice(0, i)] = c.slice(i + 1).split(';')[0];
      }
    },
    getCookieRequestHeader() {
      return Object.entries(this.jar).map(([k, v]) => `${k}=${v}`).join('; ');
    },
  },
  documentRoot: '/www/public',
  absoluteUrl: 'http://localhost:8080' + BASE,
  getFileNotFoundAction: () => ({ type: 'internal-redirect', uri: '/index.php' }),
});

// php-wasm wants Uint8Array bodies (string bodies are deprecated and lose the form fields).
const form = (o) => new TextEncoder().encode(new URLSearchParams(o).toString());
const hdr =(r, n) => r.headers[n.toLowerCase()]?.[0];
const csrf = (html) => /name="_token"\s+value="([^"]+)"/.exec(html)?.[1] ?? /name="csrf-token"\s+content="([^"]+)"/.exec(html)?.[1];
const step = async (label, req) => {
    const t = performance.now();
  const headers = { 'sec-fetch-site': 'same-origin', ...(BASE ? { 'x-app-base': BASE } : {}), ...(req.headers ?? {}) };
  const r = await handler.request({ ...req, url: 'http://localhost:8080' + BASE + req.url, headers });
  console.log(label.padEnd(28), r.httpStatusCode, hdr(r, 'location') ?? '', Math.round(performance.now() - t) + 'ms');
  return r;
};

let r = await step('GET /login', { url: '/login' });
if (r.httpStatusCode >= 500) console.log(r.text.slice(0, 2000));
let token = csrf(r.text);
console.log('login form action:', /<form[^>]*action="([^"]+)"/.exec(r.text)?.[1], '| first asset:', /(?:href|src)="([^"]*build\/assets[^"]+)"/.exec(r.text)?.[1]);
const asset = /(?:href|src)="[^"]*(\/build\/assets[^"]+\.(?:css|js))"/.exec(r.text)?.[1];
if (asset) { const a = await step('GET static ' + asset.slice(-24), { url: asset }); console.log('  content-type:', hdr(a, 'content-type'), 'bytes:', a.bytes.length); }
r = await step('POST /login', {
  url: '/login', method: 'POST',
  headers: { 'content-type': 'application/x-www-form-urlencoded' },
  body: form({ _token: token, email: 'demo@example.com', password: 'demo-password' }),
});
r = await step('GET /home', { url: '/home' });
r = await step('GET /diagrams', { url: '/diagrams' });
token = csrf(r.text);
r = await step('GET /diagrams/create', { url: '/diagrams/create' });
token = csrf(r.text) ?? token;
r = await step('POST /diagrams', {
  url: '/diagrams', method: 'POST',
  headers: { 'content-type': 'application/x-www-form-urlencoded' },
  body: form({ _token: token, title: 'Spike diagram' }),
});
if (r.httpStatusCode >= 400) console.log(r.text.slice(0, 1500));
const loc = hdr(r, 'location');
console.log('after create ->', loc);
r = await step('GET diagrams list', { url: '/diagrams' });
console.log('list has title:', r.text.includes('Spike diagram'));
const slug = /diagrams\/([^"\/?]+)\/(?:edit|content)/.exec(r.text)?.[1] ?? 'spike-diagram';
r = await step('GET editor workspace', { url: `/diagrams/${slug}` });
const meta = csrf(r.text);
r = await step('GET content', { url: `/diagrams/${slug}/content`, headers: { accept: 'application/json' } });
const xml = JSON.parse(r.text).content;
const edited = xml.replace('</bpmn:process>', '</bpmn:process><!-- saved in wasm -->');
r = await step('PUT content (save)', {
  url: `/diagrams/${slug}/content`, method: 'PUT',
  headers: { 'content-type': 'application/json', accept: 'application/json', 'x-csrf-token': meta },
  body: new TextEncoder().encode(JSON.stringify({ content: edited })),
});
console.log(r.text.slice(0, 300));
r = await step('GET content again', { url: `/diagrams/${slug}/content`, headers: { accept: 'application/json' } });
console.log('save persisted:', JSON.parse(r.text).content.includes('saved in wasm'));
process.exit(0);
