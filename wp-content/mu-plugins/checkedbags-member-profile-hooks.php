<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Member Profile (Ultimate Member hooks)
 * Description: Consolidates our custom profile functionality onto Ultimate
 *              Member's native /user/{user_nicename}/ URL, retiring the
 *              separate /members/{user_nicename}/ page that used to carry
 *              it (see mu-plugins/checkedbags-landing.php for the 301
 *              redirect from the old URL). Confirmed by reading UM 2.12.1's
 *              own source rather than assuming: profile.php is a thin shell
 *              of do_action() calls, so this hooks UM's documented API
 *              instead of overriding the template file -- less invasive,
 *              survives plugin updates.
 *
 *              Cover Photo and Bio are NOT custom-built here -- both are
 *              native UM features (image/textarea field types, confirmed
 *              via UM()->builtin()->get_specific_fields()), enabled via the
 *              Default Profile form's settings (_um_has_cover_photo,
 *              _um_profile_show_bio, set once via wp-cli, not code) and
 *              added to the Account page's General tab below so members can
 *              actually edit them.
 *
 *              Profile access is split into a public part and a gated part
 *              (Follow/Unfollow feature): the header (avatar, cover, name,
 *              member-since, Follow button) is visible to any logged-in
 *              viewer, full stop -- anyone can follow anyone, not gated by
 *              shared-trip access (cb_users_share_any_trip()). Only the
 *              Timeline/composer stay behind the existing
 *              current_user_can( 'read_forum', ... ) check. There used to
 *              be a the_content filter here blocking the ENTIRE page for a
 *              non-trip-sharing viewer; it's gone now, on purpose -- the
 *              per-section checks inside the Timeline/composer hooks below
 *              (previously redundant "defense in depth") are the real gate.
 *              This is what makes the accepted Feed asymmetry coherent: a
 *              follower can see someone's name/photo and a flagged post's
 *              teaser in their Feed, and still correctly get told the full
 *              wall isn't visible to them if they click through.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-member-profile-hooks.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Add Bio + Cover Photo as editable fields on the Account page's General
      tab -- both are built-in UM field types (confirmed live: 'description'
      => textarea/Biography, 'cover_photo' => image/Cover Photo), so no
      custom upload/crop code is needed, just adding the keys.
   ========================================================================== */
add_filter( 'um_account_tab_general_fields', function ( $args, $shortcode_args ) {
	return $args . ',description,cover_photo';
}, 10, 2 );

/* ==========================================================================
   2. "Member since {date}" -- our own concept, not native to UM, appended
      just after UM's own header (avatar, cover, name).
   ========================================================================== */
add_action( 'um_profile_header', function ( $args ) {
	$profile_user_id = function_exists( 'um_profile_id' ) ? um_profile_id() : 0;
	$user            = $profile_user_id ? get_userdata( $profile_user_id ) : false;
	if ( ! $user ) {
		return;
	}

	echo '<p class="cb-member-since">Member since ' . esc_html( date_i18n( 'M j, Y', strtotime( $user->user_registered ) ) ) . '</p>';
}, 20 );

/* ==========================================================================
   2b. Follow/Unfollow button -- part of the public header, not the gated
       Timeline/composer, per this file's header comment: anyone can follow
       anyone, independent of current_user_can( 'read_forum', ... ). Hidden
       entirely on your own profile (can't follow yourself) and for
       logged-out visitors (nothing to attribute the follow to). State
       (Follow vs. Unfollow) is rendered server-side from cb_user_follows()
       on load; the click handler (member-profile.js) just flips the button
       label/state locally after a successful REST call, no page reload.
   ========================================================================== */
add_action( 'um_profile_header', function ( $args ) {
	$profile_user_id = function_exists( 'um_profile_id' ) ? um_profile_id() : 0;
	if ( ! $profile_user_id || ! function_exists( 'cb_render_follow_button' ) ) {
		return;
	}

	echo cb_render_follow_button( $profile_user_id, get_current_user_id(), 'member-profile-follow-btn' );
}, 21 );

/* ==========================================================================
   3. Rename the one remaining tab to "Timeline" and drop the "Posts" /
      "Comments" tabs -- both are for native WP blog posts/comments, which
      this site's members never publish, and are irrelevant to the wall.
   ========================================================================== */
add_filter( 'um_profile_tabs', function ( $tabs ) {
	unset( $tabs['posts'], $tabs['comments'] );
	if ( isset( $tabs['main'] ) ) {
		$tabs['main']['name'] = 'Timeline';
	}
	return $tabs;
}, 20 );

/* ==========================================================================
   3b. Point "Edit Profile" (the gear-menu item on a member's own profile,
       confirmed live at includes/core/um-actions-profile.php's
       um_myprofile_edit_menu_items filter) at the Account page instead of
       UM's own inline ?um_action=edit mode -- Bio and Cover Photo are
       editable there (section 1 above), not on the still-mostly-unbuilt
       Default Profile form's own edit fields.
   ========================================================================== */
add_filter( 'um_myprofile_edit_menu_items', function ( $items ) {
	if ( isset( $items['editprofile'] ) && function_exists( 'um_get_core_page' ) ) {
		$items['editprofile'] = '<a href="' . esc_url( um_get_core_page( 'account' ) ) . '" class="real_url">' . esc_html__( 'Edit Profile', 'ultimate-member' ) . '</a>';
	}
	return $items;
} );

/* ==========================================================================
   4. The wall composer, relocated from the retired /members/{nicename}/
      page onto this hook. This $can_view check is now the REAL gate on the
      Timeline/composer -- there is no longer a page-level the_content
      block above it (see the file-level doc comment for why: the header is
      deliberately public now, only this section and the Timeline below it
      stay gated).
   ========================================================================== */
add_action( 'um_profile_content_main', function ( $args ) {
	$profile_user_id = function_exists( 'um_profile_id' ) ? um_profile_id() : 0;
	if ( ! $profile_user_id || ! function_exists( 'cb_ensure_user_wall_forum' ) ) {
		return;
	}

	$wall_forum_id = cb_ensure_user_wall_forum( $profile_user_id );
	$can_view      = $wall_forum_id && current_user_can( 'read_forum', $wall_forum_id );
	if ( ! $can_view ) {
		return;
	}
	?>
	<section class="member-profile-composer" id="cb-wall-composer" data-wall-owner-id="<?php echo (int) $profile_user_id; ?>">
		<textarea id="cb-wall-composer-text" class="member-profile-composer-text" rows="3" placeholder="Write something..."></textarea>
		<div class="member-profile-composer-actions">
			<button type="button" class="btn btn-ghost" id="cb-wall-post-profile-btn">Post to Profile</button>
			<button type="button" class="btn btn-ticket" id="cb-wall-post-profile-feed-btn">Post to Profile + Feed</button>
		</div>
		<p class="member-profile-composer-result" id="cb-wall-composer-result"></p>
	</section>
	<?php
}, 20 );

/* ==========================================================================
   4b. Timeline -- the wall's post list (piece 3), rendered below the
       composer (priority 25 vs. its 20). Rendering itself lives in
       checkedbags-member-wall.php (cb_wall_render_posts()) alongside the
       ownership/delete logic it depends on; this just calls it.

       Now that the header above is public (no page-level block), a denied
       viewer needs to be TOLD the wall isn't visible to them instead of
       just seeing nothing where it would be -- the composer above stays
       silent (nothing to explain, it's just an input), but this is the one
       place that explains the gate, since it's the first/only thing a
       denied viewer would otherwise find missing.
   ========================================================================== */
add_action( 'um_profile_content_main', function ( $args ) {
	$profile_user_id = function_exists( 'um_profile_id' ) ? um_profile_id() : 0;
	if ( ! $profile_user_id || ! function_exists( 'cb_ensure_user_wall_forum' ) ) {
		return;
	}

	$wall_forum_id = cb_ensure_user_wall_forum( $profile_user_id );
	$can_view      = $wall_forum_id && current_user_can( 'read_forum', $wall_forum_id );

	if ( ! $can_view ) {
		echo '<p class="cb-empty">This member&#8217;s wall isn&#8217;t visible to you.</p>';
		return;
	}
	if ( ! function_exists( 'cb_wall_render_posts' ) ) {
		return;
	}

	echo cb_wall_render_posts( $wall_forum_id );
}, 25 );

/* ==========================================================================
   5. Composer + Timeline + Follow-button JS -- enqueued on the profile page
      AND the Find Members page (checkedbags-find-members.php), since both
      render cb-follow-btn buttons now. The composer/Timeline handlers are
      inert on Find Members (no #cb-wall-composer there), same as they're
      inert for a denied viewer on the profile page, matching the
      enqueue-conditionally pattern used elsewhere in this codebase (e.g.
      Gate 08's photo gallery script).
   ========================================================================== */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_page( 'user' ) && ! is_page( 'members' ) ) {
		return;
	}

	$js_path = WP_CONTENT_DIR . '/uploads/checkedbags/js/member-profile.js';
	$js_ver  = file_exists( $js_path ) ? filemtime( $js_path ) : '1.0.0';

	wp_enqueue_script(
		'cb-member-profile',
		content_url( 'uploads/checkedbags/js/member-profile.js' ),
		array(),
		$js_ver,
		true
	);
	wp_localize_script( 'cb-member-profile', 'cbMemberProfile', array(
		'restUrl' => esc_url_raw( rest_url( 'cb/v1/' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
	) );
} );
