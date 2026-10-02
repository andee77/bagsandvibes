<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Landing Redesign Core
 * Description: Foundation for the redesigned public trip landing page: the
 *              rollout switches, the one predicate that decides which design
 *              a viewer gets, the admin ?preview=new path, and the new
 *              template's assets. Nothing here changes what any public
 *              visitor sees until BOTH switches below are on for a trip.
 *
 *              Rollout, per trip:
 *                1. Master kill switch -- the cbv_lp_redesign_live option
 *                   (default off; turned on with `wp option update
 *                   cbv_lp_redesign_live 1`, no code deploy needed).
 *                2. The trip's own "Use new landing design" checkbox
 *                   (cbv_lp_use_new_design, default off).
 *              Public visitors get the new design only when the master
 *              switch is on AND the trip's box is checked AND the trip's
 *              existing "Create Public Landing Page" box is checked.
 *              Admins can always see it with ?preview=new on the trip URL,
 *              regardless of all three.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-core.php           <- this file
 *   wp-content/mu-plugins/checkedbags-landing/template-trip-landing.php
 *   wp-content/uploads/checkedbags/css/trip-landing.css
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CBV_LP_MASTER_OPTION', 'cbv_lp_redesign_live' );
define( 'CBV_LP_TRIP_META', 'cbv_lp_use_new_design' );

/* ==========================================================================
   1. Switches + the one predicate that picks the design.
   ========================================================================== */
function cbv_lp_master_is_live() {
	return (bool) get_option( CBV_LP_MASTER_OPTION, 0 );
}

function cbv_lp_trip_opted_in( $trip_id ) {
	return (bool) get_post_meta( $trip_id, CBV_LP_TRIP_META, true );
}

function cbv_lp_preview_requested() {
	return isset( $_GET['preview'] ) && 'new' === sanitize_key( wp_unslash( $_GET['preview'] ) );
}

/**
 * Which experience a viewer gets on a trip's URL:
 *   'new_preview' -- an admin who asked for ?preview=new (always honored)
 *   'private'     -- roster member or admin: the existing private trip detail
 *   'denied'      -- everyone else, landing page not enabled: existing
 *                    sign-in / no-access message
 *   'legacy'      -- everyone else, landing enabled: today's design
 *   'new'         -- everyone else, landing enabled, master switch on AND the
 *                    trip's "Use new landing design" box checked
 *
 * The roster / admin-bypass test deliberately mirrors the one in
 * checkedbags-gate07.php's single-trip the_content filter, so 'private',
 * 'denied' and 'legacy' always agree with what that filter does on its own
 * -- only 'new' and 'new_preview' are acted on by anything new.
 * $viewer_id is a parameter (not read inside) so the whole matrix is
 * testable without logging in as each kind of viewer.
 */
function cbv_lp_trip_view( $trip_id, $viewer_id = null ) {
	$trip_id   = (int) $trip_id;
	$viewer_id = null === $viewer_id ? ( is_user_logged_in() ? get_current_user_id() : 0 ) : (int) $viewer_id;
	$is_admin  = $viewer_id && user_can( $viewer_id, 'manage_options' );

	if ( $is_admin && cbv_lp_preview_requested() ) {
		return 'new_preview';
	}

	$on_roster    = $viewer_id && function_exists( 'cb_trip_get_roster' ) && in_array( $viewer_id, cb_trip_get_roster( $trip_id ), true );
	$admin_bypass = $viewer_id && ! $on_roster && $is_admin;
	if ( $on_roster || $admin_bypass ) {
		return 'private';
	}

	if ( ! get_post_meta( $trip_id, 'cb_public_landing_enabled', true ) ) {
		return 'denied';
	}

	return ( cbv_lp_master_is_live() && cbv_lp_trip_opted_in( $trip_id ) ) ? 'new' : 'legacy';
}

/**
 * One plain sentence on what the PUBLIC currently sees for a trip -- shown
 * to admins in the preview banner and the trip edit screen box, so nobody
 * has to guess whether a trip is live on the new design.
 */
function cbv_lp_public_status_text( $trip_id ) {
	if ( ! get_post_meta( $trip_id, 'cb_public_landing_enabled', true ) ) {
		return 'Public visitors see no landing page for this trip ("Create Public Landing Page" is unchecked).';
	}
	if ( ! cbv_lp_master_is_live() ) {
		return 'Public visitors see the CURRENT design (master switch is off).';
	}
	if ( ! cbv_lp_trip_opted_in( $trip_id ) ) {
		return 'Public visitors see the CURRENT design (this trip\'s "Use new landing design" box is unchecked).';
	}
	return 'Public visitors see the NEW design (master switch on and this trip is checked).';
}

/* ==========================================================================
   2. Hand the new experience to the right renderer + template.
      gate07's the_content filter calls cbv_lp_render_trip() for the two new
      views; every other view runs through its existing, untouched code.
   ========================================================================== */
/**
 * Step 1 placeholder: the new template's shell around TODAY's landing
 * content, so the switch, preview path and shell can be verified before any
 * section is rebuilt. Later steps replace the inner call with the real
 * section renderers.
 */
function cbv_lp_render_trip( $trip_id, $view = 'new' ) {
	if ( ! function_exists( 'cbv_render_public_trip_landing' ) ) {
		return '';
	}

	ob_start();
	if ( 'new_preview' === $view ) {
		?>
		<div class="cbv-lp-preview-banner" role="note">
			<strong>New landing design &middot; admin preview</strong>
			<span><?php echo esc_html( cbv_lp_public_status_text( $trip_id ) ); ?></span>
		</div>
		<?php
	}
	echo '<div class="cbv-lp-legacy-wrap">' . cbv_render_public_trip_landing( $trip_id ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- the renderer escapes its own output
	return ob_get_clean();
}

add_filter( 'template_include', function ( $template ) {
	if ( ! is_singular( 'cb_trip' ) ) {
		return $template;
	}
	if ( ! in_array( cbv_lp_trip_view( get_queried_object_id() ), array( 'new', 'new_preview' ), true ) ) {
		return $template;
	}
	$custom = __DIR__ . '/checkedbags-landing/template-trip-landing.php';
	return file_exists( $custom ) ? $custom : $template;
}, 30 ); // after checkedbags-landing.php's priority-20 filter that forces the Gate shell for cb_trip

/* ==========================================================================
   3. Preview pages: never cached, never indexed. The canonical URL is left
      to Yoast (the clean permalink), so ?preview=new can't become a
      duplicate-content URL even if a link leaks.
   ========================================================================== */
add_action( 'template_redirect', function () {
	if ( ! cbv_lp_preview_requested() || ! is_singular( 'cb_trip' ) ) {
		return;
	}
	if ( 'new_preview' !== cbv_lp_trip_view( get_queried_object_id() ) ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );
}, 1 );

add_filter( 'wp_robots', function ( $robots ) {
	if ( cbv_lp_preview_requested() && is_singular( 'cb_trip' ) && 'new_preview' === cbv_lp_trip_view( get_queried_object_id() ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );
	}
	return $robots;
}, 99 ); // after the existing priority-10 filter and Yoast's, so noindex always wins

/* ==========================================================================
   4. Assets -- the new template only. Landing-specific font request
      (includes Fraunces 400, which the shared request doesn't load); the
      shared styles.css still loads as it does for every trip page, because
      the legacy content inside the Step 1 shell depends on it. app.js is
      dropped here: it drives the old header's menu toggle, which this
      template doesn't have.
   ========================================================================== */
function cbv_lp_is_new_experience() {
	return is_singular( 'cb_trip' ) && in_array( cbv_lp_trip_view( get_queried_object_id() ), array( 'new', 'new_preview' ), true );
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! cbv_lp_is_new_experience() ) {
		return;
	}

	wp_enqueue_style(
		'cbv-lp-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500;1,9..144,600&family=Work+Sans:wght@400;500;600&family=Space+Mono:wght@400;700&display=swap',
		array(),
		null
	);

	$css_path = WP_CONTENT_DIR . '/uploads/checkedbags/css/trip-landing.css';
	wp_enqueue_style(
		'cbv-lp',
		content_url( 'uploads/checkedbags/css/trip-landing.css' ),
		array(),
		file_exists( $css_path ) ? filemtime( $css_path ) : '1.0.0'
	);
}, 20 );

add_action( 'wp_enqueue_scripts', function () {
	if ( cbv_lp_is_new_experience() ) {
		wp_dequeue_script( 'checkedbags-app' );
	}
}, 30 );

/* ==========================================================================
   5. Admin: the per-trip "Use new landing design" box. Admin-only to see
      and to save -- turning a trip live is an owner decision, not an
      editor's. A plain checkbox, no nested <form>, so it's safe inside the
      trip edit form.
   ========================================================================== */
add_action( 'init', function () {
	register_post_meta( 'cb_trip', CBV_LP_TRIP_META, array(
		'type'          => 'boolean',
		'single'        => true,
		'default'       => false,
		'show_in_rest'  => false,
		'auth_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	) );
} );

add_action( 'add_meta_boxes', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	add_meta_box( 'cbv_lp_design_switch', 'New Landing Design', 'cbv_lp_render_design_switch_box', 'cb_trip', 'side', 'default' );
} );

function cbv_lp_render_design_switch_box( $post ) {
	wp_nonce_field( 'cbv_lp_design_switch_save', 'cbv_lp_design_switch_nonce' );
	$opted_in = cbv_lp_trip_opted_in( $post->ID );
	?>
	<p>
		<label>
			<input type="checkbox" name="cbv_lp_use_new_design" value="1" <?php checked( $opted_in ); ?>>
			<strong>Use new landing design</strong>
		</label>
	</p>
	<p class="description">
		Off by default. Takes effect for the public only when the master switch is on and "Create Public Landing Page" is checked.
	</p>
	<p><strong>Master switch:</strong> <?php echo cbv_lp_master_is_live() ? 'ON' : 'OFF'; ?></p>
	<p class="description"><?php echo esc_html( cbv_lp_public_status_text( $post->ID ) ); ?></p>
	<?php if ( 'auto-draft' !== $post->post_status ) : ?>
		<p><a href="<?php echo esc_url( add_query_arg( 'preview', 'new', get_permalink( $post ) ) ); ?>" target="_blank" rel="noopener">Preview new design (admins only)</a></p>
	<?php endif; ?>
	<?php
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_lp_design_switch_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_lp_design_switch_nonce'], 'cbv_lp_design_switch_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	update_post_meta( $post_id, CBV_LP_TRIP_META, ! empty( $_POST['cbv_lp_use_new_design'] ) ? 1 : 0 );
} );
