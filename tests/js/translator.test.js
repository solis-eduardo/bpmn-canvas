import { describe, expect, it } from 'vitest';
import { createTranslator } from '../../public/assets/translator.js';

describe('createTranslator', () => {
  const t = createTranslator({
    'messages.saved': 'Saved.',
    'messages.version_by_author': 'by :name',
    'messages.comparing_versions': 'Comparing :older with :newer',
    'messages.longer': ':versions and :version',
  });

  it('returns the string for a key', () => {
    expect(t('messages.saved')).toBe('Saved.');
  });

  it('returns the key itself when there is no translation', () => {
    expect(t('messages.unknown')).toBe('messages.unknown');
  });

  it('replaces :placeholders', () => {
    expect(t('messages.version_by_author', { name: 'Ana' })).toBe('by Ana');
    expect(t('messages.comparing_versions', { older: 'V1', newer: 'V2' })).toBe('Comparing V1 with V2');
  });

  it('does not half-replace a placeholder that starts with another one', () => {
    expect(t('messages.longer', { version: 'A', versions: 'B' })).toBe('B and A');
  });
});
