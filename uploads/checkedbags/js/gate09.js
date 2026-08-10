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

  // Trip card "View details" expand/collapse.
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('.cbv-payment-card-toggle');
    if (!toggle) return;

    var target = document.getElementById(toggle.getAttribute('data-target'));
    if (!target) return;

    var isHidden = target.hasAttribute('hidden');
    if (isHidden) {
      target.removeAttribute('hidden');
      toggle.classList.add('is-open');
    } else {
      target.setAttribute('hidden', '');
      toggle.classList.remove('is-open');
    }
  });

  // Shared "Schedule Appointment" modal -- one panel on the page, opened
  // and re-labeled per click (page-level button has trip-id="0", a card's
  // own button carries that trip's real ID).
  var modal        = document.getElementById('cbv-schedule-appt-modal');
  var modalTitle   = document.getElementById('cbv-schedule-appt-modal-title');
  var timeInput    = document.getElementById('cbv-schedule-appt-time');
  var notesInput   = document.getElementById('cbv-schedule-appt-notes');
  var submitBtn    = document.getElementById('cbv-schedule-appt-submit');
  var resultEl     = document.getElementById('cbv-schedule-appt-result');
  var closeBtn     = modal ? modal.querySelector('.cbv-schedule-appt-close') : null;
  var activeTripId = '0';

  function openScheduleModal(tripId, tripTitle) {
    if (!modal) return;
    activeTripId = tripId || '0';
    modalTitle.textContent = tripTitle ? 'Schedule an Appointment — ' + tripTitle : 'Schedule an Appointment';
    timeInput.value = '';
    notesInput.value = '';
    resultEl.textContent = '';
    submitBtn.disabled = false;
    submitBtn.textContent = 'Send Request';
    modal.removeAttribute('hidden');
    timeInput.focus();
  }

  function closeScheduleModal() {
    if (modal) { modal.setAttribute('hidden', ''); }
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.cbv-schedule-appt-btn');
    if (!btn) return;
    openScheduleModal(btn.getAttribute('data-trip-id'), btn.getAttribute('data-trip-title'));
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', closeScheduleModal);
  }
  if (modal) {
    modal.addEventListener('click', function (e) {
      if (e.target === modal) { closeScheduleModal(); } // click on the overlay itself, not the inner panel
    });
  }

  if (submitBtn) {
    submitBtn.addEventListener('click', function () {
      if (!timeInput.value.trim()) {
        resultEl.textContent = 'Please enter a preferred time.';
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = 'Sending…';
      resultEl.textContent = '';

      var body = { preferred_time: timeInput.value.trim(), notes: notesInput.value.trim() };
      if (activeTripId && activeTripId !== '0') { body.trip_id = activeTripId; }

      fetch(cbGate09.restUrl + 'appointment-request', {
        method: 'POST',
        headers: { 'X-WP-Nonce': cbGate09.nonce, 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (!result.ok || !result.data.requested) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Request';
            resultEl.textContent = result.data.message || 'Could not send the request — please try again.';
            return;
          }
          submitBtn.textContent = 'Sent!';
          resultEl.textContent = 'Thanks — we\'ll be in touch to arrange your Travel Payment.';
          setTimeout(closeScheduleModal, 1600);
        })
        .catch(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Send Request';
          resultEl.textContent = 'Something went wrong — please try again.';
        });
    });
  }
})();
