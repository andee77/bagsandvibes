<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Landing Redesign Hero
 * Description: Step 4 of the public trip landing redesign: the hero (looping
 *              video or photo, title, lede) and the boarding pass (route
 *              codes, dates, vessel, "claim your seat" stub), plus the trip
 *              edit-screen box that feeds them and the pure helpers behind
 *              them. Only the new design calls any of this (preview with
 *              ?preview=new, or live per trip once switched on in
 *              checkedbags-lp-core.php); the current public design is
 *              untouched.
 *
 *              Fields (trip edit screen, "Hero & boarding pass"): hero video,
 *              optional smaller (720p) video, poster image, a focus point
 *              for the picture, and the vessel / venue name (also the
 *              {vessel} token). Port codes live on the itinerary rows
 *              ("Code" column, saved by checkedbags-trips.php).
 *
 *              Media rule: Virgin images and video are used UNALTERED. The
 *              frame may crop the edges for layout, but must never hide
 *              Virgin's logo/branding or change what the picture shows; the
 *              focus point is how a picture whose subject is not centred is
 *              kept in view (see docs/media-sources.md).
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-hero.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Meta. All presentation-only; nothing here is read by the current design.
   ========================================================================== */
add_action( 'init', function () {
	$auth = function () {
		return current_user_can( 'edit_posts' );
	};
	foreach ( array( 'cbv_lp_hero_video', 'cbv_lp_hero_video_small', 'cbv_lp_hero_poster' ) as $key ) {
		register_post_meta( 'cb_trip', $key, array(
			'type'          => 'integer',
			'single'        => true,
			'default'       => 0,
			'show_in_rest'  => false,
			'auth_callback' => $auth,
		) );
	}
	foreach ( array( 'cbv_lp_hero_focus', 'cbv_lp_venue_name' ) as $key ) {
		register_post_meta( 'cb_trip', $key, array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => false,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => $auth,
		) );
	}
} );

/* ==========================================================================
   2. Small pure helpers (no globals, no writes), tested directly.
   ========================================================================== */

/** A port / stop code: letters and digits only, upper case, at most 5 characters. */
function cbv_lp_clean_stop_code( $value ) {
	$value = is_scalar( $value ) ? strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $value ) ) : '';
	return substr( $value, 0, 5 );
}

/**
 * The picture focus points an admin can choose (stored as the key). Used for
 * the hero now, and for each gallery image later.
 */
function cbv_lp_focus_options() {
	return array(
		''             => 'Centre (default)',
		'top'          => 'Top',
		'bottom'       => 'Bottom',
		'left'         => 'Left',
		'right'        => 'Right',
		'top-left'     => 'Top left',
		'top-right'    => 'Top right',
		'bottom-left'  => 'Bottom left',
		'bottom-right' => 'Bottom right',
	);
}

/** Stored focus key -> a safe CSS object-position value (unknown keys mean centre). */
function cbv_lp_focus_position( $key ) {
	$map = array(
		'top'          => 'center top',
		'bottom'       => 'center bottom',
		'left'         => 'left center',
		'right'        => 'right center',
		'top-left'     => 'left top',
		'top-right'    => 'right top',
		'bottom-left'  => 'left bottom',
		'bottom-right' => 'right bottom',
	);
	return is_string( $key ) && isset( $map[ $key ] ) ? $map[ $key ] : 'center center';
}

/** An attachment id only if it really is an attachment of that kind ('video' or 'image'); otherwise 0. */
function cbv_lp_clean_attachment_id( $value, $kind ) {
	$id = is_scalar( $value ) ? absint( $value ) : 0;
	if ( ! $id ) {
		return 0;
	}
	$post = get_post( $id );
	if ( ! $post || 'attachment' !== $post->post_type ) {
		return 0;
	}
	return 0 === strpos( (string) $post->post_mime_type, $kind . '/' ) ? $id : 0;
}

/** Posted hero box values -> what gets stored. */
function cbv_lp_sanitize_hero( $raw ) {
	$raw   = is_array( $raw ) ? $raw : array();
	$focus = isset( $raw['focus'] ) && is_scalar( $raw['focus'] ) ? sanitize_key( (string) $raw['focus'] ) : '';
	if ( ! isset( cbv_lp_focus_options()[ $focus ] ) ) {
		$focus = '';
	}
	$venue = isset( $raw['venue'] ) && is_scalar( $raw['venue'] ) ? sanitize_text_field( (string) $raw['venue'] ) : '';
	$venue = function_exists( 'mb_substr' ) ? mb_substr( $venue, 0, 60 ) : substr( $venue, 0, 60 );

	return array(
		'video'       => cbv_lp_clean_attachment_id( $raw['video'] ?? 0, 'video' ),
		'video_small' => cbv_lp_clean_attachment_id( $raw['video_small'] ?? 0, 'video' ),
		'poster'      => cbv_lp_clean_attachment_id( $raw['poster'] ?? 0, 'image' ),
		'focus'       => $focus,
		'venue'       => $venue,
	);
}

/**
 * FROM / VIA / TO for the boarding pass, from the itinerary rows.
 *   - At-sea rows and rows with no port are ignored.
 *   - FROM is the first remaining stop.
 *   - TO is the last stop that is not the same place as FROM (so a round
 *     trip Miami > Puerto Plata > Bimini > Miami reads Miami to Bimini).
 *   - VIA is each different stop in between, in order (at most 3).
 *   - A stop's identity is its code when it has one, otherwise its port name.
 *   - A code typed on ANY row for a port is used for every row with that same
 *     port name (itineraries list a port twice, Arrival and Departure, and the
 *     return to the start port again), so the code only has to be entered once
 *     per port and the final "Miami" is never mistaken for a different place.
 * Returns null when there is no route to show (fewer than two different stops).
 * Each stop is array( 'code' => 'MIA', 'name' => 'Miami, USA', 'label' => 'MIA' ).
 */
function cbv_lp_route_endpoints( $itinerary ) {
	$rows  = array();
	$codes = array(); // lower-case port name => first code typed for that port
	foreach ( (array) $itinerary as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$port = trim( (string) ( $row['port'] ?? '' ) );
		if ( '' === $port || 'at sea' === strtolower( trim( (string) ( $row['description'] ?? '' ) ) ) || 'at sea' === strtolower( $port ) ) {
			continue;
		}
		$key  = strtolower( $port );
		$code = cbv_lp_clean_stop_code( $row['stop_code'] ?? '' );
		if ( '' !== $code && ! isset( $codes[ $key ] ) ) {
			$codes[ $key ] = $code;
		}
		$rows[] = array( $row, $port, $key, $code );
	}

	$stops = array();
	foreach ( $rows as $r ) {
		list( $row, $port, $key, $code ) = $r;
		if ( '' === $code && isset( $codes[ $key ] ) ) {
			$code = $codes[ $key ];
		}
		$country = trim( (string) ( $row['country'] ?? '' ) );
		$stops[] = array(
			'id'    => '' !== $code ? $code : $key,
			'code'  => $code,
			'name'  => '' !== $country ? $port . ', ' . $country : $port,
			'label' => '' !== $code ? $code : $port,
		);
	}
	if ( count( $stops ) < 2 ) {
		return null;
	}

	$from   = $stops[0];
	$to_idx = -1;
	foreach ( $stops as $i => $stop ) {
		if ( $i > 0 && $stop['id'] !== $from['id'] ) {
			$to_idx = $i;
		}
	}
	if ( $to_idx < 0 ) {
		return null;
	}
	$to  = $stops[ $to_idx ];
	$via = array();
	$seen = array( $from['id'] => true, $to['id'] => true );
	for ( $i = 1; $i < $to_idx; $i++ ) {
		if ( ! isset( $seen[ $stops[ $i ]['id'] ] ) ) {
			$seen[ $stops[ $i ]['id'] ] = true;
			$via[]                      = $stops[ $i ];
		}
	}

	return array(
		'from' => $from,
		'to'   => $to,
		'via'  => array_slice( $via, 0, 3 ),
	);
}

/** "Oct 25, 2027" (empty when there is no usable date). */
function cbv_lp_pass_date( $ymd ) {
	$ts = $ymd ? strtotime( (string) $ymd ) : false;
	return $ts ? date_i18n( 'M j, Y', $ts ) : '';
}

/* ==========================================================================
   3. What the hero shows for a trip.
   ========================================================================== */
function cbv_lp_hero_data( $trip_id ) {
	$trip_id = (int) $trip_id;
	$labels  = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
	$title   = get_the_title( $trip_id );
	$tagline = (string) get_post_meta( $trip_id, 'cb_public_landing_tagline', true );
	$code    = trim( (string) get_post_meta( $trip_id, 'cb_trip_code', true ) );
	$start   = (string) get_post_meta( $trip_id, 'cb_start_date', true );
	$end     = (string) get_post_meta( $trip_id, 'cb_end_date', true );

	$video_id = (int) get_post_meta( $trip_id, 'cbv_lp_hero_video', true );
	$small_id = (int) get_post_meta( $trip_id, 'cbv_lp_hero_video_small', true );
	$video    = $video_id ? (string) wp_get_attachment_url( $video_id ) : '';
	$small    = $small_id ? (string) wp_get_attachment_url( $small_id ) : '';
	if ( '' === $video && '' !== $small ) {
		$video = $small; // only the smaller file was chosen: use it for every screen
		$small = '';
	}

	// Still picture: poster, else the trip's cover photo, else the featured image, else none.
	$image_url = '';
	$image_alt = '';
	$poster_id = (int) get_post_meta( $trip_id, 'cbv_lp_hero_poster', true );
	if ( $poster_id ) {
		$image_url = (string) wp_get_attachment_image_url( $poster_id, 'full' );
	}
	if ( '' === $image_url ) {
		$image_url = function_exists( 'cbv_get_trip_cover_photo_url' ) ? (string) cbv_get_trip_cover_photo_url( $trip_id, 'full' ) : '';
		if ( '' === $image_url ) {
			$image_url = (string) get_the_post_thumbnail_url( $trip_id, 'full' );
		}
		$image_alt = '' !== $tagline ? $title . ' — ' . $tagline : $title;
	}

	$show_itinerary = (bool) get_post_meta( $trip_id, 'cb_public_landing_show_itinerary', true );
	$itinerary      = function_exists( 'cb_trip_get_itinerary' ) ? cb_trip_get_itinerary( $trip_id ) : array();

	$range   = '' !== $start ? ( function_exists( 'cb_format_date_range' ) ? cb_format_date_range( $start, $end ) : cbv_lp_pass_date( $start ) ) : '';
	$eyebrow = trim( ( $labels['eyebrow'] ?? '' ) . ( '' !== $range && ! empty( $labels['eyebrow'] ) ? ' · ' : '' ) . $range );

	return array(
		'title'       => $title,
		'tagline'     => $tagline,
		'eyebrow'     => $eyebrow,
		'code'        => $code,
		'image_url'   => $image_url,
		'image_alt'   => $image_alt,
		'video_url'   => $video,
		'video_small' => $small,
		'focus'       => cbv_lp_focus_position( (string) get_post_meta( $trip_id, 'cbv_lp_hero_focus', true ) ),
		'route'       => $show_itinerary ? cbv_lp_route_endpoints( $itinerary ) : null,
		'start'       => cbv_lp_pass_date( $start ),
		'end'         => cbv_lp_pass_date( $end ),
		'vessel'      => trim( (string) get_post_meta( $trip_id, 'cbv_lp_venue_name', true ) ),
		'pass_title'  => 'Boarding Pass · ' . ( $labels['party_one'] ?? 'Guest' ),
		'label_start' => $labels['pass_start'] ?? 'Departs',
		'label_end'   => $labels['pass_end'] ?? 'Returns',
		'label_place' => $labels['pass_place'] ?? 'Vessel',
		'cta_label'   => 'Claim your seat',
		'cta_url'     => '' !== $code ? home_url( '/join/?trip=' . rawurlencode( $code ) ) : home_url( '/join/' ),
	);
}

/* ==========================================================================
   4. Markup.
   ========================================================================== */
function cbv_lp_pass_stop_html( $label, $stop ) {
	$has_code = '' !== $stop['code'];
	ob_start();
	?>
	<div class="cbv-lp-pass-stop">
		<span class="cbv-lp-pass-tag"><?php echo esc_html( $label ); ?></span>
		<span class="<?php echo $has_code ? 'cbv-lp-pass-code' : 'cbv-lp-pass-code cbv-lp-pass-code--name'; ?>"><?php echo esc_html( $stop['label'] ); ?></span>
		<?php if ( $has_code ) : ?>
			<span class="cbv-lp-pass-place"><?php echo esc_html( $stop['name'] ); ?></span>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

function cbv_lp_render_hero( $trip_id ) {
	$d = cbv_lp_hero_data( $trip_id );

	$cells = array();
	if ( '' !== $d['start'] ) {
		$cells[] = array( $d['label_start'], $d['start'] );
	}
	if ( '' !== $d['end'] ) {
		$cells[] = array( $d['label_end'], $d['end'] );
	}
	if ( '' !== $d['vessel'] ) {
		$cells[] = array( $d['label_place'], $d['vessel'] );
	}

	$focus_style = 'object-position:' . $d['focus'] . ';';

	ob_start();
	?>
	<section class="cbv-lp-hero" id="hero" aria-labelledby="cbv-lp-title">
		<?php if ( '' !== $d['image_url'] ) : ?>
			<img class="cbv-lp-hero-media" src="<?php echo esc_url( $d['image_url'] ); ?>" alt="<?php echo esc_attr( $d['image_alt'] ); ?>" style="<?php echo esc_attr( $focus_style ); ?>" fetchpriority="high">
		<?php endif; ?>
		<?php if ( '' !== $d['video_url'] ) : ?>
			<video class="cbv-lp-hero-media cbv-lp-hero-video" muted loop playsinline preload="none" aria-hidden="true" tabindex="-1" hidden
				data-src="<?php echo esc_url( $d['video_url'] ); ?>"
				<?php echo '' !== $d['video_small'] ? 'data-src-small="' . esc_url( $d['video_small'] ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url above ?>
				style="<?php echo esc_attr( $focus_style ); ?>"></video>
		<?php endif; ?>
		<div class="cbv-lp-hero-scrim"></div>

		<div class="cbv-lp-hero-inner">
			<div class="cbv-lp-hero-copy">
				<?php if ( '' !== $d['eyebrow'] ) : ?>
					<p class="cbv-lp-eyebrow"><?php echo esc_html( $d['eyebrow'] ); ?></p>
				<?php endif; ?>
				<h1 class="cbv-lp-hero-title" id="cbv-lp-title"><?php echo esc_html( $d['title'] ); ?></h1>
				<?php if ( '' !== $d['tagline'] ) : ?>
					<p class="cbv-lp-lede"><?php echo esc_html( $d['tagline'] ); ?></p>
				<?php endif; ?>
			</div>

			<div class="cbv-lp-pass">
				<div class="cbv-lp-pass-main">
					<div class="cbv-lp-pass-top">
						<span><?php echo esc_html( $d['pass_title'] ); ?></span>
						<?php if ( '' !== $d['code'] ) : ?>
							<span class="cbv-lp-pass-chip"><?php echo esc_html( $d['code'] ); ?></span>
						<?php endif; ?>
					</div>

					<?php if ( $d['route'] ) : ?>
						<div class="cbv-lp-pass-route">
							<?php echo cbv_lp_pass_stop_html( 'FROM', $d['route']['from'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper ?>
							<div class="cbv-lp-pass-via">
								<?php if ( $d['route']['via'] ) : ?>
									<span class="cbv-lp-pass-tag">VIA <?php echo esc_html( implode( ' · ', wp_list_pluck( $d['route']['via'], 'label' ) ) ); ?></span>
								<?php endif; ?>
								<svg width="76" height="20" viewBox="0 0 76 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" focusable="false"><path d="M2 10h60" stroke-dasharray="4 4"></path><path d="M62 4l10 6-10 6"></path></svg>
							</div>
							<?php echo cbv_lp_pass_stop_html( 'TO', $d['route']['to'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper ?>
						</div>
					<?php endif; ?>

					<?php if ( $cells ) : ?>
						<dl class="cbv-lp-pass-facts" style="--cbv-lp-cols:<?php echo (int) count( $cells ); ?>">
							<?php foreach ( $cells as $cell ) : ?>
								<div>
									<dt><?php echo esc_html( strtoupper( $cell[0] ) ); ?></dt>
									<dd><?php echo esc_html( $cell[1] ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>
				</div>
				<div class="cbv-lp-pass-stub">
					<span class="cbv-lp-pass-tear" aria-hidden="true">TEAR HERE</span>
					<a class="cbv-lp-pass-cta" href="<?php echo esc_url( $d['cta_url'] ); ?>"><?php echo esc_html( $d['cta_label'] ); ?></a>
				</div>
			</div>
		</div>

		<?php if ( '' !== $d['video_url'] ) : ?>
			<button type="button" class="cbv-lp-video-toggle" hidden aria-pressed="false">Pause background video</button>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Step 4 scaffolding: today's landing content still renders under the new
 * hero until the real sections replace it, but its own hero (cover photo,
 * title, "Reserve Your Spot" badge) and "Departs from / Dates" strip are now
 * the new hero's job, so they are removed from the markup (a second <h1>
 * would otherwise remain on the page). If the markup can't be parsed, or
 * those blocks aren't found, the content is returned unchanged.
 */
function cbv_lp_strip_legacy_hero( $html ) {
	if ( '' === trim( (string) $html ) || ! class_exists( 'DOMDocument' ) ) {
		return $html;
	}
	$prev = libxml_use_internal_errors( true );
	$dom  = new DOMDocument();
	$ok   = $dom->loadHTML( '<?xml encoding="utf-8" ?><div id="cbv-lp-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	if ( ! $ok ) {
		return $html;
	}

	$xp      = new DOMXPath( $dom );
	$nodes   = $xp->query( '//div[contains(concat(" ", normalize-space(@class), " "), " cbv-landing-hero ") or contains(concat(" ", normalize-space(@class), " "), " cbv-landing-basics ")]' );
	$removed = 0;
	foreach ( iterator_to_array( $nodes ) as $node ) {
		if ( $node->parentNode ) {
			$node->parentNode->removeChild( $node );
			$removed++;
		}
	}
	if ( ! $removed ) {
		return $html;
	}

	$root = $dom->getElementById( 'cbv-lp-root' );
	if ( ! $root ) {
		$found = $xp->query( '//div[@id="cbv-lp-root"]' );
		$root  = $found->length ? $found->item( 0 ) : null;
	}
	if ( ! $root ) {
		return $html;
	}
	$out = '';
	foreach ( $root->childNodes as $child ) {
		$out .= $dom->saveHTML( $child );
	}
	return $out;
}

/* ==========================================================================
   5. Assets: the hero video script, only for the new experience.
   ========================================================================== */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'cbv_lp_is_new_experience' ) || ! cbv_lp_is_new_experience() ) {
		return;
	}
	$path = WP_CONTENT_DIR . '/uploads/checkedbags/js/trip-landing.js';
	wp_enqueue_script(
		'cbv-lp',
		content_url( 'uploads/checkedbags/js/trip-landing.js' ),
		array(),
		file_exists( $path ) ? filemtime( $path ) : '1.0.0',
		true
	);
}, 20 );

/* ==========================================================================
   6. Trip edit screen: the "Hero & boarding pass" box.
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cbv_lp_hero', 'Hero & boarding pass (new design)', 'cbv_lp_render_hero_box', 'cb_trip', 'normal', 'default' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'cb_trip' === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		wp_enqueue_media();
	}
} );

/** One media chooser: a hidden attachment id, the chosen file name, Choose and Remove buttons. */
function cbv_lp_media_field( $name, $id, $kind, $label, $help ) {
	$id       = (int) $id;
	$filename = $id ? wp_basename( (string) get_attached_file( $id ) ) : '';
	?>
	<div class="cbv-lp-media" data-kind="<?php echo esc_attr( $kind ); ?>" style="margin:0 0 14px;">
		<strong><?php echo esc_html( $label ); ?></strong><br>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo $id ? (int) $id : ''; ?>">
		<span class="cbv-lp-media-name"><?php echo '' !== $filename ? esc_html( $filename ) : ( $id ? esc_html( 'File #' . $id ) : '' ); ?></span>
		<button type="button" class="button cbv-lp-media-pick">Choose from Media Library</button>
		<button type="button" class="button-link cbv-lp-media-clear" style="color:#b32d2e;">Remove</button>
		<p class="description" style="margin:4px 0 0;"><?php echo esc_html( $help ); ?></p>
	</div>
	<?php
}

function cbv_lp_render_hero_box( $post ) {
	wp_nonce_field( 'cbv_lp_hero_save', 'cbv_lp_hero_nonce' );
	$focus = (string) get_post_meta( $post->ID, 'cbv_lp_hero_focus', true );
	$venue = (string) get_post_meta( $post->ID, 'cbv_lp_venue_name', true );
	?>
	<p class="description">Used only by the new landing design (preview with <code>?preview=new</code>). Virgin media must come from the First Mates marketing toolkit and be used unaltered (web compression only).</p>

	<?php
	cbv_lp_media_field( 'cbv_lp_hero_video', get_post_meta( $post->ID, 'cbv_lp_hero_video', true ), 'video', 'Hero video', 'A looping background video (MP4, no sound needed). Plays on larger screens only; phones and visitors who prefer reduced motion see the poster picture instead.' );
	cbv_lp_media_field( 'cbv_lp_hero_video_small', get_post_meta( $post->ID, 'cbv_lp_hero_video_small', true ), 'video', 'Smaller hero video (optional)', 'A 720p version for medium screens and slower connections. If you only choose this one, it is used for every screen.' );
	cbv_lp_media_field( 'cbv_lp_hero_poster', get_post_meta( $post->ID, 'cbv_lp_hero_poster', true ), 'image', 'Poster picture', 'Shown before the video loads, on phones, and if the video is off. If empty the trip\'s cover photo (or featured image) is used.' );
	?>

	<p>
		<label for="cbv_lp_hero_focus"><strong>Picture focus point</strong></label><br>
		<select name="cbv_lp_hero_focus" id="cbv_lp_hero_focus">
			<?php foreach ( cbv_lp_focus_options() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $focus, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p class="description">The hero fills a wide frame, so the edges of the picture can be cut off. If the main subject is not in the middle, choose where it is. A frame must never hide Virgin's logo or branding or change what the picture shows.</p>

	<p>
		<label for="cbv_lp_venue_name"><strong>Vessel / venue name</strong></label><br>
		<input type="text" name="cbv_lp_venue_name" id="cbv_lp_venue_name" value="<?php echo esc_attr( $venue ); ?>" maxlength="60" class="regular-text" placeholder="Valiant Lady">
	</p>
	<p class="description">Shown on the boarding pass (labelled Vessel, Property or Venue to suit the event type) and available as <code>{vessel}</code> in text. Port codes (MIA, BIM ...) for the pass are entered in the "Code" column of the Day-by-Day Itinerary.</p>
	<?php
}

add_action( 'admin_footer', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'cb_trip' !== $screen->post_type || 'post' !== $screen->base ) {
		return;
	}
	?>
	<script>
	(function () {
		document.addEventListener('click', function (e) {
			var pick = e.target.closest ? e.target.closest('.cbv-lp-media-pick') : null;
			var clear = e.target.closest ? e.target.closest('.cbv-lp-media-clear') : null;
			if (!pick && !clear) { return; }
			e.preventDefault();
			var wrap = (pick || clear).closest('.cbv-lp-media');
			var input = wrap.querySelector('input[type=hidden]');
			var name = wrap.querySelector('.cbv-lp-media-name');
			if (clear) { input.value = ''; name.textContent = ''; return; }
			if (!window.wp || !wp.media) { return; }
			var kind = wrap.getAttribute('data-kind');
			var frame = wp.media({ title: 'Choose ' + kind, library: { type: kind }, multiple: false, button: { text: 'Use this file' } });
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				input.value = a.id;
				name.textContent = a.filename || a.title || ('File #' + a.id);
			});
			frame.open();
		});
	})();
	</script>
	<?php
} );

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_lp_hero_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_lp_hero_nonce'], 'cbv_lp_hero_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$clean = cbv_lp_sanitize_hero( array(
		'video'       => wp_unslash( $_POST['cbv_lp_hero_video'] ?? 0 ),
		'video_small' => wp_unslash( $_POST['cbv_lp_hero_video_small'] ?? 0 ),
		'poster'      => wp_unslash( $_POST['cbv_lp_hero_poster'] ?? 0 ),
		'focus'       => wp_unslash( $_POST['cbv_lp_hero_focus'] ?? '' ),
		'venue'       => wp_unslash( $_POST['cbv_lp_venue_name'] ?? '' ),
	) );

	update_post_meta( $post_id, 'cbv_lp_hero_video', $clean['video'] );
	update_post_meta( $post_id, 'cbv_lp_hero_video_small', $clean['video_small'] );
	update_post_meta( $post_id, 'cbv_lp_hero_poster', $clean['poster'] );
	update_post_meta( $post_id, 'cbv_lp_hero_focus', $clean['focus'] );
	update_post_meta( $post_id, 'cbv_lp_venue_name', $clean['venue'] );
} );
