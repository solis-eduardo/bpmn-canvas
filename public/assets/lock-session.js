/*
 * Lock session (server build only, ADR-0019): keeps the holder's Lock alive
 * with Heartbeats, releases it on request and shows who is asking for it.
 * Built only on the Editor's public API (isDirty, revision, currentXml,
 * markClean, on), so the Editor core itself knows nothing about Locks.
 */

import { toast, errorMessage, chooseRelease } from './common.js';

const HEARTBEAT_INTERVAL_MS = 30000;
export const IDLE_AFTER_MS = 2 * 60 * 1000;
const ACTIVITY_EVENTS = ['pointerdown', 'pointermove', 'keydown', 'wheel', 'touchstart'];

/**
 * Acquires the Lock for the Editor that has just loaded (ADR-0003). Resolves
 * to false when the server refuses it — 423 when someone else holds the Lock,
 * 403 when the User can't edit — and the caller then falls back to the
 * read-only Viewer. Any other failure is thrown, not treated as a refusal, so
 * a transient error can't send the page into a reload loop.
 *
 * @param {Object} options
 * @param {(method: string, url: string, payload?: Object) => Promise<any>} options.request
 * @param {string} options.url  the Lock acquire endpoint (POST)
 * @returns {Promise<boolean>}
 */
export async function acquireLock({ request, url }) {
  try {
    await request('POST', url);
    return true;
  } catch (e) {
    if (e.status === 423 || e.status === 403) return false;

    throw e;
  }
}

/**
 * One Heartbeat at a time. The Lock expires after a few minutes without one
 * (ADR-0003), so a beat only goes out while the holder is actually using the
 * Editor: an abandoned tab stops renewing and its Lock is released. When
 * there are unsaved changes, the beat carries them along so the server can
 * keep them as a provisional Version if the Lock expires.
 *
 * @param {Object} options
 * @param {Object} options.editor  isDirty(), revision(), currentXml(), on('dirty', …)
 * @param {(payload?: {draft: string}) => Promise<{lock_requested_by: ?string}>} options.send
 * @param {number} [options.idleAfterMs]
 * @param {() => number} [options.now]
 * @returns {{beat: () => Promise<?{lockRequestedBy: ?string}>, touch: () => void}}
 *   beat() resolves to null when skipped for idleness, and rejects when the
 *   server refused it
 */
export function createHeartbeat({ editor, send, idleAfterMs = IDLE_AFTER_MS, now = Date.now }) {
  let lastActivityAt = now();

  // The revision the server was last sent a draft for. A save settles the
  // content and clears the server's draft, so the mark is dropped then.
  let lastSentRevision = null;
  editor.on('dirty', dirty => { if (!dirty) lastSentRevision = null; });

  return {
    touch() {
      lastActivityAt = now();
    },

    async beat() {
      if (now() - lastActivityAt > idleAfterMs) return null;

      // Serializing the diagram is costly, so it only happens when an edit
      // happened since the last draft the server holds.
      let payload;
      let sentRevision = null;

      if (editor.isDirty() && editor.revision() !== lastSentRevision) {
        sentRevision = editor.revision();
        payload = { draft: await editor.currentXml() };
      }

      const data = await send(payload);
      if (payload) lastSentRevision = sentRevision;

      return { lockRequestedBy: data.lock_requested_by ?? null };
    },
  };
}

/**
 * The timer that sends a Heartbeat every HEARTBEAT_INTERVAL_MS. A 403 or 423
 * answer means the Lock is gone: the loop stops and reports it once through
 * onLost, instead of retrying a Lock the server no longer grants.
 *
 * @param {Object} options
 * @param {{beat: () => Promise<?{lockRequestedBy: ?string}>}} options.heartbeat
 * @param {(requestedBy: ?string) => void} options.onRequested
 * @param {() => void} options.onLost
 * @param {number} [options.intervalMs]
 */
export function createHeartbeatLoop({ heartbeat, onRequested, onLost, intervalMs = HEARTBEAT_INTERVAL_MS }) {
  let timer = null;

  // Bumped by resume(). A beat sent before a save or a re-acquire that then
  // fails says nothing about the Lock as it is now, so it is ignored.
  let epoch = 0;

  function start() {
    if (timer === null) timer = setInterval(beatNow, intervalMs);
  }

  function stop() {
    clearInterval(timer);
    timer = null;
  }

  async function beatNow() {
    const sentEpoch = epoch;

    try {
      const answer = await heartbeat.beat();
      if (answer) onRequested(answer.lockRequestedBy);
    } catch (e) {
      console.error(e);

      if ((e.status === 403 || e.status === 423) && sentEpoch === epoch) {
        stop();
        onLost();
      }
    }
  }

  return {
    start,
    stop,
    beatNow,

    /** The Lock is held again (a save took it back or it was re-acquired): renews it from now on. */
    resume() {
      epoch += 1;
      start();
    },
  };
}

/**
 * A save the server refused with 423 means the Lock is gone (see
 * AuthorizesDiagramWrites): the Heartbeat stops and the lost notice replaces
 * the generic save-failure toast. Other errors are left to the Editor core.
 *
 * @returns {boolean} whether the error was handled here
 */
export function handleSaveFailure(error, { stopHeartbeat, showLockLost }) {
  if (error?.status !== 423) return false;

  stopHeartbeat();
  showLockLost();
  return true;
}

/**
 * Asks for the Lock again. The server answers "acquired" when it was free;
 * otherwise the holder is asked and the answer is "waiting", so nothing is
 * resumed and the lost notice stays up. Only once acquired is the notice
 * dismissed and the Heartbeat restarted.
 *
 * @returns {Promise<boolean>} whether the Lock was acquired
 */
export async function reacquireLock({ request, url, loop, onAcquired }) {
  const answer = await request('POST', url);
  if (answer?.state !== 'acquired') return false;

  onAcquired();
  loop.resume();
  await loop.beatNow();
  return true;
}

/**
 * Wires the Lock session into the Editor page: periodic Heartbeats, the
 * lock-request banner, the lost-Lock banner and the release button.
 *
 * @param {Object} options
 * @param {Object} options.editor  the Editor core's public API
 * @param {(method: string, url: string, payload?: Object) => Promise<any>} options.request
 * @param {{heartbeat: string, lock: string, unlock: string, index: string}} options.urls
 * @param {boolean} options.isHolder  whether the viewer holds the Lock (else an Administrator)
 * @param {(key: string) => string} options.t  localized UI string by key
 * @param {?HTMLElement} options.banner  the lock-request banner
 * @param {?HTMLButtonElement} options.releaseButton
 * @param {?HTMLElement} options.lostBanner  shown when the Lock is lost; holds
 *   a `.lock-lost-text`, a `.lock-lost-reacquire` and a `.lock-lost-dismiss` button
 */
export function startLockSession({ editor, request, urls, isHolder, t, banner, releaseButton, lostBanner }) {
  // The Lock holder's only signal that someone else wants the Lock is this
  // same heartbeat poll (see LockController::update) — no separate poll
  // loop is started just for this (issue #9).
  function showLockRequestedNotice(requestedByName) {
    if (!banner) return;

    if (!requestedByName) {
      banner.hidden = true;
      banner.textContent = '';
      return;
    }

    banner.textContent = (t('lockRequestedByNotice') || ':name').replace(':name', requestedByName);
    banner.hidden = false;
  }

  function showLockLost() {
    if (!lostBanner) {
      toast(t('lockLostNotice'), true);
      return;
    }

    lostBanner.querySelector('.lock-lost-text').textContent = t('lockLostNotice');
    lostBanner.hidden = false;
  }

  function hideLockLost() {
    if (lostBanner) lostBanner.hidden = true;
  }

  const heartbeat = createHeartbeat({ editor, send: payload => request('PATCH', urls.heartbeat, payload) });
  ACTIVITY_EVENTS.forEach(name => document.addEventListener(name, heartbeat.touch, { passive: true }));

  const loop = createHeartbeatLoop({
    heartbeat,
    onRequested: showLockRequestedNotice,
    onLost: showLockLost,
  });
  loop.start();

  // A successful save takes a free Lock back (DiagramContentController::update),
  // so the Heartbeat resumes and the lost notice no longer applies.
  editor.on('saved', () => {
    hideLockLost();
    loop.resume();
  });

  // A save refused for the Lock is the same signal as a refused Heartbeat.
  editor.on('saveFailed', error => handleSaveFailure(error, {
    stopHeartbeat: loop.stop,
    showLockLost,
  }));

  if (lostBanner) {
    lostBanner.querySelector('.lock-lost-dismiss').addEventListener('click', hideLockLost);

    const reacquire = lostBanner.querySelector('.lock-lost-reacquire');

    reacquire.addEventListener('click', async () => {
      reacquire.disabled = true;

      try {
        await reacquireLock({ request, url: urls.lock, loop, onAcquired: hideLockLost });
      } catch (e) {
        console.error(e);
        toast(errorMessage(e, t('reacquireLockFailedNotice')), true);
      } finally {
        reacquire.disabled = false;
      }
    });
  }

  if (!releaseButton) return;

  releaseButton.addEventListener('click', async () => {
    // Disabled while asking and releasing, so a second click can't open a second dialog.
    releaseButton.disabled = true;

    try {
      // The holder's own unsaved content lives here; for an Administrator
      // releasing someone else's Lock, only the server knows whether the
      // holder left a draft.
      const hasDraft = isHolder ? editor.isDirty() : (await request('GET', urls.unlock)).has_draft;

      let payload;

      if (hasDraft) {
        const choice = await chooseRelease({
          message: isHolder ? t('releaseDraftHolderMessage') : t('releaseDraftAdminMessage'),
          saveLabel: t('releaseSaveLabel'),
          discardLabel: t('releaseDiscardLabel'),
          cancelLabel: t('releaseCancelLabel'),
        });

        if (choice === 'cancel') return;

        if (choice === 'save') {
          payload = { save_draft: true };
          if (isHolder) payload.draft = await editor.currentXml();
        }
      }

      await request('DELETE', urls.unlock, payload);
      // Whatever was unsaved was kept or deliberately discarded: leaving
      // must not ask again.
      editor.markClean();
      location.href = urls.index;
    } catch (e) {
      console.error(e);
      toast(errorMessage(e, t('releaseLockFailedNotice')), true);
    } finally {
      releaseButton.disabled = false;
    }
  });
}
