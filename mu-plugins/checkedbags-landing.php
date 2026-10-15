<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Custom Page Templates
 * Description: Registers custom page templates that bypass Kadence's
 *              header/footer: the static scrollytelling landing page and
 *              the member dashboard shell. Lives in mu-plugins so it
 *              survives theme switches/updates.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-landing.php   <- this file itself
 *   wp-content/mu-plugins/checkedbags-landing/       <- the folder next to it
 *     ├── template-scrollytelling.php                <- landing page template
 *     └── template-dashboard.php                     <- member dashboard template
 *
 * mu-plugins only auto-load *.php files directly inside wp-content/mu-plugins/
 * (not subfolders), which is why the loader (this file) lives at the top level
 * and reaches into the checkedbags-landing/ subfolder manually below.
 *
 * Member profile URLs used to live at /members/{user_nicename}/, a custom
 * rewrite rule resolving to our own "Member Profile" page/template. That
 * page has been retired in favor of consolidating all profile functionality
 * onto Ultimate Member's native /user/{user_nicename}/ URL (see
 * checkedbags-member-profile-hooks.php) -- section 4 below now keeps only
 * the rewrite rule (so old /members/ links still resolve to a real request
 * rather than a plain 404) and 301-redirects them to the new URL. No
 * flush_rewrite_rules() precedent existed anywhere in this codebase before
 * this rule was first added -- a manual `wp rewrite flush` was run once at
 * that time; the rule itself is unchanged here, so no re-flush is needed.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CB_LANDING_TEMPLATE_SLUG', 'checkedbags-scrollytelling.php' );
define( 'CB_DASHBOARD_TEMPLATE_SLUG', 'checkedbags-dashboard.php' );
define( 'CB_GATE_TEMPLATE_SLUG', 'checkedbags-gate.php' );

/**
 * 1. Make all templates selectable in the Page Attributes > Template
 *    dropdown, same as any theme-provided template would appear.
 */
add_filter(
	'theme_page_templates',
	function ( $templates ) {
		$templates[ CB_LANDING_TEMPLATE_SLUG ]         = 'Scrollytelling Landing (Checked Bags & Good Vibes)';
		$templates[ CB_DASHBOARD_TEMPLATE_SLUG ]       = 'My Passport (member home, Checked Bags & Good Vibes)';
		$templates[ CB_GATE_TEMPLATE_SLUG ]            = 'Gate Page (Checked Bags & Good Vibes)';
		return $templates;
	}
);

/**
 * 2. When a page has one of these templates selected, serve our own
 *    template file instead of anything from the active theme.
 */
/**
 * Default brand-new pages to the "Gate Page" template automatically,
 * so future pages get our dark chrome without someone remembering to
 * assign it manually. Only fires once, on the page's first save (when it
 * has no template meta AND no revisions yet) — so deliberately choosing
 * Kadence's "Default Template" afterward is never silently overridden.
 */
add_action( 'save_post_page', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	$already_has_template = get_post_meta( $post_id, '_wp_page_template', true );
	$has_prior_revisions   = ! empty( wp_get_post_revisions( $post_id ) );
	if ( ! $already_has_template && ! $has_prior_revisions ) {
		update_post_meta( $post_id, '_wp_page_template', CB_GATE_TEMPLATE_SLUG );
	}
}, 10, 2 );

/**
 * Every bbPress page: the forum list, each forum, topics and replies, the
 * topic list, forum search, bbPress views, tag and user pages (owner,
 * 2026-10-13: all forum pages use the CBGV member-area shell and skin).
 */
function cbv_is_forum_page() {
	return function_exists( 'is_bbpress' ) && is_bbpress();
}

/**
 * bbPress's empty-state messages in CBGV words (owner, 2026-10-13), through
 * the translation filter rather than copied templates.
 */
function cbv_forum_empty_text( $text ) {
	$map = array(
		'Oh, bother! No topics were found here.'         => 'No conversations yet. Be the first to start one!',
		'Oh, bother! No forums were found here.'         => 'No conversations yet. Be the first to start one!',
		'Oh, bother! No replies were found here.'        => 'No conversations yet. Be the first to start one!',
		'Oh, bother! No search results were found here.' => 'No matching conversations found.',
	);
	return $map[ $text ] ?? null;
}

add_filter( 'gettext_bbpress', function ( $translation, $text ) {
	$ours = cbv_forum_empty_text( $text );
	return null !== $ours ? $ours : $translation;
}, 10, 2 );

// An empty forum shows one notice (the wording above), not bbPress's extra
// "This forum is empty." summary box as well.
add_filter( 'bbp_get_single_forum_description', function ( $html, $r ) {
	$forum_id = function_exists( 'bbp_get_forum_id' ) ? bbp_get_forum_id( $r['forum_id'] ?? 0 ) : 0;
	return ( $forum_id && 0 === (int) bbp_get_forum_topic_count( $forum_id, true, true ) ) ? '' : $html;
}, 10, 2 );

// No stray "|" before Subscribe on topic pages.
add_filter( 'bbp_after_get_topic_subscribe_link_parse_args', function ( $r ) {
	$r['before'] = '';
	return $r;
} );

// Topic tags are not used: no "Tags" field on new topics and replies (owner, 2026-10-13).
add_filter( 'bbp_allow_topic_tags', function ( $allowed ) {
	return is_admin() ? $allowed : false;
} );

/**
 * Text pages (owner, 2026-10-14): Privacy, Terms and Contact use the gate
 * shell with a readable text column instead of the home page's landing
 * template, which printed their text edge to edge under the header. The home
 * page keeps its landing design.
 */
function cbv_text_page_slugs() {
	return array( 'privacy-policy', 'terms-of-service', 'contact', 'master-traveler-agreement' );
}

/** Ultimate Member form pages that get the CBGV form skin (owner, 2026-10-14). */
function cbv_um_form_page_slugs() {
	return array( 'login', 'register', 'password-reset' );
}

add_filter( 'body_class', function ( $classes ) {
	if ( is_page( cbv_text_page_slugs() ) ) {
		$classes[] = 'cbv-text-page';
	}
	if ( is_page( cbv_um_form_page_slugs() ) ) {
		$classes[] = 'cbv-um-page';
	}
	return $classes;
} );

add_filter(
	'template_include',
	function ( $template ) {
		if ( is_singular( array( 'cb_trip', 'forum', 'topic', 'reply' ) ) || cbv_is_forum_page() || is_page( cbv_text_page_slugs() ) ) {
			$custom = __DIR__ . '/checkedbags-landing/template-gate.php';
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		}
		if ( ! is_page() ) {
			return $template;
		}

		$slug = get_page_template_slug();

		if ( $slug === CB_LANDING_TEMPLATE_SLUG ) {
			$custom = __DIR__ . '/checkedbags-landing/template-scrollytelling.php';
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		}

		if ( $slug === CB_DASHBOARD_TEMPLATE_SLUG ) {
			$custom = __DIR__ . '/checkedbags-landing/template-dashboard.php';
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		}

		if ( $slug === CB_GATE_TEMPLATE_SLUG ) {
			$custom = __DIR__ . '/checkedbags-landing/template-gate.php';
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		}

		return $template;
	},
	20 // must run after bbPress's own template_include filter (priority 10),
	   // so ours wins for forum/topic/reply pages instead of bbPress's
	   // theme-compat layer forcing Kadence's default header/footer back in
);

/**
 * 3. On any of our custom pages, enqueue Google Fonts + our own styles.css.
 *    app.js (mobile nav toggle + nav dropdown behavior, see
 *    checkedbags-nav.php) loads everywhere the shared primary nav renders --
 *    landing, dashboard, and Gate pages alike, now that the dashboard uses
 *    the same nav markup instead of its own simpler one.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$is_trip = is_singular( array( 'cb_trip', 'forum', 'topic', 'reply' ) ) || cbv_is_forum_page();
		if ( ! $is_trip && ! is_page() ) {
			return;
		}

		// Text pages use the gate shell whatever page template they have (the Master Traveler Agreement page has none).
		$slug = ( $is_trip || is_page( cbv_text_page_slugs() ) ) ? CB_GATE_TEMPLATE_SLUG : get_page_template_slug();
		$known_slugs = array( CB_LANDING_TEMPLATE_SLUG, CB_DASHBOARD_TEMPLATE_SLUG, CB_GATE_TEMPLATE_SLUG );
		if ( ! in_array( $slug, $known_slugs, true ) ) {
			return;
		}

		$base = content_url( 'uploads/checkedbags' );

		wp_enqueue_style(
			'checkedbags-fonts',
			'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,500;1,9..144,600&family=Work+Sans:wght@400;500;600&family=Space+Mono:wght@400;700&family=Dancing+Script:wght@700&display=swap',
			array(),
			null
		);

		$styles_path = WP_CONTENT_DIR . '/uploads/checkedbags/css/styles.css';
		$styles_ver  = file_exists( $styles_path ) ? filemtime( $styles_path ) : '3.0.0';

		wp_enqueue_style(
			'checkedbags-styles',
			"$base/css/styles.css",
			array(),
			$styles_ver
		);

		if ( $slug === CB_LANDING_TEMPLATE_SLUG || $slug === CB_GATE_TEMPLATE_SLUG || $slug === CB_DASHBOARD_TEMPLATE_SLUG ) {
			$app_js_path = WP_CONTENT_DIR . '/uploads/checkedbags/js/app.js';
			$app_js_ver  = file_exists( $app_js_path ) ? filemtime( $app_js_path ) : '2.0.0';

			wp_enqueue_script(
				'checkedbags-app',
				"$base/js/app.js",
				array(),
				$app_js_ver,
				true
			);
		}
	}
);

/**
 * 3b. Forum pages: Kadence's own bbPress stylesheet (its "kadence-bbpress",
 *     added at wp_enqueue_scripts priority 60) is the old theme look and its
 *     selectors outrank the CBGV forum skin in styles.css, so it is not loaded
 *     on forum pages; the skin does the layout too.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( cbv_is_forum_page() ) {
			wp_dequeue_style( 'kadence-bbpress' );
		}
	},
	99
);

/**
 * 4. Member Profile URLs -- /members/{user_nicename}/ -- the one rewrite
 *    rule in this codebase. It used to resolve to our own "Member Profile"
 *    page/template; that page is retired now that the same functionality
 *    lives on Ultimate Member's native /user/{user_nicename}/ URL instead
 *    (checkedbags-member-profile-hooks.php). The rewrite rule itself is
 *    left exactly as it was (so this stays a no-op change to anything
 *    already indexed/bookmarked, and no further `wp rewrite flush` is
 *    needed) -- only its target changed, to just carry the nicename in as
 *    a query var with no page attached, and a template_redirect below
 *    301-redirects to the new URL before any template would render.
 */
add_action( 'init', function () {
	add_rewrite_rule(
		'^members/([^/]+)/?$',
		'index.php?cb_member_nicename=$matches[1]',
		'top'
	);
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'cb_member_nicename';
	return $vars;
} );

add_action( 'template_redirect', function () {
	$nicename = get_query_var( 'cb_member_nicename' );
	if ( ! $nicename ) {
		return;
	}

	$target = home_url( '/user/' . sanitize_title( $nicename ) . '/' );
	if ( function_exists( 'cb_member_profile_url' ) ) {
		$user = get_user_by( 'slug', $nicename );
		if ( $user ) {
			$target = cb_member_profile_url( $user->ID );
		}
	}

	wp_safe_redirect( $target, 301 );
	exit;
}, 5 );
