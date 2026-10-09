<?php
/*
 * Payment safeguard tests: CBGV never collects travel funds (owner's rules, 2026-10-08).
 * Every charge is the CBGV Group Experience Fee x the member's travelers + approved extras, never cb_price
 * ("Travel price") or cb_quoted_price, whatever their values; a hard ceiling refuses and logs anything above it;
 * Stripe sees "CBGV Group Experience Fee: {trip name}".
 *
 * Run against a temp COPY of the mu-plugins folder (same setup as tests/landing/README.md):
 *   wp --require=/tmp/cbv_t/define.php eval-file test_fee_safeguard.php
 * NO database writes, NO email, Stripe is NEVER called: every outgoing web request is answered locally by a
 * filter and recorded (request bodies only; headers are never stored or printed). Meta, user meta and options
 * are faked by filters and every write is intercepted. Set CBV_VERBOSE=1 to print every passing check.
 */
$GLOBALS['n'] = 0; $GLOBALS['fail'] = 0;
function check( $l, $c, $d = '' ) {
	$GLOBALS['n']++;
	if ( ! $c ) { $GLOBALS['fail']++; echo "FAIL: $l $d\n"; }
	elseif ( getenv( 'CBV_VERBOSE' ) ) { echo '  pass ' . str_pad( $GLOBALS['n'], 3, ' ', STR_PAD_LEFT ) . ": $l\n"; }
}
function section( $s ) { if ( getenv( 'CBV_VERBOSE' ) ) { echo "\n=== $s ===\n"; } }

// The safeguard also writes to the PHP error log: keep this run's lines out of the site's real log.
ini_set( 'error_log', '/tmp/cbv_t/test_errors.log' );
check( 'running against the temp mu-plugins copy', '/tmp/cbv_mu' === WPMU_PLUGIN_DIR );
check( 'payment code is loaded', function_exists( 'cb_trip_cbgv_fee_total' ) && function_exists( 'cb_trip_charge_ceiling' ) && function_exists( 'cb_create_checkout_session' ) && function_exists( 'cb_accept_trip_quote' ) && function_exists( 'cbv_lp_planning_fee' ) );

/* ---- isolation: no writes, no mail, no web requests ---- */
$GLOBALS['fake'] = array(); $GLOBALS['umeta'] = array(); $GLOBALS['writes'] = array(); $GLOBALS['http'] = array(); $GLOBALS['mail'] = 0; $GLOBALS['guard'] = array();
add_filter( 'get_post_metadata', function ( $v, $oid, $key ) {
	if ( isset( $GLOBALS['fake'][ $oid ] ) && array_key_exists( $key, $GLOBALS['fake'][ $oid ] ) ) {
		$val = $GLOBALS['fake'][ $oid ][ $key ];
		return array( $val instanceof Closure ? $val() : $val );
	}
	return $v;
}, 10, 3 );
add_filter( 'get_user_metadata', function ( $v, $uid, $key ) {
	if ( '_accepted_payment_disclaimer_version' === $key ) { return array( 999 ); } // disclaimer already accepted
	if ( 0 === strpos( (string) $key, '_traveler_intake_' ) ) { return array( $GLOBALS['umeta'][ $uid ][ $key ] ?? '' ); }
	return $v;
}, 10, 3 );
foreach ( array( 'update_post_metadata', 'add_post_metadata', 'delete_post_metadata', 'update_user_metadata', 'add_user_metadata', 'delete_user_metadata' ) as $hook ) {
	add_filter( $hook, function ( $check, $oid, $key, $value = null ) use ( $hook ) { $GLOBALS['writes'][] = array( $hook, $oid, $key, $value ); return true; }, 1, 4 );
}
$GLOBALS['opt_fees'] = array(); $GLOBALS['opt_live'] = '0';
add_filter( 'pre_option_cbv_lp_planning_fees', function () { return $GLOBALS['opt_fees']; } );
add_filter( 'pre_option_cbv_lp_planning_fee_live', function () { return $GLOBALS['opt_live']; } );
add_filter( 'pre_option_cb_payment_guard_log', function () { return array(); } );
add_filter( 'pre_update_option_cb_payment_guard_log', function ( $new, $old ) { $GLOBALS['guard'][] = $new[0] ?? null; return $old; }, 10, 2 ); // captured, never written
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$GLOBALS['http'][] = array( 'url' => $url, 'body' => $args['body'] ?? null );
	if ( 0 === strpos( $url, 'https://api.stripe.com/' ) ) {
		return array( 'headers' => array(), 'body' => json_encode( array( 'url' => 'https://checkout.example.test/session' ) ), 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	}
	return new WP_Error( 'blocked_in_test', 'blocked' );
}, 1, 3 );
add_filter( 'pre_wp_mail', function () { $GLOBALS['mail']++; return false; }, 1 );
remove_all_actions( 'cb_trip_status_changed' );
remove_all_actions( 'cb_trip_payment_recorded' );

const TRIP = 181;   // a real trip, so the Payment page's own trip list finds it; every value used is faked
const OTHER = 320;
$uid = 1;
wp_set_current_user( $uid );
// The target set-up for trip 181: its own fee $225 per traveler, no approved extras.
function trip( $extra = array() ) {
	return array_merge( array( 'cb_price' => 0, 'cb_quoted_price' => 0, 'cb_extras_cost' => 0, 'cbv_lp_planning_fee' => array( 'mode' => 'custom', 'amount' => 225, 'basis' => 'per_traveler' ), 'cbv_lp_event_type' => 'cruise', 'cb_payment_mode' => 'full_only', 'cb_deposit_amount' => 0, 'cb_num_installments' => 1, 'cb_payments' => array(), 'cb_roster' => array( 1 ), 'cb_next_payment_due_date' => '', 'cb_status' => '' ), $extra );
}
function travelers( $adults, $children = 0 ) {
	$GLOBALS['umeta'][1][ '_traveler_intake_' . TRIP ] = json_encode( array( 'additional_adults' => $adults, 'additional_children' => $children ) );
}
$GLOBALS['fake'][ OTHER ] = array( 'cb_roster' => array() );
function checkout( $trip_id = TRIP ) {
	$GLOBALS['http'] = array();
	$req = new WP_REST_Request( 'POST', '/cb/v1/trips/' . $trip_id . '/checkout' );
	$req->set_url_params( array( 'id' => $trip_id ) );
	return cb_create_checkout_session( $req );
}
function stripe_body() {
	foreach ( $GLOBALS['http'] as $h ) {
		if ( 0 === strpos( $h['url'], 'https://api.stripe.com/' ) ) { return $h['body']; }
	}
	return null; // Stripe not called
}
function charged_cents() { $b = stripe_body(); return $b ? (int) $b['line_items'][0]['price_data']['unit_amount'] : null; }

/* ---- A. what may be billed ---- */
section( 'A. what may be billed' );
$GLOBALS['fake'][ TRIP ] = trip();
check( "trip's own fee $225 per traveler, 1 traveler: $225", 225.0 === cb_trip_cbgv_fee_total( TRIP, $uid ) );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_price' => 1000 ) );
check( 'Travel price $1,000 is ignored: still $225', 225.0 === cb_trip_cbgv_fee_total( TRIP, $uid ) && 225.0 === cb_trip_charge_ceiling( TRIP, $uid ) );
foreach ( array( 1, 848, 5000, 99999.99 ) as $p ) {
	$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_price' => $p, 'cb_quoted_price' => $p ) );
	check( "Travel price and quoted price \$$p are ignored: still \$225", 225.0 === cb_trip_cbgv_fee_total( TRIP, $uid ) );
}
$GLOBALS['fake'][ TRIP ] = trip();
travelers( 1, 1 );
check( 'member + 1 adult + 1 child on the intake: 2 adults + 1 child at $225 = $562.50 (children pay half); the ceiling is the same', array( 'adults' => 2, 'children' => 1 ) === cb_trip_member_party( TRIP, $uid ) && 562.5 === cb_trip_cbgv_fee_total( TRIP, $uid ) && 562.5 === cb_trip_charge_ceiling( TRIP, $uid ) );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_price' => 1000 ) );
check( '...and with a Travel price of $1,000: still $562.50', 562.5 === cb_trip_cbgv_fee_total( TRIP, $uid ) );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cbv_lp_planning_fee' => array( 'mode' => 'custom', 'amount' => 300, 'basis' => 'per_cabin' ) ) );
check( 'per-cabin fee: children change nothing ($300 once; ceiling $300)', 300.0 === cb_trip_cbgv_fee_total( TRIP, $uid ) && 300.0 === cb_trip_charge_ceiling( TRIP, $uid ) );
$GLOBALS['fake'][ TRIP ] = trip();
travelers( 0, 0 );
$GLOBALS['umeta'] = array();
check( 'no intake filled in: 1 traveler', 1 === cb_trip_member_travelers( TRIP, $uid ) );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_extras_cost' => 40 ) );
check( 'approved extras $40 per member are added: $265', 265.0 === cb_trip_cbgv_fee_total( TRIP, $uid ) && 265.0 === cb_trip_charge_ceiling( TRIP, $uid ) );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_extras_cost' => -40 ) );
check( 'negative approved extras count as $0', 225.0 === cb_trip_cbgv_fee_total( TRIP, $uid ) );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cbv_lp_planning_fee' => array( 'mode' => 'custom', 'amount' => 150, 'basis' => 'per_traveler' ) ) );
check( "Helmets' set-up: own fee $150 per traveler", 150.0 === cb_trip_cbgv_fee_total( TRIP, $uid ) );
$GLOBALS['opt_fees'] = array( 'cruise' => array( 'amount' => 250, 'basis' => 'per_traveler' ) );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cbv_lp_planning_fee' => array( 'mode' => 'standard' ), 'cb_extras_cost' => 225 ) );
check( 'standard $250 entered but not live: the standard is not billed (only the extras)', 225.0 === cb_trip_cbgv_fee_total( TRIP, $uid ) );
$GLOBALS['opt_live'] = '1';
check( 'standard $250 once live: billed ($250 + extras)', 475.0 === cb_trip_cbgv_fee_total( TRIP, $uid ) );
$GLOBALS['opt_live'] = '0'; $GLOBALS['opt_fees'] = array();
$GLOBALS['fake'][ TRIP ] = trip( array( 'cbv_lp_planning_fee' => array( 'mode' => 'none' ), 'cb_price' => 1000 ) );
check( 'fee waived and no extras: nothing to bill, even with a Travel price of $1,000', 0.0 === cb_trip_cbgv_fee_total( TRIP, $uid ) && 0 == cb_trip_next_payment_amount( TRIP, $uid ) );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cbv_lp_planning_fee' => array(), 'cb_extras_cost' => 225 ) );
check( 'today (no fee set on the trip, $225 in the extras field): still $225, so the deploy alone changes nothing', 225.0 === cb_trip_cbgv_fee_total( TRIP, $uid ) );
$src = file_get_contents( WPMU_PLUGIN_DIR . '/checkedbags-gate09.php' );
$bodies = '';
foreach ( array( 'cb_trip_member_travelers', 'cb_trip_billable_fee', 'cb_trip_approved_extras', 'cb_trip_cbgv_fee_total', 'cb_trip_charge_ceiling', 'cb_trip_balance_due', 'cb_trip_next_payment_amount', 'cb_create_checkout_session' ) as $fn ) {
	preg_match( '/function ' . $fn . '\(.*?\n}\n/s', $src, $m );
	$bodies .= $m[0] ?? 'MISSING ' . $fn;
}
check( 'none of the money functions or the checkout read cb_price or cb_quoted_price', false === strpos( $bodies, 'MISSING' ) && false === strpos( preg_replace( '#^\s*(//|\*|/\*).*$#m', '', $bodies ), 'cb_price' ) && false === strpos( $bodies, 'cb_quoted_price' ) );

/* ---- B. balance and installments ---- */
section( 'B. balance and installments' );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_price' => 1000, 'cb_payment_mode' => 'deposit_installments', 'cb_deposit_amount' => 500, 'cb_num_installments' => 10 ) );
check( 'deposit setting $500 (more than the fee): first payment capped at $225', 225.0 == cb_trip_next_payment_amount( TRIP, $uid ) );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_price' => 1000, 'cb_payment_mode' => 'deposit_installments', 'cb_deposit_amount' => 50, 'cb_num_installments' => 4 ) );
$paid = array(); $steps = array();
for ( $i = 0; $i < 20; $i++ ) {
	$GLOBALS['fake'][ TRIP ]['cb_payments'] = $paid;
	$next = cb_trip_next_payment_amount( TRIP, $uid );
	if ( $next <= 0 ) { break; }
	$steps[] = $next; $paid[] = array( 'user_id' => $uid, 'amount' => $next, 'date' => '2026-10-08 00:00:00' );
}
check( 'a whole deposit + installments plan adds up to exactly $225', abs( array_sum( $steps ) - 225 ) < 0.02 && 50.0 == $steps[0], json_encode( $steps ) );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_price' => 1000, 'cb_payments' => array( array( 'user_id' => $uid, 'amount' => 225, 'date' => 'x' ) ) ) );
check( 'fee paid in full: nothing more is due, even with a Travel price of $1,000', 0.0 == cb_trip_balance_due( TRIP, $uid ) && 0 == cb_trip_next_payment_amount( TRIP, $uid ) );

/* ---- C. the Stripe checkout ---- */
section( 'C. the Stripe checkout (Stripe answered locally, never called)' );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_price' => 1000 ) );
$r = checkout();
check( 'cb_price = $1,000 is never charged: Stripe is asked for 22500 cents ($225), one request', is_array( $r ) && 22500 === charged_cents() && 1 === count( $GLOBALS['http'] ), json_encode( $GLOBALS['http'] ) );
$b = stripe_body();
check( 'Stripe name and charge description: "CBGV Group Experience Fee: Annual Family and Friends"', 'CBGV Group Experience Fee: Annual Family and Friends' === $b['line_items'][0]['price_data']['product_data']['name'] && 'CBGV Group Experience Fee: Annual Family and Friends' === $b['payment_intent_data']['description'] && 'usd' === $b['line_items'][0]['price_data']['currency'] );
$GLOBALS['fake'][ OTHER ] = trip( array( 'cbv_lp_planning_fee' => array( 'mode' => 'custom', 'amount' => 150, 'basis' => 'per_traveler' ) ) );
checkout( OTHER );
check( 'a trip name with "&": shown plainly ("HELMETS, HEARTS & HIGH SEAS"), $150', 'CBGV Group Experience Fee: HELMETS, HEARTS & HIGH SEAS' === stripe_body()['payment_intent_data']['description'] && 15000 === charged_cents(), json_encode( stripe_body()['payment_intent_data'] ?? null ) );
$GLOBALS['fake'][ OTHER ] = array( 'cb_roster' => array() );
foreach ( array( 1, 848, 99999.99 ) as $p ) {
	$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_price' => $p, 'cb_quoted_price' => $p ) );
	checkout();
	check( "Travel price \$$p: still charged 22500 cents", 22500 === charged_cents() );
}
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_price' => 1000 ) );
travelers( 1 );
checkout();
check( '2 adults on the intake: 45000 cents ($225 x 2)', 45000 === charged_cents() );
travelers( 1, 1 );
checkout();
check( '2 adults + 1 child, Travel price $1,000: 56250 cents ($562.50)', 56250 === charged_cents() );
$calls = 0; $GLOBALS['guard'] = array();
$GLOBALS['fake'][ TRIP ] = trip( array( 'cbv_lp_planning_fee' => function () use ( &$calls ) { return ++$calls === 1 ? array( 'mode' => 'custom', 'amount' => 250, 'basis' => 'per_traveler' ) : array( 'mode' => 'custom', 'amount' => 225, 'basis' => 'per_traveler' ); } ) );
$r = checkout();
check( 'the ceiling counts the child at half: $625 asked against a $562.50 limit is refused and logged', is_wp_error( $r ) && null === stripe_body() && 562.5 === ( $GLOBALS['guard'][0]['limit'] ?? 0 ), json_encode( $GLOBALS['guard'] ) );
$GLOBALS['umeta'] = array();
$GLOBALS['fake'][ TRIP ] = trip( array( 'cbv_lp_planning_fee' => array( 'mode' => 'none' ), 'cb_price' => 1000 ) );
$r = checkout();
check( 'no fee and no extras (Travel price $1,000): no checkout, Stripe never called', is_wp_error( $r ) && 'cb_nothing_due' === $r->get_error_code() && array() === $GLOBALS['http'] );
// The ceiling: if the amount worked out is ever above fee x travelers + extras (less what is paid), refuse and log.
$calls = 0; $GLOBALS['guard'] = array();
$GLOBALS['fake'][ TRIP ] = trip( array( 'cbv_lp_planning_fee' => function () use ( &$calls ) { return ++$calls === 1 ? array( 'mode' => 'custom', 'amount' => 5000, 'basis' => 'per_traveler' ) : array( 'mode' => 'custom', 'amount' => 225, 'basis' => 'per_traveler' ); } ) );
$r = checkout();
$g = $GLOBALS['guard'][0] ?? array();
check( 'the ceiling refuses an over-limit amount ($5,000 against a $225 limit) before Stripe is called', is_wp_error( $r ) && 'cb_amount_guard' === $r->get_error_code() && null === stripe_body(), is_wp_error( $r ) ? $r->get_error_code() : json_encode( $GLOBALS['http'] ) );
check( '...and logs it (checkout refused, trip, member, amount $5,000, limit $225)', 'checkout refused' === ( $g['where'] ?? '' ) && TRIP === ( $g['trip_id'] ?? 0 ) && $uid === ( $g['user_id'] ?? 0 ) && 5000.0 === ( $g['amount'] ?? 0 ) && 225.0 === ( $g['limit'] ?? 0 ), json_encode( $g ) );
$GLOBALS['guard'] = array();
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_payments' => array( array( 'user_id' => $uid, 'amount' => 200, 'date' => 'x' ) ) ) );
checkout();
check( 'part paid ($200 of $225): only the $25 left is charged, nothing logged', 2500 === charged_cents() && array() === $GLOBALS['guard'] );
$calls = 0; $GLOBALS['guard'] = array();
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_payments' => array( array( 'user_id' => $uid, 'amount' => 200, 'date' => 'x' ) ), 'cbv_lp_planning_fee' => function () use ( &$calls ) { return ++$calls === 1 ? array( 'mode' => 'custom', 'amount' => 300, 'basis' => 'per_traveler' ) : array( 'mode' => 'custom', 'amount' => 225, 'basis' => 'per_traveler' ); } ) );
$r = checkout();
check( 'the ceiling counts what is already paid: $100 asked with $200 of a $225 limit paid is refused and logged (limit left $25)', is_wp_error( $r ) && null === stripe_body() && 25.0 === ( $GLOBALS['guard'][0]['limit'] ?? 0 ), json_encode( $GLOBALS['guard'] ) );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_roster' => array( 999999 ) ) );
$r = checkout();
check( 'not on the trip: refused, Stripe never called', is_wp_error( $r ) && null === stripe_body() );

/* ---- D. the Stripe webhook ---- */
section( 'D. the Stripe webhook' );
if ( defined( 'CB_STRIPE_WEBHOOK_SECRET' ) ) {
	$hook = function ( $amount_cents ) {
		$payload = json_encode( array( 'type' => 'checkout.session.completed', 'data' => array( 'object' => array( 'id' => 'cs_test_x', 'amount_total' => $amount_cents, 'metadata' => array( 'trip_id' => TRIP, 'user_id' => 1 ) ) ) ) );
		$t = time();
		$req = new WP_REST_Request( 'POST', '/cb/v1/stripe-webhook' );
		$req->set_body( $payload );
		$req->set_header( 'stripe_signature', 't=' . $t . ',v1=' . hash_hmac( 'sha256', $t . '.' . $payload, CB_STRIPE_WEBHOOK_SECRET ) );
		$GLOBALS['writes'] = array(); $GLOBALS['guard'] = array();
		return cb_handle_stripe_webhook( $req );
	};
	$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_price' => 1000 ) );
	$hook( 22500 );
	check( 'webhook: a $225 payment is recorded (write intercepted) and nothing is logged', 1 === count( $GLOBALS['writes'] ) && 'cb_payments' === $GLOBALS['writes'][0][2] && array() === $GLOBALS['guard'] );
	$hook( 100000 );
	check( 'webhook: a $1,000 payment Stripe reports above the $225 limit is still recorded, and logged for reconciliation', 'webhook over limit' === ( $GLOBALS['guard'][0]['where'] ?? '' ) && 1000.0 === $GLOBALS['guard'][0]['amount'] && 1 === count( $GLOBALS['writes'] ) );
} else {
	check( 'webhook secret is configured on this site (needed for the webhook checks)', false );
}

/* ---- E. Gate 12 quote acceptance ---- */
section( 'E. Gate 12 quote acceptance' );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_status' => 'quoted', 'cb_quoted_price' => 1000 ) );
$GLOBALS['writes'] = array(); $GLOBALS['http'] = array();
$req = new WP_REST_Request( 'POST', '/cb/v1/trips/' . TRIP . '/accept-quote' );
$req->set_url_params( array( 'id' => TRIP ) );
$r = cb_accept_trip_quote( $req );
$w = array(); foreach ( $GLOBALS['writes'] as $x ) { if ( 'update_post_metadata' === $x[0] ) { $w[ $x[2] ] = $x[3]; } }
check( 'accepting a $1,000 quote stores it only as the reference Travel price; no fee, extras or payment is touched; no payment service called', is_array( $r ) && 1000.0 === $w['cb_price'] && ! isset( $w['cb_extras_cost'] ) && ! isset( $w['cbv_lp_planning_fee'] ) && ! isset( $w['cb_payments'] ) && array() === $GLOBALS['http'], json_encode( $w ) );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_status' => 'accepted', 'cb_price' => 1000, 'cb_quoted_price' => 1000 ) );
checkout();
check( 'after acceptance: the checkout still charges only the $225 fee', 22500 === charged_cents() );
$g12 = file_get_contents( WPMU_PLUGIN_DIR . '/checkedbags-gate12.php' );
check( 'member-facing quote text: travel price paid directly to the cruise line or supplier; never "pay your deposit" on Gate 09', false !== strpos( $g12, '(travel price, paid directly to the cruise line or supplier)' ) && false !== strpos( $g12, 'paid directly to the cruise line or supplier, never to CBGV' ) && false === strpos( $g12, 'to pay your deposit' ) );

/* ---- F. what members and admins see ---- */
section( 'F. Payment page, admin labels, roster export' );
$GLOBALS['fake'][ TRIP ] = trip( array( 'cb_price' => 1000, 'cb_extras_cost' => 40 ) );
travelers( 1 );
$GLOBALS['http'] = array();
$html = do_shortcode( '[cb_gate_payments]' );
$page_http = $GLOBALS['http'];
$GLOBALS['umeta'] = array();
check( 'Payment page: "$0.00 of $490.00", "Pay $490.00", fee line "(2 adults x $225.00)", extras $40.00, "Total owed to CBGV"', false !== strpos( $html, '$0.00 of $490.00' ) && false !== strpos( $html, 'Pay $490.00' ) && false !== strpos( $html, '(2 adults x $225.00)' ) && false !== strpos( $html, 'Approved extras: $40.00' ) && false !== strpos( $html, 'Total owed to CBGV: $490.00' ), substr( strip_tags( $html ), 0, 500 ) );
travelers( 1, 1 );
$html_kid = do_shortcode( '[cb_gate_payments]' );
$GLOBALS['umeta'] = array();
check( 'Payment page with a child: "(2 adults x $225.00 + 1 child x $112.50)", total $602.50', false !== strpos( $html_kid, '(2 adults x $225.00 + 1 child x $112.50)' ) && false !== strpos( $html_kid, 'Total owed to CBGV: $602.50' ), substr( strip_tags( $html_kid ), 0, 500 ) );
check( 'Payment page never shows the travel price ($1,000) or "Price per person"', false === strpos( $html, '1,000' ) && false === stripos( $html, 'Price per person' ) );
check( 'Payment page: no "Commitment Fee" and no "InteleTravel"', false === stripos( $html, 'Commitment Fee' ) && false === stripos( $html, 'InteleTravel' ) );
check( 'showing the Payment page calls no payment service (only the exchange-rate lookups, blocked here)', array() === array_filter( $page_http, function ( $h ) { return ! preg_match( '#^https://(api\.frankfurter\.dev|open\.er-api\.com)/#', $h['url'] ); } ), json_encode( array_column( $page_http, 'url' ) ) );
$tsrc = file_get_contents( WPMU_PLUGIN_DIR . '/checkedbags-trips.php' );
check( 'admin: "Travel price (reference only, never charged here)" and the quoted travel price likewise; no "Price per person ($)" label left', false !== strpos( $tsrc, '<label for="cb_price">Travel price (reference only, never charged here) ($)</label>' ) && false !== strpos( $tsrc, 'Quoted travel price per person (reference only, never charged here) ($)' ) && false === strpos( $tsrc, '>Price per person ($)<' ) );
ob_start(); cb_render_payment_meta_box( get_post( TRIP ) ); $box = ob_get_clean();
check( 'admin: the extras field is "Approved extras ($ per member)", never travel costs', false !== strpos( $box, 'Approved extras ($ per member)' ) && false !== strpos( $box, 'Never travel costs' ) );
$rsrc = file_get_contents( WPMU_PLUGIN_DIR . '/checkedbags-roster-export.php' );
check( 'roster export: "Paid in Full" is judged on what the member owes CBGV, not the travel price', false !== strpos( $rsrc, 'cb_trip_cbgv_fee_total( $trip_id, $user_id )' ) && false !== strpos( $rsrc, "( \$fee_total > 0 && \$balance_due <= 0 ) ? 'Paid in Full'" ) );

/* ---- G. nothing escaped ---- */
section( 'G. isolation held' );
check( 'no email was sent', 0 === $GLOBALS['mail'] );
remove_all_filters( 'get_post_metadata' ); remove_all_filters( 'pre_option_cb_payment_guard_log' );
check( 'real trip 181 untouched: no payments stored, Travel price still $0, its Extras Cost still $225', array() === array_filter( (array) get_post_meta( TRIP, 'cb_payments', true ) ) && 0.0 === (float) get_post_meta( TRIP, 'cb_price', true ) && 225.0 === (float) get_post_meta( TRIP, 'cb_extras_cost', true ) );
check( 'nothing was written to the real safeguard log', false === get_option( 'cb_payment_guard_log', false ) );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
