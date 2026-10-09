<?php
/**
 * Checked Bags & Good Vibes — New Public Trip Landing shell
 *
 * Own header/footer, no Kadence chrome and no member nav, for the redesigned
 * public trip landing page (design reference:
 * docs/design/event-page-design-reference.html). Served ONLY when
 * checkedbags-lp-core.php's cbv_lp_trip_view() says 'new' (public rollout,
 * per trip) or 'new_preview' (admin ?preview=new) -- everyone else still
 * gets the Gate shell (template-gate.php).
 *
 * The body is the_content(): gate07's filter hands back the new renderer for
 * this view, so there is exactly one place deciding what a viewer sees.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cbv_lp_trip_id  = get_queried_object_id();
$cbv_lp_trip_url = get_permalink( $cbv_lp_trip_id );

if ( is_user_logged_in() ) {
	$cbv_lp_signin_url   = home_url( '/dashboard/' );
	$cbv_lp_signin_label = 'Dashboard';
} else {
	$cbv_lp_signin_url   = function_exists( 'um_get_core_page' ) ? um_get_core_page( 'login' ) : wp_login_url( $cbv_lp_trip_url );
	$cbv_lp_signin_label = 'Members Sign In';
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'cbv-lp' ); ?>>
<?php wp_body_open(); ?>

<header class="cbv-lp-header">
	<div class="cbv-lp-header-inner">
		<a class="cbv-lp-wordmark" href="<?php echo esc_url( home_url( '/' ) ); ?>">Checked Bags <span class="cbv-lp-amp">&amp;</span> Good Vibes</a>
		<nav class="cbv-lp-nav" aria-label="Event sections">
			<a class="cbv-lp-nav-signin" href="<?php echo esc_url( $cbv_lp_signin_url ); ?>"><?php echo esc_html( $cbv_lp_signin_label ); ?></a>
		</nav>
	</div>
</header>

<main class="cbv-lp-main" id="top">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>

<footer class="cbv-lp-footer">
	<div class="cbv-lp-footer-inner">
		<div class="cbv-lp-footer-brand">
			<span class="cbv-lp-footer-name">Checked Bags &amp; Good Vibes</span>
			<span class="cbv-lp-footer-sub">A JourneyWell Global LLC brand &middot; Bookings by an independent travel advisor</span>
		</div>
		<nav class="cbv-lp-footer-links" aria-label="Footer">
			<a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy</a>
			<a href="<?php echo esc_url( home_url( '/terms-of-service/' ) ); ?>">Terms</a>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a>
			<a href="<?php echo esc_url( home_url( '/payment-disclaimer/' ) ); ?>">Payment Disclaimer</a>
		</nav>
		<span class="cbv-lp-footer-url"><?php echo esc_html( preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $cbv_lp_trip_url ) ) ); ?></span>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
