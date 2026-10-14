<?php
/*
 * Read-only page renderer: prints the full HTML WordPress would send for PATH to USER (0 = signed out).
 * Usage: CBV_PATH=/forums/ CBV_USER=1 wp [--require=define.php] eval-file render_page.php > out.html
 * Redirects are reported (REDIRECT <url>) instead of followed. Nothing is written.
 */
ini_set( 'error_log', '/tmp/render-err.log' );
$path = getenv( 'CBV_PATH' ) ?: '/';
$uid  = (int) getenv( 'CBV_USER' );
$_SERVER['REQUEST_URI']  = $path;
$_SERVER['HTTP_HOST']    = 'bagsandvibes.com';
$_SERVER['SERVER_NAME']  = 'bagsandvibes.com';
$_SERVER['HTTPS']        = 'on';
$_SERVER['REQUEST_METHOD'] = 'GET';
$q = wp_parse_url( $path, PHP_URL_QUERY );
$_GET = array(); if ( $q ) { parse_str( $q, $_GET ); }
wp_set_current_user( $uid );
add_filter( 'wp_redirect', function ( $l ) { echo "<!-- REDIRECT $l -->\n"; throw new Exception( 'redirect' ); } );
foreach ( array( 'update_post_metadata', 'add_post_metadata', 'update_user_metadata', 'add_user_metadata' ) as $h ) {
	add_filter( $h, function () { return true; }, 1 ); // never write
}
// Never create posts either (2026-10-14): rendering a member's profile used to create a new wall forum each time
// (cb_ensure_user_wall_forum), since the meta that marks the wall was blocked above. wp_insert_post() now refuses.
add_filter( 'wp_insert_post_empty_content', '__return_true', 1 );
foreach ( array( 'delete_post_metadata', 'delete_user_metadata' ) as $h ) {
	add_filter( $h, function () { return true; }, 1 );
}
$GLOBALS['cbv_render_posts_before'] = (int) $GLOBALS['wpdb']->get_var( "SELECT MAX(ID) FROM {$GLOBALS['wpdb']->posts}" );
register_shutdown_function( function () {
	$after = (int) $GLOBALS['wpdb']->get_var( "SELECT MAX(ID) FROM {$GLOBALS['wpdb']->posts}" );
	if ( $after !== $GLOBALS['cbv_render_posts_before'] ) { echo "\n<!-- RENDER WROTE A POST: max ID {$GLOBALS['cbv_render_posts_before']} -> $after -->\n"; }
} );
try {
	wp();
	if ( ! defined( 'WP_USE_THEMES' ) ) { define( 'WP_USE_THEMES', true ); }
	require ABSPATH . WPINC . '/template-loader.php';
} catch ( Exception $e ) {
	// redirect reported above
}
