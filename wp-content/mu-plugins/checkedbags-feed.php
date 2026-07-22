<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Member Feed
 * Description: The [cb_feed] shortcode — a Facebook-style feed combining
 *              upcoming open trips (from cb_trip, same data Gate 07 uses),
 *              recent discussion activity (bbPress topics, same boards as
 *              Gate 10), admin-managed travel tip cards (new cb_tip post
 *              type), and admin-managed destination inspiration photos
 *              (new cb_destination post type). Entirely read-only on the
 *              front end — no forms, no REST endpoints, no JS file needed.
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

function cb_feed_recent_topics( $limit = 5 ) {
	if ( ! function_exists( 'bbp_get_topic_permalink' ) ) {
		return array();
	}
	return get_posts( array(
		'post_type'   => 'topic',
		'post_status' => 'publish',
		'numberposts' => $limit,
		'orderby'     => 'date',
		'order'       => 'DESC',
	) );
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
