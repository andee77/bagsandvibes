<?php
/**
 * Checked Bags & Good Vibes — Static landing template
 *
 * Simplified from the original GSAP scrollytelling version: each photo is
 * now its own full-height section in normal page flow, no pin/zoom/crossfade
 * JS at all. Nav links are plain anchor jumps (native smooth-scroll via CSS).
 *
 * Registered the same way as before via checkedbags-landing.php's
 * theme_page_templates / template_include filters — no need to re-select
 * the template on the Page in wp-admin, it's still the same slug.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'checkedbags-landing' ); ?>>

<!-- ============ HEADER ============ -->
<header class="site-header" id="site-header">
  <div class="header-inner">
    <?php
    $cb_logo_id  = get_theme_mod( 'custom_logo' );
    $cb_logo_url = $cb_logo_id ? wp_get_attachment_image_url( $cb_logo_id, 'medium' ) : '';
    ?>
    <a href="#top" class="brand brand-logo-only" aria-label="Checked Bags & Good Vibes">
      <?php if ( $cb_logo_url ) : ?>
        <img src="<?php echo esc_url( $cb_logo_url ); ?>" alt="Checked Bags &amp; Good Vibes" class="brand-logo-img">
      <?php endif; ?>
    </a>

    <button class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="primary-nav">
      <span class="nav-toggle-label">Menu</span>
      <span class="nav-toggle-bars" aria-hidden="true"></span>
    </button>

    <nav class="primary-nav" id="primary-nav" aria-label="<?php echo is_front_page() ? 'Trip phases' : 'Site navigation'; ?>">
      <?php if ( is_front_page() ) : ?>
      <ul class="phase-nav-list">
        <li><a href="#sunset">First Light</a></li>
        <li><a href="#cabin">Cabin Views</a></li>
        <li><a href="#beach">Shore Leave</a></li>
        <li><a href="#packing">Pack List</a></li>
        <li><a href="#dancing">Golden Hour</a></li>
        <li><a href="#boarding">Final Boarding</a></li>
      </ul>
      <?php else : ?>
      <ul class="phase-nav-list">
        <li><a href="https://bagsandvibes.com/">Home</a></li>
        <li><a href="https://bagsandvibes.com/privacy-policy/">Privacy</a></li>
        <li><a href="https://bagsandvibes.com/terms-of-service/">Terms</a></li>
        <li><a href="https://bagsandvibes.com/contact/">Contact</a></li>
      </ul>
      <?php endif; ?>
      <a href="https://bagsandvibes.com/login/" class="btn btn-ghost btn-signin">Members Sign In</a>
    </nav>
  </div>
</header>

<main id="top">
<?php
if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
endif;
?>
</main>

<!-- ============ FOOTER ============ -->
<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <p class="footer-brand-name">Checked Bags &amp; Good Vibes</p>
      <p class="footer-tagline">A JourneyWell Global LLC brand.</p>
    </div>

    <nav class="footer-links" aria-label="Footer">
      <a href="https://bagsandvibes.com/privacy-policy/">Privacy</a>
      <a href="https://bagsandvibes.com/terms-of-service/">Terms</a>
      <a href="https://bagsandvibes.com/contact/">Contact</a>
    </nav>

    <div class="footer-meta">
      <p>&copy; 2026 JourneyWell Global LLC. All rights reserved.</p>
      <p class="footer-stamp">BAGSANDVIBES.COM &middot; EST. 2026</p>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
