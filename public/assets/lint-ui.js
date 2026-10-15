/*
 * The Editor's modeling-check controls (the lint toggle and issue count in
 * the header), on top of the bpmn-js-bpmnlint service in the Modeler. Part of
 * the shared core (ADR-0019); the checks themselves are wired by
 * resources/js/editor-lint.js (ADR-0026).
 */

export const LINT_KEY = 'bpmn-canvas.lint';

/** localStorage, or null where the browser refuses access to it. */
export function browserStorage() {
  try {
    return window.localStorage;
  } catch (e) {
    return null;
  }
}

/** Whether the checks are shown on the canvas: the User's last choice, on by default. */
export function readLintActive(storage) {
  try {
    return storage?.getItem(LINT_KEY) !== '0';
  } catch (e) {
    return true;
  }
}

/**
 * @param {Object} options
 * @param {Object} options.modeler  a Modeler with the lint module
 * @param {HTMLElement} options.button  toggles the checks on the canvas
 * @param {HTMLElement} options.counter  shows how many issues were found
 * @param {(key: string, replacements?: Object) => string} options.t
 * @param {Storage} [options.storage]  remembers the toggle between sessions
 */
export function setupLintUi({ modeler, button, counter, t, storage }) {
  const linting = modeler.get('linting');

  function showToggle(active) {
    button.setAttribute('aria-pressed', String(active));
    button.title = t(active ? 'messages.lint_hide' : 'messages.lint_show');
  }

  modeler.on('linting.completed', ({ issues }) => {
    const count = Object.values(issues).reduce((total, list) => total + list.length, 0);

    counter.textContent = String(count);
    button.setAttribute('aria-label', t('messages.lint_count', { count }));
  });

  modeler.on('linting.toggle', ({ active }) => {
    showToggle(active);
    try {
      storage?.setItem(LINT_KEY, active ? '1' : '0');
    } catch (e) {
      // The choice only lasts for this session.
    }
  });

  button.addEventListener('click', () => linting.toggle());
  showToggle(linting.isActive());
  button.hidden = false;
}
