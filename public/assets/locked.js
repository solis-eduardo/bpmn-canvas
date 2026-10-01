/* Blocked-screen behavior: requesting the Editing Lock and waiting for its
 * explicit release (issue #95). */
(function () {
  const el = document.getElementById('lock-request');
  if (!el) return;

  const requestUrl = el.dataset.requestUrl;
  const showUrl = el.dataset.showUrl;
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  const btn = document.getElementById('btn-request-lock');
  let pollTimeout = null;

  function stopTimers() {
    if (pollTimeout) clearTimeout(pollTimeout);
    pollTimeout = null;
  }

  async function requestLock() {
    let response;

    try {
      response = await fetch(requestUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
      });
    } catch (e) {
      console.error(e);
      pollTimeout = setTimeout(requestLock, 3000);
      return;
    }

    if (!response.ok) {
      console.error('lock/request → HTTP ' + response.status);
      pollTimeout = setTimeout(requestLock, 3000);
      return;
    }

    const data = await response.json();

    if (data.state === 'acquired') {
      stopTimers();
      location.href = showUrl;
      return;
    }

    btn.disabled = true;

    stopTimers();
    const waiting = document.getElementById('lock-request-waiting');
    if (waiting) waiting.hidden = false;
    pollTimeout = setTimeout(requestLock, 5000);
  }

  btn.addEventListener('click', () => {
    btn.disabled = true;
    requestLock();
  });
})();
