/*
 * Version history panel + comparison. Built on the Editor core's public API
 * (the live Modeler, the DiagramStore, `t`, the time zone) and uses diff.js
 * (compareBpmnXml/highlightDiff) to compare two BPMN sources.
 *
 * Two comparison flows share this module:
 *  - "Compare with current" (ticket 35): an old Version against whatever is
 *    currently on the canvas. The canvas itself is never touched — it's
 *    already showing the "current" side.
 *  - "Compare selected" (ticket 36): any two Versions the User picks from
 *    the history via checkboxes. Neither side is necessarily the current
 *    canvas content, so the canvas is temporarily swapped to show the newer
 *    of the two selected Versions (with the diff highlighted on it) and
 *    restored to whatever it displayed before once the User exits the
 *    comparison.
 */
import { toast, errorMessage } from './common.js';
import { compareBpmnXml, highlightDiff, renderRemovedGhosts } from './diff.js';
import { groupVersionsByDay, oldestFirst } from './version-history.js';

/**
 * Wires the Version history panel of the page to the Editor.
 *
 * @param {Object} options
 * @param {Object} options.editor  what createEditor() returned
 * @param {boolean} [options.canEdit]  whether Versions can be renamed,
 *   made permanent or deleted
 * @returns {{reload: () => Promise<void>}}
 */
export function createVersionHistory({ editor, canEdit = true }) {
  const { modeler, store, t, timeZone, Modeler } = editor;

  const $ = id => document.getElementById(id);
  const list = $('version-list');
  const diffBanner = $('diff-banner');
  const diffBannerLabel = $('diff-banner-label');
  const removedPanel = $('diff-removed-panel');
  const removedList = $('diff-removed-list');
  const btnCompareSelected = $('btn-compare-selected');
  const compareCount = $('version-compare-count');
  const btnSave = $('btn-save');

  const MAX_SELECTED = 2;

  let clearHighlight = null;
  let comparingIds = [];
  // Set only while comparing two arbitrary selected Versions (never for
  // "compare with current", which doesn't touch the canvas): the canvas
  // content to restore when the User exits that comparison.
  let canvasSnapshot = null;
  let versionsById = new Map();
  let selectedIds = [];

  function formatTime(version) {
    const locale = (document.documentElement.lang || '').replace('_', '-') || undefined;

    return new Date(version.savedAt).toLocaleTimeString(locale, { timeStyle: 'short', timeZone });
  }

  function formatVersion(version) {
    const date = new Date(version.savedAt);

    // document.documentElement.lang carries Laravel's locale code (e.g.
    // "pt_BR"), not a valid BCP-47 language tag (needs "pt-BR") —
    // Date#toLocaleString throws a RangeError otherwise.
    const locale = (document.documentElement.lang || '').replace('_', '-') || undefined;

    return date.toLocaleString(locale, {
      dateStyle: 'medium',
      timeStyle: 'short',
      timeZone,
    });
  }

  // The day keys are "YYYY-MM-DD" (see version-history.js); show them in the page's locale.
  // Built from its parts so the day isn't shifted by the time zone.
  function formatDay(day) {
    const [year, month, date] = day.split('-').map(Number);
    const locale = (document.documentElement.lang || '').replace('_', '-') || undefined;

    return new Date(year, month - 1, date).toLocaleDateString(locale, { dateStyle: 'long' });
  }

  function versionLabel(version) {
    return version.name + ' — ' + formatVersion(version);
  }

  async function loadVersions() {
    try {
      renderVersions(groupVersionsByDay(await store.versions(), timeZone));
    } catch (e) {
      console.error(e);
      toast(errorMessage(e, t('messages.versions_load_failed')), true);
    }
  }

  function renderVersions(days) {
    list.innerHTML = '';
    versionsById = new Map();

    // A Version can disappear from a re-fetched history in practice it
    // never does, but stay defensive: drop selections that no longer exist
    // rather than leaving btnCompareSelected in a stale, inconsistent state.
    const seenIds = new Set();

    if (!days.length) {
      const empty = document.createElement('li');
      empty.className = 'empty';
      empty.textContent = t('messages.no_versions_yet');
      list.appendChild(empty);
      selectedIds = [];
      updateCompareBar();
      return;
    }

    days.forEach(day => {
      const header = document.createElement('li');
      header.className = 'empty version-day';
      header.textContent = formatDay(day.day);
      list.appendChild(header);

      day.items.forEach(version => {
        versionsById.set(version.id, version);
        seenIds.add(version.id);
        list.appendChild(renderVersionCard(version));
      });
    });

    selectedIds = selectedIds.filter(id => seenIds.has(id));
    updateCompareBar();
    highlightActiveComparison();
  }

  function addBadge(parent, text, modifier) {
    const badge = document.createElement('span');
    badge.className = 'version-badge ' + modifier;
    badge.textContent = text;
    parent.appendChild(badge);
  }

  function addAction(parent, text, handler, modifier) {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = text;
    if (modifier) button.className = modifier;
    button.addEventListener('click', handler);
    parent.appendChild(button);
  }

  // One card per Version: name and number, badges, time and author, then
  // the actions available for it.
  function renderVersionCard(version) {
    const li = document.createElement('li');
    li.className = 'version-card';
    li.dataset.versionId = version.id;

    const row = document.createElement('div');
    row.className = 'version-row';

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.checked = selectedIds.includes(version.id);
    checkbox.addEventListener('change', () => toggleSelection(version.id, checkbox));
    row.appendChild(checkbox);

    const name = document.createElement('strong');
    name.className = 'version-name';
    name.textContent = version.name;
    row.appendChild(name);

    // A custom name replaces "Version N", so the number is shown beside it.
    const number = document.createElement('span');
    number.className = 'version-number';
    number.textContent = '#' + version.number;
    row.appendChild(number);

    li.appendChild(row);

    const badges = document.createElement('div');
    badges.className = 'version-badges';
    if (version.current) addBadge(badges, t('messages.current_version_badge'), 'current');
    if (version.provisional) addBadge(badges, t('messages.provisional_badge'), 'provisional');
    if (badges.childElementCount) li.appendChild(badges);

    const meta = document.createElement('small');
    meta.className = 'version-meta';
    meta.textContent = formatTime(version)
      + (version.author ? ' · ' + t('messages.version_by_author', { name: version.author }) : '');
    li.appendChild(meta);

    const actions = document.createElement('div');
    actions.className = 'version-actions';

    // The current Version is what's on the canvas: nothing to restore or
    // compare it against.
    if (!version.current) {
      addAction(actions, t('messages.compare_with_current'), () => compareWithCurrent(version));
      addAction(actions, t('messages.restore'), () => restoreVersion(version));
    }

    // A Fork is open to anyone who can open the Diagram, not only editors.
    if (store.forkVersion) {
      addAction(actions, t('messages.fork_from_version'), () => forkVersion(version));
    }

    if (canEdit) {
      addAction(actions, t('messages.rename_version'), () => renameVersion(version));

      if (version.provisional) {
        addAction(actions, t('messages.make_permanent'), () => promoteVersion(version));
        addAction(actions, t('messages.delete'), () => deleteVersion(version), 'danger');
      }
    }

    if (actions.childElementCount) li.appendChild(actions);

    return li;
  }

  // Creates a Fork from this Version and moves to it. The Diagram being left
  // is untouched, so unlike a restore this needs no confirmation.
  async function forkVersion(version) {
    try {
      const copyTags = Boolean($('version-fork-copy-tags')?.checked);
      const fork = await store.forkVersion(version.id, { copyTags });
      location.assign(fork.url);
    } catch (e) {
      console.error(e);
      toast(errorMessage(e, t('messages.fork_version_failed')), true);
    }
  }

  // Runs a change on one Version, then re-fetches the history to show it.
  async function changeVersion(change, doneNotice, failedNotice) {
    try {
      await change();
      await loadVersions();
      if (doneNotice) toast(doneNotice);
    } catch (e) {
      console.error(e);
      toast(errorMessage(e, failedNotice), true);
    }
  }

  async function renameVersion(version) {
    const name = (prompt(t('messages.rename_version_prompt'), version.name) || '').trim();
    if (!name || name === version.name) return;

    await changeVersion(
      () => store.renameVersion(version.id, name),
      '',
      t('messages.version_rename_failed'),
    );
  }

  function promoteVersion(version) {
    return changeVersion(
      () => store.promoteVersion(version.id),
      t('messages.version_made_permanent'),
      t('messages.version_promote_failed'),
    );
  }

  async function deleteVersion(version) {
    if (!confirm(t('messages.delete_version_confirm'))) return;

    selectedIds = selectedIds.filter(id => id !== version.id);

    await changeVersion(
      () => store.deleteVersion(version.id),
      t('messages.version_deleted'),
      t('messages.version_delete_failed'),
    );
  }

  function toggleSelection(id, checkbox) {
    if (checkbox.checked) {
      if (selectedIds.length >= MAX_SELECTED) {
        // Enforce "pick two": drop the oldest selection to make room, and
        // uncheck it in the list.
        const dropped = selectedIds.shift();
        const droppedCheckbox = list.querySelector(
          'li[data-version-id="' + CSS.escape(dropped) + '"] input[type=checkbox]'
        );
        if (droppedCheckbox) droppedCheckbox.checked = false;
      }
      selectedIds.push(id);
    } else {
      selectedIds = selectedIds.filter(existing => existing !== id);
    }

    updateCompareBar();
  }

  function updateCompareBar() {
    compareCount.textContent = selectedIds.length + '/' + MAX_SELECTED;
    btnCompareSelected.disabled = selectedIds.length !== MAX_SELECTED;
  }

  function highlightActiveComparison() {
    list.querySelectorAll('li').forEach(li => {
      li.classList.toggle('comparing', comparingIds.includes(li.dataset.versionId));
    });
  }

  // Fits the canvas to the diagram plus `extra` ({left, top, right, bottom}).
  // The canvas' own "fit to viewport" ignores the ghost clones, hence done by hand.
  function frameWith(extra) {
    const canvas = modeler.get('canvas');
    const { inner, outer } = canvas.viewbox();
    const padding = 40;

    const left = Math.min(inner.x, extra.left) - padding;
    const top = Math.min(inner.y, extra.top) - padding;
    const width = Math.max(inner.x + inner.width, extra.right) + padding - left;
    const height = Math.max(inner.y + inner.height, extra.bottom) + padding - top;

    const scale = Math.min(outer.width / width, outer.height / height, 1);
    const viewWidth = outer.width / scale;
    const viewHeight = outer.height / scale;

    canvas.viewbox({
      x: left - (viewWidth - width) / 2,
      y: top - (viewHeight - height) / 2,
      width: viewWidth,
      height: viewHeight,
    });
  }

  // Marks what was added/changed on the canvas and draws what was removed
  // (only present in `oldXml`) as ghosts; returns one function that undoes both.
  async function highlightWithGhosts(oldXml, result) {
    const clearMarkers = highlightDiff(modeler, result);
    const clearGhosts = await renderRemovedGhosts(modeler, oldXml, result, Modeler);

    // Ghosts can sit outside what the canvas is showing: frame them together with the diagram.
    if (clearGhosts.bounds) frameWith(clearGhosts.bounds);

    return () => {
      clearMarkers();
      clearGhosts();
    };
  }

  async function compareWithCurrent(version, { rethrow = false } = {}) {
    if (!modeler) return;

    try {
      const [oldXml, { xml: currentXml }] = await Promise.all([
        store.versionContent(version.id),
        modeler.saveXML({ format: true }),
      ]);

      const result = await compareBpmnXml(oldXml, currentXml, Modeler);

      exitComparison();

      clearHighlight = await highlightWithGhosts(oldXml, result);
      comparingIds = [version.id];
      highlightActiveComparison();

      showDiffSummary(
        t('messages.comparing_with', { version: versionLabel(version) }),
        result,
      );
    } catch (e) {
      console.error(e);
      if (rethrow) throw e;
      toast(errorMessage(e, t('messages.version_compare_failed')), true);
    }
  }

  /**
   * Compares the two Versions currently selected via checkboxes (ticket 36).
   * Unlike compareWithCurrent(), neither side is guaranteed to be what the
   * canvas currently shows, so the canvas is temporarily swapped to display
   * the newer of the two Versions with the diff highlighted on it; the
   * older side's exclusive elements are listed in the side panel, same as
   * the "compare with current" flow. The canvas is restored to whatever it
   * showed before on exitComparison().
   */
  async function compareSelectedVersions() {
    if (!modeler || selectedIds.length !== MAX_SELECTED) return;

    const [older, newer] = oldestFirst(selectedIds.map(id => versionsById.get(id)));

    try {
      const contentPromise = Promise.all([store.versionContent(older.id), store.versionContent(newer.id)]);

      // Kicked off alongside the loads above (rather than after they
      // resolve) since it's independent of the loaded versions: it just
      // reads whatever the canvas shows right now. Skipped entirely when a
      // snapshot is already held from a previous, still-active comparison.
      const snapshotPromise = canvasSnapshot !== null ? null : modeler.saveXML({ format: true });

      const [olderXml, newerXml] = await contentPromise;

      const result = await compareBpmnXml(olderXml, newerXml, Modeler);

      // Snapshot whatever the canvas showed before this comparison (the
      // live diagram, or the result of a previous comparison) so it can be
      // restored exactly once the User exits this comparison.
      const snapshot = canvasSnapshot !== null ? canvasSnapshot : (await snapshotPromise).xml;

      exitComparison();

      canvasSnapshot = snapshot;
      if (btnSave) btnSave.disabled = true;

      const { warnings } = await modeler.importXML(newerXml);
      if (warnings && warnings.length) console.warn('Import warnings:', warnings);
      modeler.get('canvas').zoom('fit-viewport', 'auto');

      clearHighlight = await highlightWithGhosts(olderXml, result);
      comparingIds = [older.id, newer.id];
      highlightActiveComparison();

      showDiffSummary(
        t('messages.comparing_versions', { older: versionLabel(older), newer: versionLabel(newer) }),
        result,
      );
    } catch (e) {
      console.error(e);
      toast(errorMessage(e, t('messages.versions_compare_failed')), true);
    }
  }

  function showDiffSummary(label, result) {
    diffBannerLabel.textContent = label;
    diffBanner.hidden = false;

    const removedIds = Object.keys(result.removed);
    removedList.innerHTML = '';

    if (removedIds.length) {
      removedIds.forEach(id => {
        const element = result.removed[id];
        const li = document.createElement('li');

        const type = document.createElement('span');
        type.className = 'type';
        const typeName = (element.$type || '').replace(/^bpmn:/, '');
        // Translated name of the element type (e.g. "UserTask"); a type with
        // no translation comes back as its key.
        const translatedType = t('messages.bpmn_element_types.' + typeName);
        type.textContent = !translatedType.startsWith('messages.')
          ? translatedType
          : typeName.replace(/([a-z])([A-Z])/g, '$1 $2');

        const name = document.createElement('span');
        name.textContent = element.name || element.id;

        li.appendChild(type);
        li.appendChild(name);
        removedList.appendChild(li);
      });
      removedPanel.hidden = false;
    } else {
      removedPanel.hidden = true;
    }
  }

  function exitComparison() {
    if (clearHighlight) {
      clearHighlight();
      clearHighlight = null;
    }
    comparingIds = [];
    diffBanner.hidden = true;
    removedPanel.hidden = true;
    highlightActiveComparison();

    if (canvasSnapshot !== null && modeler) {
      const restore = canvasSnapshot;
      canvasSnapshot = null;
      if (btnSave) btnSave.disabled = false;
      modeler.importXML(restore).catch(e => console.error(e));
    }
  }

  /**
   * Reloads the live canvas from the Diagram's current content (the
   * restored Version's content, now saved to disk by restoreVersion on the
   * server — see VersionController::restoreVersion). Reuses the Editor's
   * Modeler rather than navigating away, so the dirty-guard state of the
   * Editor core is left untouched.
   */
  async function reloadCanvas() {
    if (!modeler) return;

    const { warnings } = await modeler.importXML(await store.load());
    if (warnings && warnings.length) console.warn('Import warnings:', warnings);
    modeler.get('canvas').zoom('fit-viewport', 'auto');
  }

  async function restoreVersion(version) {
    const confirmMessage = t('messages.restore_version_confirm');
    if (!confirm(confirmMessage)) return;

    try {
      await store.restoreVersion(version.id);

      exitComparison();
      await reloadCanvas();
      // Re-fetch the history so the new Version created by the restore
      // shows up at the top of the list (the store returns them newest
      // first).
      await loadVersions();

      toast(t('messages.version_restored'));
    } catch (e) {
      console.error(e);
      toast(errorMessage(e, t('messages.version_restore_failed')), true);
    }
  }

  $('btn-diff-exit').addEventListener('click', exitComparison);
  btnCompareSelected.addEventListener('click', compareSelectedVersions);

  // Saving the diagram adds a Version: refresh the history.
  editor.on('saved', loadVersions);

  // A save ran into a newer Version (issue #243): show how the canvas, with
  // its unsaved edits, differs from it. The history is re-read first, as the
  // newer Version is not in the panel yet.
  editor.on('compare', async versionId => {
    await loadVersions();

    // A deleted (hidden) Version is not in the panel's history but can still
    // be the current one: compare against it all the same.
    const newer = versionsById.get(versionId)
      ?? { id: versionId, name: t('messages.current_version_badge'), savedAt: new Date().toISOString() };

    await compareWithCurrent(newer, { rethrow: true });
  });

  loadVersions();

  return { reload: loadVersions };
}
