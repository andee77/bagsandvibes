<?php
/**
 * Checked Bags & Good Vibes — Gate Page shell
 *
 * Shared no-chrome template for Gate 07–12. Bypasses Kadence's own
 * header/footer entirely (same approach as the landing page and dashboard),
 * giving these pages the same dark sticky header — logo only, no text
 * wordmark — with member-relevant nav instead of Kadence's auto-generated
 * page list.
 *
 * The page's own content (each Gate's shortcode, e.g. [cb_gate_vacations])
 * still renders normally via the_content() below — only the surrounding
 * chrome changes. Body background is intentionally left at Kadence's own
 * light theme (not overridden dark), since the existing Gate content cards
 * (.trip-card, .payment-card, .board-row, etc.) were built for a light
 * page background — do not add a dark body override here.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gate_nav = array(
	array( 'label' => 'Gate 07', 'title' => 'Pre-Planned Vacations', 'url' => 'https://bagsandvibes.com/gate-07-pre-planned-vacations/' ),
	array( 'label' => 'Gate 08', 'title' => 'Photo Gallery',          'url' => 'https://bagsandvibes.com/gate-08-photo-gallery/' ),
	array( 'label' => 'Gate 09', 'title' => 'Payments',                'url' => 'https://bagsandvibes.com/gate-09-payments/' ),
	array( 'label' => 'Gate 10', 'title' => 'Discussion Boards',       'url' => 'https://bagsandvibes.com/gate-10-discussion-boards/' ),
	array( 'label' => 'Gate 11', 'title' => 'Travel Rules',            'url' => 'https://bagsandvibes.com/gate-11-travel-rules/' ),
	array( 'label' => 'Gate 12', 'title' => 'Vacation Requests',       'url' => 'https://bagsandvibes.com/gate-12-vacation-requests/' ),
);
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'checkedbags-gate' ); ?>>

<header class="site-header is-solid" id="site-header">
  <div class="header-inner">
    <a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="brand brand-logo-only" aria-label="Dashboard">
      <?php the_custom_logo(); ?>
    </a>

    <button class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="primary-nav">
      <span class="nav-toggle-label">Menu</span>
      <span class="nav-toggle-bars" aria-hidden="true"></span>
    </button>

    <nav class="primary-nav" id="primary-nav" aria-label="Member navigation">
      <ul class="gate-nav-list">
        <li><a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>">Dashboard</a></li>
        <li><a href="https://bagsandvibes.com/account/">Account</a></li>
        <li><a href="https://bagsandvibes.com/logout/">Logout</a></li>
        <?php foreach ( $gate_nav as $g ) : ?>
          <li><a href="<?php echo esc_url( $g['url'] ); ?>" title="<?php echo esc_attr( $g['title'] ); ?>"><?php echo esc_html( $g['label'] ); ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>
</header>

<main class="gate-main">
	<?php
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			?>
			<h1 class="gate-page-title"><?php the_title(); ?></h1>
			<div class="gate-page-content"><?php the_content(); ?></div>
			<?php
		endwhile;
	endif;
	?>
</main>

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
