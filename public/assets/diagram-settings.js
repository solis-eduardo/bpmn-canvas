/* The Editor's info panel as a settings form (issue #195): click-to-edit
   Title, Tags saved as the chips change, and a modal to transfer the Diagram
   Owner role. Every change goes through the JSON endpoints and applies at once,
   without leaving the Diagram. */
(function () {
  const { toast, errorMessage } = window.BpmnCommon;

  const editor = document.getElementById('editor');
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  const $ = id => document.getElementById(id);

  async function putJson(url, payload) {
    const res = await fetch(url, {
      method: 'PUT',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      },
      body: JSON.stringify(payload),
    });
    if (!res.ok) throw await window.BpmnCommon.httpError(res);
    return res.json();
  }

  // The first validation message for `field`, else the server's own message, else `fallback`.
  function failureMessage(error, field, fallback) {
    const validation = error && error.body && error.body.errors && error.body.errors[field];
    return (validation && validation[0]) || errorMessage(error, fallback);
  }

  function showError(el, message) {
    el.textContent = message || '';
    el.hidden = !message;
  }

  // --- Title -----------------------------------------------------------

  const titleView = $('info-title-view');
  const titleForm = $('info-title-form');
  const titleInput = $('info-title-input');
  const titleError = $('info-title-error');
  let currentTitle = $('info-title').textContent;

  function showTitleForm(open) {
    titleView.hidden = open;
    titleForm.hidden = !open;
    showError(titleError, '');
    if (open) {
      titleInput.value = currentTitle;
      titleInput.focus();
      titleInput.select();
    }
  }

  // The slug changed with the title: keep the address bar and the page's own
  // labels in step. The old slug keeps answering, so URLs already read from
  // the page stay valid.
  function applyRename({ title, slug }) {
    const suffix = editor.dataset.documentTitleSuffix || '';
    currentTitle = title;
    $('info-title').textContent = title;
    $('editor-brand').textContent = title;
    $('info-slug').textContent = slug;
    document.title = title + suffix;

    const path = location.pathname.replace(/^(.*\/diagrams\/)[^/]+/, '$1' + encodeURIComponent(slug));
    history.replaceState(history.state, '', path + location.search + location.hash);

    const editLink = $('info-edit-link');
    editLink.href = editLink.href.replace(/(\/diagrams\/)[^/]+/, '$1' + encodeURIComponent(slug));
  }

  $('info-title-edit').addEventListener('click', () => showTitleForm(true));
  $('info-title-cancel').addEventListener('click', () => showTitleForm(false));
  titleInput.addEventListener('keydown', e => { if (e.key === 'Escape') showTitleForm(false); });

  titleForm.addEventListener('submit', async e => {
    e.preventDefault();
    const title = titleInput.value.trim();
    if (title === currentTitle) return showTitleForm(false);

    try {
      applyRename(await putJson(editor.dataset.renameUrl, { title }));
      showTitleForm(false);
      toast(editor.dataset.renamedNotice);
    } catch (error) {
      console.error(error);
      showError(titleError, failureMessage(error, 'title', editor.dataset.renameFailedNotice));
    }
  });

  // --- Tags ------------------------------------------------------------

  const tagsSelect = document.querySelector('#info-tags-form select');
  let savingTags = false;
  let tagsDirty = false;

  // One request at a time: chips picked while a save is in flight are sent
  // right after it, as the full set, so the last state always wins.
  async function saveTags() {
    tagsDirty = true;
    if (savingTags) return;
    savingTags = true;

    try {
      while (tagsDirty) {
        tagsDirty = false;
        const tags = Array.from(tagsSelect.selectedOptions).map(option => option.value);
        await putJson(editor.dataset.tagsUrl, { tags });
      }
      toast(editor.dataset.tagsSavedNotice);
    } catch (error) {
      console.error(error);
      toast(failureMessage(error, 'tags', editor.dataset.tagsSaveFailedNotice), true);
    } finally {
      savingTags = false;
    }
  }

  $('info-tags-form').addEventListener('submit', e => e.preventDefault());
  tagsSelect.addEventListener('change', saveTags);

  // --- Owner transfer (Diagram Owner and Administrators only) ------------

  const dialog = $('owner-transfer-dialog');
  if (!dialog) return;

  const transferForm = $('owner-transfer-form');
  const transferError = $('owner-transfer-error');
  const giveUp = $('owner-transfer-give-up');
  const newOwner = transferForm.elements.user_id;

  $('info-owner-transfer').addEventListener('click', () => {
    showError(transferError, '');
    dialog.showModal();
  });
  $('owner-transfer-cancel').addEventListener('click', () => dialog.close());

  transferForm.addEventListener('submit', async e => {
    e.preventDefault();
    if (!newOwner.value) return showError(transferError, newOwner.dataset.placeholder);

    try {
      await putJson(editor.dataset.ownerUrl, {
        user_id: Number(newOwner.value),
        give_up_access: giveUp.checked,
      });
    } catch (error) {
      console.error(error);
      return showError(transferError, failureMessage(error, 'user_id', editor.dataset.ownerTransferFailedNotice));
    }

    // Who may manage Collaborators, Shares and the transfer itself just
    // changed: reload so the page shows what the User can still do, or
    // leave when they gave up their access altogether.
    if (giveUp.checked) location.href = editor.dataset.indexUrl;
    else location.reload();
  });
})();
