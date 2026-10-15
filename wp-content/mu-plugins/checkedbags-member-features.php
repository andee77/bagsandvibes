<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Member Features switch
 * Description: One setting (Settings > CBGV Member Features) that turns the member social features on or off:
 *              profile walls, following, the members directory and profile pages (owner, 2026-10-15). Off by
 *              default. Nothing is deleted: wall posts, follow lists, bios and cover photos stay, and switching
 *              back on restores everything as it was. Trip pages, trip boards, the Lounge, My Account, payments
 *              and Gate 12 are not affected.
 *
 * The social modules stay loaded (trip pages use helpers from checkedbags-member-wall.php); each feature is
 * switched off where it is used: here (REST, Following / Find Members pages, profile and wall redirects) and in
 * small guarded spots in checkedbags-member-wall.php, checkedbags-follows.php, checkedbags-feed.php and
 * checkedbags-gate07.php ("Who's going").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Are the member social features on? A CBV_MEMBER_SOCIAL constant (wp-config) wins over the setting. */
function cbv_member_social_enabled() {
	if ( defined( 'CBV_MEMBER_SOCIAL' ) ) {
		return (bool) CBV_MEMBER_SOCIAL;
	}
	return '1' === (string) get_option( 'cbv_member_social_enabled', '0' );
}

/** The setting is stored as "1" (on) or "0" (off). */
function cbv_member_social_sanitize( $v ) {
	return ! empty( $v ) && '0' !== (string) $v ? '1' : '0';
}

/** What a member sees on the Following and Find Members pages while the features are off. */
function cbv_member_social_off_notice() {
	return '<p class="cb-empty">This part of the site is switched off for now. Your trips, trip boards and account are all still here: <a href="' . esc_url( home_url( '/my-passport/' ) ) . '">go to My Passport</a>.</p>';
}

/* ==========================================================================
   1. Off: wall posting / deleting and follow / unfollow requests are refused.
   ========================================================================== */
add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
	if ( null !== $result || cbv_member_social_enabled() ) {
		return $result;
	}
	if ( preg_match( '#^/cb/v1/members/\d+/(wall-posts|follow)(/|$)#', (string) $request->get_route() ) ) {
		return new WP_Error( 'cbv_social_off', 'This feature is switched off.', array( 'status' => 403 ) );
	}
	return $result;
}, 10, 3 );

/* ==========================================================================
   2. Off: the Following and Find Members pages show a notice. Signed-out
      visitors still get the pages' own sign-in prompt.
   ========================================================================== */
add_action( 'init', function () {
	global $shortcode_tags;
	foreach ( array( 'cb_following_list', 'cb_find_members' ) as $tag ) {
		if ( empty( $shortcode_tags[ $tag ] ) ) {
			continue;
		}
		$original               = $shortcode_tags[ $tag ];
		$shortcode_tags[ $tag ] = function ( $atts = array(), $content = '', $shortcode = '' ) use ( $original ) {
			if ( cbv_member_social_enabled() || ! is_user_logged_in() ) {
				return call_user_func( $original, $atts, $content, $shortcode );
			}
			return cbv_member_social_off_notice();
		};
	}
}, 99 );

/* ==========================================================================
   3. Off: profile pages go to My Account, a wall's forum page to My Passport.
      Runs before the /members/{name}/ redirect (priority 5) and the wall
      forum redirect (priority 9). Moderators can still open bbPress user
      pages and wall forums.
   ========================================================================== */
function cbv_member_social_redirect_target() {
	if ( cbv_member_social_enabled() ) {
		return '';
	}
	$account = home_url( '/account/general/' );
	if ( get_query_var( 'cb_member_nicename' ) ) {
		return $account; // /members/{name}/
	}
	if ( is_page( 'user' ) ) {
		return $account; // Ultimate Member profile, /user/{name}/
	}
	if ( current_user_can( 'moderate' ) ) {
		return '';
	}
	if ( function_exists( 'bbp_is_single_user' ) && bbp_is_single_user() ) {
		return $account; // bbPress profile, /forums/users/{name}/
	}
	if ( function_exists( 'bbp_is_single_forum' ) && bbp_is_single_forum() && function_exists( 'cb_wall_forum_owner_id' ) && cb_wall_forum_owner_id( get_queried_object_id() ) ) {
		return home_url( '/my-passport/' );
	}
	return '';
}

add_action( 'template_redirect', function () {
	$to = cbv_member_social_redirect_target();
	if ( '' !== $to ) {
		wp_safe_redirect( $to );
		exit;
	}
}, 4 );

/* ==========================================================================
   4. Settings > CBGV Member Features.
   ========================================================================== */
add_action( 'admin_init', function () {
	register_setting( 'cbv_member_features', 'cbv_member_social_enabled', array(
		'type'              => 'string',
		'default'           => '0',
		'sanitize_callback' => 'cbv_member_social_sanitize',
	) );
} );

add_action( 'admin_menu', function () {
	add_options_page( 'CBGV Member Features', 'CBGV Member Features', 'manage_options', 'cbv-member-features', 'cbv_render_member_features_page' );
} );

function cbv_render_member_features_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$forced = defined( 'CBV_MEMBER_SOCIAL' );
	?>
	<div class="wrap">
		<h1>CBGV Member Features</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'cbv_member_features' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Member social features</th>
					<td>
						<input type="hidden" name="cbv_member_social_enabled" value="0">
						<label><input type="checkbox" name="cbv_member_social_enabled" value="1" <?php checked( cbv_member_social_enabled() ); ?> <?php disabled( $forced ); ?>> On: profile walls, following, the members directory and profile pages</label>
						<p class="description">Off by default (owner, 2026-10-15). While off, members don't see walls, Follow buttons, the Following and Find Members pages or profile pages (those go to My Account), and the Feed shows no wall posts. "Who's going" on trip pages shows names and photos without profile links.</p>
						<p class="description">Nothing is deleted: wall posts, follow lists, bios and cover photos are kept, and switching this back on restores everything as it was. Trip pages, trip boards, the Lounge, My Account, payments and Gate 12 are not affected. Settings &gt; Auto-Follow Account has no effect while this is off.</p>
						<?php if ( $forced ) : ?>
							<p class="description"><strong>Fixed by CBV_MEMBER_SOCIAL in wp-config.php</strong>; this checkbox can't change it.</p>
						<?php endif; ?>
					</td>
				</tr>
				<?php do_action( 'cbv_member_features_fields' ); // more member settings (e.g. the podcast on the Feed) ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
