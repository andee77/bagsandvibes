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
 *              three are hooked, not just read_forum. No UI yet -- the
 *              /members/{user}/ page, wall posting, and Feed integration are
 *              later pieces (piece 2+ should call
 *              current_user_can( 'read_forum', $wall_forum_id ) directly as
 *              the one source of truth, rather than re-deriving the check).
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
