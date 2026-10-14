// Smoke test for the browser demo bundle: serves demo/dist the way GitHub Pages does (a sub-path, no
// COOP/COEP headers), boots it in headless Chrome, signs in as the seeded Author and opens a Diagram in
// the Editor. Exits non-zero when any step fails; the deploy job depends on it.
//
// Usage: node smoke.mjs [dist-dir]   (CHROME_PATH overrides the Chrome binary)
import http from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import path from 'node:path';
import puppeteer from 'puppeteer-core';

const DIST = path.resolve(process.argv[2] ?? 'dist');
const PREFIX = '/bpmn-canvas/';
const CHROME = process.env.CHROME_PATH ?? '/usr/bin/google-chrome';
const STEP_TIMEOUT = 240_000;
const CREDENTIALS = { email: 'author@demo.test', password: 'demo-password' };

const TYPES = {
    '.html': 'text/html; charset=utf-8',
    '.js': 'text/javascript',
    '.mjs': 'text/javascript',
    '.css': 'text/css',
    '.json': 'application/json',
    '.wasm': 'application/wasm',
    '.zip': 'application/zip',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.woff2': 'font/woff2',
    '.woff': 'font/woff',
};

function serve() {
    const server = http.createServer(async (req, res) => {
        const { pathname } = new URL(req.url, 'http://localhost');
        if (!pathname.startsWith(PREFIX)) {
            res.writeHead(404).end('not found');
            return;
        }
        const relative = decodeURIComponent(pathname.slice(PREFIX.length)) || 'index.html';
        let file = path.join(DIST, relative);
        if (!file.startsWith(DIST + path.sep)) {
            res.writeHead(403).end('forbidden');
            return;
        }
        try {
            if ((await stat(file)).isDirectory()) file = path.join(file, 'index.html');
            const body = await readFile(file);
            res.writeHead(200, {
                'content-type': TYPES[path.extname(file)] ?? 'application/octet-stream',
                'content-length': body.length,
                'cache-control': 'no-store',
            });
            res.end(body);
        } catch {
            res.writeHead(404).end('not found');
        }
    });
    return new Promise((resolve) => server.listen(0, 'localhost', () => resolve(server)));
}

// The app runs in an iframe under <base>app/; find it once its document exists.
async function appFrame(page) {
    const deadline = Date.now() + STEP_TIMEOUT;
    while (Date.now() < deadline) {
        const frame = page.frames().find((candidate) => new URL(candidate.url() || 'about:blank', 'http://x').pathname.includes(`${PREFIX}app/`));
        if (frame) return frame;
        await new Promise((resolve) => setTimeout(resolve, 250));
    }
    throw new Error('the app iframe never loaded');
}

async function step(name, action) {
    const started = Date.now();
    await action();
    console.log(`ok   ${name} (${Date.now() - started} ms)`);
}

const server = await serve();
const { port } = server.address();
const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

let failed = false;
let page;
try {
    page = await browser.newPage();
    const shellErrors = [];
    page.on('pageerror', (error) => shellErrors.push(error.message));

    await step('shell loads and the service worker takes control', async () => {
        await page.goto(`http://localhost:${port}${PREFIX}`, { waitUntil: 'load', timeout: STEP_TIMEOUT });
        await page.waitForSelector('#app', { timeout: STEP_TIMEOUT });
    });

    await step('login page renders inside the app', async () => {
        const frame = await appFrame(page);
        await frame.waitForSelector('form[action$="/login"] input[name="email"]', { timeout: STEP_TIMEOUT });
    });

    await step('signs in as the seeded Author', async () => {
        const frame = await appFrame(page);
        await frame.type('form[action$="/login"] input[name="email"]', CREDENTIALS.email);
        await frame.type('form[action$="/login"] input[name="password"]', CREDENTIALS.password);
        await Promise.all([
            frame.waitForFunction(() => location.pathname.endsWith('/home'), { timeout: STEP_TIMEOUT }),
            frame.click('form[action$="/login"] [type="submit"]'),
        ]);
    });

    await step('lists the Diagrams', async () => {
        const frame = await appFrame(page);
        await frame.evaluate(() => {
            location.href = location.href.replace(/\/home.*$/, '/diagrams');
        });
        await frame.waitForFunction(() => location.pathname.endsWith('/diagrams'), { timeout: STEP_TIMEOUT });
    });

    await step('opens a Diagram in the Editor', async () => {
        const frame = await appFrame(page);
        const href = await frame.evaluate(() => {
            const links = [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href'));
            return links.find((link) => /\/diagrams\/[^/]+$/.test(link) && !link.endsWith('/create'));
        });
        if (!href) throw new Error('no Diagram link on the list page');
        await frame.evaluate((target) => {
            location.href = target;
        }, href);
        await frame.waitForSelector('.djs-container .djs-element', { timeout: STEP_TIMEOUT });
    });

    if (shellErrors.length > 0) {
        throw new Error(`uncaught errors in the shell: ${shellErrors.join('; ')}`);
    }
    console.log('smoke test passed');
} catch (error) {
    failed = true;
    console.error(`FAILED: ${error.message}`);
    // The shell's boot log says which step of the boot broke (unzip, OPFS, ...).
    const bootLog = page ? await page.$eval('#log', (log) => log.textContent).catch(() => '') : '';
    if (bootLog.trim()) console.error(`shell boot log:\n${bootLog}`);
} finally {
    await browser.close();
    server.close();
}

process.exit(failed ? 1 : 0);
