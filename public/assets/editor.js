import { createHttpDiagramStore, httpError } from './http-store.js';

(function () {
  const { toast, setupZoom, errorMessage } = window.BpmnCommon;

  const body = document.getElementById('editor');
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  const indexUrl = body.dataset.indexUrl;
  const heartbeatUrl = body.dataset.heartbeatUrl;
  const unlockUrl = body.dataset.unlockUrl;
  const lockRequestedByNoticeTemplate = body.dataset.lockRequestedByNotice;

  const modeler = new BpmnJS({ container: '#canvas' });
  const store = createHttpDiagramStore({
    urls: {
      contentUrl: body.dataset.contentUrl,
      saveUrl: body.dataset.saveUrl,
      versionsUrl: body.dataset.versionsUrl,
      versionContentUrlTemplate: body.dataset.versionContentUrlTemplate,
      versionRestoreUrlTemplate: body.dataset.versionRestoreUrlTemplate,
      versionRenameUrlTemplate: body.dataset.versionRenameUrlTemplate,
      versionPromoteUrlTemplate: body.dataset.versionPromoteUrlTemplate,
      versionDeleteUrlTemplate: body.dataset.versionDeleteUrlTemplate,
    },
    csrfToken,
  });
  const $ = id => document.getElementById(id);

  // Exposed so the Version history module (versions.js) can reuse this same
  // live Modeler/canvas and DiagramStore instead of creating its own.
  window.BpmnEditor = { modeler, store };

  let dirty = false;

  // The draft the server was last told about, so a Heartbeat can skip
  // re-sending content that hasn't changed since.
  let lastSentDraft = null;

  setupZoom(modeler);

  function setDirty(v) {
    dirty = v;
    $('dirty').classList.toggle('on', v);

    // A save settles the content and clears the server's draft, so what it
    // holds is no longer what we last sent.
    if (!v) lastSentDraft = null;
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
      throw await httpError(res);
    }
    return res;
  }

  async function loadContent() {
    try {
      const { warnings } = await modeler.importXML(await store.load());
      if (warnings && warnings.length) console.warn('Import warnings:', warnings);
      modeler.get('canvas').zoom('fit-viewport', 'auto');
      setDirty(false);
    } catch (e) {
      console.error(e);
      toast(errorMessage(e, body.dataset.loadFailedNotice), true);
    }
  }

  async function save() {
    try {
      const { xml } = await modeler.saveXML({ format: true });
      await store.save(xml);
      setDirty(false);
      document.dispatchEvent(new CustomEvent('diagram:saved'));
      toast(body.dataset.savedNotice);
    } catch (e) {
      console.error(e);
      toast(errorMessage(e, body.dataset.saveFailedNotice), true);
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
      // Disabled while asking and releasing, so a second click can't open a second dialog.
      releaseLockButton.disabled = true;

      try {
        // The holder's own unsaved content lives here; for an Administrator
        // releasing someone else's Lock, only the server knows whether the
        // holder left a draft.
        const isHolder = body.dataset.isLockHolder === '1';
        const hasDraft = isHolder
          ? dirty
          : (await (await api('GET', unlockUrl)).json()).has_draft;

        let payload;

        if (hasDraft) {
          const choice = await window.BpmnCommon.chooseRelease({
            message: isHolder ? body.dataset.releaseDraftHolderMessage : body.dataset.releaseDraftAdminMessage,
            saveLabel: body.dataset.releaseSaveLabel,
            discardLabel: body.dataset.releaseDiscardLabel,
            cancelLabel: body.dataset.releaseCancelLabel,
          });

          if (choice === 'cancel') return;

          if (choice === 'save') {
            payload = { save_draft: true };
            if (isHolder) payload.draft = (await modeler.saveXML({ format: true })).xml;
          }
        }

        await api('DELETE', unlockUrl, payload);
        setDirty(false);
        location.href = indexUrl;
      } catch (e) {
        console.error(e);
        toast(errorMessage(e, body.dataset.releaseLockFailedNotice), true);
      } finally {
        releaseLockButton.disabled = false;
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

  // The Lock expires after a few minutes without a Heartbeat (ADR-0003), so
  // the Heartbeat only goes out while the holder is actually using the
  // Editor: an abandoned tab stops renewing and its Lock is released. When
  // there are unsaved changes, the Heartbeat carries them along so the
  // server can keep them as a provisional Version if the Lock expires.
  const HEARTBEAT_INTERVAL_MS = 30000;
  const IDLE_AFTER_MS = 3 * 60 * 1000;
  let lastActivityAt = Date.now();
  ['pointerdown', 'pointermove', 'keydown', 'wheel', 'touchstart'].forEach(name => {
    document.addEventListener(name, () => { lastActivityAt = Date.now(); }, { passive: true });
  });

  async function sendHeartbeat() {
    if (Date.now() - lastActivityAt > IDLE_AFTER_MS) return;

    try {
      // `dirty` stays true until a save, so without this the whole diagram
      // would be re-serialized, re-uploaded, re-parsed and rewritten on
      // every beat of an editing session. The server only needs the draft
      // when it differs from the one it already holds.
      let payload;

      if (dirty) {
        const xml = (await modeler.saveXML({ format: true })).xml;

        if (xml !== lastSentDraft) payload = { draft: xml };
      }

      const res = await api('PATCH', heartbeatUrl, payload);
      const data = await res.json();
      if (payload) lastSentDraft = payload.draft;
      showLockRequestedNotice(data.lock_requested_by);
    } catch (e) {
      console.error(e);

      // The Lock is gone (expired or taken): stop renewing and say so, once.
      if (e.status === 403 || e.status === 423) {
        clearInterval(heartbeatTimer);
        toast(errorMessage(e, body.dataset.saveFailedNotice), true);
      }
    }
  }

  const heartbeatTimer = setInterval(sendHeartbeat, HEARTBEAT_INTERVAL_MS);

  loadContent();
})();
