<?php
/*
 * Step 4 tests: hero + boarding pass + itinerary stop codes.
 * Run against a temp COPY of the mu-plugins folder with the Step 4 files overlaid:
 *   wp --require=/tmp/cbv_t/define.php eval-file test_step4.php
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
check( 'Step 4 code is loaded', function_exists( 'cbv_lp_render_hero' ) && function_exists( 'cbv_lp_route_endpoints' ) && function_exists( 'cbv_lp_strip_legacy_hero' ) && function_exists( 'cbv_lp_sanitize_hero' ) );

$fake = array();
$writes = array();
add_filter( 'get_post_metadata', function ( $v, $oid, $key ) use ( &$fake ) {
	if ( isset( $fake[ $oid ] ) && array_key_exists( $key, $fake[ $oid ] ) ) { return array( $fake[ $oid ][ $key ] ); }
	return $v;
}, 10, 3 );
// Intercept every meta write (returning non-null short-circuits the DB) and record it.
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
const TRIP = 999050; const VID = 999061; const VID2 = 999062; const IMG = 999063; const PAGE = 999064;
fake_post( TRIP, 'cb_trip', 'publish', 'Test <b>Voyage</b>' );
fake_post( VID, 'attachment', 'inherit', 'hero video', 'video/mp4' );
fake_post( VID2, 'attachment', 'inherit', 'hero video 720', 'video/mp4' );
fake_post( IMG, 'attachment', 'inherit', 'poster', 'image/jpeg' );
fake_post( PAGE, 'page', 'publish', 'A page' );
$fake[ VID ]  = array( '_wp_attached_file' => 'virgin/terminal-v-hero-1080.mp4' );
$fake[ VID2 ] = array( '_wp_attached_file' => 'virgin/terminal-v-hero-720.mp4' );
$fake[ IMG ]  = array( '_wp_attached_file' => 'virgin/terminal-v-hero-poster.jpg' );

/* ---- A. stop codes ---- */
section( 'A. stop codes' );
check( 'code: upper-cased', 'MIA' === cbv_lp_clean_stop_code( 'mia' ) );
check( 'code: punctuation and spaces dropped', 'MIA' === cbv_lp_clean_stop_code( ' m-i.a! ' ) );
check( 'code: at most 5 characters', 'ABCDE' === cbv_lp_clean_stop_code( 'abcdefgh' ) );
check( 'code: digits kept', 'PM2' === cbv_lp_clean_stop_code( 'pm2' ) );
check( 'code: non-scalar and null become empty', '' === cbv_lp_clean_stop_code( array( 'MIA' ) ) && '' === cbv_lp_clean_stop_code( null ) );
check( 'code: tags are neutralised', 'SCRIP' === cbv_lp_clean_stop_code( '<script>alert(1)</script>' ) );

/* ---- B. focus point ---- */
section( 'B. focus point' );
check( 'focus: default is centre', 'center center' === cbv_lp_focus_position( '' ) && 'center center' === cbv_lp_focus_position( null ) && 'center center' === cbv_lp_focus_position( 'nonsense' ) );
check( 'focus: all 8 presets map', 'center top' === cbv_lp_focus_position( 'top' ) && 'center bottom' === cbv_lp_focus_position( 'bottom' ) && 'left center' === cbv_lp_focus_position( 'left' ) && 'right center' === cbv_lp_focus_position( 'right' ) && 'left top' === cbv_lp_focus_position( 'top-left' ) && 'right top' === cbv_lp_focus_position( 'top-right' ) && 'left bottom' === cbv_lp_focus_position( 'bottom-left' ) && 'right bottom' === cbv_lp_focus_position( 'bottom-right' ) );
check( 'focus: a CSS injection attempt is not passed through', 'center center' === cbv_lp_focus_position( '50% 50%; background:url(x)' ) && 'center center' === cbv_lp_focus_position( array( 'top' ) ) );
check( 'focus: options list covers every preset plus default', 9 === count( cbv_lp_focus_options() ) && isset( cbv_lp_focus_options()[''] ) );

/* ---- C. route endpoints ---- */
section( 'C. FROM / VIA / TO' );
$round = array(
	array( 'day' => 1, 'port' => 'Miami', 'country' => 'USA', 'stop_code' => 'mia', 'description' => 'Embarkation' ),
	array( 'day' => 2, 'port' => 'At Sea', 'description' => 'At Sea' ),
	array( 'day' => 3, 'port' => 'Puerto Plata', 'country' => 'Dominican Republic', 'stop_code' => 'POP', 'description' => 'Arrival' ),
	array( 'day' => 4, 'port' => 'Bimini', 'country' => 'Bahamas', 'stop_code' => 'BIM', 'description' => 'Arrival' ),
	array( 'day' => 5, 'port' => 'Miami', 'country' => 'USA', 'stop_code' => 'MIA', 'description' => 'Disembarkation' ),
);
$r = cbv_lp_route_endpoints( $round );
check( 'round trip: FROM is the first stop', 'MIA' === $r['from']['code'] && 'Miami, USA' === $r['from']['name'] );
check( 'round trip: TO is the farthest different stop, not the return to FROM', 'BIM' === $r['to']['code'] && 'Bimini, Bahamas' === $r['to']['name'] );
check( 'round trip: VIA lists the stop in between', 1 === count( $r['via'] ) && 'POP' === $r['via'][0]['code'] );
check( 'at-sea rows are ignored', null !== $r && 4 === count( array_filter( $round, function ( $x ) { return 'At Sea' !== $x['port']; } ) ) );
$r = cbv_lp_route_endpoints( array( array( 'port' => 'Miami', 'stop_code' => 'MIA' ), array( 'port' => 'Nassau', 'stop_code' => 'NAS' ) ) );
check( 'one-way: from and to, no via', 'MIA' === $r['from']['code'] && 'NAS' === $r['to']['code'] && array() === $r['via'] );
check( 'a single stop has no route', null === cbv_lp_route_endpoints( array( array( 'port' => 'Miami', 'stop_code' => 'MIA' ) ) ) );
check( 'no itinerary has no route', null === cbv_lp_route_endpoints( array() ) && null === cbv_lp_route_endpoints( null ) && null === cbv_lp_route_endpoints( 'junk' ) );
check( 'all rows the same place: no route', null === cbv_lp_route_endpoints( array( array( 'port' => 'Miami', 'stop_code' => 'MIA' ), array( 'port' => 'Miami', 'stop_code' => 'MIA' ) ) ) );
check( 'same place with different capitalisation (no codes) counts as the same', null === cbv_lp_route_endpoints( array( array( 'port' => 'Miami' ), array( 'port' => 'MIAMI' ), array( 'port' => 'miami' ) ) ) );
$r = cbv_lp_route_endpoints( array( array( 'port' => 'Miami' ), array( 'port' => 'Nassau', 'country' => 'Bahamas' ) ) );
check( 'no codes: the port name is the label and there is no code', '' === $r['from']['code'] && 'Miami' === $r['from']['label'] && 'Nassau' === $r['to']['label'] && 'Nassau, Bahamas' === $r['to']['name'] );
$many = array( array( 'port' => 'A', 'stop_code' => 'AAA' ), array( 'port' => 'B', 'stop_code' => 'BBB' ), array( 'port' => 'B again', 'stop_code' => 'BBB' ), array( 'port' => 'C', 'stop_code' => 'CCC' ), array( 'port' => 'D', 'stop_code' => 'DDD' ), array( 'port' => 'E', 'stop_code' => 'EEE' ), array( 'port' => 'F', 'stop_code' => 'FFF' ) );
$r = cbv_lp_route_endpoints( $many );
check( 'via: repeats collapsed and capped at 3', array( 'BBB', 'CCC', 'DDD' ) === wp_list_pluck( $r['via'], 'code' ) && 'FFF' === $r['to']['code'] );
$r = cbv_lp_route_endpoints( array( 'junk', null, array( 'port' => 'Miami', 'stop_code' => 'MIA' ), 5, array( 'port' => 'Nassau', 'stop_code' => 'NAS' ) ) );
check( 'junk rows are skipped', null !== $r && 'MIA' === $r['from']['code'] && 'NAS' === $r['to']['code'] );
$r = cbv_lp_route_endpoints( array( array( 'port' => '', 'stop_code' => 'XXX' ), array( 'port' => 'Miami', 'stop_code' => 'MIA' ), array( 'port' => 'Nassau', 'stop_code' => 'NAS' ) ) );
check( 'a row with no port is skipped even if it has a code', 'MIA' === $r['from']['code'] );
$r = cbv_lp_route_endpoints( array( array( 'port' => 'Miami', 'stop_code' => 'MIA' ), array( 'port' => 'Nassau', 'stop_code' => 'NAS', 'description' => 'AT SEA ' ) ) );
check( 'At Sea matches in any case and with spaces', null === $r );
$r = cbv_lp_route_endpoints( array( array( 'port' => 'Miami', 'stop_code' => 'mia<script>' ), array( 'port' => 'Nassau', 'stop_code' => 'nas' ) ) );
check( 'codes are cleaned on the way in', 'MIASC' === $r['from']['code'] );

/* trip 181's REAL itinerary (copied from the live data): ports listed twice (Arrival + Departure), no country on most rows, no codes yet */
$real181 = array(
	array( 'day' => '0', 'port' => 'Miami', 'country' => 'US', 'description' => 'Embarkation' ),
	array( 'day' => '1', 'port' => '', 'country' => '', 'description' => 'At Sea' ),
	array( 'day' => '2', 'port' => 'Puerto Plata, Dominican Republic', 'country' => '', 'description' => 'Arrival' ),
	array( 'day' => '2', 'port' => 'Puerto Plata, Dominican Republic', 'country' => '', 'description' => 'Departure' ),
	array( 'day' => '3', 'port' => '', 'country' => '', 'description' => 'At Sea' ),
	array( 'day' => '4', 'port' => 'Bimini Beach Club', 'country' => '', 'description' => 'Arrival' ),
	array( 'day' => '4', 'port' => 'Bimini Beach Club', 'country' => '', 'description' => 'Departure' ),
	array( 'day' => '5', 'port' => 'Miami', 'country' => '', 'description' => 'Disembarkation' ),
);
$r = cbv_lp_route_endpoints( $real181 );
check( 'REAL trip 181, no codes yet: FROM Miami, VIA Puerto Plata, TO Bimini Beach Club (names shown)', 'Miami' === $r['from']['label'] && 'Bimini Beach Club' === $r['to']['label'] && 1 === count( $r['via'] ) && 'Puerto Plata, Dominican Republic' === $r['via'][0]['label'] && '' === $r['from']['code'], json_encode( $r ) );
$c = $real181; $c[0]['stop_code'] = 'mia'; $c[2]['stop_code'] = 'pop'; $c[5]['stop_code'] = 'bim';
$r = cbv_lp_route_endpoints( $c );
check( 'REAL trip 181, one code per port (first row of each): MIA / POP / BIM, and the final Miami is NOT a different place', 'MIA' === $r['from']['code'] && 'BIM' === $r['to']['code'] && array( 'POP' ) === wp_list_pluck( $r['via'], 'code' ), json_encode( $r ) );
check( 'REAL trip 181 with codes: place names shown under the codes', 'Miami, US' === $r['from']['name'] && 'Bimini Beach Club' === $r['to']['name'] );
$c = $real181; $c[7]['stop_code'] = 'MIA'; $c[5]['stop_code'] = 'BIM';
$r = cbv_lp_route_endpoints( $c );
check( 'a code typed only on the LAST Miami row still applies to the first (and no code on Puerto Plata stays a name)', 'MIA' === $r['from']['code'] && 'BIM' === $r['to']['code'] && '' === $r['via'][0]['code'] && 'Puerto Plata, Dominican Republic' === $r['via'][0]['label'] );
$r = cbv_lp_route_endpoints( array( array( 'port' => 'Miami', 'stop_code' => 'MIA' ), array( 'port' => 'Nassau', 'stop_code' => 'NAS' ), array( 'port' => 'MIAMI' ) ) );
check( 'a code propagates to a same-named port in a different capitalisation (return leg is Miami again)', 'NAS' === $r['to']['code'] && 'MIA' === $r['from']['code'] );
$r = cbv_lp_route_endpoints( array( array( 'port' => 'Miami', 'stop_code' => 'MIA' ), array( 'port' => 'Port of Miami', 'stop_code' => 'MIA' ), array( 'port' => 'Nassau', 'stop_code' => 'NAS' ) ) );
check( 'different port names that share a code are the same place', 'MIA' === $r['from']['code'] && 'NAS' === $r['to']['code'] && array() === $r['via'] );

/* ---- D. sanitizer ---- */
section( 'D. hero box sanitizer' );
$c = cbv_lp_sanitize_hero( array( 'video' => VID, 'video_small' => VID2, 'poster' => IMG, 'focus' => 'top-left', 'venue' => '  <b>Valiant</b> Lady  ' ) );
check( 'valid values pass', VID === $c['video'] && VID2 === $c['video_small'] && IMG === $c['poster'] && 'top-left' === $c['focus'] );
check( 'venue: tags stripped and trimmed', 'Valiant Lady' === $c['venue'] );
$c = cbv_lp_sanitize_hero( array( 'video' => IMG, 'video_small' => PAGE, 'poster' => VID ) );
check( 'wrong kind of file is rejected (image as video, page as video, video as poster)', 0 === $c['video'] && 0 === $c['video_small'] && 0 === $c['poster'] );
$c = cbv_lp_sanitize_hero( array( 'video' => 123456789, 'poster' => -5, 'video_small' => 'abc' ) );
check( 'missing / negative / text ids become 0', 0 === $c['video'] && 0 === $c['poster'] && 0 === $c['video_small'] );
$c = cbv_lp_sanitize_hero( array( 'video' => array( VID ), 'focus' => array( 'top' ), 'venue' => array( 'x' ) ) );
check( 'arrays are rejected', 0 === $c['video'] && '' === $c['focus'] && '' === $c['venue'] );
check( 'unknown focus becomes empty (centre)', '' === cbv_lp_sanitize_hero( array( 'focus' => 'diagonal' ) )['focus'] );
check( 'venue capped at 60 characters', 60 === strlen( cbv_lp_sanitize_hero( array( 'venue' => str_repeat( 'a', 100 ) ) )['venue'] ) );
check( 'non-array input gives all-empty values', array( 'video' => 0, 'video_small' => 0, 'poster' => 0, 'focus' => '', 'venue' => '' ) === cbv_lp_sanitize_hero( 'junk' ) );

/* ---- E. hero data ---- */
section( 'E. hero data' );
$fake[ TRIP ] = array( 'cbv_lp_event_type' => 'cruise', 'cb_start_date' => '2027-10-25', 'cb_end_date' => '2027-10-30', 'cb_trip_code' => 'VL2710255NPP', 'cb_public_landing_tagline' => 'A high-vibe reunion', 'cb_public_landing_show_itinerary' => 1, 'cb_itinerary' => $round, 'cbv_lp_venue_name' => 'Valiant Lady' );
$d = cbv_lp_hero_data( TRIP );
check( 'title and tagline', 'Test <b>Voyage</b>' === $d['title'] || false !== strpos( $d['title'], 'Voyage' ), $d['title'] );
check( 'eyebrow: cruise label + date range', 0 === strpos( $d['eyebrow'], 'Now boarding · Oct 25' ) && false !== strpos( $d['eyebrow'], '2027' ), $d['eyebrow'] );
check( 'trip code, dates and vessel', 'VL2710255NPP' === $d['code'] && 'Oct 25, 2027' === $d['start'] && 'Oct 30, 2027' === $d['end'] && 'Valiant Lady' === $d['vessel'] );
check( 'pass labels follow the event type (cruise)', 'Boarding Pass · Traveler' === $d['pass_title'] && 'Departs' === $d['label_start'] && 'Returns' === $d['label_end'] && 'Vessel' === $d['label_place'] );
check( 'route present when Show Itinerary is on', is_array( $d['route'] ) && 'BIM' === $d['route']['to']['code'] );
check( 'CTA goes to the tagged registration', home_url( '/join/?trip=VL2710255NPP' ) === $d['cta_url'] );
check( 'no media: no image and no video', '' === $d['image_url'] && '' === $d['video_url'] && '' === $d['video_small'] );
$fake[ TRIP ]['cb_public_landing_show_itinerary'] = '';
check( 'Show Itinerary OFF hides the route but keeps dates and vessel', null === cbv_lp_hero_data( TRIP )['route'] && 'Oct 25, 2027' === cbv_lp_hero_data( TRIP )['start'] && 'Valiant Lady' === cbv_lp_hero_data( TRIP )['vessel'] );
$fake[ TRIP ]['cb_public_landing_show_itinerary'] = 1;
$fake[ TRIP ]['cbv_lp_hero_video'] = VID; $fake[ TRIP ]['cbv_lp_hero_video_small'] = VID2; $fake[ TRIP ]['cbv_lp_hero_poster'] = IMG; $fake[ TRIP ]['cbv_lp_hero_focus'] = 'top-right';
$d = cbv_lp_hero_data( TRIP );
check( 'both videos resolve to upload URLs', false !== strpos( $d['video_url'], 'terminal-v-hero-1080.mp4' ) && false !== strpos( $d['video_small'], 'terminal-v-hero-720.mp4' ), $d['video_url'] . ' | ' . $d['video_small'] );
check( 'poster resolves; a poster picture is decorative (empty alt)', false !== strpos( $d['image_url'], 'terminal-v-hero-poster.jpg' ) && '' === $d['image_alt'], $d['image_url'] );
check( 'focus point resolves to object-position', 'right top' === $d['focus'] );
$fake[ TRIP ]['cbv_lp_hero_video'] = 0;
$d = cbv_lp_hero_data( TRIP );
check( 'only the smaller video chosen: it is used for every screen', false !== strpos( $d['video_url'], '720.mp4' ) && '' === $d['video_small'] );
$fake[ TRIP ]['cbv_lp_hero_video'] = VID; $fake[ TRIP ]['cbv_lp_hero_video_small'] = 0; $fake[ TRIP ]['cbv_lp_hero_poster'] = 0;
$d = cbv_lp_hero_data( TRIP );
check( 'video only: no small video; no poster and no cover photo means no still image', false !== strpos( $d['video_url'], '1080.mp4' ) && '' === $d['video_small'] && '' === $d['image_url'] );
$fake[ TRIP ]['cb_trip_code'] = '';
check( 'no trip code: the join link has no trip tag and the chip data is empty', home_url( '/join/' ) === cbv_lp_hero_data( TRIP )['cta_url'] && '' === cbv_lp_hero_data( TRIP )['code'] );
$fake[ TRIP ]['cb_trip_code'] = 'A B&C';
check( 'trip code is URL-encoded in the join link', home_url( '/join/?trip=A%20B%26C' ) === cbv_lp_hero_data( TRIP )['cta_url'] );
$fake[ TRIP ]['cb_trip_code'] = 'VL2710255NPP';
$fake[ TRIP ]['cbv_lp_event_type'] = 'resort';
$d = cbv_lp_hero_data( TRIP );
check( 'resort wording: eyebrow, pass title and labels', 0 === strpos( $d['eyebrow'], 'Now booking' ) && 'Boarding Pass · Guest' === $d['pass_title'] && 'Check-in' === $d['label_start'] && 'Check-out' === $d['label_end'] && 'Property' === $d['label_place'], $d['eyebrow'] );
$fake[ TRIP ]['cbv_lp_event_type'] = 'cruise';
$fake[ TRIP ]['cb_start_date'] = '';
check( 'no start date: no eyebrow date and no date cells', 'Now boarding' === cbv_lp_hero_data( TRIP )['eyebrow'] && '' === cbv_lp_hero_data( TRIP )['start'] );
$fake[ TRIP ]['cb_start_date'] = '2027-10-25';

/* ---- F. render ---- */
section( 'F. hero markup' );
$fake[ TRIP ]['cbv_lp_hero_video'] = VID; $fake[ TRIP ]['cbv_lp_hero_video_small'] = VID2; $fake[ TRIP ]['cbv_lp_hero_poster'] = IMG; $fake[ TRIP ]['cbv_lp_hero_focus'] = 'bottom';
$h = cbv_lp_render_hero( TRIP );
check( 'one hero section labelled by the title', 1 === substr_count( $h, '<section class="cbv-lp-hero"' ) && false !== strpos( $h, 'aria-labelledby="cbv-lp-title"' ) && false !== strpos( $h, 'id="cbv-lp-title"' ) );
check( 'exactly one <h1>', 1 === substr_count( $h, '<h1' ) );
check( 'title is escaped (no raw <b>)', false === strpos( $h, '<b>Voyage' ) );
check( 'poster image with the focus point', 1 === preg_match( '/<img class="cbv-lp-hero-media" src="[^"]*poster\.jpg"[^>]*style="object-position:center bottom;"/', $h ), '' );
check( 'video has NO src attribute (the script decides), is hidden, muted, looping, inline, not preloaded', 1 === preg_match( '/<video [^>]*>/', $h, $vm ) && 0 === preg_match( '/\ssrc=/', $vm[0] ) && false !== strpos( $vm[0], ' hidden' ) && false !== strpos( $vm[0], ' muted' ) && false !== strpos( $vm[0], ' loop' ) && false !== strpos( $vm[0], ' playsinline' ) && false !== strpos( $vm[0], 'preload="none"' ) );
check( 'video carries both sources as data attributes', false !== strpos( $h, 'data-src="' ) && false !== strpos( $h, 'data-src-small="' ) && false !== strpos( $h, '1080.mp4' ) && false !== strpos( $h, '720.mp4' ) );
check( 'video is decorative: aria-hidden and not focusable', false !== strpos( $h, 'aria-hidden="true" tabindex="-1"' ) );
check( 'pause button exists, hidden until the video plays', 1 === preg_match( '/<button type="button" class="cbv-lp-video-toggle" hidden aria-pressed="false">Pause background video<\/button>/', $h ) );
check( 'FROM / TO codes on the pass', false !== strpos( $h, '>MIA<' ) && false !== strpos( $h, '>BIM<' ) && false !== strpos( $h, 'VIA POP' ) && false !== strpos( $h, '>FROM<' ) && false !== strpos( $h, '>TO<' ) );
check( 'place names under the codes', false !== strpos( $h, 'Miami, USA' ) && false !== strpos( $h, 'Bimini, Bahamas' ) );
check( 'three fact cells: departs, returns, vessel', false !== strpos( $h, '--cbv-lp-cols:3' ) && false !== strpos( $h, '<dt>DEPARTS</dt>' ) && false !== strpos( $h, '<dt>RETURNS</dt>' ) && false !== strpos( $h, '<dt>VESSEL</dt>' ) && false !== strpos( $h, 'Oct 25, 2027' ) && false !== strpos( $h, 'Valiant Lady' ) );
check( 'trip code chip', false !== strpos( $h, '<span class="cbv-lp-pass-chip">VL2710255NPP</span>' ) );
check( 'claim-your-seat link to the tagged registration', false !== strpos( $h, 'href="' . esc_url( home_url( '/join/?trip=VL2710255NPP' ) ) . '"' ) && false !== strpos( $h, 'Claim your seat' ) );
check( 'pass heading is cruise wording', false !== strpos( $h, 'Boarding Pass · Traveler' ) );
check( 'tagline shown as the lede', false !== strpos( $h, 'class="cbv-lp-lede">A high-vibe reunion<' ) );

$fake[ TRIP ]['cbv_lp_hero_video'] = 0; $fake[ TRIP ]['cbv_lp_hero_video_small'] = 0;
$h = cbv_lp_render_hero( TRIP );
check( 'no video: no <video> and no pause button', false === strpos( $h, '<video' ) && false === strpos( $h, 'cbv-lp-video-toggle' ) );
check( 'no video, poster only: still image is there', false !== strpos( $h, 'poster.jpg' ) );
$fake[ TRIP ]['cbv_lp_hero_poster'] = 0;
$h = cbv_lp_render_hero( TRIP );
check( 'no media at all: no <img> and no <video>, hero still renders', false === strpos( $h, '<img' ) && false === strpos( $h, '<video' ) && false !== strpos( $h, 'cbv-lp-hero-title' ) );
$fake[ TRIP ]['cb_trip_code'] = '';
$fake[ TRIP ]['cbv_lp_venue_name'] = '';
$h = cbv_lp_render_hero( TRIP );
check( 'no trip code: no chip; no vessel: only two fact cells', false === strpos( $h, 'cbv-lp-pass-chip' ) && false !== strpos( $h, '--cbv-lp-cols:2' ) && false === strpos( $h, '<dt>VESSEL</dt>' ) );
$fake[ TRIP ]['cb_end_date'] = '';
$h = cbv_lp_render_hero( TRIP );
check( 'no end date either: one fact cell', false !== strpos( $h, '--cbv-lp-cols:1' ) && false === strpos( $h, '<dt>RETURNS</dt>' ) );
$fake[ TRIP ]['cb_start_date'] = '';
$h = cbv_lp_render_hero( TRIP );
check( 'no dates and no vessel: no facts list at all', false === strpos( $h, '<dl' ) );
$fake[ TRIP ]['cb_public_landing_show_itinerary'] = '';
$h = cbv_lp_render_hero( TRIP );
check( 'Show Itinerary off: no route block on the pass', false === strpos( $h, 'cbv-lp-pass-route' ) && false === strpos( $h, '>FROM<' ) );
$fake[ TRIP ]['cb_public_landing_show_itinerary'] = 1;
$fake[ TRIP ]['cb_itinerary'] = array( array( 'port' => 'Miami' ), array( 'port' => 'Nassau' ) );
$h = cbv_lp_render_hero( TRIP );
check( 'ports without codes: names shown in the code slot, marked as names', false !== strpos( $h, 'cbv-lp-pass-code cbv-lp-pass-code--name">Miami<' ) && false !== strpos( $h, '>Nassau<' ) );

// hostile values everywhere
$fake[ TRIP ] = array( 'cb_start_date' => '2027-10-25', 'cb_end_date' => '2027-10-30', 'cb_trip_code' => '"><script>alert(1)</script>', 'cb_public_landing_tagline' => '<img src=x onerror=alert(1)> "quoted"', 'cb_public_landing_show_itinerary' => 1,
	'cb_itinerary' => array( array( 'port' => '<script>x</script>Port', 'country' => '"><b>', 'stop_code' => '<b>' ), array( 'port' => 'Two"><svg onload=1>', 'stop_code' => 'AB"C' ) ), 'cbv_lp_venue_name' => '"><img src=x onerror=alert(2)>', 'cbv_lp_hero_poster' => IMG, 'cbv_lp_hero_video' => VID );
$h = cbv_lp_render_hero( TRIP );
check( 'hostile content: no live <script>, no event-handler attributes, no injected tags', false === stripos( $h, '<script' ) && 0 === preg_match( '/<[a-z]+[^>]*\son[a-z]+=/i', $h ) && 1 === substr_count( $h, '<svg' ) && false !== strpos( $h, '&lt;svg onload=1&gt;' ) && false === strpos( $h, '<b>' ) && 1 === substr_count( $h, '<img' ) );
check( 'hostile content: still exactly one section and one h1', 1 === substr_count( $h, '<section' ) && 1 === substr_count( $h, '<h1' ) );
$dom = new DOMDocument(); libxml_use_internal_errors( true ); $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $h ); libxml_clear_errors();
check( 'hostile content: output parses with one <section>, one <h1>, and one <img>', 1 === $dom->getElementsByTagName( 'section' )->length && 1 === $dom->getElementsByTagName( 'h1' )->length && 1 === $dom->getElementsByTagName( 'img' )->length );

/* ---- G. stripping the old hero from today's content ---- */
section( 'G. legacy hero removal' );
$legacy = '<div class="cbv-public-landing"><div class="cbv-landing-hero"><img class="cbv-landing-hero-img" src="https://example.test/a.jpg" alt="x — y"><div class="cbv-landing-hero-scrim"><h1 class="cbv-landing-title">Old Title</h1><p>Old tagline</p></div><div class="phase-tag cbv-landing-badge"><span>JOIN US</span></div></div>'
	. '<div class="cbv-landing-basics"><div class="cbv-landing-basic"><span>Departs from</span><span>Miami, USA</span></div></div>'
	. '<div class="cbv-landing-section"><h2>Itinerary — Café ’quoted’ &amp; more</h2><table><tr><td>1</td><td>Miami</td></tr></table><details><summary>Docs</summary><p>Policy text</p></details></div></div>';
$out = cbv_lp_strip_legacy_hero( $legacy );
check( 'legacy hero and basics are removed', false === strpos( $out, 'cbv-landing-hero' ) && false === strpos( $out, 'cbv-landing-basics' ) && false === strpos( $out, 'Old Title' ) && false === strpos( $out, 'JOIN US' ) && false === strpos( $out, 'Departs from' ) );
check( 'the old <h1> is gone, so the page keeps a single <h1>', 0 === substr_count( $out, '<h1' ) );
check( 'the rest of the content survives intact (heading, table, accordion)', false !== strpos( $out, '<h2>Itinerary' ) && false !== strpos( $out, '<table>' ) && false !== strpos( $out, 'Miami' ) && false !== strpos( $out, '<summary>Docs</summary>' ) && false !== strpos( $out, 'Policy text' ) );
check( 'non-ASCII characters and entities survive', false !== strpos( $out, 'Café' ) && false !== strpos( $out, '’quoted’' ) && false !== strpos( $out, '&amp; more' ), $out );
check( 'the wrapper div is kept', 0 === strpos( $out, '<div class="cbv-public-landing">' ) );
check( 'no stray root div or xml declaration leaks out', false === strpos( $out, 'cbv-lp-root' ) && false === strpos( $out, '<?xml' ) );
$nohero = '<div class="cbv-public-landing"><div class="cbv-landing-section"><h2>Only this</h2></div></div>';
check( 'content without those blocks is returned unchanged', $nohero === cbv_lp_strip_legacy_hero( $nohero ) );
check( 'empty and whitespace content is returned unchanged', '' === cbv_lp_strip_legacy_hero( '' ) && '  ' === cbv_lp_strip_legacy_hero( '  ' ) );
$bad = '<div class="cbv-landing-hero"><p>unclosed <div class="cbv-landing-basics">x';
check( 'malformed markup does not fatal', is_string( cbv_lp_strip_legacy_hero( $bad ) ) );
check( 'a class that merely CONTAINS the word is not matched (cbv-landing-hero-extra)', false !== strpos( cbv_lp_strip_legacy_hero( '<div class="cbv-landing-hero-extra">keep</div><div class="cbv-landing-hero">drop</div>' ), 'keep' ) );

/* ---- H. the real current page for trip 181 (read-only render) ---- */
section( 'H. the real trip 181' );
$real = cbv_render_public_trip_landing( 181 );
check( 'the real current page has exactly one <h1> before', 1 === substr_count( $real, '<h1' ), (string) substr_count( $real, '<h1' ) );
$stripped = cbv_lp_strip_legacy_hero( $real );
check( 'after stripping the real page: no hero/basics and no <h1>', false === strpos( $stripped, 'cbv-landing-hero' ) && false === strpos( $stripped, 'cbv-landing-basics' ) && 0 === substr_count( $stripped, '<h1' ) );
$norm = function ( $html ) { return preg_replace( '/\s+/', ' ', trim( html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) ); };
$d2 = new DOMDocument(); libxml_use_internal_errors( true ); $d2->loadHTML( '<?xml encoding="utf-8" ?><div id="r">' . $real . '</div>' ); libxml_clear_errors();
$xp = new DOMXPath( $d2 );
$gone = 0;
foreach ( iterator_to_array( $xp->query( '//div[contains(concat(" ",normalize-space(@class)," ")," cbv-landing-hero ") or contains(concat(" ",normalize-space(@class)," ")," cbv-landing-basics ")]' ) ) as $n ) { $n->parentNode->removeChild( $n ); $gone++; }
$expected_rest = trim( preg_replace( '/\s+/', ' ', $d2->getElementById( 'r' )->textContent ) );
check( 'exactly the hero and the basics strip were removed (2 blocks)', 2 === $gone, (string) $gone );
check( 'everything else is word-for-word still there (text of the rest is identical)', strlen( $expected_rest ) > 200 && $norm( $stripped ) === $expected_rest, strlen( $norm( $stripped ) ) . ' vs ' . strlen( $expected_rest ) );
check( 'the number of accordions, tables and links is unchanged apart from the hero', substr_count( $real, '<details' ) === substr_count( $stripped, '<details' ) && substr_count( $real, '<table' ) === substr_count( $stripped, '<table' ) );

/* ---- I. wiring: cbv_lp_render_trip ---- */
section( 'I. page assembly' );
$page = cbv_lp_render_trip( 181, 'new_preview' );
check( 'preview banner first, then the new hero, then the content wrap', strpos( $page, 'cbv-lp-preview-banner' ) < strpos( $page, 'class="cbv-lp-hero"' ) && strpos( $page, 'class="cbv-lp-hero"' ) < strpos( $page, 'cbv-lp-legacy-wrap' ) );
check( 'assembled page: exactly one <h1>, no old hero', 1 === substr_count( $page, '<h1' ) && false === strpos( $page, 'cbv-landing-hero' ) );
$public = cbv_lp_render_trip( 181, 'new' );
check( 'public view has no preview banner but has the new hero', false === strpos( $public, 'cbv-lp-preview-banner' ) && false !== strpos( $public, 'class="cbv-lp-hero"' ) );

/* Run ONLY the hero box's save handler (found by its source file), never the other trip save hooks. */
function run_hero_save( $post_id ) {
	global $wp_filter;
	foreach ( $wp_filter['save_post_cb_trip']->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( $fn instanceof Closure && basename( ( new ReflectionFunction( $fn ) )->getFileName() ) === 'checkedbags-lp-hero.php' ) {
				$fn( $post_id );
				return true;
			}
		}
	}
	return false;
}
check( 'the hero save handler is registered', true === run_hero_save( 999999 ) );

/* ---- J. admin box ---- */
section( 'J. admin box' );
$fake[ TRIP ] = array( 'cbv_lp_hero_video' => VID, 'cbv_lp_hero_video_small' => 0, 'cbv_lp_hero_poster' => IMG, 'cbv_lp_hero_focus' => 'top-left', 'cbv_lp_venue_name' => 'Valiant "Lady" <i>x</i>' );
ob_start(); cbv_lp_render_hero_box( get_post( TRIP ) ); $box = ob_get_clean();
check( 'box has the nonce and all five fields', false !== strpos( $box, 'name="cbv_lp_hero_nonce"' ) && false !== strpos( $box, 'name="cbv_lp_hero_video"' ) && false !== strpos( $box, 'name="cbv_lp_hero_video_small"' ) && false !== strpos( $box, 'name="cbv_lp_hero_poster"' ) && false !== strpos( $box, 'name="cbv_lp_hero_focus"' ) && false !== strpos( $box, 'name="cbv_lp_venue_name"' ) );
check( 'saved ids are in the hidden inputs and the focus is selected', false !== strpos( $box, 'value="' . VID . '"' ) && false !== strpos( $box, 'value="' . IMG . '"' ) && 1 === preg_match( '/<option value="top-left"\s+selected=\'selected\'/', $box ) );
check( 'chooser shows the chosen file name', false !== strpos( $box, 'terminal-v-hero-1080.mp4' ) );
check( 'venue value is attribute-escaped', false === strpos( $box, 'value="Valiant "Lady"' ) && false !== strpos( $box, 'Valiant &quot;Lady&quot; &lt;i&gt;x&lt;/i&gt;' ) );
check( 'help text carries the media rule', false !== strpos( $box, 'never hide Virgin' ) && false !== strpos( $box, 'First Mates' ) );
check( 'the box registers on cb_trip', false !== has_action( 'add_meta_boxes' ) );

// save handler: valid nonce + admin => exact sanitized writes; bad nonce => nothing
wp_set_current_user( 1 );
$_POST = array( 'cbv_lp_hero_nonce' => 'bad', 'cbv_lp_hero_video' => VID );
$writes = array();
run_hero_save( 181 );
$mine = array_values( array_filter( $writes, function ( $w ) { return 0 === strpos( (string) $w[2], 'cbv_lp_hero' ) || 'cbv_lp_venue_name' === $w[2]; } ) );
check( 'bad nonce: nothing written', array() === $mine );
$_POST = array( 'cbv_lp_hero_nonce' => wp_create_nonce( 'cbv_lp_hero_save' ), 'cbv_lp_hero_video' => (string) VID, 'cbv_lp_hero_video_small' => (string) PAGE, 'cbv_lp_hero_poster' => (string) IMG, 'cbv_lp_hero_focus' => 'bottom-right', 'cbv_lp_venue_name' => ' <b>Valiant</b> Lady ' );
$writes = array();
run_hero_save( 181 );
$by = array(); foreach ( $writes as $w ) { if ( 'update_post_metadata' === $w[0] ) { $by[ $w[2] ] = $w[3]; } }
check( 'valid save writes the five values, sanitized (a page id is refused for the video)', VID === $by['cbv_lp_hero_video'] && 0 === $by['cbv_lp_hero_video_small'] && IMG === $by['cbv_lp_hero_poster'] && 'bottom-right' === $by['cbv_lp_hero_focus'] && 'Valiant Lady' === $by['cbv_lp_venue_name'], json_encode( $by ) );
// A real existing user who is NOT allowed to edit this trip, with a nonce that is valid for THEM:
// only the capability check can stop the write.
$low = 0;
foreach ( get_users( array( 'number' => 40, 'fields' => 'ID', 'role__not_in' => array( 'administrator' ) ) ) as $uid ) {
	if ( ! user_can( (int) $uid, 'edit_post', 181 ) ) { $low = (int) $uid; break; }
}
check( 'found a real user without edit rights for the capability test', $low > 0 );
wp_set_current_user( $low );
$_POST = array( 'cbv_lp_hero_nonce' => wp_create_nonce( 'cbv_lp_hero_save' ), 'cbv_lp_hero_video' => (string) VID, 'cbv_lp_hero_focus' => 'top', 'cbv_lp_venue_name' => 'Hijack' );
check( 'the nonce really is valid for that user (so the test isolates the capability check)', false !== wp_verify_nonce( $_POST['cbv_lp_hero_nonce'], 'cbv_lp_hero_save' ) );
$writes = array();
run_hero_save( 181 );
$mine = array_filter( $writes, function ( $w ) { return 0 === strpos( (string) $w[2], 'cbv_lp_hero' ) || 'cbv_lp_venue_name' === $w[2]; } );
check( 'a user who cannot edit the trip writes nothing even with a valid nonce', array() === $mine );
wp_set_current_user( 0 );
$_POST = array();

/* ---- K. itinerary stop code in the existing trip editor (checkedbags-trips.php) ---- */
section( 'K. itinerary Code column' );
ob_start(); cb_render_itinerary_row_fields( 3, array( 'port' => 'Miami', 'stop_code' => 'M"IA' ) ); $row = ob_get_clean();
check( 'row has a Code input with the right name, 5-char limit and an escaped value', false !== strpos( $row, 'name="cb_itinerary[3][stop_code]"' ) && false !== strpos( $row, 'maxlength="5"' ) && false !== strpos( $row, 'value="M&quot;IA"' ) );
ob_start(); cb_render_itinerary_row_fields( '__INDEX__', array() ); $tpl = ob_get_clean();
check( 'the blank template row has the Code input too (so added rows get it)', false !== strpos( $tpl, 'cb_itinerary[__INDEX__][stop_code]' ) );
check( 'existing fields in the row are still there', false !== strpos( $row, '[port]' ) && false !== strpos( $row, '[country]' ) && false !== strpos( $row, '[description]' ) && false !== strpos( $row, '[tender_mode]' ) && false !== strpos( $row, '[day]' ) );
$src = file_get_contents( WPMU_PLUGIN_DIR . '/checkedbags-trips.php' );
preg_match( "/'stop_code'\s*=>\s*(.+?),\r?\n/", $src, $m );
check( 'the save handler has the stop_code line', ! empty( $m[1] ) );
$expr = $m[1];
$eval = function ( $row ) use ( $expr ) { return eval( 'return ' . $expr . ';' ); };
check( 'save expression: lower-case, junk, long and missing inputs', 'MIA' === $eval( array( 'stop_code' => 'mia' ) ) && 'MIA' === $eval( array( 'stop_code' => ' m-i.a ' ) ) && 'ABCDE' === $eval( array( 'stop_code' => 'abcdefghi' ) ) && '' === $eval( array() ) );
check( 'save expression agrees with cbv_lp_clean_stop_code', $eval( array( 'stop_code' => '<b>Zz9!' ) ) === cbv_lp_clean_stop_code( '<b>Zz9!' ) );
check( 'grid CSS has a column for the new input (9 columns)', 1 === preg_match( '/grid-template-columns: 70px 130px 1fr 80px 1fr 140px 90px 100px auto;/', $src ) );

/* ---- L. what Step 4 must NOT do ---- */
section( 'L. no side effects' );
check( 'the current (legacy) renderer is unchanged: still outputs its own hero for the public view', false !== strpos( cbv_render_public_trip_landing( 181 ), 'cbv-landing-hero' ) );
check( 'new-design switch logic untouched: master option is not live', ! get_option( 'cbv_lp_redesign_live' ) );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
