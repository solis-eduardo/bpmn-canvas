(async function () {
  const { toast, exportBpmn, exportSvg, exportPng, setupDropdowns, setupZoom, baseName } = window.BpmnCommon;
  const msg = document.getElementById('message');
  const viewer = new BpmnJS({ container: '#canvas' });

  setupDropdowns();
  setupZoom(viewer);

  const name = new URLSearchParams(location.search).get('d');
  if (!name || !/^[A-Za-z0-9][A-Za-z0-9._-]*\.bpmn$/.test(name)) {
    msg.textContent = 'Link inválido: nenhum diagrama informado.';
    return;
  }

  document.title = baseName(name) + ' — BPMN';
  document.getElementById('title').textContent = baseName(name);

  try {
    const res = await fetch('/files/' + encodeURIComponent(name), { cache: 'no-cache' });
    if (res.status === 404) throw new Error('Diagrama não encontrado. Ele pode ter sido renomeado ou excluído.');
    if (!res.ok) throw new Error('Erro ao carregar o diagrama (HTTP ' + res.status + ').');
    const xml = await res.text();

    const { warnings } = await viewer.importXML(xml);
    if (warnings && warnings.length) console.warn('Avisos na importação:', warnings);

    viewer.get('canvas').zoom('fit-viewport', 'auto');
    msg.remove();
    document.getElementById('btn-download').disabled = false;
  } catch (err) {
    console.error(err);
    msg.textContent = err.message || 'Não foi possível abrir o diagrama.';
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
        toast('Falha ao exportar: ' + e.message, true);
      }
    });
  });
})();
