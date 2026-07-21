<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Gate Content Assets
 * Description: Loads styles.css and the brand Google Fonts on any front-end
 *              page that renders Gate content (Gate 07/10 listing pages, or
 *              a single cb_trip page) — these live inside Kadence's normal
 *              template, unlike the no-chrome landing/dashboard templates
 *              which already enqueue this stylesheet themselves.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-gate-assets.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add new shortcode names here (e.g. 'cb_gate_gallery', 'cb_gate_payments')
 * as later gates are built, so this same file keeps working for all of them.
 */
function cb_is_gate_content_page() {
	if ( is_singular( 'cb_trip' ) ) {
		return true;
	}

	if ( is_page() ) {
		$post = get_post();
		if ( $post && (
			has_shortcode( $post->post_content, 'cb_gate_vacations' ) ||
			has_shortcode( $post->post_content, 'cb_gate_boards' ) ||
			has_shortcode( $post->post_content, 'cb_gate_payments' )
		) ) {
			return true;
		}
	}

	return false;
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! cb_is_gate_content_page() ) {
		return;
	}

	$base = content_url( 'uploads/checkedbags' );

	wp_enqueue_style(
		'cb-gate-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;1,9..144,500;1,9..144,600&family=Work+Sans:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'cb-gate-styles',
		"$base/css/styles.css",
		array(),
		'1.0.1'
	);
} );

add_filter( 'body_class', function ( $classes ) {
	if ( cb_is_gate_content_page() ) {
		$classes[] = 'cb-gate-content';
	}
	return $classes;
} );
