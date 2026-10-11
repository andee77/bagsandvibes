<?php
/*
 * Step 9a follow-up tests (sign-in page and forum feeds): every "Please sign in" link and the forum redirect use the site's own sign-in page
 * (/login/, cbv_login_url() in checkedbags-nav.php) instead of wp-login.php, and forum feeds are closed
 * to signed-out visitors (Gate 10, bbp_request at priority 5).
 * Run against a temp COPY of the mu-plugins folder with the Step 9a follow-up files overlaid:
 *   wp --require=/tmp/cbv_t/define.php eval-file test_step9a_login.php
 * Read only: renders shortcodes as a signed-out visitor; no writes of any kind.
 * Set CBV_VERBOSE=1 to print every passing check.
 */
$GLOBALS['n'] = 0; $GLOBALS['fail'] = 0;
function check( $l, $c, $d = '' ) {
	$GLOBALS['n']++;
	if ( ! $c ) { $GLOBALS['fail']++; echo "FAIL: $l $d\n"; }
	elseif ( getenv( 'CBV_VERBOSE' ) ) { echo '  pass ' . str_pad( $GLOBALS['n'], 3, ' ', STR_PAD_LEFT ) . ": $l\n"; }
}
function section( $s ) { if ( getenv( 'CBV_VERBOSE' ) ) { echo "\n=== $s ===\n"; } }

check( 'running against the temp mu-plugins copy', '/tmp/cbv_mu' === WPMU_PLUGIN_DIR );
check( 'Step 9a follow-up code is loaded', function_exists( 'cbv_login_url' ) && function_exists( 'cb_gate10_is_forum_feed_query' ) );

/* ---- A. the sign-in address ---- */
section( 'A. the sign-in address' );
$login = um_get_core_page( 'login' );
check( "the site's sign-in page is Ultimate Member's /login/", '/login/' === wp_parse_url( $login, PHP_URL_PATH ), $login );
check( 'no return page: just /login/', $login === cbv_login_url() && $login === cbv_login_url( '' ) );
$u = cbv_login_url( 'https://bagsandvibes.com/forums/forum/lounge/?x=1&y=2' );
parse_str( (string) wp_parse_url( $u, PHP_URL_QUERY ), $q );
check( 'with a return page: /login/?redirect_to=<that page>, encoded so its own ?x=1&y=2 survive', '/login/' === wp_parse_url( $u, PHP_URL_PATH ) && 'https://bagsandvibes.com/forums/forum/lounge/?x=1&y=2' === ( $q['redirect_to'] ?? '' ) && 1 === count( $q ), $u );
check( 'never the plain wp-login.php', false === strpos( $u . cbv_login_url(), 'wp-login.php' ) );

/* ---- B. every "Please sign in" message, as a signed-out visitor ---- */
section( 'B. sign-in messages' );
wp_set_current_user( 0 );
$pages = array(
	'cb_gate_vacations' => 'gate-07-pre-planned-vacations', 'cb_gate_gallery' => 'gate-08-photo-gallery', 'cb_gate_payments' => 'gate-09-payments',
	'cb_gate_boards' => 'gate-10-discussion-boards', 'cb_gate_rules' => 'gate-12-travel-rules', 'cb_gate_requests' => 'gate-11-vacation-requests',
	'cb_feed' => 'member-feed', 'cb_following_list' => 'following', 'cb_find_members' => 'members', 'cbv_reaccept_terms' => 'reaccept-terms',
);
foreach ( $pages as $sc => $slug ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	$GLOBALS['post'] = $page; setup_postdata( $page ); // so get_permalink() is that page, as when the visitor opens it
	$html = do_shortcode( "[$sc]" );
	wp_reset_postdata();
	preg_match( '/<a href="([^"]+)">sign in<\/a>/', $html, $m );
	$href = html_entity_decode( $m[1] ?? '' );
	parse_str( (string) wp_parse_url( $href, PHP_URL_QUERY ), $q );
	check( "[$sc]: \"sign in\" goes to /login/ and back to its own page", '/login/' === wp_parse_url( $href, PHP_URL_PATH ) && $page && get_permalink( $page ) === ( $q['redirect_to'] ?? '' ), $href );
}
// The member-only trip details message (Gate 07 on a trip page with no public landing): same rule, read from source.
$src07 = file_get_contents( WPMU_PLUGIN_DIR . '/checkedbags-gate07.php' );
check( 'Gate 07 trip details and the "I\'m in" sign-in address use cbv_login_url()', false !== strpos( $src07, "'loginUrl' => cbv_login_url( get_permalink() )," ) && false !== strpos( $src07, "esc_url( cbv_login_url( get_permalink() ) ) . '\">sign in</a> to see trip details." ) );

/* ---- C. nothing left pointing at wp-login.php ---- */
section( 'C. source scan' );
$left = array();
foreach ( glob( WPMU_PLUGIN_DIR . '/checkedbags-*.php' ) as $f ) {
	foreach ( file( $f ) as $i => $line ) {
		if ( preg_match( '/\bwp_login_url\(/', $line ) && ! preg_match( '/^\s*(\*|\/\/)/', $line ) && false === strpos( $line, 'return wp_login_url( $redirect );' ) && false === strpos( $line, "um_get_core_page( 'login' ) : wp_login_url();" ) ) {
			$left[] = basename( $f ) . ':' . ( $i + 1 );
		}
	}
}
check( 'no wp_login_url() left except the helper fallback and the invite email (already /login/)', array() === $left, implode( ', ', $left ) );
$src10 = file_get_contents( WPMU_PLUGIN_DIR . '/checkedbags-gate10.php' );
check( 'the forum redirect uses the site sign-in page', 2 === substr_count( $src10, 'wp_safe_redirect( cbv_login_url(' ) );
$nav = cb_render_logged_out_nav();
check( 'the signed-out menu still links to /login/', false !== strpos( (string) $nav, 'href="' . esc_url( $login ) . '"' ) );

/* ---- D. forum feeds ---- */
section( 'D. forum feeds' );
check( 'a forum, topic or reply feed is a forum feed', cb_gate10_is_forum_feed_query( array( 'feed' => 'feed', 'post_type' => 'forum' ) ) && cb_gate10_is_forum_feed_query( array( 'feed' => 'rss2', 'post_type' => 'topic' ) ) && cb_gate10_is_forum_feed_query( array( 'feed' => 'rss2', 'post_type' => array( 'reply' ) ) ) );
check( 'other feeds and normal forum pages are not', ! cb_gate10_is_forum_feed_query( array( 'feed' => 'rss2', 'post_type' => 'post' ) ) && ! cb_gate10_is_forum_feed_query( array( 'feed' => 'rss2' ) ) && ! cb_gate10_is_forum_feed_query( array( 'post_type' => 'forum' ) ) && ! cb_gate10_is_forum_feed_query( 'junk' ) );
global $wp_filter;
$ours = false; $theirs = has_filter( 'bbp_request', 'bbp_request_feed_trap' );
foreach ( ( $wp_filter['bbp_request']->callbacks[5] ?? array() ) as $cb ) {
	if ( $cb['function'] instanceof Closure && basename( ( new ReflectionFunction( $cb['function'] ) )->getFileName() ) === 'checkedbags-gate10.php' ) { $ours = true; }
}
check( "the feed check runs on bbp_request at priority 5, before bbPress's own feed (priority $theirs)", $ours && 10 === $theirs );
wp_set_current_user( 1 );
$qv = array( 'feed' => 'feed', 'post_type' => 'forum' );
check( 'a signed-in member reaches the forum feed check without being sent away', false === cb_gate10_guest_must_sign_in( cb_gate10_is_forum_feed_query( $qv ), is_user_logged_in() ) );
wp_set_current_user( 0 );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
