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
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand">Checked Bags <span class="brand-amp">&amp;</span> Good Vibes</a>
    <nav class="member-nav" aria-label="Member navigation">
      <a href="https://bagsandvibes.com/member-feed/" class="btn btn-ghost">Feed</a>
      <a href="https://bagsandvibes.com/account/" class="btn btn-ghost">My Account</a>
      <a href="https://bagsandvibes.com/logout/" class="btn btn-ghost">Log Out</a>
    </nav>
  </div>
</header>

<main class="dashboard-main">

  <section class="dashboard-hero">
    <p class="dashboard-hero-eyebrow">Welcome back</p>
    <h1 class="dashboard-hero-name"><?php echo esc_html( $display_name ); ?></h1>
    <p class="dashboard-hero-sub">
      <?php echo $is_trip_guest
        ? 'Here&#8217;s the trip you&#8217;re part of.'
        : 'Your crew, your calendar, your next great escape.'; ?>
    </p>
  </section>

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
					if ( resultEl ) { resultEl.textContent = res.ok ? res.body.url : ( 'Error: ' + res.body.message ); }
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
})();
</script>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
