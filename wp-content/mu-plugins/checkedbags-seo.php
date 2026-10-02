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

/**
 * The one list of login-gated / no-content-for-visitors pages, used by both
 * the noindex rule and the Yoast sitemap exclusion below so they can never
 * drift apart.
 *
 * gate-11-vacation-requests and gate-12-travel-rules are the slugs the two
 * pages are being renamed to (they used to be swapped relative to the
 * displayed gate numbers; see checkedbags-redirects.php). Both the new and
 * the old slugs are listed during the changeover; a slug that matches no
 * page is simply skipped.
 */
function cbv_login_gated_page_slugs() {
	return array(
		// Login-gated -- shows only "please sign in" to an anonymous visitor.
		'following',
		'member-feed',
		'gate-12-vacation-requests',
		'gate-11-vacation-requests',
		'gate-08-photo-gallery',
		'gate-09-payments',
		'gate-10-discussion-boards',
		'gate-11-travel-rules',
		'gate-12-travel-rules',
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
}

add_filter( 'wp_robots', function ( $robots ) {
	if ( is_page( cbv_login_gated_page_slugs() ) ) {
		$robots['noindex'] = true;
	}

	return $robots;
} );

/**
 * Keep those same pages out of Yoast's XML sitemap. The noindex above is
 * applied through core's wp_robots filter, which Yoast's own indexable
 * records don't see, so Yoast was still listing every one of them in
 * /page-sitemap.xml: "submitted in the sitemap but marked noindex", which
 * search consoles report as an error. Yoast's documented exclusion filter
 * takes post IDs, so resolve each slug to its page.
 */
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', function ( $ids ) {
	$ids = is_array( $ids ) ? $ids : array();

	// Public pages that still have no business in the sitemap. Left indexable
	// (NOT added to the noindex list above) -- only the sitemap listing goes.
	// Register stays listed on purpose.
	$sitemap_only_slugs = array( 'login', 'password-reset' );

	foreach ( array_merge( cbv_login_gated_page_slugs(), $sitemap_only_slugs ) as $slug ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page ) {
			$ids[] = (int) $page->ID;
		}
	}

	return array_values( array_unique( array_map( 'intval', $ids ) ) );
} );
