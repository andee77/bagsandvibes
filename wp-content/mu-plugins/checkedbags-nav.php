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
 * Final order: Dashboard, My Profile (dropdown), Feed, Navigation (dropdown,
 * Gates 07-12), Account (dropdown), Logout.
 *
 * Every link/condition here matches what template-gate.php's inline markup
 * had before this file existed -- this is a structural change (flat list ->
 * dropdowns), not a change to who sees which links.
 */
function cb_render_primary_nav() {

	$gate_nav = array(
		array( 'label' => 'Gate 07', 'title' => 'Pre-Planned Vacations', 'url' => 'https://bagsandvibes.com/gate-07-pre-planned-vacations/' ),
		array( 'label' => 'Gate 08', 'title' => 'Photo Gallery',          'url' => 'https://bagsandvibes.com/gate-08-photo-gallery/' ),
		array( 'label' => 'Gate 09', 'title' => 'Payments',                'url' => 'https://bagsandvibes.com/gate-09-payments/' ),
		array( 'label' => 'Gate 10', 'title' => 'Discussion Boards',       'url' => 'https://bagsandvibes.com/gate-10-discussion-boards/' ),
		array( 'label' => 'Gate 11', 'title' => 'Travel Rules',            'url' => 'https://bagsandvibes.com/gate-11-travel-rules/' ),
		array( 'label' => 'Gate 12', 'title' => 'Vacation Requests',       'url' => 'https://bagsandvibes.com/gate-12-vacation-requests/' ),
	);

	// "Following" and "Find Members" are built in the next phase of this
	// work -- these URLs are correct destinations already (Find Members
	// reuses the existing /members/ page/slug; Following is new), but until
	// that phase lands, Following 404s and Find Members still shows UM's
	// non-functional native directory. Expected, not a bug -- the nav is
	// deliberately going in first per this phase's own sequencing.
	$profile_nav = array(
		array( 'label' => 'My Profile',   'url' => ( function_exists( 'cb_member_profile_url' ) && is_user_logged_in() ) ? cb_member_profile_url( get_current_user_id() ) : '' ),
		array( 'label' => 'Following',    'url' => home_url( '/following/' ) ),
		array( 'label' => 'Find Members', 'url' => home_url( '/members/' ) ),
	);

	$account_nav = array(
		array( 'label' => 'Account',         'url' => home_url( '/account/general/' ) ),
		array( 'label' => 'Change Password', 'url' => home_url( '/account/password/' ) ),
		array( 'label' => 'Travel Profile',  'url' => home_url( '/account/travel-profile/' ) ),
		array( 'label' => 'Privacy',         'url' => home_url( '/account/privacy/' ) ),
		array( 'label' => 'Delete Account',  'url' => home_url( '/account/delete/' ) ),
	);

	ob_start();
	?>
	<button class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="primary-nav">
		<span class="nav-toggle-label">Menu</span>
		<span class="nav-toggle-bars" aria-hidden="true"></span>
	</button>

	<nav class="primary-nav" id="primary-nav" aria-label="Member navigation">
		<ul class="gate-nav-list">
			<li><a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>">Dashboard</a></li>

			<li class="nav-dropdown">
				<button type="button" class="nav-dropdown-toggle" aria-expanded="false">My Profile</button>
				<ul class="nav-dropdown-menu">
					<?php foreach ( $profile_nav as $item ) :
						if ( empty( $item['url'] ) ) {
							continue;
						}
						?>
						<li><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</li>

			<li><a href="https://bagsandvibes.com/member-feed/">Feed</a></li>

			<li class="nav-dropdown">
				<button type="button" class="nav-dropdown-toggle" aria-expanded="false">Navigation</button>
				<ul class="nav-dropdown-menu">
					<?php foreach ( $gate_nav as $g ) : ?>
						<li><a href="<?php echo esc_url( $g['url'] ); ?>" title="<?php echo esc_attr( $g['title'] ); ?>"><?php echo esc_html( $g['label'] ); ?></a></li>
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

			<li><a href="https://bagsandvibes.com/logout/">Logout</a></li>
		</ul>
	</nav>
	<?php
	return ob_get_clean();
}
