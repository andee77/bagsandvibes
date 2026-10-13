<?php
/*
 * Payment wording tests (2026-10-13): the member welcome email and Gate 12's Build Your Own Trip fine print
 * never say CBGV handles travel payments or takes a "planning deposit".
 * Run against a temp COPY of the mu-plugins folder with the changed files overlaid:
 *   wp --require=/tmp/cbv_t/define.php eval-file test_payment_wording.php
 * No writes and NO EMAIL SENT: wp_mail is short-circuited by pre_wp_mail and the message is only captured.
 * Set CBV_VERBOSE=1 to print every passing check.
 */
$GLOBALS['n'] = 0; $GLOBALS['fail'] = 0;
function check( $l, $c, $d = '' ) {
	$GLOBALS['n']++;
	if ( ! $c ) { $GLOBALS['fail']++; echo "FAIL: $l $d\n"; }
	elseif ( getenv( 'CBV_VERBOSE' ) ) { echo '  pass ' . str_pad( $GLOBALS['n'], 3, ' ', STR_PAD_LEFT ) . ": $l\n"; }
}
check( 'running against the temp mu-plugins copy', '/tmp/cbv_mu' === WPMU_PLUGIN_DIR );

/* ---- the welcome email (captured, never sent) ---- */
$GLOBALS['mails'] = array();
add_filter( 'pre_wp_mail', function ( $null, $atts ) { $GLOBALS['mails'][] = $atts; return true; }, 1, 2 );
foreach ( array( 'update_user_metadata', 'add_user_metadata', 'update_post_metadata', 'add_post_metadata' ) as $hook ) {
	add_filter( $hook, function () { $GLOBALS['writes'][] = func_get_args(); return true; }, 1, 4 );
}
cbv_send_new_member_welcome_email( 1 );
check( 'the welcome email is built once (and captured, not sent)', 1 === count( $GLOBALS['mails'] ) );
$body = wp_strip_all_tags( html_entity_decode( (string) ( $GLOBALS['mails'][0]['message'] ?? '' ) ) );
$body = preg_replace( '/\s+/', ' ', $body );
check( 'email: "plans your group program and coordinates activities"', false !== strpos( $body, 'Our team designs a tailored itinerary, plans your group program and coordinates activities.' ) );
check( 'email: travel is booked by the independent travel advisor and paid directly to the supplier', false !== strpos( $body, 'Your travel is booked by our independent travel advisor and paid directly to the cruise line or supplier.' ) );
check( 'email: no "manages individual payments", no "accommodation bookings", no host agency name', false === stripos( $body, 'manages individual payments' ) && false === stripos( $body, 'handles accommodation bookings' ) && false === stripos( $body, 'InteleTravel' ) );

/* ---- Gate 12 fine print (as a signed-in member) ---- */
wp_set_current_user( 1 );
$g12 = preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( do_shortcode( '[cb_gate_requests]' ) ) ) );
check( 'Gate 12: the fee is shown with the quote before accepting', false !== strpos( $g12, 'The CBGV Group Experience Fee for your trip will be shown with your quote before you accept it.' ) );
check( 'Gate 12: the rest of the fine print is kept (6–12 months, under 4 travelers)', false !== strpos( $g12, 'We recommend booking 6–12 months ahead for custom group trips. If your group falls below 4 travelers, per-person pricing may adjust.' ) );
check( 'Gate 12: no "planning deposit"', false === stripos( $g12, 'planning deposit' ) );
wp_set_current_user( 0 );

/* ---- source scan: the old wording is gone from the code ---- */
$left = array();
foreach ( glob( WPMU_PLUGIN_DIR . '/checkedbags-*.php' ) as $f ) {
	$src = file_get_contents( $f );
	foreach ( array( 'manages individual payments', 'planning deposit may apply' ) as $old ) {
		if ( false !== stripos( $src, $old ) ) { $left[] = basename( $f ) . ": $old"; }
	}
}
check( 'no file still has "manages individual payments" or "planning deposit may apply"', array() === $left, implode( '; ', $left ) );
check( 'nothing was written', empty( $GLOBALS['writes'] ) );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
