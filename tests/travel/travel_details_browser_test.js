/*
 * Travel Details form -- browser test (2026-10-13). Injected into a rendered trip page (the member's Travel Details
 * form) and run in headless Edge/Chrome by run_travel_details_browser_test.sh. It never sends anything: fetch() is
 * replaced by a stub that records the request. Results go into <pre id="cbv-test-result"> as JSON.
 */
(function () {
	var out = { checks: [], fatal: null };
	function check( label, ok, detail ) { out.checks.push( { label: label, ok: !! ok, detail: detail || '' } ); }
	function $( id ) { return document.getElementById( id ); }
	var sent = [];
	window.fetch = function ( url, opts ) {
		sent.push( { url: String( url ), body: JSON.parse( ( opts && opts.body ) || '{}' ) } );
		return Promise.resolve( { json: function () { return Promise.resolve( { saved: true } ); } } );
	};
	function toggle( section, on ) {
		var t = document.querySelector( '.cbv-intake-optional-category-toggle[data-target="' + section + '"]' );
		if ( ! t ) { return false; }
		if ( t.checked !== on ) { t.checked = on; t.dispatchEvent( new Event( 'change', { bubbles: true } ) ); }
		return true;
	}
	function allOff() {
		[ 'cbv-intake-section-air', 'cbv-intake-section-cruise', 'cbv-intake-section-hotel', 'cbv-intake-section-car-rental' ].forEach( function ( s ) { toggle( s, false ); } );
	}
	function result() { var r = $( 'cbv-intake-result' ); return r ? r.textContent : ''; }
	function save() {
		var before = sent.length;
		$( 'cbv-intake-result' ).textContent = '';
		var btn = $( 'cbv-intake-save-btn' ); btn.disabled = false;
		btn.click();
		return sent.length > before ? sent[ sent.length - 1 ].body : null;
	}
	function fillAlwaysRequired() {
		var ins = $( 'cbv-intake-insurance-decision' ); if ( ins ) { ins.value = 'accepted'; ins.dispatchEvent( new Event( 'change' ) ); }
		var cc = $( 'cbv-intake-cc-auth-completed' ); if ( cc ) { cc.checked = true; }
	}

	try {
		check( 'the Travel Details form is on the page', !! $( 'cbv-traveler-intake' ) && !! $( 'cbv-intake-save-btn' ) );
		check( 'all four optional sections are offered as checkboxes', [ 'air', 'cruise', 'hotel', 'car-rental' ].every( function ( s ) { return !! document.querySelector( '.cbv-intake-optional-category-toggle[data-target="cbv-intake-section-' + s + '"]' ); } ) );

		// 1. Every optional section OFF: saves (the reported bug: "Please complete Car Category").
		allOff(); fillAlwaysRequired();
		var body = save();
		check( 'every optional section off: saves (request sent), no "Please complete" message', !! body && result().indexOf( 'Please complete' ) === -1, result() );
		check( 'every optional section off: no car, hotel, cruise or flight category is asked for', result().indexOf( 'Car category' ) === -1 && result().indexOf( 'Room' ) === -1 );
		check( 'every optional section off: the saved choices list no optional sections', !! body && Array.isArray( body.optional_categories ) && body.optional_categories.length === 0, body && JSON.stringify( body.optional_categories ) );

		// 2. The always-required items are still required when everything optional is off.
		var cc = $( 'cbv-intake-cc-auth-completed' ); cc.checked = false;
		body = save();
		check( 'still required with sections off: Credit Card Authorization confirmation', ! body && result().indexOf( 'Credit Card Authorization confirmation' ) !== -1, result() );
		fillAlwaysRequired();

		// 3. A section turned ON is required again: Car Rental, all three of its fields including the category group.
		toggle( 'cbv-intake-section-car-rental', true );
		body = save();
		check( 'Car Rental on and empty: not saved; asks for car preferences, add-ons and car category', ! body && [ 'Car preferences', 'Add-ons', 'Car category' ].every( function ( w ) { return result().indexOf( w ) !== -1; } ), result() );
		// ... and turned OFF again: saves, even with its fields still empty.
		toggle( 'cbv-intake-section-car-rental', false );
		body = save();
		check( 'Car Rental turned back off: saves although its fields are empty', !! body && result().indexOf( 'Please complete' ) === -1, result() );

		// 4. Hotel: same rules for its "Room" checkbox group.
		toggle( 'cbv-intake-section-hotel', true );
		body = save();
		check( 'Hotel on and empty: asks for the Room choice and the hotel fields', ! body && result().indexOf( 'Room' ) !== -1 && result().indexOf( '# of nights' ) !== -1, result() );
		toggle( 'cbv-intake-section-hotel', false );
		check( 'Hotel turned back off: saves', !! save() && result().indexOf( 'Please complete' ) === -1, result() );

		// 5. Cruise: on and empty is required, off saves.
		toggle( 'cbv-intake-section-cruise', true );
		check( 'Cruise on and empty: asks for cruise company', ! save() && result().indexOf( 'Cruise company' ) !== -1, result() );
		toggle( 'cbv-intake-section-cruise', false );
		check( 'Cruise turned back off: saves', !! save() );

		// 6. Air: loyalty number is optional; TSA PreCheck is an optional checkbox that is saved.
		toggle( 'cbv-intake-section-air', true );
		check( 'Air on: the loyalty number is not marked required', ! $( 'cbv-intake-frequent-flyer-number' ).required && $( 'cbv-intake-frequent-flyer-number' ).closest( 'label' ).querySelector( '.cbv-required' ) === null );
		check( 'Air on: "I have TSA PreCheck" checkbox shown, optional, unticked by default', !! $( 'cbv-intake-tsa-precheck' ) && $( 'cbv-intake-tsa-precheck' ).type === 'checkbox' && ! $( 'cbv-intake-tsa-precheck' ).required && ! $( 'cbv-intake-tsa-precheck' ).checked );
		check( 'no Known Traveler Number field anywhere', ! /known traveler/i.test( $( 'cbv-traveler-intake' ).textContent ) );
		var sp = $( 'cbv-intake-seat-preference' ); sp.value = sp.options[ 1 ].value;
		$( 'cbv-intake-departure-airport' ).value = 'MIA';
		var fc = $( 'cbv-intake-flight-cabin-class' ); fc.value = fc.options[ 1 ].value;
		var al = $( 'cbv-intake-preferred-airline' ); al.value = al.options[ 1 ].value === 'Other' ? al.options[ 2 ].value : al.options[ 1 ].value; al.dispatchEvent( new Event( 'change' ) );
		$( 'cbv-intake-frequent-flyer-number' ).value = '';
		body = save();
		check( 'Air on with the loyalty number left empty: saves', !! body && result().indexOf( 'Please complete' ) === -1, result() );
		check( 'PreCheck unticked: saved as false', !! body && body.tsa_precheck === false );
		$( 'cbv-intake-tsa-precheck' ).checked = true;
		body = save();
		check( 'PreCheck ticked: saved as true', !! body && body.tsa_precheck === true );
		$( 'cbv-intake-departure-airport' ).value = '';
		check( 'Air on: other air fields are still required (departure airport)', ! save() && result().indexOf( 'Departure airport' ) !== -1, result() );
	} catch ( e ) {
		out.fatal = String( e && e.stack || e );
	}
	var pre = document.createElement( 'pre' ); pre.id = 'cbv-test-result'; pre.textContent = JSON.stringify( out );
	document.body.appendChild( pre );
})();
