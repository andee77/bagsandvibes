<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Custom Page Templates
 * Description: Registers two custom page templates that bypass Kadence's
 *              header/footer: the static scrollytelling landing page, and
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
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CB_LANDING_TEMPLATE_SLUG', 'checkedbags-scrollytelling.php' );
define( 'CB_DASHBOARD_TEMPLATE_SLUG', 'checkedbags-dashboard.php' );
define( 'CB_GATE_TEMPLATE_SLUG', 'checkedbags-gate.php' );

/**
 * 1. Make both templates selectable in the Page Attributes > Template
 *    dropdown, same as any theme-provided template would appear.
 */
add_filter(
	'theme_page_templates',
	function ( $templates ) {
		$templates[ CB_LANDING_TEMPLATE_SLUG ]   = 'Scrollytelling Landing (Checked Bags & Good Vibes)';
		$templates[ CB_DASHBOARD_TEMPLATE_SLUG ] = 'Member Dashboard (Checked Bags & Good Vibes)';
		$templates[ CB_GATE_TEMPLATE_SLUG ]      = 'Gate Page (Checked Bags & Good Vibes)';
		return $templates;
	}
);

/**
 * 2. When a page has one of these templates selected, serve our own
 *    template file instead of anything from the active theme.
 */
add_filter(
	'template_include',
	function ( $template ) {
		if ( is_singular( 'cb_trip' ) ) {
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
	}
);

/**
 * 3. On either of our custom pages, enqueue Google Fonts + our own
 *    styles.css. app.js (mobile nav toggle) only loads on the landing
 *    page, since the dashboard's simpler nav doesn't need it yet.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$is_trip = is_singular( 'cb_trip' );
		if ( ! $is_trip && ! is_page() ) {
			return;
		}

		$slug = $is_trip ? CB_GATE_TEMPLATE_SLUG : get_page_template_slug();
		if ( $slug !== CB_LANDING_TEMPLATE_SLUG && $slug !== CB_DASHBOARD_TEMPLATE_SLUG && $slug !== CB_GATE_TEMPLATE_SLUG ) {
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
			'3.0.0'
		);

		if ( $slug === CB_LANDING_TEMPLATE_SLUG || $slug === CB_GATE_TEMPLATE_SLUG ) {
			wp_enqueue_script(
				'checkedbags-app',
				"$base/js/app.js",
				array(),
				'2.0.0',
				true
			);
		}
	}
);
