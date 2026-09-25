/*
 * Version history panel + "compare with current" (ticket 35) + "compare any
 * two Versions" (ticket 36). Reads the live Modeler exposed by editor.js
 * (window.BpmnEditor) and uses diff.js (compareBpmnXml/highlightDiff) to
 * compare two arbitrary Versions' content, reusing the same generic
 * "content of one Version" endpoint for both sides.
 *
 * Comparing with current (ticket 35) never touches the canvas — it's
 * already showing the "current" side. Comparing two arbitrary Versions
 * (ticket 36) has no such anchor, so the newer of the two selected
 * Versions is imported onto the canvas to render the "new" side, and the
 * canvas is reloaded back to the Diagram's actual current content on exit.
 */
import { compareBpmnXml, highlightDiff } from './diff.js';

(async function () {
  const body = document.getElementById('editor');
  const versionContentUrlTemplate = body.dataset.versionContentUrlTemplate;
  const versionRestoreUrlTemplate = body.dataset.versionRestoreUrlTemplate;
  const restoreConfirmMessage = body.dataset.restoreVersionConfirm;
  const versionRestoredNotice = body.dataset.versionRestoredNotice;
  const versionRestoreFailedNotice = body.dataset.versionRestoreFailedNotice;
  const restoreLabel = body.dataset.restoreLabel || 'Restore';
  const selectToCompareLabel = body.dataset.selectToCompareLabel || 'Select to compare';
  const compareSelectedLabel = body.dataset.compareSelectedLabel || 'Compare selected';
  const comparePairConfirmMessage = body.dataset.comparePairConfirm;
  const comparePairFailedNotice = body.dataset.comparePairFailedNotice;
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  // editor.js runs as a classic (non-module) script earlier in the document
  // and sets this synchronously when it creates the Modeler; module scripts
  // are deferred, so by the time this runs it is always already set.
  const { modeler, versionsUrl, contentUrl, isDirty } = window.BpmnEditor || {};

  const $ = id => document.getElementById(id);
  const app = $('app');
  const panel = $('version-panel');
  const list = $('version-list');
  const compareFooter = $('version-compare-footer');
  const compareSelectedBtn = $('btn-compare-selected');
  const diffBanner = $('diff-banner');
  const diffBannerLabel = $('diff-banner-label');
  const removedPanel = $('diff-removed-panel');
  const removedList = $('diff-removed-list');

  let clearHighlight = null;
  let comparingId = null;

  // Ticket 36: Versions checked for "compare any two Versions", keyed by id
  // (Map preserves insertion order, so the first-checked one stays sideA
  // even after a re-render — see toggleSelection). Never holds more than
  // two entries at once.
  const selectedForCompare = new Map();

  // True while the canvas shows one of the two Versions selected for a pair
  // comparison (ticket 36) instead of the Diagram's actual current content
  // — cleared, and the canvas reloaded, by exitComparison.
  let pairComparisonActive = false;

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

  function togglePanel(open) {
    panel.hidden = !open;
    app.classList.toggle('with-sidebar', open);
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
      window.BpmnCommon.toast('Could not load version history.', true);
    }
  }

  function renderVersions(days) {
    list.innerHTML = '';

    if (!days.length) {
      const empty = document.createElement('li');
      empty.className = 'empty';
      empty.textContent = 'No versions yet.';
      list.appendChild(empty);
      return;
    }

    days.forEach(day => {
      const header = document.createElement('li');
      header.className = 'empty';
      header.textContent = day.day;
      list.appendChild(header);

      day.items.forEach(version => {
        const li = document.createElement('li');
        li.dataset.versionId = version.id;

        const row = document.createElement('div');
        row.className = 'version-row';

        // Ticket 36: checkbox to pick this Version as one of the two sides
        // of a pair comparison. Kept separate from "Compare with current"
        // (ticket 35), which stays a one-click action per Version.
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.setAttribute('aria-label', selectToCompareLabel);
        checkbox.checked = selectedForCompare.has(version.id);
        checkbox.disabled = !checkbox.checked && selectedForCompare.size >= 2;
        checkbox.addEventListener('change', () => toggleSelection(version, checkbox));
        row.appendChild(checkbox);

        const label = document.createElement('span');
        label.textContent = formatVersion(version) + ' — ' + version.author;
        row.appendChild(label);

        li.appendChild(row);

        const actions = document.createElement('div');
        actions.className = 'version-actions';

        const compareBtn = document.createElement('button');
        compareBtn.type = 'button';
        compareBtn.textContent = 'Compare with current';
        compareBtn.addEventListener('click', () => compareWithCurrent(version));
        actions.appendChild(compareBtn);

        const restoreBtn = document.createElement('button');
        restoreBtn.type = 'button';
        restoreBtn.textContent = restoreLabel;
        restoreBtn.addEventListener('click', () => restoreVersion(version));
        actions.appendChild(restoreBtn);

        li.appendChild(actions);
        list.appendChild(li);
      });
    });

    updateCompareFooter();
    highlightActiveComparison();
  }

  function highlightActiveComparison() {
    list.querySelectorAll('li').forEach(li => {
      const id = li.dataset.versionId;
      li.classList.toggle('comparing', id === comparingId || selectedForCompare.has(id));
    });
  }

  /**
   * Ticket 36: keeps at most two Versions checked for "compare any two
   * Versions". Once two are selected, every other checkbox is disabled
   * (re-rendered by loadVersions/renderVersions) until one is unchecked.
   */
  function toggleSelection(version, checkbox) {
    if (checkbox.checked) {
      if (selectedForCompare.size >= 2) {
        checkbox.checked = false;
        return;
      }
      selectedForCompare.set(version.id, version);
    } else {
      selectedForCompare.delete(version.id);
    }

    updateCompareFooter();
    renderCheckboxStates();
  }

  function renderCheckboxStates() {
    list.querySelectorAll('li').forEach(li => {
      const id = li.dataset.versionId;
      const checkbox = li.querySelector('input[type="checkbox"]');
      if (!checkbox) return;
      const checked = selectedForCompare.has(id);
      checkbox.checked = checked;
      checkbox.disabled = !checked && selectedForCompare.size >= 2;
    });
    highlightActiveComparison();
  }

  function updateCompareFooter() {
    if (!compareFooter) return;
    compareFooter.hidden = selectedForCompare.size !== 2;
  }

  async function compareWithCurrent(version) {
    if (!modeler) return;

    // If a pair comparison (ticket 36) left a historical Version on the
    // canvas, put the actual current content back first — otherwise the
    // saveXML() below would grab that historical Version's XML instead of
    // what "current" is supposed to mean here.
    await exitComparison();

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

      clearHighlight = highlightDiff(modeler, result);
      comparingId = version.id;
      highlightActiveComparison();

      showDiffSummary('Comparing with ' + formatVersion(version), result);
    } catch (e) {
      console.error(e);
      window.BpmnCommon.toast('Could not compare this version.', true);
    }
  }

  /**
   * Ticket 36: compares the two Versions currently checked in the history
   * list. Neither side is necessarily the Diagram's current content, so —
   * unlike compareWithCurrent — this imports the newer of the two Versions
   * onto the canvas to have something to render the "new" side against,
   * then restores the actual current content on exitComparison.
   */
  async function compareSelected() {
    if (!modeler || selectedForCompare.size !== 2) return;

    const [sideA, sideB] = Array.from(selectedForCompare.values())
      .sort((a, b) => new Date(a.saved_at) - new Date(b.saved_at));

    if (isDirty && isDirty()) {
      const confirmMessage = comparePairConfirmMessage
        || 'Comparing these versions replaces the canvas with one of them, discarding any unsaved changes. Continue?';
      if (!confirm(confirmMessage)) return;
    }

    try {
      const [resA, resB] = await Promise.all([
        fetch(versionContentUrl(sideA.id), {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        }),
        fetch(versionContentUrl(sideB.id), {
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        }),
      ]);

      if (!resA.ok) throw new Error('HTTP ' + resA.status);
      if (!resB.ok) throw new Error('HTTP ' + resB.status);

      const [{ content: xmlA }, { content: xmlB }] = await Promise.all([resA.json(), resB.json()]);

      const result = await compareBpmnXml(xmlA, xmlB);

      await exitComparison();

      const { warnings } = await modeler.importXML(xmlB);
      if (warnings && warnings.length) console.warn('Import warnings:', warnings);
      modeler.get('canvas').zoom('fit-viewport', 'auto');
      pairComparisonActive = true;

      clearHighlight = highlightDiff(modeler, result);
      highlightActiveComparison();

      showDiffSummary('Comparing ' + formatVersion(sideA) + ' with ' + formatVersion(sideB), result);
    } catch (e) {
      console.error(e);
      window.BpmnCommon.toast(comparePairFailedNotice || 'Could not compare these versions.', true);
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
        type.textContent = (element.$type || '').replace(/^bpmn:/, '');

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

  async function exitComparison() {
    if (clearHighlight) {
      clearHighlight();
      clearHighlight = null;
    }
    comparingId = null;
    diffBanner.hidden = true;
    removedPanel.hidden = true;
    highlightActiveComparison();

    // Ticket 36: the canvas may currently be showing one of the two
    // compared Versions rather than the Diagram's actual current content
    // — put the real content back.
    if (pairComparisonActive) {
      pairComparisonActive = false;
      await reloadCanvas();
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

      await exitComparison();
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

  $('btn-version-history-entry').addEventListener('click', () => togglePanel(panel.hidden));
  $('btn-history-close').addEventListener('click', () => togglePanel(false));
  $('btn-diff-exit').addEventListener('click', exitComparison);
  if (compareSelectedBtn) {
    compareSelectedBtn.textContent = compareSelectedLabel;
    compareSelectedBtn.addEventListener('click', compareSelected);
  }

  loadVersions();
})();
