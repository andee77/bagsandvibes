<?php
/*
 * Step 8 tests: price board data (Pricing Tiers tags, price columns, all-in breakdown, board structure, proposal PDF Fare column).
 * Run against a temp COPY of the mu-plugins folder with the Step 8 files overlaid:
 *   wp --require=/tmp/cbv_t/define.php eval-file test_step8.php
 * NO database writes: fake posts live in the in-memory object cache, meta is faked by a get_post_metadata filter,
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
check( 'Step 8 code is loaded', function_exists( 'cbv_lp_price_board_data' ) && function_exists( 'cb_sanitize_pricing_tiers' ) && function_exists( 'cbv_lp_point_breakdown' ) && 2 === CBV_LP_PRICE_HEADCOUNT );

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
function fake_post( $id, $type, $status, $title ) {
	$p = new WP_Post( (object) array( 'ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_title' => $title, 'post_author' => 1, 'post_name' => sanitize_title( $title ), 'post_date' => '2026-01-01 00:00:00', 'post_date_gmt' => '2026-01-01 00:00:00', 'post_content' => '', 'post_excerpt' => '', 'post_mime_type' => '', 'filter' => 'raw' ) );
	wp_cache_set( $id, $p, 'posts' );
	return $p;
}
const TRIP = 999450; const PROV = 999451; const PROV2 = 999452;
fake_post( TRIP, 'cb_trip', 'publish', 'Test Voyage' );
fake_post( PROV, 'cb_provider', 'publish', 'Virgin-like provider' );
fake_post( PROV2, 'cb_provider', 'publish', 'No-columns provider' );
$fake[ PROV ] = array( 'cbv_lp_columns' => array( 'Base', 'Essential', 'Premium', '' ) );
$fake[ PROV2 ] = array( 'cbv_lp_columns' => array( '', '', '', '' ) );

function pt( $n, $fare, $tax, $grat, $ins, $disc, $basis = 'per_cabin', $col = '' ) {
	return array( 'occupancy_count' => $n, 'voyage_fare' => $fare, 'taxes_fees' => $tax, 'gratuities' => $grat, 'insurance' => $ins, 'discount' => $disc, 'pricing_basis' => $basis, 'price_column' => $col );
}

/* ---- A. price columns ---- */
section( 'A. price columns' );
$fake[ TRIP ] = array( 'cbv_lp_provider_id' => PROV, 'cbv_lp_columns' => array() );
check( 'inherited from the provider', array( 'Base', 'Essential', 'Premium', '' ) === cbv_lp_price_columns( TRIP ), json_encode( cbv_lp_price_columns( TRIP ) ) );
$fake[ TRIP ] = array( 'cbv_lp_provider_id' => PROV, 'cbv_lp_columns' => array( 'Standard', 'Plus', '', '' ) );
check( "the trip's own columns replace the provider's", array( 'Standard', 'Plus', '', '' ) === cbv_lp_price_columns( TRIP ) );
$fake[ TRIP ] = array( 'cbv_lp_provider_id' => PROV2 );
check( 'none anywhere: column 1 is "Price"', array( 'Price', '', '', '' ) === cbv_lp_price_columns( TRIP ) );
$fake[ TRIP ] = array( 'cbv_lp_provider_id' => 0 );
check( 'no provider: column 1 is "Price"', 'Price' === cbv_lp_price_columns( TRIP )[0] );
check( 'point column: blank/missing/unknown = 1, 2-4 kept, off = 0', 1 === cbv_lp_point_column( array() ) && 1 === cbv_lp_point_column( array( 'price_column' => '' ) ) && 1 === cbv_lp_point_column( array( 'price_column' => '9' ) ) && 1 === cbv_lp_point_column( array( 'price_column' => array( 2 ) ) ) && 3 === cbv_lp_point_column( array( 'price_column' => '3' ) ) && 0 === cbv_lp_point_column( array( 'price_column' => 'off' ) ) && 1 === cbv_lp_point_column( 'junk' ) );
$ch = cbv_lp_price_column_choices( array( 'Base', 'Essential', 'Premium', '' ) );
check( 'editor choices: column 1 is the blank value, named columns, blank names skipped, off last', array( '', '2', '3', 'off' ) === array_map( 'strval', array_keys( $ch ) ) && 'Column 1: Base' === $ch[''] && 'Column 3: Premium' === $ch['3'] && 'Not on the price board' === end( $ch ), json_encode( $ch ) );
check( 'editor choices with no names: "Column 1: Price" and off only', array( '' => 'Column 1: Price', 'off' => 'Not on the price board' ) === cbv_lp_price_column_choices( array() ) );
check( 'PDF fare label: name, "Price" fallback, "Not on price board"', 'Essential' === cbv_lp_point_fare_label( array( 'price_column' => '2' ), array( 'Base', 'Essential' ) ) && 'Price' === cbv_lp_point_fare_label( array(), array() ) && 'Column 3' === cbv_lp_point_fare_label( array( 'price_column' => '3' ), array( 'A', 'B' ) ) && 'Not on price board' === cbv_lp_point_fare_label( array( 'price_column' => 'off' ), array( 'Base' ) ) );

/* ---- B. breakdown ---- */
section( 'B. all-in breakdown' );
// Trip 181 "The Insider" as stored: per cabin for 2.
$b = cbv_lp_point_breakdown( pt( 2, 2522, 402, 200, 152, 724 ) );
check( 'per cabin: total 2552 for the cabin, 1276 per person', 2552.0 === $b['total'] && 1276.0 === $b['per_person'] && 2 === $b['headcount'] && 'per_cabin' === $b['basis'], json_encode( $b ) );
check( 'per cabin: parts as typed, cruise fare = fare less discount', array( 'cruise_fare' => 1798.0, 'taxes_fees' => 402.0, 'gratuities' => 200.0, 'protection' => 152.0 ) === $b['parts'] );
check( 'parts add up to the total', abs( array_sum( $b['parts'] ) - $b['total'] ) < 0.005 );
// Helmets "Insider" as stored: marked per person for 2.
$b = cbv_lp_point_breakdown( pt( 2, 2938, 404, 160, 174, 906.6, 'per_person' ) );
check( 'per person: parts multiplied up to the cabin (x2)', array( 'cruise_fare' => 4062.8, 'taxes_fees' => 808.0, 'gratuities' => 320.0, 'protection' => 348.0 ) === $b['parts'], json_encode( $b['parts'] ) );
check( 'per person: total = existing cabin total (5538.80), per person 2769.40, matches cb_pricing_occupancy_point_total', 5538.8 === $b['total'] && 2769.4 === $b['per_person'] && abs( cb_pricing_occupancy_point_total( pt( 2, 2938, 404, 160, 174, 906.6, 'per_person' ) ) - 2769.4 ) < 0.005 );
$b = cbv_lp_point_breakdown( pt( 3, 3334, 606, 240, 255, 1167.8, 'per_person' ) );
check( 'per person for 3: parts x3, total matches the existing cabin total', abs( $b['total'] - cb_pricing_occupancy_point_cabin_total( pt( 3, 3334, 606, 240, 255, 1167.8, 'per_person' ) ) ) < 0.005 && abs( array_sum( $b['parts'] ) - $b['total'] ) < 0.005 && 3 === $b['headcount'] );
$b = cbv_lp_point_breakdown( pt( 2, 100, 0, 0, 0, 500 ) );
check( 'a discount larger than the fare never gives a negative total (existing floor)', 0.0 === $b['total'] && 0.0 === $b['per_person'] );
$b = cbv_lp_point_breakdown( pt( 0, 1000, 100, 0, 0, 0 ) );
check( 'headcount 0 or missing counts as 1 (no division by zero)', 1 === $b['headcount'] && 1100.0 === $b['per_person'] && 1 === cbv_lp_point_breakdown( 'junk' )['headcount'] );
check( 'breakdown line wording', 'All-in for 2 travelers: cruise fare $1,798 · taxes & fees $402 · prepaid gratuities $200 · Voyage Protection $152' === cbv_lp_price_breakdown_line( cbv_lp_point_breakdown( pt( 2, 2522, 402, 200, 152, 724 ) ) ), cbv_lp_price_breakdown_line( cbv_lp_point_breakdown( pt( 2, 2522, 402, 200, 152, 724 ) ) ) );
check( 'breakdown line shows cents when there are any', false !== strpos( cbv_lp_price_breakdown_line( cbv_lp_point_breakdown( pt( 2, 2938, 404, 160, 174, 906.6, 'per_person' ) ) ), 'cruise fare $4,062.80' ) );

/* ---- C. the board ---- */
section( 'C. the board' );
$GLOBALS['tiers'] = array(
	array( 'name' => 'The Insider', 'group' => 'Insider · Interior', 'badge' => 'You choose your cabin', 'occupancy_points' => array( pt( 2, 2522, 402, 200, 152, 724, 'per_cabin', '' ), pt( 2, 2700, 402, 200, 160, 724, 'per_cabin', '2' ) ) ),
	array( 'name' => 'Insider Locked In', 'group' => 'Insider · Interior', 'occupancy_points' => array( pt( 2, 1520, 402, 200, 106, 532, 'per_cabin', 'off' ) ) ),
	array( 'name' => 'The Sea Terrace', 'group' => 'Sea Terrace · Balcony + Hammock', 'badge' => '★ Group favorite', 'highlight' => 1, 'note' => 'Held in our group block', 'occupancy_points' => array( pt( 4, 5000, 804, 400, 300, 0, 'per_cabin', '2' ), pt( 2, 3572, 402, 200, 208, 1124, 'per_cabin', '2' ), pt( 2, 3900, 402, 200, 230, 1124, 'per_cabin', '3' ) ) ),
	array( 'name' => 'Central Sea Terrace', 'group' => 'sea terrace · balcony + hammock', 'occupancy_points' => array( pt( 3, 4500, 603, 300, 270, 0, 'per_cabin', '2' ), pt( 4, 5200, 804, 400, 300, 0, 'per_cabin', '2' ) ) ),
	array( 'name' => 'Seriously Suite', 'single_price' => 1, 'occupancy_points' => array( pt( 2, 6000, 402, 200, 300, 0, 'per_cabin', '3' ), pt( 2, 6100, 402, 200, 300, 0, 'per_cabin', '2' ) ) ),
	array( 'name' => 'Base only cabin', 'occupancy_points' => array( pt( 2, 1000, 402, 200, 100, 0, 'per_cabin', 'off' ) ) ),
	'junk',
	array( 'name' => 'No points' ),
);
$fake[ TRIP ] = array( 'cbv_lp_provider_id' => PROV, 'cb_pricing_tiers' => $GLOBALS['tiers'] );
$d = cbv_lp_price_board_data( TRIP );
check( 'three groups, in tier order; the Lock-It-In and Base-only rows are gone (Not on the price board)', array( 'Insider · Interior', 'Sea Terrace · Balcony + Hammock', 'Seriously Suite' ) === wp_list_pluck( $d['groups'], 'name' ), json_encode( wp_list_pluck( $d['groups'], 'name' ) ) );
check( 'Insider group has one row (Locked In removed); Sea Terrace group joins both rows (Group matched regardless of case)', 1 === count( $d['groups'][0]['rows'] ) && array( 'The Sea Terrace', 'Central Sea Terrace' ) === wp_list_pluck( $d['groups'][1]['rows'], 'name' ) );
check( 'columns: only those a (non-suite) row uses, with their names', array( 1 => 'Base', 2 => 'Essential', 3 => 'Premium' ) === $d['columns'], json_encode( $d['columns'] ) );
$st = $d['groups'][1]['rows'][0];
check( 'badge, highlight and note carried', '★ Group favorite' === $st['badge'] && true === $st['highlight'] && 'Held in our group block' === $st['note'] && false === $st['single'] );
check( 'the cell for a column is the point for 2 travelers (not the 4-traveler point listed first)', 2 === $st['cells'][2]['headcount'] && 3258.0 === $st['cells'][2]['total'], json_encode( $st['cells'][2] ) );
check( 'Premium column for the same row', 3608.0 === $st['cells'][3]['total'] && array( 2, 3 ) === array_keys( $st['cells'] ) );
$cst = $d['groups'][1]['rows'][1];
check( 'no point for 2: the smallest headcount is used (3)', 3 === $cst['cells'][2]['headcount'] && 5673.0 === $cst['cells'][2]['total'] );
check( '"from" is the lowest lead total in the group', 2552.0 === $d['groups'][0]['from'] && 3258.0 === $d['groups'][1]['from'] );
$suite = $d['groups'][2]['rows'][0];
check( 'one price only: a single cell (the first column that has a price), and it does not add a column', true === $suite['single'] && array( 2 ) === array_keys( $suite['cells'] ) && 7002.0 === $suite['cells'][2]['total'] );
$fake[ TRIP ]['cb_pricing_tiers'] = array( array( 'name' => 'Cabin', 'occupancy_points' => array( pt( 2, 1000, 0, 0, 0, 0, 'per_cabin', '' ) ) ), array( 'name' => 'Suite', 'single_price' => 1, 'occupancy_points' => array( pt( 2, 9000, 0, 0, 0, 0, 'per_cabin', '3' ) ) ) );
check( "a suite priced in a column no cabin uses does not add that column to the board's header", array( 1 => 'Base' ) === cbv_lp_price_board_data( TRIP )['columns'], json_encode( cbv_lp_price_board_data( TRIP )['columns'] ) );
$fake[ TRIP ]['cb_pricing_tiers'] = $GLOBALS['tiers'];
check( 'a tier with no Group is its own group named after the tier', 'Seriously Suite' === $d['groups'][2]['name'] );
check( 'lead headcount is 2', 2 === $d['headcount'] );
$fake[ TRIP ]['cb_pricing_tiers'] = array( array( 'name' => 'A', 'occupancy_points' => array( pt( 2, 1, 0, 0, 0, 0, 'per_cabin', 'off' ) ) ) );
check( 'everything off: an empty board', array() === cbv_lp_price_board_data( TRIP )['groups'] && array() === cbv_lp_price_board_data( TRIP )['columns'] );
$fake[ TRIP ]['cb_pricing_tiers'] = array();
check( 'no tiers: an empty board', array() === cbv_lp_price_board_data( TRIP )['groups'] );
$fake[ TRIP ]['cb_pricing_tiers'] = array( array( 'name' => 'Untagged A', 'occupancy_points' => array( pt( 2, 1000, 100, 50, 25, 0 ) ) ), array( 'name' => 'Untagged B', 'occupancy_points' => array( pt( 2, 2000, 100, 50, 25, 0 ) ) ) );
$d = cbv_lp_price_board_data( TRIP );
check( 'data entered before Step 8 (no tags): every tier its own group, everything in column 1', array( 'Untagged A', 'Untagged B' ) === wp_list_pluck( $d['groups'], 'name' ) && array( 1 => 'Base' ) === $d['columns'] && array( 1 ) === array_keys( $d['groups'][0]['rows'][0]['cells'] ) );

/* REAL trips, read as stored (no tags yet) */
$d181 = cbv_lp_price_board_data( 181 );
$t181 = cb_trip_get_pricing_tiers( 181 );
check( 'REAL trip 181: one group per tier (7), every price in column 1, totals equal the existing cabin totals', count( $t181 ) === count( $d181['groups'] ) && array( 1 ) === array_keys( $d181['columns'] ) && abs( $d181['groups'][0]['rows'][0]['cells'][1]['total'] - cb_pricing_occupancy_point_cabin_total( $t181[0]['occupancy_points'][0] ) ) < 0.005, json_encode( wp_list_pluck( $d181['groups'], 'name' ) ) );
check( 'REAL trip 181: "The Insider" all-in for 2 = $2,552, per person $1,276', 2552.0 === $d181['groups'][0]['rows'][0]['cells'][1]['total'] && 1276.0 === $d181['groups'][0]['rows'][0]['cells'][1]['per_person'] );
$d320 = cbv_lp_price_board_data( 320 );
check( 'REAL trip 320: read without errors; totals equal the existing cabin totals', ! empty( $d320['groups'] ) && abs( $d320['groups'][0]['rows'][0]['cells'][1]['total'] - cb_pricing_occupancy_point_cabin_total( cb_trip_get_pricing_tiers( 320 )[0]['occupancy_points'][0] ) ) < 0.005 );

/* ---- D. saving the Pricing Tiers box ---- */
section( 'D. saving the Pricing Tiers box' );
$posted = array(
	array( 'name' => ' The <b>Insider</b> ', 'capacity_low' => '2', 'capacity_high' => '4', 'description' => "Line 1\nLine 2", 'group' => ' Insider · <i>Interior</i> ', 'badge' => str_repeat( 'b', 60 ), 'note' => str_repeat( 'n', 200 ), 'highlight' => '1', 'single_price' => '',
		'occupancy_points' => array(
			array( 'occupancy_count' => '2', 'voyage_fare' => '2522', 'taxes_fees' => '402', 'gratuities' => '200', 'insurance' => '0', 'discount' => '724', 'pricing_basis_per_cabin' => '1', 'price_column' => '2' ),
			array( 'occupancy_count' => '', 'voyage_fare' => '', 'taxes_fees' => '', 'gratuities' => '', 'insurance' => '', 'discount' => '', 'price_column' => '' ),
			array( 'occupancy_count' => '3', 'voyage_fare' => '3000', 'taxes_fees' => '600', 'gratuities' => '300', 'insurance' => '200', 'discount' => '0', 'price_column' => '<script>' ),
			array( 'occupancy_count' => '2', 'voyage_fare' => '1520', 'taxes_fees' => '402', 'gratuities' => '200', 'insurance' => '106', 'discount' => '532', 'pricing_basis_per_cabin' => '1', 'price_column' => 'off' ),
		),
		'addons' => array( array( 'name' => '', 'qty' => '' ), array( 'name' => 'Drinks', 'qty' => '2' ) ),
	),
	array( 'name' => '', 'capacity_low' => '', 'capacity_high' => '', 'description' => '', 'group' => '', 'badge' => '', 'note' => '', 'occupancy_points' => array( array( 'occupancy_count' => '', 'price_column' => '' ) ), 'addons' => array() ),
);
$s = cb_sanitize_pricing_tiers( wp_slash( $posted ) );
check( 'an untouched added tier is dropped (its untouched point row has price_column "" and stays blank)', 1 === count( $s ) );
check( 'an untouched added occupancy point is dropped; filled ones kept in order', 3 === count( $s[0]['occupancy_points'] ) );
check( 'existing fields saved exactly as before (name, sleeps, description, prices, basis, $0 insurance kept)', 'The Insider' === $s[0]['name'] && 2 === $s[0]['capacity_low'] && 4 === $s[0]['capacity_high'] && "Line 1\nLine 2" === $s[0]['description'] && 2522.0 === $s[0]['occupancy_points'][0]['voyage_fare'] && 0.0 === $s[0]['occupancy_points'][0]['insurance'] && 'per_cabin' === $s[0]['occupancy_points'][0]['pricing_basis'] && 'per_person' === $s[0]['occupancy_points'][1]['pricing_basis'] && array( array( 'name' => 'Drinks', 'qty' => 2 ) ) === $s[0]['addons'] );
check( 'price column: "2" kept, junk becomes "" (column 1), "off" kept', '2' === $s[0]['occupancy_points'][0]['price_column'] && '' === $s[0]['occupancy_points'][1]['price_column'] && 'off' === $s[0]['occupancy_points'][2]['price_column'] );
check( 'tier fields: group tags stripped, badge capped at 40, note at 160, highlight 1, single 0', 'Insider · Interior' === $s[0]['group'] && 40 === mb_strlen( $s[0]['badge'] ) && 160 === mb_strlen( $s[0]['note'] ) && 1 === $s[0]['highlight'] && 0 === $s[0]['single_price'] );
check( 'junk input gives an empty list; junk rows inside a tier are skipped', array() === cb_sanitize_pricing_tiers( 'junk' ) && array() === cb_sanitize_pricing_tiers( array() ) && array() === cb_sanitize_pricing_tiers( array( 'junk', 5, null ) ) && 1 === count( cb_sanitize_pricing_tiers( array( array( 'name' => 'X', 'occupancy_points' => array( 'junk', array( 'occupancy_count' => '2', 'voyage_fare' => '1' ) ), 'addons' => array( 'junk' ) ) ) )[0]['occupancy_points'] ) );
// Round trip: the REAL trip 181 tiers, posted back the way the form posts them, keep every price.
$form = array();
foreach ( $t181 as $t ) {
	$row = array( 'name' => $t['name'], 'capacity_low' => (string) $t['capacity_low'], 'capacity_high' => (string) $t['capacity_high'], 'description' => $t['description'] ?? '', 'occupancy_points' => array(), 'addons' => array() );
	foreach ( $t['occupancy_points'] as $p ) {
		$fp = array( 'occupancy_count' => (string) $p['occupancy_count'], 'voyage_fare' => (string) $p['voyage_fare'], 'taxes_fees' => (string) $p['taxes_fees'], 'gratuities' => (string) $p['gratuities'], 'insurance' => (string) $p['insurance'], 'discount' => (string) $p['discount'], 'price_column' => '' );
		if ( 'per_cabin' === $p['pricing_basis'] ) { $fp['pricing_basis_per_cabin'] = '1'; }
		$row['occupancy_points'][] = $fp;
	}
	$form[] = $row;
}
$rt = cb_sanitize_pricing_tiers( wp_slash( $form ) );
$same = count( $rt ) === count( $t181 );
foreach ( $t181 as $i => $t ) {
	foreach ( array( 'name', 'capacity_low', 'capacity_high' ) as $k ) { $same = $same && $t[ $k ] === $rt[ $i ][ $k ]; }
	foreach ( $t['occupancy_points'] as $j => $p ) {
		foreach ( array( 'occupancy_count', 'voyage_fare', 'taxes_fees', 'gratuities', 'insurance', 'discount', 'pricing_basis' ) as $k ) { $same = $same && $p[ $k ] == $rt[ $i ]['occupancy_points'][ $j ][ $k ]; } // phpcs:ignore -- loose: stored floats vs re-parsed floats
	}
}
check( 'REAL trip 181 saved again with no tags: every name and price identical; the only additions are the empty Step 8 fields', $same && '' === $rt[0]['group'] && '' === $rt[0]['occupancy_points'][0]['price_column'] && 0 === $rt[0]['highlight'] );
$src = file_get_contents( WPMU_PLUGIN_DIR . '/checkedbags-trips.php' );
check( 'the save handler stores the sanitized tiers through the new function', 1 === substr_count( $src, "update_post_meta( \$post_id, 'cb_pricing_tiers', cb_sanitize_pricing_tiers( \$_POST['cb_pricing_tiers'] ?? array() ) );" ) );
check( 'checkedbags-trips.php keeps its CRLF line endings (no bare LF anywhere)', false !== strpos( $src, "\r\n" ) && 0 === preg_match( '/(?<!\r)\n/', $src ) );

/* ---- E. the editor ---- */
section( 'E. the Pricing Tiers editor' );
$GLOBALS['cbv_lp_price_column_names'] = array( 'Base', 'Essential', 'Premium', '' );
ob_start(); cb_render_pricing_tier_row_fields( 3, array( 'name' => 'T', 'group' => 'Sea "Terrace"', 'badge' => '<b>Fav</b>', 'note' => 'N', 'highlight' => 1, 'single_price' => 0, 'occupancy_points' => array( pt( 2, 1, 1, 1, 1, 0, 'per_cabin', 'off' ) ) ) ); $h = ob_get_clean();
check( 'tier row has Group, Badge, Note, Highlight and One price only, escaped', false !== strpos( $h, 'name="cb_pricing_tiers[3][group]"' ) && false !== strpos( $h, 'value="Sea &quot;Terrace&quot;"' ) && false !== strpos( $h, 'value="&lt;b&gt;Fav&lt;/b&gt;"' ) && false !== strpos( $h, 'name="cb_pricing_tiers[3][note]"' ) && 1 === preg_match( '/name="cb_pricing_tiers\[3\]\[highlight\]" value="1"\s+checked=\'checked\'/', $h ) && 1 === preg_match( '/name="cb_pricing_tiers\[3\]\[single_price\]" value="1"\s*>/', $h ) );
check( 'point row has the Price column select with the provider names and "off" selected', false !== strpos( $h, 'name="cb_pricing_tiers[3][occupancy_points][0][price_column]"' ) && false !== strpos( $h, '>Column 1: Base</option>' ) && false !== strpos( $h, '>Column 2: Essential</option>' ) && 1 === preg_match( '/<option value="off"\s+selected=\'selected\'>Not on the price board/', $h ) );
check( 'existing fields are still there (prices, basis checkbox, add-ons, sleeps)', false !== strpos( $h, '[voyage_fare]' ) && false !== strpos( $h, '[pricing_basis_per_cabin]' ) && false !== strpos( $h, '[capacity_low]' ) && false !== strpos( $h, 'data-repeater="addons"' ) );
ob_start(); cb_render_occupancy_point_row_fields( 0, '__POINT_INDEX__', array() ); $tpl = ob_get_clean();
check( 'a new (template) point row has column 1 (value "") selected, so it stays blank until filled', 1 === preg_match( '/<option value=""\s+selected=\'selected\'>Column 1: Base/', $tpl ) );
$fake[ TRIP ] = array( 'cbv_lp_provider_id' => PROV, 'cb_pricing_tiers' => array() );
unset( $GLOBALS['cbv_lp_price_column_names'] );
ob_start(); cb_render_trip_pricing_tiers_meta_box( get_post( TRIP ) ); ob_get_clean();
check( "the box hands the trip's column names to the rows", array( 'Base', 'Essential', 'Premium', '' ) === ( $GLOBALS['cbv_lp_price_column_names'] ?? null ) );

/* ---- F. proposal PDF ---- */
section( 'F. proposal PDF' );
$html = cb_proposal_render_pricing_html( array( 'pricing_tiers' => array( array( 'name' => 'Insider', 'capacity_low' => 2, 'capacity_high' => 4, 'occupancy_points' => array( pt( 2, 2522, 402, 200, 152, 724, 'per_cabin', '2' ), pt( 2, 1520, 402, 200, 106, 532, 'per_cabin', 'off' ) ), 'addons' => array() ) ), 'single_price' => 0, 'price_columns' => array( 'Base', 'Essential', 'Premium', '' ) ) );
check( 'tagged trip: PDF pricing table starts with a Fare column', false !== strpos( $html, '<th>Fare</th><th># Sailors</th>' ) );
check( 'PDF rows show the column name, and "Not on price board" for off', false !== strpos( $html, '<td>Essential</td><td>2</td>' ) && false !== strpos( $html, '<td>Not on price board</td><td>2</td>' ) );
check( 'PDF totals unchanged ($2,552.00 per cabin)', false !== strpos( $html, '2,552.00' ) );
// The Fare column only appears once something on the trip is tagged (fix 2026-10-08).
check( 'tag helper: untouched / blank / missing / junk tags do not count', false === cbv_lp_trip_has_price_tags( array() ) && false === cbv_lp_trip_has_price_tags( array( array( 'occupancy_points' => array( pt( 2, 1, 0, 0, 0, 0, 'per_cabin', '' ), array( 'occupancy_count' => 2 ) ) ) ) ) && false === cbv_lp_trip_has_price_tags( array( array( 'occupancy_points' => array( array( 'price_column' => '9' ), array( 'price_column' => array( 'off' ) ) ) ) ) ) && false === cbv_lp_trip_has_price_tags( 'junk' ) && false === cbv_lp_trip_has_price_tags( array( 'junk', array( 'occupancy_points' => 'junk' ) ) ) );
check( "tag helper: any one '2', '3', '4' or 'off' anywhere on the trip counts", true === cbv_lp_trip_has_price_tags( array( array( 'occupancy_points' => array( pt( 2, 1, 0, 0, 0, 0 ) ) ), array( 'occupancy_points' => array( pt( 2, 1, 0, 0, 0, 0 ), pt( 2, 1, 0, 0, 0, 0, 'per_cabin', 'off' ) ) ) ) ) && true === cbv_lp_trip_has_price_tags( array( array( 'occupancy_points' => array( array( 'price_column' => '3' ) ) ) ) ) && true === cbv_lp_trip_has_price_tags( array( array( 'occupancy_points' => array( array( 'price_column' => '4' ) ) ) ) ) );
$untagged = array( 'pricing_tiers' => array( array( 'name' => 'Insider', 'capacity_low' => 2, 'capacity_high' => 4, 'occupancy_points' => array( pt( 2, 2522, 402, 200, 152, 724, 'per_cabin', '' ), array( 'occupancy_count' => 3, 'voyage_fare' => 3000, 'taxes_fees' => 600, 'gratuities' => 300, 'insurance' => 200, 'discount' => 0, 'pricing_basis' => 'per_person' ) ), 'addons' => array() ) ), 'single_price' => 0, 'price_columns' => array( 'Base', 'Essential', 'Premium', '' ) );
$h_untagged = cb_proposal_render_pricing_html( $untagged );
check( 'untagged trip: no Fare header and no Fare cells (the table starts with # Sailors, each row with the headcount)', false === strpos( $h_untagged, 'Fare</th><th># Sailors' ) && false !== strpos( $h_untagged, '<thead><tr><th># Sailors</th><th>Voyage Fare</th>' ) && false !== strpos( $h_untagged, '<tr><td>2</td><td>$2,522.00</td>' ) && false !== strpos( $h_untagged, '<tr><td>3</td><td>$3,000.00</td>' ) && false === strpos( $h_untagged, 'Base' ) );
check( 'untagged trip: 9 header cells and 9 cells per row, as before Step 8', 9 === substr_count( $h_untagged, '<th>' ) && 18 === substr_count( $h_untagged, '<td>' ) ); // 2 rows x 9 cells ('<td><strong>' also counts as a '<td>')
$one_tag = $untagged; $one_tag['pricing_tiers'][0]['occupancy_points'][1]['price_column'] = 'off';
$h_one = cb_proposal_render_pricing_html( $one_tag );
check( 'one tag anywhere: the Fare column appears for every row (untagged rows show the column 1 name)', false !== strpos( $h_one, '<th>Fare</th><th># Sailors</th>' ) && false !== strpos( $h_one, '<td>Base</td><td>2</td>' ) && false !== strpos( $h_one, '<td>Not on price board</td><td>3</td>' ) );
check( 'totals are the same with and without the Fare column', preg_match_all( '/\$[\d,]+\.\d\d/', $h_untagged, $m1 ) && preg_match_all( '/\$[\d,]+\.\d\d/', $h_one, $m2 ) && $m1[0] === $m2[0] );
$h181 = cb_proposal_render_pricing_html( array( 'pricing_tiers' => cb_trip_get_pricing_tiers( 181 ), 'single_price' => 0, 'price_columns' => cbv_lp_price_columns( 181 ) ) );
check( 'REAL trip 181 (untagged today): no Fare column in its proposal', false === strpos( $h181, '<th>Fare</th>' ) && false !== strpos( $h181, '<th># Sailors</th>' ) );

/* ---- G. what Step 8 must NOT do ---- */
section( 'G. no side effects' );
check( 'the public price range for trip 181 is computed exactly as before', cb_trip_get_price_range( 181 )['low'] === min( array_map( 'cb_pricing_occupancy_point_total', array_merge( ...array_map( function ( $t ) { return $t['occupancy_points']; }, $t181 ) ) ) ) );
check( 'the current (legacy) landing page still lists every tier, Lock-It-In included', false !== strpos( cbv_render_public_trip_landing( 181 ), 'Insider Locked In' ) );
check( 'master switch still off', ! get_option( 'cbv_lp_redesign_live' ) );
check( 'no new section is drawn on the new-design page in Step 8', false === strpos( cbv_lp_render_trip( 181, 'new' ), 'cbv-lp-prices' ) );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
