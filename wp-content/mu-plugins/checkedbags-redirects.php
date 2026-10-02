<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Legacy URL Redirects
 * Description: Permanent (301) redirects from page URLs that have been
 *              renamed, so old bookmarks, old emails and old ?redirect_to=
 *              links keep working.
 *
 *              Why this exists: there is no redirect plugin on the site, the
 *              Yoast edition installed (free) does not create redirects when
 *              a slug changes, and WordPress's built-in "old slug" redirect
 *              is not reliable for Pages. So the renames are listed here by
 *              hand.
 *
 *              How it stays safe: a request is only redirected when it would
 *              otherwise be a 404 AND the page it maps to exists. That means
 *              this file can be deployed BEFORE a page is renamed (the old
 *              URL is still a real page, so nothing happens) and starts
 *              working the instant the rename lands, in either order, and it
 *              can never redirect to a page that isn't there.
 *
 *              Matching ignores case and a trailing slash, and the original
 *              query string is carried over, so
 *              /Gate-11-Travel-Rules?redirect_to=... lands on the new URL
 *              with that query intact. Only GET / HEAD requests are touched.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-redirects.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * old page slug => new page slug.
 *
 * Gate 11 / Gate 12: the two slugs had been left swapped relative to the gate
 * numbers shown on the pages (Travel Rules shows as GATE 12, Vacation
 * Requests as GATE 11).
 */
function cbv_legacy_page_redirects() {
	return apply_filters( 'cbv_legacy_page_redirects', array(
		'gate-11-travel-rules'      => 'gate-12-travel-rules',
		'gate-12-vacation-requests' => 'gate-11-vacation-requests',
	) );
}

/**
 * Pure lookup: given a request URI, the absolute URL to send it to, or ''.
 * Takes the URI as an argument (and checks that the target page exists) so it
 * can be tested without making a real request.
 */
function cbv_legacy_redirect_target( $request_uri ) {
	$parts = wp_parse_url( (string) $request_uri );
	$path  = isset( $parts['path'] ) ? rawurldecode( $parts['path'] ) : '';

	// Strip the site's own sub-directory, if it ever lives in one.
	$home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	$path      = trim( $path, '/' );
	if ( '' !== $home_path && 0 === strpos( $path . '/', $home_path . '/' ) ) {
		$path = trim( substr( $path, strlen( $home_path ) ), '/' );
	}
	$path = strtolower( $path );

	$map = array_change_key_case( cbv_legacy_page_redirects(), CASE_LOWER );
	if ( '' === $path || ! isset( $map[ $path ] ) ) {
		return '';
	}

	$new_slug = $map[ $path ];
	$page     = get_page_by_path( $new_slug, OBJECT, 'page' );
	if ( ! $page || 'publish' !== $page->post_status ) {
		return '';
	}

	$url = get_permalink( $page );
	if ( ! empty( $parts['query'] ) ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $parts['query'];
	}
	return $url;
}

add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return;
	}

	$uri    = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$target = cbv_legacy_redirect_target( $uri );
	if ( '' !== $target ) {
		wp_safe_redirect( $target, 301, 'Checked Bags legacy URL' );
		exit;
	}
}, 1 );
