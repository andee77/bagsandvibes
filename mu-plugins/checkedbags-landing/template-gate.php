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

    <?php echo function_exists( 'cb_render_primary_nav' ) ? cb_render_primary_nav() : ''; ?>
  </div>
</header>

<?php
$cb_gate_page_config = array(
	110 => array( 'number' => 'GATE 07', 'bg_style' => 'frame', 'bg_url' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/Mountian-and-River-scaled.jpg' ),
	132 => array( 'number' => 'GATE 09', 'bg_style' => 'frame', 'bg_url' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/1-Sunset-Mountian-Beach-scaled.png' ),
	114 => array( 'number' => 'GATE 10', 'bg_style' => 'frame', 'bg_url' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/friends-at-rooftop-party-scaled.jpg' ),
	134 => array( 'number' => 'GATE 08', 'bg_style' => 'left-video', 'bg_url' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/now-loading-JET.mp4' ),
	// Post 136 = Travel Rules (gate-11-travel-rules); number swapped to
	// GATE 12 -- see checkedbags-nav.php's $gate_nav comment.
	136 => array( 'number' => 'GATE 12', 'bg_style' => 'left-video', 'bg_url' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/ship-view-from-above.mp4' ),
	158 => array( 'number' => null, 'bg_style' => 'full', 'bg_url' => 'https://bagsandvibes.com/wp-content/uploads/2026/07/red-mountians.avif' ),
	// Post 139 = Vacation Requests (gate-12-vacation-requests); number
	// swapped to GATE 11 -- see checkedbags-nav.php's $gate_nav comment.
	139 => array(
		'number'   => 'GATE 11',
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
<?php elseif ( $cb_current_gate && $cb_current_gate['bg_style'] === 'scatter' ) : ?>
	<div class="gate-scatter-field" aria-hidden="true">
		<?php foreach ( $cb_current_gate['photos'] as $i => $photo_url ) : ?>
			<div class="gate-scatter-item gate-scatter-photo scatter-pos-<?php echo esc_attr( $i + 1 ); ?>" style="background-image:url('<?php echo esc_url( $photo_url ); ?>');"></div>
		<?php endforeach; ?>
		<?php foreach ( $cb_current_gate['videos'] as $j => $video_url ) : ?>
			<div class="gate-scatter-item gate-scatter-video scatter-pos-video-<?php echo esc_attr( $j + 1 ); ?>">
				<video autoplay muted loop playsinline>
					<source src="<?php echo esc_url( $video_url ); ?>" type="video/mp4">
				</video>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<main class="gate-main <?php echo $cb_current_gate ? 'has-gate-bg gate-bg-mode-' . esc_attr( $cb_current_gate['bg_style'] ) : ''; ?>">
	<?php
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			?>
			<?php
			// The Member Profile page ('user', UM's own core page) also uses
			// this shared shell -- it got assigned the Gate Page template by
			// checkedbags-landing.php's auto-default-new-pages logic when UM
			// first created it, not a deliberate per-page choice. Harmless
			// everywhere else this template is used (Gate 07-12, forums,
			// trip pages -- the_title() there is the actual page/trip name,
			// meant to show), but on the profile page the_title() renders
			// the member's own display name, duplicating the name UM's own
			// um_profile_header already renders below via .um-name --
			// confirmed live via DOM inspection (two literal "Andee P" text
			// nodes: one here in .gate-ribbon-title, one in UM's .um-name).
			// Suppressed for this one page only; every other consumer of
			// this shared template is untouched.
			if ( ! is_page( 'user' ) ) :
			?>
			<div class="gate-ribbon">
				<?php if ( $cb_current_gate && $cb_current_gate['number'] ) : ?>
					<span class="gate-ribbon-number"><?php echo esc_html( $cb_current_gate['number'] ); ?></span>
					<span class="gate-ribbon-divider" aria-hidden="true"></span>
				<?php endif; ?>
				<span class="gate-ribbon-title"><?php the_title(); ?></span>
			</div>
			<?php endif; ?>
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
      <a href="https://bagsandvibes.com/privacy-policy/">Privacy</a>
      <a href="https://bagsandvibes.com/terms-of-service/">Terms</a>
      <a href="https://bagsandvibes.com/contact/">Contact</a>
      <a href="https://bagsandvibes.com/payment-disclaimer/">Payment Disclaimer</a>
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
