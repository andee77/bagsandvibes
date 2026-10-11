<?php
/*
 * Step 9 tests: the price board drawn (groups, table, breakdown toggle, header note, footer bar, fee note),
 * the "Price board" box and its save, "Claim your seat", the coin stamp, the legacy pricing removal, and the
 * Step 9 fee rule (a trip's own Group Experience Fee shows on the board before the fee is live).
 * Run against a temp COPY of the mu-plugins folder with the Step 9 files overlaid:
 *   wp --require=/tmp/cbv_t/define.php eval-file test_step9.php
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
check( 'Step 9 code is loaded', function_exists( 'cbv_lp_render_priceboard' ) && function_exists( 'cbv_lp_priceboard_data' ) && function_exists( 'cbv_lp_price_board_visible' ) && function_exists( 'cbv_lp_strip_legacy_pricing' ) );

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
// Options: faked (never written). '0' (not false) so the real options are never read.
$GLOBALS['opt_fees'] = array(); $GLOBALS['opt_live'] = '0';
add_filter( 'pre_option_cbv_lp_planning_fees', function () { return $GLOBALS['opt_fees']; } );
add_filter( 'pre_option_cbv_lp_planning_fee_live', function () { return $GLOBALS['opt_live']; } );
add_filter( 'pre_option_cbv_lp_child_age_under', function () { return '0'; } );
function fake_post( $id, $type, $status, $title ) {
	$p = new WP_Post( (object) array( 'ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_title' => $title, 'post_author' => 1, 'post_name' => sanitize_title( $title ), 'post_date' => '2026-01-01 00:00:00', 'post_date_gmt' => '2026-01-01 00:00:00', 'post_content' => '', 'post_excerpt' => '', 'post_mime_type' => '', 'filter' => 'raw' ) );
	wp_cache_set( $id, $p, 'posts' );
	return $p;
}
const TRIP = 999950; const PROV = 999951;
fake_post( TRIP, 'cb_trip', 'publish', 'Board Voyage' );
fake_post( PROV, 'cb_provider', 'publish', 'Columns provider' );
$fake[ PROV ] = array( 'cbv_lp_columns' => array( 'Base', 'Essential', 'Premium', '' ) );

function pt( $n, $fare, $tax, $grat, $ins, $disc, $col = '', $basis = 'per_cabin' ) {
	return array( 'occupancy_count' => $n, 'voyage_fare' => $fare, 'taxes_fees' => $tax, 'gratuities' => $grat, 'insurance' => $ins, 'discount' => $disc, 'pricing_basis' => $basis, 'price_column' => $col );
}
// A Virgin-like board: Insider (Essential + Premium), a Sea Terrace group with a highlighted favourite and a
// Premium-only row, and a suite group (one price only). Base is tagged "Not on the price board".
$GLOBALS['tiers'] = array(
	array( 'name' => 'The Insider', 'group' => 'Insider · Interior', 'badge' => '', 'note' => '', 'highlight' => 0, 'single_price' => 0,
		'occupancy_points' => array( pt( 2, 2522, 402, 200, 152, 724, '2' ), pt( 2, 2800, 402, 200, 152, 724, '3' ), pt( 2, 2300, 402, 200, 152, 724, 'off' ) ), 'addons' => array() ),
	array( 'name' => 'The Sea Terrace', 'group' => 'Sea Terrace · Balcony', 'badge' => '★ Group favorite', 'note' => 'Booked alongside the group', 'highlight' => 1, 'single_price' => 0,
		'occupancy_points' => array( pt( 2, 3000, 402, 200, 152, 500, '2' ), pt( 2, 3300, 402, 200, 152, 500, '3' ) ), 'addons' => array() ),
	array( 'name' => 'XL <Sea> Terrace', 'group' => 'Sea Terrace · Balcony', 'badge' => '', 'note' => '', 'highlight' => 0, 'single_price' => 0,
		'occupancy_points' => array( pt( 3, 4000, 603, 300, 228, 0, '3' ) ), 'addons' => array() ),
	array( 'name' => 'Seriously Suite', 'group' => 'RockStar Quarters', 'badge' => '', 'note' => '', 'highlight' => 0, 'single_price' => 1,
		'occupancy_points' => array( pt( 2, 6000, 402, 200, 300, 0, '2' ) ), 'addons' => array() ),
);
function trip_meta( $extra = array() ) {
	return array_merge( array(
		'cbv_lp_event_type' => 'cruise', 'cbv_lp_sections' => array(), 'cbv_lp_provider_id' => PROV, 'cbv_lp_columns' => array(),
		'cb_pricing_tiers' => $GLOBALS['tiers'], 'cbv_lp_planning_fee' => array(), 'cbv_lp_priceboard' => array(),
		'cb_trip_code' => 'CBV-2027-TST', 'cb_start_date' => '2027-10-25', 'cb_end_date' => '2027-10-30',
		'cb_public_landing_enabled' => 1, 'cb_public_landing_show_itinerary' => 0,
	), $extra );
}
$own225 = array( 'cbv_lp_planning_fee' => array( 'mode' => 'custom', 'amount' => 225, 'basis' => 'per_traveler' ) );
$kd = function ( $dates, $trip_id ) { if ( TRIP === (int) $trip_id ) { $dates['final_payment'] = 'June 27, 2027'; } return $dates; };
add_filter( 'cbv_lp_key_dates', $kd, 99, 2 );
wp_set_current_user( 0 ); unset( $_GET['preview'] );
function board_html( $trip = TRIP ) { cbv_lp_next_gate( true ); return cbv_lp_render_priceboard( $trip ); }

/* ---- A. the box: cleaning input ---- */
section( 'A. the "Price board" box: cleaning input' );
$s = cbv_lp_sanitize_priceboard( array( 'note' => "  <b>All in</b> for {party}\nline 2 ", 'photo1' => 407, 'focus1' => 'top-left', 'photo2' => TRIP, 'focus2' => 'nonsense' ) );
check( 'note: tags stripped, trimmed, line breaks kept', "All in for {party}\nline 2" === $s['note'], json_encode( $s['note'] ) );
check( 'photo 1: a real image kept with its focus; photo 2: not an image -> 0, junk focus -> centre', 407 === $s['photo1'] && 'top-left' === $s['focus1'] && 0 === $s['photo2'] && '' === $s['focus2'], json_encode( $s ) );
check( 'note capped at 600 characters; junk input gives an empty box', 600 === mb_strlen( cbv_lp_sanitize_priceboard( array( 'note' => str_repeat( 'x', 900 ) ) )['note'] ) && array( 'note' => '', 'photo1' => 0, 'focus1' => '', 'photo2' => 0, 'focus2' => '' ) === cbv_lp_sanitize_priceboard( 'junk' ) );

/* ---- B. small helpers ---- */
section( 'B. small helpers' );
check( 'money: whole dollars without cents, otherwise two places', '$2,552' === cbv_lp_board_money( 2552 ) && '$1,526.50' === cbv_lp_board_money( 1526.5 ) && '$0' === cbv_lp_board_money( 0 ) );
check( 'open group: the first group with a highlighted row', 1 === cbv_lp_board_open_group( array( array( 'rows' => array( array( 'highlight' => false ) ) ), array( 'rows' => array( array( 'highlight' => false ), array( 'highlight' => true ) ) ), array( 'rows' => array( array( 'highlight' => true ) ) ) ) ) );
check( 'open group: no highlight -> the first group', 0 === cbv_lp_board_open_group( array( array( 'rows' => array( array( 'highlight' => false ) ) ), array( 'rows' => array() ) ) ) && 0 === cbv_lp_board_open_group( array() ) );
$g2 = array( array( 'rows' => array( array( 'cells' => array( 1 => array( 'parts' => array( 'gratuities' => 200, 'protection' => 0 ) ), 2 => array( 'parts' => array( 'gratuities' => 0, 'protection' => 0 ) ) ) ) ) ) );
check( 'part use: "any" and "all" across every price', array( 'any' => true, 'all' => false ) === cbv_lp_board_part_use( $g2, 'gratuities' ) && array( 'any' => false, 'all' => false ) === cbv_lp_board_part_use( $g2, 'protection' ) );
$cruise_labels = cbv_lp_base_labels();
$fake[ TRIP ] = trip_meta();
$groups = cbv_lp_price_board_data( TRIP )['groups'];
$auto = cbv_lp_board_auto_note( $groups, $cruise_labels, false, true );
check( 'auto note, no fee: the four parts and the travel advisor sentence', array( 'All-in prices per cabin for 2 travelers:', 'cruise fare, taxes & fees, prepaid gratuities and Voyage Protection. Travel is booked by your travel advisor and paid directly to the cruise line.' ) === $auto, json_encode( $auto ) );
$auto = cbv_lp_board_auto_note( $groups, $cruise_labels, true, true );
check( 'auto note, with a fee: "plus the CBGV Group Experience Fee"', 'cruise fare, taxes & fees, prepaid gratuities and Voyage Protection, plus the CBGV Group Experience Fee. Travel is booked by your travel advisor and paid directly to the cruise line.' === $auto[1] );
$auto = cbv_lp_board_auto_note( $g2, array( 'accommodation' => 'room', 'party' => 'Guests' ), false, false );
check( 'auto note: parts no price contains are left out; other event types say "the supplier"', array( 'All-in prices per room for 2 guests:', 'cruise fare and prepaid gratuities. Travel is booked by your travel advisor and paid directly to the supplier.' ) === $auto, json_encode( $auto ) );
check( 'registration link: /join/?trip=CODE', home_url( '/join/?trip=CBV-2027-TST' ) === cbv_lp_board_registration_url( TRIP ) );

/* ---- C. what the board shows ---- */
section( 'C. board data' );
$fake[ TRIP ] = trip_meta();
$d = cbv_lp_priceboard_data( TRIP );
check( 'labels: the section name and heading for a cruise', 'Fare classes' === $d['section'] && 'Pick your cabin' === $d['heading'] );
check( 'columns used: Essential and Premium only (Base is off the board)', array( 2 => 'Essential', 3 => 'Premium' ) === $d['columns'], json_encode( $d['columns'] ) );
check( 'groups in order; the group with the favourite starts open', array( 'Insider · Interior', 'Sea Terrace · Balcony', 'RockStar Quarters' ) === wp_list_pluck( $d['groups'], 'name' ) && 1 === $d['open'] );
check( 'footer: final payment date and "Tips & protection included" (every price has both)', 'Final payment due · June 27, 2027 · Tips & protection included' === $d['foot'], $d['foot'] );
check( 'button: "Reserve a cabin" to the registration', 'Reserve a cabin' === $d['cta_label'] && home_url( '/join/?trip=CBV-2027-TST' ) === $d['cta_url'] );
check( 'no fee: no fee note', array() === $d['fee_note'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_event_type' => 'resort' ) );
$dr = cbv_lp_priceboard_data( TRIP );
check( 'resort: its own words ("Pick your room", "Reserve a room", "One price"), no tips line', 'Pick your room' === $dr['heading'] && 'Reserve a room' === $dr['cta_label'] && 'One price' === $dr['single'] && 'Final payment due · June 27, 2027' === $dr['foot'] );
$t = $GLOBALS['tiers']; $t[0]['occupancy_points'][0]['insurance'] = 0;
$fake[ TRIP ] = trip_meta( array( 'cb_pricing_tiers' => $t ) );
check( 'one price without Voyage Protection: no "Tips & protection included"', false === strpos( cbv_lp_priceboard_data( TRIP )['foot'], 'Tips' ) );
remove_filter( 'cbv_lp_key_dates', $kd, 99 );
$fake[ TRIP ] = trip_meta();
check( 'no final payment date: the date part is left out', 'Tips & protection included' === cbv_lp_priceboard_data( TRIP )['foot'] );
add_filter( 'cbv_lp_key_dates', $kd, 99, 2 );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_sections' => array( 'prices' => 'off' ) ) );
check( 'section Off: no board, not visible', null === cbv_lp_priceboard_data( TRIP ) && false === cbv_lp_price_board_visible( TRIP ) && '' === board_html() );
$off = $GLOBALS['tiers'];
foreach ( $off as &$tier ) { foreach ( $tier['occupancy_points'] as &$p ) { $p['price_column'] = 'off'; } unset( $p ); } unset( $tier );
$fake[ TRIP ] = trip_meta( array( 'cb_pricing_tiers' => $off ) );
check( 'every price "Not on the price board": no board', null === cbv_lp_priceboard_data( TRIP ) && '' === board_html() );
$fake[ TRIP ] = trip_meta( array( 'cb_pricing_tiers' => array() ) );
check( 'no Pricing Tiers at all: no board', null === cbv_lp_priceboard_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cb_pricing_tiers' => array( $GLOBALS['tiers'][3] ) ) );
check( 'only a one-price suite: the board still shows', null !== cbv_lp_priceboard_data( TRIP ) && false !== strpos( board_html(), 'Seriously Suite' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_priceboard' => array( 'note' => 'Hold your cabin by **{date:final_payment}**. <script>x</script> [More](javascript:alert(1))' ) ) );
$h = board_html();
check( 'own header note: tokens filled, bold kept, HTML escaped, unsafe link dropped', false !== strpos( $h, 'Hold your cabin by <strong>June 27, 2027</strong>.' ) && false === strpos( $h, '<script>' ) && false === strpos( $h, 'javascript:' ) );

/* ---- D. the markup ---- */
section( 'D. markup' );
$fake[ TRIP ] = trip_meta();
$h = board_html();
check( 'a section with id "fares", labelled by its heading', false !== strpos( $h, '<section class="cbv-lp-prices" id="fares" aria-labelledby="cbv-lp-prices-title">' ) && false !== strpos( $h, 'id="cbv-lp-prices-title">Pick your cabin</h2>' ) );
check( 'GATE label: the next number with "Fare classes"', false !== strpos( $h, '<p class="cbv-lp-gate">Gate 14 · Fare classes</p>' ) );
check( 'header note: automatic, no deposit, no payment plans, no host agency, no "CBGV books"', false !== strpos( $h, '<strong>All-in prices per cabin for 2 travelers:</strong> cruise fare, taxes &amp; fees, prepaid gratuities and Voyage Protection. Travel is booked by your travel advisor and paid directly to the cruise line.' ) && false === stripos( $h, 'payment plan' ) && false === stripos( $h, 'holds your cabin' ) && false === stripos( $h, 'InteleTravel' ) && false === stripos( $h, 'CBGV books' ) );
check( 'one collapsible group per cabin group, all in one name so only one is open', 3 === substr_count( $h, '<details class="cbv-lp-board-group" name="cbv-lp-fare-groups"' ) );
check( 'only the favourite\'s group starts open', 1 === substr_count( $h, 'name="cbv-lp-fare-groups" open>' ) && 1 === preg_match( '/name="cbv-lp-fare-groups" open>\s*<summary>\s*<span class="cbv-lp-board-group-name">Sea Terrace · Balcony</', $h ) );
check( 'group bar: name and "From" price (lowest all-in total in the group)', false !== strpos( $h, '<span class="cbv-lp-board-from">From $2,552</span>' ) && false !== strpos( $h, 'From $3,254' ) && false !== strpos( $h, 'From $6,902' ) );
check( 'column bar (visual only) names the used columns', 1 === preg_match( '/<div class="cbv-lp-board-cols" aria-hidden="true">\s*<span>cabin · tap a type to see prices<\/span>\s*<span>Essential<\/span>\s*<span>Premium<\/span>/', $h ) );
check( 'a real table per group: caption and column headers for screen readers (kept in the table, text hidden)', 3 === substr_count( $h, '<table class="cbv-lp-board-table">' ) && false !== strpos( $h, '<caption class="cbv-lp-board-caption"><span class="cbv-lp-sr">Insider · Interior: all-in prices per cabin for 2 travelers</span></caption>' ) && 3 === substr_count( $h, '<th scope="col"><span class="cbv-lp-sr">Essential</span></th>' ) && 3 === substr_count( $h, '<th scope="col"><span class="cbv-lp-sr">Cabin</span></th>' ) && 3 === substr_count( $h, '<thead class="cbv-lp-board-head">' ) );
check( 'column widths set by a colgroup (cabin column, then one per price column)', 3 === substr_count( $h, '<colgroup><col class="cbv-lp-board-col-cabin"><col span="2"></colgroup>' ) );
check( 'each cabin is a row header', 4 === substr_count( $h, '<th scope="row">' ) );
check( 'a price: the all-in total, the per-person price under it', 1 === preg_match( '/data-col="Essential">\s*<span class="cbv-lp-board-price">\$2,552<\/span><span class="cbv-lp-board-pp">\$1,276 per person<\/span>/', $h ) );
check( 'favourite row tinted, badge and note shown', 1 === substr_count( $h, 'cbv-lp-board-row--highlight' ) && false !== strpos( $h, '<span class="cbv-lp-board-badge">★ Group favorite</span>' ) && false !== strpos( $h, '<span class="cbv-lp-board-note">Booked alongside the group</span>' ) );
check( 'a cabin name is escaped', false !== strpos( $h, 'XL &lt;Sea&gt; Terrace' ) && false === strpos( $h, 'XL <Sea>' ) );
check( 'a column a cabin does not offer: a dash for the eye, "Not offered" for screen readers', false !== strpos( $h, '<span class="cbv-lp-board-none" aria-hidden="true">&mdash;</span><span class="cbv-lp-sr">Not offered</span>' ) );
check( 'a price quoted for 3 says so', false !== strpos( $h, 'per person · for 3 travelers' ) );
check( 'a one-price suite spans the columns and says "Suite fare"', 1 === preg_match( '/<td class="cbv-lp-board-cell cbv-lp-board-cell--single" colspan="2" data-col="Suite fare">\s*<span class="cbv-lp-board-price">\$6,902<\/span>/', $h ) && false !== strpos( $h, '<span class="cbv-lp-board-tag">Suite fare</span>' ) );
check( '"What\'s in this price?" under every cabin, named for screen readers', 4 === substr_count( $h, '<details class="cbv-lp-board-more">' ) && false !== strpos( $h, 'What\'s in this price?<span class="cbv-lp-sr"> (The Insider)</span>' ) );
check( 'the breakdown names its column when the board has several', false !== strpos( $h, '<strong>Essential:</strong>' ) && false !== strpos( $h, 'All-in for 2 travelers: cruise fare $1,798 · taxes &amp; fees $402 · prepaid gratuities $200 · Voyage Protection $152' ) );
check( 'no fee: the breakdown has four items and nothing is said about a fee', false === strpos( $h, 'Experience Fee' ) && false === strpos( $h, 'cbv-lp-prices-notes' ) );
check( 'footer bar and the "Reserve a cabin" button', false !== strpos( $h, '<p class="cbv-lp-board-foot-text">Final payment due · June 27, 2027 · Tips &amp; protection included</p>' ) && false !== strpos( $h, '<a class="cbv-lp-board-cta" href="' . esc_url( home_url( '/join/?trip=CBV-2027-TST' ) ) . '">Reserve a cabin <span aria-hidden="true">&rarr;</span></a>' ) );
check( 'no photos: the board runs full width', false !== strpos( $h, 'cbv-lp-prices-body cbv-lp-prices-body--solo' ) && false === strpos( $h, 'cbv-lp-prices-photos' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_priceboard' => array( 'photo1' => 407, 'focus1' => 'top-left', 'photo2' => 408 ) ) );
$h = board_html();
check( 'photos: both drawn beside the board, with the focus point', 2 === substr_count( $h, 'cbv-lp-prices-img' ) && false !== strpos( $h, 'object-position:left top;' ) && false === strpos( $h, 'cbv-lp-prices-body--solo' ) );
$one = array( array( 'name' => 'Only', 'occupancy_points' => array( pt( 2, 1000, 100, 0, 0, 0 ) ), 'addons' => array() ) );
$fake[ TRIP ] = trip_meta( array( 'cb_pricing_tiers' => $one, 'cbv_lp_provider_id' => 0 ) );
$h = board_html();
check( 'one column only: the breakdown does not repeat the column name', false === strpos( $h, '<strong>Price:</strong>' ) && false !== strpos( $h, 'All-in for 2 travelers: cruise fare $1,000' ) );

/* ---- E. the fee on the board (Step 8b rules + Step 9: the trip's own fee) ---- */
section( 'E. the CBGV Group Experience Fee on the board' );
$fake[ TRIP ] = trip_meta( $own225 );
$GLOBALS['opt_live'] = '0';
check( "not live: the trip's OWN fee is shown on the page to a visitor (the Payment page already bills it)", 225.0 === cbv_lp_planning_fee_shown( TRIP, 'page' )['amount'] );
check( "not live: the trip's own fee still stays out of the proposal PDF", null === cbv_lp_planning_fee_shown( TRIP, 'pdf' ) );
$h = board_html();
check( 'fifth breakdown line: "CBGV Group Experience Fee $450" (2 x $225); totals include it', false !== strpos( $h, 'Voyage Protection $152 · CBGV Group Experience Fee $450' ) && false !== strpos( $h, '$3,002</span><span class="cbv-lp-board-pp">$1,501 per person' ) );
check( 'header note adds "plus the CBGV Group Experience Fee"', false !== strpos( $h, 'Voyage Protection, plus the CBGV Group Experience Fee. Travel is booked by your travel advisor' ) );
check( 'under the board: the approved fee note with the refund wording and the final payment date, then "Children pay half ..."', 1 === preg_match( '/<div class="cbv-lp-prices-notes">\s*<p>The CBGV Group Experience Fee is paid to Checked Bags &amp; Good Vibes \(a d\/b\/a of JourneyWell Global LLC\) for the group program; travel payments go directly to the cruise line or supplier\. It&#039;s fully refundable within 7 days of paying, 50% refundable until June 27, 2027, and non-refundable after that\. Full refund if the trip is cancelled\.<\/p>\s*<p>Children pay half the CBGV Group Experience Fee\.<\/p>\s*<\/div>/', $h ) );
check( 'the fee note comes after the board', strpos( $h, 'cbv-lp-board-foot' ) < strpos( $h, 'cbv-lp-prices-notes' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_planning_fee' => array( 'mode' => 'custom', 'amount' => 100, 'basis' => 'per_booking' ) ) );
$h = board_html();
check( 'flat per booking: "One fee per booking ..." and no children line', false !== strpos( $h, '<p>One fee per booking, however many cabins you book together.</p>' ) && false === strpos( $h, 'Children pay half' ) && false !== strpos( $h, 'CBGV Group Experience Fee $100' ) );
$GLOBALS['opt_fees'] = array( 'cruise' => array( 'amount' => 250, 'basis' => 'per_traveler' ) );
$fake[ TRIP ] = trip_meta();
$h = board_html();
check( 'not live, STANDARD fee, visitor: no fee, no note', false === strpos( $h, 'Experience Fee' ) );
wp_set_current_user( 1 ); $_GET['preview'] = 'new';
$h = board_html();
check( "not live, standard fee, admin ?preview=new: shown (fifth line $500, note)", false !== strpos( $h, 'CBGV Group Experience Fee $500' ) && false !== strpos( $h, 'cbv-lp-prices-notes' ) );
wp_set_current_user( 0 ); unset( $_GET['preview'] );
$GLOBALS['opt_live'] = '1';
check( 'live, standard fee, visitor: shown', false !== strpos( board_html(), 'CBGV Group Experience Fee $500' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_planning_fee' => array( 'mode' => 'none' ) ) );
check( 'live but waived on this trip: nothing about a fee', false === strpos( board_html(), 'Experience Fee' ) );
$GLOBALS['opt_fees'] = array(); $GLOBALS['opt_live'] = '0';

/* ---- F. boarding pass: "Claim your seat" and the coin stamp ---- */
section( 'F. boarding pass' );
$fake[ TRIP ] = trip_meta();
check( '"Claim your seat" jumps to the board when it is shown', '#fares' === cbv_lp_hero_data( TRIP )['cta_url'] );
$hero = cbv_lp_render_hero( TRIP );
check( 'the pass link points at #fares', false !== strpos( $hero, '<a class="cbv-lp-pass-cta" href="#fares">Claim your seat</a>' ) );
check( 'the coin stamp is on the pass, hidden from screen readers', 1 === substr_count( $hero, '<span class="cbv-lp-pass-stamp" aria-hidden="true"></span>' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_sections' => array( 'prices' => 'off' ) ) );
check( 'board Off: "Claim your seat" goes to the registration', home_url( '/join/?trip=CBV-2027-TST' ) === cbv_lp_hero_data( TRIP )['cta_url'] );
$fake[ TRIP ] = trip_meta( array( 'cb_pricing_tiers' => $off ) );
check( 'no price on the board: "Claim your seat" goes to the registration', home_url( '/join/?trip=CBV-2027-TST' ) === cbv_lp_hero_data( TRIP )['cta_url'] );
$fake[ TRIP ] = trip_meta( array( 'cb_pricing_tiers' => array(), 'cb_trip_code' => '' ) );
check( 'no board and no trip code: /join/', home_url( '/join/' ) === cbv_lp_hero_data( TRIP )['cta_url'] );

/* ---- G. the page: order, legacy pricing removed ---- */
section( 'G. the new-design page' );
$legacy_sample = '<div class="cbv-landing"><div class="cbv-landing-section"><h2>About</h2><p>Hi</p></div><div class="cbv-landing-section"><h2>Choose Your Option</h2><div class="cbv-landing-pricing"><div class="cbv-landing-pricing-card">From $1</div></div></div><div class="cbv-landing-cta"><a href="/join/">Reserve Your Spot</a></div></div>';
$st = cbv_lp_strip_legacy_pricing( $legacy_sample );
check( 'strip: the "Choose Your Option" block is removed, everything else kept', false === strpos( $st, 'Choose Your Option' ) && false === strpos( $st, 'cbv-landing-pricing' ) && false !== strpos( $st, '<h2>About</h2>' ) && false !== strpos( $st, 'Reserve Your Spot' ) );
check( 'strip: unchanged when there is no pricing block, or nothing at all', '<div class="cbv-landing-section"><h2>About</h2></div>' === cbv_lp_strip_legacy_pricing( '<div class="cbv-landing-section"><h2>About</h2></div>' ) && '' === cbv_lp_strip_legacy_pricing( '' ) );
$page = cbv_lp_render_trip( 181, 'new' );
check( 'REAL trip 181, new design: the board is drawn once, after the gallery and before the legacy content', 1 === substr_count( $page, '<section class="cbv-lp-prices"' ) && strpos( $page, 'class="cbv-lp-gallery"' ) < strpos( $page, 'class="cbv-lp-prices"' ) && strpos( $page, 'class="cbv-lp-prices"' ) < strpos( $page, 'cbv-lp-legacy-wrap' ) );
check( "REAL trip 181, new design: today's \"Choose Your Option\" cards are gone", false === strpos( $page, 'Choose Your Option' ) );
check( 'REAL trip 181, new design: GATE numbers run on (board = Gate 18 after intro, route, featured, gallery)', false !== strpos( $page, 'Gate 18 · Fare classes' ), '' );
check( 'REAL trip 181, new design: one <h1>, and "Claim your seat" points at the board', 1 === substr_count( $page, '<h1' ) && false !== strpos( $page, 'href="#fares">Claim your seat' ) );
$legacy = cbv_render_public_trip_landing( 181 );
check( "today's public page (legacy renderer) is unchanged: still has its cards and the old prices", false !== strpos( $legacy, 'Choose Your Option' ) && false !== strpos( $legacy, '1,276' ) && false === strpos( $legacy, 'cbv-lp-prices' ) );

/* ---- H. the "Price board" box and its save ---- */
section( 'H. admin box and save' );
wp_set_current_user( 1 );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_priceboard' => array( 'note' => 'Mine & <b>yours</b>', 'photo1' => 407, 'focus1' => 'top-left' ) ) );
ob_start(); cbv_lp_render_priceboard_box( get_post( TRIP ) ); $box = ob_get_clean();
check( 'box: header note (tags stripped, escaped), two photo choosers with focus points, the nonce', false !== strpos( $box, 'name="cbv_lp_priceboard[note]"' ) && false !== strpos( $box, '>Mine &amp; yours</textarea>' ) && false !== strpos( $box, 'cbv_lp_priceboard[photo1]' ) && false !== strpos( $box, 'cbv_lp_priceboard[photo2]' ) && false !== strpos( $box, 'name="cbv_lp_priceboard[focus2]"' ) && false !== strpos( $box, 'name="cbv_lp_priceboard_nonce"' ) );
check( 'box: says where the prices come from, and the wording rules', false !== strpos( $box, 'Pricing Tiers' ) && false !== strpos( $box, 'Never name the host agency' ) && false === stripos( $box, 'InteleTravel' ) );
function run_board_save( $post_id ) {
	global $wp_filter;
	foreach ( $wp_filter['save_post_cb_trip']->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( $fn instanceof Closure && basename( ( new ReflectionFunction( $fn ) )->getFileName() ) === 'checkedbags-lp-priceboard.php' ) { $fn( $post_id ); return true; }
		}
	}
	return false;
}
check( 'the save handler is registered', true === run_board_save( 999999 ) );
$writes = array();
$_POST = array( 'cbv_lp_priceboard_nonce' => 'bad', 'cbv_lp_priceboard' => array( 'note' => 'x' ) );
run_board_save( TRIP );
check( 'bad nonce: nothing written', array() === $writes );
$writes = array();
$_POST = array( 'cbv_lp_priceboard_nonce' => wp_create_nonce( 'cbv_lp_priceboard_save' ), 'cbv_lp_priceboard' => wp_slash( array( 'note' => ' <i>Hi</i> it\'s {party} ', 'photo1' => '407', 'focus1' => 'bottom-right', 'photo2' => 'junk' ) ) );
run_board_save( TRIP );
$by = array(); foreach ( $writes as $w ) { if ( 'update_post_metadata' === $w[0] ) { $by[ $w[2] ] = $w[3]; } }
check( 'save: stored cleaned (slashes removed), only its own key', array( 'cbv_lp_priceboard' => array( 'note' => 'Hi it\'s {party}', 'photo1' => 407, 'focus1' => 'bottom-right', 'photo2' => 0, 'focus2' => '' ) ) === $by, json_encode( $by ) );
wp_set_current_user( 0 );
$writes = array();
$_POST = array( 'cbv_lp_priceboard_nonce' => wp_create_nonce( 'cbv_lp_priceboard_save' ), 'cbv_lp_priceboard' => array( 'note' => 'x' ) );
run_board_save( TRIP );
check( 'logged out: nothing written', array() === $writes );
$_POST = array();

/* ---- I. the REAL site (read only) ---- */
section( 'I. real trips, read only' );
remove_filter( 'cbv_lp_key_dates', $kd, 99 );
$GLOBALS['opt_fees'] = array(); $GLOBALS['opt_live'] = '0';
$d181 = cbv_lp_priceboard_data( 181 );
check( 'REAL trip 181: board shown, 4 groups in one Premium column (Locked In tiers "Not on the price board" since 2026-10-11)', is_array( $d181 ) && 4 === count( $d181['groups'] ) && array( 3 => 'Premium' ) === $d181['columns'], json_encode( $d181 ? $d181['columns'] : null ) );
check( "REAL trip 181: its own fee ($225 per traveler) is on the board, with the children line", 'trip' === cbv_lp_price_board_data( 181 )['fee']['source'] && in_array( 'Children pay half the CBGV Group Experience Fee.', $d181['fee_note'], true ) );
check( 'REAL trip 181: "The Insider" $2,552 + $450 = $3,002 for 2 (Premium)', 3002.0 === $d181['groups'][0]['rows'][0]['cells'][3]['total'] );
$h181 = board_html( 181 );
check( 'REAL trip 181: nothing on the board names the host agency, a deposit, payment plans, or says CBGV books', false === stripos( $h181, 'InteleTravel' ) && false === stripos( $h181, 'payment plan' ) && false === stripos( $h181, 'holds your cabin' ) && false === stripos( $h181, 'CBGV books' ) );
$t181 = cb_trip_get_pricing_tiers( 181 );
check( 'REAL trip 181: Gate 07 price range unchanged by Step 9 (no fee in it)', cb_trip_get_price_range( 181 )['low'] === min( array_map( 'cb_pricing_occupancy_point_total', array_merge( ...array_map( function ( $t ) { return $t['occupancy_points']; }, $t181 ) ) ) ) );
$d320 = cbv_lp_priceboard_data( 320 );
check( 'REAL trip 320: board data reads without errors, own fee $150 per traveler', is_array( $d320 ) && ! empty( $d320['groups'] ) && 150.0 === cbv_lp_price_board_data( 320 )['fee']['amount'] );
check( 'no write happened during the whole run', array() === array_filter( $writes ) );
remove_all_filters( 'pre_option_cbv_lp_planning_fees' ); remove_all_filters( 'pre_option_cbv_lp_planning_fee_live' );
check( 'real options untouched: no standard fee, fee not live, master switch off', false === get_option( 'cbv_lp_planning_fees', false ) && ! get_option( 'cbv_lp_planning_fee_live' ) && ! get_option( 'cbv_lp_redesign_live' ) );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
