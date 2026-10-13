import { defineConfig } from 'vitest/config';

// Kept apart from vite.config.js: the Laravel plugin there expects a Laravel
// build, and these tests only exercise the Editor's browser-side modules.
export default defineConfig({
    test: {
        include: ['tests/js/**/*.test.js'],
    },
});
