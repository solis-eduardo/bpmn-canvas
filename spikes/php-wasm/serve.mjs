// Minimal static server that behaves like GitHub Pages for this test: NO COOP/COEP headers,
// served under a sub-path (/bpmn-canvas/), brotli-compressed on the fly for compressible types.
// Usage: node serve.mjs [dir=bundle] [port=8081] [prefix=/bpmn-canvas/]
import http from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import { brotliCompressSync, gzipSync, constants } from 'node:zlib';
import path from 'node:path';

const dir = path.resolve(process.argv[2] ?? 'bundle');
const port = Number(process.argv[3] ?? 8081);
const prefix = process.argv[4] ?? '/bpmn-canvas/';
const types = { '.html': 'text/html', '.js': 'text/javascript', '.mjs': 'text/javascript', '.css': 'text/css', '.wasm': 'application/wasm', '.zip': 'application/zip', '.dat': 'application/octet-stream', '.json': 'application/json' };
const cache = new Map();

http.createServer(async (req, res) => {
  const url = new URL(req.url, 'http://x');
  if (!url.pathname.startsWith(prefix)) { res.writeHead(404); return res.end('not under ' + prefix); }
  let rel = decodeURIComponent(url.pathname.slice(prefix.length)) || 'index.html';
  let file = path.join(dir, rel);
  try { if ((await stat(file)).isDirectory()) file = path.join(file, 'index.html'); } catch { res.writeHead(404); return res.end('404'); }
  const ext = path.extname(file);
  const accept = req.headers['accept-encoding'] ?? '';
  const key = file + (accept.includes('br') ? ':br' : accept.includes('gzip') ? ':gz' : '');
  if (!cache.has(key)) {
    const raw = await readFile(file);
    const compressible = ['.html', '.js', '.css', '.wasm', '.json', '.dat'].includes(ext) || ext === '.zip' && false;
    if (compressible && accept.includes('br')) cache.set(key, { enc: 'br', body: brotliCompressSync(raw, { params: { [constants.BROTLI_PARAM_QUALITY]: 5 } }) });
    else if (compressible && accept.includes('gzip')) cache.set(key, { enc: 'gzip', body: gzipSync(raw) });
    else cache.set(key, { enc: null, body: raw });
  }
  const { enc, body } = cache.get(key);
  const h = { 'content-type': types[ext] ?? 'application/octet-stream', 'content-length': body.length, 'cache-control': process.env.CACHE ? 'public, max-age=600' : 'no-store' };
  if (enc) h['content-encoding'] = enc;
  res.writeHead(200, h);
  res.end(body);
}).listen(port, () => console.log(`serving ${dir} at http://localhost:${port}${prefix}`));
