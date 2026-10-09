/*
 * The bell (ticket 15): unread count polling + dropdown + mark as read.
 * Self-contained — rendered on every screen via the shared layout, so it
 * can't depend on common.js/editor.js (those only load in the Editor).
 * Polling interval matches the heartbeat's (see editor.js, #9): 30s.
 */
(function () {
  const root = document.getElementById('notification-bell');
  if (!root) return;

  const unreadCountUrl = root.dataset.unreadCountUrl;
  const listUrl = root.dataset.listUrl;
  const readAllUrl = root.dataset.readAllUrl;
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  const button = document.getElementById('btn-notification-bell');
  const dropdown = document.getElementById('notification-dropdown');
  const badge = document.getElementById('notification-unread-badge');
  const list = document.getElementById('notification-list');
  const markAllBtn = document.getElementById('btn-notification-mark-all-read');

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
      throw error;
    }

    if (res.status === 204) return null;

    return res.json();
  }

  function setUnreadCount(count) {
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.classList.remove('hidden');
    } else {
      badge.classList.add('hidden');
      badge.textContent = '';
    }
  }

  async function refreshUnreadCount() {
    try {
      const data = await requestJson(unreadCountUrl);
      setUnreadCount(data.count || 0);
    } catch (e) {
      console.error(e);
    }
  }

  function renderList(notifications) {
    list.innerHTML = '';

    if (!notifications.length) {
      const empty = document.createElement('li');
      empty.className = 'px-3 py-4 text-sm text-gray-500';
      empty.textContent = root.dataset.emptyNotice || 'No notifications yet.';
      list.appendChild(empty);
      return;
    }

    notifications.forEach(notification => {
      const li = document.createElement('li');

      const link = document.createElement('a');
      link.href = notification.url;
      link.className = 'block px-3 py-2 text-sm hover:bg-gray-50 '
        + (notification.read ? 'text-gray-500' : 'font-medium text-gray-900');
      link.textContent = notification.message;

      // Already on the page the notification opens (the holder's open
      // Editor): navigating would just reload it, so mark it read in place.
      if (notification.opens && new URL(notification.opens, location.href).pathname === location.pathname) {
        link.addEventListener('click', async event => {
          event.preventDefault();
          try {
            // The redirect back to this page isn't followed: marking it read
            // is all that's needed, so the response is opaque and ignored.
            await fetch(notification.url, { credentials: 'same-origin', redirect: 'manual' });
          } catch (e) {
            console.error(e);
          }
          closeDropdown();
          refreshUnreadCount();
        });
      }

      li.appendChild(link);
      list.appendChild(li);
    });
  }

  async function loadList() {
    try {
      const notifications = await requestJson(listUrl);
      renderList(notifications || []);
    } catch (e) {
      console.error(e);
    }
  }

  function openDropdown() {
    dropdown.hidden = false;
    button.setAttribute('aria-expanded', 'true');
    loadList();
  }

  function closeDropdown() {
    dropdown.hidden = true;
    button.setAttribute('aria-expanded', 'false');
  }

  button.addEventListener('click', event => {
    event.stopPropagation();
    if (dropdown.hidden) {
      openDropdown();
    } else {
      closeDropdown();
    }
  });

  dropdown.addEventListener('click', event => event.stopPropagation());

  document.addEventListener('click', () => {
    if (!dropdown.hidden) closeDropdown();
  });

  markAllBtn.addEventListener('click', async () => {
    try {
      await requestJson(readAllUrl, { method: 'POST' });
      await loadList();
      await refreshUnreadCount();
    } catch (e) {
      console.error(e);
    }
  });

  refreshUnreadCount();
  setInterval(refreshUnreadCount, 30000);
})();
