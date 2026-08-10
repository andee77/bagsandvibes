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
		'post_status'  => bbp_get_hidden_status_id(), // engages bbPress's own native forum/topic/reply 404 gate; our map_meta_cap filter below still makes the actual per-user decision
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
   2b. Canonical profile URL for a given user -- the one place this string
       gets built, so every nav link pointing at a member's profile agrees.

       Consolidated onto Ultimate Member's native /user/{user_nicename}/ URL
       (previously our own separate /members/{user_nicename}/ page) -- our
       wall/composer/gating now lives on that URL via hooks, see
       checkedbags-member-profile-hooks.php. Delegates to UM's own
       um_user_profile_url() so this always agrees with whatever URL
       structure UM itself considers authoritative, rather than assuming a
       path format here; falls back to constructing /user/{nicename}/
       directly only if UM's function is unexpectedly unavailable.
   ========================================================================== */
function cb_member_profile_url( $user_id ) {
	if ( function_exists( 'um_user_profile_url' ) ) {
		$url = um_user_profile_url( $user_id );
		if ( $url ) {
			return $url;
		}
	}
	$user = get_userdata( $user_id );
	return $user ? home_url( '/user/' . $user->user_nicename . '/' ) : '';
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
   4b. bbPress's own hidden-forum query exclusion is a SEPARATE gate from the
       map_meta_cap filter above, and the one that actually mattered once wall
       forums were switched to hidden status: bbp_get_excluded_forum_ids()
       (includes/forums/functions.php) builds its exclusion list purely from
       the blanket read_hidden_forums/read_private_forums ROLE capability --
       which no normal member has, not even a wall's own owner -- and applies
       it as a post__not_in / meta_query filter on every forum, topic, and
       reply query site-wide, via pre_get_posts, before map_meta_cap is ever
       consulted. Confirmed live: after forums went hidden, even the owner's
       own wall 404'd, because bbPress excluded it from the query before our
       per-forum decision could run.

       bbPress ships the fix for exactly this shape of problem: the
       'bbp_get_excluded_forum_ids' filter is the same one bbp_allow_forums_of_user()
       uses to let a per-forum moderator see one specific hidden forum despite
       lacking the blanket role cap (includes/core/filters.php:305). This
       hooks the same filter with the same ownership rule already proven
       above, so the two gates agree.

       cb_user_follows() added below (Follow feature, Feed integration
       piece) for exactly one reason: cb_feed_recent_topics()'s own
       get_posts() call is subject to this SAME global exclusion, so
       without this, a follower's feed-flagged post would silently never
       reach cb_feed_user_can_view_wall_post()'s own follow-based check at
       all -- confirmed live, not assumed (the query simply returned the
       topic in a candidate list of zero wall entries). This does NOT touch
       the map_meta_cap filter above -- a mere follower still correctly
       gets 'do_not_allow' if they try to view the actual forum/topic/
       profile directly; this only lets the topic surface in queries like
       the Feed's.
   ========================================================================== */
add_filter( 'bbp_get_excluded_forum_ids', function ( $forum_ids ) {
	if ( empty( $forum_ids ) ) {
		return $forum_ids;
	}

	$user_id = get_current_user_id();

	foreach ( $forum_ids as $key => $forum_id ) {
		$owner_id = cb_wall_forum_owner_id( $forum_id );
		if ( ! $owner_id ) {
			continue; // not a wall forum -- leave bbPress's own decision alone
		}

		if ( $user_id === $owner_id
			|| user_can( $user_id, 'moderate' )
			|| cb_users_share_any_trip( $user_id, $owner_id )
			|| ( function_exists( 'cb_user_follows' ) && cb_user_follows( $user_id, $owner_id ) )
		) {
			unset( $forum_ids[ $key ] );
		}
	}

	return array_values( $forum_ids );
} );

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

/* ==========================================================================
   6. Timeline -- the wall's post list (piece 3 of the profile redesign).
      No per-post visibility filtering needed beyond what already exists:
      the_content gate in checkedbags-member-profile-hooks.php already
      blocks the whole page for anyone who can't view this wall, so anyone
      who reaches this function is already authorized to see every post on
      it. Ordered newest-first, matching the composer's "post to the top"
      expectation.
   ========================================================================== */
function cb_wall_get_posts( $wall_forum_id ) {
	if ( ! $wall_forum_id || ! function_exists( 'bbp_get_topic_post_type' ) ) {
		return array();
	}

	return get_posts( array(
		'post_type'      => bbp_get_topic_post_type(),
		'post_parent'    => $wall_forum_id,
		'post_status'    => 'publish',
		'numberposts'    => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
}

/* ==========================================================================
   7. Can the current user delete this specific wall post? Three-tier rule,
      matching the brief exactly: the wall's own owner can delete ANY post
      on their wall (it's their space to manage); the post's original
      author can always delete their own words, even on someone else's
      wall; a moderator can always delete, same bypass used everywhere else
      in this codebase.
   ========================================================================== */
function cb_wall_user_can_delete_post( $user_id, $topic ) {
	if ( ! $topic || ! $user_id ) {
		return false;
	}

	$wall_owner_id = cb_wall_forum_owner_id( (int) $topic->post_parent );

	return (int) $user_id === $wall_owner_id
		|| (int) $user_id === (int) $topic->post_author
		|| user_can( $user_id, 'moderate' );
}

/* ==========================================================================
   8. Delete a wall post -- new server-side logic (piece 3), not just a
      relocation. The {user_id} in the URL identifies which wall is being
      managed; the topic's own post_parent is independently checked against
      that wall's actual forum ID before any authorization check runs, so a
      valid topic ID from a DIFFERENT wall can't be pointed at this
      endpoint to bypass the owner/author/moderator check above --
      confirmed by explicit test, not assumed (see verification notes).
   ========================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'cb/v1', '/members/(?P<user_id>\d+)/wall-posts/(?P<topic_id>\d+)', array(
		'methods'             => 'DELETE',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function ( $request ) {
			$wall_owner_id = (int) $request['user_id'];
			$topic_id      = (int) $request['topic_id'];

			if ( ! function_exists( 'bbp_get_topic_post_type' ) ) {
				return new WP_Error( 'cb_wall_bbpress_inactive', 'Discussion boards are not available right now.', array( 'status' => 500 ) );
			}

			$wall_forum_id = cb_ensure_user_wall_forum( $wall_owner_id );
			if ( ! $wall_forum_id ) {
				return new WP_Error( 'cb_wall_no_owner', 'That member could not be found.', array( 'status' => 404 ) );
			}

			$topic = get_post( $topic_id );
			if ( ! $topic || bbp_get_topic_post_type() !== $topic->post_type ) {
				return new WP_Error( 'cb_wall_post_not_found', 'That post could not be found.', array( 'status' => 404 ) );
			}

			// The topic must actually belong to THIS wall -- independent of
			// which wall's URL the request was sent to, closing off a
			// bypass where a real topic ID from a different wall is pointed
			// at this endpoint to piggyback on that wall's owner/author check.
			if ( (int) $topic->post_parent !== $wall_forum_id ) {
				return new WP_Error( 'cb_wall_post_wrong_wall', 'That post does not belong to this wall.', array( 'status' => 404 ) );
			}

			if ( ! cb_wall_user_can_delete_post( get_current_user_id(), $topic ) ) {
				return new WP_Error( 'cb_wall_no_delete_access', "You don't have permission to delete this post.", array( 'status' => 403 ) );
			}

			$deleted = wp_delete_post( $topic_id, true );
			if ( ! $deleted ) {
				return new WP_Error( 'cb_wall_delete_failed', 'Something went wrong deleting that post.', array( 'status' => 500 ) );
			}

			return array( 'success' => true );
		},
	) );
} );

/* ==========================================================================
   9. Render the Timeline list -- hooked onto the same um_profile_content_main
      action as the composer (checkedbags-member-profile-hooks.php), at a
      later priority so it renders below it.
   ========================================================================== */
function cb_wall_render_posts( $wall_forum_id ) {
	$posts        = cb_wall_get_posts( $wall_forum_id );
	$current_user = get_current_user_id();

	ob_start();
	?>
	<div class="member-profile-timeline">
		<?php if ( empty( $posts ) ) : ?>
			<p class="cb-empty">No posts yet.</p>
		<?php else : foreach ( $posts as $topic ) :
			$author    = get_userdata( $topic->post_author );
			$can_delete = cb_wall_user_can_delete_post( $current_user, $topic );
			if ( ! $author ) {
				continue;
			}
			?>
			<div class="member-profile-post" data-topic-id="<?php echo (int) $topic->ID; ?>">
				<div class="member-profile-post-header">
					<?php echo get_avatar( $author->ID, 40 ); ?>
					<div class="member-profile-post-byline">
						<a href="<?php echo esc_url( cb_member_profile_url( $author->ID ) ); ?>" class="member-profile-post-author"><?php echo esc_html( $author->display_name ); ?></a>
						<span class="member-profile-post-date"><?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $topic->post_date ) ) ); ?></span>
					</div>
					<?php if ( $can_delete ) : ?>
						<button type="button" class="member-profile-post-delete" data-topic-id="<?php echo (int) $topic->ID; ?>" aria-label="Delete post">
							<i class="ti ti-trash" aria-hidden="true"></i>
						</button>
					<?php endif; ?>
				</div>
				<p class="member-profile-post-content"><?php echo nl2br( esc_html( $topic->post_content ) ); ?></p>
			</div>
		<?php endforeach; endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
