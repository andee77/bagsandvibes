<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Gate 07: All Planned Vacations
 * Description: Front-end for Gate 07 — trip listing shortcode, single trip
 *              detail content, and the "I'm in" REST endpoint wired to the
 *              roster helpers in checkedbags-trips.php. Deploys independently
 *              of that file; only depends on the cb_trip post type existing.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-gate07.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Extra meta: packing notes — free text, admin fills in per trip.
   ========================================================================== */
add_action( 'init', function () {
	register_post_meta( 'cb_trip', 'cb_packing_notes', array(
		'type'              => 'string',
		'single'            => true,
		'default'           => '',
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_textarea_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );
} );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cb_gate07_packing', 'Gate 07 — Packing Notes', 'cb_render_packing_meta_box', 'cb_trip', 'normal', 'default' );
} );

function cb_render_packing_meta_box( $post ) {
	wp_nonce_field( 'cb_gate07_save', 'cb_gate07_nonce' );
	$notes = get_post_meta( $post->ID, 'cb_packing_notes', true );
	?>
	<textarea name="cb_packing_notes" rows="4" style="width:100%;" placeholder="What to pack for this trip..."><?php echo esc_textarea( $notes ); ?></textarea>
	<?php
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cb_gate07_nonce'] ) || ! wp_verify_nonce( $_POST['cb_gate07_nonce'], 'cb_gate07_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['cb_packing_notes'] ) ) {
		update_post_meta( $post_id, 'cb_packing_notes', sanitize_textarea_field( wp_unslash( $_POST['cb_packing_notes'] ) ) );
	}
} );

/* ==========================================================================
   2. Trip type -> icon mapping, shared by list + detail rendering.
   ========================================================================== */
function cb_trip_type_icon( $term_slug ) {
	$map = array(
		'cruise'      => 'ti-anchor',
		'destination' => 'ti-map-pin',
		'flight'      => 'ti-plane',
		'train'       => 'ti-train',
		'resort'      => 'ti-pool',
		'retreat'     => 'ti-yoga',
		'other'       => 'ti-compass',
	);
	return isset( $map[ $term_slug ] ) ? $map[ $term_slug ] : 'ti-compass';
}

// Default photo per trip type, used when a trip has no featured image set.
// To change a photo later, just update the URL below and redeploy this file.
function cb_trip_type_photo( $term_slug ) {
	$map = array(
		'cruise'      => 'https://bagsandvibes.com/wp-content/uploads/2026/07/2-Ship-Porthole-Red-Room-scaled.png',
		'destination' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/staning-on-cliff-over-water-morning-scaled.jpg',
		'flight'      => 'https://bagsandvibes.com/wp-content/uploads/2026/07/jet-inside-scaled.jpg',
		'train'       => 'https://bagsandvibes.com/wp-content/uploads/2026/07/Train-Ride.avif',
		'resort'      => 'https://bagsandvibes.com/wp-content/uploads/2026/07/feet-in-the-pool-scaled.jpg',
		'other'       => 'https://bagsandvibes.com/wp-content/uploads/2026/07/river-fall-leaves-scaled.jpg',
	);
	return isset( $map[ $term_slug ] ) ? $map[ $term_slug ] : $map['other'];
}

function cb_format_date_range( $start, $end ) {
	if ( ! $start ) {
		return 'Dates TBD';
	}
	$start_ts = strtotime( $start );
	$end_ts   = $end ? strtotime( $end ) : null;
	if ( ! $end_ts ) {
		return date_i18n( 'M j, Y', $start_ts );
	}
	if ( date_i18n( 'M', $start_ts ) === date_i18n( 'M', $end_ts ) ) {
		return date_i18n( 'M j', $start_ts ) . '–' . date_i18n( 'j, Y', $end_ts );
	}
	return date_i18n( 'M j', $start_ts ) . ' – ' . date_i18n( 'M j, Y', $end_ts );
}

/* ==========================================================================
   3. REST endpoint — join a trip. Calls the roster helper from
      checkedbags-trips.php; requires that file to already be active.
   ========================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'cb/v1', '/trips/(?P<id>\d+)/join', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function ( $request ) {
			$trip_id = (int) $request['id'];
			$trip    = get_post( $trip_id );

			if ( ! $trip || $trip->post_type !== 'cb_trip' ) {
				return new WP_Error( 'cb_not_found', 'Trip not found.', array( 'status' => 404 ) );
			}

			$result = cb_trip_add_member( $trip_id, get_current_user_id() );

			if ( is_wp_error( $result ) ) {
				return new WP_Error( $result->get_error_code(), $result->get_error_message(), array( 'status' => 409 ) );
			}

			return array(
				'joined'          => true,
				'spots_remaining' => cb_trip_spots_remaining( $trip_id ),
				'roster_count'    => count( cb_trip_get_roster( $trip_id ) ),
			);
		},
	) );
} );

/* ==========================================================================
   4. Front-end JS localization — the script file itself was already
      deployed separately as uploads/checkedbags/js/gate07.js
   ========================================================================== */
add_action( 'wp_enqueue_scripts', function () {
	$cb_gate07_js_path = WP_CONTENT_DIR . '/uploads/checkedbags/js/gate07.js';
	$cb_gate07_js_ver  = file_exists( $cb_gate07_js_path ) ? filemtime( $cb_gate07_js_path ) : '1.0.0';
	wp_enqueue_script( 'cb-gate07', content_url( 'uploads/checkedbags/js/gate07.js' ), array(), $cb_gate07_js_ver, true );
	wp_localize_script( 'cb-gate07', 'cbGate07', array(
		'restUrl'  => esc_url_raw( rest_url( 'cb/v1/' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
		'loggedIn' => is_user_logged_in(),
		'loginUrl' => wp_login_url( get_permalink() ),
	) );
} );

/* ==========================================================================
   5. Shortcode: [cb_gate_vacations] — the trip card grid for Gate 07.
   ========================================================================== */
add_shortcode( 'cb_gate_vacations', function () {

	if ( ! is_user_logged_in() ) {
		return '<p class="cb-empty">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to see planned vacations.</p>';
	}

	$current_user_id = get_current_user_id();

	// Private trips (cb_visibility) are invite-only by design -- they must not
	// be discoverable or joinable through open browsing here, same as they're
	// already excluded from the public ?trip=CODE join path (Phase 2). A
	// roster member who's already on a Private trip still sees/manages it via
	// their dashboard's "Your Trips" section instead.
	//
	// The nested OR/NOT EXISTS below fixes a real bug found while building
	// Phase 11: a trip whose cb_visibility row was never explicitly saved
	// has no meta row at all, and a plain != 'private' comparison silently
	// excludes it (SQL NULL != 'private' isn't true) even though
	// register_post_meta()'s 'public' default makes get_post_meta() report
	// it as public everywhere else -- confirmed directly against the live
	// DB, same root cause as cbv_resolve_trip_code() in
	// checkedbags-trip-invites.php.
	$trips = get_posts( array(
		'post_type'   => 'cb_trip',
		'numberposts' => -1,
		'meta_query'  => array(
			array( 'key' => 'cb_status', 'value' => 'active' ),
			array(
				'relation' => 'OR',
				array( 'key' => 'cb_visibility', 'compare' => 'NOT EXISTS' ),
				array( 'key' => 'cb_visibility', 'value' => 'private', 'compare' => '!=' ),
			),
		),
	) );

	if ( empty( $trips ) ) {
		return '<p class="cb-empty">No trips are open for sign-up right now — check back soon.</p>';
	}

	ob_start();
	?>
	<p class="cb-gate-vacations-hint">Click &#8220;I&#8217;m In&#8221; on a trip to see full details, itinerary, and pricing.</p>
	<div class="trip-grid">
		<?php foreach ( $trips as $trip ) :
			$terms      = get_the_terms( $trip->ID, 'cb_trip_type' );
			$type_label = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : 'Trip';
			$type_slug  = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : 'other';
			$icon       = cb_trip_type_icon( $type_slug );

			$start      = get_post_meta( $trip->ID, 'cb_start_date', true );
			$end        = get_post_meta( $trip->ID, 'cb_end_date', true );
			$price      = (float) get_post_meta( $trip->ID, 'cb_price', true );
			$price_range = cb_trip_get_price_range( $trip->ID );
			$spots      = cb_trip_spots_remaining( $trip->ID );
			$roster     = cb_trip_get_roster( $trip->ID );
			$already_in = in_array( $current_user_id, $roster, true );
			$is_full    = ( $spots !== null && $spots <= 0 );
			?>
			<div class="trip-card">
				<span class="trip-card-type"><i class="ti <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></i> <?php echo esc_html( $type_label ); ?></span>
				<h3 class="trip-card-title"><?php echo esc_html( get_the_title( $trip ) ); ?></h3>
				<p class="trip-card-dates"><?php echo esc_html( cb_format_date_range( $start, $end ) ); ?></p>
				<p class="trip-card-price">
					<?php if ( $price_range && $price_range['low'] < $price_range['high'] ) : ?>
						$<?php echo esc_html( number_format_i18n( $price_range['low'] ) ); ?>&#8211;$<?php echo esc_html( number_format_i18n( $price_range['high'] ) ); ?> / person
					<?php elseif ( $price_range ) : ?>
						$<?php echo esc_html( number_format_i18n( $price_range['low'] ) ); ?> / person
					<?php else : ?>
						$<?php echo esc_html( number_format_i18n( $price ) ); ?> / person
					<?php endif; ?>
				</p>
				<p class="trip-card-spots">
					<?php echo $spots === null ? 'Open' : esc_html( $spots ) . ' spot' . ( $spots === 1 ? '' : 's' ) . ' left'; ?>
				</p>
				<div class="trip-card-actions">
					<a href="<?php echo esc_url( get_permalink( $trip ) ); ?>" class="btn btn-ghost">Details</a>
					<?php if ( $already_in ) : ?>
						<button class="btn btn-ticket" disabled>You're in <i class="ti ti-check" aria-hidden="true"></i></button>
					<?php elseif ( $is_full ) : ?>
						<button class="btn btn-ghost" disabled>Full</button>
					<?php else : ?>
						<button class="btn btn-ticket cb-join-btn" data-trip-id="<?php echo esc_attr( $trip->ID ); ?>">I'm in</button>
					<?php endif; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
} );

/* ==========================================================================
   6. Single trip detail — appended to the_content on singular cb_trip pages.
   ========================================================================== */
add_filter( 'the_content', function ( $content ) {

	if ( ! is_singular( 'cb_trip' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	if ( ! is_user_logged_in() ) {
		return $content . '<p class="cb-empty">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to see trip details.</p>';
	}

	global $post;
	$viewer_id = get_current_user_id();

	// Access gate (Phase 4 of the trip-invite build): roster membership,
	// then this trip's own agreement — being logged in alone used to be
	// enough to see any trip's full roster/packing notes/request details,
	// regardless of whether the viewer was actually on that trip.
	if ( ! in_array( $viewer_id, cb_trip_get_roster( $post->ID ), true ) ) {
		return $content . '<p class="cb-empty">You don&#8217;t have access to this trip&#8217;s details yet — join it from <a href="' . esc_url( home_url( '/gate-07-pre-planned-vacations/' ) ) . '">All Planned Vacations</a> first.</p>';
	}

	// Itinerary PDF (Phase 6): deliberately available to any roster member
	// regardless of agreement acceptance -- per spec §6.1 it's meant to help
	// someone still deciding whether to commit, so it must be visible
	// *before* that acceptance step, not gated behind it. No PDF attached =
	// simply no button, not a broken link.
	$pdf_html = '';
	if ( function_exists( 'cbv_get_trip_itinerary_pdf_url' ) ) {
		$pdf_url = cbv_get_trip_itinerary_pdf_url( $post->ID );
		if ( $pdf_url ) {
			$pdf_html = '<div class="trip-detail-section"><a class="btn btn-ticket" href="' . esc_url( $pdf_url ) . '" target="_blank" rel="noopener">Download Full Itinerary PDF <i class="ti ti-download" aria-hidden="true"></i></a></div>';
		}
	}

	// Per-Traveler Trip Intake (Phase 8c): same reasoning as the PDF above --
	// roster membership is the gate, not agreement re-acceptance. Seat/cabin
	// preference and the insurance decision aren't a legal-terms concern.
	$intake_html = function_exists( 'cbv_render_traveler_intake_form' ) ? cbv_render_traveler_intake_form( $post->ID ) : '';

	if ( cbv_user_needs_trip_agreement_reaccept( $viewer_id, $post->ID ) ) {
		return $content . $pdf_html . $intake_html . cbv_render_trip_agreement_prompt( $post->ID );
	}

	$terms      = get_the_terms( $post->ID, 'cb_trip_type' );
	$type_label = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : 'Trip';
	$packing    = get_post_meta( $post->ID, 'cb_packing_notes', true );
	$addendum   = get_post_meta( $post->ID, 'cb_rules_addendum', true );
	$roster     = cb_trip_get_roster( $post->ID );
	$min_ok     = cb_trip_meets_minimum_group_size( $post->ID );

	$price    = (float) get_post_meta( $post->ID, 'cb_price', true );
	$price_range = cb_trip_get_price_range( $post->ID );
	$start    = get_post_meta( $post->ID, 'cb_start_date', true );
	$end      = get_post_meta( $post->ID, 'cb_end_date', true );
	$spots    = cb_trip_spots_remaining( $post->ID );
	$source   = get_post_meta( $post->ID, 'cb_source', true );
	$when     = get_post_meta( $post->ID, 'cb_when_notes', true );
	$duration = get_post_meta( $post->ID, 'cb_duration_notes', true );

	$has_request_details = function_exists( 'cbv_get_trip_request_field' )
		&& ( cbv_get_trip_request_field( $post->ID, 'organizer_name' ) || cbv_get_trip_request_field( $post->ID, 'destination_pref' ) || get_post_meta( $post->ID, 'cb_request_details', true ) );

	$cover_url = function_exists( 'cbv_get_trip_cover_photo_url' ) ? cbv_get_trip_cover_photo_url( $post->ID, 'large' ) : '';

	ob_start();
	?>
	<div class="trip-detail">
		<?php if ( $cover_url ) : ?>
			<div class="trip-detail-cover" style="background-image:url('<?php echo esc_url( $cover_url ); ?>');"></div>
		<?php endif; ?>
		<p class="trip-detail-type"><?php echo esc_html( $type_label ); ?> trip</p>

		<div class="trip-detail-summary">
			<?php if ( $price_range && $price_range['low'] < $price_range['high'] ) : ?>
				<span class="trip-detail-stat"><strong>$<?php echo esc_html( number_format( $price_range['low'] ) ); ?>&#8211;$<?php echo esc_html( number_format( $price_range['high'] ) ); ?></strong> / person</span>
			<?php elseif ( $price_range ) : ?>
				<span class="trip-detail-stat"><strong>$<?php echo esc_html( number_format( $price_range['low'] ) ); ?></strong> / person</span>
			<?php elseif ( $price > 0 ) : ?>
				<span class="trip-detail-stat"><strong>$<?php echo esc_html( number_format( $price ) ); ?></strong> / person</span>
			<?php endif; ?>
			<?php if ( $start ) : ?>
				<span class="trip-detail-stat"><?php echo esc_html( cb_format_date_range( $start, $end ) ); ?></span>
			<?php elseif ( $when ) : ?>
				<span class="trip-detail-stat"><?php echo esc_html( $when ); ?></span>
			<?php endif; ?>
			<?php if ( $duration ) : ?>
				<span class="trip-detail-stat"><?php echo esc_html( $duration ); ?></span>
			<?php endif; ?>
			<?php if ( $spots !== null ) : ?>
				<span class="trip-detail-stat"><?php echo esc_html( $spots ); ?> spot<?php echo $spots === 1 ? '' : 's'; ?> left</span>
			<?php endif; ?>
		</div>

		<?php if ( $source === 'member_built' && $has_request_details ) : ?>
		<div class="trip-detail-section trip-detail-request">
			<h3>Your Request Details</h3>
			<ul class="request-detail-list">
				<?php
				$f    = function ( $key ) use ( $post ) { return cbv_get_trip_request_field( $post->ID, $key ); };
				$list = function ( $key ) use ( $post ) { return implode( ', ', (array) cbv_get_trip_request_field( $post->ID, $key, array() ) ); };

				$request_rows = array(
					'Group dynamic'          => $f( 'group_dynamic' ),
					'Rooming preference'     => $f( 'rooming' ),
					'Trip category'          => $list( 'trip_category' ),
					'Trip elements'          => $list( 'transport_modes' ),
					'Departure city'         => $f( 'origin_city' ),
					'Budget target'          => $f( 'budget_tier' ),
					'Accommodation'          => $f( 'accommodation_type' ),
					'Pace'                   => $f( 'pace' ),
					'Occasion'               => $f( 'occasion' ),
					'Must-haves'             => $f( 'must_haves' ),
					'Dietary'                => $f( 'dietary' ),
					'Mobility/access'        => $f( 'mobility' ),
					'Airline preference'     => $f( 'airline_preference' ),
					'Seat preference'        => $list( 'seat_preference' ),
					'Cruise preferences'     => $f( 'cruise_preferences' ),
					'Cruise itinerary'       => $f( 'cruise_itinerary' ),
					'Cruise length'          => $f( 'cruise_length' ),
					'Cruise cabin class'     => $f( 'cruise_cabin_class' ),
					'Beverage plan'          => $f( 'beverage_plan' ),
					'Hotel # of nights'      => $f( 'hotel_nights' ),
					'Hotel preferences'      => $f( 'hotel_preferences' ),
					'Hotel room type'        => $list( 'hotel_room_type' ),
					'Hotel features'         => $list( 'hotel_features' ),
					'Car preferences'        => $f( 'car_preferences' ),
					'Car category'           => $list( 'car_category' ),
					'Package tour countries' => $f( 'package_countries' ),
					'Package tour style'     => $list( 'package_style' ),
				);
				foreach ( $request_rows as $label => $value ) :
					if ( empty( $value ) ) { continue; }
					?>
					<li><strong><?php echo esc_html( $label ); ?>:</strong> <?php echo esc_html( $value ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php endif; ?>

		<?php if ( $packing ) : ?>
		<div class="trip-detail-section">
			<h3>Packing notes</h3>
			<p><?php echo nl2br( esc_html( $packing ) ); ?></p>
		</div>
		<?php endif; ?>

		<?php if ( $addendum ) : ?>
		<div class="trip-detail-section trip-detail-rules">
			<h3>Rules for this trip</h3>
			<p><?php echo nl2br( esc_html( $addendum ) ); ?></p>
		</div>
		<?php endif; ?>

		<div class="trip-detail-section">
			<h3>Who's going (<?php echo count( $roster ); ?>)</h3>
			<?php if ( empty( $roster ) ) : ?>
				<p>Be the first to join this trip.</p>
			<?php else : ?>
				<ul class="trip-roster-list">
					<?php foreach ( $roster as $uid ) : $u = get_userdata( $uid ); if ( ! $u ) { continue; } ?>
						<?php /* Anyone who can see this page is already on this trip's roster (cbv_user_can_view_trip() enforces that), so they share this exact trip with every other name listed here -- cb_users_share_any_trip() is always true between them, meaning every link below always resolves to a viewable profile. No conditional check needed. */ ?>
						<li><a href="<?php echo esc_url( home_url( '/members/' . $u->user_nicename . '/' ) ); ?>"><?php echo esc_html( $u->display_name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( ! $min_ok ) : ?>
				<p class="trip-detail-min-notice">This trip needs a few more people before it's confirmed.</p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return $content . $pdf_html . $intake_html . ob_get_clean();
}, 20 );
