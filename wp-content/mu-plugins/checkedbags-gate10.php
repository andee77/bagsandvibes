<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Gate 10: Discussion Boards
 * Description: Auto-creates one bbPress forum per active trip, maintains a
 *              permanent "Lounge" general forum, and provides the
 *              [cb_gate_boards] listing shortcode. Requires bbPress active.
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

	$trips = get_posts( array(
		'post_type'   => 'cb_trip',
		'numberposts' => -1,
		'meta_key'    => 'cb_status',
		'meta_value'  => 'active',
	) );

	ob_start();
	?>
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

	$link = '<div class="trip-detail-section"><a class="btn btn-ghost" href="' . esc_url( bbp_get_forum_permalink( $forum_id ) ) . '">Discuss this trip <i class="ti ti-arrow-right" aria-hidden="true"></i></a></div>';

	return $content . $link;

}, 25 );
