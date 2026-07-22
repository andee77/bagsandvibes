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
    <?php
    $cb_logo_id  = get_theme_mod( 'custom_logo' );
    $cb_logo_url = $cb_logo_id ? wp_get_attachment_image_url( $cb_logo_id, 'medium' ) : '';
    ?>
    <a href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>" class="brand brand-logo-only" aria-label="Dashboard">
      <?php if ( $cb_logo_url ) : ?>
        <img src="<?php echo esc_url( $cb_logo_url ); ?>" alt="Checked Bags &amp; Good Vibes" class="brand-logo-img">
      <?php endif; ?>
    </a>

    <button class="nav-toggle" id="nav-toggle" aria-expanded="false" aria-controls="primary-nav">
      <span class="nav-toggle-label">Menu</span>
      <span class="nav-toggle-bars" aria-hidden="true"></span>
    </button>

    <nav class="primary-nav" id="primary-nav" aria-label="Member navigation">
      <ul class="gate-nav-list">
        <li><a href="https://bagsandvibes.com/member-feed/">Feed</a></li>
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

<?php
$cb_gate_page_config = array(
	110 => array( 'number' => 'GATE 07', 'bg_style' => 'frame', 'bg_url' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/Mountian-and-River-scaled.jpg' ),
	132 => array( 'number' => 'GATE 09', 'bg_style' => 'frame', 'bg_url' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/1-Sunset-Mountian-Beach-scaled.png' ),
	114 => array( 'number' => 'GATE 10', 'bg_style' => 'frame', 'bg_url' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/friends-at-rooftop-party-scaled.jpg' ),
	134 => array( 'number' => 'GATE 08', 'bg_style' => 'left-video', 'bg_url' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/now-loading-JET.mp4' ),
	136 => array( 'number' => 'GATE 11', 'bg_style' => 'left-video', 'bg_url' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/ship-view-from-above.mp4' ),
	158 => array( 'number' => null, 'bg_style' => 'full', 'bg_url' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/red-mountians.avif' ),
	139 => array(
		'number'   => 'GATE 12',
		'bg_style' => 'scatter',
		'photos'   => array(
			'https://bagsandvibes.com/wp-content/uploads/2026/07/Running-thru-water.avif',
			'https://bagsandvibes.com/wp-content/uploads/2026/07/walking-cliff-side-scaled.jpg',
			'https://bagsandvibes.com/wp-content/uploads/2026/07/Diver-and-Octopus.jpg',
			'https://bagsandvibes.com/wp-content/uploads/2026/07/Mountians-and-soak.avif',
			'https://bagsandvibes.com/wp-content/uploads/2026/07/1-Sunset-Mountian-Beach-scaled.png',
		),
		'videos'   => array(
			'https://bagsandvibes.com/wp-content/uploads/2026/07/ship-view-from-above.mp4',
			'https://bagsandvibes.com/wp-content/uploads/2026/07/dance-on-the-beach.mp4',
		),
	),
);
$cb_current_gate = isset( $cb_gate_page_config[ get_the_ID() ] ) ? $cb_gate_page_config[ get_the_ID() ] : null;
?>

<?php if ( $cb_current_gate && $cb_current_gate['bg_style'] === 'frame' ) : ?>
	<div class="gate-bg-frame" style="background-image:url('<?php echo esc_url( $cb_current_gate['bg_url'] ); ?>');"></div>
<?php elseif ( $cb_current_gate && $cb_current_gate['bg_style'] === 'full' ) : ?>
	<div class="gate-bg-full" style="background-image:url('<?php echo esc_url( $cb_current_gate['bg_url'] ); ?>');"></div>
<?php elseif ( $cb_current_gate && $cb_current_gate['bg_style'] === 'left-video' ) : ?>
	<div class="gate-bg-left-video">
		<video autoplay muted loop playsinline>
			<source src="<?php echo esc_url( $cb_current_gate['bg_url'] ); ?>" type="video/mp4">
		</video>
	</div>
<?php endif; ?>

<main class="gate-main <?php echo $cb_current_gate ? 'has-gate-bg gate-bg-mode-' . esc_attr( $cb_current_gate['bg_style'] ) : ''; ?>">
	<?php
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			?>
			<div class="gate-ribbon">
				<?php if ( $cb_current_gate && $cb_current_gate['number'] ) : ?>
					<span class="gate-ribbon-number"><?php echo esc_html( $cb_current_gate['number'] ); ?></span>
					<span class="gate-ribbon-divider" aria-hidden="true"></span>
				<?php endif; ?>
				<span class="gate-ribbon-title"><?php the_title(); ?></span>
			</div>
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
