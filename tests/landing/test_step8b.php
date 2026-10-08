<?php
/*
 * Step 8b tests: CBGV Group Experience Fee (settings, trip choice, live switch, breakdown, board, note, proposal PDF).
 * Run against a temp COPY of the mu-plugins folder with the Step 8b files overlaid:
 *   wp --require=/tmp/cbv_t/define.php eval-file test_step8b.php
 * NO database writes: fake posts live in the in-memory object cache, meta and options are faked by filters,
 * and every write is intercepted. Set CBV_VERBOSE=1 to print every passing check.
 * (wp eval-file runs this file inside a function: data shared with helper functions goes through $GLOBALS.)
 */
$GLOBALS['n'] = 0; $GLOBALS['fail'] = 0;
function check( $l, $c, $d = '' ) {
	$GLOBALS['n']++;
	if ( ! $c ) { $GLOBALS['fail']++; echo "FAIL: $l $d\n"; }
	elseif ( getenv( 'CBV_VERBOSE' ) ) { echo '  pass ' . str_pad( $GLOBALS['n'], 3, ' ', STR_PAD_LEFT ) . ": $l\n"; }
}
function section( $s ) { if ( getenv( 'CBV_VERBOSE' ) ) { echo "\n=== $s ===\n"; } }

check( 'running against the temp mu-plugins copy', '/tmp/cbv_mu' === WPMU_PLUGIN_DIR );
check( 'Step 8b code is loaded', function_exists( 'cbv_lp_planning_fee' ) && function_exists( 'cbv_lp_fee_for_cabin' ) && function_exists( 'cbv_lp_planning_fee_shown' ) && function_exists( 'cbv_lp_planning_fee_note' ) );

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
// Options: faked (never written).
$GLOBALS['opt_fees'] = false; $GLOBALS['opt_live'] = false;
add_filter( 'pre_option_cbv_lp_planning_fees', function () { return $GLOBALS['opt_fees']; } );
add_filter( 'pre_option_cbv_lp_planning_fee_live', function () { return $GLOBALS['opt_live']; } );
add_filter( 'pre_update_option_cbv_lp_planning_fees', function ( $new, $old ) { $GLOBALS['opt_writes'][] = $new; return $old; }, 10, 2 );
function fake_post( $id, $type, $status, $title ) {
	$p = new WP_Post( (object) array( 'ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_title' => $title, 'post_author' => 1, 'post_name' => sanitize_title( $title ), 'post_date' => '2026-01-01 00:00:00', 'post_date_gmt' => '2026-01-01 00:00:00', 'post_content' => '', 'post_excerpt' => '', 'post_mime_type' => '', 'filter' => 'raw' ) );
	wp_cache_set( $id, $p, 'posts' );
	return $p;
}
const TRIP = 999650;
fake_post( TRIP, 'cb_trip', 'publish', 'Fee Voyage' );
function pt( $n, $fare, $tax, $grat, $ins, $disc, $basis = 'per_cabin', $col = '' ) {
	return array( 'occupancy_count' => $n, 'voyage_fare' => $fare, 'taxes_fees' => $tax, 'gratuities' => $grat, 'insurance' => $ins, 'discount' => $disc, 'pricing_basis' => $basis, 'price_column' => $col );
}
$GLOBALS['tiers'] = array(
	array( 'name' => 'The Insider', 'capacity_low' => 2, 'capacity_high' => 3, 'occupancy_points' => array( pt( 2, 2522, 402, 200, 152, 724 ), pt( 3, 3000, 603, 300, 200, 0, 'per_cabin', '2' ) ), 'addons' => array() ),
);
function trip_meta( $extra = array() ) {
	return array_merge( array( 'cbv_lp_event_type' => 'cruise', 'cbv_lp_sections' => array(), 'cbv_lp_provider_id' => 0, 'cb_pricing_tiers' => $GLOBALS['tiers'], 'cbv_lp_planning_fee' => array(), 'cb_start_date' => '2027-10-25' ), $extra );
}
$all250 = array();
foreach ( array_keys( cbv_lp_event_types() ) as $t ) { $all250[ $t ] = array( 'amount' => 250, 'basis' => 'per_traveler' ); }
$GLOBALS['all250'] = $all250;

/* ---- A. cleaning ---- */
section( 'A. cleaning input' );
check( 'amounts: plain, with $ and commas, cents rounded', 250.0 === cbv_lp_clean_fee_amount( '250' ) && 250.0 === cbv_lp_clean_fee_amount( '$250.00' ) && 1250.5 === cbv_lp_clean_fee_amount( '1,250.50' ) && 250.56 === cbv_lp_clean_fee_amount( '250.555' ) );
check( 'amounts: blank, junk, negative, arrays become 0; capped at 100,000', 0.0 === cbv_lp_clean_fee_amount( '' ) && 0.0 === cbv_lp_clean_fee_amount( 'abc' ) && 0.0 === cbv_lp_clean_fee_amount( '-5' ) && 0.0 === cbv_lp_clean_fee_amount( array( 5 ) ) && 100000.0 === cbv_lp_clean_fee_amount( '250000' ) );
check( 'basis: the three allowed, anything else is per traveler', 'per_cabin' === cbv_lp_clean_fee_basis( 'per_cabin' ) && 'per_booking' === cbv_lp_clean_fee_basis( 'per_booking' ) && 'per_traveler' === cbv_lp_clean_fee_basis( 'per_party' ) && 'per_traveler' === cbv_lp_clean_fee_basis( null ) );
$st = cbv_lp_sanitize_planning_fees( array( 'cruise' => array( 'amount' => '$250', 'basis' => 'per_traveler' ), 'resort' => array( 'amount' => '100', 'basis' => 'per_cabin' ), 'spaceship' => array( 'amount' => '9' ), 'wedding' => 'junk' ) );
check( 'settings: one row per event type (unknown types dropped, missing rows blank)', array_keys( cbv_lp_event_types() ) === array_keys( $st ) && ! isset( $st['spaceship'] ) && 0.0 === $st['wedding']['amount'] && 250.0 === $st['cruise']['amount'] && 'per_cabin' === $st['resort']['basis'] );
check( 'trip box: modes and their values', array( 'mode' => 'custom', 'amount' => 199.0, 'basis' => 'per_booking' ) === cbv_lp_sanitize_trip_fee( array( 'mode' => 'custom', 'amount' => '199', 'basis' => 'per_booking' ) ) && array( 'mode' => 'none', 'amount' => 0.0, 'basis' => 'per_traveler' ) === cbv_lp_sanitize_trip_fee( array( 'mode' => 'none', 'amount' => '500' ) ) && 'standard' === cbv_lp_sanitize_trip_fee( array( 'mode' => 'hack' ) )['mode'] && 'standard' === cbv_lp_sanitize_trip_fee( 'junk' )['mode'] );

/* ---- B. fee for one cabin ---- */
section( 'B. fee for one cabin' );
$pt250 = array( 'amount' => 250.0, 'basis' => 'per_traveler' );
check( 'per traveler: x headcount (2 = $500, 3 = $750; 0 counts as 1)', 500.0 === cbv_lp_fee_for_cabin( $pt250, 2 ) && 750.0 === cbv_lp_fee_for_cabin( $pt250, 3 ) && 250.0 === cbv_lp_fee_for_cabin( $pt250, 0 ) );
check( 'per cabin and flat per booking: once per cabin', 150.0 === cbv_lp_fee_for_cabin( array( 'amount' => 150, 'basis' => 'per_cabin' ), 4 ) && 99.5 === cbv_lp_fee_for_cabin( array( 'amount' => 99.5, 'basis' => 'per_booking' ), 3 ) );
check( 'no fee, $0, junk: 0', 0.0 === cbv_lp_fee_for_cabin( null, 2 ) && 0.0 === cbv_lp_fee_for_cabin( array( 'amount' => 0 ), 2 ) && 0.0 === cbv_lp_fee_for_cabin( 'junk', 2 ) );

/* ---- C. the trip's fee ---- */
section( 'C. the trip\'s fee' );
$fake[ TRIP ] = trip_meta();
check( 'nothing set anywhere: no fee', array( 'amount' => 0.0, 'basis' => 'per_traveler', 'source' => 'none' ) === cbv_lp_planning_fee( TRIP ) );
$GLOBALS['opt_fees'] = $all250;
check( "standard fee for the trip's event type ($250 per traveler)", array( 'amount' => 250.0, 'basis' => 'per_traveler', 'source' => 'standard' ) === cbv_lp_planning_fee( TRIP ) );
$GLOBALS['opt_fees'] = array_merge( $all250, array( 'resort' => array( 'amount' => 80, 'basis' => 'per_cabin' ) ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_event_type' => 'resort' ) );
check( 'a resort trip uses the resort row', 80.0 === cbv_lp_planning_fee( TRIP )['amount'] && 'per_cabin' === cbv_lp_planning_fee( TRIP )['basis'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_planning_fee' => array( 'mode' => 'custom', 'amount' => 199, 'basis' => 'per_booking' ) ) );
check( 'a different fee for this trip wins', array( 'amount' => 199.0, 'basis' => 'per_booking', 'source' => 'trip' ) === cbv_lp_planning_fee( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_planning_fee' => array( 'mode' => 'custom', 'amount' => 0 ) ) );
check( 'a different fee of $0 means no fee', 'none' === cbv_lp_planning_fee( TRIP )['source'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_planning_fee' => array( 'mode' => 'none' ) ) );
check( '"No Group Experience Fee on this trip" waives the standard fee', array( 'amount' => 0.0, 'basis' => 'per_traveler', 'source' => 'none' ) === cbv_lp_planning_fee( TRIP ) );
$GLOBALS['opt_fees'] = 'junk';
$fake[ TRIP ] = trip_meta();
check( 'a broken settings value: no fee', 0.0 === cbv_lp_planning_fee( TRIP )['amount'] );

/* ---- D. the live switch ---- */
section( 'D. not public until live' );
$GLOBALS['opt_fees'] = $all250; $GLOBALS['opt_live'] = false;
$fake[ TRIP ] = trip_meta();
wp_set_current_user( 0 ); unset( $_GET['preview'] );
check( 'not live: no fee on the page for a visitor, none in the PDF', null === cbv_lp_planning_fee_shown( TRIP, 'page' ) && null === cbv_lp_planning_fee_shown( TRIP, 'pdf' ) );
wp_set_current_user( 1 ); $_GET['preview'] = 'new';
check( "not live: an admin's ?preview=new shows it on the page, but never in the PDF", 250.0 === cbv_lp_planning_fee_shown( TRIP, 'page' )['amount'] && null === cbv_lp_planning_fee_shown( TRIP, 'pdf' ) );
unset( $_GET['preview'] );
check( 'not live: an admin without ?preview=new does not see it', null === cbv_lp_planning_fee_shown( TRIP, 'page' ) );
wp_set_current_user( 0 ); $_GET['preview'] = 'new';
check( 'not live: a visitor typing ?preview=new does not see it', null === cbv_lp_planning_fee_shown( TRIP, 'page' ) && array() === cbv_lp_planning_fee_note( TRIP ) );
unset( $_GET['preview'] );
$GLOBALS['opt_live'] = '1';
check( 'live: shown on the page and in the PDF', 250.0 === cbv_lp_planning_fee_shown( TRIP, 'page' )['amount'] && 250.0 === cbv_lp_planning_fee_shown( TRIP, 'pdf' )['amount'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_planning_fee' => array( 'mode' => 'none' ) ) );
check( 'live but waived: nothing shown anywhere', null === cbv_lp_planning_fee_shown( TRIP, 'page' ) && null === cbv_lp_planning_fee_shown( TRIP, 'pdf' ) );

/* ---- E. the all-in breakdown ---- */
section( 'E. the all-in breakdown' );
$no  = cbv_lp_point_breakdown( pt( 2, 2522, 402, 200, 152, 724 ) );
$yes = cbv_lp_point_breakdown( pt( 2, 2522, 402, 200, 152, 724 ), $pt250 );
check( 'no fee: exactly the Step 8 result (four parts, $2,552)', array( 'cruise_fare', 'taxes_fees', 'gratuities', 'protection' ) === array_keys( $no['parts'] ) && 2552.0 === $no['total'] && 1276.0 === $no['per_person'] );
check( '$250 per traveler, cabin for 2: a fifth part of $500, total $3,052, per person $1,526', 500.0 === $yes['parts']['planning_fee'] && 3052.0 === $yes['total'] && 1526.0 === $yes['per_person'], json_encode( $yes ) );
check( 'the cruise fare is never changed by the fee', $no['parts']['cruise_fare'] === $yes['parts']['cruise_fare'] && 1798.0 === $yes['parts']['cruise_fare'] );
check( 'the five parts add up to the total', abs( array_sum( $yes['parts'] ) - $yes['total'] ) < 0.005 );
$pp = cbv_lp_point_breakdown( pt( 3, 3000, 600, 300, 200, 0, 'per_person' ), $pt250 );
check( 'per-person amounts for 3: fee $750, added once to the cabin total', 750.0 === $pp['parts']['planning_fee'] && abs( $pp['total'] - ( cb_pricing_occupancy_point_cabin_total( pt( 3, 3000, 600, 300, 200, 0, 'per_person' ) ) + 750 ) ) < 0.005 );
$book = cbv_lp_point_breakdown( pt( 2, 2522, 402, 200, 152, 724 ), array( 'amount' => 100, 'basis' => 'per_booking' ) );
check( 'flat per booking: once in the cabin price ($100)', 100.0 === $book['parts']['planning_fee'] && 2652.0 === $book['total'] );
check( 'breakdown line: fifth item "CBGV Group Experience Fee $500"', 'All-in for 2 travelers: cruise fare $1,798 · taxes & fees $402 · prepaid gratuities $200 · Voyage Protection $152 · CBGV Group Experience Fee $500' === cbv_lp_price_breakdown_line( $yes ), cbv_lp_price_breakdown_line( $yes ) );
check( 'breakdown line with no fee: unchanged (four items)', false === strpos( cbv_lp_price_breakdown_line( $no ), 'Experience' ) );
check( 'a discount bigger than everything still never goes negative, but the fee is still added', 500.0 === cbv_lp_point_breakdown( pt( 2, 100, 0, 0, 0, 900 ), $pt250 )['total'] );

$GLOBALS['note_all'] = array();
/* ---- F. the board and its note ---- */
section( 'F. the board and its note' );
$GLOBALS['opt_fees'] = $all250; $GLOBALS['opt_live'] = '1';
$fake[ TRIP ] = trip_meta();
$d = cbv_lp_price_board_data( TRIP );
check( 'every cell includes the fee for its headcount; "from" includes it', 3052.0 === $d['groups'][0]['rows'][0]['cells'][1]['total'] && 4853.0 === $d['groups'][0]['rows'][0]['cells'][2]['total'] && 3052.0 === $d['groups'][0]['from'], json_encode( $d['groups'][0]['rows'][0]['cells'] ) );
check( 'the board says which fee it used', is_array( $d['fee'] ) && 250.0 === $d['fee']['amount'] );
add_filter( 'cbv_lp_key_dates', $kd = function ( $dates, $trip_id ) { if ( TRIP === (int) $trip_id ) { $dates['final_payment'] = 'June 27, 2027'; } return $dates; }, 99, 2 );
$note = cbv_lp_planning_fee_note( TRIP ); $GLOBALS['note_all'] = array_merge( $GLOBALS['note_all'], $note );
check( 'note: the approved compliance wording, with the trip\'s final payment date, as one paragraph', array( 'The CBGV Group Experience Fee is paid to Checked Bags & Good Vibes (a d/b/a of JourneyWell Global LLC) for the group program; travel payments go directly to the cruise line or supplier. It\'s fully refundable within 7 days of paying, 50% refundable until June 27, 2027, and non-refundable after that. Full refund if the trip is cancelled.' ) === $note, json_encode( $note ) );
check( 'note: names JourneyWell Global LLC and never says CBGV books anything', false !== strpos( $note[0], '(a d/b/a of JourneyWell Global LLC)' ) && false === stripos( implode( ' ', $note ), 'CBGV books' ) );
check( 'note is on the board data', $note === cbv_lp_price_board_data( TRIP )['fee_note'] );
remove_filter( 'cbv_lp_key_dates', $kd, 99 );
$note = cbv_lp_planning_fee_note( TRIP ); $GLOBALS['note_all'] = array_merge( $GLOBALS['note_all'], $note );
check( "no final payment date: \"...until the trip's final payment date...\"", false !== strpos( end( $note ), "50% refundable until the trip's final payment date, and non-refundable after that." ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_event_type' => 'resort' ) );
$note = cbv_lp_planning_fee_note( TRIP ); $GLOBALS['note_all'] = array_merge( $GLOBALS['note_all'], $note );
check( 'note: the same wording for a resort trip', array( 'The CBGV Group Experience Fee is paid to Checked Bags & Good Vibes (a d/b/a of JourneyWell Global LLC) for the group program; travel payments go directly to the cruise line or supplier. It\'s fully refundable within 7 days of paying, 50% refundable until the trip\'s final payment date, and non-refundable after that. Full refund if the trip is cancelled.' ) === $note );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_planning_fee' => array( 'mode' => 'custom', 'amount' => 100, 'basis' => 'per_booking' ) ) );
$note = cbv_lp_planning_fee_note( TRIP ); $GLOBALS['note_all'] = array_merge( $GLOBALS['note_all'], $note );
check( 'flat per booking: the "one fee per booking" line after the note', 2 === count( $note ) && 0 === strpos( $note[0], 'The CBGV Group Experience Fee is paid' ) && 'One fee per booking, however many cabins you book together.' === $note[1] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_planning_fee' => array( 'mode' => 'none' ) ) );
check( 'waived: no note and no fee on the board', array() === cbv_lp_planning_fee_note( TRIP ) && null === cbv_lp_price_board_data( TRIP )['fee'] && 2552.0 === cbv_lp_price_board_data( TRIP )['groups'][0]['rows'][0]['cells'][1]['total'] );
$GLOBALS['opt_live'] = false;
$fake[ TRIP ] = trip_meta();
check( 'not live, visitor: the board has no fee (prices as in Step 8)', null === cbv_lp_price_board_data( TRIP )['fee'] && 2552.0 === cbv_lp_price_board_data( TRIP )['groups'][0]['rows'][0]['cells'][1]['total'] && array() === cbv_lp_price_board_data( TRIP )['fee_note'] );

/* ---- G. proposal PDF ---- */
section( 'G. proposal PDF' );
$trip_pdf = array( 'pricing_tiers' => $GLOBALS['tiers'], 'single_price' => 0, 'price_columns' => array( 'Base', 'Essential', '', '' ) );
$h0 = cb_proposal_render_pricing_html( $trip_pdf + array( 'planning_fee' => null ) );
$hn = cb_proposal_render_pricing_html( $trip_pdf );
check( 'no fee: no fee column, totals as before ($2,552.00 per cabin)', false === strpos( $h0, 'Experience Fee' ) && false !== strpos( $h0, '$2,552.00' ) && $h0 === $hn );
$h1 = cb_proposal_render_pricing_html( $trip_pdf + array( 'planning_fee' => $pt250 ) );
check( 'with a fee: a "CBGV Group Experience Fee / Cabin" column after Discount', false !== strpos( $h1, '<th>Discount</th><th>CBGV Group Experience Fee / Cabin</th><th>Basis</th>' ) );
check( 'with a fee: the fee cell and both totals include it ($500; $3,052.00 per cabin; $1,526.00 per person)', false !== strpos( $h1, '<td>$500.00</td><td>Per Cabin</td><td><strong>$1,526.00</strong></td><td>$3,052.00</td>' ), $h1 );
check( 'with a fee, for 3 travelers: $750 fee, $4,853.00 per cabin', false !== strpos( $h1, '<td>$750.00</td>' ) && false !== strpos( $h1, '$4,853.00' ) );
check( 'with a fee and a tagged price: Fare and fee columns together', false !== strpos( $h1, '<th>Fare</th><th># Sailors</th>' ) && 11 === substr_count( explode( '</thead>', $h1 )[0], '<th>' ) );
$GLOBALS['opt_fees'] = $all250; $GLOBALS['opt_live'] = false;
$fake[ TRIP ] = trip_meta();
check( 'not live: the PDF data carries no fee', null === cbv_lp_planning_fee_shown( TRIP, 'pdf' ) );
$src = file_get_contents( WPMU_PLUGIN_DIR . '/checkedbags-proposal-pdf.php' );
check( "the PDF builder takes the fee only through the 'pdf' (live-only) check", 1 === substr_count( $src, "cbv_lp_planning_fee_shown( \$trip_id, 'pdf' )" ) );

/* ---- H. admin screens and saving ---- */
section( 'H. admin screens and saving' );
$GLOBALS['opt_fees'] = $all250; $GLOBALS['opt_live'] = false;
wp_set_current_user( 1 );
ob_start(); cbv_lp_render_fees_page(); $page = ob_get_clean(); $page_admin = $page;
check( 'settings page: a row per event type with amount and basis, saved values shown', count( cbv_lp_event_types() ) === substr_count( $page, '[amount]"' ) && false !== strpos( $page, 'name="cbv_lp_planning_fees[cruise][amount]" value="250.00"' ) && false !== strpos( $page, 'name="cbv_lp_planning_fees[corporate][basis]"' ) );
check( 'settings page: the live switch, unticked, with the go-live warning', 1 === preg_match( '/name="cbv_lp_planning_fee_live" value="1"\s*>/', $page ) && false !== strpos( $page, 'Seller-of-Travel' ) && false !== strpos( $page, 'option_page' ) );
wp_set_current_user( 0 );
ob_start(); cbv_lp_render_fees_page(); $page = ob_get_clean();
check( 'settings page: nothing for a non-admin', '' === trim( $page ) );
wp_set_current_user( 1 );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_planning_fee' => array( 'mode' => 'custom', 'amount' => 199, 'basis' => 'per_cabin' ) ) );
ob_start(); cbv_lp_render_trip_fee_box( get_post( TRIP ) ); $box = ob_get_clean();
check( 'trip box: three choices, "different fee" selected with its values, standard described in words', 1 === preg_match( '/value="custom"\s+checked=\'checked\'/', $box ) && false !== strpos( $box, 'value="none"' ) && false !== strpos( $box, 'value="199.00"' ) && 1 === preg_match( '/<option value="per_cabin"\s+selected=\'selected\'/', $box ) && false !== strpos( $box, 'Cruise: $250 per traveler' ) && false !== strpos( $box, 'not live yet' ) );
check( 'old name gone from every screen: settings page, trip box, PDF column, note, breakdown line', false === stripos( $page_admin . $box . $h1 . implode( ' ', $GLOBALS['note_all'] ) . cbv_lp_price_breakdown_line( $yes ), 'planning fee' ) );
function run_fee_save( $post_id ) {
	global $wp_filter;
	foreach ( $wp_filter['save_post_cb_trip']->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( $fn instanceof Closure && basename( ( new ReflectionFunction( $fn ) )->getFileName() ) === 'checkedbags-lp-fees.php' ) { $fn( $post_id ); return true; }
		}
	}
	return false;
}
check( 'the trip save handler is registered', true === run_fee_save( 999999 ) );
$writes = array();
$_POST = array( 'cbv_lp_fee_nonce' => 'bad', 'cbv_lp_planning_fee' => array( 'mode' => 'none' ) );
run_fee_save( TRIP );
check( 'bad nonce: nothing written', array() === $writes );
$writes = array();
$_POST = array( 'cbv_lp_fee_nonce' => wp_create_nonce( 'cbv_lp_fee_save' ), 'cbv_lp_planning_fee' => array( 'mode' => 'custom', 'amount' => '$1,250.50', 'basis' => 'per_booking' ) );
run_fee_save( TRIP );
$by = array(); foreach ( $writes as $w ) { if ( 'update_post_metadata' === $w[0] ) { $by[ $w[2] ] = $w[3]; } }
check( 'admin save: stored cleaned, only its own key', array( 'cbv_lp_planning_fee' => array( 'mode' => 'custom', 'amount' => 1250.5, 'basis' => 'per_booking' ) ) === $by, json_encode( $by ) );
// A real non-admin who CAN edit trips (an editor), with a nonce valid for them: still cannot set the fee.
$ed = 0;
foreach ( get_users( array( 'number' => 50, 'fields' => 'ID', 'role__not_in' => array( 'administrator' ) ) ) as $uid ) {
	if ( ! user_can( (int) $uid, 'manage_options' ) ) { $ed = (int) $uid; break; }
}
check( 'found a real non-admin user for the permission test', $ed > 0 );
// Give that user every capability EXCEPT manage_options (in memory only), so only the admin check can stop the save.
add_filter( 'user_has_cap', $grant = function ( $all, $caps, $args, $user ) use ( $ed ) {
	if ( (int) $user->ID === $ed ) { foreach ( $caps as $c ) { $all[ $c ] = 'manage_options' !== $c; } }
	return $all;
}, 99, 4 );
wp_set_current_user( $ed );
check( 'that user can edit trip 181 but is not an admin', current_user_can( 'edit_post', 181 ) && ! current_user_can( 'manage_options' ) );
$_POST = array( 'cbv_lp_fee_nonce' => wp_create_nonce( 'cbv_lp_fee_save' ), 'cbv_lp_planning_fee' => array( 'mode' => 'none' ) );
$writes = array();
run_fee_save( 181 );
check( 'a non-admin who can edit the trip writes nothing, even with a valid nonce', array() === $writes );
remove_filter( 'user_has_cap', $grant, 99 );
wp_set_current_user( 0 );
$_POST = array();

/* ---- I. what Step 8b must NOT do ---- */
section( 'I. no side effects' );
$GLOBALS['opt_fees'] = $all250; $GLOBALS['opt_live'] = '1';
$t181 = cb_trip_get_pricing_tiers( 181 );
check( 'even with a live $250 fee: Gate 07 / legacy price range for trip 181 unchanged', cb_trip_get_price_range( 181 )['low'] === min( array_map( 'cb_pricing_occupancy_point_total', array_merge( ...array_map( function ( $t ) { return $t['occupancy_points']; }, $t181 ) ) ) ) );
check( "even with a live fee: today's landing page shows the old prices (From \$1,276 / person for The Insider)", false !== strpos( cbv_render_public_trip_landing( 181 ), '1,276' ) );
$GLOBALS['opt_fees'] = false; $GLOBALS['opt_live'] = false;
check( 'REAL site today (no fee set): trip 181 has no fee and its board prices are as in Step 8', null === cbv_lp_price_board_data( 181 )['fee'] && 2552.0 === cbv_lp_price_board_data( 181 )['groups'][0]['rows'][0]['cells'][1]['total'] );
remove_all_filters( 'pre_option_cbv_lp_planning_fees' ); remove_all_filters( 'pre_option_cbv_lp_planning_fee_live' );
check( 'the real options are not set (nothing written by this build)', false === get_option( 'cbv_lp_planning_fees', false ) && ! get_option( 'cbv_lp_planning_fee_live' ) );
check( 'master switch still off', ! get_option( 'cbv_lp_redesign_live' ) );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
