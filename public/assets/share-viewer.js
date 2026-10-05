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
