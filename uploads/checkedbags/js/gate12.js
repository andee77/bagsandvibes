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

    if (e.target.id === 'cb-suggestion-form') {
      e.preventDefault();
      var title = val('cb-suggestion-title');
      var desc = val('cb-suggestion-desc');
      if (!title.trim()) return;

      fetch(cbGate12.restUrl + 'suggestions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cbGate12.nonce },
        body: JSON.stringify({ title: title, description: desc })
      })
        .then(function (res) { return res.json(); })
        .then(function () { location.reload(); })
        .catch(function () { alert('Something went wrong. Please try again.'); });
      return;
    }

    if (e.target.id === 'cb-trip-request-form') {
      e.preventDefault();

      var payload = {
        organizer_name: val('req-organizer-name'),
        organizer_email: val('req-organizer-email'),
        organizer_phone: val('req-organizer-phone'),
        organizer_role: val('req-organizer-role'),
        decision_style: val('req-decision-style'),
        group_size: val('req-group-size'),
        adults: val('req-adults'),
        children: val('req-children-note'),
        seniors: val('req-seniors'),
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
    var voteBtn = e.target.closest('.suggestion-vote-btn');
    if (voteBtn) {
      var id = voteBtn.getAttribute('data-suggestion-id');
      voteBtn.disabled = true;
      fetch(cbGate12.restUrl + 'suggestions/' + id + '/vote', {
        method: 'POST',
        headers: { 'X-WP-Nonce': cbGate12.nonce }
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          voteBtn.disabled = false;
          voteBtn.classList.toggle('is-voted', data.voted);
          voteBtn.querySelector('.suggestion-vote-count').textContent = data.count;
        })
        .catch(function () { voteBtn.disabled = false; });
      return;
    }

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
