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
} );

/* ==========================================================================
   3. Admin: formatted read-only view of the full intake
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cb_gate12_request', 'Gate 12 — Member Request Details', 'cb_render_request_meta_box', 'cb_trip', 'normal', 'default' );
} );

function cb_render_request_meta_box( $post ) {
	$raw = get_post_meta( $post->ID, 'cb_request_details', true );
	$d   = $raw ? json_decode( $raw, true ) : null;

	if ( ! $d ) {
		echo '<p><em>No custom request details on file for this trip (either a curated trip, or submitted before this field existed).</em></p>';
		return;
	}

	$rows = array(
		'Organizer'              => trim( ( $d['organizer_name'] ?? '' ) . ' — ' . ( $d['organizer_email'] ?? '' ) . ' — ' . ( $d['organizer_phone'] ?? '' ) ),
		'Organizer role'         => $d['organizer_role'] ?? '',
		'Decision style'         => $d['decision_style'] ?? '',
		'Group breakdown'        => trim( ( $d['ages_0_17'] ?? '0' ) . ' age 0–17, ' . ( $d['ages_18_64'] ?? '0' ) . ' age 18–64, ' . ( $d['ages_65_plus'] ?? '0' ) . ' age 65+' ),
		'Group dynamic'          => $d['group_dynamic'] ?? '',
		'Rooming preference'     => $d['rooming'] ?? '',
		'Destination pref.'      => $d['destination_pref'] ?? '',
		'Date flexibility'       => $d['date_flexibility'] ?? '',
		'Trip category'          => implode( ', ', (array) ( $d['trip_category'] ?? array() ) ),
		'Transport modes'        => implode( ', ', (array) ( $d['transport_modes'] ?? array() ) ),
		'Origin city(ies)'       => $d['origin_city'] ?? '',
		'Special transit'        => $d['special_transit'] ?? '',
		'Budget tier'            => $d['budget_tier'] ?? '',
		'Payment logistics'      => $d['payment_logistics'] ?? '',
		'Accommodation type'     => $d['accommodation_type'] ?? '',
		'Pace of travel'         => $d['pace'] ?? '',
		'Occasion'               => $d['occasion'] ?? '',
		'Must-have experiences'  => $d['must_haves'] ?? '',
		'Dietary restrictions'   => $d['dietary'] ?? '',
		'Mobility/accessibility' => $d['mobility'] ?? '',
		'Special requests'       => $d['special_requests'] ?? '',
	);
	?>
	<table class="widefat striped">
		<tbody>
			<?php foreach ( $rows as $label => $value ) : if ( empty( $value ) ) { continue; } ?>
				<tr>
					<td style="width:220px;"><strong><?php echo esc_html( $label ); ?></strong></td>
					<td><?php echo esc_html( $value ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

/* ==========================================================================
   4. REST endpoints
   ========================================================================== */
add_action( 'rest_api_init', function () {

	register_rest_route( 'cb/v1', '/suggestions', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return is_user_logged_in(); },
		'callback'            => 'cb_create_suggestion',
	) );

	register_rest_route( 'cb/v1', '/suggestions/(?P<id>\d+)/vote', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return is_user_logged_in(); },
		'callback'            => 'cb_toggle_suggestion_vote',
	) );

	register_rest_route( 'cb/v1', '/trip-requests', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return is_user_logged_in(); },
		'callback'            => 'cb_create_trip_request',
	) );

	register_rest_route( 'cb/v1', '/trips/(?P<id>\d+)/accept-quote', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return is_user_logged_in(); },
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

function cb_sanitize_request_details( $body ) {
	$str = function ( $key ) use ( $body ) {
		return isset( $body[ $key ] ) ? sanitize_text_field( $body[ $key ] ) : '';
	};
	$txt = function ( $key ) use ( $body ) {
		return isset( $body[ $key ] ) ? sanitize_textarea_field( $body[ $key ] ) : '';
	};
	$arr = function ( $key ) use ( $body ) {
		if ( empty( $body[ $key ] ) || ! is_array( $body[ $key ] ) ) {
			return array();
		}
		return array_map( 'sanitize_text_field', $body[ $key ] );
	};

	return array(
		'organizer_name'     => $str( 'organizer_name' ),
		'organizer_email'    => sanitize_email( $body['organizer_email'] ?? '' ),
		'organizer_phone'    => $str( 'organizer_phone' ),
		'organizer_role'     => $str( 'organizer_role' ),
		'decision_style'     => $str( 'decision_style' ),
		'ages_0_17'          => absint( $body['ages_0_17'] ?? 0 ),
		'ages_18_64'         => absint( $body['ages_18_64'] ?? 0 ),
		'ages_65_plus'       => absint( $body['ages_65_plus'] ?? 0 ),
		'group_dynamic'      => $str( 'group_dynamic' ),
		'rooming'            => $str( 'rooming' ),
		'destination_pref'   => $str( 'destination_pref' ),
		'date_flexibility'   => $str( 'date_flexibility' ),
		'trip_category'      => $arr( 'trip_category' ),
		'transport_modes'    => $arr( 'transport_modes' ),
		'origin_city'        => $str( 'origin_city' ),
		'special_transit'    => $txt( 'special_transit' ),
		'budget_tier'        => $str( 'budget_tier' ),
		'payment_logistics'  => $str( 'payment_logistics' ),
		'accommodation_type' => $str( 'accommodation_type' ),
		'pace'               => $str( 'pace' ),
		'occasion'           => $str( 'occasion' ),
		'must_haves'         => $txt( 'must_haves' ),
		'dietary'            => $txt( 'dietary' ),
		'mobility'           => $txt( 'mobility' ),
		'special_requests'   => $txt( 'special_requests' ),
	);
}

function cb_create_trip_request( $request ) {
	$body        = $request->get_json_params();
	$destination = sanitize_text_field( $body['destination_pref'] ?? '' );
	$type        = sanitize_text_field( $body['type'] ?? '' );
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
	update_post_meta( $trip_id, 'cb_request_details', wp_json_encode( cb_sanitize_request_details( $body ) ) );

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
			<legend>Transportation (check all that apply)</legend>
			<label class="check-row"><input type="checkbox" name="transport_modes" value="Flight"> Flight needed</label>
			<label class="check-row"><input type="checkbox" name="transport_modes" value="Cruise"> Cruise</label>
			<label class="check-row"><input type="checkbox" name="transport_modes" value="Bus/Motorcoach"> Bus / motorcoach</label>
			<label class="check-row"><input type="checkbox" name="transport_modes" value="Train"> Train</label>
			<label>Departure city(ies) <input type="text" id="req-origin-city" placeholder="One city, or list if members fly from different airports"></label>
			<label>Special transit needs <input type="text" id="req-special-transit" placeholder="e.g. sleeper cabins, wheelchair-accessible bus"></label>
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
	wp_enqueue_script( 'cb-gate12', content_url( 'uploads/checkedbags/js/gate12.js' ), array(), '1.0.0', true );
	wp_localize_script( 'cb-gate12', 'cbGate12', array(
		'restUrl' => esc_url_raw( rest_url( 'cb/v1/' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
	) );
} );
