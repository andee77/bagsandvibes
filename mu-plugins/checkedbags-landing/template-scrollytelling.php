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

$cb_images = array(
	'sunset'   => 'https://bagsandvibes.com/wp-content/uploads/2026/07/1-Sunset-Mountian-Beach-scaled.png',
	'cabin'    => 'https://bagsandvibes.com/wp-content/uploads/2026/07/2-Ship-Porthole-Red-Room-scaled.png',
	'beach'    => 'https://bagsandvibes.com/wp-content/uploads/2026/07/3-Couple-on-island-Beach-scaled.png',
	'packing'  => 'https://bagsandvibes.com/wp-content/uploads/2026/07/4-woman-packing-bag-coral-room-scaled.png',
	'dancing'  => 'https://bagsandvibes.com/wp-content/uploads/2026/07/5-couple-dancing-on-beach-at-night-scaled.png',
	'boarding' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/6-couple-blue-private-jet-scaled.png',
);
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
    <a href="#top" class="brand">Checked Bags <span class="brand-amp">&amp;</span> Good Vibes</a>

    <button class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="primary-nav">
      <span class="nav-toggle-label">Menu</span>
      <span class="nav-toggle-bars" aria-hidden="true"></span>
    </button>

    <nav class="primary-nav" id="primary-nav" aria-label="Trip phases">
      <ul class="phase-nav-list">
        <li><a href="#sunset">First Light</a></li>
        <li><a href="#cabin">Cabin Views</a></li>
        <li><a href="#beach">Shore Leave</a></li>
        <li><a href="#packing">Pack List</a></li>
        <li><a href="#dancing">Golden Hour</a></li>
        <li><a href="#boarding">Final Boarding</a></li>
      </ul>
      <a href="#" class="btn btn-ghost btn-signin">Members Sign In</a>
    </nav>
  </div>
</header>

<main id="top">

  <section class="photo-section" id="sunset" style="--img:url('<?php echo esc_url( $cb_images['sunset'] ); ?>'); --fallback-a:#1B3A4B; --fallback-b:#FF6B4A;">
    <div class="hero-overlay">
      <p class="hero-welcome">Welcome</p>
      <p class="hero-scroll">Scroll <span class="hero-arrow" aria-hidden="true">&#8595;</span></p>
    </div>
    <div class="phase-tag" aria-hidden="true">
      <span class="phase-tag-gate">GATE 01</span>
      <span class="phase-tag-label">First Light</span>
    </div>
  </section>

  <section class="photo-section" id="cabin" style="--img:url('<?php echo esc_url( $cb_images['cabin'] ); ?>'); --fallback-a:#2E7D6E; --fallback-b:#1B3A4B;">
    <div class="phase-tag" aria-hidden="true">
      <span class="phase-tag-gate">GATE 02</span>
      <span class="phase-tag-label">Cabin Views</span>
    </div>
  </section>

  <section class="photo-section" id="beach" style="--img:url('<?php echo esc_url( $cb_images['beach'] ); ?>'); --fallback-a:#F4A94A; --fallback-b:#FF6B4A;">
    <div class="phase-tag" aria-hidden="true">
      <span class="phase-tag-gate">GATE 03</span>
      <span class="phase-tag-label">Shore Leave</span>
    </div>
  </section>

  <section class="photo-section" id="packing" style="--img:url('<?php echo esc_url( $cb_images['packing'] ); ?>'); --fallback-a:#16232B; --fallback-b:#2E7D6E;">
    <div class="phase-tag" aria-hidden="true">
      <span class="phase-tag-gate">GATE 04</span>
      <span class="phase-tag-label">Pack List: Vibes Only</span>
    </div>
  </section>

  <section class="photo-section" id="dancing" style="--img:url('<?php echo esc_url( $cb_images['dancing'] ); ?>'); --fallback-a:#E8A94E; --fallback-b:#1B3A4B;">
    <div class="phase-tag" aria-hidden="true">
      <span class="phase-tag-gate">GATE 05</span>
      <span class="phase-tag-label">Golden Hour Encore</span>
    </div>
  </section>

  <section class="photo-section" id="boarding" style="--img:url('<?php echo esc_url( $cb_images['boarding'] ); ?>'); --fallback-a:#1B3A4B; --fallback-b:#FF6B4A;">
    <div class="phase-tag" aria-hidden="true">
      <span class="phase-tag-gate">GATE 06</span>
      <span class="phase-tag-label">Final Boarding</span>
    </div>
  </section>

  <section class="cta-section">
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
  </section>

</main>

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

<?php wp_footer(); ?>
</body>
</html>
