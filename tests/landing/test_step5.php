<?php
/*
 * Step 5 tests: status board + intro + GATE counter.
 * Run against a temp COPY of the mu-plugins folder with the Step 5 files overlaid:
 *   wp --require=/tmp/cbv_t/define.php eval-file test_step5.php
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
check( 'Step 5 code is loaded', function_exists( 'cbv_lp_render_status' ) && function_exists( 'cbv_lp_render_intro' ) && function_exists( 'cbv_lp_next_gate' ) && function_exists( 'cbv_lp_sanitize_intro' ) );

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
const TRIP = 999150; const IMG = 999163; const VID = 999161; const PAGE = 999164;
fake_post( TRIP, 'cb_trip', 'publish', 'Test Voyage' );
fake_post( IMG, 'attachment', 'inherit', 'valiant lady', 'image/jpeg' );
fake_post( VID, 'attachment', 'inherit', 'hero video', 'video/mp4' );
fake_post( PAGE, 'page', 'publish', 'A page' );
$fake[ IMG ] = array( '_wp_attached_file' => 'virgin/valiant-lady.jpg', '_wp_attachment_metadata' => array( 'width' => 1200, 'height' => 900, 'file' => 'virgin/valiant-lady.jpg', 'sizes' => array() ), '_wp_attachment_image_alt' => 'Valiant Lady at sea' );
$fake[ VID ] = array( '_wp_attached_file' => 'virgin/hero.mp4' );

function trip_meta( $extra = array() ) {
	return array_merge( array(
		'cbv_lp_event_type' => 'cruise', 'cbv_lp_sections' => array(), 'cbv_lp_accommodation_noun' => '',
		'cb_status' => 'active', 'cb_min_group_size' => 25, 'cb_capacity' => 50, 'cbv_lp_travelers_booked' => '',
		'cb_start_date' => '2027-10-25', 'cb_end_date' => '2027-10-30', 'cbv_lp_venue_name' => 'Valiant Lady',
		'cbv_lp_intro_heading' => '', 'cbv_lp_intro_body' => '', 'cbv_lp_intro_chips' => array(),
		'cbv_lp_intro_photo' => 0, 'cbv_lp_intro_photo_focus' => '', 'cbv_lp_intro_caption' => '',
		'cb_trip_code' => 'CBV-TEST', 'cb_deposit_amount' => 0,
	), $extra );
}

/* ---- A. helpers ---- */
section( 'A. helpers' );
check( 'booked: blank stays blank', '' === cbv_lp_clean_booked( '' ) && '' === cbv_lp_clean_booked( '   ' ) && '' === cbv_lp_clean_booked( null ) );
check( 'booked: zero is kept (differs from blank)', '0' === cbv_lp_clean_booked( '0' ) && '0' === cbv_lp_clean_booked( 0 ) );
check( 'booked: leading zeros dropped, spaces trimmed', '7' === cbv_lp_clean_booked( ' 007 ' ) );
check( 'booked: negative, decimal, text, array, 5+ digits rejected', '' === cbv_lp_clean_booked( '-3' ) && '' === cbv_lp_clean_booked( '3.5' ) && '' === cbv_lp_clean_booked( 'ten' ) && '' === cbv_lp_clean_booked( array( 3 ) ) && '' === cbv_lp_clean_booked( '12345' ) );
check( 'booked: 4 digits allowed', '9999' === cbv_lp_clean_booked( '9999' ) );
check( 'nights: Oct 25-30 is 5', 5 === cbv_lp_nights( '2027-10-25', '2027-10-30' ) );
check( 'nights: across the US clock change (Nov 7, 2027) still whole', 7 === cbv_lp_nights( '2027-11-03', '2027-11-10' ) );
check( 'nights: missing, same-day or reversed dates give 0', 0 === cbv_lp_nights( '', '2027-10-30' ) && 0 === cbv_lp_nights( '2027-10-25', '' ) && 0 === cbv_lp_nights( '2027-10-25', '2027-10-25' ) && 0 === cbv_lp_nights( '2027-10-30', '2027-10-25' ) && 0 === cbv_lp_nights( 'junk', 'junk2' ) );
check( 'state: blank number = before', 'before' === cbv_lp_status_state( '', 25, 50 ) );
check( 'state: below minimum = before', 'before' === cbv_lp_status_state( '24', 25, 50 ) );
check( 'state: exactly the minimum = after', 'after' === cbv_lp_status_state( '25', 25, 50 ) );
check( 'state: reaching capacity = full', 'full' === cbv_lp_status_state( '50', 25, 50 ) && 'full' === cbv_lp_status_state( '61', 25, 50 ) );
check( 'state: no capacity set never shows full', 'after' === cbv_lp_status_state( '500', 25, 0 ) );
check( 'state: zero booked = before', 'before' === cbv_lp_status_state( '0', 25, 50 ) );
check( 'chips: one per line, at most 4, blanks dropped', array( 'A', 'B', 'C', 'D' ) === cbv_lp_clean_chips( "A\n\n B \r\nC\nD\nE" ) );
check( 'chips: tags stripped and 40-char cap', array( 'Adults only' ) === cbv_lp_clean_chips( '<b>Adults</b> only' ) && 40 === mb_strlen( cbv_lp_clean_chips( str_repeat( 'é', 60 ) )[0] ) );
check( 'chips: stored arrays accepted, junk ignored', array( 'X', 'Y' ) === cbv_lp_clean_chips( array( 'X', array( 'bad' ), null, 'Y' ) ) && array() === cbv_lp_clean_chips( null ) );

$c = cbv_lp_sanitize_intro( array( 'booked' => '12', 'heading' => ' <i>Hi</i> {vessel} ', 'body' => "Line <script>x</script>\n\nTwo", 'chips' => "Adults only\nLow 80s", 'photo' => IMG, 'focus' => 'top-right', 'caption' => '<b>Our ride</b>' ) );
check( 'sanitize: valid values pass', '12' === $c['booked'] && IMG === $c['photo'] && 'top-right' === $c['focus'] && array( 'Adults only', 'Low 80s' ) === $c['chips'] );
check( 'sanitize: heading and caption lose tags, tokens kept for render time', 'Hi {vessel}' === $c['heading'] && 'Our ride' === $c['caption'] );
check( 'sanitize: body keeps line breaks, loses tags', false === strpos( $c['body'], '<script>' ) && false !== strpos( $c['body'], "\n\nTwo" ) );
$c = cbv_lp_sanitize_intro( array( 'photo' => VID, 'focus' => 'diagonal', 'booked' => '-1' ) );
check( 'sanitize: a video as photo, unknown focus, negative number are refused', 0 === $c['photo'] && '' === $c['focus'] && '' === $c['booked'] );
check( 'sanitize: a page id as photo refused', 0 === cbv_lp_sanitize_intro( array( 'photo' => PAGE ) )['photo'] );
check( 'sanitize: caps (heading 120, caption 100, body 3000)', 120 === mb_strlen( cbv_lp_sanitize_intro( array( 'heading' => str_repeat( 'a', 300 ) ) )['heading'] ) && 100 === mb_strlen( cbv_lp_sanitize_intro( array( 'caption' => str_repeat( 'a', 300 ) ) )['caption'] ) && 3000 === mb_strlen( cbv_lp_sanitize_intro( array( 'body' => str_repeat( 'a', 5000 ) ) )['body'] ) );
check( 'sanitize: arrays in text fields become empty', '' === cbv_lp_sanitize_intro( array( 'heading' => array( 'x' ), 'body' => array( 'y' ) ) )['heading'] );
check( 'sanitize: non-array input gives all-empty values', array( 'booked' => '', 'heading' => '', 'body' => '', 'chips' => array(), 'photo' => 0, 'focus' => '', 'caption' => '' ) === cbv_lp_sanitize_intro( 'junk' ) );

/* ---- B. GATE counter ---- */
section( 'B. GATE counter' );
check( 'before any reset the first gate is already 14', 14 === cbv_lp_next_gate() );
cbv_lp_next_gate( true );
check( 'first label after a reset is GATE 14', 'Gate 14 · The trip' === cbv_lp_gate_label( 'The trip' ) );
check( 'next section is GATE 15', 'Gate 15 · Flight path' === cbv_lp_gate_label( 'Flight path' ) );
cbv_lp_next_gate( true );
check( 'reset starts again at 14', 14 === cbv_lp_next_gate() );
check( 'a blank section name gives just the gate', 'Gate 15' === cbv_lp_gate_label( '' ) );

/* ---- C. status data ---- */
section( 'C. status board data, by stage' );
$fake[ TRIP ] = trip_meta();
$d = cbv_lp_status_data( TRIP );
check( 'blank number: line "25 travelers needed to depart"', '25 travelers needed to depart' === $d['line'], $d['line'] );
check( 'cruise kicker is Departure status', 'Departure status' === $d['kicker'] );
check( 'blank number: no tiles, chip Boarding, no spoken sentence', null === $d['tiles'] && 'before' === $d['state'] && 'Boarding' === $d['chip'] && '' === $d['spoken'] );

// Stage 1: below the minimum
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '3' ) );
$d = cbv_lp_status_data( TRIP );
check( 'STAGE 1 (3 of min 25, capacity 50): tiles 03 OF 25 (the minimum, not the capacity)', array( '0', '3' ) === $d['tiles']['booked'] && array( '2', '5' ) === $d['tiles']['total'], json_encode( $d['tiles'] ) );
check( 'STAGE 1: line "25 travelers needed to depart", chip Boarding', '25 travelers needed to depart' === $d['line'] && 'Boarding' === $d['chip'] && 'before' === $d['state'] );
check( 'STAGE 1: spoken "3 of 25 travelers booked"', '3 of 25 travelers booked' === $d['spoken'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '24' ) );
check( 'STAGE 1: one short of the minimum is still stage 1', 'before' === cbv_lp_status_data( TRIP )['state'] && '25 travelers needed to depart' === cbv_lp_status_data( TRIP )['line'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '0' ) );
check( 'STAGE 1: zero typed shows 00 OF 25', array( '0', '0' ) === cbv_lp_status_data( TRIP )['tiles']['booked'] && array( '2', '5' ) === cbv_lp_status_data( TRIP )['tiles']['total'] );

// Stage 2: minimum reached
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '25' ) );
$d = cbv_lp_status_data( TRIP );
check( 'STAGE 2 (exactly 25 of min 25, capacity 50): tiles switch to 25 OF 50', array( '2', '5' ) === $d['tiles']['booked'] && array( '5', '0' ) === $d['tiles']['total'], json_encode( $d['tiles'] ) );
check( 'STAGE 2: line "Departure confirmed · 25 spots left", chip On time', 'Departure confirmed · 25 spots left' === $d['line'] && 'On time' === $d['chip'] && 'after' === $d['state'], $d['line'] );
check( 'STAGE 2: spoken "25 of 50 travelers booked"', '25 of 50 travelers booked' === $d['spoken'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '49' ) );
$d = cbv_lp_status_data( TRIP );
check( 'STAGE 2: one spot left is singular', 'Departure confirmed · 1 spot left' === $d['line'] && 'after' === $d['state'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '30', 'cb_capacity' => 0 ) );
$d = cbv_lp_status_data( TRIP );
check( 'STAGE 2 with no capacity: tiles stay 30 OF 25 and the line is just "Departure confirmed"', array( '3', '0' ) === $d['tiles']['booked'] && array( '2', '5' ) === $d['tiles']['total'] && 'Departure confirmed' === $d['line'] && 'On time' === $d['chip'], json_encode( $d ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '120', 'cb_capacity' => 0 ) );
$d = cbv_lp_status_data( TRIP );
check( 'STAGE 2 with no capacity and more digits than the minimum: both padded to 3', array( '1', '2', '0' ) === $d['tiles']['booked'] && array( '0', '2', '5' ) === $d['tiles']['total'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '30', 'cb_capacity' => 120 ) );
$d = cbv_lp_status_data( TRIP );
check( 'STAGE 2 with a 3-digit capacity: 030 OF 120, 90 spots left', array( '0', '3', '0' ) === $d['tiles']['booked'] && array( '1', '2', '0' ) === $d['tiles']['total'] && 'Departure confirmed · 90 spots left' === $d['line'] );

// Stage 3: full
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '50' ) );
$d = cbv_lp_status_data( TRIP );
check( 'STAGE 3 (50 of capacity 50): tiles 50 OF 50, chip is the agreed full wording', array( '5', '0' ) === $d['tiles']['booked'] && array( '5', '0' ) === $d['tiles']['total'] && 'Fully booked · Ask about the waitlist' === $d['chip'] && 'full' === $d['state'] );
check( 'STAGE 3: line "Departure confirmed" (no "0 spots left")', 'Departure confirmed' === $d['line'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '52' ) );
$d = cbv_lp_status_data( TRIP );
check( 'STAGE 3: over capacity is still full (52 OF 50), never negative spots', 'full' === $d['state'] && array( '5', '2' ) === $d['tiles']['booked'] && array( '5', '0' ) === $d['tiles']['total'] && false === strpos( $d['line'], '-' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '20', 'cb_capacity' => 20 ) );
check( 'capacity below the minimum: reaching capacity is full even before the minimum', 'full' === cbv_lp_status_data( TRIP )['state'] );

// Non-cruise wording at each stage
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_event_type' => 'resort', 'cbv_lp_travelers_booked' => '3' ) );
$d = cbv_lp_status_data( TRIP );
check( 'resort STAGE 1: "25 guests needed to confirm", chip Open, kicker Group status', '25 guests needed to confirm' === $d['line'] && 'Open' === $d['chip'] && 'Group status' === $d['kicker'] && '3 of 25 guests booked' === $d['spoken'], json_encode( $d ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_event_type' => 'resort', 'cbv_lp_travelers_booked' => '30' ) );
$d = cbv_lp_status_data( TRIP );
check( 'resort STAGE 2: "Group confirmed · 20 spots left", chip Confirmed', 'Group confirmed · 20 spots left' === $d['line'] && 'Confirmed' === $d['chip'], $d['line'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_event_type' => 'resort', 'cbv_lp_travelers_booked' => '50' ) );
check( 'resort STAGE 3: "Group confirmed" + full chip', 'Group confirmed' === cbv_lp_status_data( TRIP )['line'] && 'full' === cbv_lp_status_data( TRIP )['state'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_event_type' => 'corporate', 'cbv_lp_travelers_booked' => '3' ) );
check( 'corporate uses its own people word (attendees)', '25 attendees needed to confirm' === cbv_lp_status_data( TRIP )['line'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_event_type' => 'destination', 'cbv_lp_travelers_booked' => '3' ) );
check( 'destination: "25 travelers needed to confirm"', '25 travelers needed to confirm' === cbv_lp_status_data( TRIP )['line'] );
$fake[ TRIP ] = trip_meta( array( 'cb_min_group_size' => 1 ) );
check( 'a minimum of 1 uses the singular', '1 traveler needed to depart' === cbv_lp_status_data( TRIP )['line'] );
check( 'no "sailor" wording anywhere for a cruise', false === stripos( json_encode( cbv_lp_status_data( TRIP ) ), 'sailor' ) );
check( 'the shared cruise label is Travelers / Traveler', 'Travelers' === cbv_lp_base_labels()['party'] && 'Traveler' === cbv_lp_base_labels()['party_one'] );

// Hidden
foreach ( array( 'completed', 'declined' ) as $st ) {
	$fake[ TRIP ] = trip_meta( array( 'cb_status' => $st ) );
	check( "hidden when the trip is $st", null === cbv_lp_status_data( TRIP ) );
}
$fake[ TRIP ] = trip_meta( array( 'cb_status' => 'requested' ) );
check( 'shown for other statuses (requested)', null !== cbv_lp_status_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cb_min_group_size' => 0 ) );
check( 'hidden when there is no minimum', null === cbv_lp_status_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_sections' => array( 'status' => 'off' ) ) );
check( 'hidden when the section is switched Off on the trip', null === cbv_lp_status_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_event_type' => 'wedding' ) );
check( 'hidden by default for an event type without it (wedding)', null === cbv_lp_status_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_event_type' => 'wedding', 'cbv_lp_sections' => array( 'status' => 'on' ) ) );
check( 'shown when switched On for a wedding', null !== cbv_lp_status_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => 'abc' ) );
check( 'a junk stored number is treated as blank', null === cbv_lp_status_data( TRIP )['tiles'] );

/* ---- D. status markup ---- */
section( 'D. status board markup' );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '3' ) );
$h = cbv_lp_render_status( TRIP );
check( 'section has an accessible name', false !== strpos( $h, 'aria-label="Group status"' ) );
check( 'tiles are hidden from screen readers; one plain sentence instead', 1 === preg_match( '/class="cbv-lp-status-tiles" aria-hidden="true"/', $h ) && false !== strpos( $h, '<p class="cbv-lp-sr">3 of 25 travelers booked</p>' ) );
check( 'four tiles and the OF', 4 === substr_count( $h, 'class="cbv-lp-tile"' ) && false !== strpos( $h, '>of<' ) );
check( 'chip carries its state class', false !== strpos( $h, 'cbv-lp-status-chip--before' ) && false !== strpos( $h, '>Boarding<' ) );
$fake[ TRIP ] = trip_meta();
$h = cbv_lp_render_status( TRIP );
check( 'blank number: no tiles and no spoken sentence in the markup', false === strpos( $h, 'cbv-lp-tile' ) && false === strpos( $h, 'cbv-lp-sr' ) && false !== strpos( $h, '25 travelers needed to depart' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '50' ) );
check( 'full chip is escaped text', false !== strpos( cbv_lp_render_status( TRIP ), '>Fully booked · Ask about the waitlist<' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '25' ) );
$h = cbv_lp_render_status( TRIP );
check( 'STAGE 2 markup: confirmed line, tiles 25 OF 50, chip On time', false !== strpos( $h, '>Departure confirmed · 25 spots left<' ) && 1 === preg_match( '/>2<\/span>\s*<span class="cbv-lp-tile">5<\/span>\s*<span class="cbv-lp-status-of">of<\/span>\s*<span class="cbv-lp-tile">5<\/span>\s*<span class="cbv-lp-tile">0</', $h ) && false !== strpos( $h, 'cbv-lp-status-chip--after">On time<' ) && false !== strpos( $h, '>25 of 50 travelers booked<' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '50' ) );
check( 'STAGE 3 markup: full chip class and 50 OF 50', false !== strpos( cbv_lp_render_status( TRIP ), 'cbv-lp-status-chip--full' ) && false !== strpos( cbv_lp_render_status( TRIP ), '>50 of 50 travelers booked<' ) );
add_filter( 'cbv_lp_labels', $evil = function ( $l ) { $l['party'] = '<img src=x onerror=alert(1)>'; $l['status_before'] = '<b>x</b>'; return $l; } );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '3' ) );
$h = cbv_lp_render_status( TRIP );
check( 'labels are escaped in the line, sentence and chip', false === strpos( $h, '<img' ) && false === strpos( $h, '<b>x' ) && false !== strpos( $h, '&lt;b&gt;x&lt;/b&gt;' ) );
remove_filter( 'cbv_lp_labels', $evil );
$fake[ TRIP ] = trip_meta( array( 'cb_status' => 'completed' ) );
check( 'hidden status renders nothing', '' === cbv_lp_render_status( TRIP ) );

/* ---- E. intro data ---- */
section( 'E. intro data' );
$fake[ TRIP ] = trip_meta();
check( 'no heading and no text: intro hidden (even with chips and photo)', null === cbv_lp_intro_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_chips' => array( 'Adults only' ), 'cbv_lp_intro_photo' => IMG ) );
check( 'chips and a photo alone do not show the intro', null === cbv_lp_intro_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_body' => "   \n  " ) );
check( 'whitespace-only text counts as empty', null === cbv_lp_intro_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_heading' => 'Aboard {vessel}', 'cbv_lp_intro_chips' => array( 'Adults only · 18+', 'Low 80s °F on {vessel}' ) ) );
$d = cbv_lp_intro_data( TRIP );
check( 'heading only shows the intro; tokens filled', null !== $d && 'Aboard Valiant Lady' === $d['heading'] );
check( 'chips: nights, vessel, then typed (with tokens)', array( '5 nights', 'Valiant Lady', 'Adults only · 18+', 'Low 80s °F on Valiant Lady' ) === $d['chips'], json_encode( $d['chips'] ) );
check( 'section name comes from the event type (The trip)', 'The trip' === $d['section'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_body' => 'Just text', 'cb_end_date' => '2027-10-26', 'cbv_lp_venue_name' => '' ) );
$d = cbv_lp_intro_data( TRIP );
check( 'text only shows the intro', null !== $d && false !== strpos( $d['body'], '<p>Just text</p>' ) );
check( 'one night is singular; no vessel means no vessel chip', array( '1 night' ) === $d['chips'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_body' => 'x', 'cb_start_date' => '', 'cbv_lp_venue_name' => '' ) );
check( 'no dates: no nights chip', array() === cbv_lp_intro_data( TRIP )['chips'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_body' => 'x', 'cbv_lp_intro_chips' => array( 'a', 'b', 'c', 'd', 'e', 'f' ) ) );
check( 'stored chips over 4 are capped (2 automatic + 4 typed)', 6 === count( cbv_lp_intro_data( TRIP )['chips'] ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_body' => 'x', 'cbv_lp_intro_photo' => VID, 'cbv_lp_intro_caption' => 'cap' ) );
$d = cbv_lp_intro_data( TRIP );
check( 'a stored id that is not an image is ignored, and its caption with it', 0 === $d['photo_id'] && '' === $d['caption'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_body' => 'x', 'cbv_lp_intro_photo' => IMG, 'cbv_lp_intro_caption' => 'Our ride: {vessel}', 'cbv_lp_intro_photo_focus' => 'bottom' ) );
$d = cbv_lp_intro_data( TRIP );
check( 'photo, caption tokens and focus position', IMG === $d['photo_id'] && 'Our ride: Valiant Lady' === $d['caption'] && 'center bottom' === $d['focus'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_body' => 'x', 'cbv_lp_sections' => array( 'intro' => 'off' ) ) );
check( 'hidden when the section is switched Off', null === cbv_lp_intro_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_body' => 'x', 'cbv_lp_event_type' => 'resort' ) );
check( 'resort still has an intro section', null !== cbv_lp_intro_data( TRIP ) );

/* ---- F. intro markup ---- */
section( 'F. intro markup' );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_heading' => 'The people <script>alert(1)</script> who make it', 'cbv_lp_intro_body' => "First **bold** para.\n\n[Book](https://bagsandvibes.com/join/) now <img src=x onerror=1>", 'cbv_lp_intro_chips' => array( '<b>18+</b>' ), 'cbv_lp_intro_photo' => IMG, 'cbv_lp_intro_photo_focus' => 'top-left', 'cbv_lp_intro_caption' => 'Our <i>ride</i>' ) );
cbv_lp_next_gate( true );
$h = cbv_lp_render_intro( TRIP );
check( 'GATE 14 label with the section name', false !== strpos( $h, '<p class="cbv-lp-gate">Gate 14 · The trip</p>' ) );
check( 'heading is an escaped <h2> linked to the section', false !== strpos( $h, 'aria-labelledby="cbv-lp-intro-title"' ) && false !== strpos( $h, '<h2 class="cbv-lp-h2" id="cbv-lp-intro-title">The people &lt;script&gt;' ) && false === strpos( $h, '<script>' ) );
check( 'text: paragraphs, bold and a link, raw HTML neutralised', false !== strpos( $h, '<strong>bold</strong>' ) && false !== strpos( $h, '<a href="https://bagsandvibes.com/join/" rel="noopener">Book</a>' ) && false === strpos( $h, '<img src=x' ) );
check( 'chips are a list; tags in a stored chip are stripped again at render time', false !== strpos( $h, '<ul class="cbv-lp-chips">' ) && false !== strpos( $h, '<li>18+</li>' ) && false === strpos( $h, '<b>18' ) );
check( 'photo in a figure with alt text, lazy, focus applied, caption escaped', false !== strpos( $h, '<figure class="cbv-lp-polaroid">' ) && false !== strpos( $h, 'alt="Valiant Lady at sea"' ) && false !== strpos( $h, 'loading="lazy"' ) && false !== strpos( $h, 'object-position:left top;' ) && false !== strpos( $h, '<figcaption>Our &lt;i&gt;ride&lt;/i&gt;</figcaption>' ) );
check( 'with a photo the layout is two columns (no solo class)', false === strpos( $h, 'cbv-lp-intro-inner--solo' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_body' => 'Only text' ) );
cbv_lp_next_gate( true );
$h = cbv_lp_render_intro( TRIP );
check( 'no photo: solo layout, no figure', false !== strpos( $h, 'cbv-lp-intro-inner--solo' ) && false === strpos( $h, '<figure' ) );
check( 'no heading: the section name is the <h2> (never an empty heading)', false !== strpos( $h, 'id="cbv-lp-intro-title">The trip</h2>' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_body' => 'x', 'cbv_lp_intro_photo' => IMG, 'cbv_lp_intro_caption' => '' ) );
check( 'no caption: no empty figcaption', false === strpos( cbv_lp_render_intro( TRIP ), '<figcaption' ) );
$fake[ TRIP ] = trip_meta();
cbv_lp_next_gate( true );
check( 'hidden intro renders nothing and does not use up GATE 14', '' === cbv_lp_render_intro( TRIP ) && 14 === cbv_lp_next_gate() );

/* ---- G. the real trip 181 + page assembly ---- */
section( 'G. trip 181 and page assembly' );
$d = cbv_lp_status_data( 181 );
check( 'REAL trip 181: "25 travelers needed to depart", no tiles yet, chip Boarding', $d && '25 travelers needed to depart' === $d['line'] && null === $d['tiles'] && 'Boarding' === $d['chip'], json_encode( $d ) );
check( 'REAL trip 181: the intro written 2026-10-05 is shown (heading, 3 paragraphs, chips after the automatic ones)', ( $i181 = cbv_lp_intro_data( 181 ) ) && "Five nights. One crew. Zero kids' table." === $i181['heading'] && 3 === substr_count( $i181['body'], '<p>' ) && array( '5 nights', 'Valiant Lady', 'Adults only · 18+', 'Miami round trip', 'Low 80s °F' ) === $i181['chips'], json_encode( $i181 ) );
$page = cbv_lp_render_trip( 181, 'new_preview' );
check( 'order: banner, hero, status board, legacy content', strpos( $page, 'cbv-lp-preview-banner' ) < strpos( $page, 'class="cbv-lp-hero"' ) && strpos( $page, 'class="cbv-lp-hero"' ) < strpos( $page, 'class="cbv-lp-status"' ) && strpos( $page, 'class="cbv-lp-status"' ) < strpos( $page, 'cbv-lp-legacy-wrap' ) );
check( 'still exactly one <h1>', 1 === substr_count( $page, '<h1' ) );
$fake[ 181 ] = array( 'cbv_lp_intro_heading' => 'Hello', 'cbv_lp_travelers_booked' => '7' );
$p1 = cbv_lp_render_trip( 181, 'new' );
$p2 = cbv_lp_render_trip( 181, 'new' );
check( 'intro sits between the status board and the legacy content', strpos( $p1, 'class="cbv-lp-status"' ) < strpos( $p1, 'class="cbv-lp-intro"' ) && strpos( $p1, 'class="cbv-lp-intro"' ) < strpos( $p1, 'cbv-lp-legacy-wrap' ) );
check( 'GATE numbering restarts at 14 on every render (each render has exactly one GATE 14, on the intro)', 1 === substr_count( $p1, 'Gate 14 · ' ) && 1 === substr_count( $p2, 'Gate 14 · ' ) && false !== strpos( $p2, 'Gate 14 · The trip' ) );
check( 'typed number shows as tiles: 07 OF 25', false !== strpos( $p1, '7 of 25 travelers booked' ) && 4 === substr_count( $p1, 'class="cbv-lp-tile"' ) );
unset( $fake[ 181 ] );

/* ---- H. admin box + save ---- */
section( 'H. admin box and save' );
function run_intro_save( $post_id ) {
	global $wp_filter;
	foreach ( $wp_filter['save_post_cb_trip']->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( $fn instanceof Closure && basename( ( new ReflectionFunction( $fn ) )->getFileName() ) === 'checkedbags-lp-intro.php' ) {
				$fn( $post_id );
				return true;
			}
		}
	}
	return false;
}
check( 'the save handler is registered', true === run_intro_save( 999999 ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_travelers_booked' => '12', 'cbv_lp_intro_heading' => 'Say "hi" <b>', 'cbv_lp_intro_body' => "a\n</textarea><script>", 'cbv_lp_intro_chips' => array( 'One', 'Two' ), 'cbv_lp_intro_photo' => IMG, 'cbv_lp_intro_photo_focus' => 'right', 'cbv_lp_intro_caption' => 'Cap' ) );
ob_start(); cbv_lp_render_intro_box( get_post( TRIP ) ); $box = ob_get_clean();
check( 'box has the nonce and all seven fields', false !== strpos( $box, 'name="cbv_lp_intro_nonce"' ) && 7 === count( array_filter( array( 'cbv_lp_travelers_booked', 'cbv_lp_intro_heading', 'cbv_lp_intro_body', 'cbv_lp_intro_chips', 'cbv_lp_intro_photo', 'cbv_lp_intro_photo_focus', 'cbv_lp_intro_caption' ), function ( $f ) use ( $box ) { return false !== strpos( $box, 'name="' . $f . '"' ); } ) ) );
check( 'booked field labelled as paid deposits, number input', false !== strpos( $box, 'Travelers booked (paid deposits)' ) && 1 === preg_match( '/type="number" name="cbv_lp_travelers_booked"[^>]*value="12"/', $box ) );
check( 'values are escaped (attribute and textarea)', false !== strpos( $box, 'value="Say &quot;hi&quot; &lt;b&gt;"' ) && false === strpos( $box, '</textarea><script>' ) );
check( 'chips shown one per line, photo id and focus selected, file name shown', false !== strpos( $box, ">One\nTwo</textarea>" ) && false !== strpos( $box, 'value="' . IMG . '"' ) && 1 === preg_match( '/<option value="right"\s+selected=\'selected\'/', $box ) && false !== strpos( $box, 'valiant-lady.jpg' ) );
check( 'help text carries the media rule and the minimum', false !== strpos( $box, 'First Mates' ) && false !== strpos( $box, 'never hide Virgin' ) && false !== strpos( $box, 'NN OF 25' ) );

wp_set_current_user( 1 );
$writes = array();
$_POST = array( 'cbv_lp_intro_nonce' => 'bad', 'cbv_lp_travelers_booked' => '9' );
run_intro_save( 181 );
check( 'bad nonce: nothing written', array() === $writes );
$writes = array();
$_POST = array( 'cbv_lp_intro_nonce' => wp_create_nonce( 'cbv_lp_intro_save' ), 'cbv_lp_travelers_booked' => ' 09 ', 'cbv_lp_intro_heading' => '<b>Hi</b>', 'cbv_lp_intro_body' => "P1\n\nP2", 'cbv_lp_intro_chips' => "A\nB", 'cbv_lp_intro_photo' => (string) PAGE, 'cbv_lp_intro_photo_focus' => 'top', 'cbv_lp_intro_caption' => 'Cap' );
run_intro_save( 181 );
$by = array(); foreach ( $writes as $w ) { if ( 'update_post_metadata' === $w[0] ) { $by[ $w[2] ] = $w[3]; } }
check( 'valid save writes the seven values, sanitized (a page id refused as photo)', '9' === $by['cbv_lp_travelers_booked'] && 'Hi' === $by['cbv_lp_intro_heading'] && "P1\n\nP2" === $by['cbv_lp_intro_body'] && array( 'A', 'B' ) === $by['cbv_lp_intro_chips'] && 0 === $by['cbv_lp_intro_photo'] && 'top' === $by['cbv_lp_intro_photo_focus'] && 'Cap' === $by['cbv_lp_intro_caption'], json_encode( $by ) );
check( 'only its own keys are written', 7 === count( $by ) );
$writes = array();
$_POST['cbv_lp_travelers_booked'] = '';
run_intro_save( 181 );
$by = array(); foreach ( $writes as $w ) { if ( 'update_post_metadata' === $w[0] ) { $by[ $w[2] ] = $w[3]; } }
check( 'clearing the number saves blank (no tiles), not 0', '' === $by['cbv_lp_travelers_booked'] );
$low = 0;
foreach ( get_users( array( 'number' => 40, 'fields' => 'ID', 'role__not_in' => array( 'administrator' ) ) ) as $uid ) {
	if ( ! user_can( (int) $uid, 'edit_post', 181 ) ) { $low = (int) $uid; break; }
}
check( 'found a real user without edit rights for the capability test', $low > 0 );
wp_set_current_user( $low );
$_POST = array( 'cbv_lp_intro_nonce' => wp_create_nonce( 'cbv_lp_intro_save' ), 'cbv_lp_travelers_booked' => '99' );
check( 'the nonce really is valid for that user', false !== wp_verify_nonce( $_POST['cbv_lp_intro_nonce'], 'cbv_lp_intro_save' ) );
$writes = array();
run_intro_save( 181 );
check( 'a user who cannot edit the trip writes nothing even with a valid nonce', array() === $writes );
wp_set_current_user( 0 );
$_POST = array();

/* ---- J. link safety in the light-markup formatter (used by the intro text) ---- */
section( 'J. link safety' );
$no_link = function ( $md ) { $h = cbv_lp_inline( $md ); return false === strpos( $h, '<a' ) && false === stripos( $h, 'href' ); };
check( 'javascript: link dropped, words kept', 'x' === cbv_lp_inline( '[x](javascript:void0)' ), cbv_lp_inline( '[x](javascript:void0)' ) );
check( 'javascript: with brackets in it is dropped too (no link, no "javascript" left in the output)', $no_link( '[x](javascript:alert(1))' ) && false === stripos( cbv_lp_inline( '[x](javascript:alert(1))' ), 'javascript' ) );
check( 'JavaScript: in any case is dropped', $no_link( '[x](JaVaScRiPt:alert(1))' ) && $no_link( '[x](JAVASCRIPT:alert(1))' ) );
check( 'entity-encoded javascript: is dropped', $no_link( '[x](jav&#x61;script:alert(1))' ) && $no_link( '[x](&#106;avascript:alert(1))' ) );
check( 'javascript: split by a tab or encoded tab is dropped', $no_link( "[x](java\tscript:alert(1))" ) && $no_link( '[x](java&#9;script:alert(1))' ) );
check( 'data: and vbscript: links dropped', $no_link( '[x](data:text/html;base64,PHNjcmlwdD4=)' ) && $no_link( '[x](vbscript:msgbox(1))' ) );
check( 'file: and ftp: links dropped', $no_link( '[x](file:///etc/passwd)' ) && $no_link( '[x](ftp://example.com/a)' ) );
check( 'https link kept and attribute-safe', '<a href="https://example.com/a?b=1&#038;c=2" rel="noopener">go</a>' === cbv_lp_inline( '[go](https://example.com/a?b=1&c=2)' ), cbv_lp_inline( '[go](https://example.com/a?b=1&c=2)' ) );
check( 'http link kept', false !== strpos( cbv_lp_inline( '[go](http://example.com/)' ), 'href="http://example.com/"' ) );
check( 'mailto link kept', false !== strpos( cbv_lp_inline( '[mail](mailto:hello@bagsandvibes.com)' ), 'href="mailto:hello@bagsandvibes.com"' ) );
check( 'tel link kept (phone links are allowed by design)', false !== strpos( cbv_lp_inline( '[call](tel:+18005551234)' ), 'href="tel:+18005551234"' ) );
check( 'a quote cannot break out of the href', false === strpos( cbv_lp_inline( '[x](https://a.com/"onmouseover="alert(1))' ), '"onmouseover' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_intro_body' => "See [this](javascript:stealCookies) and [that](https://bagsandvibes.com/)." ) );
cbv_lp_next_gate( true );
$h = cbv_lp_render_intro( TRIP );
check( 'intro text: the javascript: link is dropped, the https link stays', false === stripos( $h, 'javascript:' ) && false !== strpos( $h, 'See this and' ) && false !== strpos( $h, '<a href="https://bagsandvibes.com/" rel="noopener">that</a>' ), $h );

/* ---- I. what Step 5 must NOT do ---- */
section( 'I. no side effects' );
check( 'the current (legacy) renderer has no status board or intro', false === strpos( cbv_render_public_trip_landing( 181 ), 'cbv-lp-status' ) && false === strpos( cbv_render_public_trip_landing( 181 ), 'cbv-lp-intro' ) );
check( 'master switch still off', ! get_option( 'cbv_lp_redesign_live' ) );
check( 'boarding pass title now reads "Boarding Pass · Traveler" for a cruise (shared label)', false !== strpos( cbv_lp_render_hero( 181 ), 'Boarding Pass · Traveler' ) );
check( 'Step 4 hero still renders', false !== strpos( cbv_lp_render_hero( 181 ), 'class="cbv-lp-hero"' ) );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
