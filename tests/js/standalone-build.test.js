import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const json = path => JSON.parse(readFileSync(path, 'utf8'));

describe('standalone build', () => {
  it('bundles the same bpmn-js and bpmn-js-differ the server build vendors', () => {
    const bundled = json('package.json').devDependencies;
    const vendored = json('docker/vendor/package.json').dependencies;

    expect(bundled['bpmn-js']).toBe(vendored['bpmn-js']);
    expect(bundled['bpmn-js-differ']).toBe(vendored['bpmn-js-differ']);
  });
});
