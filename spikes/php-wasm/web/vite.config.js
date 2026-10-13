import { defineConfig } from 'vite';

// Relative base so the same build works at "/" and at "/<repo>/" (GitHub Pages project site).
export default defineConfig({
  base: './',
  assetsInclude: [/\.dat$/, /\.so$/, /\.la$/],
  optimizeDeps: { exclude: ['@php-wasm/web'] },
  build: { target: 'es2022', outDir: '../bundle', emptyOutDir: true, chunkSizeWarningLimit: 4000 },
});
