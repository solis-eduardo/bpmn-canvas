import {
  exportBpmn, exportSvg, exportPng, setupDropdowns, setupZoom, baseName, httpError, chooseRelease, toast, errorMessage,
} from './common.js';
import { startComments } from './comments.js';

(async function () {
  const msg = document.getElementById('message');
  const body = document.getElementById('viewer');
  const name = baseName(body.dataset.title) + '.bpmn';
  const viewer = new BpmnJS({ container: '#canvas' });

  setupDropdowns();
  setupZoom(viewer);

  try {
    const xml = JSON.parse(document.getElementById('diagram-xml').textContent);
    const { warnings } = await viewer.importXML(xml);
    if (warnings && warnings.length) console.warn('Import warnings:', warnings);

    viewer.get('canvas').zoom('fit-viewport', 'auto');
    msg.remove();
    startComments({ modeler: viewer });
  } catch (err) {
    console.error(err);
    msg.textContent = body.dataset.openFailedNotice || 'Could not open the diagram.';
    return;
  }

  // Administrators opening a locked Diagram in the Viewer can force-release it.
  const releaseButton = document.getElementById('btn-release-lock');
  if (releaseButton) {
    releaseButton.addEventListener('click', async () => {
      const request = async (method, payload) => {
        const res = await fetch(releaseButton.dataset.unlockUrl, {
          method,
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: payload ? JSON.stringify(payload) : undefined,
        });
        if (!res.ok) throw await httpError(res);
        return res;
      };

      // Disabled while asking and releasing, so a second click can't open a second dialog.
      releaseButton.disabled = true;

      try {
        // Only the server knows whether the holder left an unsaved draft.
        const { has_draft: hasDraft } = await (await request('GET')).json();
        let payload;

        if (hasDraft) {
          const choice = await chooseRelease({
            message: releaseButton.dataset.releaseDraftMessage,
            saveLabel: releaseButton.dataset.releaseSaveLabel,
            discardLabel: releaseButton.dataset.releaseDiscardLabel,
            cancelLabel: releaseButton.dataset.releaseCancelLabel,
          });

          if (choice === 'cancel') return;
          if (choice === 'save') payload = { save_draft: true };
        }

        await request('DELETE', payload);
        location.reload();
      } catch (e) {
        console.error(e);
        toast(errorMessage(e, releaseButton.dataset.releaseFailedNotice), true);
      } finally {
        releaseButton.disabled = false;
      }
    });
  }

  document.querySelectorAll('[data-export]').forEach(btn => {
    btn.addEventListener('click', async () => {
      try {
        const kind = btn.dataset.export;
        if (kind === 'png') await exportPng(viewer, name);
        if (kind === 'svg') await exportSvg(viewer, name);
        if (kind === 'bpmn') await exportBpmn(viewer, name);
      } catch (e) {
        console.error(e);
      }
    });
  });
})();
