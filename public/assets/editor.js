(function () {
  const { toast, setupZoom } = window.BpmnCommon;

  const body = document.getElementById('editor');
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  const contentUrl = body.dataset.contentUrl;
  const saveUrl = body.dataset.saveUrl;
  const indexUrl = body.dataset.indexUrl;
  const heartbeatUrl = body.dataset.heartbeatUrl;
  const unlockUrl = body.dataset.unlockUrl;
  const lockRequestedByNoticeTemplate = body.dataset.lockRequestedByNotice;

  const modeler = new BpmnJS({ container: '#canvas' });
  const $ = id => document.getElementById(id);

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
      toast(body.dataset.loadFailedNotice, true);
    }
  }

  async function save() {
    try {
      const { xml } = await modeler.saveXML({ format: true });
      await api('PUT', saveUrl, { content: xml });
      setDirty(false);
      document.dispatchEvent(new CustomEvent('diagram:saved'));
      toast(body.dataset.savedNotice);
    } catch (e) {
      console.error(e);
      toast(body.dataset.saveFailedNotice, true);
    }
  }

  $('btn-save').addEventListener('click', save);
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      save();
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
        toast(body.dataset.releaseLockFailedNotice, true);
      }
    });
  }

  modeler.on('commandStack.changed', () => setDirty(true));
  window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  const lockRequestBanner = $('lock-request-banner');

  // The Lock holder's only signal that someone else wants the Lock is this
  // same heartbeat poll (see LockController::update) — no separate
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
    api('PATCH', heartbeatUrl)
      .then(res => res.json())
      .then(data => showLockRequestedNotice(data.lock_requested_by))
      .catch(e => console.error(e));
  }, 30000);

  loadContent();
})();
