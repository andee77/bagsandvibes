(function () {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.cb-join-btn');
    if (!btn) return;

    if (!window.cbGate07 || !cbGate07.loggedIn) {
      window.location.href = (window.cbGate07 && cbGate07.loginUrl) || '/login/';
      return;
    }

    var tripId = btn.getAttribute('data-trip-id');
    btn.disabled = true;
    btn.textContent = 'Joining...';

    fetch(cbGate07.restUrl + 'trips/' + tripId + '/join', {
      method: 'POST',
      headers: { 'X-WP-Nonce': cbGate07.nonce }
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, data: data };
        });
      })
      .then(function (result) {
        if (!result.ok) {
          btn.disabled = false;
          btn.textContent = "I'm in";
          alert(result.data.message || 'Could not join this trip. It may be full.');
          return;
        }
        btn.textContent = "You're in";
        btn.classList.add('is-joined');
        var card = btn.closest('.trip-card');
        var spotsEl = card ? card.querySelector('.trip-card-spots') : null;
        if (spotsEl && result.data.spots_remaining !== null) {
          spotsEl.textContent = result.data.spots_remaining + ' spot' + (result.data.spots_remaining === 1 ? '' : 's') + ' left';
        }
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = "I'm in";
        alert('Something went wrong. Please try again.');
      });
  });
})();
