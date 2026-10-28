<?php
/**
 * Plugin Name: CBGV Staff Notifications & Travel Advisor Hand-off
 * Description: Emails staff when something needs their attention (Gate 11
 *   Vacation Request, quote accepted, Full Membership requested, Trip
 *   Registration saved/changed) and emails the travel advisor a summary of a
 *   traveler's travel requests when they save their Trip Registration.
 *   Settings > CBGV Notifications holds both addresses (blank = the site
 *   Administration Email).
 *
 * The events are announced by do_action() calls in the code where they
 * happen (checkedbags-gate12.php, checkedbags-trip-invites.php); this file
 * only decides what to send. Emails are plain text and never include
 * passport numbers, card details, insurance or medical information. The
 * travel advisor email includes the traveler's email address (not phone).
 */

defined( 'ABSPATH' ) || exit;

/* ==========================================================================
   1. Addresses
   ========================================================================== */
function cbv_notify_staff_email() {
	$email = get_option( 'cbv_staff_notify_email', '' );
	return is_email( $email ) ? $email : get_option( 'admin_email' );
}

function cbv_notify_advisor_email() {
	$email = get_option( 'cbv_travel_advisor_email', '' );
	return is_email( $email ) ? $email : get_option( 'admin_email' );
}

/* ==========================================================================
   2. Settings > CBGV Notifications
   ========================================================================== */
add_action( 'admin_init', function () {
	foreach ( array( 'cbv_staff_notify_email', 'cbv_travel_advisor_email' ) as $option ) {
		register_setting( 'cbv_notifications', $option, array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => function ( $value ) use ( $option ) {
				$value = trim( (string) $value );
				if ( '' === $value ) {
					return ''; // blank = use the site Administration Email
				}
				if ( ! is_email( $value ) ) {
					add_settings_error( $option, $option . '_invalid', 'That is not a valid email address, so the previous setting was kept.' );
					return get_option( $option, '' );
				}
				return sanitize_email( $value );
			},
		) );
	}
} );

add_action( 'admin_menu', function () {
	add_options_page( 'CBGV Notifications', 'CBGV Notifications', 'manage_options', 'cbv-notifications', 'cbv_notify_settings_page' );
} );

function cbv_notify_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$admin_email = get_option( 'admin_email' );
	$fields      = array(
		'cbv_staff_notify_email'   => array(
			'label' => 'Staff notifications email',
			'help'  => 'Gets an email when a Gate 11 Vacation Request is submitted, a Lead Client accepts a quote, a Trip Guest asks for Full Membership, or a traveler saves or changes their Trip Registration.',
		),
		'cbv_travel_advisor_email' => array(
			'label' => 'Travel advisor email',
			'help'  => 'Gets a summary of a traveler\'s travel requests (the extras they ticked and their answers, plus companions) each time they save a change to their Trip Registration. Never includes passport, card, insurance or medical details.',
		),
	);
	?>
	<div class="wrap">
		<h1>CBGV Notifications</h1>
		<p>Leave a box blank to use the site Administration Email (currently <strong><?php echo esc_html( $admin_email ); ?></strong>, set under Settings &gt; General).</p>
		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'cbv_notifications' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $key => $f ) : ?>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f['label'] ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( get_option( $key, '' ) ); ?>" placeholder="<?php echo esc_attr( $admin_email ); ?>">
							<p class="description"><?php echo esc_html( $f['help'] ); ?></p>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( 'Save' ); ?>
		</form>
	</div>
	<?php
}

/* ==========================================================================
   3. Helpers
   ========================================================================== */
function cbv_notify_send( $to, $subject, array $lines ) {
	$body = implode( "\n", $lines ) . "\n\n-- \nChecked Bags & Good Vibes website (automatic message)";
	return wp_mail( $to, '[CBGV] ' . $subject, $body );
}

function cbv_notify_member_name( $user_id ) {
	$user = get_userdata( $user_id );
	return $user ? $user->display_name : 'Member #' . (int) $user_id;
}

// Used only in the travel advisor email (owner, 2026-10-28: email yes, phone no).
function cbv_notify_member_email( $user_id ) {
	$user = get_userdata( $user_id );
	return ( $user && is_email( $user->user_email ) ) ? $user->user_email : 'not on file';
}

function cbv_notify_trip_title( $trip_id ) {
	$title = get_the_title( $trip_id );
	return '' !== $title ? html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) : 'Trip #' . (int) $trip_id;
}

// Built directly (get_edit_post_link() returns nothing when the member who
// triggered the event can't edit the trip, which is always the case here).
function cbv_notify_trip_admin_url( $trip_id ) {
	return admin_url( 'post.php?post=' . (int) $trip_id . '&action=edit' );
}

function cbv_notify_user_admin_url( $user_id ) {
	return admin_url( 'user-edit.php?user_id=' . (int) $user_id );
}

/* ==========================================================================
   4. Staff emails
   ========================================================================== */

// Gate 11 Vacation Request submitted (checkedbags-gate12.php, cb_create_trip_request).
add_action( 'cbv_vacation_request_submitted', function ( $trip_id, $user_id ) {
	$group = get_post_meta( $trip_id, 'cb_capacity', true );
	$when  = get_post_meta( $trip_id, 'cb_when_notes', true );
	cbv_notify_send( cbv_notify_staff_email(), 'New Vacation Request: ' . cbv_notify_trip_title( $trip_id ), array(
		'A new Gate 11 Vacation Request was submitted.',
		'',
		'Member: ' . cbv_notify_member_name( $user_id ),
		'Trip request: ' . cbv_notify_trip_title( $trip_id ),
		'Group size: ' . ( $group ? $group : 'not given' ),
		'When: ' . ( $when ? $when : 'not given' ),
		'',
		'Next step: open the trip, review the request details, add the quote and set Status to Quoted.',
		'Open the request: ' . cbv_notify_trip_admin_url( $trip_id ),
	) );
}, 10, 2 );

// Lead Client accepted a quote (checkedbags-gate12.php, cb_accept_trip_quote).
add_action( 'cbv_quote_accepted', function ( $trip_id, $user_id ) {
	cbv_notify_send( cbv_notify_staff_email(), 'Quote accepted: ' . cbv_notify_trip_title( $trip_id ), array(
		'A Lead Client accepted the quote for their group trip. The trip is now "Accepted".',
		'',
		'Member: ' . cbv_notify_member_name( $user_id ),
		'Trip: ' . cbv_notify_trip_title( $trip_id ),
		'',
		'Next step: finish building the trip and set Status to Active to make it live.',
		'Open the trip: ' . cbv_notify_trip_admin_url( $trip_id ),
	) );
}, 10, 2 );

// Trip Guest asked for Full Membership (checkedbags-trip-invites.php).
add_action( 'cbv_full_membership_requested', function ( $user_id ) {
	$trip_id = (int) get_user_meta( $user_id, '_invited_by_trip_id', true );
	cbv_notify_send( cbv_notify_staff_email(), 'Full Membership request: ' . cbv_notify_member_name( $user_id ), array(
		'A Trip Guest asked to become a Full Member.',
		'',
		'Member: ' . cbv_notify_member_name( $user_id ),
		'Trip they joined: ' . ( $trip_id ? cbv_notify_trip_title( $trip_id ) : 'not recorded' ),
		'',
		'Next step: review them in Users > Trip Guests and click "Promote to Full Member" if approved.',
		'Open Trip Guests: ' . admin_url( 'users.php?page=cbv-trip-guests' ),
	) );
} );

// Trip Registration saved for the first time, or changed (checkedbags-trip-invites.php).
// Only fires when the saved answers actually differ from what was there before.
add_action( 'cbv_traveler_intake_saved', function ( $trip_id, $user_id, $old, $new ) {
	$first = empty( $old );
	cbv_notify_send( cbv_notify_staff_email(), 'Trip Registration ' . ( $first ? 'submitted' : 'updated' ) . ': ' . cbv_notify_member_name( $user_id ) . ' - ' . cbv_notify_trip_title( $trip_id ), array(
		$first ? 'A traveler saved their Trip Registration for the first time.' : 'A traveler changed their Trip Registration.',
		'',
		'Member: ' . cbv_notify_member_name( $user_id ),
		'Trip: ' . cbv_notify_trip_title( $trip_id ),
		'Extras ticked: ' . cbv_intake_ticked_label( $new ),
		'',
		'The travel advisor has been sent a summary of their travel requests.',
		'Full answers: open the trip and use Export Roster to Excel.',
		'Open the trip: ' . cbv_notify_trip_admin_url( $trip_id ),
		'Member profile: ' . cbv_notify_user_admin_url( $user_id ),
	) );
	cbv_notify_send( cbv_notify_advisor_email(), 'Travel requests: ' . cbv_notify_member_name( $user_id ) . ' - ' . cbv_notify_trip_title( $trip_id ), cbv_intake_advisor_summary( $trip_id, $user_id, $new, $first ) );
}, 10, 4 );

/* ==========================================================================
   5. Travel advisor summary (no passport, card, insurance or medical data)
   ========================================================================== */

// The four extras a traveler can tick, by stored value => staff label.
const CBV_INTAKE_EXTRAS = array( 'Flight' => 'Air', 'Cruise' => 'Cruise', 'Hotel' => 'Hotel', 'Car Rental' => 'Car' );

function cbv_intake_ticked_label( array $data ) {
	$ticked = array();
	foreach ( (array) ( $data['optional_categories'] ?? array() ) as $value ) {
		if ( array_key_exists( $value, CBV_INTAKE_EXTRAS ) ) {
			$ticked[] = CBV_INTAKE_EXTRAS[ $value ];
		}
	}
	return $ticked ? implode( ', ', $ticked ) : 'none';
}

/**
 * Which travel sections apply to this traveler on this trip, and why:
 * 'ticked' (the traveler opted in) or 'trip type' (shown because of the
 * trip's own Trip Type). Package Tour can't be ticked; it only comes from
 * the trip type. Same rules as the Trip Registration form itself.
 */
function cbv_intake_sections( $trip_id, array $data ) {
	$ticked = (array) ( $data['optional_categories'] ?? array() );
	$has    = function ( $type ) use ( $trip_id ) {
		return function_exists( 'cbv_trip_has_type' ) && cbv_trip_has_type( $trip_id, $type );
	};
	$why = function ( $by_type, $value ) use ( $ticked ) {
		if ( in_array( $value, $ticked, true ) ) {
			return 'ticked';
		}
		return $by_type ? 'trip type' : '';
	};
	return array(
		'Air'          => $why( $has( 'Flight' ), 'Flight' ),
		'Cruise'       => $why( $has( 'Cruise' ), 'Cruise' ),
		'Hotel'        => $why( $has( 'Hotel' ) || $has( 'Resort' ), 'Hotel' ),
		'Car'          => $why( $has( 'Car Rental' ), 'Car Rental' ),
		'Package Tour' => $has( 'Package Tour' ) ? 'trip type' : '',
	);
}

function cbv_intake_advisor_summary( $trip_id, $user_id, array $d, $first ) {
	$v = function ( $key ) use ( $d ) {
		$val = $d[ $key ] ?? '';
		if ( is_array( $val ) ) {
			$val = implode( ', ', array_filter( $val ) );
		}
		if ( is_bool( $val ) ) {
			$val = $val ? 'Yes' : 'No';
		}
		return '' === (string) $val ? '-' : (string) $val;
	};
	$sections = cbv_intake_sections( $trip_id, $d );
	$fields   = array(
		'Air'          => array( 'Departure airport' => 'departure_airport', 'Seat preference' => 'seat_preference', 'Cabin class' => 'flight_cabin_class', 'Preferred airline' => 'preferred_airline', 'Frequent flyer / loyalty number' => 'frequent_flyer_number', 'TSA PreCheck' => 'tsa_precheck' ),
		'Cruise'       => array( 'Cruise company' => 'cruise_company', 'Cruise program number' => 'cruise_program_number', 'Start date' => 'cruise_start_date', 'End date' => 'cruise_end_date', 'Duration' => 'cruise_duration', 'Region' => 'cruise_region', 'Departure port' => 'cruise_departure_port', 'Pre/post cruise nights' => 'pre_post_cruise_nights', 'Cabin class' => 'cruise_cabin_class', 'Beverage plan' => 'beverage_plan' ),
		'Hotel'        => array( 'Nights' => 'hotel_nights', 'Preferences / frequent guest programs' => 'hotel_preferences', 'Rooms / arrangement' => 'hotel_rooms_arrangement', 'Room type' => 'hotel_room_type' ),
		'Car'          => array( 'Preferences / frequent renter programs' => 'car_preferences', 'Add-ons' => 'car_addons', 'Category' => 'car_category' ),
		'Package Tour' => array( 'Countries' => 'package_countries', 'Style' => 'package_style', 'Activity level' => 'package_activity_level' ),
	);

	$lines = array(
		( $first ? 'New Trip Registration' : 'Updated Trip Registration' ) . ' from a Checked Bags & Good Vibes traveler.',
		'',
		'Traveler: ' . cbv_notify_member_name( $user_id ),
		'Traveler email: ' . cbv_notify_member_email( $user_id ),
		'Trip: ' . cbv_notify_trip_title( $trip_id ),
		'Extras they ticked: ' . cbv_intake_ticked_label( $d ),
	);
	$any = false;
	foreach ( $sections as $name => $why ) {
		if ( '' === $why ) {
			continue;
		}
		$any     = true;
		$lines[] = '';
		$lines[] = strtoupper( $name ) . ( 'trip type' === $why ? ' (part of this trip)' : ' (requested by the traveler)' );
		foreach ( $fields[ $name ] as $label => $key ) {
			$lines[] = '  ' . $label . ': ' . $v( $key );
		}
	}
	if ( ! $any ) {
		$lines[] = '';
		$lines[] = 'No air, cruise, hotel, car or package tour requests.';
	}
	$lines[] = '';
	$lines[] = 'COMPANIONS';
	$lines[] = '  Additional adults: ' . (int) ( $d['additional_adults'] ?? 0 );
	$lines[] = '  Names: ' . $v( 'traveling_companions' );
	$lines[] = '  Additional children: ' . (int) ( $d['additional_children'] ?? 0 );
	$lines[] = "  Children's ages: " . $v( 'children_ages' );
	$lines[] = '';
	$lines[] = 'For a phone number or other details, please ask the CBGV team.';
	return $lines;
}
