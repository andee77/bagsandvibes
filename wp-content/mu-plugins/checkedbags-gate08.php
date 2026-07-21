<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Gate 08: Photo Gallery
 * Description: Trip photo albums using native WordPress attachments
 *              (post_parent = trip ID) rather than a custom post type.
 *              Members on a trip's roster can upload; gallery visibility
 *              respects the existing cb_gallery_privacy meta field on
 *              cb_trip (already defined in checkedbags-trips.php).
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-gate08.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. REST: upload a photo, toggle a like, delete your own photo.
   ========================================================================== */
add_action( 'rest_api_init', function () {

	register_rest_route( 'cb/v1', '/trips/(?P<id>\d+)/photos', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return is_user_logged_in(); },
		'callback'            => 'cb_handle_photo_upload',
	) );

	register_rest_route( 'cb/v1', '/photos/(?P<id>\d+)/like', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return is_user_logged_in(); },
		'callback'            => 'cb_toggle_photo_like',
	) );

	register_rest_route( 'cb/v1', '/photos/(?P<id>\d+)', array(
		'methods'             => 'DELETE',
		'permission_callback' => function () { return is_user_logged_in(); },
		'callback'            => 'cb_delete_photo',
	) );

} );

function cb_handle_photo_upload( $request ) {
	$trip_id = (int) $request['id'];
	$trip    = get_post( $trip_id );
	$user_id = get_current_user_id();

	if ( ! $trip || $trip->post_type !== 'cb_trip' ) {
		return new WP_Error( 'cb_not_found', 'Trip not found.', array( 'status' => 404 ) );
	}
	if ( ! in_array( $user_id, cb_trip_get_roster( $trip_id ), true ) ) {
		return new WP_Error( 'cb_not_on_trip', 'Only members on this trip can upload photos.', array( 'status' => 403 ) );
	}

	$files = $request->get_file_params();
	if ( empty( $files['photo'] ) ) {
		return new WP_Error( 'cb_no_file', 'No photo was received.', array( 'status' => 400 ) );
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$attachment_id = media_handle_upload( 'photo', $trip_id );

	if ( is_wp_error( $attachment_id ) ) {
		return new WP_Error( 'cb_upload_failed', $attachment_id->get_error_message(), array( 'status' => 400 ) );
	}

	return array(
		'id'          => $attachment_id,
		'thumb_url'   => wp_get_attachment_image_url( $attachment_id, 'medium' ),
		'full_url'    => wp_get_attachment_image_url( $attachment_id, 'large' ),
	);
}

function cb_toggle_photo_like( $request ) {
	$photo_id = (int) $request['id'];
	$photo    = get_post( $photo_id );
	$user_id  = get_current_user_id();

	if ( ! $photo || $photo->post_type !== 'attachment' ) {
		return new WP_Error( 'cb_not_found', 'Photo not found.', array( 'status' => 404 ) );
	}

	$likes = get_post_meta( $photo_id, 'cb_photo_likes', true );
	$likes = is_array( $likes ) ? $likes : array();

	if ( in_array( $user_id, $likes, true ) ) {
		$likes = array_values( array_diff( $likes, array( $user_id ) ) );
		$liked = false;
	} else {
		$likes[] = $user_id;
		$liked   = true;
	}

	update_post_meta( $photo_id, 'cb_photo_likes', $likes );

	return array( 'liked' => $liked, 'count' => count( $likes ) );
}

function cb_delete_photo( $request ) {
	$photo_id = (int) $request['id'];
	$photo    = get_post( $photo_id );
	$user_id  = get_current_user_id();

	if ( ! $photo || $photo->post_type !== 'attachment' ) {
		return new WP_Error( 'cb_not_found', 'Photo not found.', array( 'status' => 404 ) );
	}
	if ( (int) $photo->post_author !== $user_id && ! current_user_can( 'edit_others_posts' ) ) {
		return new WP_Error( 'cb_not_yours', 'You can only delete your own photos.', array( 'status' => 403 ) );
	}

	wp_delete_attachment( $photo_id, true );
	return array( 'deleted' => true );
}

/* ==========================================================================
   2. Helpers
   ========================================================================== */
function cb_trip_photos( $trip_id ) {
	return get_posts( array(
		'post_type'      => 'attachment',
		'post_parent'    => $trip_id,
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'numberposts'    => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
}

function cb_render_photo_grid( $trip_id, $can_upload ) {
	$photos  = cb_trip_photos( $trip_id );
	$user_id = get_current_user_id();
	ob_start();
	?>
	<div class="gallery-grid" data-trip-id="<?php echo esc_attr( $trip_id ); ?>">
		<?php foreach ( $photos as $photo ) :
			$thumb = wp_get_attachment_image_url( $photo->ID, 'medium' );
			$full  = wp_get_attachment_image_url( $photo->ID, 'large' );
			$likes = get_post_meta( $photo->ID, 'cb_photo_likes', true );
			$likes = is_array( $likes ) ? $likes : array();
			$liked = in_array( $user_id, $likes, true );
			?>
			<div class="photo-tile">
				<img src="<?php echo esc_url( $thumb ); ?>" data-full="<?php echo esc_url( $full ); ?>" class="photo-tile-img" alt="" loading="lazy">
				<button class="photo-like-btn <?php echo $liked ? 'is-liked' : ''; ?>" data-photo-id="<?php echo esc_attr( $photo->ID ); ?>">
					<i class="ti ti-heart<?php echo $liked ? '-filled' : ''; ?>" aria-hidden="true"></i>
					<span class="photo-like-count"><?php echo esc_html( count( $likes ) ); ?></span>
				</button>
				<?php if ( (int) $photo->post_author === $user_id ) : ?>
				<button class="photo-delete-btn" data-photo-id="<?php echo esc_attr( $photo->ID ); ?>" aria-label="Delete photo">
					<i class="ti ti-trash" aria-hidden="true"></i>
				</button>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<?php if ( empty( $photos ) ) : ?>
			<p class="gallery-empty">No photos yet.</p>
		<?php endif; ?>
	</div>

	<?php if ( $can_upload ) : ?>
	<label class="btn btn-ghost cb-upload-btn" data-trip-id="<?php echo esc_attr( $trip_id ); ?>">
		<i class="ti ti-upload" aria-hidden="true"></i> Upload a photo
		<input type="file" accept="image/*" class="cb-upload-input" hidden>
	</label>
	<?php endif; ?>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   3. Shortcode: [cb_gate_gallery]
   ========================================================================== */
add_shortcode( 'cb_gate_gallery', function () {

	if ( ! is_user_logged_in() ) {
		return '<p class="cb-empty">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to see photos.</p>';
	}

	$user_id = get_current_user_id();

	$trips = get_posts( array(
		'post_type'   => 'cb_trip',
		'numberposts' => -1,
		'meta_query'  => array(
			array( 'key' => 'cb_status', 'value' => array( 'active', 'accepted', 'completed' ), 'compare' => 'IN' ),
		),
	) );

	$my_trips     = array();
	$public_trips = array();

	foreach ( $trips as $trip ) {
		$on_roster = in_array( $user_id, cb_trip_get_roster( $trip->ID ), true );
		$privacy   = get_post_meta( $trip->ID, 'cb_gallery_privacy', true ) ?: 'public';

		if ( $on_roster ) {
			$my_trips[] = $trip;
		} elseif ( $privacy === 'public' ) {
			$public_trips[] = $trip;
		}
	}

	ob_start();

	if ( ! empty( $my_trips ) ) {
		echo '<h3 class="gallery-section-title">Your Trips</h3>';
		foreach ( $my_trips as $trip ) {
			echo '<div class="gallery-section">';
			echo '<h4 class="gallery-trip-title">' . esc_html( get_the_title( $trip ) ) . '</h4>';
			echo cb_render_photo_grid( $trip->ID, true );
			echo '</div>';
		}
	}

	if ( ! empty( $public_trips ) ) {
		echo '<h3 class="gallery-section-title">Other Trips</h3>';
		foreach ( $public_trips as $trip ) {
			echo '<div class="gallery-section">';
			echo '<h4 class="gallery-trip-title">' . esc_html( get_the_title( $trip ) ) . '</h4>';
			echo cb_render_photo_grid( $trip->ID, false );
			echo '</div>';
		}
	}

	if ( empty( $my_trips ) && empty( $public_trips ) ) {
		echo '<p class="cb-empty">No photo galleries available yet.</p>';
	}

	echo '<div id="cb-lightbox" class="cb-lightbox"><button class="cb-lightbox-close" aria-label="Close"><i class="ti ti-x" aria-hidden="true"></i></button><img src="" alt=""></div>';

	return ob_get_clean();
} );

/* ==========================================================================
   4. Front-end JS
   ========================================================================== */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_script( 'cb-gate08', content_url( 'uploads/checkedbags/js/gate08.js' ), array(), '1.0.0', true );
	wp_localize_script( 'cb-gate08', 'cbGate08', array(
		'restUrl' => esc_url_raw( rest_url( 'cb/v1/' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
	) );
} );
