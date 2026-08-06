<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Gate 12: Vacation Requests
 * Description: Suggestion/idea board with voting, plus the full "Build Your
 *              Own Trip" custom group request form. Detailed intake answers
 *              (group leader info, destination/timing, transit, budget,
 *              style, activities, accessibility) are stored as one JSON
 *              blob in cb_request_details on the cb_trip post — core fields
 *              the rest of the site depends on (price, dates, capacity,
 *              type, roster, status) stay on their existing top-level meta
 *              keys from checkedbags-trips.php, untouched.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-gate12.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. cb_suggestion post type — the idea board
   ========================================================================== */
add_action( 'init', function () {
	register_post_type( 'cb_suggestion', array(
		'label'        => 'Trip Suggestions',
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-lightbulb',
		'supports'     => array( 'title', 'editor', 'author' ),
		'show_in_rest' => true,
	) );

	register_post_meta( 'cb_suggestion', 'cb_suggestion_votes', array(
		'type'         => 'array',
		'single'       => true,
		'default'      => array(),
		'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ),
		'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
	) );
} );

/* ==========================================================================
   2. cb_trip extra meta

   cb_request_details (the old single JSON blob) is kept registered and
   still READ everywhere below, but is no longer WRITTEN to by new
   submissions (see cb_save_trip_request_fields()) -- it's now purely a
   fallback so trips created before Phase 8 still display correctly. Every
   field it used to hold is now its own cb_req_{key} post meta key instead,
   matching the Customer Information Form's own section layout (Air
   Travel / Cruise Vacation / Hotel and Resort Vacation / Car Rental /
   Package Tour) so Phase 9's spreadsheet export can read plain columns
   instead of parsing JSON.
   ========================================================================== */
add_action( 'init', function () {
	foreach ( array( 'cb_when_notes', 'cb_duration_notes' ) as $key ) {
		register_post_meta( 'cb_trip', $key, array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function () { return current_user_can( 'edit_posts' ); },
		) );
	}

	register_post_meta( 'cb_trip', 'cb_request_details', array(
		'type'              => 'string',
		'single'            => true,
		'default'           => '',
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () { return current_user_can( 'edit_posts' ); },
	) );

	foreach ( cbv_trip_request_field_defs() as $key => $def ) {
		if ( 'array' === $def['type'] ) {
			register_post_meta( 'cb_trip', 'cb_req_' . $key, array(
				'type'          => 'array',
				'single'        => true,
				'default'       => array(),
				'show_in_rest'  => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ),
				'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
			) );
		} else {
			register_post_meta( 'cb_trip', 'cb_req_' . $key, array(
				'type'              => 'string',
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () { return current_user_can( 'edit_posts' ); },
			) );
		}
	}
} );

/**
 * Every individual Trip Request field, old and new, in one place -- both
 * the meta registration above and the save function below loop over this
 * instead of listing each key twice. Field names are unchanged from the
 * old cb_request_details JSON keys where they already existed, so
 * cbv_get_trip_request_field()'s fallback to that old blob just works.
 */
function cbv_trip_request_field_defs() {
	return array(
		// Group Leader / Group Size / Destination & Timing (already existed)
		'organizer_name'     => array( 'type' => 'text' ),
		'organizer_email'    => array( 'type' => 'email' ),
		'organizer_phone'    => array( 'type' => 'text' ),
		'organizer_role'     => array( 'type' => 'text' ),
		'decision_style'     => array( 'type' => 'text' ),
		'client_address'     => array( 'type' => 'textarea' ), // new -- CIF's "Address"
		'ages_0_17'          => array( 'type' => 'text' ),
		'ages_18_64'         => array( 'type' => 'text' ),
		'ages_65_plus'       => array( 'type' => 'text' ),
		'group_dynamic'      => array( 'type' => 'text' ),
		'rooming'            => array( 'type' => 'text' ),
		'destination_pref'   => array( 'type' => 'text' ),
		'date_flexibility'   => array( 'type' => 'text' ),
		'trip_category'      => array( 'type' => 'array' ),
		'transport_modes'    => array( 'type' => 'array' ),
		'origin_city'        => array( 'type' => 'text' ),
		'special_transit'    => array( 'type' => 'textarea' ),
		'budget_tier'        => array( 'type' => 'text' ),
		'payment_logistics'  => array( 'type' => 'text' ),
		'accommodation_type' => array( 'type' => 'text' ),
		'pace'               => array( 'type' => 'text' ),
		'occasion'           => array( 'type' => 'text' ),
		'must_haves'         => array( 'type' => 'textarea' ),
		'dietary'            => array( 'type' => 'textarea' ),
		'mobility'           => array( 'type' => 'textarea' ),
		'special_requests'   => array( 'type' => 'textarea' ),

		// Air Travel (CIF section) -- shown when "Flight" is checked
		'airline_preference' => array( 'type' => 'text' ),
		'seat_preference'    => array( 'type' => 'array' ),

		// Cruise Vacation (CIF section) -- shown when "Cruise" is checked
		'cruise_preferences'     => array( 'type' => 'text' ),
		'cruise_itinerary'       => array( 'type' => 'text' ),
		'cruise_length'          => array( 'type' => 'text' ),
		'pre_post_cruise_nights' => array( 'type' => 'text' ),
		'cruise_cabin_class'     => array( 'type' => 'text' ),
		'beverage_plan'          => array( 'type' => 'text' ),
		'beverage_plan_type'     => array( 'type' => 'text' ),

		// Hotel and Resort Vacation (CIF section) -- shown when "Hotel/Resort" is checked
		'hotel_nights'            => array( 'type' => 'text' ),
		'hotel_preferences'       => array( 'type' => 'text' ),
		'hotel_rooms_arrangement' => array( 'type' => 'text' ),
		'hotel_room_type'         => array( 'type' => 'array' ),
		'hotel_features'          => array( 'type' => 'array' ),
		'hotel_concierge_notes'   => array( 'type' => 'text' ),

		// Car Rental (CIF section) -- shown when "Car Rental" is checked
		'car_preferences' => array( 'type' => 'text' ),
		'car_addons'      => array( 'type' => 'text' ),
		'car_category'    => array( 'type' => 'array' ),

		// Package Tour (CIF section) -- shown when "Package Tour" is checked
		'package_countries'      => array( 'type' => 'text' ),
		'package_style'          => array( 'type' => 'array' ),
		'package_activity_level' => array( 'type' => 'text' ),
	);
}

/**
 * Reads one Trip Request field, falling back to the old cb_request_details
 * JSON blob for trips created before Phase 8 restructured this into
 * individual meta keys. Field names are identical between the two, by
 * design, so this fallback needs no key-renaming logic.
 */
function cbv_get_trip_request_field( $trip_id, $key, $default = '' ) {
	$value = get_post_meta( $trip_id, 'cb_req_' . $key, true );
	if ( '' !== $value && array() !== $value ) {
		return $value;
	}

	$legacy_raw = get_post_meta( $trip_id, 'cb_request_details', true );
	if ( ! $legacy_raw ) {
		return $default;
	}
	$legacy = json_decode( $legacy_raw, true );
	return ( is_array( $legacy ) && isset( $legacy[ $key ] ) ) ? $legacy[ $key ] : $default;
}

function cb_save_trip_request_fields( $trip_id, $body ) {
	foreach ( cbv_trip_request_field_defs() as $key => $def ) {
		if ( ! isset( $body[ $key ] ) ) {
			continue;
		}
		$raw = $body[ $key ];

		switch ( $def['type'] ) {
			case 'email':
				$value = sanitize_email( $raw );
				break;
			case 'textarea':
				$value = sanitize_textarea_field( $raw );
				break;
			case 'array':
				$value = is_array( $raw ) ? array_values( array_map( 'sanitize_text_field', $raw ) ) : array();
				break;
			default:
				$value = sanitize_text_field( $raw );
		}

		update_post_meta( $trip_id, 'cb_req_' . $key, $value );
	}
}

/* ==========================================================================
   3. Admin: formatted read-only view of the full intake
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cb_gate12_request', 'Gate 12 — Member Request Details', 'cb_render_request_meta_box', 'cb_trip', 'normal', 'default' );
} );

function cb_render_request_meta_box( $post ) {
	$f = function ( $key ) use ( $post ) { return cbv_get_trip_request_field( $post->ID, $key ); };
	$list = function ( $key ) use ( $post ) { return implode( ', ', (array) cbv_get_trip_request_field( $post->ID, $key, array() ) ); };

	$has_any = $f( 'organizer_name' ) || $f( 'destination_pref' ) || get_post_meta( $post->ID, 'cb_request_details', true );
	if ( ! $has_any ) {
		echo '<p><em>No custom request details on file for this trip (either a curated trip, or submitted before this field existed).</em></p>';
		return;
	}

	$rows = array(
		'Organizer'              => trim( $f( 'organizer_name' ) . ' — ' . $f( 'organizer_email' ) . ' — ' . $f( 'organizer_phone' ) ),
		'Organizer role'         => $f( 'organizer_role' ),
		'Decision style'         => $f( 'decision_style' ),
		'Address'                => $f( 'client_address' ),
		'Group breakdown'        => trim( ( $f( 'ages_0_17' ) ?: '0' ) . ' age 0–17, ' . ( $f( 'ages_18_64' ) ?: '0' ) . ' age 18–64, ' . ( $f( 'ages_65_plus' ) ?: '0' ) . ' age 65+' ),
		'Group dynamic'          => $f( 'group_dynamic' ),
		'Rooming preference'     => $f( 'rooming' ),
		'Destination pref.'      => $f( 'destination_pref' ),
		'Date flexibility'       => $f( 'date_flexibility' ),
		'Trip category'          => $list( 'trip_category' ),
		'Trip elements'          => $list( 'transport_modes' ),
		'Origin city(ies)'       => $f( 'origin_city' ),
		'Special transit'        => $f( 'special_transit' ),
		'Budget tier'            => $f( 'budget_tier' ),
		'Payment logistics'      => $f( 'payment_logistics' ),
		'Accommodation type'     => $f( 'accommodation_type' ),
		'Pace of travel'         => $f( 'pace' ),
		'Occasion'               => $f( 'occasion' ),
		'Must-have experiences'  => $f( 'must_haves' ),
		'Dietary restrictions'   => $f( 'dietary' ),
		'Mobility/accessibility' => $f( 'mobility' ),
		'Special requests'       => $f( 'special_requests' ),

		'— Air Travel —'         => '',
		'Airline preference'     => $f( 'airline_preference' ),
		'Seat preference'        => $list( 'seat_preference' ),

		'— Cruise Vacation —'    => '',
		'Cruise preferences'     => $f( 'cruise_preferences' ),
		'Cruise itinerary'       => $f( 'cruise_itinerary' ),
		'Cruise length'          => $f( 'cruise_length' ),
		'Pre/post cruise nights' => $f( 'pre_post_cruise_nights' ),
		'Cabin class'            => $f( 'cruise_cabin_class' ),
		'Beverage plan'          => $f( 'beverage_plan' ),
		'Beverage plan type'     => $f( 'beverage_plan_type' ),

		'— Hotel and Resort Vacation —' => '',
		'# of nights'            => $f( 'hotel_nights' ),
		'Hotel preferences'      => $f( 'hotel_preferences' ),
		'# of rooms/arrangement' => $f( 'hotel_rooms_arrangement' ),
		'Room type'              => $list( 'hotel_room_type' ),
		'Features'               => $list( 'hotel_features' ),
		'Concierge notes'        => $f( 'hotel_concierge_notes' ),

		'— Car Rental —'         => '',
		'Car preferences'        => $f( 'car_preferences' ),
		'Add-ons'                => $f( 'car_addons' ),
		'Car category'           => $list( 'car_category' ),

		'— Package Tour —'       => '',
		'Countries of interest'  => $f( 'package_countries' ),
		'Style'                  => $list( 'package_style' ),
		'Activity level'         => $f( 'package_activity_level' ),
	);
	?>
	<table class="widefat striped">
		<tbody>
			<?php foreach ( $rows as $label => $value ) :
				$is_heading = ( '' === $value && str_starts_with( $label, '—' ) );
				if ( empty( $value ) && ! $is_heading ) { continue; }
				?>
				<tr>
					<?php if ( $is_heading ) : ?>
						<td colspan="2"><strong><?php echo esc_html( $label ); ?></strong></td>
					<?php else : ?>
						<td style="width:220px;"><strong><?php echo esc_html( $label ); ?></strong></td>
						<td><?php echo esc_html( $value ); ?></td>
					<?php endif; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/* ==========================================================================
   4. REST endpoints
   ========================================================================== */
/**
 * Gate 12 (idea board, "Build Your Own Trip" requests, quote acceptance) is
 * Full-Member functionality -- a Trip Guest's access is scoped to the
 * specific trip(s) they were invited to, not the ability to propose or vote
 * on brand-new trips. Not being linked from the Guest dashboard isn't the
 * same as not being reachable, so every route here checks the role
 * directly rather than relying on is_user_logged_in() alone.
 */
function cbv_gate12_permission_check() {
	return is_user_logged_in() && cbv_user_is_full_member();
}

add_action( 'rest_api_init', function () {

	register_rest_route( 'cb/v1', '/suggestions', array(
		'methods'             => 'POST',
		'permission_callback' => 'cbv_gate12_permission_check',
		'callback'            => 'cb_create_suggestion',
	) );

	register_rest_route( 'cb/v1', '/suggestions/(?P<id>\d+)/vote', array(
		'methods'             => 'POST',
		'permission_callback' => 'cbv_gate12_permission_check',
		'callback'            => 'cb_toggle_suggestion_vote',
	) );

	register_rest_route( 'cb/v1', '/trip-requests', array(
		'methods'             => 'POST',
		'permission_callback' => 'cbv_gate12_permission_check',
		'callback'            => 'cb_create_trip_request',
	) );

	register_rest_route( 'cb/v1', '/trips/(?P<id>\d+)/accept-quote', array(
		'methods'             => 'POST',
		'permission_callback' => 'cbv_gate12_permission_check',
		'callback'            => 'cb_accept_trip_quote',
	) );

} );

function cb_create_suggestion( $request ) {
	$title = sanitize_text_field( $request->get_param( 'title' ) );
	$desc  = sanitize_textarea_field( $request->get_param( 'description' ) );

	if ( empty( $title ) ) {
		return new WP_Error( 'cb_missing_title', 'Please give your suggestion a name.', array( 'status' => 400 ) );
	}

	$post_id = wp_insert_post( array(
		'post_type'    => 'cb_suggestion',
		'post_title'   => $title,
		'post_content' => $desc,
		'post_status'  => 'publish',
		'post_author'  => get_current_user_id(),
	) );

	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'cb_create_failed', 'Could not save your suggestion.', array( 'status' => 500 ) );
	}

	return array( 'id' => $post_id, 'title' => $title );
}

function cb_toggle_suggestion_vote( $request ) {
	$id      = (int) $request['id'];
	$user_id = get_current_user_id();
	$post    = get_post( $id );

	if ( ! $post || $post->post_type !== 'cb_suggestion' ) {
		return new WP_Error( 'cb_not_found', 'Suggestion not found.', array( 'status' => 404 ) );
	}

	$votes = get_post_meta( $id, 'cb_suggestion_votes', true );
	$votes = is_array( $votes ) ? $votes : array();

	if ( in_array( $user_id, $votes, true ) ) {
		$votes = array_values( array_diff( $votes, array( $user_id ) ) );
		$voted = false;
	} else {
		$votes[] = $user_id;
		$voted   = true;
	}

	update_post_meta( $id, 'cb_suggestion_votes', $votes );

	return array( 'voted' => $voted, 'count' => count( $votes ) );
}

/**
 * Maps a checked "Trip Elements" value to an existing cb_trip_type term
 * (Cruise, Destination, Flight, Train, Other, Resort, Retreat -- see
 * checkedbags-trips.php). Without this, wp_set_object_terms() would
 * silently CREATE a new taxonomy term for any unmapped value (e.g. "Hotel/
 * Resort", "Car Rental") the first time someone checked it, since WP
 * auto-creates terms that don't already exist by that name.
 */
function cbv_map_request_type_to_trip_type( $type ) {
	$map = array(
		'Flight'       => 'Flight',
		'Cruise'       => 'Cruise',
		'Hotel/Resort' => 'Resort',
		'Train'        => 'Train',
		'Package Tour' => 'Destination',
	);
	return isset( $map[ $type ] ) ? $map[ $type ] : 'Other';
}

function cb_create_trip_request( $request ) {
	$body        = $request->get_json_params();
	$destination = ucwords( sanitize_text_field( $body['destination_pref'] ?? '' ) );
	$type        = cbv_map_request_type_to_trip_type( sanitize_text_field( $body['type'] ?? '' ) );
	$when        = sanitize_text_field( $body['when'] ?? '' );
	$duration    = sanitize_text_field( $body['duration'] ?? '' );
	$group_size  = absint( $body['group_size'] ?? 0 );
	$user_id     = get_current_user_id();

	if ( empty( $destination ) ) {
		return new WP_Error( 'cb_missing_destination', 'Please tell us where (or what vibe) you have in mind.', array( 'status' => 400 ) );
	}
	if ( $group_size > 0 && $group_size < 4 ) {
		return new WP_Error( 'cb_group_too_small', 'Custom group trips need a minimum of 4 travelers.', array( 'status' => 400 ) );
	}

	$trip_id = wp_insert_post( array(
		'post_type'   => 'cb_trip',
		'post_title'  => $destination,
		'post_status' => 'publish',
		'post_author' => $user_id,
	) );

	if ( is_wp_error( $trip_id ) ) {
		return new WP_Error( 'cb_create_failed', 'Could not submit your request.', array( 'status' => 500 ) );
	}

	update_post_meta( $trip_id, 'cb_status', 'requested' );
	update_post_meta( $trip_id, 'cb_source', 'member_built' );
	update_post_meta( $trip_id, 'cb_capacity', $group_size ?: 4 );
	update_post_meta( $trip_id, 'cb_min_group_size', 4 );
	update_post_meta( $trip_id, 'cb_when_notes', $when );
	update_post_meta( $trip_id, 'cb_duration_notes', $duration );
	cb_save_trip_request_fields( $trip_id, $body );

	if ( $type ) {
		wp_set_object_terms( $trip_id, $type, 'cb_trip_type' );
	}

	cb_trip_add_member( $trip_id, $user_id );

	return array( 'trip_id' => $trip_id, 'status' => 'requested' );
}

function cb_accept_trip_quote( $request ) {
	$trip_id = (int) $request['id'];
	$user_id = get_current_user_id();
	$trip    = get_post( $trip_id );

	if ( ! $trip || $trip->post_type !== 'cb_trip' ) {
		return new WP_Error( 'cb_not_found', 'Trip not found.', array( 'status' => 404 ) );
	}
	if ( ! in_array( $user_id, cb_trip_get_roster( $trip_id ), true ) ) {
		return new WP_Error( 'cb_not_yours', 'This is not your request.', array( 'status' => 403 ) );
	}
	if ( get_post_meta( $trip_id, 'cb_status', true ) !== 'quoted' ) {
		return new WP_Error( 'cb_not_quoted', 'This request has not been quoted yet.', array( 'status' => 400 ) );
	}

	$quoted_price = (float) get_post_meta( $trip_id, 'cb_quoted_price', true );
	if ( $quoted_price > 0 ) {
		update_post_meta( $trip_id, 'cb_price', $quoted_price );
	}

	cb_trip_set_status( $trip_id, 'accepted' );

	return array( 'accepted' => true );
}

/* ==========================================================================
   5. Shortcode: [cb_gate_requests]
   ========================================================================== */
add_shortcode( 'cb_gate_requests', function () {

	if ( ! is_user_logged_in() ) {
		return '<p class="cb-empty">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to suggest trips or build your own.</p>';
	}

	if ( ! cbv_user_is_full_member() ) {
		return '<p class="cb-empty">This feature is available to Full Members.</p>';
	}

	$user_id = get_current_user_id();
	ob_start();

	?>
	<?php
	$my_requests = get_posts( array(
		'post_type'   => 'cb_trip',
		'numberposts' => -1,
		'meta_query'  => array(
			array( 'key' => 'cb_source', 'value' => 'member_built' ),
			array( 'key' => 'cb_status', 'value' => array( 'requested', 'quoted', 'accepted', 'declined' ), 'compare' => 'IN' ),
		),
	) );
	$my_requests = array_filter( $my_requests, function ( $t ) use ( $user_id ) {
		return in_array( $user_id, cb_trip_get_roster( $t->ID ), true );
	} );
	?>
	<h3 class="requests-section-title">Build Your Own Trip</h3>

	<?php foreach ( $my_requests as $req ) :
		$status       = get_post_meta( $req->ID, 'cb_status', true );
		$quoted_price = (float) get_post_meta( $req->ID, 'cb_quoted_price', true );
		$quote_notes  = get_post_meta( $req->ID, 'cb_quote_notes', true );
		?>
		<div class="request-status-card">
			<h4 class="request-status-title"><?php echo esc_html( get_the_title( $req ) ); ?></h4>
			<span class="request-status-badge status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( ucfirst( $status ) ); ?></span>
			<?php if ( $status === 'quoted' ) : ?>
				<div class="request-quote-box">
					<p class="request-quote-price">$<?php echo esc_html( number_format( $quoted_price, 2 ) ); ?> / person</p>
					<?php if ( $quote_notes ) : ?><p class="request-quote-notes"><?php echo nl2br( esc_html( $quote_notes ) ); ?></p><?php endif; ?>
					<button class="btn btn-ticket cb-accept-quote-btn" data-trip-id="<?php echo esc_attr( $req->ID ); ?>">Accept this quote</button>
				</div>
			<?php elseif ( $status === 'accepted' ) : ?>
				<p class="request-accepted-note">Accepted! Head to <a href="/gate-09-payments/">Gate 09 — Payments</a> to pay your deposit.</p>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>

	<form id="cb-trip-request-form" class="trip-request-form">

		<fieldset>
			<legend>Group Leader</legend>
			<label>Your name <input type="text" id="req-organizer-name"></label>
			<label>Email <input type="email" id="req-organizer-email"></label>
			<label>Phone <input type="tel" id="req-organizer-phone"></label>
			<label>Your role <select id="req-organizer-role">
				<option>Birthday Host</option><option>Family Reunion Planner</option>
				<option>Corporate Lead</option><option>Friend Group Lead</option><option>Other</option>
			</select></label>
			<label>Who's paying? <select id="req-decision-style">
				<option value="I'm paying for the whole group">I'm paying for the whole group</option>
				<option value="Each member pays individually">Each member pays individually</option>
			</select></label>
			<label>Address <textarea id="req-client-address" rows="2"></textarea></label>
		</fieldset>

		<fieldset>
			<legend>Group Size (minimum 4)</legend>
			<label>Total travelers <input type="number" id="req-group-size" min="4" value="4"></label>
			<label>Travelers age 0–17 <input type="number" id="req-ages-0-17" min="0" value="0"></label>
			<label>Travelers age 18–64 <input type="number" id="req-ages-18-64" min="0" value="4"></label>
			<label>Travelers age 65+ <input type="number" id="req-ages-65-plus" min="0" value="0"></label>
			<label>Group dynamic <select id="req-group-dynamic">
				<option>All Couples</option><option>Single Friends</option>
				<option>Multi-Generational Family</option><option>Active/Fitness Group</option><option>Other</option>
			</select></label>
			<label>Rooming preference <select id="req-rooming">
				<option>Doubles (1 bed each room)</option><option>Twins (2 beds each room)</option>
				<option>Shared suites/villas</option><option>Mix of the above</option>
			</select></label>
		</fieldset>

		<fieldset>
			<legend>Destination &amp; Timing</legend>
			<label>Where (specific place, or general vibe) <input type="text" id="req-destination" required placeholder="e.g. Amalfi Coast, or 'warm Caribbean beach'"></label>
			<label>Dates <select id="req-date-flexibility">
				<option value="Fixed dates">Fixed dates</option>
				<option value="Flexible window">Flexible window</option>
			</select></label>
			<label>When <input type="text" id="req-when" placeholder="e.g. March 10-17, 2027 or 'any week in Sept 2027'"></label>
			<label>Trip length <input type="text" id="req-duration" placeholder="e.g. 7 nights"></label>
		</fieldset>

		<fieldset>
			<legend>Trip Category (check all that apply)</legend>
			<label class="check-row"><input type="checkbox" name="trip_category" value="Domestic US"> Destination within the contiguous U.S.</label>
			<label class="check-row"><input type="checkbox" name="trip_category" value="Non-continental US"> U.S. territories / non-continental (Hawaii, Alaska, PR, USVI, Guam)</label>
			<label class="check-row"><input type="checkbox" name="trip_category" value="International"> International (passport required)</label>
			<label class="check-row"><input type="checkbox" name="trip_category" value="Multi-stop"> Multi-stop / multi-city trip</label>
		</fieldset>

		<fieldset>
			<legend>Trip Elements (check all that apply)</legend>
			<label class="check-row"><input type="checkbox" name="transport_modes" value="Flight"> Flight needed</label>
			<label class="check-row"><input type="checkbox" name="transport_modes" value="Cruise"> Cruise</label>
			<label class="check-row"><input type="checkbox" name="transport_modes" value="Hotel/Resort"> Hotel/Resort stay</label>
			<label class="check-row"><input type="checkbox" name="transport_modes" value="Car Rental"> Car rental</label>
			<label class="check-row"><input type="checkbox" name="transport_modes" value="Package Tour"> Package tour</label>
			<label class="check-row"><input type="checkbox" name="transport_modes" value="Bus/Motorcoach"> Bus / motorcoach</label>
			<label class="check-row"><input type="checkbox" name="transport_modes" value="Train"> Train</label>
			<label>Departure city(ies) <input type="text" id="req-origin-city" placeholder="One city, or list if members fly from different airports"></label>
			<label>Special transit needs <input type="text" id="req-special-transit" placeholder="e.g. sleeper cabins, wheelchair-accessible bus"></label>
		</fieldset>

		<fieldset id="req-section-air" class="req-conditional-section" style="display:none;">
			<legend>Air Travel</legend>
			<label>Airline preference / frequent flyer programs <input type="text" id="req-airline-preference"></label>
			<p class="requests-check-group-label">Seat preference (check all that apply):</p>
			<label class="check-row"><input type="checkbox" name="seat_preference" value="Economy"> Economy</label>
			<label class="check-row"><input type="checkbox" name="seat_preference" value="Extra Leg Room/Premium"> Extra Leg Room/Premium</label>
			<label class="check-row"><input type="checkbox" name="seat_preference" value="Business Class"> Business Class</label>
			<label class="check-row"><input type="checkbox" name="seat_preference" value="First Class"> First Class</label>
			<label class="check-row"><input type="checkbox" name="seat_preference" value="Aisle"> Aisle</label>
			<label class="check-row"><input type="checkbox" name="seat_preference" value="Middle"> Middle</label>
			<label class="check-row"><input type="checkbox" name="seat_preference" value="Window"> Window</label>
			<label class="check-row"><input type="checkbox" name="seat_preference" value="Bulkhead"> Bulkhead</label>
			<label class="check-row"><input type="checkbox" name="seat_preference" value="Forward"> Forward</label>
			<label class="check-row"><input type="checkbox" name="seat_preference" value="Wing"> Wing</label>
		</fieldset>

		<fieldset id="req-section-cruise" class="req-conditional-section" style="display:none;">
			<legend>Cruise Vacation</legend>
			<label>Cruise preferences / frequent cruiser programs <input type="text" id="req-cruise-preferences"></label>
			<label>Cruise itinerary <input type="text" id="req-cruise-itinerary"></label>
			<label>Cruise length <input type="text" id="req-cruise-length"></label>
			<label>Pre and post cruise nights <select id="req-pre-post-cruise-nights">
				<option value="">—</option><option value="Yes">Yes</option><option value="No">No</option>
			</select></label>
			<label>Cabin class <select id="req-cruise-cabin-class">
				<option value="">—</option>
				<option value="Interior">Interior</option>
				<option value="Oceanview">Oceanview</option>
				<option value="Balcony">Balcony</option>
				<option value="Suite">Suite</option>
			</select></label>
			<label>Beverage plan <select id="req-beverage-plan">
				<option value="">—</option><option value="Yes">Yes</option><option value="No">No</option>
			</select></label>
			<label>Beverage plan type <input type="text" id="req-beverage-plan-type"></label>
		</fieldset>

		<fieldset id="req-section-hotel" class="req-conditional-section" style="display:none;">
			<legend>Hotel and Resort Vacation</legend>
			<label># of nights <input type="text" id="req-hotel-nights"></label>
			<label>Hotel preferences / frequent guest programs <input type="text" id="req-hotel-preferences"></label>
			<label># of rooms/arrangement <input type="text" id="req-hotel-rooms-arrangement"></label>
			<p class="requests-check-group-label">Room (check all that apply):</p>
			<label class="check-row"><input type="checkbox" name="hotel_room_type" value="Standard Room"> Standard Room</label>
			<label class="check-row"><input type="checkbox" name="hotel_room_type" value="Garden View"> Garden View</label>
			<label class="check-row"><input type="checkbox" name="hotel_room_type" value="Ocean View/Front"> Ocean View/Front</label>
			<label class="check-row"><input type="checkbox" name="hotel_room_type" value="Other"> Other</label>
			<p class="requests-check-group-label">Features (check all that apply):</p>
			<label class="check-row"><input type="checkbox" name="hotel_features" value="All Inclusive"> All Inclusive</label>
			<label class="check-row"><input type="checkbox" name="hotel_features" value="Adults Only"> Adults Only</label>
			<label class="check-row"><input type="checkbox" name="hotel_features" value="Family Friendly"> Family Friendly</label>
			<label class="check-row"><input type="checkbox" name="hotel_features" value="Concierge Level"> Concierge Level</label>
			<label class="check-row"><input type="checkbox" name="hotel_features" value="Suite/Jr Suite"> Suite/Jr Suite</label>
			<label class="check-row"><input type="checkbox" name="hotel_features" value="On the Beach"> On the Beach</label>
			<label class="check-row"><input type="checkbox" name="hotel_features" value="Near City Center"> Near City Center</label>
			<label class="check-row"><input type="checkbox" name="hotel_features" value="Kids Club"> Kids Club</label>
			<label class="check-row"><input type="checkbox" name="hotel_features" value="Near Air/Cruise Port"> Near Air/Cruise Port</label>
			<label class="check-row"><input type="checkbox" name="hotel_features" value="Luxury Resort"> Luxury Resort</label>
			<label class="check-row"><input type="checkbox" name="hotel_features" value="Activities On-Site"> Activities On-Site</label>
			<label class="check-row"><input type="checkbox" name="hotel_features" value="Standard View"> Standard View</label>
			<label class="check-row"><input type="checkbox" name="hotel_features" value="Ocean View"> Ocean View</label>
			<label>Concierge level notes <input type="text" id="req-hotel-concierge-notes"></label>
		</fieldset>

		<fieldset id="req-section-car" class="req-conditional-section" style="display:none;">
			<legend>Car Rental</legend>
			<label>Car preferences / frequent renter programs <input type="text" id="req-car-preferences"></label>
			<label>Add-ons <input type="text" id="req-car-addons"></label>
			<p class="requests-check-group-label">Car category (check all that apply):</p>
			<label class="check-row"><input type="checkbox" name="car_category" value="Compact"> Compact</label>
			<label class="check-row"><input type="checkbox" name="car_category" value="Mid Size"> Mid Size</label>
			<label class="check-row"><input type="checkbox" name="car_category" value="Full Size"> Full Size</label>
			<label class="check-row"><input type="checkbox" name="car_category" value="Luxury"> Luxury</label>
			<label class="check-row"><input type="checkbox" name="car_category" value="Other"> Other</label>
		</fieldset>

		<fieldset id="req-section-package" class="req-conditional-section" style="display:none;">
			<legend>Package Tour</legend>
			<label>Country or countries of interest <input type="text" id="req-package-countries"></label>
			<label class="check-row"><input type="checkbox" name="package_style" value="Escorted"> Escorted</label>
			<label class="check-row"><input type="checkbox" name="package_style" value="Independent"> Independent</label>
			<label>Activity level <input type="text" id="req-package-activity-level"></label>
		</fieldset>

		<fieldset>
			<legend>Budget</legend>
			<label>Target per person <select id="req-budget-tier">
				<option>$1,500 – $2,500</option><option>$2,500 – $4,000</option>
				<option>$4,000 – $6,000</option><option>Luxury $6,000+</option>
			</select></label>
			<label>Payment setup <select id="req-payment-logistics">
				<option>One card for the whole group</option>
				<option>Individual invoicing per traveler</option>
			</select></label>
		</fieldset>

		<fieldset>
			<legend>Style &amp; Activities</legend>
			<label>Accommodation type <select id="req-accommodation-type">
				<option>4-Star Hotels</option><option>5-Star Hotels</option><option>Boutique Lodging</option>
				<option>All-Inclusive Resort</option><option>Private Villa</option><option>Cruise</option><option>Luxury Train</option>
			</select></label>
			<label>Pace <select id="req-pace">
				<option>Relaxed</option><option>Balanced</option><option>Fast-Paced</option>
			</select></label>
			<label>Occasion <input type="text" id="req-occasion" placeholder="e.g. milestone birthday, bachelorette, reunion"></label>
			<label>Must-have experiences <textarea id="req-must-haves" rows="2" placeholder="e.g. private boat charter, cooking class"></textarea></label>
		</fieldset>

		<fieldset>
			<legend>Health &amp; Accessibility</legend>
			<label>Dietary restrictions <input type="text" id="req-dietary" placeholder="allergies, vegan, kosher, etc."></label>
			<label>Mobility/accessibility needs <input type="text" id="req-mobility" placeholder="wheelchair access, minimal walking, etc."></label>
			<label>Anything else special? <textarea id="req-special-requests" rows="2"></textarea></label>
		</fieldset>

		<p class="requests-fine-print">A planning deposit may apply before we begin building your custom itinerary, and we recommend booking 6–12 months ahead for custom group trips. If your group falls below 4 travelers, per-person pricing may adjust.</p>

		<button type="submit" class="btn btn-ticket">Submit request</button>
	</form>

	<?php
	return ob_get_clean();
} );

/* ==========================================================================
   6. Front-end JS enqueue
   ========================================================================== */
add_action( 'wp_enqueue_scripts', function () {
	$cb_gate12_js_path = WP_CONTENT_DIR . '/uploads/checkedbags/js/gate12.js';
	$cb_gate12_js_ver  = file_exists( $cb_gate12_js_path ) ? filemtime( $cb_gate12_js_path ) : '1.0.0';
	wp_enqueue_script( 'cb-gate12', content_url( 'uploads/checkedbags/js/gate12.js' ), array(), $cb_gate12_js_ver, true );
	wp_localize_script( 'cb-gate12', 'cbGate12', array(
		'restUrl' => esc_url_raw( rest_url( 'cb/v1/' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
	) );
} );
