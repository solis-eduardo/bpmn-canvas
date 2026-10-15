/*
 * The save-conflict dialog flow (issue #243): a save was refused because a
 * newer Version exists than the one the canvas was loaded from. Plain data in,
 * the User's choice carried out through the callbacks the caller passes, so
 * the flow is testable without a DOM or a Modeler.
 */

/**
 * Asks the User how to settle the conflict and carries the answer out.
 *
 * @param {Object} options
 * @param {(key: string) => string} options.t  localized UI string by key
 * @param {(dialog: Object) => Promise<string>} options.choose  shows the
 *   dialog, resolves to the picked value (the first choice is the safe one)
 * @param {() => Promise<any>} options.reload  loads the newer Version onto
 *   the canvas, dropping the local edits
 * @param {() => Promise<boolean>} options.overwrite  saves the local edits on
 *   top of the newer Version; resolves to whether it was stored
 * @param {?() => Promise<any>} options.compare  shows the differences between
 *   the local edits and the newer Version; null when the page can't
 * @param {?() => Promise<any>} options.fork  saves the local edits as a Fork
 *   (a new Diagram); null when the store can't
 * @returns {Promise<boolean>} whether the local edits were stored as a new
 *   Version of this Diagram: only "save anyway" does that. Reloading, comparing,
 *   forking and "keep editing" leave the canvas unsaved.
 */
export async function resolveSaveConflict({ t, choose, reload, overwrite, compare = null, fork = null }) {
  const choices = [{ value: 'cancel', label: t('messages.conflict_cancel') }];
  if (compare) choices.push({ value: 'compare', label: t('messages.conflict_compare') });
  if (fork) choices.push({ value: 'fork', label: t('messages.conflict_fork') });
  choices.push(
    { value: 'overwrite', label: t('messages.conflict_overwrite') },
    { value: 'reload', label: t('messages.conflict_reload'), primary: true },
  );

  const choice = await choose({ message: t('messages.diagram_conflict'), choices });

  if (choice === 'overwrite') return overwrite();

  const others = { reload, compare, fork };
  if (others[choice]) await others[choice]();

  return false;
}
