// Shell page: boots php-wasm, unpacks the Laravel app, mounts OPFS persistence, and answers
// the Service Worker's forwarded requests. The app itself is shown in an <iframe> under ./app/.
import { PHP, PHPRequestHandler } from '@php-wasm/universal';
import { loadWebRuntime, createDirectoryHandleMountHandler } from '@php-wasm/web';

const BASE = new URL('./', location.href).pathname; // "/" locally, "/<repo>/" on Pages
const APP_PREFIX = BASE + 'app/';
const t0 = performance.now();
const marks = {};
const logEl = document.getElementById('log');
const mark = (name) => {
  marks[name] = Math.round(performance.now() - t0);
  logEl.textContent += `${String(marks[name]).padStart(6)} ms  ${name}\n`;
  console.log('[spike]', name, marks[name]);
};
window.__spike = { marks };

async function main() {
  // 1. Service Worker (must control the page before the iframe loads).
  const reg = await navigator.serviceWorker.register(`${BASE}sw.js`, { scope: BASE });
  await navigator.serviceWorker.ready;
  if (!navigator.serviceWorker.controller) await new Promise((r) => navigator.serviceWorker.addEventListener('controllerchange', r, { once: true }));
  mark('service worker active');

  // 2. PHP 8.4 runtime (wasm download + compile + init).
  const php = new PHP(await loadWebRuntime('8.4'));
  mark('php runtime ready');

  // 3. App bundle: one zip (vendor, views, public assets, seed DB), unzipped by PHP's own ZipArchive.
  const zip = await fetch('./app.zip').then((r) => r.arrayBuffer());
  mark(`app.zip downloaded (${(zip.byteLength / 1048576).toFixed(1)} MiB)`);
  await php.mkdir('/www');
  await php.mkdir('/persist');
  await php.writeFile('/tmp/app.zip', new Uint8Array(zip));
  const un = await php.run({
    code: `<?php $z = new ZipArchive; if ($z->open('/tmp/app.zip') !== true) { echo 'zip open failed'; exit(1); }
      $z->extractTo('/www'); $z->close(); unlink('/tmp/app.zip');
      rename('/www/db.sqlite', '/seed.sqlite'); @mkdir('/www/storage/app/diagrams', 0777, true);
      echo 'ok';`,
  });
  if (un.text !== 'ok') throw new Error('unzip failed: ' + un.text + un.errors);
  mark('app unzipped into MEMFS');

  // 4. Persistence: OPFS directories mounted over the DB dir and the diagrams dir.
  const root = await navigator.storage.getDirectory();
  const persistDir = await root.getDirectoryHandle('persist', { create: true });
  const diagramsDir = await root.getDirectoryHandle('diagrams', { create: true });
  let seeded = false;
  for await (const _ of persistDir.keys()) { seeded = true; break; }
  window.__spike.restoredFromOpfs = seeded;
  if (!seeded) await php.run({ code: `<?php copy('/seed.sqlite', '/persist/db.sqlite'); echo 'seeded';` });
  const direction = seeded ? 'opfs-to-memfs' : 'memfs-to-opfs';
  const mounts = [];
  const mountDir = (handle, path) =>
    php.mount(path, createDirectoryHandleMountHandler(handle, { initialSync: { direction }, onMount: (m) => mounts.push(m) }));
  await mountDir(persistDir, '/persist');
  await mountDir(diagramsDir, '/www/storage/app/diagrams');
  window.__spike.flush = () => Promise.all(mounts.map((m) => m.flush()));
  mark(seeded ? 'OPFS state restored (existing data)' : 'OPFS mounted (fresh seed)');

  // 5. Request handler. The cookie jar lives here because a SW cannot set cookies.
  const jarKey = 'spike-cookie-jar';
  const jar = JSON.parse(localStorage.getItem(jarKey) ?? '{}');
  const handler = new PHPRequestHandler({
    php,
    documentRoot: '/www/public',
    absoluteUrl: location.origin + APP_PREFIX,
    getFileNotFoundAction: () => ({ type: 'internal-redirect', uri: '/index.php' }),
    cookieStore: {
      rememberCookiesFromResponseHeaders(h) {
        for (const c of h?.['set-cookie'] ?? []) {
          const i = c.indexOf('=');
          const [value, ...attrs] = c.slice(i + 1).split(';');
          const expired = /max-age=0|expires=thu, 01 jan 1970/i.test(attrs.join(';')) || value === 'deleted';
          if (expired) delete jar[c.slice(0, i)];
          else jar[c.slice(0, i)] = value;
        }
        localStorage.setItem(jarKey, JSON.stringify(jar));
      },
      getCookieRequestHeader: () => Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; '),
    },
  });

  navigator.serviceWorker.addEventListener('message', async (e) => {
    if (e.data?.type !== 'php-request') return;
    const port = e.ports[0];
    try {
      const { method, url, headers, body } = e.data;
      const started = performance.now();
      const res = await handler.request({
        method,
        url: location.origin + url,
        headers: { ...headers, 'x-app-base': APP_PREFIX.replace(/\/$/, '') },
        body: body && body.byteLength ? new Uint8Array(body) : undefined,
      });
      const out = {};
      for (const [k, v] of Object.entries(res.headers)) if (!['content-length', 'transfer-encoding', 'connection'].includes(k)) out[k] = v;
      const bytes = res.bytes.slice().buffer;
      (window.__spike.requests ??= []).push({ method, url, status: res.httpStatusCode, ms: Math.round(performance.now() - started) });
      port.postMessage({ status: res.httpStatusCode, headers: out, body: bytes }, [bytes]);
      if (method !== 'GET') window.__spike.flush().catch((err) => console.error('OPFS flush failed', err));
    } catch (err) {
      console.error(err);
      port.postMessage({ error: String(err?.stack ?? err) });
    }
  });
  window.__spike.handler = handler; window.__spike.php = php;
  mark('request handler ready');

  const frame = document.getElementById('app');
  frame.addEventListener('load', () => {
    if (!marks['first app page rendered (time to interactive)']) {
      mark('first app page rendered (time to interactive)');
      document.getElementById('timing').textContent = `time to interactive: ${marks['first app page rendered (time to interactive)']} ms`;
    }
  });
  frame.src = APP_PREFIX;
  document.getElementById('boot').style.display = 'none';
  document.getElementById('bar').style.display = 'block';
  frame.style.display = 'block';
  frame.style.flex = '1';
}

document.getElementById('reset').addEventListener('click', async () => {
  const root = await navigator.storage.getDirectory();
  for (const d of ['persist', 'diagrams']) await root.removeEntry(d, { recursive: true }).catch(() => {});
  localStorage.clear();
  location.reload();
});

main().catch((e) => {
  console.error(e);
  logEl.textContent += '\nFAILED: ' + (e?.stack ?? e);
  window.__spike.error = String(e?.stack ?? e);
});
