<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Member Feed
 * Description: The [cb_feed] shortcode — a Facebook-style feed combining
 *              upcoming open trips (from cb_trip, same data Gate 07 uses),
 *              recent discussion activity (bbPress topics, same boards as
 *              Gate 10, plus -- Member Profile + Wall Post, piece 4 --
 *              wall posts explicitly flagged _cb_show_in_feed via
 *              cb_feed_user_can_view_wall_post()), admin-managed travel tip
 *              cards (new cb_tip post type), and admin-managed destination
 *              inspiration photos (new cb_destination post type). Entirely
 *              read-only on the front end — no forms, no REST endpoints, no
 *              JS file needed.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-feed.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. cb_tip — travel tip cards
   ========================================================================== */
add_action( 'init', function () {
	register_post_type( 'cb_tip', array(
		'label'        => 'Travel Tips',
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-lightbulb',
		'supports'     => array( 'title' ),
		'show_in_rest' => false,
	) );

	register_post_meta( 'cb_tip', 'cb_tip_icon', array(
		'type'              => 'string',
		'single'            => true,
		'default'           => 'ti-bulb',
		'show_in_rest'      => false,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () { return current_user_can( 'edit_posts' ); },
	) );

	register_post_meta( 'cb_tip', 'cb_tip_text', array(
		'type'              => 'string',
		'single'            => true,
		'default'           => '',
		'show_in_rest'      => false,
		'sanitize_callback' => 'sanitize_textarea_field',
		'auth_callback'     => function () { return current_user_can( 'edit_posts' ); },
	) );
} );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cb_tip_details', 'Tip Details', 'cb_render_tip_meta_box', 'cb_tip', 'normal', 'high' );
} );

function cb_render_tip_meta_box( $post ) {
	wp_nonce_field( 'cb_tip_save', 'cb_tip_nonce' );
	$icon = get_post_meta( $post->ID, 'cb_tip_icon', true ) ?: 'ti-bulb';
	$text = get_post_meta( $post->ID, 'cb_tip_text', true );

	$icon_options = array(
		'ti-bulb'          => 'Lightbulb (general tip)',
		'ti-plane'         => 'Plane (flying)',
		'ti-luggage'       => 'Luggage (packing)',
		'ti-passport'      => 'Passport (documents)',
		'ti-shield-check'  => 'Shield (safety)',
		'ti-cash'          => 'Cash (budget)',
		'ti-first-aid-kit' => 'First aid (health)',
		'ti-map-pin'       => 'Map pin (destination)',
		'ti-cloud'         => 'Cloud (weather)',
	);
	?>
	<p><label><strong>Icon</strong></label><br>
		<select name="cb_tip_icon" style="width:100%;max-width:400px;">
			<?php foreach ( $icon_options as $val => $label ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $icon, $val ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p><label><strong>Short tip text</strong> (one or two sentences)</label><br>
		<textarea name="cb_tip_text" rows="3" style="width:100%;max-width:500px;"><?php echo esc_textarea( $text ); ?></textarea>
	</p>
	<p><em>The post Title is used as the tip's headline (e.g. "Pack a portable charger").</em></p>
	<?php
}

add_action( 'save_post_cb_tip', function ( $post_id ) {
	if ( ! isset( $_POST['cb_tip_nonce'] ) || ! wp_verify_nonce( $_POST['cb_tip_nonce'], 'cb_tip_save' ) ) {
		return;
	}
	if ( isset( $_POST['cb_tip_icon'] ) ) {
		update_post_meta( $post_id, 'cb_tip_icon', sanitize_text_field( wp_unslash( $_POST['cb_tip_icon'] ) ) );
	}
	if ( isset( $_POST['cb_tip_text'] ) ) {
		update_post_meta( $post_id, 'cb_tip_text', sanitize_textarea_field( wp_unslash( $_POST['cb_tip_text'] ) ) );
	}
} );

/* ==========================================================================
   2. cb_destination — inspiration photos (title + featured image only)
   ========================================================================== */
add_action( 'init', function () {
	register_post_type( 'cb_destination', array(
		'label'        => 'Destination Inspiration',
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-camera',
		'supports'     => array( 'title', 'thumbnail' ),
		'show_in_rest' => false,
	) );
} );

/* ==========================================================================
   3. Data helpers
   ========================================================================== */
function cb_feed_upcoming_trips( $limit = 6 ) {
	return get_posts( array(
		'post_type'   => 'cb_trip',
		'numberposts' => $limit,
		'meta_key'    => 'cb_status',
		'meta_value'  => 'active',
		'orderby'     => 'meta_value',
		'meta_key2'   => 'cb_start_date',
		'order'       => 'ASC',
	) );
}

/**
 * True if $user_id may see topics in $forum_id -- the shared Lounge is open
 * to everyone (per its own description in checkedbags-gate10.php); a
 * per-trip board requires roster membership on the trip it belongs to. An
 * orphaned/unrecognized forum defaults to NOT visible, not leaked.
 *
 * Deliberately does NOT handle wall forums -- cb_wall_forum_owner_id()
 * returns 0 for every trip/Lounge forum this checks, so this function
 * already naturally excludes wall topics without any special-casing; see
 * cb_feed_user_can_view_wall_post() below for that separate case, kept
 * distinct rather than folded in here so this function's existing
 * trip/Lounge behavior is untouched.
 */
function cb_feed_user_can_view_forum( $forum_id, $user_id ) {
	if ( ! $forum_id ) {
		return false;
	}
	if ( function_exists( 'cb_ensure_lounge_forum' ) && (int) $forum_id === (int) cb_ensure_lounge_forum() ) {
		return true;
	}

	$trip_ids = get_posts( array(
		'post_type'      => 'cb_trip',
		'posts_per_page' => 1,
		'meta_key'       => 'cb_forum_id',
		'meta_value'     => $forum_id,
		'fields'         => 'ids',
	) );

	if ( empty( $trip_ids ) ) {
		return false;
	}

	return in_array( (int) $user_id, cb_trip_get_roster( $trip_ids[0] ), true );
}

/**
 * True if $user_id may see $topic_id in the feed, given it's a wall post
 * (Member Profile + Wall Post, piece 4). The flag check is a hard
 * requirement; after that, EITHER of the following is sufficient:
 *   1. The poster chose "Post to Profile + Feed" (_cb_show_in_feed === '1'),
 *      set by the composer's REST endpoint in checkedbags-member-wall.php.
 *      A "Post to Profile"-only post never surfaces here, regardless of
 *      who's asking. This part is a hard requirement, not an OR branch.
 *   2. $user_id can actually read the topic at all -- reuses
 *      current_user_can( 'read_topic', $topic_id )'s underlying check via
 *      user_can(), the exact same map_meta_cap gate proven in pieces 1-3
 *      (true for the wall owner, a moderator, or anyone sharing a trip
 *      roster with the owner). Deliberately NOT re-deriving
 *      cb_users_share_any_trip() independently here -- this codebase
 *      already has three divergent copies of forum-visibility logic
 *      (checkedbags-gate10.php's listing filter and detail-page link, plus
 *      this file's own cb_feed_user_can_view_forum() above); a wall post's
 *      feed visibility reuses the one real access-control mechanism piece 1
 *      built instead of adding a fourth.
 *   3. OR, added for the Follow feature: $user_id follows the wall's
 *      owner (cb_user_follows(), independent of shared-trip access by
 *      design -- see checkedbags-follows.php's header comment). This is
 *      purely additive -- it never narrows what #2 already allows, and it
 *      does NOT touch the actual map_meta_cap gate or the profile page's
 *      current_user_can( 'read_forum', ... ) check (checkedbags-member-
 *      profile-hooks.php). The accepted result: a follower can see a
 *      flagged post's teaser here and still correctly get told the wall
 *      isn't visible to them if they click through to the full profile --
 *      intentional, not a bug.
 */
function cb_feed_user_can_view_wall_post( $topic_id, $user_id ) {
	if ( '1' !== get_post_meta( $topic_id, '_cb_show_in_feed', true ) ) {
		return false;
	}

	if ( user_can( $user_id, 'read_topic', $topic_id ) ) {
		return true;
	}

	if ( ! function_exists( 'cb_user_follows' ) || ! function_exists( 'cb_wall_forum_owner_id' ) ) {
		return false;
	}

	$forum_id      = (int) get_post_meta( $topic_id, '_bbp_forum_id', true );
	$wall_owner_id = cb_wall_forum_owner_id( $forum_id );

	return $wall_owner_id && cb_user_follows( $user_id, $wall_owner_id );
}

function cb_feed_recent_topics( $limit = 5 ) {
	if ( ! function_exists( 'bbp_get_topic_permalink' ) ) {
		return array();
	}

	$user_id = get_current_user_id();

	// Over-fetch and filter in PHP -- there's no meta_query for "forums this
	// user has access to," only a per-topic _bbp_forum_id to check against
	// roster membership. A recent-activity teaser, not paginated, so this
	// may show fewer than $limit if most recent topics aren't visible to
	// this viewer -- acceptable for a lightweight feed widget. This same
	// pool already includes wall-forum topics (get_posts() below has no
	// forum-type distinction) -- the per-topic filter is what decides
	// whether a wall post belongs in it.
	$candidates = get_posts( array(
		'post_type'   => 'topic',
		'post_status' => 'publish',
		'numberposts' => $limit * 4,
		'orderby'     => 'date',
		'order'       => 'DESC',
	) );

	$visible = array_filter( $candidates, function ( $topic ) use ( $user_id ) {
		$forum_id = get_post_meta( $topic->ID, '_bbp_forum_id', true );

		if ( function_exists( 'cb_wall_forum_owner_id' ) && cb_wall_forum_owner_id( $forum_id ) ) {
			return cb_feed_user_can_view_wall_post( $topic->ID, $user_id );
		}

		return cb_feed_user_can_view_forum( $forum_id, $user_id );
	} );

	return array_slice( array_values( $visible ), 0, $limit );
}

/* ==========================================================================
   4. Shortcode: [cb_feed]
   ========================================================================== */
add_shortcode( 'cb_feed', function () {

	if ( ! is_user_logged_in() ) {
		return '<p class="cb-empty">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to see your feed.</p>';
	}

	$trips  = cb_feed_upcoming_trips();
	$topics = cb_feed_recent_topics();
	$tips   = get_posts( array( 'post_type' => 'cb_tip', 'numberposts' => -1 ) );
	$dests  = get_posts( array( 'post_type' => 'cb_destination', 'numberposts' => -1 ) );

	ob_start();
	?>

	<?php if ( ! empty( $trips ) ) : ?>
	<section class="feed-section">
		<h3 class="feed-section-title">Upcoming Open Travel</h3>
		<div class="feed-trip-row">
			<?php foreach ( $trips as $trip ) :
				$terms      = get_the_terms( $trip->ID, 'cb_trip_type' );
				$type_label = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : 'Trip';
				$spots      = cb_trip_spots_remaining( $trip->ID );
				?>
				<a href="<?php echo esc_url( get_permalink( $trip ) ); ?>" class="feed-trip-card">
					<span class="feed-trip-type"><?php echo esc_html( $type_label ); ?></span>
					<span class="feed-trip-title"><?php echo esc_html( get_the_title( $trip ) ); ?></span>
					<span class="feed-trip-spots"><?php echo $spots === null ? 'Open' : esc_html( $spots ) . ' spot' . ( $spots === 1 ? '' : 's' ) . ' left'; ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( ! empty( $topics ) ) : ?>
	<section class="feed-section">
		<h3 class="feed-section-title">Recent Discussion</h3>
		<div class="feed-topic-list">
			<?php foreach ( $topics as $topic ) :
				$forum_id    = get_post_meta( $topic->ID, '_bbp_forum_id', true );
				$forum_title = $forum_id ? get_the_title( $forum_id ) : '';
				?>
				<a href="<?php echo esc_url( function_exists( 'bbp_get_topic_permalink' ) ? bbp_get_topic_permalink( $topic->ID ) : get_permalink( $topic ) ); ?>" class="feed-topic-row">
					<span class="feed-topic-title"><?php echo esc_html( get_the_title( $topic ) ); ?></span>
					<?php if ( $forum_title ) : ?><span class="feed-topic-forum"><?php echo esc_html( $forum_title ); ?></span><?php endif; ?>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( ! empty( $tips ) ) : ?>
	<section class="feed-section">
		<h3 class="feed-section-title">Travel Tips</h3>
		<div class="feed-tip-grid">
			<?php foreach ( $tips as $tip ) :
				$icon = get_post_meta( $tip->ID, 'cb_tip_icon', true ) ?: 'ti-bulb';
				$text = get_post_meta( $tip->ID, 'cb_tip_text', true );
				?>
				<div class="tip-card">
					<i class="ti <?php echo esc_attr( $icon ); ?> tip-card-icon" aria-hidden="true"></i>
					<h4 class="tip-card-title"><?php echo esc_html( get_the_title( $tip ) ); ?></h4>
					<?php if ( $text ) : ?><p class="tip-card-text"><?php echo esc_html( $text ); ?></p><?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( ! empty( $dests ) ) : ?>
	<section class="feed-section">
		<h3 class="feed-section-title">Destination Inspiration</h3>
		<div class="destination-grid">
			<?php foreach ( $dests as $dest ) :
				$photo = get_the_post_thumbnail_url( $dest->ID, 'medium_large' );
				?>
				<div class="destination-card" <?php if ( $photo ) : ?>style="background-image:url('<?php echo esc_url( $photo ); ?>');"<?php endif; ?>>
					<span class="destination-card-name"><?php echo esc_html( get_the_title( $dest ) ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( empty( $trips ) && empty( $topics ) && empty( $tips ) && empty( $dests ) ) : ?>
		<p class="cb-empty">Nothing to show yet — check back soon.</p>
	<?php endif; ?>

	<?php
	return ob_get_clean();
} );
