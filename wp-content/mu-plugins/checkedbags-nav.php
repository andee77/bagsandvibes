<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Shared Primary Nav
 * Description: Single source of truth for the member-facing header nav
 *              (hamburger toggle + link list), used by both
 *              template-gate.php and template-dashboard.php. Previously
 *              each template had its own independent copy of this markup --
 *              a departure from that pattern, made deliberately here since
 *              this is the third round of nav changes needing to land
 *              identically in both places (My Profile link, logo/dashboard
 *              link, and now this dropdown restructure). One function
 *              means one place to fix next time, instead of two kept in
 *              sync by hand.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-nav.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the hamburger toggle button + <nav class="primary-nav"> markup.
 * Order (owner, 2026-10-15): Feed, My Passport, Navigation (dropdown, Gates
 * 07-12), Account (dropdown: My Account, Traveler info, Log out).
 *
 * Every link/condition here matches what template-gate.php's inline markup
 * had before this file existed -- this is a structural change (flat list ->
 * dropdowns), not a change to who sees which links.
 */
/** A small passport-book icon for the My Passport menu item (inherits the menu's colour). */
function cb_nav_passport_icon() {
	return '<svg class="nav-passport-icon" width="14" height="16" viewBox="0 0 14 16" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true" focusable="false"><rect x="1" y="1" width="12" height="14" rx="1.5"/><circle cx="7" cy="7" r="2.6"/><path d="M4.4 7h5.2M7 4.4c-1 1.4-1 3.8 0 5.2M7 4.4c1 1.4 1 3.8 0 5.2M4.5 12.2h5"/></svg>';
}

function cb_render_primary_nav() {

	// A logged-out visitor on a Gate / Login / Register page used to get the
	// full member menu (Dashboard, My Profile, Account, Logout ...), every
	// item of which just bounced them to a sign-in prompt.
	if ( ! is_user_logged_in() ) {
		return cb_render_logged_out_nav();
	}

	// "label" is now the primary visible text; "gate" only shows as a hover
	// badge (see .nav-gate-badge in styles.css). Vacation Requests is GATE 11
	// and Travel Rules is GATE 12 (originally the other way round); the page
	// slugs now match those numbers (gate-11-vacation-requests,
	// gate-12-travel-rules), kept consistent with the Dashboard gate cards and
	// the Gate page ribbon (template-dashboard.php, template-gate.php). The
	// old slugs 301 to the new ones (checkedbags-redirects.php).
	$gate_nav = array(
		array( 'label' => 'Planned Vacations',  'gate' => 'GATE 07', 'url' => 'https://bagsandvibes.com/gate-07-pre-planned-vacations/' ),
		array( 'label' => 'Photo Gallery',      'gate' => 'GATE 08', 'url' => 'https://bagsandvibes.com/gate-08-photo-gallery/' ),
		array( 'label' => 'Payments',           'gate' => 'GATE 09', 'url' => 'https://bagsandvibes.com/gate-09-payments/' ),
		array( 'label' => 'Discussion Boards',  'gate' => 'GATE 10', 'url' => 'https://bagsandvibes.com/gate-10-discussion-boards/' ),
		array( 'label' => 'Vacation Requests',  'gate' => 'GATE 11', 'url' => 'https://bagsandvibes.com/gate-11-vacation-requests/' ),
		array( 'label' => 'Travel Rules',       'gate' => 'GATE 12', 'url' => 'https://bagsandvibes.com/gate-12-travel-rules/' ),
	);

	// Account menu (owner, 2026-10-15): My Account, Traveler info, Log out. Change Password, Privacy and Delete
	// Account stay as tabs inside My Account. The "My Profile" menu (My Profile, Following, Find Members) is gone.
	$account_nav = array(
		array( 'label' => 'My Account',     'url' => home_url( '/account/general/' ) ),
		array( 'label' => 'Traveler info',  'url' => home_url( '/account/travel-profile/' ) ),
		array( 'label' => 'Log out',        'url' => home_url( '/logout/' ) ),
	);

	ob_start();
	?>
	<button class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="primary-nav">
		<span class="nav-toggle-label">Menu</span>
		<span class="nav-toggle-bars" aria-hidden="true"></span>
	</button>

	<nav class="primary-nav" id="primary-nav" aria-label="Member navigation">
		<ul class="gate-nav-list">
			<?php // Menu order (owner, 2026-10-15): Feed | My Passport | Navigation | Account. ?>
			<li><a href="<?php echo esc_url( home_url( '/member-feed/' ) ); ?>">Feed</a></li>
			<li><a class="nav-passport" href="<?php echo esc_url( home_url( '/my-passport/' ) ); ?>"><?php echo cb_nav_passport_icon(); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed SVG ?>My Passport</a></li>

			<li class="nav-dropdown">
				<button type="button" class="nav-dropdown-toggle" aria-expanded="false">Navigation</button>
				<ul class="nav-dropdown-menu">
					<?php foreach ( $gate_nav as $g ) : ?>
						<li class="nav-gate-item">
							<a href="<?php echo esc_url( $g['url'] ); ?>"><?php echo esc_html( $g['label'] ); ?></a>
							<span class="nav-gate-badge" aria-hidden="true"><?php echo esc_html( $g['gate'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</li>

			<li class="nav-dropdown">
				<button type="button" class="nav-dropdown-toggle" aria-expanded="false">Account</button>
				<ul class="nav-dropdown-menu">
					<?php foreach ( $account_nav as $item ) : ?>
						<li><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</li>

		</ul>
	</nav>
	<?php
	return ob_get_clean();
}

/**
 * The site's own sign-in page (Ultimate Member's /login/, the page the menu
 * and /dashboard/ use), with an optional page to return to after signing in.
 * Every "Please sign in" link and the forum redirect use this, so nobody is
 * sent to the plain wp-login.php screen (owner, 2026-10-11). Falls back to
 * wp_login_url() if Ultimate Member is ever switched off.
 */
function cbv_login_url( $redirect = '' ) {
	$base = function_exists( 'um_get_core_page' ) ? (string) um_get_core_page( 'login' ) : '';
	if ( '' === $base ) {
		return wp_login_url( $redirect );
	}
	return '' !== (string) $redirect ? add_query_arg( 'redirect_to', rawurlencode( (string) $redirect ), $base ) : $base;
}

/**
 * The menu for someone who isn't signed in: same toggle + <nav> markup and
 * classes as the member menu (so the same CSS and app.js drive it), but only
 * the three places that make sense without an account.
 */
function cb_render_logged_out_nav() {
	$login_url    = cbv_login_url();
	$register_url = function_exists( 'um_get_core_page' ) ? um_get_core_page( 'register' ) : home_url( '/register/' );

	ob_start();
	?>
	<button class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="primary-nav">
		<span class="nav-toggle-label">Menu</span>
		<span class="nav-toggle-bars" aria-hidden="true"></span>
	</button>

	<nav class="primary-nav" id="primary-nav" aria-label="Site navigation">
		<ul class="gate-nav-list">
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a></li>
			<li><a href="<?php echo esc_url( $login_url ); ?>">Sign in</a></li>
			<li><a href="<?php echo esc_url( $register_url ); ?>">Register</a></li>
		</ul>
	</nav>
	<?php
	return ob_get_clean();
}
