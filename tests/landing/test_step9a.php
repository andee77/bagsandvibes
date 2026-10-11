<?php
/*
 * Step 9a tests: price notes (pricing note on the current public page, Gate 07 card note) and their box/save,
 * the legacy "Reserve Your Spot" block removed on the new design (itinerary PDF button kept), and the
 * members-only forum rule (Gate 10 section 8).
 * Run against a temp COPY of the mu-plugins folder with the Step 9a files overlaid:
 *   wp --require=/tmp/cbv_t/define.php eval-file test_step9a.php
 * NO database writes: fake posts live in the in-memory object cache, meta is faked by a filter, and every
 * write is intercepted. Set CBV_VERBOSE=1 to print every passing check.
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
check( 'Step 9a code is loaded', function_exists( 'cbv_render_pricing_note' ) && function_exists( 'cbv_render_card_note' ) && function_exists( 'cbv_lp_strip_legacy_cta' ) && function_exists( 'cb_gate10_guest_must_sign_in' ) );

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
const TRIP = 999960;
fake_post( TRIP, 'cb_trip', 'publish', 'Notes Voyage' );
$uplift = "Payment options: pay in full when you book, or apply for monthly payments through Uplift, Virgin Voyages' financing partner (subject to approval).";

/* ---- A. cleaning and drawing the notes ---- */
section( 'A. cleaning and drawing the notes' );
check( 'pricing note: tags stripped, trimmed, line breaks kept', "Pay **now**\nor later" === cbv_clean_pricing_note( "  <b>Pay</b> **now**\nor later <script>x</script> " ) );
check( 'pricing note: capped at 600 characters', 600 === mb_strlen( cbv_clean_pricing_note( str_repeat( 'é', 700 ) ) ) );
check( 'pricing note: non-text input gives ""', '' === cbv_clean_pricing_note( array( 'x' ) ) && '' === cbv_clean_pricing_note( null ) );
check( 'card note: one line, tags stripped, capped at 80', 'Pay in full or monthly' === cbv_clean_card_note( " Pay in full\nor <i>monthly</i> " ) && 80 === mb_strlen( cbv_clean_card_note( str_repeat( 'x', 120 ) ) ) );
$fake[ TRIP ] = array( 'cb_pricing_note' => '', 'cb_card_note' => '' );
check( 'blank notes draw nothing', '' === cbv_render_pricing_note( TRIP ) && '' === cbv_render_card_note( TRIP ) );
$fake[ TRIP ] = array( 'cb_pricing_note' => "**Payment options:** see [Virgin](https://www.virginvoyages.com) & more\nLine 2 <img src=x>", 'cb_card_note' => 'Pay in full or monthly with Uplift & co <b>' );
$pn = cbv_render_pricing_note( TRIP );
check( 'pricing note: in its own paragraph, **bold** and links work, & escaped, line break kept, no raw tag', 0 === strpos( $pn, '<p class="cbv-landing-pricing-note">' ) && false !== strpos( $pn, '<strong>Payment options:</strong>' ) && false !== strpos( $pn, '<a href="https://www.virginvoyages.com" rel="noopener">Virgin</a>' ) && false !== strpos( $pn, '&amp; more<br>' ) && false === strpos( $pn, '<img' ), $pn );
$fake[ TRIP ]['cb_pricing_note'] = '[x](javascript:alert(1))';
check( 'pricing note: a javascript: link keeps only its words', false === strpos( cbv_render_pricing_note( TRIP ), 'href' ) );
check( 'card note: plain text, escaped, in its own paragraph', '<p class="trip-card-note">Pay in full or monthly with Uplift &amp; co</p>' === cbv_render_card_note( TRIP ), cbv_render_card_note( TRIP ) );

/* ---- B. the current public page and Gate 07 (REAL trips, read only, notes faked) ---- */
section( 'B. current public page and Gate 07' );
$fake[320] = array( 'cb_pricing_note' => $uplift, 'cb_card_note' => 'Pay in full or monthly with Uplift' );
$legacy = cbv_render_public_trip_landing( 320 );
$at     = strpos( $legacy, 'cbv-landing-pricing-note' );
check( 'REAL trip 320, current page: the pricing note is drawn once', 1 === substr_count( $legacy, 'class="cbv-landing-pricing-note"' ) );
check( 'REAL trip 320, current page: it sits under the "Choose Your Option" cards, before the Reserve block', false !== $at && strpos( $legacy, 'Choose Your Option' ) < $at && strrpos( substr( $legacy, 0, $at ), 'cbv-landing-pricing-card' ) !== false && $at < strpos( $legacy, 'cbv-landing-cta' ) );
check( 'REAL trip 320, current page: Uplift wording shown with "subject to approval", Reserve block still there', false !== strpos( $legacy, 'Uplift, Virgin Voyages&#039; financing partner (subject to approval)' ) && false !== strpos( $legacy, 'Reserve Your Spot' ) );
unset( $fake[320] );
check( 'REAL trip 320, current page: no note saved, nothing drawn', false === strpos( cbv_render_public_trip_landing( 320 ), 'cbv-landing-pricing-note' ) );

$fake[320] = array( 'cb_card_note' => 'Pay in full or monthly with Uplift' );
wp_set_current_user( 1 );
$g07 = do_shortcode( '[cb_gate_vacations]' );
$h   = strpos( $g07, 'HELMETS' );
$n   = strpos( $g07, '<p class="trip-card-note">Pay in full or monthly with Uplift</p>' );
check( 'Gate 07: the Helmets card shows its note, under the price and above the spots', false !== $h && false !== $n && $n > strpos( $g07, 'trip-card-price', $h ) && $n < strpos( $g07, 'trip-card-spots', $h ), substr( $g07, (int) $h, 600 ) );
check( 'Gate 07: only one card has a note (trip 181 has none)', 1 === substr_count( $g07, 'trip-card-note' ) );
unset( $fake[320] );
check( 'Gate 07: no note saved, none drawn', false === strpos( do_shortcode( '[cb_gate_vacations]' ), 'trip-card-note' ) );
wp_set_current_user( 0 );

/* ---- C. the legacy Reserve block on the new design ---- */
section( 'C. legacy Reserve block' );
$cta_pdf = '<div class="cbv-landing-section"><h2>About</h2></div><div class="cbv-landing-cta"><a class="btn btn-ticket" href="/join/?trip=X">Reserve Your Spot</a><p class="cbv-landing-cta-note">This submits a request to join.</p><a class="btn btn-ghost" href="/x.pdf" target="_blank" rel="noopener">Download Full Itinerary PDF</a></div><p class="cbv-landing-disclaimer">*Fine print</p>';
$s = cbv_lp_strip_legacy_cta( $cta_pdf );
check( 'strip: Reserve button and its note go; the PDF button, the block around it, and everything else stay', false === strpos( $s, 'Reserve Your Spot' ) && false === strpos( $s, 'cbv-landing-cta-note' ) && false !== strpos( $s, 'Download Full Itinerary PDF' ) && false !== strpos( $s, 'class="cbv-landing-cta"' ) && false !== strpos( $s, '<h2>About</h2>' ) && false !== strpos( $s, '*Fine print' ), $s );
$s = cbv_lp_strip_legacy_cta( '<div class="cbv-landing-cta"><a class="btn btn-ticket" href="/join/">Reserve Your Spot</a><p class="cbv-landing-cta-note">Note</p></div><p>After</p>' );
check( 'strip: with no PDF button the empty block goes too', false === strpos( $s, 'cbv-landing-cta' ) && false !== strpos( $s, '<p>After</p>' ), $s );
check( 'strip: unchanged when there is no Reserve block, or nothing at all', '<div class="cbv-landing-section"><h2>About</h2></div>' === cbv_lp_strip_legacy_cta( '<div class="cbv-landing-section"><h2>About</h2></div>' ) && '' === cbv_lp_strip_legacy_cta( '' ) );
check( 'strip: a ticket button outside the Reserve block is left alone', false !== strpos( cbv_lp_strip_legacy_cta( '<div class="other"><a class="btn btn-ticket" href="#">Go</a></div><div class="cbv-landing-cta"><p class="cbv-landing-cta-note">n</p></div>' ), '>Go</a>' ) );

$page    = cbv_lp_render_trip( 320, 'new' );
$wrap    = substr( $page, (int) strpos( $page, 'cbv-lp-legacy-wrap' ) );
$has_pdf = false !== strpos( cbv_render_public_trip_landing( 320 ), 'Download Full Itinerary PDF' );
check( 'REAL trip 320, new design: the board is there, the old Reserve button and note are gone', false !== strpos( $page, 'class="cbv-lp-prices"' ) && false === strpos( $wrap, 'Reserve Your Spot' ) && false === strpos( $wrap, 'cbv-landing-cta-note' ) );
check( 'REAL trip 320, new design: the itinerary PDF button stays (when the trip has a PDF)', ! $has_pdf || false !== strpos( $wrap, 'Download Full Itinerary PDF' ) );
check( 'REAL trip 320, new design: the pricing note is not drawn (its section is removed there)', false === strpos( $page, 'cbv-landing-pricing-note' ) );
$fake[320] = array( 'cbv_lp_sections' => array( 'prices' => 'off' ) );
$off = cbv_lp_render_trip( 320, 'new' );
check( 'REAL trip 320, new design with the price board Off: the Reserve block stays (still a way to book)', false === strpos( $off, 'class="cbv-lp-prices"' ) && false !== strpos( $off, 'Reserve Your Spot' ) );
unset( $fake[320] );
check( "today's public page keeps its Reserve block", false !== strpos( cbv_render_public_trip_landing( 320 ), 'Reserve Your Spot' ) && false !== strpos( cbv_render_public_trip_landing( 181 ), 'Reserve Your Spot' ) );

/* ---- D. members-only forums ---- */
section( 'D. members-only forums' );
check( 'rule: a forum page + not signed in = sign in first', true === cb_gate10_guest_must_sign_in( true, false ) );
check( 'rule: signed-in members and non-forum pages are left alone', false === cb_gate10_guest_must_sign_in( true, true ) && false === cb_gate10_guest_must_sign_in( false, false ) && false === cb_gate10_guest_must_sign_in( false, true ) );
check( 'rule: this command-line run is not a forum request', false === cb_gate10_is_forum_request() );
global $wp_filter;
$hooked = false;
foreach ( ( $wp_filter['template_redirect']->callbacks[5] ?? array() ) as $cb ) {
	if ( $cb['function'] instanceof Closure && basename( ( new ReflectionFunction( $cb['function'] ) )->getFileName() ) === 'checkedbags-gate10.php' ) { $hooked = true; }
}
check( 'rule: hooked to template_redirect (early, priority 5)', $hooked );

/* ---- E. the "Price notes" box and its save ---- */
section( 'E. box and save' );
wp_set_current_user( 1 );
$fake[ TRIP ] = array( 'cb_pricing_note' => 'Mine & <b>yours</b>', 'cb_card_note' => 'Short & sweet' );
ob_start(); cbv_render_price_notes_box( get_post( TRIP ) ); $box = ob_get_clean();
check( 'box: both fields filled (cleaned, escaped), limits and the nonce', false !== strpos( $box, 'name="cb_pricing_note"' ) && false !== strpos( $box, '>Mine &amp; yours</textarea>' ) && false !== strpos( $box, 'maxlength="600"' ) && false !== strpos( $box, 'name="cb_card_note"' ) && false !== strpos( $box, 'value="Short &amp; sweet"' ) && false !== strpos( $box, 'maxlength="80"' ) && false !== strpos( $box, 'name="cbv_price_notes_nonce"' ) );
check( 'box: says where each note shows, and the wording rules (Uplift with "subject to approval")', false !== strpos( $box, 'Choose Your Option' ) && false !== strpos( $box, 'Gate 07' ) && false !== strpos( $box, 'never name the host agency' ) && false !== strpos( $box, '"subject to approval"' ) && false === stripos( $box, 'InteleTravel' ) );
function run_notes_save( $post_id ) {
	global $wp_filter;
	foreach ( $wp_filter['save_post_cb_trip']->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( $fn instanceof Closure && basename( ( new ReflectionFunction( $fn ) )->getFileName() ) === 'checkedbags-price-notes.php' ) { $fn( $post_id ); return true; }
		}
	}
	return false;
}
check( 'the save handler is registered', true === run_notes_save( 999999 ) );
$writes = array();
$_POST = array( 'cbv_price_notes_nonce' => 'bad', 'cb_pricing_note' => 'x', 'cb_card_note' => 'y' );
run_notes_save( TRIP );
check( 'bad nonce: nothing written', array() === $writes );
$writes = array();
$_POST = array( 'cbv_price_notes_nonce' => wp_create_nonce( 'cbv_price_notes_save' ), 'cb_pricing_note' => wp_slash( " Pay <i>now</i>, it's **easy** " ), 'cb_card_note' => wp_slash( str_repeat( 'a', 90 ) ) );
run_notes_save( TRIP );
$by = array(); foreach ( $writes as $w ) { if ( 'update_post_metadata' === $w[0] ) { $by[ $w[2] ] = $w[3]; } }
check( 'save: both stored cleaned (slashes removed, card note capped), only its own keys', array( 'cb_pricing_note' => "Pay now, it's **easy**", 'cb_card_note' => str_repeat( 'a', 80 ) ) === $by, json_encode( $by ) );
wp_set_current_user( 0 );
$writes = array();
$_POST = array( 'cbv_price_notes_nonce' => wp_create_nonce( 'cbv_price_notes_save' ), 'cb_pricing_note' => 'x' );
run_notes_save( TRIP );
check( 'logged out: nothing written', array() === $writes );
$_POST = array();

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
