(function () {

  function val(id) {
    var el = document.getElementById(id);
    return el ? el.value : '';
  }

  function checkedValues(name) {
    var boxes = document.querySelectorAll('input[name="' + name + '"]:checked');
    return Array.prototype.map.call(boxes, function (b) { return b.value; });
  }

  document.addEventListener('submit', function (e) {

    if (e.target.id === 'cb-trip-request-form') {
      e.preventDefault();

      var payload = {
        organizer_name: val('req-organizer-name'),
        organizer_email: val('req-organizer-email'),
        organizer_phone: val('req-organizer-phone'),
        organizer_role: val('req-organizer-role'),
        decision_style: val('req-decision-style'),
        group_size: val('req-group-size'),
        ages_0_17: val('req-ages-0-17'),
        ages_18_64: val('req-ages-18-64'),
        ages_65_plus: val('req-ages-65-plus'),
        group_dynamic: val('req-group-dynamic'),
        rooming: val('req-rooming'),
        destination_pref: val('req-destination'),
        date_flexibility: val('req-date-flexibility'),
        when: val('req-when'),
        duration: val('req-duration'),
        trip_category: checkedValues('trip_category'),
        transport_modes: checkedValues('transport_modes'),
        origin_city: val('req-origin-city'),
        special_transit: val('req-special-transit'),
        budget_tier: val('req-budget-tier'),
        payment_logistics: val('req-payment-logistics'),
        accommodation_type: val('req-accommodation-type'),
        pace: val('req-pace'),
        occasion: val('req-occasion'),
        must_haves: val('req-must-haves'),
        dietary: val('req-dietary'),
        mobility: val('req-mobility'),
        special_requests: val('req-special-requests'),
        type: (checkedValues('transport_modes')[0] || 'Other')
      };

      if (!payload.destination_pref.trim()) return;

      var submitBtn = e.target.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Submitting...';

      fetch(cbGate12.restUrl + 'trip-requests', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cbGate12.nonce },
        body: JSON.stringify(payload)
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (result.ok && result.data.trip_id) {
            alert('Thanks! Your request has been submitted. We will review it and follow up with a quote soon.');
            location.reload();
          } else {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit request';
            alert(result.data.message || 'Could not submit your request.');
          }
        })
        .catch(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Submit request';
          alert('Something went wrong. Please try again.');
        });
    }
  });

  document.addEventListener('click', function (e) {
    var acceptBtn = e.target.closest('.cb-accept-quote-btn');
    if (acceptBtn) {
      if (!confirm('Accept this quote? You will be able to pay your deposit on the Payments page next.')) return;
      var tripId = acceptBtn.getAttribute('data-trip-id');
      acceptBtn.disabled = true;
      fetch(cbGate12.restUrl + 'trips/' + tripId + '/accept-quote', {
        method: 'POST',
        headers: { 'X-WP-Nonce': cbGate12.nonce }
      })
        .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
        .then(function (result) {
          if (result.ok && result.data.accepted) {
            location.reload();
          } else {
            acceptBtn.disabled = false;
            alert(result.data.message || 'Could not accept the quote.');
          }
        })
        .catch(function () {
          acceptBtn.disabled = false;
          alert('Something went wrong. Please try again.');
        });
    }
  });

})();
