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

  const modeler = new BpmnJS({ container: '#canvas' });
  const $ = id => document.getElementById(id);
  const nameInput = $('name');

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
      if (warnings && warnings.length) console.warn('Avisos na importação:', warnings);
      modeler.get('canvas').zoom('fit-viewport', 'auto');
      setDirty(false);
    } catch (e) {
      console.error(e);
      toast('Não foi possível carregar o diagrama.', true);
    }
  }

  async function save() {
    try {
      const { xml } = await modeler.saveXML({ format: true });
      await api('PUT', saveUrl, { content: xml });
      setDirty(false);
      toast('Salvo.');
    } catch (e) {
      console.error(e);
      toast('Erro ao salvar: ' + e.message, true);
    }
  }

  async function rename() {
    const title = nameInput.value.trim();
    if (!title) {
      toast('Informe um nome para o diagrama.', true);
      return;
    }
    try {
      const data = await (await api('PUT', renameUrl, { title })).json();
      location.href = data.slug ? renameUrl.replace(/\/diagramas\/[^/]+/, '/diagramas/' + data.slug) : location.href;
    } catch (e) {
      console.error(e);
      toast('Erro ao renomear: ' + e.message, true);
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
    if (!confirm('Excluir "' + nameInput.value + '"?')) return;
    try {
      await api('DELETE', deleteUrl);
      toast('Excluído.');
      location.href = indexUrl;
    } catch (e) {
      console.error(e);
      toast('Erro ao excluir: ' + e.message, true);
    }
  });

  modeler.on('commandStack.changed', () => setDirty(true));
  window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  const heartbeatInterval = setInterval(() => {
    api('POST', heartbeatUrl).catch(e => console.error(e));
  }, 30000);

  function releaseLock() {
    clearInterval(heartbeatInterval);
    fetch(unlockUrl, {
      method: 'DELETE',
      credentials: 'same-origin',
      keepalive: true,
      headers: { 'X-CSRF-TOKEN': csrfToken },
    }).catch(() => {});
  }

  window.addEventListener('pagehide', releaseLock);

  loadContent();
})();
