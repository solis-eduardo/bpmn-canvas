/*
 * Translations for the shared Editor core (ADR-0019). The core never reads
 * UI strings from the page: it gets a `t(key, replacements)` function built
 * from the JSON that `php artisan lang:export` writes from `lang/`.
 */

/**
 * @param {Record<string, string>} messages  flat map of Laravel translation
 *   keys (e.g. "messages.saved") to strings
 * @returns {(key: string, replacements?: Record<string, string|number>) => string}
 *   Like Laravel's `__()`: ":name" placeholders are replaced, and a key with
 *   no translation comes back as the key itself.
 */
export function createTranslator(messages) {
  return function t(key, replacements = {}) {
    const text = Object.prototype.hasOwnProperty.call(messages, key) ? messages[key] : key;

    return text.replace(/:(\w+)/g, (match, name) =>
      Object.prototype.hasOwnProperty.call(replacements, name) ? String(replacements[name]) : match);
  };
}
