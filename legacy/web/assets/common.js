/* Utilitários compartilhados entre editor e viewer */
(function () {
  function toast(msg, isError) {
    let el = document.querySelector('.toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'toast';
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.toggle('error', !!isError);
    el.classList.add('show');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), isError ? 5000 : 2500);
  }

  function download(blob, filename) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
  }

  function baseName(name) {
    return (name || 'diagrama.bpmn').replace(/\.bpmn$/i, '');
  }

  async function exportBpmn(bpmnJS, name) {
    const { xml } = await bpmnJS.saveXML({ format: true });
    download(new Blob([xml], { type: 'application/xml' }), baseName(name) + '.bpmn');
  }

  async function exportSvg(bpmnJS, name) {
    const { svg } = await bpmnJS.saveSVG();
    download(new Blob([svg], { type: 'image/svg+xml' }), baseName(name) + '.svg');
  }

  async function exportPng(bpmnJS, name, scale) {
    scale = scale || 2;
    const { svg } = await bpmnJS.saveSVG();
    const doc = new DOMParser().parseFromString(svg, 'image/svg+xml').documentElement;
    const w = parseFloat(doc.getAttribute('width')) || 1000;
    const h = parseFloat(doc.getAttribute('height')) || 800;
    const img = new Image();
    const url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml;charset=utf-8' }));
    await new Promise((resolve, reject) => { img.onload = resolve; img.onerror = reject; img.src = url; });
    const canvas = document.createElement('canvas');
    canvas.width = Math.ceil(w * scale);
    canvas.height = Math.ceil(h * scale);
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
    URL.revokeObjectURL(url);
    const blob = await new Promise(r => canvas.toBlob(r, 'image/png'));
    download(blob, baseName(name) + '.png');
  }

  function setupDropdowns() {
    document.querySelectorAll('.dropdown > button').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        const dd = btn.parentElement;
        const wasOpen = dd.classList.contains('open');
        document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
        if (!wasOpen) dd.classList.add('open');
      });
    });
    document.addEventListener('click', () =>
      document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open')));
  }

  function setupZoom(bpmnJS) {
    const canvas = bpmnJS.get('canvas');
    const on = (id, fn) => { const el = document.getElementById(id); if (el) el.addEventListener('click', fn); };
    on('zoom-in', () => canvas.zoom(canvas.zoom() * 1.2));
    on('zoom-out', () => canvas.zoom(canvas.zoom() / 1.2));
    on('zoom-fit', () => canvas.zoom('fit-viewport', 'auto'));
  }

  window.BpmnCommon = { toast, download, exportBpmn, exportSvg, exportPng, setupDropdowns, setupZoom, baseName };
})();
