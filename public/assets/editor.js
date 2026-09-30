(function () {
  const { toast, setupZoom } = window.BpmnCommon;

  const body = document.getElementById('editor');
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  const contentUrl = body.dataset.contentUrl;
  const saveUrl = body.dataset.saveUrl;
  const renameUrl = body.dataset.renameUrl;
  const deleteUrl = body.dataset.deleteUrl;
  const indexUrl = body.dataset.indexUrl;
  const heartbeatUrl = body.dataset.heartbeatUrl;
  const unlockUrl = body.dataset.unlockUrl;
  const lockRequestedByNoticeTemplate = body.dataset.lockRequestedByNotice;

  const modeler = new BpmnJS({ container: '#canvas' });
  const $ = id => document.getElementById(id);
  const nameInput = $('name');

  // Exposed so the version-comparison module (loaded separately, see
  // versions.js/diff.js) can reuse this same live Modeler/canvas instead of
  // creating a second one.
  window.BpmnEditor = { modeler, contentUrl, versionsUrl: body.dataset.versionsUrl };

  let dirty = false;

  setupZoom(modeler);

  function setDirty(v) {
    dirty = v;
    $('dirty').classList.toggle('on', v);
  }

  async function api(method, url, body) {
    const res = await fetch(url, {
      method,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) {
      throw new Error(method + ' ' + url + ' → HTTP ' + res.status);
    }
    return res;
  }

  async function loadContent() {
    try {
      const res = await fetch(contentUrl, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const data = await res.json();
      const { warnings } = await modeler.importXML(data.content);
      if (warnings && warnings.length) console.warn('Import warnings:', warnings);
      modeler.get('canvas').zoom('fit-viewport', 'auto');
      setDirty(false);
    } catch (e) {
      console.error(e);
      toast('Could not load the diagram.', true);
    }
  }

  async function save() {
    try {
      const { xml } = await modeler.saveXML({ format: true });
      await api('PUT', saveUrl, { content: xml });
      setDirty(false);
      toast('Saved.');
    } catch (e) {
      console.error(e);
      toast('Error saving: ' + e.message, true);
    }
  }

  async function rename() {
    const title = nameInput.value.trim();
    if (!title) {
      toast('Enter a name for the diagram.', true);
      return;
    }
    try {
      const data = await (await api('PUT', renameUrl, { title })).json();
      location.href = data.slug ? renameUrl.replace(/\/diagrams\/[^/]+/, '/diagrams/' + data.slug) : location.href;
    } catch (e) {
      console.error(e);
      toast('Error renaming: ' + e.message, true);
    }
  }

  $('btn-save').addEventListener('click', save);
  nameInput.addEventListener('change', rename);
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      save();
    }
  });

  $('btn-delete').addEventListener('click', async () => {
    if (!confirm('Delete "' + nameInput.value + '"?')) return;
    try {
      await api('DELETE', deleteUrl);
      toast('Deleted.');
      location.href = indexUrl;
    } catch (e) {
      console.error(e);
      toast('Error deleting: ' + e.message, true);
    }
  });

  const releaseLockButton = $('btn-release-lock');
  if (releaseLockButton) {
    releaseLockButton.addEventListener('click', async () => {
      if (dirty && !confirm(body.dataset.releaseUnsavedConfirm)) return;

      try {
        await api('DELETE', unlockUrl);
        setDirty(false);
        location.href = indexUrl;
      } catch (e) {
        console.error(e);
        toast('Error releasing the Lock: ' + e.message, true);
      }
    });
  }

  modeler.on('commandStack.changed', () => setDirty(true));
  window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  const lockRequestBanner = $('lock-request-banner');

  // The Lock holder's only signal that someone else wants the Lock is this
  // same heartbeat poll (see DiagramController::heartbeat) — no separate
  // poll loop is started just for this (issue #9).
  function showLockRequestedNotice(requestedByName) {
    if (!lockRequestBanner) return;

    if (!requestedByName) {
      lockRequestBanner.hidden = true;
      lockRequestBanner.textContent = '';
      return;
    }

    lockRequestBanner.textContent = (lockRequestedByNoticeTemplate || ':name')
      .replace(':name', requestedByName);
    lockRequestBanner.hidden = false;
  }

  const heartbeatInterval = setInterval(() => {
    api('POST', heartbeatUrl)
      .then(res => res.json())
      .then(data => showLockRequestedNotice(data.lock_requested_by))
      .catch(e => console.error(e));
  }, 30000);

  loadContent();
})();
