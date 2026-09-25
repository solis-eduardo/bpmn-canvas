/*
 * Collaborators & Shares panel (ticket 11). Backed by the existing JSON
 * endpoints (DiagramCollaboratorController / ShareController) — no new API
 * shape, this just renders and drives them. Follows the same
 * "header button toggles a side panel" pattern as the Version history
 * panel (see versions.js / #10), but lives in its own module so we don't
 * touch that file.
 */
(function () {
  const body = document.getElementById('editor');
  if (!body || !body.dataset.collaboratorsUrl) return; // panel not rendered for this user

  const slug = body.dataset.slug;
  const collaboratorsUrl = body.dataset.collaboratorsUrl;
  const collaboratorUrlTemplate = body.dataset.collaboratorUrlTemplate;
  const canManageShares = body.dataset.canManageShares === 'true';
  const sharesUrl = body.dataset.sharesUrl;
  const shareDestroyUrlTemplate = body.dataset.shareDestroyUrlTemplate;
  const versionsUrl = body.dataset.versionsUrl;
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  const $ = id => document.getElementById(id);
  const app = $('app');
  const panel = $('collaborators-panel');
  const collabList = $('collab-list');
  const shareList = $('share-list');
  const sharesSection = $('shares-section');
  const inviteForm = $('collab-invite-form');
  const inviteEmail = $('collab-invite-email');
  const shareForm = $('share-create-form');
  const shareDays = $('share-days');
  const shareAllowDownload = $('share-allow-download');
  const sharePinVersion = $('share-pin-version');

  const versionPanel = $('version-panel');
  const versionEntryBtn = $('btn-version-history-entry');

  function toast(msg, isError) {
    if (window.BpmnCommon && window.BpmnCommon.toast) {
      window.BpmnCommon.toast(msg, isError);
    }
  }

  function collaboratorUrl(userId) {
    return collaboratorUrlTemplate.replace('USER_ID', encodeURIComponent(userId));
  }

  function shareDestroyUrl(shareId) {
    return shareDestroyUrlTemplate.replace('SHARE_ID', encodeURIComponent(shareId));
  }

  async function requestJson(url, options) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: Object.assign({
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken,
      }, (options && options.headers) || {}),
      ...options,
    });

    if (!res.ok) {
      const error = new Error('HTTP ' + res.status);
      error.status = res.status;
      try {
        error.body = await res.json();
      } catch (e) {
        // no JSON body
      }
      throw error;
    }

    if (res.status === 204) return null;

    return res.json();
  }

  function togglePanel(open) {
    panel.hidden = !open;
    app.classList.toggle('with-sidebar', open || !versionPanel.hidden);

    if (open) {
      versionPanel.hidden = true;
    }
  }

  if (versionEntryBtn) {
    versionEntryBtn.addEventListener('click', () => {
      if (!panel.hidden) panel.hidden = true;
    });
  }

  // --- Collaborators -------------------------------------------------

  async function loadCollaborators() {
    try {
      const collaborators = await requestJson(collaboratorsUrl);
      renderCollaborators(collaborators || []);
    } catch (e) {
      console.error(e);
      toast('Could not load collaborators.', true);
    }
  }

  function renderCollaborators(collaborators) {
    collabList.innerHTML = '';

    if (!collaborators.length) {
      const empty = document.createElement('li');
      empty.className = 'empty';
      empty.textContent = body.dataset.noCollaboratorsNotice || 'No collaborators yet.';
      collabList.appendChild(empty);
      return;
    }

    collaborators.forEach(collaborator => {
      const li = document.createElement('li');
      li.className = 'collab-row';

      const info = document.createElement('div');
      info.className = 'collab-info';

      const name = document.createElement('span');
      name.className = 'collab-name';
      name.textContent = collaborator.name + ' (' + collaborator.email + ')';
      info.appendChild(name);

      const status = document.createElement('span');
      status.className = 'badge badge-' + collaborator.status;
      status.textContent = collaborator.status === 'requested'
        ? (body.dataset.statusPendingLabel || 'Pending')
        : (body.dataset.statusApprovedLabel || 'Approved');
      info.appendChild(status);

      li.appendChild(info);

      const actions = document.createElement('div');
      actions.className = 'collab-actions';

      if (collaborator.status === 'requested') {
        const approveBtn = document.createElement('button');
        approveBtn.type = 'button';
        approveBtn.textContent = body.dataset.approveLabel || 'Approve';
        approveBtn.addEventListener('click', () => approveCollaborator(collaborator));
        actions.appendChild(approveBtn);

        const refuseBtn = document.createElement('button');
        refuseBtn.type = 'button';
        refuseBtn.className = 'danger';
        refuseBtn.textContent = body.dataset.refuseLabel || 'Refuse';
        refuseBtn.addEventListener('click', () => removeCollaborator(collaborator, true));
        actions.appendChild(refuseBtn);
      } else {
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'danger';
        removeBtn.textContent = body.dataset.removeLabel || 'Remove';
        removeBtn.addEventListener('click', () => removeCollaborator(collaborator, false));
        actions.appendChild(removeBtn);
      }

      li.appendChild(actions);
      collabList.appendChild(li);
    });
  }

  async function approveCollaborator(collaborator) {
    try {
      await requestJson(collaboratorUrl(collaborator.user_id), { method: 'PUT' });
      toast(body.dataset.approvedNotice || 'Collaborator approved.');
      await loadCollaborators();
    } catch (e) {
      console.error(e);
      toast(body.dataset.approveFailedNotice || 'Could not approve this collaborator.', true);
    }
  }

  async function removeCollaborator(collaborator, isRefusal) {
    const confirmMessage = isRefusal
      ? (body.dataset.refuseRequestConfirm || 'Refuse this request?')
      : (body.dataset.removeCollaboratorConfirm || 'Remove this collaborator?');

    if (!confirm(confirmMessage)) return;

    try {
      await requestJson(collaboratorUrl(collaborator.user_id), { method: 'DELETE' });
      toast(body.dataset.removedNotice || 'Collaborator removed.');
      await loadCollaborators();
    } catch (e) {
      console.error(e);
      toast(body.dataset.removeFailedNotice || 'Could not remove this collaborator.', true);
    }
  }

  inviteForm.addEventListener('submit', async event => {
    event.preventDefault();

    const email = inviteEmail.value.trim();
    if (!email) return;

    try {
      await requestJson(collaboratorsUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });
      inviteEmail.value = '';
      toast(body.dataset.invitedNotice || 'Collaborator invited.');
      await loadCollaborators();
    } catch (e) {
      console.error(e);
      toast(body.dataset.inviteFailedNotice || 'Could not invite this collaborator.', true);
    }
  });

  // --- Shares ----------------------------------------------------------

  // Populates the "pin to version" select in the creation form, reusing
  // the same Version history endpoint the Version panel already fetches
  // (see versions.js) — one list, two consumers.
  async function loadVersionsForPinSelect() {
    if (!canManageShares || !sharePinVersion || !versionsUrl) return;

    try {
      const res = await fetch(versionsUrl, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const data = await res.json();
      const days = (data && data.versions) || [];

      while (sharePinVersion.options.length > 1) {
        sharePinVersion.remove(1);
      }

      days.forEach(day => {
        day.items.forEach(version => {
          const option = document.createElement('option');
          option.value = version.id;
          option.textContent = version.name;
          sharePinVersion.appendChild(option);
        });
      });
    } catch (e) {
      console.error(e);
    }
  }

  function formatExpiry(isoString) {
    const date = new Date(isoString);
    const locale = (document.documentElement.lang || '').replace('_', '-') || undefined;
    const formatted = date.toLocaleString(locale, { dateStyle: 'medium', timeStyle: 'short' });

    const template = body.dataset.expiresLabelTemplate || 'Expires EXPIRES_AT';
    return template.replace('EXPIRES_AT', formatted);
  }

  async function loadShares() {
    if (!canManageShares) return;

    try {
      const data = await requestJson(sharesUrl);
      renderShares((data && data.shares) || []);
    } catch (e) {
      console.error(e);
      toast('Could not load shares.', true);
    }
  }

  function renderShares(shares) {
    shareList.innerHTML = '';

    if (!shares.length) {
      const empty = document.createElement('li');
      empty.className = 'empty';
      empty.textContent = body.dataset.noSharesNotice || 'No active shares yet.';
      shareList.appendChild(empty);
      return;
    }

    shares.forEach(share => {
      const li = document.createElement('li');
      li.className = 'share-row';

      const info = document.createElement('div');
      info.className = 'share-info';

      const expiry = document.createElement('span');
      expiry.textContent = formatExpiry(share.expires_at);
      info.appendChild(expiry);

      const download = document.createElement('span');
      download.className = 'badge ' + (share.allow_download ? 'badge-approved' : 'badge-muted');
      download.textContent = share.allow_download
        ? (body.dataset.downloadAllowedLabel || 'Download allowed')
        : (body.dataset.downloadNotAllowedLabel || 'Download not allowed');
      info.appendChild(download);

      const pin = document.createElement('span');
      pin.className = 'badge ' + (share.pinned_version ? 'badge-approved' : 'badge-muted');
      pin.textContent = share.pinned_version
        ? (body.dataset.pinnedToVersionTemplate || 'Pinned to VERSION_NAME').replace('VERSION_NAME', share.pinned_version.name)
        : (body.dataset.followLatestVersionLabel || 'Follow latest version');
      info.appendChild(pin);

      li.appendChild(info);

      const actions = document.createElement('div');
      actions.className = 'share-actions';

      const copyBtn = document.createElement('button');
      copyBtn.type = 'button';
      copyBtn.textContent = body.dataset.copyLinkLabel || 'Copy link';
      copyBtn.addEventListener('click', () => copyShareLink(share));
      actions.appendChild(copyBtn);

      const revokeBtn = document.createElement('button');
      revokeBtn.type = 'button';
      revokeBtn.className = 'danger';
      revokeBtn.textContent = body.dataset.revokeLabel || 'Revoke';
      revokeBtn.addEventListener('click', () => revokeShare(share));
      actions.appendChild(revokeBtn);

      li.appendChild(actions);
      shareList.appendChild(li);
    });
  }

  async function copyShareLink(share) {
    try {
      await navigator.clipboard.writeText(share.url);
      toast(body.dataset.linkCopiedNotice || 'Link copied to clipboard.');
    } catch (e) {
      console.error(e);
      toast(body.dataset.linkCopyFailedNotice || 'Could not copy the link.', true);
    }
  }

  async function revokeShare(share) {
    const confirmMessage = body.dataset.revokeShareConfirm
      || 'Revoke this share? The link will stop working immediately.';
    if (!confirm(confirmMessage)) return;

    try {
      await requestJson(shareDestroyUrl(share.id), { method: 'DELETE' });
      toast(body.dataset.shareRevokedNotice || 'Share revoked.');
      await loadShares();
    } catch (e) {
      console.error(e);
      toast(body.dataset.shareRevokeFailedNotice || 'Could not revoke this share.', true);
    }
  }

  if (shareForm) {
    shareForm.addEventListener('submit', async event => {
      event.preventDefault();

      const days = parseInt(shareDays.value, 10) || 7;
      const allowDownload = !!shareAllowDownload.checked;
      const pinnedVersionId = sharePinVersion && sharePinVersion.value ? sharePinVersion.value : null;

      try {
        await requestJson(sharesUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ days, allow_download: allowDownload, pinned_version_id: pinnedVersionId }),
        });
        toast(body.dataset.shareCreatedNotice || 'Share link created.');
        if (sharePinVersion) sharePinVersion.value = '';
        await loadShares();
      } catch (e) {
        console.error(e);
        toast(body.dataset.shareCreateFailedNotice || 'Could not create the share link.', true);
      }
    });
  }

  if (!canManageShares && sharesSection) {
    sharesSection.hidden = true;
  }

  $('btn-collaborators-entry').addEventListener('click', () => togglePanel(panel.hidden));
  $('btn-collaborators-close').addEventListener('click', () => togglePanel(false));

  loadCollaborators();
  loadShares();
  loadVersionsForPinSelect();

  // Deep link from the notification bell (issue #15): clicking a
  // "requested access" notification lands here with ?open-panel=collaborators.
  if (new URLSearchParams(window.location.search).get('open-panel') === 'collaborators') {
    togglePanel(true);
  }
})();
