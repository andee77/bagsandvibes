<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Member Profile + Wall: Piece 1 (Wall Forum Infrastructure)
 * Description: Foundation for the Member Profile + Wall Post feature. Provides
 *              cb_ensure_user_wall_forum() -- one personal bbPress forum per
 *              user, created lazily and cached (mirrors cb_ensure_lounge_forum()
 *              in checkedbags-gate10.php) -- and cb_users_share_any_trip(), a
 *              pairwise roster-overlap check (mirrors the roster-filter idiom
 *              already used for trip-board visibility in checkedbags-gate10.php
 *              and checkedbags-feed.php). Unlike the existing trip/Lounge
 *              boards (soft-gated by link-hiding only -- see those files),
 *              wall forums are genuinely access-controlled: a map_meta_cap
 *              filter overrides bbPress's own read_forum/read_topic/read_reply
 *              decisions specifically for wall forums, confirmed by reading
 *              bbPress's own includes/forums|topics|replies/capabilities.php
 *              on the live install -- read_topic and read_reply do NOT
 *              re-check their parent forum's access on their own, so all
 *              three are hooked, not just read_forum. Piece 2 built the
 *              /members/{user}/ profile shell (mu-plugins/checkedbags-landing.php
 *              + template-member-profile.php), gated on
 *              current_user_can( 'read_forum', $wall_forum_id ) as the one
 *              source of truth. Piece 3 (this file's section 5) adds the
 *              REST endpoint the profile page's composer posts to --
 *              bbp_insert_topic() plus a _cb_show_in_feed topic-meta flag.
 *              Feed integration itself (surfacing flagged posts there) is
 *              still a later piece.
 *              Requires bbPress active. Depends on checkedbags-trips.php for
 *              the cb_trip post type and cb_trip_get_roster().
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-member-wall.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Personal wall forum -- one per user, created the first time it's
      needed (first profile view, in a later piece) rather than eagerly for
      every registered user. ID cached in user meta so repeat calls are a
      single get_user_meta() read, not a new bbp_insert_forum() every time --
      same check-cache/insert-if-missing shape as cb_ensure_lounge_forum(),
      just keyed to a user instead of a singleton option.
   ========================================================================== */
function cb_ensure_user_wall_forum( $user_id ) {
	if ( ! function_exists( 'bbp_insert_forum' ) ) {
		return 0;
	}

	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return 0;
	}

	$existing = get_user_meta( $user_id, '_cb_wall_forum_id', true );
	if ( $existing && get_post( $existing ) ) {
		return (int) $existing;
	}

	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return 0;
	}

	$forum_id = bbp_insert_forum( array(
		'post_title'   => $user->display_name . '&#8217;s Wall',
		'post_content' => 'Personal wall for ' . $user->display_name . '.',
		'post_author'  => $user_id,
	) );

	if ( $forum_id ) {
		update_user_meta( $user_id, '_cb_wall_forum_id', $forum_id );
		// Reverse lookup (forum -> owner), needed by cb_wall_forum_owner_id()
		// below so the map_meta_cap hook can identify a wall forum from a
		// bare forum/topic/reply ID without scanning every user's meta.
		update_post_meta( $forum_id, '_cb_wall_owner_user_id', $user_id );
	}

	return (int) $forum_id;
}

/* ==========================================================================
   2a. Forum ID -> wall owner user ID, or 0 if it isn't a wall forum. Single
       source of truth for "is this forum a wall forum," used by the
       enforcement hook below and available to later pieces too.
   ========================================================================== */
function cb_wall_forum_owner_id( $forum_id ) {
	return (int) get_post_meta( (int) $forum_id, '_cb_wall_owner_user_id', true );
}

/* ==========================================================================
   2b. Canonical /members/{user_nicename}/ URL for a given user -- the one
       place this string gets built, so every nav link pointing at a
       member's profile agrees with the rewrite rule registered in
       mu-plugins/checkedbags-landing.php.
   ========================================================================== */
function cb_member_profile_url( $user_id ) {
	$user = get_userdata( $user_id );
	return $user ? home_url( '/members/' . $user->user_nicename . '/' ) : '';
}

/* ==========================================================================
   3. Pairwise trip-sharing check -- true if the two users have ever been on
      the same trip's roster together. This is the gate a later piece will
      use to decide whether one member can see/post on another's wall.
      Deliberately the same shape as the roster-filter idiom already used
      for trip-board visibility (checkedbags-gate10.php:100-110,
      checkedbags-feed.php:130-157): fetch all cb_trip IDs, loop, in_array()
      against cb_trip_get_roster() -- no meta_query trickery, since the
      roster is a plain serialized array, not individually queryable.
   ========================================================================== */
function cb_users_share_any_trip( $user_id_a, $user_id_b ) {
	$user_id_a = (int) $user_id_a;
	$user_id_b = (int) $user_id_b;

	if ( ! $user_id_a || ! $user_id_b || $user_id_a === $user_id_b ) {
		return false;
	}

	$trip_ids = get_posts( array(
		'post_type'      => 'cb_trip',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	foreach ( $trip_ids as $trip_id ) {
		$roster = cb_trip_get_roster( $trip_id );
		if ( in_array( $user_id_a, $roster, true ) && in_array( $user_id_b, $roster, true ) ) {
			return true;
		}
	}

	return false;
}

/* ==========================================================================
   4. Real access enforcement for wall forums -- unlike the existing trip and
      Lounge boards (gated only by hiding links, confirmed by reading
      checkedbags-gate10.php/checkedbags-feed.php: no post_password, no
      capability mapping, so a bookmarked/guessed URL isn't actually
      blocked), wall content gets genuinely enforced at the WordPress
      capability layer, since a personal wall is more sensitive than a trip
      board. That existing trip/Lounge gap is intentionally left as-is here
      -- a separate future fix, not part of this piece.

      Confirmed directly against this site's installed bbPress
      (wp-content/plugins/bbpress/includes/{forums,topics,replies}/capabilities.php):
      bbPress hooks its entire capability system onto core's own
      `map_meta_cap` filter at priority 10 (includes/core/filters.php:45).
      Hooking the same filter at priority 20 runs strictly after bbPress's
      own read_forum/read_topic/read_reply decision, so it can be fully
      overridden -- but ONLY when the forum/topic/reply in question belongs
      to a wall forum; every other forum's $caps passes through untouched,
      leaving all existing trip/Lounge behavior exactly as it is today.

      read_topic and read_reply do NOT re-check their parent forum's access
      on their own (each is gated purely on the topic's/reply's own post
      status) -- confirmed by reading bbPress's own source, not assumed --
      so all three meta caps are hooked here, not just read_forum. Both
      topics and replies carry bbPress's own `_bbp_forum_id` post meta
      pointing back to their forum (confirmed at
      includes/replies/template.php:1465), used below to resolve either one
      back to its forum before checking wall ownership.
   ========================================================================== */
function cb_wall_forum_resolve_forum_id( $cap, $post_id ) {
	if ( 'read_forum' === $cap ) {
		return (int) $post_id;
	}
	return (int) get_post_meta( $post_id, '_bbp_forum_id', true );
}

add_filter( 'map_meta_cap', function ( $caps, $cap, $user_id, $args ) {
	if ( ! in_array( $cap, array( 'read_forum', 'read_topic', 'read_reply' ), true ) || empty( $args[0] ) ) {
		return $caps;
	}

	$forum_id = cb_wall_forum_resolve_forum_id( $cap, (int) $args[0] );
	$owner_id = $forum_id ? cb_wall_forum_owner_id( $forum_id ) : 0;
	if ( ! $owner_id ) {
		return $caps; // not a wall forum -- leave bbPress's own decision alone
	}

	if ( (int) $user_id === $owner_id
		|| user_can( $user_id, 'moderate' )
		|| cb_users_share_any_trip( $user_id, $owner_id )
	) {
		return array( 'read' ); // universally-held cap, used purely as an "allow" signal
	}

	return array( 'do_not_allow' );
}, 20, 4 );

/* ==========================================================================
   5. Wall posting REST endpoint -- the profile page's composer (piece 2's
      template-member-profile.php) posts here. Same house pattern as every
      other member-facing action in this codebase (cb/v1 namespace,
      permission_callback just checks is_user_logged_in(), the real access
      check happens inside the callback with a WP_Error on failure -- see
      e.g. the cover-photo endpoints in checkedbags-trip-invites.php).

      Posting is gated on the EXACT same current_user_can( 'read_forum', ... )
      check as viewing -- not owner-only, matching the "wall" concept: a
      roster-sharer can post to someone else's wall, not just the owner.
      _cb_show_in_feed is stored as plain topic post meta ('1'/'0'); Feed
      integration (actually reading this flag to decide what surfaces there)
      is a later piece, not this one.
   ========================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'cb/v1', '/members/(?P<user_id>\d+)/wall-posts', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function ( $request ) {
			$wall_owner_id = (int) $request['user_id'];
			$wall_owner    = get_userdata( $wall_owner_id );
			if ( ! $wall_owner ) {
				return new WP_Error( 'cb_wall_no_owner', 'That member could not be found.', array( 'status' => 404 ) );
			}

			if ( ! function_exists( 'bbp_insert_topic' ) ) {
				return new WP_Error( 'cb_wall_bbpress_inactive', 'Discussion boards are not available right now.', array( 'status' => 500 ) );
			}

			$wall_forum_id = cb_ensure_user_wall_forum( $wall_owner_id );
			if ( ! $wall_forum_id || ! current_user_can( 'read_forum', $wall_forum_id ) ) {
				return new WP_Error( 'cb_wall_no_access', "You don't have access to post on this wall.", array( 'status' => 403 ) );
			}

			$content = sanitize_textarea_field( (string) $request->get_param( 'content' ) );
			if ( '' === trim( $content ) ) {
				return new WP_Error( 'cb_wall_empty_post', 'Write something before posting.', array( 'status' => 400 ) );
			}

			$topic_id = bbp_insert_topic( array(
				'post_title'   => wp_trim_words( $content, 10, '…' ),
				'post_content' => $content,
				'post_parent'  => $wall_forum_id,
				'post_author'  => get_current_user_id(),
			), array( 'forum_id' => $wall_forum_id ) );

			if ( ! $topic_id || is_wp_error( $topic_id ) ) {
				return new WP_Error( 'cb_wall_post_failed', 'Something went wrong posting to the wall.', array( 'status' => 500 ) );
			}

			$show_in_feed = ! empty( $request->get_param( 'show_in_feed' ) );
			update_post_meta( $topic_id, '_cb_show_in_feed', $show_in_feed ? '1' : '0' );

			return array( 'success' => true, 'topic_id' => $topic_id );
		},
	) );
} );
