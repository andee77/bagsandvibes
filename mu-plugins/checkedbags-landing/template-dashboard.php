<?php
/**
 * Checked Bags & Good Vibes — Member Dashboard shell
 *
 * Only visible to logged-in members; logged-out visitors get redirected to
 * the login page. This is a shell for now — each gate card links to "#"
 * until that module actually gets built. Continues the "GATE" numbering
 * from the landing page's 6 scrollytelling phases (GATE 01-06), picking up
 * at GATE 07 for the member area.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	wp_redirect( 'https://bagsandvibes.com/login/' );
	exit;
}

$current_user = wp_get_current_user();
$display_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;

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
    <p class="dashboard-hero-sub">Your crew, your calendar, your next great escape.</p>
  </section>

  <section class="gate-grid">
    <?php foreach ( $gates as $gate ) : ?>
    <a class="gate-card" href="<?php echo esc_url( $gate['url'] ); ?>">
      <span class="gate-card-number"><?php echo esc_html( $gate['number'] ); ?></span>
      <span class="gate-card-title"><?php echo esc_html( $gate['title'] ); ?></span>
      <span class="gate-card-desc"><?php echo esc_html( $gate['desc'] ); ?></span>
    </a>
    <?php endforeach; ?>
  </section>

  <?php
  // TEMPORARY — Phase 2 invite-link testing aid. Remove once the invite
  // flow has been verified end-to-end; this is not part of the Phase 5
  // dashboard build, just a quick way to exercise the REST endpoint with a
  // real, correctly-localized nonce instead of hand-rolled devtools fetches.
  $cbv_test_trips = array_filter(
    get_posts( array( 'post_type' => 'cb_trip', 'numberposts' => -1 ) ),
    function ( $t ) use ( $current_user ) {
      return in_array( $current_user->ID, cb_trip_get_roster( $t->ID ), true );
    }
  );
  ?>
  <?php if ( ! empty( $cbv_test_trips ) ) : ?>
  <section class="dashboard-invite-test" style="margin:2rem auto;max-width:640px;padding:1.5rem;border:2px dashed #999;">
    <p style="font-weight:bold;margin-top:0;">TEMPORARY — Phase 2 invite-link testing (remove after verified)</p>
    <?php foreach ( $cbv_test_trips as $t ) : ?>
      <div style="margin-bottom:1rem;">
        <strong><?php echo esc_html( get_the_title( $t ) ); ?></strong>
        (ID <?php echo (int) $t->ID; ?>)
        <button type="button" class="btn btn-ghost cbv-test-invite-btn" data-trip-id="<?php echo (int) $t->ID; ?>">Generate Invite Link</button>
        <div class="cbv-test-invite-result" style="margin-top:.5rem;font-family:monospace;font-size:.85rem;word-break:break-all;"></div>
      </div>
    <?php endforeach; ?>
  </section>
  <script>
  (function () {
    var restUrl = <?php echo wp_json_encode( esc_url_raw( rest_url( 'cb/v1/' ) ) ); ?>;
    var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;

    document.querySelectorAll( '.cbv-test-invite-btn' ).forEach( function ( btn ) {
      btn.addEventListener( 'click', function () {
        var tripId   = btn.getAttribute( 'data-trip-id' );
        var resultEl = btn.parentElement.querySelector( '.cbv-test-invite-result' );
        resultEl.textContent = 'Generating…';

        fetch( restUrl + 'trips/' + tripId + '/invite-link', {
          method: 'POST',
          headers: { 'X-WP-Nonce': nonce }
        } )
          .then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
          .then( function ( res ) {
            resultEl.textContent = res.ok ? res.body.url : ( 'Error: ' + res.body.message );
          } )
          .catch( function ( err ) { resultEl.textContent = 'Request failed: ' + err; } );
      } );
    } );
  })();
  </script>
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

<?php wp_footer(); ?>
</body>
</html>
