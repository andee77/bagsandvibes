<?php
/**
 * Checked Bags & Good Vibes — Member Dashboard shell
 *
 * Only visible to logged-in members; logged-out visitors get redirected to
 * the login page. Continues the "GATE" numbering from the landing page's 6
 * scrollytelling phases (GATE 01-06), picking up at GATE 07 for the member
 * area.
 *
 * Phase 5 of the trip-invite build: Trip Guests get a scoped view (only
 * their own trip(s), no Gates 07-12 grid, plus their own invite-link and
 * full-membership-request actions) instead of the generic Full Member
 * dashboard below. Full Members additionally get a one-time highlight
 * banner pointing at whichever trip drew them here (public trip-interest
 * code, or the trip they were originally invited to if later promoted from
 * Trip Guest).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	wp_redirect( 'https://bagsandvibes.com/login/' );
	exit;
}

$current_user  = wp_get_current_user();
$display_name  = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
$is_trip_guest = in_array( 'trip_guest', (array) $current_user->roles, true );

$gates = array(
	array(
		'number' => 'GATE 07',
		'title'  => 'All Planned Vacations',
		'desc'   => "See every trip your crew has on the books, past and upcoming.",
		'url'    => 'https://bagsandvibes.com/gate-07-pre-planned-vacations/',
	),
	array(
		'number' => 'GATE 08',
		'title'  => 'Photo Gallery',
		'desc'   => 'Shared photos from every trip, all in one place.',
		'url'    => 'https://bagsandvibes.com/gate-08-photo-gallery/',
	),
	array(
		'number' => 'GATE 09',
		'title'  => 'Payment Section',
		'desc'   => "Track deposits, balances, and who's paid what.",
		'url'    => 'https://bagsandvibes.com/gate-09-payments/',
	),
	array(
		'number' => 'GATE 10',
		'title'  => 'Discussion Boards',
		'desc'   => 'Talk logistics, split rooms, and settle itinerary debates.',
		'url'    => 'https://bagsandvibes.com/gate-10-discussion-boards/',
	),
	array(
		'number' => 'GATE 11',
		'title'  => 'Travel Rules',
		'desc'   => 'The house rules for group trips — read before you pack.',
		'url'    => 'https://bagsandvibes.com/gate-11-travel-rules/',
	),
	array(
		'number' => 'GATE 12',
		'title'  => 'Vacation Request',
		'desc'   => 'Pitch a new destination or start your own trip.',
		'url'    => 'https://bagsandvibes.com/gate-12-vacation-requests/',
	),
);

// Any roster member -- Trip Guest or Full Member -- gets a "Your Trips"
// list; it's just placed differently below (scoped view vs. alongside the
// Gate 07-12 grid).
$my_trips = array_filter(
	get_posts( array( 'post_type' => 'cb_trip', 'numberposts' => -1 ) ),
	function ( $t ) use ( $current_user ) {
		return in_array( $current_user->ID, cb_trip_get_roster( $t->ID ), true );
	}
);

$highlight_trip = null;
if ( ! $is_trip_guest && function_exists( 'cbv_get_dashboard_highlight_trip_id' ) ) {
	$highlight_trip_id = cbv_get_dashboard_highlight_trip_id( $current_user->ID );
	if ( $highlight_trip_id ) {
		$highlight_trip = get_post( $highlight_trip_id );
	}
}

// Travel Profile nudge -- account-level, so shown to Guests and Full
// Members alike, unlike the trip-highlight banner above.
$needs_profile_nudge = function_exists( 'cbv_user_profile_is_complete' )
	&& ! cbv_user_profile_is_complete( $current_user->ID )
	&& ! get_user_meta( $current_user->ID, '_profile_nudge_dismissed', true );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'checkedbags-dashboard' ); ?>>

<header class="site-header" id="site-header">
  <div class="header-inner">
    <?php
    $cb_logo_id  = get_theme_mod( 'custom_logo' );
    $cb_logo_url = $cb_logo_id ? wp_get_attachment_image_url( $cb_logo_id, 'medium' ) : '';
    ?>
    <a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="brand brand-logo-only" aria-label="Dashboard">
      <?php if ( $cb_logo_url ) : ?>
        <img src="<?php echo esc_url( $cb_logo_url ); ?>" alt="Checked Bags &amp; Good Vibes" class="brand-logo-img">
      <?php endif; ?>
    </a>
    <?php echo function_exists( 'cb_render_primary_nav' ) ? cb_render_primary_nav() : ''; ?>
  </div>
</header>

<main class="dashboard-main">

  <div class="dashboard-hero-row">
    <section class="dashboard-hero">
      <p class="dashboard-hero-pagelabel">Dashboard</p>
      <p class="dashboard-hero-eyebrow">Welcome back</p>
      <h1 class="dashboard-hero-name"><?php echo esc_html( $display_name ); ?></h1>
      <p class="dashboard-hero-sub">
        <?php echo $is_trip_guest
          ? 'Here&#8217;s the trip you&#8217;re part of.'
          : 'Your crew, your calendar, your next great escape.'; ?>
      </p>
    </section>

    <?php if ( function_exists( 'cbv_render_my_trips_sticky_note' ) ) : ?>
      <?php echo cbv_render_my_trips_sticky_note( $my_trips ); ?>
    <?php endif; ?>
  </div>

  <?php if ( $needs_profile_nudge ) : ?>
    <section class="dashboard-highlight-banner" id="cbv-profile-nudge">
      <p class="dashboard-highlight-text">
        Complete your Travel Profile so we have what we need before your next trip.
      </p>
      <a href="<?php echo esc_url( function_exists( 'UM' ) ? UM()->account()->tab_link( 'travel-profile' ) : home_url( '/account/' ) ); ?>" class="btn btn-ticket">Complete profile</a>
      <button type="button" class="btn btn-ghost" id="cbv-dismiss-profile-nudge-btn">Dismiss</button>
    </section>
    <script>
    (function () {
    	var restUrl = <?php echo wp_json_encode( esc_url_raw( rest_url( 'cb/v1/' ) ) ); ?>;
    	var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;

    	var dismissProfileNudgeBtn = document.getElementById( 'cbv-dismiss-profile-nudge-btn' );
    	if ( dismissProfileNudgeBtn ) {
    		dismissProfileNudgeBtn.addEventListener( 'click', function () {
    			var banner = document.getElementById( 'cbv-profile-nudge' );
    			fetch( restUrl + 'dismiss-profile-nudge', { method: 'POST', headers: { 'X-WP-Nonce': nonce } } )
    				.then( function () { if ( banner ) { banner.remove(); } } )
    				.catch( function () { if ( banner ) { banner.remove(); } } );
    		} );
    	}
    })();
    </script>
  <?php endif; ?>

  <?php if ( $is_trip_guest ) : ?>

    <section class="dashboard-my-trips">
      <?php if ( empty( $my_trips ) ) : ?>
        <p class="cb-empty">You don&#8217;t have any trips yet — check with whoever invited you.</p>
      <?php else : ?>
        <?php echo cbv_render_dashboard_trip_cards( $my_trips ); ?>
      <?php endif; ?>
    </section>

    <section class="dashboard-upgrade-cta">
      <p>Want full access to the site — discussion boards, photo galleries, and more?</p>
      <button type="button" class="btn btn-ticket" id="cbv-request-membership-btn">Request Full Membership</button>
      <div class="dashboard-invite-result" id="cbv-request-membership-result"></div>
    </section>

  <?php else : ?>

    <?php if ( $highlight_trip ) : ?>
      <section class="dashboard-highlight-banner" id="cbv-trip-highlight">
        <p class="dashboard-highlight-text">
          Welcome! Here&#8217;s the trip you were invited to: <strong><?php echo esc_html( get_the_title( $highlight_trip ) ); ?></strong>
        </p>
        <a href="<?php echo esc_url( get_permalink( $highlight_trip ) ); ?>" class="btn btn-ticket">View trip</a>
        <button type="button" class="btn btn-ghost" id="cbv-dismiss-highlight-btn">Dismiss</button>
      </section>
    <?php endif; ?>

    <?php if ( ! empty( $my_trips ) ) : ?>
      <section class="dashboard-my-trips">
        <p class="dashboard-section-title">Your Trips</p>
        <?php echo cbv_render_dashboard_trip_cards( $my_trips ); ?>
      </section>
    <?php endif; ?>

    <section class="gate-grid">
      <?php foreach ( $gates as $gate ) : ?>
      <a class="gate-card" href="<?php echo esc_url( $gate['url'] ); ?>">
        <span class="gate-card-number"><?php echo esc_html( $gate['number'] ); ?></span>
        <span class="gate-card-title"><?php echo esc_html( $gate['title'] ); ?></span>
        <span class="gate-card-desc"><?php echo esc_html( $gate['desc'] ); ?></span>
      </a>
      <?php endforeach; ?>
    </section>

  <?php endif; ?>

</main>

<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <p class="footer-brand-name">Checked Bags &amp; Good Vibes</p>
      <p class="footer-tagline">A JourneyWell Global LLC brand.</p>
    </div>

    <nav class="footer-links" aria-label="Footer">
      <a href="https://bagsandvibes.com/privacy-policy/">Privacy</a>
      <a href="https://bagsandvibes.com/terms-of-service/">Terms</a>
      <a href="https://bagsandvibes.com/contact/">Contact</a>
    </nav>

    <div class="footer-meta">
      <p>&copy; 2026 JourneyWell Global LLC. All rights reserved.</p>
      <p class="footer-stamp">BAGSANDVIBES.COM &middot; EST. 2026</p>
    </div>
  </div>
</footer>

<?php if ( $is_trip_guest || $highlight_trip || ! empty( $my_trips ) ) : ?>
<script>
(function () {
	var restUrl = <?php echo wp_json_encode( esc_url_raw( rest_url( 'cb/v1/' ) ) ); ?>;
	var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;

	// Shared by the invite-link and QR copy buttons below -- takes the
	// actual clipboard action as a callback since the two buttons copy
	// different things (link text vs. image data).
	function cbvMakeCopyBtn( label, doCopy ) {
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'btn btn-ghost dashboard-copy-btn';
		btn.textContent = label;
		btn.addEventListener( 'click', function () {
			doCopy().then( function () {
				var original = label;
				btn.textContent = 'Copied!';
				setTimeout( function () { btn.textContent = original; }, 1500 );
			} ).catch( function () {
				btn.textContent = 'Copy failed';
				setTimeout( function () { btn.textContent = label; }, 1500 );
			} );
		} );
		return btn;
	}

	document.querySelectorAll( '.cbv-invite-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var tripId   = btn.getAttribute( 'data-trip-id' );
			var resultEl = document.querySelector( '.dashboard-invite-result[data-trip-id="' + tripId + '"]' );
			btn.disabled = true;
			if ( resultEl ) { resultEl.textContent = 'Generating…'; }

			fetch( restUrl + 'trips/' + tripId + '/invite-link', { method: 'POST', headers: { 'X-WP-Nonce': nonce } } )
				.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
				.then( function ( res ) {
					btn.disabled = false;
					if ( ! resultEl ) { return; }
					resultEl.textContent = '';
					if ( ! res.ok ) {
						resultEl.textContent = 'Error: ' + res.body.message;
						return;
					}
					var urlRow = document.createElement( 'div' );
					urlRow.className = 'dashboard-invite-link-row';
					var urlEl = document.createElement( 'p' );
					urlEl.className = 'dashboard-invite-url';
					urlEl.textContent = res.body.url;
					urlRow.appendChild( urlEl );
					urlRow.appendChild( cbvMakeCopyBtn( 'Copy Link', function () {
						return navigator.clipboard.writeText( res.body.url );
					} ) );
					resultEl.appendChild( urlRow );
					// QR is generated server-side in the same response (no
					// third-party API -- this link identifies both the trip
					// and the inviting member) -- only render it if present.
					if ( res.body.qr_uri ) {
						var qrRow = document.createElement( 'div' );
						qrRow.className = 'dashboard-invite-qr-row';
						var qrImg = document.createElement( 'img' );
						qrImg.className = 'dashboard-invite-qr';
						qrImg.alt = 'QR code for this invite link';
						qrImg.src = res.body.qr_uri;
						qrRow.appendChild( qrImg );
						// Copies the QR image itself (not the URL again --
						// the link row above already covers that) so it can
						// be pasted straight into an email or chat as a
						// scannable image.
						qrRow.appendChild( cbvMakeCopyBtn( 'Copy QR Image', function () {
							return fetch( res.body.qr_uri )
								.then( function ( r ) { return r.blob(); } )
								.then( function ( blob ) {
									return navigator.clipboard.write( [ new ClipboardItem( { 'image/png': blob } ) ] );
								} );
						} ) );
						resultEl.appendChild( qrRow );
					}
				} )
				.catch( function () {
					btn.disabled = false;
					if ( resultEl ) { resultEl.textContent = 'Request failed — please try again.'; }
				} );
		} );
	} );

	var requestBtn = document.getElementById( 'cbv-request-membership-btn' );
	if ( requestBtn ) {
		requestBtn.addEventListener( 'click', function () {
			requestBtn.disabled = true;
			requestBtn.textContent = 'Sending…';

			fetch( restUrl + 'request-full-membership', { method: 'POST', headers: { 'X-WP-Nonce': nonce } } )
				.then( function ( r ) { return r.json(); } )
				.then( function () {
					requestBtn.textContent = 'Request sent!';
				} )
				.catch( function () {
					requestBtn.disabled = false;
					requestBtn.textContent = 'Request Full Membership';
					var resultEl = document.getElementById( 'cbv-request-membership-result' );
					if ( resultEl ) { resultEl.textContent = 'Something went wrong — please try again.'; }
				} );
		} );
	}

	var dismissBtn = document.getElementById( 'cbv-dismiss-highlight-btn' );
	if ( dismissBtn ) {
		dismissBtn.addEventListener( 'click', function () {
			var banner = document.getElementById( 'cbv-trip-highlight' );
			fetch( restUrl + 'dismiss-trip-highlight', { method: 'POST', headers: { 'X-WP-Nonce': nonce } } )
				.then( function () { if ( banner ) { banner.remove(); } } )
				.catch( function () { if ( banner ) { banner.remove(); } } );
		} );
	}

	// "My Trips" sticky note: collapse every trip card by default (progressive
	// enhancement -- if this script never runs, all cards stay visible, same
	// as before the sticky note existed), then expand + scroll to whichever
	// one a note link points at.
	var stickyLinks = document.querySelectorAll( '.sticky-note-trip-link' );
	if ( stickyLinks.length ) {
		document.querySelectorAll( '.dashboard-trip-card' ).forEach( function ( card ) {
			card.classList.add( 'is-collapsed' );
		} );

		stickyLinks.forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var card = document.getElementById( 'dashboard-trip-' + link.getAttribute( 'data-trip-id' ) );
				if ( ! card ) { return; }

				card.classList.remove( 'is-collapsed' );
				card.scrollIntoView( { behavior: 'smooth', block: 'center' } );

				stickyLinks.forEach( function ( l ) { l.classList.remove( 'is-active' ); } );
				link.classList.add( 'is-active' );
			} );
		} );
	}

	// Cover photo picker (Phase 6): toggle the panel, then either pick a
	// preset or upload a new photo -- both reload on success so the card's
	// thumbnail and button label ("Add" vs "Change") reflect the new state.
	document.querySelectorAll( '.cbv-cover-toggle-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var picker = document.getElementById( 'cbv-cover-picker-' + btn.getAttribute( 'data-trip-id' ) );
			if ( picker ) {
				picker.style.display = picker.style.display === 'none' ? '' : 'none';
			}
		} );
	} );

	function cbvSetCoverFromAttachment( tripId, attachmentId, resultEl ) {
		if ( resultEl ) { resultEl.textContent = 'Saving…'; }
		fetch( restUrl + 'trips/' + tripId + '/cover-photo', {
			method: 'POST',
			headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
			body: JSON.stringify( { attachment_id: attachmentId } )
		} )
			.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
			.then( function ( res ) {
				if ( res.ok && res.body.success ) {
					location.reload();
				} else if ( resultEl ) {
					resultEl.textContent = 'Error: ' + ( res.body.message || 'Something went wrong.' );
				}
			} )
			.catch( function () { if ( resultEl ) { resultEl.textContent = 'Request failed — please try again.'; } } );
	}

	document.querySelectorAll( '.dashboard-cover-preset-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var tripId   = btn.getAttribute( 'data-trip-id' );
			var resultEl = document.querySelector( '.dashboard-cover-result[data-trip-id="' + tripId + '"]' );
			cbvSetCoverFromAttachment( tripId, btn.getAttribute( 'data-attachment-id' ), resultEl );
		} );
	} );

	document.querySelectorAll( '.dashboard-cover-upload-input' ).forEach( function ( input ) {
		input.addEventListener( 'change', function () {
			if ( ! input.files || ! input.files[0] ) { return; }
			var tripId   = input.getAttribute( 'data-trip-id' );
			var resultEl = document.querySelector( '.dashboard-cover-result[data-trip-id="' + tripId + '"]' );
			var formData = new FormData();
			formData.append( 'photo', input.files[0] );
			if ( resultEl ) { resultEl.textContent = 'Uploading…'; }

			fetch( restUrl + 'trips/' + tripId + '/cover-photo/upload', {
				method: 'POST',
				headers: { 'X-WP-Nonce': nonce },
				body: formData
			} )
				.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
				.then( function ( res ) {
					if ( res.ok && res.body.success ) {
						location.reload();
					} else if ( resultEl ) {
						resultEl.textContent = 'Error: ' + ( res.body.message || 'Something went wrong.' );
					}
				} )
				.catch( function () { if ( resultEl ) { resultEl.textContent = 'Upload failed — please try again.'; } } );
		} );
	} );
})();
</script>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
