<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Scrollytelling Landing
 * Description: Registers a custom "Scrollytelling Landing" page template that
 *              renders the vanilla HTML/GSAP scrollytelling homepage, bypassing
 *              Kadence's header/footer for that one page only. Lives in
 *              mu-plugins so it survives theme switches/updates.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-landing.php   <- this file itself
 *   wp-content/mu-plugins/checkedbags-landing/       <- the folder next to it
 *     └── template-scrollytelling.php                <- the actual template
 *
 * mu-plugins only auto-load *.php files directly inside wp-content/mu-plugins/
 * (not subfolders), which is why the loader (this file) lives at the top level
 * and reaches into the checkedbags-landing/ subfolder manually below.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CB_LANDING_TEMPLATE_SLUG', 'checkedbags-scrollytelling.php' );

/**
 * 1. Make "Scrollytelling Landing" selectable in the Page Attributes > Template
 *    dropdown, same as any theme-provided template would appear.
 */
add_filter(
	'theme_page_templates',
	function ( $templates ) {
		$templates[ CB_LANDING_TEMPLATE_SLUG ] = 'Scrollytelling Landing (Checked Bags & Good Vibes)';
		return $templates;
	}
);

/**
 * 2. When a page has that template selected, serve our own template file
 *    instead of anything from the active theme.
 */
add_filter(
	'template_include',
	function ( $template ) {
		if ( is_page() && get_page_template_slug() === CB_LANDING_TEMPLATE_SLUG ) {
			$custom = __DIR__ . '/checkedbags-landing/template-scrollytelling.php';
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		}
		return $template;
	}
);

/**
 * 3. Only on that same page, enqueue: Google Fonts, our own styles.css,
 *    and GSAP core + ScrollTrigger + ScrollToPlugin + app.js in the exact
 *    dependency order the scroll timeline relies on.
 *
 *    NOTE: If SiteGround Speed Optimizer's JS "Combine" or "Defer" settings
 *    are on, they can sometimes reorder scripts and break GSAP's load order.
 *    If the pinned zoom doesn't animate on the live site, check Speed
 *    Optimizer's exclusion list first and exclude the "gsap-*" and
 *    "checkedbags-app" handles.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_page() || get_page_template_slug() !== CB_LANDING_TEMPLATE_SLUG ) {
			return;
		}

		$base = content_url( 'uploads/checkedbags' );

		wp_enqueue_style(
			'checkedbags-fonts',
			'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,500;1,9..144,600&family=Work+Sans:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap',
			array(),
			null
		);

		wp_enqueue_style(
			'checkedbags-styles',
			"$base/css/styles.css",
			array(),
			'1.0.0'
		);

		wp_enqueue_script(
			'gsap-core',
			'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
			array(),
			'3.12.5',
			true
		);
		wp_enqueue_script(
			'gsap-scrolltrigger',
			'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js',
			array( 'gsap-core' ),
			'3.12.5',
			true
		);
		wp_enqueue_script(
			'gsap-scrolltoplugin',
			'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollToPlugin.min.js',
			array( 'gsap-core' ),
			'3.12.5',
			true
		);
		wp_enqueue_script(
			'checkedbags-app',
			"$base/js/app.js",
			array( 'gsap-core', 'gsap-scrolltrigger', 'gsap-scrolltoplugin' ),
			'1.0.0',
			true
		);
	}
);
