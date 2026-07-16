<?php
/**
 * Checked Bags & Good Vibes — Scrollytelling landing template
 *
 * This file is NOT auto-detected by WordPress as a page template (that would
 * require it to live inside the active theme). Instead it's registered and
 * loaded via the `theme_page_templates` / `template_include` filters in
 * checkedbags-landing.php, which lets it live safely in mu-plugins where a
 * future Kadence theme update can never overwrite or delete it.
 *
 * It deliberately skips get_header() / get_footer() (Kadence's own chrome)
 * and instead calls wp_head() / wp_footer() directly, so this page still
 * plays nicely with Yoast SEO, the admin bar, and other must-run plugin
 * hooks — it just doesn't inherit Kadence's header/nav/footer markup.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cb_base = content_url( 'uploads/checkedbags' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); // Enqueued CSS/fonts (registered in checkedbags-landing.php) print here, plus Yoast SEO meta, etc. ?>
</head>
<body <?php body_class( 'checkedbags-landing' ); ?>>

<!-- ============ HEADER ============ -->
<header class="site-header" id="site-header">
  <div class="header-inner">
    <a href="#top" class="brand">Checked Bags <span class="brand-amp">&amp;</span> Good Vibes</a>

    <button class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="primary-nav">
      <span class="nav-toggle-label">Menu</span>
      <span class="nav-toggle-bars" aria-hidden="true"></span>
    </button>

    <nav class="primary-nav" id="primary-nav" aria-label="Trip phases">
      <ul class="phase-nav-list" id="phase-nav-list">
        <!-- populated by app.js from PHASES config -->
      </ul>
      <a href="#" class="btn btn-ghost btn-signin">Members Sign In</a>
    </nav>
  </div>
</header>

<main id="top">

  <!-- ============ PINNED SCROLLYTELLING SECTION ============ -->
  <section class="scrollytelling" id="scrollytelling" aria-label="Trip story">

    <div class="layer-stack" id="layer-stack">

      <div class="image-layer" data-phase="sunset" style="--fallback-a:#1B3A4B; --fallback-b:#FF6B4A;">
        <div class="image-layer-media" style="--img:url('<?php echo esc_url( "$cb_base/images/image-01-sunset.jpg" ); ?>');"></div>
        <div class="hero-overlay" id="hero-overlay">
          <p class="hero-welcome">Welcome</p>
          <p class="hero-scroll">Scroll <span class="hero-arrow" aria-hidden="true">&#8595;</span></p>
        </div>
      </div>

      <div class="image-layer" data-phase="cabin" style="--fallback-a:#2E7D6E; --fallback-b:#1B3A4B;">
        <div class="image-layer-media" style="--img:url('<?php echo esc_url( "$cb_base/images/image-02-cruise-cabin.jpg" ); ?>');"></div>
      </div>

      <div class="image-layer" data-phase="beach" style="--fallback-a:#F4A94A; --fallback-b:#FF6B4A;">
        <div class="image-layer-media" style="--img:url('<?php echo esc_url( "$cb_base/images/image-03-beach-couple.jpg" ); ?>');"></div>
      </div>

      <div class="image-layer" data-phase="packing" style="--fallback-a:#16232B; --fallback-b:#2E7D6E;">
        <div class="image-layer-media" style="--img:url('<?php echo esc_url( "$cb_base/images/image-04-packing.jpg" ); ?>');"></div>
      </div>

      <div class="image-layer" data-phase="dancing" style="--fallback-a:#E8A94E; --fallback-b:#1B3A4B;">
        <div class="image-layer-media" style="--img:url('<?php echo esc_url( "$cb_base/images/image-05-dancing.jpg" ); ?>');"></div>
      </div>

      <div class="image-layer" data-phase="boarding" style="--fallback-a:#1B3A4B; --fallback-b:#FF6B4A;">
        <div class="image-layer-media" style="--img:url('<?php echo esc_url( "$cb_base/images/image-06-boarding-plane.jpg" ); ?>');"></div>
      </div>

      <!-- CTA layer, crossfades in after final phase -->
      <div class="cta-layer" id="cta-layer">
        <div class="cta-content">
          <p class="cta-eyebrow">Ready when you are</p>
          <h2 class="cta-headline">Join Us</h2>
          <p class="cta-sub">Group trips, planned together.</p>
          <div class="cta-actions">
            <a href="#" class="btn btn-outline-light">Members Login</a>
            <a href="#" class="btn btn-ticket">
              <span class="ticket-notch" aria-hidden="true"></span>
              Request to Join
            </a>
          </div>
        </div>
      </div>

    </div>

    <!-- Boarding-pass style phase tag, text/rotation updated per phase by app.js -->
    <div class="phase-tag" id="phase-tag" aria-hidden="true">
      <span class="phase-tag-gate" id="phase-tag-gate">GATE 01</span>
      <span class="phase-tag-label" id="phase-tag-label">First Light</span>
    </div>

  </section>

  <!-- ============ FOOTER ============ -->
  <footer class="site-footer">
    <div class="footer-inner">
      <div class="footer-brand">
        <p class="footer-brand-name">Checked Bags &amp; Good Vibes</p>
        <p class="footer-tagline">A JourneyWell Global LLC brand.</p>
      </div>

      <nav class="footer-links" aria-label="Footer">
        <a href="#">Privacy</a>
        <a href="#">Terms</a>
        <a href="#">Contact</a>
      </nav>

      <div class="footer-meta">
        <p>&copy; 2026 JourneyWell Global LLC. All rights reserved.</p>
        <p class="footer-stamp">BAGSANDVIBES.COM &middot; EST. 2026</p>
      </div>
    </div>
  </footer>

</main>

<?php wp_footer(); // Enqueued GSAP + app.js (in dependency order) print here, plus admin bar / plugin footer hooks. ?>
</body>
</html>
