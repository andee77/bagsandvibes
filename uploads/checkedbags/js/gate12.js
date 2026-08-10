(function () {

  function val(id) {
    var el = document.getElementById(id);
    return el ? el.value : '';
  }

  function checkedValues(name) {
    var boxes = document.querySelectorAll('input[name="' + name + '"]:checked');
    return Array.prototype.map.call(boxes, function (b) { return b.value; });
  }

  // Airline "Other" reveal -- shared markup from cbv_render_airline_field()
  // (also used by Per-Traveler Intake), so this same block covers any future
  // airline field added to this form.
  function airlineValue(selectId) {
    var select = document.getElementById(selectId);
    if (!select) { return ''; }
    if (select.value === 'Other') {
      var other = document.getElementById(selectId + '-other');
      return other ? other.value : '';
    }
    return select.value;
  }
  document.querySelectorAll('.cbv-airline-select').forEach(function (select) {
    var other = document.getElementById(select.id + '-other');
    if (!other) { return; }
    select.addEventListener('change', function () {
      other.style.display = (select.value === 'Other') ? '' : 'none';
    });
  });

  document.addEventListener('submit', function (e) {

    if (e.target.id === 'cb-trip-request-form') {
      e.preventDefault();

      var payload = {
        organizer_name: val('req-organizer-name'),
        organizer_email: val('req-organizer-email'),
        organizer_phone: val('req-organizer-phone'),
        organizer_role: val('req-organizer-role'),
        decision_style: val('req-decision-style'),
        client_address: val('req-client-address'),
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
        type: (checkedValues('transport_modes')[0] || 'Other'),

        // Air Travel
        airline_preference: airlineValue('req-airline-preference'),
        seat_preference: checkedValues('seat_preference'),

        // Cruise Vacation
        cruise_company: val('req-cruise-company'),
        cruise_program_number: val('req-cruise-program-number'),
        cruise_start_date: val('req-cruise-start-date'),
        cruise_end_date: val('req-cruise-end-date'),
        cruise_duration: val('req-cruise-duration'),
        cruise_region: val('req-cruise-region'),
        cruise_departure_port: val('req-cruise-departure-port'),
        pre_post_cruise_nights: val('req-pre-post-cruise-nights'),
        cruise_cabin_class: val('req-cruise-cabin-class'),
        beverage_plan: val('req-beverage-plan'),
        beverage_plan_type: val('req-beverage-plan-type'),

        // Hotel and Resort Vacation
        hotel_nights: val('req-hotel-nights'),
        hotel_preferences: val('req-hotel-preferences'),
        hotel_rooms_arrangement: val('req-hotel-rooms-arrangement'),
        hotel_room_type: checkedValues('hotel_room_type'),
        hotel_features: checkedValues('hotel_features'),
        hotel_concierge_notes: val('req-hotel-concierge-notes'),

        // Car Rental
        car_preferences: val('req-car-preferences'),
        car_addons: val('req-car-addons'),
        car_category: checkedValues('car_category'),

        // Package Tour
        package_countries: val('req-package-countries'),
        package_style: checkedValues('package_style'),
        package_activity_level: val('req-package-activity-level')
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

  // Show only the Air/Cruise/Hotel/Car/Package Tour section(s) that match
  // whichever "Trip Elements" boxes are currently checked -- a request can
  // involve more than one (e.g. a flight to a cruise port), so this is
  // additive, not a single fixed "type" toggle. Hotel and Resort are two
  // separate checkboxes sharing one section id -- computed per unique
  // section below (any element mapped to it being checked shows it), not
  // per checkbox key, since a naive per-key loop would have the
  // later-processed key's checked/unchecked state silently overwrite the
  // earlier one's for that same shared section.
  var SECTION_BY_ELEMENT = {
    'Flight': 'req-section-air',
    'Cruise': 'req-section-cruise',
    'Hotel': 'req-section-hotel',
    'Resort': 'req-section-hotel',
    'Car Rental': 'req-section-car',
    'Package Tour': 'req-section-package'
  };

  function updateConditionalSections() {
    var checked = checkedValues('transport_modes');
    var visibleSectionIds = {};
    checked.forEach(function (element) {
      var sectionId = SECTION_BY_ELEMENT[element];
      if (sectionId) { visibleSectionIds[sectionId] = true; }
    });

    var allSectionIds = Object.keys(SECTION_BY_ELEMENT).map(function (element) { return SECTION_BY_ELEMENT[element]; });
    var uniqueSectionIds = allSectionIds.filter(function (id, index) { return allSectionIds.indexOf(id) === index; });

    uniqueSectionIds.forEach(function (sectionId) {
      var section = document.getElementById(sectionId);
      if (!section) { return; }
      section.style.display = visibleSectionIds[sectionId] ? '' : 'none';
    });
  }

  var tripElementBoxes = document.querySelectorAll('input[name="transport_modes"]');
  if (tripElementBoxes.length) {
    tripElementBoxes.forEach(function (box) {
      box.addEventListener('change', updateConditionalSections);
    });
    updateConditionalSections();
  }

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
