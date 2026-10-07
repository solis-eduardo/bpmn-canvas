(async function () {
  const { exportBpmn, exportSvg, exportPng, setupDropdowns, setupZoom, baseName } = window.BpmnCommon;
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
  } catch (err) {
    console.error(err);
    msg.textContent = body.dataset.openFailedNotice || 'Could not open the diagram.';
    return;
  }

  // Administrators opening a locked Diagram in the Viewer can force-release it.
  const releaseButton = document.getElementById('btn-release-lock');
  if (releaseButton) {
    releaseButton.addEventListener('click', async () => {
      try {
        const res = await fetch(releaseButton.dataset.unlockUrl, {
          method: 'DELETE',
          credentials: 'same-origin',
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
        });
        if (!res.ok) throw await window.BpmnCommon.httpError(res);
        location.reload();
      } catch (e) {
        console.error(e);
        window.BpmnCommon.toast(window.BpmnCommon.errorMessage(e, releaseButton.dataset.releaseFailedNotice), true);
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
