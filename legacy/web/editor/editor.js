(function () {
  const { toast, exportBpmn, exportSvg, exportPng, setupDropdowns, setupZoom, baseName } = window.BpmnCommon;

  const EMPTY_DIAGRAM = `<?xml version="1.0" encoding="UTF-8"?>
<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                  id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" name="Início" />
  </bpmn:process>
  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">
      <bpmndi:BPMNShape id="StartEvent_1_di" bpmnElement="StartEvent_1">
        <dc:Bounds x="180" y="160" width="36" height="36" />
      </bpmndi:BPMNShape>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>`;

  const modeler = new BpmnJS({ container: '#canvas' });
  const $ = id => document.getElementById(id);
  const nameInput = $('name');

  let currentFile = null;   // nome do arquivo salvo no servidor (ou null se novo)
  let files = [];           // lista vinda do servidor
  let dirty = false;

  setupDropdowns();
  setupZoom(modeler);

  // ---------- helpers ----------
  function slugify(text) {
    return (text || '')
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/\.bpmn$/i, '')
      .toLowerCase()
      .replace(/[^a-z0-9._-]+/g, '-')
      .replace(/^[^a-z0-9]+|-+$/g, '')
      .slice(0, 120);
  }

  function setDirty(v) {
    dirty = v;
    $('dirty').classList.toggle('on', v);
  }

  function confirmDiscard() {
    return !dirty || confirm('Há alterações não salvas. Descartar?');
  }

  function shareUrl(file) {
    return location.origin + '/view/?d=' + encodeURIComponent(file);
  }

  async function api(method, file, body) {
    const url = '/api/files/' + (file ? encodeURIComponent(file) : '');
    const res = await fetch(url, {
      method,
      body,
      cache: 'no-store',
      headers: body ? { 'Content-Type': 'application/xml; charset=utf-8' } : undefined
    });
    if (!res.ok && !(method === 'DELETE' && res.status === 404)) {
      throw new Error(method + ' ' + url + ' → HTTP ' + res.status);
    }
    return res;
  }

  // ---------- lista ----------
  async function loadList() {
    try {
      const res = await api('GET');
      const data = await res.json();
      files = data
        .filter(f => f.type === 'file' && /\.bpmn$/i.test(f.name))
        .sort((a, b) => new Date(b.mtime) - new Date(a.mtime));
      renderList();
    } catch (e) {
      console.error(e);
      toast('Não foi possível carregar a lista de diagramas.', true);
    }
  }

  function renderList() {
    const ul = $('list');
    const q = $('filter').value.trim().toLowerCase();
    const shown = files.filter(f => !q || f.name.toLowerCase().includes(q));
    ul.innerHTML = '';
    if (!shown.length) {
      const li = document.createElement('li');
      li.className = 'empty';
      li.textContent = files.length ? 'Nenhum resultado.' : 'Nenhum diagrama salvo ainda.';
      ul.appendChild(li);
      return;
    }
    for (const f of shown) {
      const li = document.createElement('li');
      if (f.name === currentFile) li.classList.add('active');
      const title = document.createElement('span');
      title.textContent = baseName(f.name);
      const meta = document.createElement('small');
      meta.textContent = new Date(f.mtime).toLocaleString('pt-BR');
      li.append(title, meta);
      li.addEventListener('click', () => openFile(f.name));
      ul.appendChild(li);
    }
  }

  // ---------- abrir / novo / importar ----------
  async function importXml(xml) {
    const { warnings } = await modeler.importXML(xml);
    if (warnings && warnings.length) console.warn('Avisos na importação:', warnings);
    modeler.get('canvas').zoom('fit-viewport', 'auto');
    setDirty(false);
  }

  async function openFile(file) {
    if (file === currentFile && !dirty) return;
    if (!confirmDiscard()) return;
    try {
      const res = await api('GET', file);
      await importXml(await res.text());
      currentFile = file;
      nameInput.value = baseName(file);
      history.replaceState(null, '', '#' + encodeURIComponent(file));
      renderList();
    } catch (e) {
      console.error(e);
      toast('Erro ao abrir "' + file + '": ' + e.message, true);
    }
  }

  async function newDiagram() {
    if (!confirmDiscard()) return;
    await importXml(EMPTY_DIAGRAM);
    currentFile = null;
    nameInput.value = '';
    history.replaceState(null, '', location.pathname);
    renderList();
    nameInput.focus();
  }

  $('btn-new').addEventListener('click', newDiagram);
  $('btn-import').addEventListener('click', () => { if (confirmDiscard()) $('file-input').click(); });
  $('file-input').addEventListener('change', async ev => {
    const f = ev.target.files[0];
    ev.target.value = '';
    if (!f) return;
    try {
      await importXml(await f.text());
      currentFile = null;
      nameInput.value = slugify(f.name);
      setDirty(true);
      renderList();
      toast('Arquivo importado. Clique em Salvar para publicá-lo.');
    } catch (e) {
      console.error(e);
      toast('Arquivo inválido: ' + e.message, true);
    }
  });

  // ---------- salvar ----------
  async function save() {
    const slug = slugify(nameInput.value);
    if (!slug) {
      toast('Informe um nome para o diagrama.', true);
      nameInput.focus();
      return;
    }
    const file = slug + '.bpmn';
    if (file !== currentFile && files.some(f => f.name === file)) {
      if (!confirm('Já existe um diagrama "' + slug + '". Substituir?')) return;
    }
    try {
      const { xml } = await modeler.saveXML({ format: true });
      await api('PUT', file, xml);
      const savedAs = currentFile && file !== currentFile;
      currentFile = file;
      nameInput.value = slug;
      setDirty(false);
      history.replaceState(null, '', '#' + encodeURIComponent(file));
      await loadList();
      toast(savedAs ? 'Salvo como "' + slug + '" (o original foi mantido).' : 'Salvo.');
    } catch (e) {
      console.error(e);
      toast('Erro ao salvar: ' + e.message, true);
    }
  }

  $('btn-save').addEventListener('click', save);
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      save();
    }
  });

  // ---------- compartilhar ----------
  $('btn-share').addEventListener('click', async () => {
    if (!currentFile) { toast('Salve o diagrama antes de compartilhar.', true); return; }
    if (dirty) toast('Atenção: o link mostra a última versão salva.');
    const url = shareUrl(currentFile);
    try {
      await navigator.clipboard.writeText(url);
      toast('Link copiado: ' + url);
    } catch (_) {
      prompt('Copie o link público (somente leitura):', url);
    }
  });

  // ---------- excluir ----------
  $('btn-delete').addEventListener('click', async () => {
    if (!currentFile) { toast('Este diagrama ainda não foi salvo.', true); return; }
    if (!confirm('Excluir "' + baseName(currentFile) + '"? O link público deixará de funcionar.')) return;
    try {
      await api('DELETE', currentFile);
      setDirty(false);
      toast('Excluído.');
      currentFile = null;
      await loadList();
      await newDiagram();
    } catch (e) {
      console.error(e);
      toast('Erro ao excluir: ' + e.message, true);
    }
  });

  // ---------- exportar ----------
  document.querySelectorAll('[data-export]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const name = currentFile || (slugify(nameInput.value) || 'diagrama') + '.bpmn';
      try {
        const kind = btn.dataset.export;
        if (kind === 'bpmn') await exportBpmn(modeler, name);
        if (kind === 'svg') await exportSvg(modeler, name);
        if (kind === 'png') await exportPng(modeler, name);
      } catch (e) {
        console.error(e);
        toast('Falha ao exportar: ' + e.message, true);
      }
    });
  });

  // ---------- estado ----------
  modeler.on('commandStack.changed', () => setDirty(true));
  $('filter').addEventListener('input', renderList);
  $('btn-refresh').addEventListener('click', loadList);
  window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  // ---------- inicialização ----------
  (async () => {
    await loadList();
    const fromHash = decodeURIComponent(location.hash.slice(1));
    if (fromHash && files.some(f => f.name === fromHash)) {
      await openFile(fromHash);
    } else {
      await newDiagram();
    }
  })();
})();
