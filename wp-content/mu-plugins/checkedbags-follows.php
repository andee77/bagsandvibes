<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Follow/Unfollow
 * Description: Follow relationships between members. Deliberately
 *              independent of the existing trip-roster/wall-access model
 *              (cb_users_share_any_trip() in checkedbags-member-wall.php)
 *              -- anyone can follow anyone, not gated by shared trips.
 *
 *              Storage: a single usermeta array on the FOLLOWER's own
 *              account (_cb_following => array of user IDs), matching this
 *              codebase's existing house style for membership-like data --
 *              cb_roster on cb_trip posts is the same shape, just postmeta
 *              instead of usermeta. Chosen over a dedicated table after
 *              checking real volume: 5 real user accounts exist on this
 *              site today. Every query this feature needs -- "does A follow
 *              B" (one array-membership check), "who does A follow" (one
 *              meta read) -- is a single lookup, not something needing
 *              joins. No reverse index ("who follows X") is stored because
 *              nothing requested needs one; if that changes later, it's a
 *              new function, not a new storage shape.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-follows.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * All user IDs $user_id follows. Empty array if none, or if $user_id is
 * invalid -- callers never need to null-check, just check emptiness.
 */
function cb_user_get_following( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return array();
	}

	$following = get_user_meta( $user_id, '_cb_following', true );
	return is_array( $following ) ? array_values( array_unique( array_map( 'intval', $following ) ) ) : array();
}

/**
 * True if $follower_id follows $followee_id.
 */
function cb_user_follows( $follower_id, $followee_id ) {
	$follower_id = (int) $follower_id;
	$followee_id = (int) $followee_id;
	if ( ! $follower_id || ! $followee_id ) {
		return false;
	}

	return in_array( $followee_id, cb_user_get_following( $follower_id ), true );
}

/**
 * Start following. Refuses a self-follow and a nonexistent followee;
 * otherwise idempotent -- calling it while already following is a no-op
 * success, not an error, so the button's REST endpoint (next piece) doesn't
 * need to special-case "already following" itself.
 */
function cb_user_follow( $follower_id, $followee_id ) {
	$follower_id = (int) $follower_id;
	$followee_id = (int) $followee_id;
	if ( ! $follower_id || ! $followee_id || $follower_id === $followee_id ) {
		return false;
	}
	if ( ! get_userdata( $followee_id ) ) {
		return false;
	}

	$following = cb_user_get_following( $follower_id );
	if ( in_array( $followee_id, $following, true ) ) {
		return true;
	}

	$following[] = $followee_id;
	update_user_meta( $follower_id, '_cb_following', $following );
	return true;
}

/**
 * Stop following. Idempotent, same reasoning as cb_user_follow() above --
 * unfollowing someone not currently followed is a no-op success.
 */
function cb_user_unfollow( $follower_id, $followee_id ) {
	$follower_id = (int) $follower_id;
	$followee_id = (int) $followee_id;
	if ( ! $follower_id || ! $followee_id ) {
		return false;
	}

	$following = cb_user_get_following( $follower_id );
	$updated   = array_values( array_diff( $following, array( $followee_id ) ) );
	update_user_meta( $follower_id, '_cb_following', $updated );
	return true;
}

/**
 * The Follow/Unfollow button markup -- shared by the profile page
 * (checkedbags-member-profile-hooks.php) and the Find Members grid
 * (checkedbags-find-members.php) so both stay in sync automatically
 * instead of two copies of the same button drifting apart. Returns '' for
 * your own profile (can't follow yourself) or a logged-out viewer (nothing
 * to attribute the follow to) -- callers don't need to check either
 * condition themselves.
 *
 * "cb-follow-btn" is a class, not an id, on purpose: Find Members renders
 * one of these per listed member, so member-profile.js's click handler is
 * event-delegated to the class rather than assuming a single button per
 * page. $extra_class is an optional second class for page-specific layout
 * (the profile page uses it for centering; Find Members doesn't need one).
 */
function cb_render_follow_button( $profile_user_id, $viewer_id, $extra_class = '' ) {
	$profile_user_id = (int) $profile_user_id;
	$viewer_id       = (int) $viewer_id;
	if ( ! $profile_user_id || ! $viewer_id || $viewer_id === $profile_user_id ) {
		return '';
	}

	$is_following = cb_user_follows( $viewer_id, $profile_user_id );

	ob_start();
	?>
	<button type="button"
		class="btn <?php echo $is_following ? 'btn-ghost' : 'btn-ticket'; ?> cb-follow-btn<?php echo $extra_class ? ' ' . esc_attr( $extra_class ) : ''; ?>"
		data-profile-user-id="<?php echo $profile_user_id; ?>"
		data-following="<?php echo $is_following ? '1' : '0'; ?>">
		<?php echo $is_following ? 'Unfollow' : 'Follow'; ?>
	</button>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   REST endpoints for the Follow/Unfollow button (profile page). Same house
   pattern as every other member-facing action in this codebase (cb/v1
   namespace, permission_callback just checks is_user_logged_in(), the real
   check happens inside the callback with a WP_Error on failure) -- see e.g.
   the wall-posts endpoints in checkedbags-member-wall.php. Deliberately NOT
   checking current_user_can( 'read_forum', ... ) here -- following is
   independent of wall/profile access by design (see this file's own
   header comment).
   ========================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'cb/v1', '/members/(?P<user_id>\d+)/follow', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function ( $request ) {
			$followee_id = (int) $request['user_id'];
			if ( ! get_userdata( $followee_id ) ) {
				return new WP_Error( 'cb_follow_no_user', 'That member could not be found.', array( 'status' => 404 ) );
			}

			$follower_id = get_current_user_id();
			if ( $follower_id === $followee_id ) {
				return new WP_Error( 'cb_follow_self', "You can't follow yourself.", array( 'status' => 400 ) );
			}

			if ( ! cb_user_follow( $follower_id, $followee_id ) ) {
				return new WP_Error( 'cb_follow_failed', 'Something went wrong.', array( 'status' => 500 ) );
			}

			return array( 'success' => true, 'following' => true );
		},
	) );

	register_rest_route( 'cb/v1', '/members/(?P<user_id>\d+)/follow', array(
		'methods'             => 'DELETE',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function ( $request ) {
			$followee_id = (int) $request['user_id'];
			$follower_id = get_current_user_id();

			if ( ! cb_user_unfollow( $follower_id, $followee_id ) ) {
				return new WP_Error( 'cb_follow_failed', 'Something went wrong.', array( 'status' => 500 ) );
			}

			return array( 'success' => true, 'following' => false );
		},
	) );
} );

/* ==========================================================================
   Shortcode: [cb_following_list] -- the "Following" page (/following/, in
   the My Profile nav dropdown). Scoped exactly to what was asked: avatar,
   name, profile link per entry, nothing more (no inline unfollow button --
   not part of the approved plan for this piece).
   ========================================================================== */
add_shortcode( 'cb_following_list', function () {

	if ( ! is_user_logged_in() ) {
		return '<p class="cb-empty">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to see who you follow.</p>';
	}

	$following_ids = cb_user_get_following( get_current_user_id() );
	if ( empty( $following_ids ) ) {
		return '<p class="cb-empty">You&#8217;re not following anyone yet.</p>';
	}

	ob_start();
	?>
	<div class="following-list">
		<?php foreach ( $following_ids as $user_id ) :
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue; // followee account no longer exists
			}
			?>
			<a href="<?php echo esc_url( function_exists( 'cb_member_profile_url' ) ? cb_member_profile_url( $user_id ) : '' ); ?>" class="following-list-item">
				<?php echo get_avatar( $user_id, 56 ); ?>
				<span class="following-list-name"><?php echo esc_html( $user->display_name ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
} );
