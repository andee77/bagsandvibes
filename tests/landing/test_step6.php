<?php
/*
 * Step 6 tests: route (one card per day), time line, code chain, "Route days" box.
 * Run against a temp COPY of the mu-plugins folder with the Step 6 files overlaid:
 *   wp --require=/tmp/cbv_t/define.php eval-file test_step6.php
 * NO database writes: fake posts live in the in-memory object cache, meta is faked by a get_post_metadata filter,
 * and every write is intercepted. Set CBV_VERBOSE=1 to print every passing check.
 */
$GLOBALS['n'] = 0; $GLOBALS['fail'] = 0;
function check( $l, $c, $d = '' ) {
	$GLOBALS['n']++;
	if ( ! $c ) { $GLOBALS['fail']++; echo "FAIL: $l $d\n"; }
	elseif ( getenv( 'CBV_VERBOSE' ) ) { echo '  pass ' . str_pad( $GLOBALS['n'], 3, ' ', STR_PAD_LEFT ) . ": $l\n"; }
}
function section( $s ) { if ( getenv( 'CBV_VERBOSE' ) ) { echo "\n=== $s ===\n"; } }

check( 'running against the temp mu-plugins copy', '/tmp/cbv_mu' === WPMU_PLUGIN_DIR );
check( 'Step 6 code is loaded', function_exists( 'cbv_lp_render_route' ) && function_exists( 'cbv_lp_route_days' ) && function_exists( 'cbv_lp_route_time_line' ) && function_exists( 'cbv_lp_strip_legacy_itinerary' ) );

$fake = array();
$writes = array();
add_filter( 'get_post_metadata', function ( $v, $oid, $key ) use ( &$fake ) {
	if ( isset( $fake[ $oid ] ) && array_key_exists( $key, $fake[ $oid ] ) ) { return array( $fake[ $oid ][ $key ] ); }
	return $v;
}, 10, 3 );
foreach ( array( 'update_post_metadata', 'add_post_metadata', 'delete_post_metadata' ) as $hook ) {
	add_filter( $hook, function ( $check, $oid, $key, $value = null ) use ( &$writes, $hook ) {
		$writes[] = array( $hook, $oid, $key, $value );
		return true;
	}, 1, 4 );
}
function fake_post( $id, $type, $status, $title, $mime = '' ) {
	$p = new WP_Post( (object) array( 'ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_title' => $title, 'post_author' => 1, 'post_name' => sanitize_title( $title ), 'post_date' => '2026-01-01 00:00:00', 'post_date_gmt' => '2026-01-01 00:00:00', 'post_content' => '', 'post_excerpt' => '', 'post_mime_type' => $mime, 'filter' => 'raw' ) );
	wp_cache_set( $id, $p, 'posts' );
	return $p;
}
const TRIP = 999250; const IMG = 999263; const VID = 999261;
fake_post( TRIP, 'cb_trip', 'publish', 'Test Voyage' );
fake_post( IMG, 'attachment', 'inherit', 'bimini', 'image/jpeg' );
fake_post( VID, 'attachment', 'inherit', 'video', 'video/mp4' );
$fake[ IMG ] = array( '_wp_attached_file' => 'virgin/bimini.jpg', '_wp_attachment_metadata' => array( 'width' => 1200, 'height' => 800, 'file' => 'virgin/bimini.jpg', 'sizes' => array() ), '_wp_attachment_image_alt' => 'Pool at The Beach Club at Bimini' );

// Trip 181's itinerary as stored (with the Step 4 codes): days numbered from 0, ports listed twice.
$it181 = array(
	array( 'day' => '0', 'date' => '2027-10-25', 'port' => 'Miami', 'country' => 'US', 'stop_code' => 'MIA', 'description' => 'Embarkation', 'time' => '12:01', 'tender_mode' => 'Dock' ),
	array( 'day' => '1', 'date' => '2027-10-26', 'port' => '', 'country' => '', 'stop_code' => '', 'description' => 'At Sea', 'time' => '', 'tender_mode' => '' ),
	array( 'day' => '2', 'date' => '2027-10-27', 'port' => 'Puerto Plata, Dominican Republic', 'country' => '', 'stop_code' => 'POP', 'description' => 'Arrival', 'time' => '10:00', 'tender_mode' => 'Dock' ),
	array( 'day' => '2', 'date' => '2027-10-27', 'port' => 'Puerto Plata, Dominican Republic', 'country' => '', 'stop_code' => 'POP', 'description' => 'Departure', 'time' => '18:00', 'tender_mode' => 'Dock' ),
	array( 'day' => '3', 'date' => '2027-10-28', 'port' => '', 'country' => '', 'stop_code' => '', 'description' => 'At Sea', 'time' => '', 'tender_mode' => '' ),
	array( 'day' => '4', 'date' => '2027-10-29', 'port' => 'Bimini Beach Club', 'country' => '', 'stop_code' => 'BIM', 'description' => 'Arrival', 'time' => '09:00', 'tender_mode' => 'Dock' ),
	array( 'day' => '4', 'date' => '2027-10-29', 'port' => 'Bimini Beach Club', 'country' => '', 'stop_code' => 'BIM', 'description' => 'Departure', 'time' => '18:00', 'tender_mode' => 'Dock' ),
	array( 'day' => '5', 'date' => '2027-10-30', 'port' => 'Miami', 'country' => '', 'stop_code' => 'MIA', 'description' => 'Disembarkation', 'time' => '18:30', 'tender_mode' => 'Dock' ),
);
$GLOBALS['it181'] = $it181; // eval-file runs this file inside a function, so share it explicitly
function trip_meta( $extra = array() ) {
	$it181 = $GLOBALS['it181'];
	return array_merge( array(
		'cbv_lp_event_type' => 'cruise', 'cbv_lp_sections' => array(), 'cbv_lp_accommodation_noun' => '',
		'cb_start_date' => '2027-10-25', 'cb_end_date' => '2027-10-30', 'cb_public_landing_show_itinerary' => 1,
		'cb_itinerary' => $it181, 'cbv_lp_route_heading' => '', 'cbv_lp_route_days' => array(), 'cbv_lp_venue_name' => 'Valiant Lady',
		'cb_trip_code' => 'CBV-TEST', 'cb_deposit_amount' => 0,
	), $extra );
}
function shift_dates( $rows, $days ) {
	foreach ( $rows as &$r ) { if ( ! empty( $r['date'] ) ) { $r['date'] = gmdate( 'Y-m-d', strtotime( $r['date'] . ' 00:00:00 UTC' ) + $days * DAY_IN_SECONDS ); } }
	return $rows;
}

/* ---- A. helpers ---- */
section( 'A. helpers' );
check( 'time: 17:00 -> 5:00 pm', '5:00 pm' === cbv_lp_route_time( '17:00' ) );
check( 'time: 06:30 -> 6:30 am, 6:30 -> 6:30 am', '6:30 am' === cbv_lp_route_time( '06:30' ) && '6:30 am' === cbv_lp_route_time( '6:30' ) );
check( 'time: noon and midnight', '12:01 pm' === cbv_lp_route_time( '12:01' ) && '12:30 am' === cbv_lp_route_time( '00:30' ) );
check( 'time: seconds ignored (17:00:00)', '5:00 pm' === cbv_lp_route_time( '17:00:00' ) );
check( 'time: blank, junk and out-of-range are empty', '' === cbv_lp_route_time( '' ) && '' === cbv_lp_route_time( 'noon' ) && '' === cbv_lp_route_time( '25:00' ) && '' === cbv_lp_route_time( '10:75' ) && '' === cbv_lp_route_time( null ) );
check( 'row type: the five itinerary types, any case', 'embarkation' === cbv_lp_route_row_type( array( 'description' => 'Embarkation' ) ) && 'arrival' === cbv_lp_route_row_type( array( 'description' => ' ARRIVAL ' ) ) && 'departure' === cbv_lp_route_row_type( array( 'description' => 'Departure' ) ) && 'disembarkation' === cbv_lp_route_row_type( array( 'description' => 'Disembarkation' ) ) );
check( 'row type: At Sea by type or by port name', 'sea' === cbv_lp_route_row_type( array( 'description' => 'At Sea' ) ) && 'sea' === cbv_lp_route_row_type( array( 'port' => 'At Sea' ) ) && '' === cbv_lp_route_row_type( array( 'description' => '' ) ) );
check( 'default headings per event type', 'The Route' === cbv_lp_route_default_heading( 'cruise' ) && 'The Route' === cbv_lp_route_default_heading( 'destination' ) && 'The Plan' === cbv_lp_route_default_heading( 'resort' ) && 'The Agenda' === cbv_lp_route_default_heading( 'corporate' ) && 'The Route' === cbv_lp_route_default_heading( 'wedding' ) );

/* ---- B. days ---- */
section( 'B. days from the itinerary' );
$days = cbv_lp_route_days( $it181, '2027-10-25' );
check( 'trip 181: six days, numbered 1-6 from the start date (not the 0-based Day boxes)', array( 1, 2, 3, 4, 5, 6 ) === wp_list_pluck( $days, 'n' ), json_encode( wp_list_pluck( $days, 'n' ) ) );
check( 'trip 181: places', array( 'Miami', 'At sea', 'Puerto Plata, Dominican Republic', 'At sea', 'Bimini Beach Club', 'Miami' ) === wp_list_pluck( $days, 'place' ), json_encode( wp_list_pluck( $days, 'place' ) ) );
check( 'trip 181: arrival and departure rows of a port join one day', 2 === count( $days[2]['rows'] ) && 2 === count( $days[4]['rows'] ) );
check( 'trip 181: at-sea flags', array( false, true, false, true, false, false ) === wp_list_pluck( $days, 'at_sea' ) );
$shifted = cbv_lp_route_days( shift_dates( $it181, 365 ), '2028-10-24' );
check( 'moving the whole trip a year later keeps the same day numbers', wp_list_pluck( $days, 'n' ) === wp_list_pluck( $shifted, 'n' ) && wp_list_pluck( $days, 'place' ) === wp_list_pluck( $shifted, 'place' ) );
$nostart = cbv_lp_route_days( $it181, '' );
check( 'no start date: the earliest row date is day 1', 1 === $nostart[0]['n'] && 6 === end( $nostart )['n'] );
$late = cbv_lp_route_days( $it181, '2027-10-24' );
check( 'a start date the day before the first row makes the first row day 2', 2 === $late[0]['n'] );
$nodates = array( array( 'day' => '0', 'port' => 'Miami', 'description' => 'Embarkation' ), array( 'day' => '1', 'description' => 'At Sea' ), array( 'day' => '2', 'port' => 'Nassau', 'description' => 'Arrival' ), array( 'day' => '2', 'port' => 'Nassau', 'description' => 'Departure' ) );
$d2 = cbv_lp_route_days( $nodates, '2027-10-25' );
check( 'rows without dates are grouped by Day number, lowest Day = day 1', array( 1, 2, 3 ) === wp_list_pluck( $d2, 'n' ) && 2 === count( $d2[2]['rows'] ) && '' === $d2[0]['date'] );
$d3 = cbv_lp_route_days( array( 'junk', null, array( 'port' => 'Nowhere' ), array( 'date' => 'not-a-date', 'port' => 'X' ), array( 'date' => '2027-10-25', 'port' => 'Miami' ) ), '2027-10-25' );
check( 'junk rows and rows with no usable date or Day are skipped', 1 === count( $d3 ) && 'Miami' === $d3[0]['place'] );
check( 'empty or non-array itinerary gives no days', array() === cbv_lp_route_days( array(), '2027-10-25' ) && array() === cbv_lp_route_days( null ) && array() === cbv_lp_route_days( 'junk' ) );
$d4 = cbv_lp_route_days( array( array( 'date' => '2027-10-25', 'port' => 'Miami', 'description' => 'Departure' ), array( 'date' => '2027-10-25', 'port' => 'Key West', 'description' => 'Arrival' ), array( 'date' => '2027-10-25', 'port' => 'miami', 'description' => 'Arrival' ) ), '2027-10-25' );
check( 'two ports on one day: both named once, in order (case-insensitive)', 'Miami · Key West' === $d4[0]['place'] );
check( 'a row dated before the start date is skipped', array() === cbv_lp_route_days( array( array( 'date' => '2027-10-20', 'port' => 'X' ) ), '2027-10-25' ) );

/* ---- C. time line ---- */
section( 'C. automatic time line (approved wording)' );
$lines = array();
foreach ( $days as $i => $day ) { $lines[] = cbv_lp_route_time_line( $day, $days[ $i + 1 ] ?? null, true ); }
check( 'trip 181 as stored', array( 'Sail-away 12:01 pm', '', 'Docked 10:00 am – 6:00 pm', '', 'Docked 9:00 am – 6:00 pm', 'Back in Miami at 6:30 pm' ) === $lines, json_encode( $lines ) );
$day = function ( $rows ) { return array( 'rows' => $rows ); };
check( 'embarkation (cruise): "Sail-away 5:00 pm"', 'Sail-away 5:00 pm' === cbv_lp_route_time_line( $day( array( array( 'port' => 'Miami', 'description' => 'Embarkation', 'time' => '17:00' ) ) ) ) );
check( 'embarkation (other event types): "Departs 5:00 pm"', 'Departs 5:00 pm' === cbv_lp_route_time_line( $day( array( array( 'port' => 'Miami', 'description' => 'Embarkation', 'time' => '17:00' ) ) ), null, false ) );
check( 'embarkation with no time: no line', '' === cbv_lp_route_time_line( $day( array( array( 'port' => 'Miami', 'description' => 'Embarkation', 'time' => '' ) ) ) ) );
check( 'tender day: "Tender port · 10:00 am – 6:00 pm"', 'Tender port · 10:00 am – 6:00 pm' === cbv_lp_route_time_line( $day( array( array( 'port' => 'Bimini', 'description' => 'Arrival', 'time' => '10:00', 'tender_mode' => 'Tender' ), array( 'port' => 'Bimini', 'description' => 'Departure', 'time' => '18:00', 'tender_mode' => 'Tender' ) ) ) ) );
check( 'tender wins when only one row says Tender', 0 === strpos( cbv_lp_route_time_line( $day( array( array( 'port' => 'B', 'description' => 'Arrival', 'time' => '10:00', 'tender_mode' => 'Dock' ), array( 'port' => 'B', 'description' => 'Departure', 'time' => '18:00', 'tender_mode' => 'Tender' ) ) ) ), 'Tender port' ) );
check( 'tender wins in either row order (Tender first, then Dock)', 0 === strpos( cbv_lp_route_time_line( $day( array( array( 'port' => 'B', 'description' => 'Arrival', 'time' => '10:00', 'tender_mode' => 'Tender' ), array( 'port' => 'B', 'description' => 'Departure', 'time' => '18:00', 'tender_mode' => 'Dock' ) ) ) ), 'Tender port' ) );
check( 'tender day with no times: just "Tender port"', 'Tender port' === cbv_lp_route_time_line( $day( array( array( 'port' => 'B', 'description' => 'Arrival', 'tender_mode' => 'Tender' ), array( 'port' => 'B', 'description' => 'Departure', 'tender_mode' => 'Tender' ) ) ) ) );
check( 'Dock/Tender blank: "In port 10:00 am – 6:00 pm"', 'In port 10:00 am – 6:00 pm' === cbv_lp_route_time_line( $day( array( array( 'port' => 'B', 'description' => 'Arrival', 'time' => '10:00' ), array( 'port' => 'B', 'description' => 'Departure', 'time' => '18:00' ) ) ) ) );
check( 'other event types: just "10:00 am – 6:00 pm"', '10:00 am – 6:00 pm' === cbv_lp_route_time_line( $day( array( array( 'port' => 'B', 'description' => 'Arrival', 'time' => '10:00', 'tender_mode' => 'Dock' ), array( 'port' => 'B', 'description' => 'Departure', 'time' => '18:00', 'tender_mode' => 'Dock' ) ) ), null, false ) );
check( 'arrival and departure with one time missing', 'Docked from 10:00 am' === cbv_lp_route_time_line( $day( array( array( 'port' => 'B', 'description' => 'Arrival', 'time' => '10:00', 'tender_mode' => 'Dock' ), array( 'port' => 'B', 'description' => 'Departure', 'time' => '', 'tender_mode' => 'Dock' ) ) ) ) && 'Docked until 6:00 pm' === cbv_lp_route_time_line( $day( array( array( 'port' => 'B', 'description' => 'Arrival', 'tender_mode' => 'Dock' ), array( 'port' => 'B', 'description' => 'Departure', 'time' => '18:00', 'tender_mode' => 'Dock' ) ) ) ) );
check( 'docked day with no times: no line', '' === cbv_lp_route_time_line( $day( array( array( 'port' => 'B', 'description' => 'Arrival', 'tender_mode' => 'Dock' ), array( 'port' => 'B', 'description' => 'Departure', 'tender_mode' => 'Dock' ) ) ) ) );
check( 'arrival only: "Arrives 10:00 am"', 'Arrives 10:00 am' === cbv_lp_route_time_line( $day( array( array( 'port' => 'B', 'description' => 'Arrival', 'time' => '10:00' ) ) ) ) );
$ov1 = $day( array( array( 'port' => 'Havana', 'description' => 'Arrival', 'time' => '14:00' ) ) );
$ov2 = $day( array( array( 'port' => 'havana', 'description' => 'Departure', 'time' => '17:00' ) ) );
check( 'overnight: "Arrives 2:00 pm · overnight", next day "Departs 5:00 pm"', 'Arrives 2:00 pm · overnight' === cbv_lp_route_time_line( $ov1, $ov2 ) && 'Departs 5:00 pm' === cbv_lp_route_time_line( $ov2 ) );
check( 'not overnight when the next day leaves a different port', 'Arrives 2:00 pm' === cbv_lp_route_time_line( $ov1, $day( array( array( 'port' => 'Key West', 'description' => 'Departure', 'time' => '17:00' ) ) ) ) );
check( 'not overnight when the next day arrives again', 'Arrives 2:00 pm' === cbv_lp_route_time_line( $ov1, $day( array( array( 'port' => 'Havana', 'description' => 'Arrival', 'time' => '08:00' ), array( 'port' => 'Havana', 'description' => 'Departure', 'time' => '17:00' ) ) ) ) );
check( 'disembarkation: "Back in Miami at 6:30 am"', 'Back in Miami at 6:30 am' === cbv_lp_route_time_line( $day( array( array( 'port' => 'Miami', 'description' => 'Disembarkation', 'time' => '06:30' ) ) ) ) );
check( 'disembarkation with no time: "Back in Miami"', 'Back in Miami' === cbv_lp_route_time_line( $day( array( array( 'port' => 'Miami', 'description' => 'Disembarkation' ) ) ) ) );
check( 'at sea: no line', '' === cbv_lp_route_time_line( $day( array( array( 'description' => 'At Sea' ) ) ) ) );
check( 'rows with no type: no line', '' === cbv_lp_route_time_line( $day( array( array( 'port' => 'X', 'time' => '10:00' ) ) ) ) && '' === cbv_lp_route_time_line( array() ) );
check( 'port names in the line are plain text (escaped later), times only from valid values', 'Back in <b>X</b>' === cbv_lp_route_time_line( $day( array( array( 'port' => '<b>X</b>', 'description' => 'Disembarkation', 'time' => 'soon' ) ) ) ) );

/* ---- D. code chain ---- */
section( 'D. code chain' );
check( 'trip 181: MIA SEA POP SEA BIM MIA', array( 'MIA', 'SEA', 'POP', 'SEA', 'BIM', 'MIA' ) === cbv_lp_route_chain( $days, $it181 ), json_encode( cbv_lp_route_chain( $days, $it181 ) ) );
$one = $it181; foreach ( $one as $k => $r ) { if ( 'Bimini Beach Club' === $r['port'] ) { $one[ $k ]['stop_code'] = ''; } }
check( 'one port without any code: no chain', null === cbv_lp_route_chain( cbv_lp_route_days( $one, '2027-10-25' ), $one ) );
$share = $it181; $share[3]['stop_code'] = ''; $share[7]['stop_code'] = ''; $share[6]['stop_code'] = '';
check( 'a code typed on one row of a port covers its other rows', array( 'MIA', 'SEA', 'POP', 'SEA', 'BIM', 'MIA' ) === cbv_lp_route_chain( cbv_lp_route_days( $share, '2027-10-25' ), $share ) );
check( 'codes are cleaned (lower case typed)', array( 'MIA', 'NAS' ) === cbv_lp_route_chain( cbv_lp_route_days( array( array( 'date' => '2027-01-01', 'port' => 'Miami', 'stop_code' => 'mia' ), array( 'date' => '2027-01-02', 'port' => 'Nassau', 'stop_code' => 'n-a-s' ) ), '2027-01-01' ), array( array( 'port' => 'Miami', 'stop_code' => 'mia' ), array( 'port' => 'Nassau', 'stop_code' => 'n-a-s' ) ) ) );
check( 'a single entry is not a chain', null === cbv_lp_route_chain( array( array( 'at_sea' => false, 'ports' => array( 'Miami' ) ) ), array( array( 'port' => 'Miami', 'stop_code' => 'MIA' ) ) ) );

/* ---- E. sanitize and merge ---- */
section( 'E. sanitize and merge the day extras' );
$c = cbv_lp_sanitize_route_day( array( 'title' => ' <b>Sail-away</b> toast ', 'text' => "One\n\n<script>x</script>Two", 'sticker' => 'Tonight: <i>PJ Party</i>', 'sticker_style' => 'highlight', 'photo' => IMG, 'focus' => 'top', 'hide_time' => '1' ) );
check( 'valid values pass, tags stripped', 'Sail-away toast' === $c['title'] && 'Tonight: PJ Party' === $c['sticker'] && 'highlight' === $c['sticker_style'] && IMG === $c['photo'] && 'top' === $c['focus'] && true === $c['hide_time'] && false === strpos( $c['text'], '<script>' ) && false !== strpos( $c['text'], "\n\n" ) );
$c = cbv_lp_sanitize_route_day( array( 'sticker_style' => 'neon', 'photo' => VID, 'focus' => 'diagonal' ) );
check( 'unknown style -> standard, video as photo -> 0, unknown focus -> centre', 'standard' === $c['sticker_style'] && 0 === $c['photo'] && '' === $c['focus'] && false === $c['hide_time'] );
check( 'caps: title 80, sticker 60, text 1500', 80 === mb_strlen( cbv_lp_sanitize_route_day( array( 'title' => str_repeat( 'a', 200 ) ) )['title'] ) && 60 === mb_strlen( cbv_lp_sanitize_route_day( array( 'sticker' => str_repeat( 'a', 200 ) ) )['sticker'] ) && 1500 === mb_strlen( cbv_lp_sanitize_route_day( array( 'text' => str_repeat( 'a', 3000 ) ) )['text'] ) );
check( 'empty detection', cbv_lp_route_day_is_empty( cbv_lp_sanitize_route_day( array() ) ) && cbv_lp_route_day_is_empty( cbv_lp_sanitize_route_day( 'junk' ) ) && ! cbv_lp_route_day_is_empty( cbv_lp_sanitize_route_day( array( 'hide_time' => 1 ) ) ) && ! cbv_lp_route_day_is_empty( cbv_lp_sanitize_route_day( array( 'sticker_style' => 'highlight' ) ) ) );
$m = cbv_lp_merge_route_days( array( 2 => array( 'title' => 'Keep me' ), 7 => array( 'title' => 'Day 7, not in the itinerary now' ), 3 => array( 'title' => 'Old 3' ) ), array( 3 => array( 'title' => 'New 3' ), 2 => array( 'title' => '' ), 'x' => array( 'title' => 'bad key' ), 0 => array( 'title' => 'zero' ), 400 => array( 'title' => 'too big' ) ) );
check( 'merge: posted replaces, posted-empty removes, unposted stored kept, bad keys ignored', array( 3, 7 ) === array_keys( $m ) && 'New 3' === $m[3]['title'] && 'Day 7, not in the itinerary now' === $m[7]['title'], json_encode( array_keys( $m ) ) );
check( 'merge: stored junk (non-number keys, non-array days) is dropped', array() === cbv_lp_merge_route_days( array( 'a' => array( 'title' => 'x' ), 5 => 'junk' ), array() ) );

/* ---- F. route data ---- */
section( 'F. route data' );
$fake[ TRIP ] = trip_meta();
$d = cbv_lp_route_data( TRIP );
check( 'cruise: section name Flight path, default heading The Route, chain present', 'Flight path' === $d['section'] && 'The Route' === $d['heading'] && 6 === count( $d['chain'] ) );
check( 'six cards; eyebrow "Day 01 · Mon Oct 25 · Miami"', 6 === count( $d['cards'] ) && 'Day 01 · Mon Oct 25 · Miami' === $d['cards'][0]['eyebrow'] && 'Day 03 · Wed Oct 27 · Puerto Plata, Dominican Republic' === $d['cards'][2]['eyebrow'], $d['cards'][0]['eyebrow'] );
check( 'no extras: title is the place, time line shown, no text/sticker/photo', 'Miami' === $d['cards'][0]['title'] && 'Sail-away 12:01 pm' === $d['cards'][0]['time'] && '' === $d['cards'][0]['text'] && '' === $d['cards'][0]['sticker'] && 0 === $d['cards'][0]['photo_id'] && 'At sea' === $d['cards'][1]['title'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_route_heading' => 'Aboard {vessel}', 'cbv_lp_route_days' => array( 1 => array( 'title' => 'Sail-away toast', 'text' => 'Raise a glass on **the top deck**.', 'sticker' => 'Tonight: PJ Party on {vessel}' ), 5 => array( 'photo' => IMG, 'focus' => 'bottom', 'sticker' => 'Private cabana takeover', 'sticker_style' => 'highlight', 'hide_time' => 1 ) ) ) );
$d = cbv_lp_route_data( TRIP );
check( 'custom heading with tokens', 'Aboard Valiant Lady' === $d['heading'] );
check( 'day 1 extras applied (title, text markup, sticker tokens)', 'Sail-away toast' === $d['cards'][0]['title'] && false !== strpos( $d['cards'][0]['text'], '<strong>the top deck</strong>' ) && 'Tonight: PJ Party on Valiant Lady' === $d['cards'][0]['sticker'] && 'standard' === $d['cards'][0]['sticker_style'] );
check( 'day 5: photo, focus, highlight sticker, time line hidden but still known', IMG === $d['cards'][4]['photo_id'] && 'center bottom' === $d['cards'][4]['focus'] && 'highlight' === $d['cards'][4]['sticker_style'] && '' === $d['cards'][4]['time'] && 'Docked 9:00 am – 6:00 pm' === $d['cards'][4]['auto_time'] );
$fake[ TRIP ]['cb_itinerary'] = shift_dates( $it181, 7 );
$fake[ TRIP ]['cb_start_date'] = '2027-11-01';
$d = cbv_lp_route_data( TRIP );
check( 'trip moved a week later: the extras stay with the same days', 'Sail-away toast' === $d['cards'][0]['title'] && IMG === $d['cards'][4]['photo_id'] && 'Day 01 · Mon Nov 1 · Miami' === $d['cards'][0]['eyebrow'], $d['cards'][0]['eyebrow'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_event_type' => 'resort' ) );
$d = cbv_lp_route_data( TRIP );
check( 'resort: section name The plan, heading The Plan, non-cruise time wording', 'The plan' === $d['section'] && 'The Plan' === $d['heading'] && 'Departs 12:01 pm' === $d['cards'][0]['time'] && '10:00 am – 6:00 pm' === $d['cards'][2]['time'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_event_type' => 'corporate' ) );
check( 'corporate: Agenda / The Agenda', 'Agenda' === cbv_lp_route_data( TRIP )['section'] && 'The Agenda' === cbv_lp_route_data( TRIP )['heading'] );
$fake[ TRIP ] = trip_meta( array( 'cb_public_landing_show_itinerary' => 0 ) );
check( 'hidden when Show Itinerary is off', null === cbv_lp_route_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_sections' => array( 'route' => 'off' ) ) );
check( 'hidden when the section is switched Off', null === cbv_lp_route_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cb_itinerary' => array() ) );
check( 'hidden when the itinerary is empty', null === cbv_lp_route_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_event_type' => 'wedding' ) );
check( 'hidden by default for a wedding', null === cbv_lp_route_data( TRIP ) );

/* ---- G. markup ---- */
section( 'G. route markup' );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_route_heading' => 'The <script>alert(1)</script> Route', 'cbv_lp_route_days' => array( 1 => array( 'title' => 'Sail <b>away</b>', 'sticker' => 'Tonight: PJ Party' ), 5 => array( 'photo' => IMG, 'focus' => 'top-left', 'sticker' => 'Cabanas', 'sticker_style' => 'highlight' ), 2 => array( 'text' => "[Bad](javascript:alert(1)) and [good](https://bagsandvibes.com/)" ) ) ) );
cbv_lp_next_gate( true );
$h = cbv_lp_render_route( TRIP );
check( 'section labelled by its escaped <h2>; GATE 14 when it is the first gated section', false !== strpos( $h, 'aria-labelledby="cbv-lp-route-title"' ) && false !== strpos( $h, '<p class="cbv-lp-gate">Gate 14 · Flight path</p>' ) && false !== strpos( $h, 'The &lt;script&gt;alert(1)&lt;/script&gt; Route' ) && false === strpos( $h, '<script>' ) );
check( 'an ordered list with six day cards', 1 === substr_count( $h, '<ol class="cbv-lp-days">' ) && 6 === substr_count( $h, '<li class="cbv-lp-day">' ) );
check( 'cards without a photo get the dashed strip; the photo card gets the image with alt, lazy, focus', 5 === substr_count( $h, 'cbv-lp-day-strip' ) && false !== strpos( $h, 'alt="Pool at The Beach Club at Bimini"' ) && false !== strpos( $h, 'object-position:left top;' ) && false !== strpos( $h, 'loading="lazy"' ) );
check( 'day titles are <h3>s with tags stripped', false !== strpos( $h, '<h3 class="cbv-lp-day-title">Sail away</h3>' ) && false === strpos( $h, '<b>away' ) );
check( 'stickers carry their style class', false !== strpos( $h, 'cbv-lp-sticker--standard">Tonight: PJ Party<' ) && false !== strpos( $h, 'cbv-lp-sticker--highlight">Cabanas<' ) );
check( 'time lines shown; at-sea days have none', false !== strpos( $h, '<p class="cbv-lp-day-time">Docked 10:00 am – 6:00 pm</p>' ) && 4 === substr_count( $h, 'cbv-lp-day-time' ) );
check( 'chain: visible with arrows (hidden from screen readers) plus a plain sentence', false !== strpos( $h, '<span class="cbv-lp-sr">Route: MIA, SEA, POP, SEA, BIM, MIA</span>' ) && false !== strpos( $h, '<span aria-hidden="true">MIA → SEA → POP → SEA → BIM → MIA</span>' ) );
check( 'day text: a javascript: link is dropped, https kept', false === stripos( $h, 'javascript' ) && false !== strpos( $h, '<a href="https://bagsandvibes.com/" rel="noopener">good</a>' ) );
$fake[ TRIP ] = trip_meta( array( 'cb_itinerary' => $one ) );
check( 'no chain markup when a port has no code', false === strpos( cbv_lp_render_route( TRIP ), 'cbv-lp-route-chain' ) );
$fake[ TRIP ] = trip_meta( array( 'cb_public_landing_show_itinerary' => 0 ) );
cbv_lp_next_gate( true );
check( 'a hidden route renders nothing and does not use up a GATE number', '' === cbv_lp_render_route( TRIP ) && 14 === cbv_lp_next_gate() );

/* ---- H. legacy itinerary removal ---- */
section( 'H. legacy itinerary table removal' );
$legacy = cbv_render_public_trip_landing( 181 );
check( 'the REAL legacy page for trip 181 has an itinerary table', false !== strpos( $legacy, 'cbv-landing-itinerary' ) );
$stripped = cbv_lp_strip_legacy_itinerary( $legacy );
check( 'it is removed, with its "Itinerary" heading', false === strpos( $stripped, 'cbv-landing-itinerary' ) && false === strpos( $stripped, '<h2>Itinerary</h2>' ) );
check( 'other legacy sections stay (Highlights)', false !== strpos( $stripped, '<h2>Highlights</h2>' ) && substr_count( $legacy, 'cbv-landing-section' ) - 1 === substr_count( $stripped, 'cbv-landing-section' ) );
check( 'no table, empty or plain text: returned unchanged', '<p>Hello</p>' === cbv_lp_strip_legacy_itinerary( '<p>Hello</p>' ) && '' === cbv_lp_strip_legacy_itinerary( '' ) );

/* ---- I. page assembly (REAL trip 181) ---- */
section( 'I. page assembly' );
$page = cbv_lp_render_trip( 181, 'new_preview' );
check( 'order: hero, status board, intro, route, legacy content', strpos( $page, 'class="cbv-lp-hero"' ) < strpos( $page, 'class="cbv-lp-status"' ) && strpos( $page, 'class="cbv-lp-status"' ) < strpos( $page, 'class="cbv-lp-intro"' ) && strpos( $page, 'class="cbv-lp-intro"' ) < strpos( $page, 'class="cbv-lp-route"' ) && strpos( $page, 'class="cbv-lp-route"' ) < strpos( $page, 'cbv-lp-legacy-wrap' ) );
check( 'GATE 14 = the trip (intro), GATE 15 = flight path (route)', false !== strpos( $page, 'Gate 14 · The trip' ) && false !== strpos( $page, 'Gate 15 · Flight path' ) );
check( 'REAL trip 181 route: six days, chain MIA → SEA → POP → SEA → BIM → MIA, stored times shown as-is', 6 === substr_count( $page, '<li class="cbv-lp-day">' ) && false !== strpos( $page, 'MIA → SEA → POP → SEA → BIM → MIA' ) && false !== strpos( $page, 'Sail-away 12:01 pm' ) && false !== strpos( $page, 'Back in Miami at 6:30 pm' ) );
check( 'the old itinerary table is gone from the new page; still exactly one <h1>', false === strpos( $page, 'cbv-landing-itinerary' ) && 1 === substr_count( $page, '<h1' ) );
$fake[ 181 ] = array( 'cbv_lp_sections' => array( 'intro' => 'off' ) );
check( 'with the intro switched off, the route becomes GATE 14', false !== strpos( cbv_lp_render_trip( 181, 'new' ), 'Gate 14 · Flight path' ) );
unset( $fake[ 181 ] );

/* ---- J. admin box + save ---- */
section( 'J. admin box and save' );
function run_route_save( $post_id ) {
	global $wp_filter;
	foreach ( $wp_filter['save_post_cb_trip']->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( $fn instanceof Closure && basename( ( new ReflectionFunction( $fn ) )->getFileName() ) === 'checkedbags-lp-route.php' ) {
				$fn( $post_id );
				return true;
			}
		}
	}
	return false;
}
check( 'the save handler is registered', true === run_route_save( 999999 ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_route_heading' => 'Say "hi"', 'cbv_lp_route_days' => array( 3 => array( 'title' => 'Beach "day"', 'text' => "a\n</textarea><script>", 'sticker' => 'Group excursion', 'sticker_style' => 'highlight', 'photo' => IMG, 'focus' => 'right', 'hide_time' => 1 ) ) ) );
ob_start(); cbv_lp_render_route_box( get_post( TRIP ) ); $box = ob_get_clean();
check( 'box: nonce, heading field and six day panels', false !== strpos( $box, 'name="cbv_lp_route_nonce"' ) && false !== strpos( $box, 'name="cbv_lp_route_heading"' ) && 6 === substr_count( $box, '<fieldset' ) );
check( 'panel legend and automatic time line preview', false !== strpos( $box, 'Day 03 · Wed Oct 27 · Puerto Plata, Dominican Republic' ) && false !== strpos( $box, 'Automatic time line: <strong>Docked 10:00 am – 6:00 pm</strong>' ) && false !== strpos( $box, 'Automatic time line: <em>none</em>' ) );
check( 'saved values shown and escaped; style, focus and checkbox selected', false !== strpos( $box, 'value="Say &quot;hi&quot;"' ) && false !== strpos( $box, 'value="Beach &quot;day&quot;"' ) && false === strpos( $box, '</textarea><script>' ) && 1 === preg_match( '/<option value="highlight"\s+selected=\'selected\'/', $box ) && 1 === preg_match( '/<option value="right"\s+selected=\'selected\'/', $box ) && 1 === preg_match( '/name="cbv_lp_route_days\[3\]\[hide_time\]" value="1"\s+checked=\'checked\'/', $box ) && false !== strpos( $box, 'bimini.jpg' ) );
check( 'heading help shows the event type default; media rule present', false !== strpos( $box, 'Leave blank to use "The Route"' ) && false !== strpos( $box, 'First Mates' ) );
$fake[ TRIP ] = trip_meta( array( 'cb_itinerary' => array() ) );
ob_start(); cbv_lp_render_route_box( get_post( TRIP ) ); $box = ob_get_clean();
check( 'no itinerary: the box says so and has no day panels', false !== strpos( $box, 'No itinerary days yet' ) && false === strpos( $box, '<fieldset' ) );

wp_set_current_user( 1 );
$writes = array();
$_POST = array( 'cbv_lp_route_nonce' => 'bad', 'cbv_lp_route_heading' => 'x' );
run_route_save( TRIP );
check( 'bad nonce: nothing written', array() === $writes );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_route_days' => array( 9 => array( 'title' => 'Day 9 from an old itinerary' ), 2 => array( 'title' => 'Old 2' ) ) ) );
$writes = array();
$_POST = array( 'cbv_lp_route_nonce' => wp_create_nonce( 'cbv_lp_route_save' ), 'cbv_lp_route_heading' => ' <b>Our</b> route ', 'cbv_lp_route_days' => array( 1 => array( 'title' => 'Sail-away toast', 'sticker' => 'Tonight', 'sticker_style' => 'standard', 'photo' => '', 'focus' => '' ), 2 => array( 'title' => '', 'text' => '', 'sticker' => '', 'sticker_style' => 'standard', 'photo' => '', 'focus' => '' ), 3 => array( 'photo' => (string) VID, 'hide_time' => '1', 'sticker_style' => 'standard' ) ) );
run_route_save( TRIP );
$by = array(); foreach ( $writes as $w ) { if ( 'update_post_metadata' === $w[0] ) { $by[ $w[2] ] = $w[3]; } }
check( 'valid save: heading cleaned', 'Our route' === $by['cbv_lp_route_heading'] );
check( 'valid save: day 1 stored, day 2 emptied and removed, day 3 keeps hide_time but refuses the video, day 9 kept', array( 1, 3, 9 ) === array_keys( $by['cbv_lp_route_days'] ) && 'Sail-away toast' === $by['cbv_lp_route_days'][1]['title'] && 0 === $by['cbv_lp_route_days'][3]['photo'] && true === $by['cbv_lp_route_days'][3]['hide_time'], json_encode( $by['cbv_lp_route_days'] ) );
check( 'only its own two keys are written', 2 === count( $by ) );
$low = 0;
foreach ( get_users( array( 'number' => 40, 'fields' => 'ID', 'role__not_in' => array( 'administrator' ) ) ) as $uid ) {
	if ( ! user_can( (int) $uid, 'edit_post', 181 ) ) { $low = (int) $uid; break; }
}
check( 'found a real user without edit rights for the capability test', $low > 0 );
wp_set_current_user( $low );
$_POST = array( 'cbv_lp_route_nonce' => wp_create_nonce( 'cbv_lp_route_save' ), 'cbv_lp_route_heading' => 'Hijack' );
check( 'the nonce really is valid for that user', false !== wp_verify_nonce( $_POST['cbv_lp_route_nonce'], 'cbv_lp_route_save' ) );
$writes = array();
run_route_save( 181 );
check( 'a user who cannot edit the trip writes nothing even with a valid nonce', array() === $writes );
wp_set_current_user( 0 );
$_POST = array();

/* ---- K. what Step 6 must NOT do ---- */
section( 'K. no side effects' );
check( 'the current (legacy) public renderer still has its itinerary table', false !== strpos( cbv_render_public_trip_landing( 181 ), 'cbv-landing-itinerary' ) );
check( 'master switch still off', ! get_option( 'cbv_lp_redesign_live' ) );
check( 'Step 4 boarding pass route unchanged for trip 181 (MIA / VIA POP / BIM)', false !== strpos( cbv_lp_render_hero( 181 ), '>MIA<' ) && false !== strpos( cbv_lp_render_hero( 181 ), 'VIA POP' ) && false !== strpos( cbv_lp_render_hero( 181 ), '>BIM<' ) );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
