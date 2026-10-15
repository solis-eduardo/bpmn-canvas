// Pure helpers of the server-side renderer, kept apart from render.mjs so
// they can be unit-tested without a browser.

export function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/** Width/height bpmn-js writes on the root <svg> element of its export. */
export function svgSize(svg) {
  const tag = /<svg\b[^>]*>/.exec(svg)?.[0] ?? '';
  const read = name => parseFloat(new RegExp(`\\b${name}="([\\d.]+)`).exec(tag)?.[1]);

  return { width: read('width') || 1000, height: read('height') || 800 };
}

export function svgDataUri(svg) {
  return 'data:image/svg+xml;base64,' + Buffer.from(svg, 'utf8').toString('base64');
}

/**
 * The page printed to PDF: a title block (title, Version, Diagram Owner,
 * date) above the Diagram. The Diagram is an <img> holding the SVG, so
 * nothing in it can execute.
 */
export function pdfHtml(svg, titleBlock) {
  const labels = titleBlock?.labels ?? {};
  const rows = [
    [labels.version, titleBlock?.version],
    [labels.owner, titleBlock?.owner],
    [labels.date, titleBlock?.date],
  ].filter(([, value]) => value)
    .map(([label, value]) => `<div><span class="label">${escapeHtml(label)}</span> ${escapeHtml(value)}</div>`)
    .join('');

  const header = titleBlock
    ? `<header><h1>${escapeHtml(titleBlock.title)}</h1><div class="meta">${rows}</div></header>`
    : '';

  return `<!doctype html><html><head><meta charset="utf-8"><style>
    @page { size: A4 landscape; margin: 12mm; }
    body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #111; }
    header { border-bottom: 1px solid #999; padding-bottom: 6px; margin-bottom: 10px; }
    h1 { font-size: 18pt; margin: 0 0 4px; }
    .meta { display: flex; gap: 24px; font-size: 10pt; }
    .label { color: #555; }
    img { display: block; max-width: 100%; max-height: 150mm; margin: 0 auto; }
  </style></head><body>${header}<img src="${svgDataUri(svg)}"></body></html>`;
}
