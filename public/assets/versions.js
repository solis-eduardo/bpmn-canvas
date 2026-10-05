/*
 * Version history panel + comparison. Reads the live Modeler exposed by
 * editor.js (window.BpmnEditor) and uses diff.js (compareBpmnXml/
 * highlightDiff) to compare two BPMN sources.
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
import { compareBpmnXml, highlightDiff, renderRemovedGhosts } from './diff.js';

(async function () {
  const body = document.getElementById('editor');
  const versionContentUrlTemplate = body.dataset.versionContentUrlTemplate;
  const versionCompareUrl = body.dataset.versionCompareUrl;
  const versionRestoreUrlTemplate = body.dataset.versionRestoreUrlTemplate;
  const restoreConfirmMessage = body.dataset.restoreVersionConfirm;
  const versionRestoredNotice = body.dataset.versionRestoredNotice;
  const versionRestoreFailedNotice = body.dataset.versionRestoreFailedNotice;
  const restoreLabel = body.dataset.restoreLabel || 'Restore';
  const compareWithCurrentLabel = body.dataset.compareWithCurrentLabel || 'Compare with current';
  // Translated names of the BPMN element types, keyed by type (e.g. "UserTask").
  const elementTypes = JSON.parse(body.dataset.elementTypes || '{}');
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  // editor.js runs as a classic (non-module) script earlier in the document
  // and sets this synchronously when it creates the Modeler; module scripts
  // are deferred, so by the time this runs it is always already set.
  const { modeler, versionsUrl, contentUrl } = window.BpmnEditor || {};

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

  function versionContentUrl(id) {
    return versionContentUrlTemplate.replace('VERSION_ID', encodeURIComponent(id));
  }

  function versionRestoreUrl(id) {
    return versionRestoreUrlTemplate.replace('VERSION_ID', encodeURIComponent(id));
  }

  function formatVersion(version) {
    const date = new Date(version.saved_at);

    // document.documentElement.lang carries Laravel's locale code (e.g.
    // "pt_BR"), not a valid BCP-47 language tag (needs "pt-BR") —
    // Date#toLocaleString throws a RangeError otherwise.
    const locale = (document.documentElement.lang || '').replace('_', '-') || undefined;

    return date.toLocaleString(locale, {
      dateStyle: 'medium',
      timeStyle: 'short',
    });
  }

  // The day headers arrive as "YYYY-MM-DD"; show them in the page's locale.
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
      const res = await fetch(versionsUrl, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const data = await res.json();
      renderVersions(data.versions || []);
    } catch (e) {
      console.error(e);
      window.BpmnCommon.toast(body.dataset.versionsLoadFailedNotice || 'Could not load version history.', true);
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
      empty.textContent = body.dataset.noVersionsNotice || 'No versions yet.';
      list.appendChild(empty);
      selectedIds = [];
      updateCompareBar();
      return;
    }

    days.forEach(day => {
      const header = document.createElement('li');
      header.className = 'empty';
      header.textContent = formatDay(day.day);
      list.appendChild(header);

      day.items.forEach(version => {
        versionsById.set(version.id, version);
        seenIds.add(version.id);

        const li = document.createElement('li');
        li.dataset.versionId = version.id;

        const row = document.createElement('div');
        row.className = 'version-row';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = selectedIds.includes(version.id);
        checkbox.addEventListener('change', () => toggleSelection(version.id, checkbox));
        row.appendChild(checkbox);

        const label = document.createElement('span');
        label.textContent = versionLabel(version);
        row.appendChild(label);

        li.appendChild(row);

        // The current Version is what's on the canvas: nothing to restore or
        // compare it against.
        if (!version.is_current) {
          const actions = document.createElement('div');
          actions.className = 'version-actions';

          const compareBtn = document.createElement('button');
          compareBtn.type = 'button';
          compareBtn.textContent = compareWithCurrentLabel;
          compareBtn.addEventListener('click', () => compareWithCurrent(version));
          actions.appendChild(compareBtn);

          const restoreBtn = document.createElement('button');
          restoreBtn.type = 'button';
          restoreBtn.textContent = restoreLabel;
          restoreBtn.addEventListener('click', () => restoreVersion(version));
          actions.appendChild(restoreBtn);

          li.appendChild(actions);
        }

        list.appendChild(li);
      });
    });

    selectedIds = selectedIds.filter(id => seenIds.has(id));
    updateCompareBar();
    highlightActiveComparison();
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
    const clearGhosts = await renderRemovedGhosts(modeler, oldXml, result);

    // Ghosts can sit outside what the canvas is showing: frame them together with the diagram.
    if (clearGhosts.bounds) frameWith(clearGhosts.bounds);

    return () => {
      clearMarkers();
      clearGhosts();
    };
  }

  async function compareWithCurrent(version) {
    if (!modeler) return;

    try {
      const [res, { xml: currentXml }] = await Promise.all([
        fetch(versionContentUrl(version.id), {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        }),
        modeler.saveXML({ format: true }),
      ]);

      if (!res.ok) throw new Error('HTTP ' + res.status);

      const { content: oldXml } = await res.json();

      const result = await compareBpmnXml(oldXml, currentXml);

      exitComparison();

      clearHighlight = await highlightWithGhosts(oldXml, result);
      comparingIds = [version.id];
      highlightActiveComparison();

      showDiffSummary(
        (body.dataset.comparingWithTemplate || 'Comparing with :version').replace(':version', versionLabel(version)),
        result,
      );
    } catch (e) {
      console.error(e);
      window.BpmnCommon.toast(body.dataset.versionCompareFailedNotice || 'Could not compare this version.', true);
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

    const [firstId, secondId] = selectedIds;

    try {
      const fetchPromise = fetch(
        versionCompareUrl + '?from=' + encodeURIComponent(firstId) + '&to=' + encodeURIComponent(secondId),
        { credentials: 'same-origin', headers: { Accept: 'application/json' } }
      );

      // Kicked off alongside the fetch above (rather than after it
      // resolves) since it's independent of the fetched versions: it just
      // reads whatever the canvas shows right now. Skipped entirely when a
      // snapshot is already held from a previous, still-active comparison.
      const snapshotPromise = canvasSnapshot !== null ? null : modeler.saveXML({ format: true });

      const res = await fetchPromise;

      if (!res.ok) throw new Error('HTTP ' + res.status);

      const { older, newer } = await res.json();

      const result = await compareBpmnXml(older.content, newer.content);

      // Snapshot whatever the canvas showed before this comparison (the
      // live diagram, or the result of a previous comparison) so it can be
      // restored exactly once the User exits this comparison.
      const snapshot = canvasSnapshot !== null ? canvasSnapshot : (await snapshotPromise).xml;

      exitComparison();

      canvasSnapshot = snapshot;
      if (btnSave) btnSave.disabled = true;

      const { warnings } = await modeler.importXML(newer.content);
      if (warnings && warnings.length) console.warn('Import warnings:', warnings);
      modeler.get('canvas').zoom('fit-viewport', 'auto');

      clearHighlight = await highlightWithGhosts(older.content, result);
      comparingIds = [older.id, newer.id];
      highlightActiveComparison();

      const olderVersion = versionsById.get(older.id);
      const newerVersion = versionsById.get(newer.id);
      const olderLabel = olderVersion ? versionLabel(olderVersion) : older.name;
      const newerLabel = newerVersion ? versionLabel(newerVersion) : newer.name;

      showDiffSummary(
        (body.dataset.comparingVersionsTemplate || 'Comparing :older with :newer')
          .replace(':older', olderLabel)
          .replace(':newer', newerLabel),
        result,
      );
    } catch (e) {
      console.error(e);
      window.BpmnCommon.toast(body.dataset.versionsCompareFailedNotice || 'Could not compare these versions.', true);
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
        type.textContent = elementTypes[typeName] || typeName.replace(/([a-z])([A-Z])/g, '$1 $2');

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
   * server — see VersionController::restoreVersion). Reuses the same
   * Modeler exposed by editor.js rather than navigating away, so the Lock
   * heartbeat/dirty-guard state in that module is left untouched.
   */
  async function reloadCanvas() {
    if (!modeler || !contentUrl) return;

    const res = await fetch(contentUrl, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    const data = await res.json();
    const { warnings } = await modeler.importXML(data.content);
    if (warnings && warnings.length) console.warn('Import warnings:', warnings);
    modeler.get('canvas').zoom('fit-viewport', 'auto');
  }

  async function restoreVersion(version) {
    const confirmMessage = restoreConfirmMessage || 'Restore this version? This creates a new version on top of the history.';
    if (!confirm(confirmMessage)) return;

    try {
      const res = await fetch(versionRestoreUrl(version.id), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
      });

      if (!res.ok) throw new Error('HTTP ' + res.status);

      exitComparison();
      await reloadCanvas();
      // Re-fetch the history so the new Version created by the restore
      // shows up at the top of the list (versions are returned newest
      // first — see VersionController::versions).
      await loadVersions();

      window.BpmnCommon.toast(versionRestoredNotice || 'Version restored.');
    } catch (e) {
      console.error(e);
      window.BpmnCommon.toast(versionRestoreFailedNotice || 'Could not restore this version.', true);
    }
  }

  $('btn-diff-exit').addEventListener('click', exitComparison);
  btnCompareSelected.addEventListener('click', compareSelectedVersions);

  // Saving the diagram adds a Version: refresh the history (see editor.js).
  document.addEventListener('diagram:saved', loadVersions);

  loadVersions();
})();
