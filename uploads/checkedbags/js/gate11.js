(function () {
  document.addEventListener('change', function (e) {
    if (e.target.id === 'cb-agree-checkbox') {
      var submitBtn = document.getElementById('cb-agree-submit');
      if (submitBtn) submitBtn.disabled = !e.target.checked;
    }
  });

  document.addEventListener('click', function (e) {
    if (e.target.id !== 'cb-agree-submit') return;

    var btn = e.target;
    btn.disabled = true;
    btn.textContent = 'Saving...';

    fetch(cbGate11.restUrl + 'agree-to-rules', {
      method: 'POST',
      headers: { 'X-WP-Nonce': cbGate11.nonce }
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.agreed) {
          location.reload();
        } else {
          btn.disabled = false;
          btn.textContent = 'Confirm agreement';
        }
      })
      .catch(function () {
        btn.disabled = false;
        btn.textContent = 'Confirm agreement';
        alert('Something went wrong. Please try again.');
      });
  });
})();
