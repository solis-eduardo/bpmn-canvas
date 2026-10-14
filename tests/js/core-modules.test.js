import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

// ADR-0018: the shared core is plain ES modules that import what they use and
// get their UI strings through `t`, so it can be bundled into the standalone build.
const CORE = ['common', 'editor-core', 'versions', 'version-history', 'diff', 'http-store', 'translator'];

describe.each(CORE)('shared core module %s', name => {
  const source = readFileSync(`public/assets/${name}.js`, 'utf8');

  it('reads no window.Bpmn* global', () => {
    expect(source).not.toMatch(/window\.Bpmn/);
  });

  it('reads no UI string from data-* attributes', () => {
    expect(source).not.toMatch(/\.dataset\.(?!versionId|panel|release|export)/);
    expect(source).not.toMatch(/getAttribute\(['"]data-/);
  });

  it('has no IIFE wrapper', () => {
    expect(source).not.toMatch(/^\(\s*(async )?function/m);
  });
});
