/* Blocked-screen behavior: requesting the Editing Lock and watching the
 * 30-second transfer countdown (issue #9, building on the request/transfer
 * mechanism from issue #1). */
(function () {
  const el = document.getElementById('lock-request');
  if (!el) return;

  const requestUrl = el.dataset.requestUrl;
  const showUrl = el.dataset.showUrl;
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

  const btn = document.getElementById('btn-request-lock');
  const countdownEl = document.getElementById('lock-countdown');

  let tickInterval = null;
  let pollTimeout = null;

  // Offset (ms) added to the client's own Date.now() to approximate the
  // server's clock, derived from the "requested_at" timestamp the server
  // just stamped on this request. The countdown is then computed against
  // that adjusted clock instead of the client's raw one, so a skewed local
  // clock doesn't throw off the displayed remaining time.
  let clockOffsetMs = 0;

  function stopTimers() {
    if (tickInterval) clearInterval(tickInterval);
    if (pollTimeout) clearTimeout(pollTimeout);
    tickInterval = null;
    pollTimeout = null;
  }

  function serverNow() {
    return Date.now() + clockOffsetMs;
  }

  function renderCountdown(expiresAtMs) {
    const remainingSeconds = Math.max(0, Math.ceil((expiresAtMs - serverNow()) / 1000));
    countdownEl.hidden = false;
    countdownEl.textContent = __('lock_transfers_in', remainingSeconds);
  }

  function __(key, seconds) {
    const templates = window.lockedMessages || {};
    const template = templates[key] || ':seconds';

    return template.replace(':seconds', seconds);
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

    const requestedAtMs = Date.parse(data.requested_at);
    const expiresAtMs = Date.parse(data.expires_at);

    if (!Number.isNaN(requestedAtMs)) {
      clockOffsetMs = requestedAtMs - Date.now();
    }

    btn.disabled = true;

    stopTimers();
    renderCountdown(expiresAtMs);
    tickInterval = setInterval(() => renderCountdown(expiresAtMs), 250);

    const delay = Math.max(0, expiresAtMs - serverNow()) + 300;
    pollTimeout = setTimeout(requestLock, delay);
  }

  btn.addEventListener('click', () => {
    btn.disabled = true;
    requestLock();
  });
})();
