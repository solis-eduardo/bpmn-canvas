// Server-side rendering of a BPMN diagram to SVG, PNG or PDF (issue #250).
//
// Reads {xml, format, title_block} as JSON from stdin, writes the rendered
// bytes to stdout. bpmn-js runs inside headless Chromium (it needs a DOM);
// the result is then drawn on a second page with JavaScript disabled, as an
// <img>, so nothing in the Diagram can execute there. Every network request
// is refused.

import { existsSync } from 'node:fs';
import { createRequire } from 'node:module';
import puppeteer from 'puppeteer-core';
import { pdfHtml, svgDataUri, svgSize } from './lib.mjs';

const require = createRequire(import.meta.url);
const VIEWER = require.resolve('bpmn-js/dist/bpmn-viewer.production.min.js');
const MAX_PIXELS = 8000;
const SCALE = 2;

function chromiumPath() {
  const candidates = [process.env.CHROMIUM_PATH, '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'];
  const found = candidates.find(path => path && existsSync(path));
  if (!found) throw new Error('No Chromium found; set CHROMIUM_PATH.');

  return found;
}

async function readStdin() {
  const chunks = [];
  for await (const chunk of process.stdin) chunks.push(chunk);

  return JSON.parse(Buffer.concat(chunks).toString('utf8'));
}

async function newLockedPage(browser, { javaScript }) {
  const page = await browser.newPage();
  await page.setJavaScriptEnabled(javaScript);
  await page.setRequestInterception(true);
  page.on('request', request => (request.url().startsWith('data:') ? request.continue() : request.abort()));

  return page;
}

async function toSvg(browser, xml) {
  const page = await newLockedPage(browser, { javaScript: true });
  await page.setContent('<!doctype html><html><body><div id="canvas"></div></body></html>');
  await page.addScriptTag({ path: VIEWER });

  const result = await page.evaluate(async source => {
    const viewer = new BpmnJS({ container: '#canvas' });
    await viewer.importXML(source);

    return (await viewer.saveSVG()).svg;
  }, xml);
  await page.close();

  return result;
}

async function toPng(browser, svg) {
  const { width, height } = svgSize(svg);
  const scale = Math.min(SCALE, MAX_PIXELS / Math.max(width, height));
  const page = await newLockedPage(browser, { javaScript: false });
  await page.setViewport({ width: Math.ceil(width), height: Math.ceil(height), deviceScaleFactor: scale });
  await page.setContent(`<!doctype html><body style="margin:0;background:#fff"><img style="display:block" src="${svgDataUri(svg)}"></body>`);
  const png = await page.screenshot({ type: 'png', fullPage: true });
  await page.close();

  return Buffer.from(png);
}

async function toPdf(browser, svg, titleBlock) {
  const page = await newLockedPage(browser, { javaScript: false });
  await page.setContent(pdfHtml(svg, titleBlock));
  const pdf = await page.pdf({ preferCSSPageSize: true, printBackground: true });
  await page.close();

  return Buffer.from(pdf);
}

const { xml, format, title_block: titleBlock } = await readStdin();
const browser = await puppeteer.launch({
  executablePath: chromiumPath(),
  headless: true,
  // The container gives Chromium no user namespaces for its own sandbox; the
  // page content is isolated above instead (no network, no JS on the result).
  args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--disable-extensions'],
});

try {
  const svg = await toSvg(browser, xml);
  const output = format === 'svg' ? Buffer.from(svg, 'utf8')
    : format === 'png' ? await toPng(browser, svg)
      : format === 'pdf' ? await toPdf(browser, svg, titleBlock)
        : (() => { throw new Error(`Unsupported format: ${format}`); })();

  process.stdout.write(output);
} finally {
  await browser.close();
}
