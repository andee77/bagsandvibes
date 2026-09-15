<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — SEO: Login-Gated Page Noindex
 * Description: Yoast SEO audit follow-up. These WordPress Pages render
 *              only a one-line "please sign in" message -- or redirect/fall
 *              through to another page entirely -- for anyone who isn't
 *              already logged in, confirmed live (anonymous, no login) on
 *              every page in this list before it was finalized. That's
 *              also exactly what Google's crawler sees, so there's
 *              genuinely nothing here worth surfacing in search results --
 *              same reasoning already applied to non-public trip landing
 *              pages (checkedbags-trip-landing.php's own wp_robots filter),
 *              just via a plain page-slug list here since these are
 *              ordinary WP Pages, not a custom post type with its own
 *              eligibility meta field to key off of.
 *
 *              Note: "dashboard" redirects anonymous visitors via
 *              wp_redirect()+exit before wp_head ever runs, so this filter
 *              never actually fires for that specific page on an anonymous
 *              request -- included anyway for defensiveness/consistency,
 *              but the real protection there is the redirect itself, not
 *              this tag.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-seo.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'wp_robots', function ( $robots ) {
	$noindex_page_slugs = array(
		// Login-gated -- shows only "please sign in" to an anonymous visitor.
		'following',
		'member-feed',
		'gate-12-vacation-requests',
		'gate-08-photo-gallery',
		'gate-09-payments',
		'gate-10-discussion-boards',
		'gate-07-pre-planned-vacations',
		'members',
		'reaccept-terms',
		'account',
		// No content of its own for an anonymous visitor (redirect or
		// fallthrough to another page).
		'dashboard',
		'logout',
		'user',
	);

	if ( is_page( $noindex_page_slugs ) ) {
		$robots['noindex'] = true;
	}

	return $robots;
} );
