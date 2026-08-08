<?php
/**
 * Checked Bags & Good Vibes — Member Profile shell
 *
 * Member Profile + Wall Post, piece 2. Reached via /members/{user_nicename}/
 * (rewrite rule registered in checkedbags-landing.php), resolved to this
 * real "Member Profile" page's template through the same
 * theme_page_templates/template_include mechanism as the Dashboard.
 *
 * Only visible to logged-in members; logged-out visitors get redirected to
 * the login page, same as the Dashboard. Access to the profile's own
 * content (beyond the bare "member not found" state) is gated on
 * current_user_can( 'read_forum', $wall_forum_id ) -- the single source of
 * truth established in checkedbags-member-wall.php (piece 1): true for the
 * profile owner, any moderator, or anyone who shares a trip roster with
 * this member; false otherwise. No wall posting or Feed integration yet --
 * later pieces.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! is_user_logged_in() ) {
	wp_redirect( 'https://bagsandvibes.com/login/' );
	exit;
}

$nicename     = get_query_var( 'cb_member_nicename' );
$profile_user = $nicename ? get_user_by( 'slug', $nicename ) : false;

$wall_forum_id = 0;
$can_view      = false;
if ( $profile_user && function_exists( 'cb_ensure_user_wall_forum' ) ) {
	$wall_forum_id = cb_ensure_user_wall_forum( $profile_user->ID );
	$can_view      = $wall_forum_id && current_user_can( 'read_forum', $wall_forum_id );
}

if ( ! $profile_user ) {
	status_header( 404 );
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class( 'checkedbags-member-profile' ); ?>>

<header class="site-header" id="site-header">
  <div class="header-inner">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand">Checked Bags <span class="brand-amp">&amp;</span> Good Vibes</a>
    <nav class="member-nav" aria-label="Member navigation">
      <a href="https://bagsandvibes.com/dashboard/" class="btn btn-ghost">Dashboard</a>
      <a href="https://bagsandvibes.com/member-feed/" class="btn btn-ghost">Feed</a>
      <a href="https://bagsandvibes.com/logout/" class="btn btn-ghost">Log Out</a>
    </nav>
  </div>
</header>

<main class="member-profile-main">

<?php if ( ! $profile_user ) : ?>

  <section class="member-profile-empty">
    <p>This member profile couldn&#8217;t be found.</p>
  </section>

<?php elseif ( ! $can_view ) : ?>

  <section class="member-profile-empty">
    <p>This member&#8217;s profile isn&#8217;t visible to you.</p>
  </section>

<?php else : ?>

  <section class="member-profile-header">
    <?php echo get_avatar( $profile_user->ID, 120 ); ?>
    <h1 class="member-profile-name"><?php echo esc_html( $profile_user->display_name ); ?></h1>
    <p class="member-profile-since">
      Member since <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $profile_user->user_registered ) ) ); ?>
    </p>
  </section>

<?php endif; ?>

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
