<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Find Members
 * Description: Custom member-discovery grid, replacing Ultimate Member's
 *              own native directory (form_id=48, the /members/ page) --
 *              confirmed non-functional for this site's real users: its
 *              query depends on a UM-internal serialized meta cache that
 *              our custom registration/invite flow never populates, even
 *              though the plain account_status meta everything else in
 *              this codebase reads is set correctly. Live-tested: 4 real
 *              approved users existed and the native directory still
 *              returned zero results. Direct get_users() instead, matching
 *              this codebase's established simple-query house style
 *              rather than depending on undocumented UM internals.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-find-members.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   Shortcode: [cb_find_members] -- swapped in for [ultimatemember form_id="48"]
   on the existing /members/ page (id 52), reusing that page/slug rather
   than creating a new one. Excludes the viewer's own account (nothing to
   discover about yourself). No role filtering or pagination -- both would
   be premature for the handful of real members this site has today; add
   them if/when real volume calls for it, not preemptively.

   Search param is deliberately named "member_q", not WordPress's own "s" --
   "s" is WP's reserved main-search query var, and a form posting ?s=... at
   any page's URL triggers WP's own search routing instead of just being a
   plain query string this shortcode can read via $_GET.
   ========================================================================== */
add_shortcode( 'cb_find_members', function () {

	if ( ! is_user_logged_in() ) {
		return '<p class="cb-empty">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to browse members.</p>';
	}

	$viewer_id = get_current_user_id();
	$search    = isset( $_GET['member_q'] ) ? sanitize_text_field( wp_unslash( $_GET['member_q'] ) ) : '';

	$args = array(
		'exclude' => array( $viewer_id ),
		'orderby' => 'display_name',
		'order'   => 'ASC',
	);
	if ( '' !== $search ) {
		$args['search']         = '*' . $search . '*';
		$args['search_columns'] = array( 'display_name', 'user_login', 'user_email' );
	}

	$members = get_users( $args );

	ob_start();
	?>
	<p class="cb-page-hint">Browse everyone on Checked Bags &amp; Good Vibes and follow the people you want to keep up with.</p>
	<form method="get" class="member-directory-search" action="<?php echo esc_url( get_permalink() ); ?>">
		<input type="search" name="member_q" placeholder="Search members…" value="<?php echo esc_attr( $search ); ?>">
		<button type="submit" class="btn btn-ghost">Search</button>
	</form>

	<?php if ( empty( $members ) ) : ?>
		<p class="cb-empty"><?php echo $search ? 'No members match that search.' : 'No other members yet.'; ?></p>
	<?php else : ?>
		<div class="member-directory-grid">
			<?php foreach ( $members as $member ) : ?>
				<div class="member-directory-card">
					<a href="<?php echo esc_url( function_exists( 'cb_member_profile_url' ) ? cb_member_profile_url( $member->ID ) : '' ); ?>" class="member-directory-card-link">
						<?php echo get_avatar( $member->ID, 80 ); ?>
						<span class="member-directory-card-name"><?php echo esc_html( $member->display_name ); ?></span>
					</a>
					<?php if ( function_exists( 'cb_render_follow_button' ) ) : ?>
						<?php echo cb_render_follow_button( $member->ID, $viewer_id ); ?>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php
	return ob_get_clean();
} );
