<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Gate 10: Discussion Boards
 * Description: Auto-creates one bbPress forum per active trip, maintains a
 *              permanent "Lounge" general forum, and provides the
 *              [cb_gate_boards] listing shortcode. Also genuinely enforces
 *              access to trip/Lounge forums at the bbPress capability level
 *              (map_meta_cap, priority 20) -- previously only soft-gated by
 *              hiding links, mirroring the same pattern already proven for
 *              wall forums in checkedbags-member-wall.php, as a fully
 *              separate/independent hook. Requires bbPress active.
 *              Depends on checkedbags-trips.php for the cb_trip post type,
 *              cb_trip_status_changed action, and cb_trip_get_roster().
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-gate10.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Admin notice if bbPress isn't active — fails loud, not silent.
   ========================================================================== */
add_action( 'admin_notices', function () {
	if ( ! function_exists( 'bbp_insert_forum' ) ) {
		echo '<div class="notice notice-error"><p><strong>Checked Bags Gate 10:</strong> bbPress is not active. Discussion boards will not be created until it is installed and activated.</p></div>';
	}
} );

/* ==========================================================================
   2. Lounge — one permanent general forum, created once, ID stored in an
      option so we never accidentally create a second one.
   ========================================================================== */
function cb_ensure_lounge_forum() {
	if ( ! function_exists( 'bbp_insert_forum' ) ) {
		return false;
	}

	$existing = get_option( 'cb_lounge_forum_id' );
	if ( $existing && get_post( $existing ) ) {
		return $existing;
	}

	$forum_id = bbp_insert_forum( array(
		'post_title'   => 'Lounge',
		'post_content' => 'General chatter — anything goes, open to every member.',
	) );

	if ( $forum_id ) {
		update_option( 'cb_lounge_forum_id', $forum_id );
	}

	return $forum_id;
}
add_action( 'init', 'cb_ensure_lounge_forum', 20 ); // after bbPress registers its post types at default priority

/* ==========================================================================
   3. Auto-create a trip's board the moment it goes active. Hooks the action
      fired by cb_trip_set_status() in checkedbags-trips.php.
   ========================================================================== */
add_action( 'cb_trip_status_changed', function ( $trip_id, $old_status, $new_status ) {

	if ( $new_status !== 'active' ) {
		return;
	}
	if ( get_post_meta( $trip_id, 'cb_forum_id', true ) ) {
		return; // board already exists for this trip, don't duplicate
	}
	if ( ! function_exists( 'bbp_insert_forum' ) ) {
		return;
	}

	$forum_id = bbp_insert_forum( array(
		'post_title'   => get_the_title( $trip_id ) . ' — Trip Board',
		'post_content' => 'Planning chatter, carpooling, roommate matching for this trip.',
		'post_status'  => bbp_get_hidden_status_id(), // engages bbPress's own native forum/topic/reply 404 gate; the map_meta_cap filter below still makes the actual per-user decision. The Lounge (cb_ensure_lounge_forum() above) is deliberately NOT changed -- it stays public/open.
	) );

	if ( $forum_id ) {
		update_post_meta( $trip_id, 'cb_forum_id', $forum_id );
	}

}, 10, 3 );

/* ==========================================================================
   4. Shortcode: [cb_gate_boards] — Lounge + one row per trip with a board.
   ========================================================================== */
add_shortcode( 'cb_gate_boards', function () {

	if ( ! is_user_logged_in() ) {
		return '<p class="cb-empty">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to see discussion boards.</p>';
	}

	if ( ! function_exists( 'bbp_get_forum_permalink' ) ) {
		return '<p class="cb-empty">Discussion boards aren\'t set up yet.</p>';
	}

	$lounge_id = cb_ensure_lounge_forum();
	$user_id   = get_current_user_id();

	// The Lounge is deliberately open to everyone (see its own description
	// above, "open to every member") -- only the per-trip boards need a
	// roster check, since each one is that trip's private planning space.
	$trips = array_filter(
		get_posts( array(
			'post_type'   => 'cb_trip',
			'numberposts' => -1,
			'meta_key'    => 'cb_status',
			'meta_value'  => 'active',
		) ),
		function ( $t ) use ( $user_id ) {
			return in_array( $user_id, cb_trip_get_roster( $t->ID ), true );
		}
	);

	ob_start();
	?>
	<p class="cb-page-hint">Chat with your crew here — the Lounge is open to everyone, and each trip gets its own board once you&#8217;re on the roster.</p>
	<div class="board-list">

		<?php if ( $lounge_id ) : ?>
		<a href="<?php echo esc_url( bbp_get_forum_permalink( $lounge_id ) ); ?>" class="board-row board-row-lounge">
			<span class="board-row-icon"><i class="ti ti-pin" aria-hidden="true"></i></span>
			<span class="board-row-title">Lounge</span>
			<span class="board-row-meta">General chatter</span>
		</a>
		<?php endif; ?>

		<?php foreach ( $trips as $trip ) :
			$forum_id = get_post_meta( $trip->ID, 'cb_forum_id', true );
			if ( ! $forum_id ) {
				continue; // no board yet for this trip
			}
			$topic_count = function_exists( 'bbp_get_forum_topic_count' ) ? bbp_get_forum_topic_count( $forum_id ) : 0;
			?>
			<a href="<?php echo esc_url( bbp_get_forum_permalink( $forum_id ) ); ?>" class="board-row">
				<span class="board-row-icon"><i class="ti ti-messages" aria-hidden="true"></i></span>
				<span class="board-row-title"><?php echo esc_html( get_the_title( $trip ) ); ?></span>
				<span class="board-row-meta"><?php echo esc_html( $topic_count ); ?> topic<?php echo $topic_count === 1 ? '' : 's'; ?></span>
			</a>
		<?php endforeach; ?>

	</div>
	<?php
	return ob_get_clean();
} );

/* ==========================================================================
   5. "Discuss this trip" link on each trip's detail page.
      Runs after Gate 07's the_content filter (priority 20), so this appends
      below the roster section already added there.
   ========================================================================== */
add_filter( 'the_content', function ( $content ) {

	if ( ! is_singular( 'cb_trip' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( ! is_user_logged_in() || ! function_exists( 'bbp_get_forum_permalink' ) ) {
		return $content;
	}

	global $post;
	$forum_id = get_post_meta( $post->ID, 'cb_forum_id', true );
	if ( ! $forum_id ) {
		return $content;
	}

	// Same access gate Gate 07 already enforces on this page's own content
	// (Phase 4) -- otherwise a visitor Gate 07 just told "you don't have
	// access" would still get a working link into this trip's private board.
	if ( function_exists( 'cbv_user_can_view_trip' ) && ! cbv_user_can_view_trip( get_current_user_id(), $post->ID ) ) {
		return $content;
	}

	$link = '<div class="trip-detail-section"><a class="btn btn-ghost" href="' . esc_url( bbp_get_forum_permalink( $forum_id ) ) . '">Discuss this trip <i class="ti ti-arrow-right" aria-hidden="true"></i></a></div>';

	return $content . $link;

}, 25 );

/* ==========================================================================
   6. Real access enforcement for trip forums and the Lounge -- until now,
      these were only soft-gated by hiding links (sections 4 and 5 above); a
      bookmarked/guessed forum/topic/reply URL was never actually blocked at
      the bbPress capability level. This mirrors the exact map_meta_cap
      approach already proven for wall forums (checkedbags-member-wall.php),
      as a fully separate, independent hook -- it never calls into that
      file's logic and explicitly skips any forum a wall-forum owns,
      leaving that gate completely untouched.

      Same idiom as the wall-forum hook: resolve topic/reply back to their
      forum via bbPress's own _bbp_forum_id post meta (read_forum needs no
      resolution, it already is the forum); override bbPress's own decision
      only when the forum is positively recognized as the Lounge or a real
      trip's board -- an unrecognized forum ID (not the Lounge, not a wall
      forum, not any trip's cb_forum_id) falls through with $caps
      unchanged, same as bbPress's own default, rather than being blocked
      outright for content this hook doesn't actually own.
   ========================================================================== */
function cb_gate10_forum_trip_id( $forum_id ) {
	$trip_ids = get_posts( array(
		'post_type'      => 'cb_trip',
		'posts_per_page' => 1,
		'meta_key'       => 'cb_forum_id',
		'meta_value'     => $forum_id,
		'fields'         => 'ids',
	) );
	return $trip_ids ? (int) $trip_ids[0] : 0;
}

function cb_gate10_resolve_forum_id( $cap, $post_id ) {
	if ( 'read_forum' === $cap ) {
		return (int) $post_id;
	}
	return (int) get_post_meta( $post_id, '_bbp_forum_id', true );
}

add_filter( 'map_meta_cap', function ( $caps, $cap, $user_id, $args ) {
	if ( ! in_array( $cap, array( 'read_forum', 'read_topic', 'read_reply' ), true ) || empty( $args[0] ) ) {
		return $caps;
	}

	$forum_id = cb_gate10_resolve_forum_id( $cap, (int) $args[0] );
	if ( ! $forum_id ) {
		return $caps;
	}

	// Wall forums are a separate, independent gate -- never override that
	// hook's decision here.
	if ( function_exists( 'cb_wall_forum_owner_id' ) && cb_wall_forum_owner_id( $forum_id ) ) {
		return $caps;
	}

	if ( (int) $forum_id === (int) cb_ensure_lounge_forum() ) {
		return array( 'read' ); // Lounge is open to every member
	}

	$trip_id = cb_gate10_forum_trip_id( $forum_id );
	if ( ! $trip_id ) {
		return $caps; // not a recognized trip forum or the Lounge -- leave bbPress's own decision alone
	}

	if ( in_array( (int) $user_id, cb_trip_get_roster( $trip_id ), true ) || user_can( $user_id, 'moderate' ) ) {
		return array( 'read' );
	}

	return array( 'do_not_allow' );
}, 20, 4 );

/* ==========================================================================
   7. bbPress's own hidden-forum query exclusion -- a separate gate from the
      map_meta_cap filter above, and the one that actually mattered once trip
      boards were switched to hidden status: bbp_get_excluded_forum_ids()
      (includes/forums/functions.php) builds its exclusion list purely from
      the blanket read_hidden_forums ROLE capability -- which no normal
      member has, not even a roster member of the trip -- and applies it as
      a post__not_in / meta_query filter on every forum, topic, and reply
      query site-wide, via pre_get_posts, before map_meta_cap is ever
      consulted. Confirmed live: after trip boards went hidden, even a
      roster member's own board 404'd, because bbPress excluded it from the
      query before our per-forum decision could run.

      Same fix as checkedbags-member-wall.php's matching hook: bbPress ships
      the 'bbp_get_excluded_forum_ids' filter for exactly this -- it's the
      same one bbp_allow_forums_of_user() uses to let a per-forum moderator
      see one specific hidden forum despite lacking the blanket role cap
      (includes/core/filters.php:305). The Lounge never appears in this list
      (it's never hidden), so it needs no handling here.
   ========================================================================== */
add_filter( 'bbp_get_excluded_forum_ids', function ( $forum_ids ) {
	if ( empty( $forum_ids ) ) {
		return $forum_ids;
	}

	$user_id = get_current_user_id();

	foreach ( $forum_ids as $key => $forum_id ) {
		if ( function_exists( 'cb_wall_forum_owner_id' ) && cb_wall_forum_owner_id( $forum_id ) ) {
			continue; // wall forums are a separate, independent gate
		}

		$trip_id = cb_gate10_forum_trip_id( $forum_id );
		if ( ! $trip_id ) {
			continue; // not a recognized trip forum
		}

		if ( in_array( $user_id, cb_trip_get_roster( $trip_id ), true ) || user_can( $user_id, 'moderate' ) ) {
			unset( $forum_ids[ $key ] );
		}
	}

	return array_values( $forum_ids );
} );
