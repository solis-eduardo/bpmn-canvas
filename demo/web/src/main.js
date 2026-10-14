// Shell page of the browser demo (ADR-0018): boots php-wasm, unpacks the Laravel app, mounts the
// OPFS directories that hold what the visitor changes, and answers the requests the Service Worker
// forwards from the app's iframe. Everything runs in this page; nothing is sent to a server.
import { PHP, PHPRequestHandler } from '@php-wasm/universal';
import { loadWebRuntime, createDirectoryHandleMountHandler } from '@php-wasm/web';

const BASE = new URL('./', location.href).pathname; // "/" locally, "/<repo>/" on GitHub Pages
const APP_PREFIX = `${BASE}app/`;
const COOKIE_JAR_KEY = 'bpmn-demo-cookie-jar';
// OPFS directories holding the visitor's data, and where each one is mounted inside the app.
const PERSISTED = [
    { opfs: 'persist', path: '/persist' },
    { opfs: 'diagrams', path: '/www/storage/app/diagrams' },
    { opfs: 'sessions', path: '/www/storage/framework/sessions' },
];

const logEl = document.getElementById('log');
const startedAt = performance.now();
function progress(message) {
    logEl.textContent += `${String(Math.round(performance.now() - startedAt)).padStart(6)} ms  ${message}\n`;
}

// A Service Worker cannot set cookies (the session cookie is HttpOnly), so the cookie jar lives in
// localStorage. Storage can be blocked (private windows, cleared site data): then the session is
// simply not remembered between requests.
function readCookieJar() {
    try {
        return JSON.parse(localStorage.getItem(COOKIE_JAR_KEY) ?? '{}');
    } catch {
        return {};
    }
}

function writeCookieJar(jar) {
    try {
        localStorage.setItem(COOKIE_JAR_KEY, JSON.stringify(jar));
    } catch {
        // Not remembered; see above.
    }
}

async function boot() {
    // 1. The Service Worker must control this page before the app's iframe loads.
    await navigator.serviceWorker.register(`${BASE}sw.js`, { scope: BASE });
    await navigator.serviceWorker.ready;
    if (!navigator.serviceWorker.controller) {
        await new Promise((resolve) => navigator.serviceWorker.addEventListener('controllerchange', resolve, { once: true }));
    }
    progress('service worker active');

    // 2. PHP 8.4 (WebAssembly download, compile and init).
    const php = new PHP(await loadWebRuntime('8.4'));
    progress('php runtime ready');

    // 3. The app: one zip with vendor/, views, public assets and the seeded SQLite file.
    const zip = await fetch(new URL('app.zip', location.href)).then((response) => {
        if (!response.ok) throw new Error(`app.zip: HTTP ${response.status}`);
        return response.arrayBuffer();
    });
    progress(`app.zip downloaded (${(zip.byteLength / 1048576).toFixed(1)} MiB)`);
    await php.mkdir('/www');
    await php.mkdir('/persist');
    await php.writeFile('/tmp/app.zip', new Uint8Array(zip));
    const unpacked = await php.run({
        code: `<?php $z = new ZipArchive; if ($z->open('/tmp/app.zip') !== true) { echo 'zip open failed'; exit(1); }
            $z->extractTo('/www'); $z->close(); unlink('/tmp/app.zip');
            rename('/www/database/demo-seed.sqlite', '/seed.sqlite');
            foreach (['storage/app/diagrams', 'storage/framework/sessions', 'storage/framework/views', 'storage/framework/cache', 'storage/logs', 'bootstrap/cache'] as $dir) { @mkdir('/www/'.$dir, 0777, true); }
            echo 'ok';`,
    });
    if (unpacked.text !== 'ok') throw new Error(`unzip failed: ${unpacked.text}${unpacked.errors}`);
    progress('app unzipped into memory');

    // 4. Persistence: OPFS directories mounted over the paths the app writes to. On the first visit
    //    they are seeded from memory; afterwards the stored state is loaded into memory.
    const root = await navigator.storage.getDirectory();
    const handles = {};
    for (const { opfs } of PERSISTED) handles[opfs] = await root.getDirectoryHandle(opfs, { create: true });
    let restored = false;
    for await (const _entry of handles.persist.keys()) {
        restored = true;
        break;
    }
    if (!restored) await php.run({ code: `<?php copy('/seed.sqlite', '/persist/database.sqlite'); echo 'seeded';` });
    const direction = restored ? 'opfs-to-memfs' : 'memfs-to-opfs';
    const mounts = [];
    for (const { opfs, path } of PERSISTED) {
        await php.mount(path, createDirectoryHandleMountHandler(handles[opfs], {
            initialSync: { direction },
            onMount: (mount) => mounts.push(mount),
        }));
    }
    const flush = () => Promise.all(mounts.map((mount) => mount.flush()));
    progress(restored ? 'restored the saved demo data' : 'created the demo data');

    // 5. Requests from the Service Worker. One PHP instance: run them one at a time, since parallel
    //    requests re-enter the runtime and come back as HTTP 500.
    const jar = readCookieJar();
    const handler = new PHPRequestHandler({
        php,
        documentRoot: '/www/public',
        absoluteUrl: location.origin + APP_PREFIX,
        getFileNotFoundAction: () => ({ type: 'internal-redirect', uri: '/index.php' }),
        cookieStore: {
            rememberCookiesFromResponseHeaders(headers) {
                for (const cookie of headers?.['set-cookie'] ?? []) {
                    const separator = cookie.indexOf('=');
                    const [value, ...attributes] = cookie.slice(separator + 1).split(';');
                    const expired = /max-age=0|expires=thu, 01 jan 1970/i.test(attributes.join(';')) || value === 'deleted';
                    if (expired) delete jar[cookie.slice(0, separator)];
                    else jar[cookie.slice(0, separator)] = value;
                }
                writeCookieJar(jar);
            },
            getCookieRequestHeader: () => Object.entries(jar).map(([key, value]) => `${key}=${value}`).join('; '),
        },
    });

    let queue = Promise.resolve();
    const inOrder = (task) => (queue = queue.then(task, task));
    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type !== 'php-request') return;
        const port = event.ports[0];
        const { method, url, headers, body } = event.data;
        inOrder(async () => {
            try {
                const response = await handler.request({
                    method,
                    url: location.origin + url,
                    headers: { ...headers, 'x-app-base': APP_PREFIX.replace(/\/$/, '') },
                    body: body && body.byteLength ? new Uint8Array(body) : undefined,
                });
                const responseHeaders = {};
                for (const [key, values] of Object.entries(response.headers)) {
                    if (!['content-length', 'transfer-encoding', 'connection'].includes(key)) responseHeaders[key] = values;
                }
                const bytes = response.bytes.slice().buffer;
                port.postMessage({ status: response.httpStatusCode, headers: responseHeaders, body: bytes }, [bytes]);
                if (method !== 'GET') flush().catch((error) => console.error('OPFS flush failed', error));
            } catch (error) {
                console.error(error);
                port.postMessage({ error: String(error?.stack ?? error) });
            }
        });
    });

    // 6. The app runs in the iframe, under the same origin so the Service Worker answers it.
    const frame = document.getElementById('app');
    frame.addEventListener('load', () => {
        if (document.getElementById('timing').textContent === '') {
            document.getElementById('timing').textContent = `first page: ${Math.round(performance.now() - startedAt)} ms`;
        }
    }, { once: true });
    frame.src = APP_PREFIX;
    document.getElementById('boot').style.display = 'none';
    document.getElementById('bar').style.display = 'flex';
    frame.style.display = 'block';
}

document.getElementById('reset').addEventListener('click', async () => {
    // Drops everything the visitor changed; the next load seeds the demo data again.
    const root = await navigator.storage.getDirectory();
    for (const { opfs } of PERSISTED) await root.removeEntry(opfs, { recursive: true }).catch(() => {});
    try {
        localStorage.removeItem(COOKIE_JAR_KEY);
    } catch {
        // Nothing stored.
    }
    location.reload();
});

boot().catch((error) => {
    console.error(error);
    progress(`FAILED: ${error?.stack ?? error}`);
});
