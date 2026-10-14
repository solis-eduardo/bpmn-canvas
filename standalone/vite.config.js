import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { viteSingleFile } from 'vite-plugin-singlefile';

// Bundles the standalone build into one self-contained HTML file: ES modules
// and import maps don't load over file://, so bpmn-js, bpmn-js-differ, the CSS,
// the fonts and the translations are all inlined (ADR-0019).
export default defineConfig({
  root: import.meta.dirname,
  base: './',
  plugins: [
    tailwindcss(),
    viteSingleFile(),
    {
      name: 'name-the-output',
      enforce: 'post',
      generateBundle(_, bundle) {
        const page = bundle['index.html'];
        page.fileName = 'bpmn-canvas.html';

        // bpmn-js's icon font also lists an SVG face, which would stay a separate
        // file; the embedded WOFF/TTF faces already cover every browser.
        page.source = page.source.replace(/,\s*url\(\.\/[^)]*\.svg[^)]*\)\s*format\(["']svg["']\)/g, '');
        for (const name of Object.keys(bundle)) {
          if (name.endsWith('.svg')) delete bundle[name];
        }
      },
    },
  ],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    assetsInlineLimit: Number.MAX_SAFE_INTEGER,
    target: 'es2022', // top-level await
  },
});
