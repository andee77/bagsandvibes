<?php
/**
 * Checked Bags & Good Vibes — Member Dashboard shell
 *
 * Only visible to logged-in members; logged-out visitors get redirected to
 * the login page. This is a shell for now — each gate card links to "#"
 * until that module actually gets built. Continues the "GATE" numbering
 * from the landing page's 6 scrollytelling phases (GATE 01-06), picking up
 * at GATE 07 for the member area.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	wp_redirect( 'https://bagsandvibes.com/login/' );
	exit;
}

$current_user = wp_get_current_user();
$display_name = $current_user->display_name ? $current_user->display_name : $current_user->user_login;

$gates = array(
	array(
		'number' => 'GATE 07',
		'title'  => 'All Planned Vacations',
		'desc'   => "See every trip your crew has on the books, past and upcoming.",
		'url'    => 'https://bagsandvibes.com/gate-07-pre-planned-vacations/',
	),
	array(
		'number' => 'GATE 08',
		'title'  => 'Photo Gallery',
		'desc'   => 'Shared photos from every trip, all in one place.',
		'url'    => '#',
	),
	array(
		'number' => 'GATE 09',
		'title'  => 'Payment Section',
		'desc'   => "Track deposits, balances, and who's paid what.",
		'url'    => 'https://bagsandvibes.com/gate-09-payments/',
	),
	array(
		'number' => 'GATE 10',
		'title'  => 'Discussion Boards',
		'desc'   => 'Talk logistics, split rooms, and settle itinerary debates.',
		'url'    => 'https://bagsandvibes.com/gate-10-discussion-boards/',
	),
	array(
		'number' => 'GATE 11',
		'title'  => 'Travel Rules',
		'desc'   => 'The house rules for group trips — read before you pack.',
		'url'    => '#',
	),
	array(
		'number' => 'GATE 12',
		'title'  => 'Vacation Request',
		'desc'   => 'Pitch a new destination or start your own trip.',
		'url'    => '#',
	),
);
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'checkedbags-dashboard' ); ?>>

<header class="site-header" id="site-header">
  <div class="header-inner">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand">Checked Bags <span class="brand-amp">&amp;</span> Good Vibes</a>
    <nav class="member-nav" aria-label="Member navigation">
      <a href="https://bagsandvibes.com/account/" class="btn btn-ghost">My Account</a>
      <a href="https://bagsandvibes.com/logout/" class="btn btn-ghost">Log Out</a>
    </nav>
  </div>
</header>

<main class="dashboard-main">

  <section class="dashboard-hero">
    <p class="dashboard-hero-eyebrow">Welcome back</p>
    <h1 class="dashboard-hero-name"><?php echo esc_html( $display_name ); ?></h1>
    <p class="dashboard-hero-sub">Your crew, your calendar, your next great escape.</p>
  </section>

  <section class="gate-grid">
    <?php foreach ( $gates as $gate ) : ?>
    <a class="gate-card" href="<?php echo esc_url( $gate['url'] ); ?>">
      <span class="gate-card-number"><?php echo esc_html( $gate['number'] ); ?></span>
      <span class="gate-card-title"><?php echo esc_html( $gate['title'] ); ?></span>
      <span class="gate-card-desc"><?php echo esc_html( $gate['desc'] ); ?></span>
    </a>
    <?php endforeach; ?>
  </section>

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
