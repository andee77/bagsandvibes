<?php
/*
 * Step 7 tests: featured moment + gallery, and their trip boxes.
 * Run against a temp COPY of the mu-plugins folder with the Step 7 files overlaid:
 *   wp --require=/tmp/cbv_t/define.php eval-file test_step7.php
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
check( 'Step 7 code is loaded', function_exists( 'cbv_lp_render_featured' ) && function_exists( 'cbv_lp_render_gallery' ) && function_exists( 'cbv_lp_sanitize_gallery' ) && 10 === CBV_LP_GALLERY_MAX );

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
const TRIP = 999350; const VID = 999361; const PAGE = 999362;
fake_post( TRIP, 'cb_trip', 'publish', 'Test Voyage' );
fake_post( VID, 'attachment', 'inherit', 'video', 'video/mp4' );
fake_post( PAGE, 'page', 'publish', 'A page' );
$fake[ VID ] = array( '_wp_attached_file' => 'virgin/hero.mp4' );
// Twelve images: 999371..999382
$imgs = array();
for ( $i = 1; $i <= 12; $i++ ) {
	$id = 999370 + $i;
	fake_post( $id, 'attachment', 'inherit', 'photo ' . $i, 'image/jpeg' );
	$fake[ $id ] = array( '_wp_attached_file' => "virgin/photo-$i.jpg", '_wp_attachment_metadata' => array( 'width' => 800, 'height' => 1000, 'file' => "virgin/photo-$i.jpg", 'sizes' => array() ), '_wp_attachment_image_alt' => "Alt text $i" );
	$imgs[] = $id;
}
$GLOBALS['imgs'] = $imgs;
$GLOBALS['it'] = array(
	array( 'date' => '2027-10-25', 'port' => 'Miami', 'description' => 'Embarkation', 'time' => '17:00', 'stop_code' => 'MIA' ),
	array( 'date' => '2027-10-26', 'port' => '', 'description' => 'At Sea' ),
	array( 'date' => '2027-10-27', 'port' => 'Miami', 'description' => 'Disembarkation', 'time' => '06:30', 'stop_code' => 'MIA' ),
);
function trip_meta( $extra = array() ) {
	return array_merge( array(
		'cbv_lp_event_type' => 'cruise', 'cbv_lp_sections' => array(), 'cbv_lp_accommodation_noun' => '',
		'cb_start_date' => '2027-10-25', 'cb_end_date' => '2027-10-27', 'cb_public_landing_show_itinerary' => 1,
		'cb_itinerary' => $GLOBALS['it'], 'cbv_lp_venue_name' => 'Valiant Lady', 'cbv_lp_featured' => array(), 'cbv_lp_gallery' => array(),
		'cb_trip_code' => 'CBV-TEST', 'cb_deposit_amount' => 0,
	), $extra );
}
function gallery_of( $n, $extra = array() ) {
	$photos = array();
	foreach ( array_slice( $GLOBALS['imgs'], 0, $n ) as $i => $id ) { $photos[] = array( 'id' => $id, 'caption' => 'Caption ' . ( $i + 1 ), 'focus' => '' ); }
	return array_merge( array( 'heading' => 'Slow mornings. Loud nights.', 'intro' => 'Hammocks and showtime.', 'photos' => $photos ), $extra );
}

/* ---- A. sanitize ---- */
section( 'A. sanitize' );
check( 'four colours, no free colour', array( 'horizon', 'ink', 'teal', 'red' ) === array_keys( cbv_lp_featured_colours() ) );
$f = cbv_lp_sanitize_featured( array( 'title' => ' <b>Scarlet</b> Night ', 'day' => '2', 'text' => "Night falls.\n\n<script>x</script>We dance.", 'note_label' => 'Dress code · <i>All red</i>', 'note_text' => 'Wear something that can get wet.', 'photo1' => $imgs[0], 'focus1' => 'top', 'photo2' => $imgs[1], 'focus2' => 'bottom-left', 'colour' => 'red' ) );
check( 'featured: valid values pass, tags stripped', 'Scarlet Night' === $f['title'] && 2 === $f['day'] && 'Dress code · All red' === $f['note_label'] && $imgs[0] === $f['photo1'] && 'top' === $f['focus1'] && $imgs[1] === $f['photo2'] && 'bottom-left' === $f['focus2'] && 'red' === $f['colour'] && false === strpos( $f['text'], '<script>' ) && false !== strpos( $f['text'], "\n\n" ) );
$f = cbv_lp_sanitize_featured( array( 'day' => '-1', 'colour' => '#ff0000', 'photo1' => VID, 'photo2' => PAGE, 'focus1' => 'diagonal' ) );
check( 'featured: bad day, free colour, video/page as photo, unknown focus are refused', 0 === $f['day'] && 'horizon' === $f['colour'] && 0 === $f['photo1'] && 0 === $f['photo2'] && '' === $f['focus1'] );
check( 'featured: day out of range or text is 0', 0 === cbv_lp_sanitize_featured( array( 'day' => '400' ) )['day'] && 0 === cbv_lp_sanitize_featured( array( 'day' => '2a' ) )['day'] && 0 === cbv_lp_sanitize_featured( array( 'day' => array( 2 ) ) )['day'] );
check( 'featured: caps (title 80, note label 40, note 200, text 2000)', 80 === mb_strlen( cbv_lp_sanitize_featured( array( 'title' => str_repeat( 'a', 200 ) ) )['title'] ) && 40 === mb_strlen( cbv_lp_sanitize_featured( array( 'note_label' => str_repeat( 'a', 99 ) ) )['note_label'] ) && 200 === mb_strlen( cbv_lp_sanitize_featured( array( 'note_text' => str_repeat( 'a', 300 ) ) )['note_text'] ) && 2000 === mb_strlen( cbv_lp_sanitize_featured( array( 'text' => str_repeat( 'a', 3000 ) ) )['text'] ) );
check( 'featured: non-array input gives defaults (Horizon)', 'horizon' === cbv_lp_sanitize_featured( 'junk' )['colour'] && '' === cbv_lp_sanitize_featured( null )['title'] );
$g = cbv_lp_sanitize_gallery( array( 'heading' => '<b>Life</b>', 'intro' => 'Hi', 'photos' => array( 2 => array( 'id' => $imgs[2], 'caption' => 'Third' ), 'n0' => array( 'id' => $imgs[0], 'caption' => '<i>First</i>', 'focus' => 'right' ), 0 => array( 'id' => VID ), 'junk', 5 => array( 'id' => $imgs[1] ) ) ) );
check( 'gallery: posted order kept (mixed keys), video and junk rows dropped, tags stripped', array( $imgs[2], $imgs[0], $imgs[1] ) === wp_list_pluck( $g['photos'], 'id' ) && 'First' === $g['photos'][1]['caption'] && 'right' === $g['photos'][1]['focus'] && 'Life' === $g['heading'], json_encode( $g ) );
$many = array(); foreach ( $imgs as $id ) { $many[] = array( 'id' => $id ); }
check( 'gallery: twelve posted, ten kept (the first ten)', 10 === count( cbv_lp_sanitize_gallery( array( 'photos' => $many ) )['photos'] ) && $imgs[9] === end( cbv_lp_sanitize_gallery( array( 'photos' => $many ) )['photos'] )['id'] );
check( 'gallery: caps (caption 60, heading 80, intro 300)', 60 === mb_strlen( cbv_lp_sanitize_gallery( array( 'photos' => array( array( 'id' => $imgs[0], 'caption' => str_repeat( 'a', 99 ) ) ) ) )['photos'][0]['caption'] ) && 80 === mb_strlen( cbv_lp_sanitize_gallery( array( 'heading' => str_repeat( 'a', 99 ) ) )['heading'] ) && 300 === mb_strlen( cbv_lp_sanitize_gallery( array( 'intro' => str_repeat( 'a', 400 ) ) )['intro'] ) );
check( 'gallery: non-array input gives an empty gallery', array( 'heading' => '', 'intro' => '', 'photos' => array() ) === cbv_lp_sanitize_gallery( 'junk' ) );

/* ---- B. featured data ---- */
section( 'B. featured data' );
$fake[ TRIP ] = trip_meta();
check( 'day choices come from the route days', array( 1 => 'Day 01 · Mon Oct 25 · Miami', 2 => 'Day 02 · Tue Oct 26 · At sea', 3 => 'Day 03 · Wed Oct 27 · Miami' ) === cbv_lp_featured_day_choices( TRIP ), json_encode( cbv_lp_featured_day_choices( TRIP ) ) );
check( 'no title: no featured section', null === cbv_lp_featured_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_featured' => array( 'title' => 'Scarlet Night on {vessel}', 'day' => 2, 'text' => 'Night falls.', 'note_label' => 'Dress code · All red', 'note_text' => 'Wear **red**.', 'photo1' => $imgs[0], 'focus1' => 'top', 'photo2' => $imgs[1], 'colour' => 'red' ) ) );
$d = cbv_lp_featured_data( TRIP );
check( 'title with tokens, Day 02, section name After dark, red', 'Scarlet Night on Valiant Lady' === $d['title'] && 'Day 02' === $d['day'] && 'After dark' === $d['section'] && 'red' === $d['colour'] );
check( 'two photos with focus positions; note with markup', 2 === count( $d['photos'] ) && 'center top' === $d['photos'][0]['focus'] && 'center center' === $d['photos'][1]['focus'] && 'Wear <strong>red</strong>.' === $d['note_text'] );
$fake[ TRIP ]['cb_itinerary'] = array_slice( $GLOBALS['it'], 0, 1 );
check( 'a day no longer in the itinerary is not shown (the rest still is)', '' === cbv_lp_featured_data( TRIP )['day'] && 'Scarlet Night on Valiant Lady' === cbv_lp_featured_data( TRIP )['title'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_featured' => array( 'title' => 'X', 'day' => 2 ) ) );
$fake[ TRIP ]['cb_itinerary'] = array_map( function ( $r ) { $r['date'] = gmdate( 'Y-m-d', strtotime( $r['date'] . ' UTC' ) + 30 * DAY_IN_SECONDS ); return $r; }, $GLOBALS['it'] );
$fake[ TRIP ]['cb_start_date'] = '2027-11-24';
check( 'trip moved 30 days later: the Day link stays on day 2', 'Day 02' === cbv_lp_featured_data( TRIP )['day'] );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_featured' => array( 'title' => 'X' ), 'cbv_lp_sections' => array( 'featured' => 'off' ) ) );
check( 'hidden when the section is switched Off', null === cbv_lp_featured_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_featured' => array( 'title' => 'X' ), 'cbv_lp_event_type' => 'resort' ) );
check( 'hidden by default for a resort (not one of its sections)', null === cbv_lp_featured_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_featured' => array( 'title' => 'X', 'photo1' => VID ) ) );
check( 'a stored non-image id is ignored', array() === cbv_lp_featured_data( TRIP )['photos'] );

/* ---- C. featured markup ---- */
section( 'C. featured markup' );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_featured' => array( 'title' => 'Scarlet <script>alert(1)</script> Night', 'day' => 2, 'text' => "[Bad](javascript:alert(1)) and [good](https://bagsandvibes.com/)", 'note_label' => 'Dress <b>code</b>', 'note_text' => '[x](javascript:alert(1)) <img src=x>', 'photo1' => $imgs[0], 'focus1' => 'top-right', 'photo2' => $imgs[1], 'colour' => 'teal' ) ) );
cbv_lp_next_gate( true ); cbv_lp_next_gate(); cbv_lp_next_gate();
$h = cbv_lp_render_featured( TRIP );
check( 'label "Gate 16 · Day 02 · After dark" after two earlier gates', false !== strpos( $h, '>Gate 16 · Day 02 · After dark</p>' ), $h );
check( 'colour class; section labelled by its h2 (a script tag is removed with its contents)', false !== strpos( $h, 'class="cbv-lp-featured cbv-lp-featured--teal"' ) && false !== strpos( $h, 'aria-labelledby="cbv-lp-featured-title"' ) && false === strpos( $h, '<script>' ) && false === strpos( $h, 'alert(1)' ) && false !== strpos( $h, 'id="cbv-lp-featured-title">Scarlet Night</h2>' ) );
check( 'two photos: alt from the Media Library, lazy, focus', 2 === substr_count( $h, 'cbv-lp-featured-img' ) && false !== strpos( $h, 'alt="Alt text 1"' ) && false !== strpos( $h, 'alt="Alt text 2"' ) && false !== strpos( $h, 'object-position:right top;' ) && false !== strpos( $h, 'loading="lazy"' ) && false === strpos( $h, 'featured-photos--one' ) );
check( 'note card escaped; javascript: links dropped in text and note, https kept', false !== strpos( $h, '<p class="cbv-lp-note-label">Dress code</p>' ) && false === stripos( $h, 'javascript' ) && false === strpos( $h, '<img src=x' ) && false !== strpos( $h, '<a href="https://bagsandvibes.com/" rel="noopener">good</a>' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_featured' => array( 'title' => 'Salt & Sea' ) ) );
check( 'the title is HTML-escaped on output (an & becomes &amp;)', false !== strpos( cbv_lp_render_featured( TRIP ), 'id="cbv-lp-featured-title">Salt &amp; Sea</h2>' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_featured' => array( 'title' => 'Solo', 'photo2' => $imgs[3] ) ) );
cbv_lp_next_gate( true );
$h = cbv_lp_render_featured( TRIP );
check( 'one photo: single-photo layout; no day: label without a day; default Horizon', false !== strpos( $h, 'cbv-lp-featured-photos--one' ) && false !== strpos( $h, '>Gate 14 · After dark</p>' ) && false !== strpos( $h, 'cbv-lp-featured--horizon' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_featured' => array( 'title' => 'Text only' ) ) );
$h = cbv_lp_render_featured( TRIP );
check( 'no photos: solo layout, no photo block, no empty note', false !== strpos( $h, 'cbv-lp-featured-inner--solo' ) && false === strpos( $h, 'cbv-lp-featured-photos' ) && false === strpos( $h, 'cbv-lp-note' ) );
$fake[ TRIP ] = trip_meta();
cbv_lp_next_gate( true );
check( 'hidden featured renders nothing and does not use up a GATE number', '' === cbv_lp_render_featured( TRIP ) && 14 === cbv_lp_next_gate() );

/* ---- D. gallery ---- */
section( 'D. gallery' );
$fake[ TRIP ] = trip_meta();
check( 'no photos: no gallery', null === cbv_lp_gallery_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_gallery' => array( 'heading' => 'X', 'photos' => array( array( 'id' => VID ), array( 'id' => PAGE ) ) ) ) );
check( 'only non-image ids: no gallery', null === cbv_lp_gallery_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_gallery' => gallery_of( 10 ) ) );
cbv_lp_next_gate( true );
$h = cbv_lp_render_gallery( TRIP );
check( 'ten prints in a list, each a figure with its caption', 10 === substr_count( $h, '<li class="cbv-lp-print-item">' ) && 10 === substr_count( $h, '<figure class="cbv-lp-print">' ) && false !== strpos( $h, '<figcaption>Caption 10</figcaption>' ) );
check( 'GATE label with the section name (Life on board), heading, intro', false !== strpos( $h, '<p class="cbv-lp-gate">Gate 14 · Life on board</p>' ) && false !== strpos( $h, 'id="cbv-lp-gallery-title">Slow mornings. Loud nights.</h2>' ) && false !== strpos( $h, '<p class="cbv-lp-gallery-intro">Hammocks and showtime.</p>' ) );
check( 'alt text from the Media Library; lazy loading', false !== strpos( $h, 'alt="Alt text 1"' ) && false !== strpos( $h, 'alt="Alt text 10"' ) && 10 === substr_count( $h, 'loading="lazy"' ) );
$stored = gallery_of( 12 ); // more than the limit stored somehow
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_gallery' => $stored ) );
check( 'more than ten stored: only ten shown', 10 === substr_count( cbv_lp_render_gallery( TRIP ), '<li class="cbv-lp-print-item">' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_gallery' => array( 'heading' => '', 'intro' => '', 'photos' => array( array( 'id' => $imgs[0], 'caption' => '', 'focus' => 'left' ), array( 'id' => $imgs[1], 'caption' => 'Rooftop <b>toast</b> on {vessel}' ) ) ) ) );
cbv_lp_next_gate( true );
$h = cbv_lp_render_gallery( TRIP );
check( 'blank heading = the section name; no intro = no empty paragraph', false !== strpos( $h, 'id="cbv-lp-gallery-title">Life on board</h2>' ) && false === strpos( $h, 'cbv-lp-gallery-intro' ) );
check( 'no caption = no empty figcaption; caption tokens filled, tags stripped; focus applied', 1 === substr_count( $h, '<figcaption>' ) && false !== strpos( $h, '<figcaption>Rooftop toast on Valiant Lady</figcaption>' ) && false !== strpos( $h, 'object-position:left center;' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_gallery' => array( 'heading' => 'H <script>x</script>', 'intro' => '[x](javascript:alert(1)) **bold**', 'photos' => array( array( 'id' => $imgs[0] ) ) ) ) );
$h = cbv_lp_render_gallery( TRIP );
check( 'heading escaped; intro keeps bold, drops the javascript: link', false === strpos( $h, '<script>' ) && false === stripos( $h, 'javascript' ) && false !== strpos( $h, '<strong>bold</strong>' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_gallery' => gallery_of( 3 ), 'cbv_lp_event_type' => 'resort' ) );
check( 'resort: section name The place', false !== strpos( cbv_lp_render_gallery( TRIP ), '· The place</p>' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_gallery' => gallery_of( 3 ), 'cbv_lp_sections' => array( 'gallery' => 'off' ) ) );
check( 'hidden when the section is switched Off', null === cbv_lp_gallery_data( TRIP ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_gallery' => gallery_of( 3 ), 'cbv_lp_event_type' => 'corporate' ) );
check( 'hidden by default for corporate', null === cbv_lp_gallery_data( TRIP ) );

/* ---- E. page assembly (REAL trip 181) ---- */
section( 'E. page assembly' );
$page = cbv_lp_render_trip( 181, 'new_preview' );
check( 'REAL trip 181 (content set 2026-10-05/08): intro 14, route 15, Scarlet Night featured 16, gallery 17 with 9 prints', false !== strpos( $page, 'Gate 14 · The trip' ) && false !== strpos( $page, 'Gate 15 · Flight path' ) && false !== strpos( $page, 'Gate 16 · After dark' ) && false !== strpos( $page, 'id="cbv-lp-featured-title">Scarlet Night</h2>' ) && false !== strpos( $page, 'Gate 17 · Life on board' ) && 9 === substr_count( $page, '<li class="cbv-lp-print-item">' ) );
$fake[ 181 ] = array( 'cbv_lp_featured' => array( 'title' => 'Scarlet Night', 'day' => 2, 'colour' => 'red' ), 'cbv_lp_gallery' => gallery_of( 4 ) );
$page = cbv_lp_render_trip( 181, 'new' );
check( 'order: route, featured, gallery, legacy content', strpos( $page, 'class="cbv-lp-route"' ) < strpos( $page, 'class="cbv-lp-featured' ) && strpos( $page, 'class="cbv-lp-featured' ) < strpos( $page, 'class="cbv-lp-gallery"' ) && strpos( $page, 'class="cbv-lp-gallery"' ) < strpos( $page, 'cbv-lp-legacy-wrap' ) );
check( 'GATE 16 · Day 02 · After dark, then GATE 17 · Life on board', false !== strpos( $page, 'Gate 16 · Day 02 · After dark' ) && false !== strpos( $page, 'Gate 17 · Life on board' ) );
$fake[ 181 ] = array( 'cbv_lp_featured' => array(), 'cbv_lp_gallery' => gallery_of( 2 ) ); // hide the real featured moment for this check
check( 'with no featured moment, the gallery becomes GATE 16', false !== strpos( cbv_lp_render_trip( 181, 'new' ), 'Gate 16 · Life on board' ) );
unset( $fake[ 181 ] );
check( 'still exactly one <h1>', 1 === substr_count( cbv_lp_render_trip( 181, 'new' ), '<h1' ) );

/* ---- F. boxes ---- */
section( 'F. boxes' );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_featured' => array( 'title' => 'Say "hi"', 'day' => 2, 'note_text' => '</textarea><b>', 'photo1' => $imgs[0], 'focus1' => 'right', 'colour' => 'ink' ) ) );
ob_start(); cbv_lp_render_featured_box( get_post( TRIP ) ); $box = ob_get_clean();
check( 'featured box: nonce and fields', false !== strpos( $box, 'name="cbv_lp_featured_nonce"' ) && false !== strpos( $box, 'name="cbv_lp_featured[title]"' ) && false !== strpos( $box, 'name="cbv_lp_featured[day]"' ) && false !== strpos( $box, 'name="cbv_lp_featured[text]"' ) && false !== strpos( $box, 'name="cbv_lp_featured[note_label]"' ) && false !== strpos( $box, 'name="cbv_lp_featured[photo1]"' ) && false !== strpos( $box, 'name="cbv_lp_featured[photo2]"' ) && false !== strpos( $box, 'name="cbv_lp_featured[colour]"' ) );
check( 'featured box: day dropdown lists the route days with day 2 selected', 1 === preg_match( '/<option value="2"\s+selected=\'selected\'>Day 02 · Tue Oct 26 · At sea<\/option>/', $box ) && false !== strpos( $box, '<option value="0">No day</option>' ) );
check( 'featured box: values escaped; colour and focus selected; file name shown', false !== strpos( $box, 'value="Say &quot;hi&quot;"' ) && false === strpos( $box, '</textarea><b>' ) && 1 === preg_match( '/<option value="ink"\s+selected=\'selected\'/', $box ) && 1 === preg_match( '/<option value="right"\s+selected=\'selected\'/', $box ) && false !== strpos( $box, 'photo-1.jpg' ) && false !== strpos( $box, 'Alt Text' ) );
$fake[ TRIP ]['cb_itinerary'] = array_slice( $GLOBALS['it'], 0, 1 );
ob_start(); cbv_lp_render_featured_box( get_post( TRIP ) ); $box = ob_get_clean();
check( 'featured box: a saved day no longer in the itinerary stays selected and is labelled as not shown', false !== strpos( $box, '<option value="2" selected="selected">Day 02 (not in the itinerary now, not shown)</option>' ) );
$fake[ TRIP ] = trip_meta( array( 'cbv_lp_gallery' => gallery_of( 3, array( 'heading' => 'H "q"' ) ) ) );
ob_start(); cbv_lp_render_gallery_box( get_post( TRIP ) ); $box = ob_get_clean();
check( 'gallery box: nonce, heading, three rows, count, Add button, a row template', false !== strpos( $box, 'name="cbv_lp_gallery_nonce"' ) && false !== strpos( $box, 'value="H &quot;q&quot;"' ) && 4 === substr_count( $box, 'class="cbv-lp-gallery-row"' ) && false !== strpos( $box, '3 of 10' ) && false !== strpos( $box, 'cbv-lp-gallery-add' ) && false !== strpos( $box, '<template class="cbv-lp-gallery-template">' ) );
check( 'gallery box: rows carry id, caption, focus and Up / Down / Remove', false !== strpos( $box, 'name="cbv_lp_gallery[photos][0][id]" value="' . $imgs[0] . '"' ) && false !== strpos( $box, 'name="cbv_lp_gallery[photos][2][caption]"' ) && false !== strpos( $box, 'name="cbv_lp_gallery[photos][1][focus]"' ) && 4 === substr_count( $box, 'cbv-lp-gallery-up' ) && 4 === substr_count( $box, 'cbv-lp-gallery-remove' ) );
check( 'gallery box: the template row has the placeholder index and no id', false !== strpos( $box, 'cbv_lp_gallery[photos][__I__][id]" value=""' ) );

/* ---- G. save ---- */
section( 'G. save' );
function run_featured_save( $post_id ) {
	global $wp_filter;
	foreach ( $wp_filter['save_post_cb_trip']->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			$fn = $cb['function'];
			if ( $fn instanceof Closure && basename( ( new ReflectionFunction( $fn ) )->getFileName() ) === 'checkedbags-lp-featured.php' ) {
				$fn( $post_id );
				return true;
			}
		}
	}
	return false;
}
check( 'the save handler is registered', true === run_featured_save( 999999 ) );
$by = function () use ( &$writes ) { $o = array(); foreach ( $writes as $w ) { if ( 'update_post_metadata' === $w[0] ) { $o[ $w[2] ] = $w[3]; } } return $o; };
wp_set_current_user( 1 );
$writes = array();
$_POST = array( 'cbv_lp_featured_nonce' => 'bad', 'cbv_lp_featured' => array( 'title' => 'x' ), 'cbv_lp_gallery_nonce' => 'bad', 'cbv_lp_gallery' => array( 'heading' => 'y' ) );
run_featured_save( TRIP );
check( 'bad nonces: nothing written', array() === $writes );
$writes = array();
$_POST = array( 'cbv_lp_featured_nonce' => wp_create_nonce( 'cbv_lp_featured_save' ), 'cbv_lp_featured' => array( 'title' => ' <b>Scarlet</b> ', 'day' => '2', 'photo1' => (string) VID, 'colour' => 'red', 'focus1' => 'top' ) );
run_featured_save( TRIP );
$o = $by();
check( 'featured only posted: featured written (sanitized), gallery untouched', array( 'cbv_lp_featured' ) === array_keys( $o ) && 'Scarlet' === $o['cbv_lp_featured']['title'] && 2 === $o['cbv_lp_featured']['day'] && 0 === $o['cbv_lp_featured']['photo1'] && 'red' === $o['cbv_lp_featured']['colour'] );
$writes = array();
$rows = array( 'n1' => array( 'id' => (string) $imgs[4], 'caption' => 'E' ), 0 => array( 'id' => (string) $imgs[0], 'caption' => 'A' ), 'n0' => array( 'id' => (string) VID ), 3 => array( 'id' => (string) $imgs[3], 'caption' => 'D' ) );
for ( $i = 5; $i < 12; $i++ ) { $rows[ 'm' . $i ] = array( 'id' => (string) $imgs[ $i ] ); }
$_POST = array( 'cbv_lp_gallery_nonce' => wp_create_nonce( 'cbv_lp_gallery_save' ), 'cbv_lp_gallery' => array( 'heading' => 'Life', 'intro' => 'Hi', 'photos' => $rows ) );
run_featured_save( TRIP );
$o = $by();
check( 'gallery only posted: order as posted, video dropped, capped at ten, featured untouched', array( 'cbv_lp_gallery' ) === array_keys( $o ) && 10 === count( $o['cbv_lp_gallery']['photos'] ) && array( $imgs[4], $imgs[0], $imgs[3], $imgs[5] ) === array_slice( wp_list_pluck( $o['cbv_lp_gallery']['photos'], 'id' ), 0, 4 ), json_encode( wp_list_pluck( $o['cbv_lp_gallery']['photos'], 'id' ) ) );
$writes = array();
$_POST = array( 'cbv_lp_gallery_nonce' => wp_create_nonce( 'cbv_lp_gallery_save' ), 'cbv_lp_gallery' => array( 'heading' => 'Life' ) );
run_featured_save( TRIP );
check( 'removing every photo saves an empty gallery (section hides)', array() === $by()['cbv_lp_gallery']['photos'] );
$low = 0;
foreach ( get_users( array( 'number' => 40, 'fields' => 'ID', 'role__not_in' => array( 'administrator' ) ) ) as $uid ) {
	if ( ! user_can( (int) $uid, 'edit_post', 181 ) ) { $low = (int) $uid; break; }
}
check( 'found a real user without edit rights for the capability test', $low > 0 );
wp_set_current_user( $low );
$_POST = array( 'cbv_lp_featured_nonce' => wp_create_nonce( 'cbv_lp_featured_save' ), 'cbv_lp_featured' => array( 'title' => 'Hijack' ), 'cbv_lp_gallery_nonce' => wp_create_nonce( 'cbv_lp_gallery_save' ), 'cbv_lp_gallery' => array( 'heading' => 'Hijack' ) );
check( 'the nonces really are valid for that user', false !== wp_verify_nonce( $_POST['cbv_lp_featured_nonce'], 'cbv_lp_featured_save' ) && false !== wp_verify_nonce( $_POST['cbv_lp_gallery_nonce'], 'cbv_lp_gallery_save' ) );
$writes = array();
run_featured_save( 181 );
check( 'a user who cannot edit the trip writes nothing even with valid nonces', array() === $writes );
wp_set_current_user( 0 );
$_POST = array();

/* ---- H. what Step 7 must NOT do ---- */
section( 'H. no side effects' );
check( 'the current (legacy) public renderer is unchanged (still has its Highlights)', false !== strpos( cbv_render_public_trip_landing( 181 ), '<h2>Highlights</h2>' ) );
check( 'master switch still off', ! get_option( 'cbv_lp_redesign_live' ) );
check( 'Step 6 route for trip 181 unchanged (six days)', 6 === substr_count( cbv_lp_render_route( 181 ), '<li class="cbv-lp-day">' ) );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
