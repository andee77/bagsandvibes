(function () {
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.cb-pay-btn');
    if (!btn) return;

    var tripId = btn.getAttribute('data-trip-id');
    btn.disabled = true;
    var originalText = btn.textContent;
    btn.textContent = 'Redirecting to Stripe...';

    fetch(cbGate09.restUrl + 'trips/' + tripId + '/checkout', {
      method: 'POST',
      headers: { 'X-WP-Nonce': cbGate09.nonce }
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, data: data };
        });
      })
      .then(function (result) {
        if (!result.ok || !result.data.checkout_url) {
          btn.disabled = false;
          btn.textContent = originalText;
          alert(result.data.message || 'Could not start checkout. Please try again.');
          return;
        }
        window.location.href = result.data.checkout_url;
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = originalText;
        alert('Something went wrong. Please try again.');
      });
  });
})();
