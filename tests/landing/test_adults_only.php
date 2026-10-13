<?php
/*
 * Adults only (18+) tests (2026-10-13): provider setting with a per-trip override; on an adults-only trip there is
 * no "Children pay half" line (price board note, proposal PDF) and no children fields on the traveler intake form.
 * Also: the Reply-To on Ultimate Member's rejection email, and on no other email.
 * Run against a temp COPY of the mu-plugins folder with the changed files overlaid:
 *   wp --require=/tmp/cbv_t/define.php eval-file test_adults_only.php
 * NO writes and NO EMAIL SENT: fake posts/meta live in memory, every meta write is intercepted, wp_mail is
 * short-circuited by pre_wp_mail. Set CBV_VERBOSE=1 to print every passing check.
 */
$GLOBALS['n'] = 0; $GLOBALS['fail'] = 0;
function check( $l, $c, $d = '' ) {
	$GLOBALS['n']++;
	if ( ! $c ) { $GLOBALS['fail']++; echo "FAIL: $l $d\n"; }
	elseif ( getenv( 'CBV_VERBOSE' ) ) { echo '  pass ' . str_pad( $GLOBALS['n'], 3, ' ', STR_PAD_LEFT ) . ": $l\n"; }
}
check( 'running against the temp mu-plugins copy', '/tmp/cbv_mu' === WPMU_PLUGIN_DIR );
check( 'the adults-only code is loaded', function_exists( 'cbv_trip_is_adults_only' ) && function_exists( 'cbv_provider_is_adults_only' ) );

$fake = array(); $writes = array();
add_filter( 'get_post_metadata', function ( $v, $oid, $key ) use ( &$fake ) {
	if ( isset( $fake[ $oid ] ) && array_key_exists( $key, $fake[ $oid ] ) ) { return array( $fake[ $oid ][ $key ] ); }
	return $v;
}, 10, 3 );
foreach ( array( 'update_post_metadata', 'add_post_metadata', 'delete_post_metadata', 'update_user_metadata', 'add_user_metadata' ) as $hook ) {
	add_filter( $hook, function ( $check, $oid, $key, $value = null ) use ( &$writes, $hook ) { $writes[] = array( $hook, $oid, $key, $value ); return true; }, 1, 4 );
}
function fake_post( $id, $type, $title ) {
	$p = new WP_Post( (object) array( 'ID' => $id, 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title, 'post_author' => 1, 'post_name' => sanitize_title( $title ), 'post_date' => '2026-01-01 00:00:00', 'post_date_gmt' => '2026-01-01 00:00:00', 'post_content' => '', 'post_excerpt' => '', 'post_mime_type' => '', 'filter' => 'raw' ) );
	wp_cache_set( $id, $p, 'posts' );
	return $p;
}
const TRIP = 999970; const PROV = 999971;
fake_post( TRIP, 'cb_trip', 'Adults Voyage' );
fake_post( PROV, 'cb_provider', 'Adults Line' );
$fee = array( 'mode' => 'custom', 'amount' => 225, 'basis' => 'per_traveler' );

/* ---- A. the rule ---- */
$fake[ PROV ] = array( 'cbv_lp_adults_only' => '1' );
$fake[ TRIP ] = array( 'cbv_lp_provider_id' => PROV, 'cbv_lp_adults_only' => '' );
check( 'provider marked adults only, trip follows it', true === cbv_trip_is_adults_only( TRIP ) );
$fake[ TRIP ]['cbv_lp_adults_only'] = 'no';
check( 'trip says No: not adults only, whatever the provider', false === cbv_trip_is_adults_only( TRIP ) );
$fake[ PROV ]['cbv_lp_adults_only'] = '';
$fake[ TRIP ]['cbv_lp_adults_only'] = 'yes';
check( 'trip says Yes: adults only, whatever the provider', true === cbv_trip_is_adults_only( TRIP ) );
$fake[ TRIP ]['cbv_lp_adults_only'] = 'inherit';
check( 'provider not marked, trip follows: not adults only', false === cbv_trip_is_adults_only( TRIP ) );
$fake[ TRIP ] = array( 'cbv_lp_provider_id' => 0, 'cbv_lp_adults_only' => 'junk' );
check( 'no provider and no valid choice: not adults only; junk counts as "same as provider"', false === cbv_trip_is_adults_only( TRIP ) && 'inherit' === cbv_clean_adults_only_choice( 'junk' ) );
check( 'no trip: not adults only', false === cbv_trip_is_adults_only( 0 ) );

/* ---- B. "Children pay half": price board note and proposal PDF ---- */
$fake[ TRIP ] = array( 'cbv_lp_provider_id' => 0, 'cbv_lp_adults_only' => 'yes', 'cbv_lp_planning_fee' => $fee, 'cb_start_date' => '2027-10-25' );
$note = implode( ' ', cbv_lp_planning_fee_note( TRIP ) );
check( 'adults-only trip: the board note keeps the refund wording but drops "Children pay half"', false !== strpos( $note, 'refundable' ) && false === stripos( $note, 'children' ), $note );
$fake[ TRIP ]['cbv_lp_adults_only'] = 'no';
check( 'trip with children: the board note says "Children pay half"', false !== strpos( implode( ' ', cbv_lp_planning_fee_note( TRIP ) ), 'Children pay half the CBGV Group Experience Fee.' ) );
$pt = array( 'occupancy_count' => 2, 'voyage_fare' => 2000, 'taxes_fees' => 400, 'gratuities' => 160, 'insurance' => 150, 'discount' => 0, 'pricing_basis' => 'per_cabin', 'price_column' => '' );
$trip = array( 'id' => TRIP, 'pricing_tiers' => array( array( 'name' => 'Inside', 'capacity_low' => 2, 'capacity_high' => 2, 'occupancy_points' => array( $pt ), 'addons' => array() ) ), 'single_price' => 0, 'price_columns' => array(), 'planning_fee' => array( 'amount' => 225, 'basis' => 'per_traveler', 'source' => 'trip' ) );
check( 'trip with children: the proposal PDF says "Children pay half"', false !== strpos( cb_proposal_render_pricing_html( $trip, true ), 'Children pay half' ) );
$fake[ TRIP ]['cbv_lp_adults_only'] = 'yes';
$p = cb_proposal_render_pricing_html( $trip, true );
check( 'adults-only trip: the proposal PDF has no "Children pay half", fee column and totals unchanged', false === stripos( $p, 'children' ) && false !== strpos( $p, 'CBGV Group Experience Fee / Cabin' ) && false !== strpos( $p, '$450.00' ) );
check( 'adults-only trip: the Internal Data Sheet has no "Children pay half" either', false === stripos( cb_proposal_render_pricing_html( $trip ), 'children' ) );

/* ---- C. the traveler intake form (REAL trip 320, read only, setting faked) ---- */
wp_set_current_user( 1 );
// The form hides itself once both admin "received" flags are set (true for the admin on trip 320), so stand in "not received".
$unrecv = function ( $v, $uid, $key ) { return in_array( $key, array( '_insurance_waiver_received_320', '_cc_auth_received_320' ), true ) ? array( '' ) : $v; };
add_filter( 'get_user_metadata', $unrecv, 10, 3 );
$fake[320] = array( 'cbv_lp_adults_only' => 'no' );
ob_start(); $r = cbv_render_traveler_intake_form( 320 ); $f_kids = ob_get_clean() . (string) $r;
$fake[320] = array( 'cbv_lp_adults_only' => 'yes' );
ob_start(); $r = cbv_render_traveler_intake_form( 320 ); $f_adults = ob_get_clean() . (string) $r;
unset( $fake[320] );
remove_filter( 'get_user_metadata', $unrecv, 10 );
$has_form = false !== strpos( $f_kids, 'cbv-intake-additional-adults' );
check( 'the intake form renders for the admin on trip 320 (needed for the next two checks)', $has_form, substr( strip_tags( $f_kids ), 0, 200 ) );
check( 'trip with children: "Additional children" and "Children\'s ages" fields are there', false !== strpos( $f_kids, 'id="cbv-intake-additional-children"' ) && false !== strpos( $f_kids, 'id="cbv-intake-children-ages"' ) );
check( 'adults-only trip: no children fields, the adults field stays', false === strpos( $f_adults, 'id="cbv-intake-additional-children"' ) && false === strpos( $f_adults, 'id="cbv-intake-children-ages"' ) && false === strpos( $f_adults, 'Additional children traveling with you' ) && false !== strpos( $f_adults, 'id="cbv-intake-additional-adults"' ) );
wp_set_current_user( 0 );

/* ---- D. admin boxes and save ---- */
wp_set_current_user( 1 );
$fake[ PROV ] = array( 'cbv_lp_adults_only' => '1' );
ob_start(); cbv_render_adults_only_provider_box( get_post( PROV ) ); $pb = ob_get_clean();
check( 'provider box: ticked checkbox "Adults only (18+)" and the nonce', 1 === preg_match( "/name=\"cbv_lp_adults_only\" value=\"1\"\s+checked='checked'/", $pb ) && false !== strpos( $pb, 'Adults only (18+)' ) && false !== strpos( $pb, 'cbv_adults_only_nonce' ) );
$fake[ TRIP ] = array( 'cbv_lp_provider_id' => PROV, 'cbv_lp_adults_only' => '' );
ob_start(); cbv_render_adults_only_trip_box( get_post( TRIP ) ); $tb = ob_get_clean();
check( 'trip box: three choices, "Same as the provider (adults only)" selected', 1 === preg_match( "/value=\"inherit\"\s+selected='selected'/", $tb ) && false !== strpos( $tb, 'Same as the provider (adults only)' ) && false !== strpos( $tb, 'value="yes"' ) && false !== strpos( $tb, 'value="no"' ), $tb );
function run_ao_save( $post_id ) {
	global $wp_filter;
	foreach ( $wp_filter['save_post']->callbacks as $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( $fn instanceof Closure && basename( ( new ReflectionFunction( $fn ) )->getFileName() ) === 'checkedbags-adults-only.php' ) { $fn( $post_id, get_post( $post_id ) ); return true; }
		}
	}
	return false;
}
check( 'the save handler is registered', run_ao_save( TRIP ) );
$writes = array(); $_POST = array( 'cbv_adults_only_nonce' => 'bad', 'cbv_lp_adults_only' => 'yes' ); run_ao_save( TRIP );
check( 'bad nonce: nothing written', array() === $writes );
$writes = array(); $_POST = array( 'cbv_adults_only_nonce' => wp_create_nonce( 'cbv_adults_only_save' ), 'cbv_lp_adults_only' => 'YES<b>' ); run_ao_save( TRIP );
$by = array(); foreach ( $writes as $w ) { if ( 'update_post_metadata' === $w[0] ) { $by[ $w[1] ][ $w[2] ] = $w[3]; } }
check( 'trip save: an unknown value is stored as "inherit"', array( TRIP => array( 'cbv_lp_adults_only' => 'inherit' ) ) === $by, json_encode( $by ) );
$writes = array(); $_POST = array( 'cbv_adults_only_nonce' => wp_create_nonce( 'cbv_adults_only_save' ), 'cbv_lp_adults_only' => 'yes' ); run_ao_save( TRIP );
$by = array(); foreach ( $writes as $w ) { if ( 'update_post_metadata' === $w[0] ) { $by[ $w[1] ][ $w[2] ] = $w[3]; } }
check( 'trip save: "yes" stored', array( TRIP => array( 'cbv_lp_adults_only' => 'yes' ) ) === $by );
$writes = array(); $_POST = array( 'cbv_adults_only_nonce' => wp_create_nonce( 'cbv_adults_only_save' ) ); run_ao_save( PROV );
$by = array(); foreach ( $writes as $w ) { if ( 'update_post_metadata' === $w[0] ) { $by[ $w[1] ][ $w[2] ] = $w[3]; } }
check( 'provider save: unticked stores ""', array( PROV => array( 'cbv_lp_adults_only' => '' ) ) === $by );
wp_set_current_user( 0 );
$writes = array(); $_POST = array( 'cbv_adults_only_nonce' => wp_create_nonce( 'cbv_adults_only_save' ), 'cbv_lp_adults_only' => 'yes' ); run_ao_save( TRIP );
check( 'logged out: nothing written', array() === $writes );
$_POST = array();

/* ---- E. Reply-To on the rejection email only (captured, never sent) ---- */
$GLOBALS['mails'] = array();
add_filter( 'pre_wp_mail', function ( $null, $atts ) { $GLOBALS['mails'][] = $atts; return true; }, 1, 2 );
function hdrs( $atts ) { $h = $atts['headers'] ?? array(); return is_array( $h ) ? implode( "\n", $h ) : (string) $h; }
UM()->mail()->send( 'applicant@example.test', 'rejected_email' );
$rej = end( $GLOBALS['mails'] );
check( 'rejection email: built (captured, not sent) with the new subject', $rej && 'An update on your Checked Bags & Good Vibes application' === html_entity_decode( $rej['subject'] ?? '' ), json_encode( $rej['subject'] ?? null ) );
check( 'rejection email: Reply-To is LaDon', 1 === preg_match( '/^Reply-To: Checked Bags & Good Vibes <LaDon@journeywellglobal\.com>$/mi', hdrs( $rej ) ), hdrs( $rej ) );
check( 'rejection email: only one Reply-To, and the From line is kept', 1 === preg_match_all( '/^reply-to:/mi', hdrs( $rej ) ) && false !== stripos( hdrs( $rej ), 'From:' ) );
wp_mail( 'next@example.test', 'Right after', 'Body' );
check( 'the very next email (not from UM) gets no Reply-To: the rejection flag does not leak', false === stripos( hdrs( end( $GLOBALS['mails'] ) ), 'reply-to' ) );
UM()->mail()->send( 'applicant@example.test', 'approved_email' );
check( 'approval email: no Reply-To', false === stripos( hdrs( end( $GLOBALS['mails'] ) ), 'reply-to' ) );
wp_mail( 'someone@example.test', 'Plain', 'Body' );
check( 'any other email: no Reply-To', false === stripos( hdrs( end( $GLOBALS['mails'] ) ), 'reply-to' ) );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
