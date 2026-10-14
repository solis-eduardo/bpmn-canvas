// Download size of what a first visit fetches: shell + php-wasm (one of the two 8.4 builds, picked by
// the browser: JSPI or Asyncify) + app.zip. Compresses each file the way a static host would.
// Run: node measure-size.mjs   (needs ./bundle from build-bundle.sh)
import { readFileSync, readdirSync } from 'node:fs';
import { brotliCompressSync, gzipSync, constants } from 'node:zlib';

const dir = 'bundle/';
const mb = (n) => (n / 1048576).toFixed(2).padStart(7) + ' MiB';
const row = (name, raw) => {
  const gz = gzipSync(raw, { level: 9 }).length;
  const br = brotliCompressSync(raw, { params: { [constants.BROTLI_PARAM_QUALITY]: 11, [constants.BROTLI_PARAM_SIZE_HINT]: raw.length } }).length;
  console.log(name.padEnd(34), mb(raw.length), mb(gz), mb(br));
  return { raw: raw.length, gz, br };
};
console.log('file'.padEnd(34), '    raw     ', '   gzip     ', '  brotli');
const all = readdirSync(dir + 'assets');
const wasms = all.filter((f) => f.endsWith('.wasm'));
const base = ['index.html', 'sw.js', 'app.zip'].map((f) => dir + f).concat(all.filter((f) => /^(index|events|rolldown-runtime|web-8-4)-.*\.js$/.test(f)).map((f) => dir + 'assets/' + f));
const loaders = all.filter((f) => /^php_8_4-.*\.js$/.test(f));
const total = (wasm, loader) => {
  const t = { raw: 0, gz: 0, br: 0 };
  for (const f of [...base, dir + 'assets/' + wasm, dir + 'assets/' + loader]) {
    const r = row(f.replace(dir, ''), readFileSync(f));
    for (const k in t) t[k] += r[k];
  }
  return t;
};
// The loader .js and wasm are paired by build (jspi vs asyncify); both pairings are reported.
for (const [i, w] of wasms.entries()) {
  console.log(`\n--- variant ${i + 1}: ${w}`);
  const t = total(w, loaders[i] ?? loaders[0]);
  console.log('TOTAL'.padEnd(34), mb(t.raw), mb(t.gz), mb(t.br));
}
