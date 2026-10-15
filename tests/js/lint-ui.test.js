import { describe, expect, it, vi } from 'vitest';

import { LINT_KEY, readLintActive, setupLintUi } from '../../public/assets/lint-ui.js';

function memoryStorage(initial = {}) {
  const data = { ...initial };

  return {
    getItem: key => (key in data ? data[key] : null),
    setItem: (key, value) => { data[key] = String(value); },
    data,
  };
}

function setup({ active = true, storage = memoryStorage() } = {}) {
  const listeners = {};
  const linting = { active, isActive: () => linting.active, toggle: vi.fn(() => { linting.active = !linting.active; }) };
  const modeler = {
    get: name => (name === 'linting' ? linting : null),
    on: (event, handler) => { listeners[event] = handler; },
  };
  const attributes = {};
  const button = {
    hidden: true,
    title: '',
    listeners: {},
    addEventListener(event, handler) { this.listeners[event] = handler; },
    setAttribute: (name, value) => { attributes[name] = value; },
  };
  const counter = { textContent: '' };

  setupLintUi({ modeler, button, counter, t: (key, r) => (r ? `${key} ${r.count}` : key), storage });

  return { linting, listeners, button, counter, attributes, storage };
}

const issues = { A: [{ category: 'warn' }, { category: 'warn' }], B: [{ category: 'warn' }] };

describe('readLintActive', () => {
  it('defaults to on, and follows the stored choice', () => {
    expect(readLintActive(memoryStorage())).toBe(true);
    expect(readLintActive(memoryStorage({ [LINT_KEY]: '0' }))).toBe(false);
    expect(readLintActive(memoryStorage({ [LINT_KEY]: '1' }))).toBe(true);
  });

  it('defaults to on when storage is unavailable', () => {
    expect(readLintActive({ getItem() { throw new Error('blocked'); } })).toBe(true);
    expect(readLintActive(null)).toBe(true);
  });
});

describe('setupLintUi', () => {
  it('shows the button with the pressed state of the checks', () => {
    const { button, attributes } = setup({ active: false });

    expect(button.hidden).toBe(false);
    expect(attributes['aria-pressed']).toBe('false');
  });

  it('shows the number of issues each time the checks finish', () => {
    const { listeners, counter } = setup();

    listeners['linting.completed']({ issues });
    expect(counter.textContent).toBe('3');

    listeners['linting.completed']({ issues: {} });
    expect(counter.textContent).toBe('0');
  });

  it('toggles the checks on click and remembers the choice', () => {
    const { listeners, button, linting, attributes, storage } = setup();

    button.listeners.click();
    expect(linting.toggle).toHaveBeenCalled();

    listeners['linting.toggle']({ active: false });
    expect(attributes['aria-pressed']).toBe('false');
    expect(storage.data[LINT_KEY]).toBe('0');

    listeners['linting.toggle']({ active: true });
    expect(storage.data[LINT_KEY]).toBe('1');
  });

  it('still works when the choice cannot be stored', () => {
    const { listeners, attributes } = setup({ storage: { getItem: () => null, setItem() { throw new Error('full'); } } });

    expect(() => listeners['linting.toggle']({ active: false })).not.toThrow();
    expect(attributes['aria-pressed']).toBe('false');
  });
});
