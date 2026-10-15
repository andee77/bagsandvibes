<?php
/*
 * Wall privacy tests (2026-10-13): personal walls are never LISTED in the forums (forum index, topic lists and
 * views, forum search), for anyone; a wall's own forum page sends readers to the owner's profile; who may OPEN a
 * wall is unchanged (owner, admins, anyone sharing a trip with the owner -- owner's decision 2026-10-13); trip
 * boards and the Lounge are unchanged; the profile timeline and the Feed still find wall posts.
 * Runs as REAL members, read only (wp_set_current_user + bbPress's own list functions; nothing is written).
 *   wp --require=/tmp/cbv_t/define.php eval-file test_wall_privacy.php
 * Set CBV_VERBOSE=1 to print every passing check.
 * Since 2026-10-15 the member social features ship OFF; this test describes walls with them ON, so run it with
 * tests/landing/social_on.php: CBV_SO_BASE=/tmp/cbv_t/define_v2.php wp --require=/tmp/cbv_t/social_on.php eval-file ...
 */
$GLOBALS['n'] = 0; $GLOBALS['fail'] = 0;
function check( $l, $c, $d = '' ) {
	$GLOBALS['n']++;
	if ( ! $c ) { $GLOBALS['fail']++; echo "FAIL: $l $d\n"; }
	elseif ( getenv( 'CBV_VERBOSE' ) ) { echo '  pass ' . str_pad( $GLOBALS['n'], 3, ' ', STR_PAD_LEFT ) . ": $l\n"; }
}
check( 'running against the temp mu-plugins copy', '/tmp/cbv_mu' === WPMU_PLUGIN_DIR );
check( 'the wall-listing code is loaded', function_exists( 'cb_wall_forum_ids' ) && function_exists( 'cb_wall_forum_page_redirect_url' ) );
$writes = array();
foreach ( array( 'update_post_metadata', 'add_post_metadata', 'update_user_metadata', 'add_user_metadata', 'update_option' ) as $h ) {
	add_filter( $h, function ( $c ) use ( &$writes, $h ) { $writes[] = $h; return $c; }, 1 );
}

$walls  = cb_wall_forum_ids();
$lounge = (int) get_option( 'cb_lounge_forum_id' );
$b181   = (int) get_post_meta( 181, 'cb_forum_id', true );
$b320   = (int) get_post_meta( 320, 'cb_forum_id', true );
check( 'the site has walls to test with (4 today)', count( $walls ) >= 4, json_encode( $walls ) );

// Real users: a member on no trip who follows a wall owner, a member on both trips, a member on Helmets only, an admin.
$on = function ( $t ) { return array_map( 'intval', cb_trip_get_roster( $t ) ); };
$plain = 0; foreach ( get_users( array( 'fields' => 'ID' ) ) as $u ) { $u = (int) $u; if ( ! user_can( $u, 'moderate' ) && ! in_array( $u, $on( 181 ), true ) && ! in_array( $u, $on( 320 ), true ) ) { $plain = $u; break; } }
// Since the 2026-10-15 Helmets move there is no Helmets-only member; the one-trip case is now a member on trip 181 only.
$both = 0; $h_only = 0;
foreach ( $on( 320 ) as $u ) { if ( ! user_can( $u, 'moderate' ) && in_array( $u, $on( 181 ), true ) ) { $both = $both ?: $u; } }
foreach ( $on( 181 ) as $u ) { if ( ! user_can( $u, 'moderate' ) && ! in_array( $u, $on( 320 ), true ) ) { $h_only = $h_only ?: $u; } }
$admin = 1;
check( 'test users found (no-trip member, member on both trips, member on one trip only (181))', $plain && $both && $h_only );

function listed_forums() { $o = array(); if ( bbp_has_forums( array( 'post_parent' => 0 ) ) ) { while ( bbp_forums() ) { bbp_the_forum(); $o[] = (int) bbp_get_forum_id(); } } return $o; }
function listed_topics( $args = array() ) { $o = array(); if ( bbp_has_topics( array_merge( array( 'posts_per_page' => 100 ), $args ) ) ) { while ( bbp_topics() ) { bbp_the_topic(); $o[] = (int) bbp_get_topic_id(); } } return $o; }
function search_hits( $s ) { $o = array(); if ( bbp_has_search_results( array( 's' => $s, 'posts_per_page' => 100 ) ) ) { while ( bbp_search_results() ) { bbp_the_search_result(); $o[] = (int) get_the_ID(); } } return $o; }
wp_set_current_user( 1 ); // bbPress hides wall forums from signed-out queries; collect the posts as admin
$wall_topics = array(); foreach ( $walls as $w ) { foreach ( cb_wall_get_posts( $w ) as $t ) { $wall_topics[] = (int) $t->ID; } }
check( 'there are wall posts to test with', count( $wall_topics ) >= 1 );
$busy = 0; foreach ( $walls as $w ) { if ( cb_wall_get_posts( $w ) ) { $busy = $w; break; } } // a wall that has posts
$probe = $wall_topics ? get_the_title( $wall_topics[0] ) : '';
$probe_word = $probe ? preg_split( '/\s+/', trim( $probe ) )[0] . ' ' . ( preg_split( '/\s+/', trim( $probe ) )[1] ?? '' ) : 'x';

$cases = array(
	'no-trip member (follows a wall owner)' => array( $plain, array( $lounge ) ),
	'member on both trips' => array( $both, array( $lounge, $b181, $b320 ) ),
	'member on trip 181 only' => array( $h_only, array( $lounge, $b181 ) ),
	'admin' => array( $admin, array( $lounge, $b181, $b320 ) ),
);
foreach ( $cases as $who => $c ) {
	list( $uid, $expect ) = $c;
	wp_set_current_user( $uid );
	$f = listed_forums(); sort( $f ); sort( $expect );
	check( "$who: /forums/ lists no wall, exactly the Lounge and their own trips' boards", array() === array_intersect( $f, $walls ) && $expect === $f, json_encode( $f ) );
	$t = listed_topics();
	check( "$who: /topics/ lists no wall post", array() === array_intersect( $t, $wall_topics ), json_encode( array_values( array_intersect( $t, $wall_topics ) ) ) );
	$v = array(); if ( function_exists( 'bbp_view_query' ) && bbp_view_query( 'popular' ) ) { while ( bbp_topics() ) { bbp_the_topic(); $v[] = (int) bbp_get_topic_id(); } }
	check( "$who: the \"popular\" view lists no wall post", array() === array_intersect( $v, $wall_topics ) );
	$s = search_hits( $probe_word );
	check( "$who: forum search for \"$probe_word\" finds no wall, wall post or wall reply", array() === array_intersect( $s, array_merge( $walls, $wall_topics ) ), json_encode( $s ) );
}

/* ---- who may OPEN a wall: unchanged (trip-mates keep access) ---- */
$owner226 = cb_wall_forum_owner_id( $walls[0] );
foreach ( array( $plain, $both, $h_only, $admin ) as $uid ) {
	$expected = $uid === $owner226 || user_can( $uid, 'moderate' ) || cb_users_share_any_trip( $uid, $owner226 );
	check( "open wall {$walls[0]} as user #$uid: allowed only for owner, admin or a trip-mate (unchanged)", $expected === user_can( $uid, 'read_forum', $walls[0] ) );
}
wp_set_current_user( $both );
check( 'a trip-mate can still open a trip-mate\'s wall post', $wall_topics && user_can( $both, 'read_topic', $wall_topics[0] ) === cb_users_share_any_trip( $both, cb_wall_forum_owner_id( (int) get_post_meta( $wall_topics[0], '_bbp_forum_id', true ) ) ) );

/* ---- lists that aren't walls are unchanged ---- */
wp_set_current_user( $admin );
$lounge_topics = listed_topics( array( 'post_parent' => $lounge ) );
check( 'a forum\'s own topic list (the Lounge) still works', is_array( $lounge_topics ) );
check( 'a single wall asked for on purpose still lists its own posts', $busy && count( cb_wall_get_posts( $busy ) ) > 0 && count( listed_topics( array( 'post_parent' => $busy ) ) ) === count( cb_wall_get_posts( $busy ) ) );

/* ---- profile timeline, Feed, wall page redirect ---- */
check( 'profile timeline still has every wall post', count( $wall_topics ) === array_sum( array_map( function ( $w ) { return count( get_posts( array( 'post_type' => 'topic', 'post_parent' => $w, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) ) ); }, $walls ) ) );
if ( function_exists( 'cb_feed_recent_topics' ) ) {
	wp_set_current_user( $admin );
	$feed = array_map( function ( $p ) { return (int) $p->ID; }, (array) ( cb_feed_recent_topics( 50 )['wall_posts'] ?? array() ) );
	$flagged = array_values( array_filter( $wall_topics, function ( $id ) { return (bool) get_post_meta( $id, '_cb_show_in_feed', true ); } ) );
	check( 'the Feed still shows wall posts flagged for it', array() === array_diff( $flagged, $feed ), json_encode( array( $flagged, $feed ) ) );
}
check( 'a wall\'s forum page goes to its owner\'s profile', cb_member_profile_url( $owner226 ) === cb_wall_forum_page_redirect_url( $walls[0] ) && '' !== cb_wall_forum_page_redirect_url( $walls[0] ) );
check( 'the Lounge and trip boards are not redirected', '' === cb_wall_forum_page_redirect_url( $lounge ) && '' === cb_wall_forum_page_redirect_url( $b181 ) );
wp_set_current_user( 0 );
check( 'nothing was written', array() === $writes, json_encode( $writes ) );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
