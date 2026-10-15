import { describe, expect, it } from 'vitest';
import { escapeHtml, pdfHtml, svgSize } from '../../docker/renderer/lib.mjs';

const titleBlock = {
  title: 'Order <script>alert(1)</script>',
  version: 'Approved (3)',
  owner: 'Maria "M" Souza',
  date: '2026-10-15',
  labels: { version: 'Version', owner: 'Diagram Owner', date: 'Date' },
};

describe('server-side renderer helpers', () => {
  it('escapes HTML metacharacters', () => {
    expect(escapeHtml(`<a href="x">&'`)).toBe('&lt;a href=&quot;x&quot;&gt;&amp;&#39;');
  });

  it('reads the size bpmn-js writes on the root svg', () => {
    expect(svgSize('<svg width="320.5" height="90" viewBox="0 0 320 90"><rect width="5"/></svg>')).toEqual({ width: 320.5, height: 90 });
  });

  it('falls back to a default size when the svg declares none', () => {
    expect(svgSize('<svg></svg>')).toEqual({ width: 1000, height: 800 });
  });

  it('prints the title block with title, Version, Owner and date, escaped', () => {
    const html = pdfHtml('<svg/>', titleBlock);

    expect(html).toContain('<h1>Order &lt;script&gt;alert(1)&lt;/script&gt;</h1>');
    expect(html).toContain('Approved (3)');
    expect(html).toContain('Maria &quot;M&quot; Souza');
    expect(html).toContain('2026-10-15');
    expect(html).not.toContain('<script>');
  });

  it('omits the Version row when the Diagram has no Version yet', () => {
    const html = pdfHtml('<svg/>', { ...titleBlock, version: null });

    expect(html).not.toContain('>Version<');
  });

  it('embeds the Diagram as an inert image', () => {
    expect(pdfHtml('<svg onload="x()"/>', titleBlock)).toMatch(/<img src="data:image\/svg\+xml;base64,[A-Za-z0-9+/=]+">/);
  });
});
