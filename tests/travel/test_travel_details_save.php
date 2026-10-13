<?php
/*
 * Travel Details form -- server-side tests (2026-10-13): the save (REST cb/v1/trips/{id}/traveler-intake) accepts a
 * form with every optional section turned off; nothing in a hidden / turned-off section is ever required by the
 * server; the loyalty number is optional; "I have TSA PreCheck" is stored as true/false and no Known Traveler Number
 * is ever stored; the roster export has a "TSA PreCheck" column.
 * Runs as the admin on the $1 test trip (424), read only: every user-meta write is intercepted and captured.
 *   wp --require=/tmp/cbv_t/define.php eval-file test_travel_details_save.php
 * Set CBV_VERBOSE=1 to print every passing check.
 */
$GLOBALS['n'] = 0; $GLOBALS['fail'] = 0;
function check( $l, $c, $d = '' ) {
	$GLOBALS['n']++;
	if ( ! $c ) { $GLOBALS['fail']++; echo "FAIL: $l $d\n"; }
	elseif ( getenv( 'CBV_VERBOSE' ) ) { echo '  pass ' . str_pad( $GLOBALS['n'], 3, ' ', STR_PAD_LEFT ) . ": $l\n"; }
}
check( 'running against the temp mu-plugins copy', '/tmp/cbv_mu' === WPMU_PLUGIN_DIR );
const TRIP = 424;
$GLOBALS['writes'] = array(); // wp eval-file runs this file inside a function: shared data goes through $GLOBALS
foreach ( array( 'update_user_metadata', 'add_user_metadata', 'delete_user_metadata', 'update_post_metadata', 'add_post_metadata' ) as $hook ) {
	add_filter( $hook, function ( $check, $oid, $key, $value = null ) use ( $hook ) { $GLOBALS['writes'][] = array( $hook, $oid, $key, $value ); return true; }, 1, 4 );
}
wp_set_current_user( 1 );
check( 'the admin is on the $1 test trip\'s roster (needed to save)', in_array( 1, cb_trip_get_roster( TRIP ), true ) );

function save_intake( $body ) {
	$GLOBALS['writes'] = array();
	$req = new WP_REST_Request( 'POST', '/cb/v1/trips/' . TRIP . '/traveler-intake' );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( wp_json_encode( $body ) );
	$res = rest_do_request( $req );
	$stored = null;
	foreach ( $GLOBALS['writes'] as $w ) { if ( '_traveler_intake_' . TRIP === $w[2] ) { $stored = json_decode( $w[3], true ); } }
	return array( $res->get_status(), $res->get_data(), $stored );
}

// 1. Every optional section off: only the always-shown answers, everything else empty.
list( $status, $data, $stored ) = save_intake( array(
	'insurance_decision' => 'accepted', 'cc_auth_completed' => true, 'optional_categories' => array(),
	'car_category' => array(), 'hotel_room_type' => array(), 'car_preferences' => '', 'car_addons' => '',
	'hotel_nights' => '', 'cruise_company' => '', 'seat_preference' => '', 'departure_airport' => '',
) );
check( 'every optional section off: saved (200, saved: true)', 200 === $status && ! empty( $data['saved'] ), json_encode( array( $status, $data ) ) );
check( 'every optional section off: stored with no optional sections, empty car/hotel/cruise/air answers', is_array( $stored ) && array() === $stored['optional_categories'] && array() === $stored['car_category'] && array() === $stored['hotel_room_type'] && '' === $stored['cruise_company'] && '' === $stored['seat_preference'] );
check( 'every optional section off: TSA PreCheck stored as false, loyalty number empty', is_array( $stored ) && false === $stored['tsa_precheck'] && '' === $stored['frequent_flyer_number'] );

// 2. The server never requires fields from a turned-off section -- not even a totally empty form is refused.
list( $status, $data ) = save_intake( array() );
check( 'an empty form is not refused by the server (the browser check handles required fields)', 200 === $status && ! empty( $data['saved'] ) );

// 3. Air on with the loyalty number left empty, PreCheck ticked.
list( $status, $data, $stored ) = save_intake( array( 'optional_categories' => array( 'Flight' ), 'seat_preference' => 'Window', 'departure_airport' => 'MIA', 'frequent_flyer_number' => '', 'tsa_precheck' => true, 'insurance_decision' => 'accepted', 'cc_auth_completed' => true ) );
check( 'Air on, loyalty number empty, PreCheck ticked: saved; PreCheck true; loyalty number ""', 200 === $status && true === $stored['tsa_precheck'] && '' === $stored['frequent_flyer_number'] && array( 'Flight' ) === $stored['optional_categories'] );
list( , , $stored ) = save_intake( array( 'tsa_precheck' => 'yes' ) );
check( 'PreCheck: any ticked value is stored as true (a real true/false, nothing else)', true === $stored['tsa_precheck'] );
list( , , $stored ) = save_intake( array( 'known_traveler_number' => '12345678', 'ktn' => '87654321', 'tsa_precheck' => true ) );
check( 'no Known Traveler Number is ever stored, even if one is sent', is_array( $stored ) && ! array_key_exists( 'known_traveler_number', $stored ) && ! array_key_exists( 'ktn', $stored ) && false === strpos( wp_json_encode( $stored ), '12345678' ) );

// 4. Roster export: "TSA PreCheck" column right after "Frequent Flyer Number", Yes / blank.
$fake = array( 'tsa_precheck' => true, 'frequent_flyer_number' => 'AB123' );
add_filter( 'get_user_metadata', function ( $v, $uid, $key ) use ( &$fake ) {
	return ( 1 === (int) $uid && '_traveler_intake_' . TRIP === $key ) ? array( wp_json_encode( $fake ) ) : $v;
}, 10, 3 );
$exp = cbv_build_trip_roster_export_data( TRIP );
$col = array_search( 'TSA PreCheck', $exp['headers'], true );
check( 'roster export: "TSA PreCheck" column comes right after "Frequent Flyer Number"', false !== $col && 'Frequent Flyer Number' === $exp['headers'][ $col - 1 ] );
check( 'roster export: every row has one value per column', array() === array_filter( $exp['rows'], function ( $r ) use ( $exp ) { return count( $r ) !== count( $exp['headers'] ); } ) );
$row = $exp['rows'][0] ?? array();
check( 'roster export: PreCheck ticked shows "Yes", next to the loyalty number', 'Yes' === ( $row[ $col ] ?? null ) && 'AB123' === ( $row[ $col - 1 ] ?? null ), json_encode( array( $row[ $col - 1 ] ?? null, $row[ $col ] ?? null ) ) );
$fake = array( 'tsa_precheck' => false );
$row = cbv_build_trip_roster_export_data( TRIP )['rows'][0] ?? array();
check( 'roster export: PreCheck not ticked shows blank', '' === ( $row[ $col ] ?? null ) );

wp_set_current_user( 0 );
echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
