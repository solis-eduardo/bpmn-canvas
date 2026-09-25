/*
 * Version history panel + "compare with current" (ticket 35). Reads the
 * live Modeler exposed by editor.js (window.BpmnEditor) and uses diff.js
 * (compareBpmnXml/highlightDiff) to compare an old Version's content
 * against whatever is currently on the canvas.
 */
import { compareBpmnXml, highlightDiff } from './diff.js';

(async function () {
  const body = document.getElementById('editor');
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  const versionsUrl = body.dataset.versionsUrl;
  const versionContentUrlTemplate = body.dataset.versionContentUrlTemplate;

  const $ = id => document.getElementById(id);
  const app = $('app');
  const panel = $('version-panel');
  const list = $('version-list');
  const diffBanner = $('diff-banner');
  const diffBannerLabel = $('diff-banner-label');
  const removedPanel = $('diff-removed-panel');
  const removedList = $('diff-removed-list');

  // editor.js runs as a classic (non-module) script earlier in the document
  // and sets this synchronously when it creates the Modeler; module scripts
  // are deferred, so by the time this runs it is always already set.
  const { modeler } = window.BpmnEditor || {};

  let clearHighlight = null;
  let comparingId = null;

  function versionContentUrl(id) {
    return versionContentUrlTemplate.replace('VERSION_ID', encodeURIComponent(id));
  }

  function formatVersion(version) {
    const date = new Date(version.saved_at);

    return date.toLocaleString(document.documentElement.lang || undefined, {
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
      const res = await fetch(versionContentUrl(version.id), {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });

      if (!res.ok) throw new Error('HTTP ' + res.status);

      const { content: oldXml } = await res.json();
      const { xml: currentXml } = await modeler.saveXML({ format: true });

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

  $('btn-history').addEventListener('click', () => togglePanel(panel.hidden));
  $('btn-history-close').addEventListener('click', () => togglePanel(false));
  $('btn-diff-exit').addEventListener('click', exitComparison);

  loadVersions();
})();
