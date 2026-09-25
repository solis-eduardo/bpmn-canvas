/*
 * Version history panel + "compare with current" (ticket 35). Reads the
 * live Modeler exposed by editor.js (window.BpmnEditor) and uses diff.js
 * (compareBpmnXml/highlightDiff) to compare an old Version's content
 * against whatever is currently on the canvas.
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
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  // editor.js runs as a classic (non-module) script earlier in the document
  // and sets this synchronously when it creates the Modeler; module scripts
  // are deferred, so by the time this runs it is always already set.
  const { modeler, versionsUrl, contentUrl } = window.BpmnEditor || {};

  const $ = id => document.getElementById(id);
  const app = $('app');
  const panel = $('version-panel');
  const list = $('version-list');
  const diffBanner = $('diff-banner');
  const diffBannerLabel = $('diff-banner-label');
  const removedPanel = $('diff-removed-panel');
  const removedList = $('diff-removed-list');

  let clearHighlight = null;
  let comparingId = null;

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

        const label = document.createElement('span');
        label.textContent = formatVersion(version) + ' — ' + version.author;
        li.appendChild(label);

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

    highlightActiveComparison();
  }

  function highlightActiveComparison() {
    list.querySelectorAll('li').forEach(li => {
      li.classList.toggle('comparing', li.dataset.versionId === comparingId);
    });
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

      clearHighlight = highlightDiff(modeler, result);
      comparingId = version.id;
      highlightActiveComparison();

      showDiffSummary(version, result);
    } catch (e) {
      console.error(e);
      window.BpmnCommon.toast('Could not compare this version.', true);
    }
  }

  function showDiffSummary(version, result) {
    diffBannerLabel.textContent = 'Comparing with ' + formatVersion(version);
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

  function exitComparison() {
    if (clearHighlight) {
      clearHighlight();
      clearHighlight = null;
    }
    comparingId = null;
    diffBanner.hidden = true;
    removedPanel.hidden = true;
    highlightActiveComparison();
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

  $('btn-version-history-entry').addEventListener('click', () => togglePanel(panel.hidden));
  $('btn-history-close').addEventListener('click', () => togglePanel(false));
  $('btn-diff-exit').addEventListener('click', exitComparison);

  loadVersions();
})();
