<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Phase 10: PDF Proposal Generator
 * Description: Admin-only tool for building a branded PDF proposal that
 *              compares 2-3 existing Trips for one prospective group. A
 *              Proposal never duplicates trip data -- it only stores which
 *              trips it references plus proposal-specific fields (client
 *              name via post_title, overview narrative, hero photo,
 *              Template Style); pricing/itinerary/dates are always pulled
 *              live from the referenced cb_trip posts at generation time.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-proposals.php
 *
 * ADMIN-ONLY, FOR REAL: this whole feature must never be reachable by a
 * Full Member, Trip Guest, or logged-out visitor, even by guessing a URL --
 * not just a hidden menu item. Two layers, matching the exact house style
 * already used for Membership Terms / Trip Guests elsewhere in this build
 * (raw current_user_can('manage_options'), no custom capability anywhere):
 *   1. The registered capability_type keeps the menu itself invisible to
 *      anyone without edit_posts-equivalent access in the first place.
 *   2. A current_screen guard hard wp_die()s on cb_proposal's list/edit/new
 *      screens for anyone who isn't current_user_can('manage_options') --
 *      this is the real gate, since it fires on direct URL access too, not
 *      just menu clicks. The meta box render/save also re-check the same
 *      capability, belt-and-suspenders, matching Membership Terms.
 * show_in_rest is deliberately false: no REST endpoint means no second
 * guessable URL surface to also have to gate, and it keeps this CPT on the
 * Classic Editor -- so, unlike cb_trip's Gutenberg-edited meta boxes, the
 * hero-photo picker below does NOT need the input/change event-dispatch
 * workaround documented in checkedbags-trip-invites.php (Gutenberg's
 * separate meta-box-loader save path doesn't exist here; this form POSTs
 * normally on Publish/Update).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. CPT registration + admin-only gating.
   ========================================================================== */
add_action( 'init', function () {
	register_post_type( 'cb_proposal', array(
		'label'        => 'Proposals',
		'labels'       => array(
			'name'          => 'Proposals',
			'singular_name' => 'Proposal',
			'add_new_item'  => 'Add New Proposal',
			'edit_item'     => 'Edit Proposal',
			'menu_name'     => 'Proposals',
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => current_user_can( 'manage_options' ),
		'menu_icon'    => 'dashicons-media-document',
		'supports'     => array( 'title' ),
		'show_in_rest' => false,
		'has_archive'  => false,
		'rewrite'      => false,
	) );
} );

add_action( 'current_screen', function ( $screen ) {
	if ( ! $screen || 'cb_proposal' !== $screen->post_type ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions.' );
	}
} );

/* ==========================================================================
   2. Getters -- for this piece's own meta box and for the PDF generation
      code (Piece 5) to consume without reaching into raw post meta.
   ========================================================================== */
function cb_proposal_get_trip_ids( $proposal_id ) {
	$trip_ids = get_post_meta( $proposal_id, 'cb_proposal_trip_ids', true );
	return is_array( $trip_ids ) ? $trip_ids : array();
}

function cb_proposal_get_overview( $proposal_id ) {
	return (string) get_post_meta( $proposal_id, 'cb_proposal_overview', true );
}

function cb_proposal_get_template_style( $proposal_id ) {
	return get_post_meta( $proposal_id, 'cb_proposal_template_style', true ) ?: 'warm_editorial';
}

function cb_proposal_get_hero_photo_url( $proposal_id, $size = 'large' ) {
	$attachment_id = (int) get_post_meta( $proposal_id, 'cb_proposal_hero_photo', true );
	if ( ! $attachment_id ) {
		return '';
	}
	return wp_get_attachment_image_url( $attachment_id, $size ) ?: '';
}

/* ==========================================================================
   3. Meta box: trip picker (reuses the validated flat-repeater mechanism
      from Day-by-Day Itinerary -- one <select> per row instead of several
      text fields, everything else identical), overview narrative, Template
      Style, hero photo picker.
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'cb_proposal_details',
		'Proposal Details',
		'cb_render_proposal_meta_box',
		'cb_proposal',
		'normal',
		'high'
	);
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	global $post;
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $post && 'cb_proposal' === $post->post_type ) {
		wp_enqueue_media();
	}
} );

function cb_render_proposal_trip_row_fields( $index, $selected_trip_id, $trips ) {
	?>
	<div class="cb-repeater-row cb-proposal-trip-row">
		<select name="cb_proposal_trip_ids[<?php echo esc_attr( $index ); ?>]">
			<option value="">-- Choose a trip --</option>
			<?php foreach ( $trips as $trip ) : ?>
				<option value="<?php echo esc_attr( $trip->ID ); ?>" <?php selected( (int) $selected_trip_id, $trip->ID ); ?>><?php echo esc_html( $trip->post_title ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="button" class="button-link cb-repeater-remove" style="color:#b32d2e;">Remove</button>
	</div>
	<?php
}

function cb_render_proposal_meta_box( $post ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_nonce_field( 'cb_proposal_save', 'cb_proposal_nonce' );

	$trip_ids       = cb_proposal_get_trip_ids( $post->ID );
	$overview       = cb_proposal_get_overview( $post->ID );
	$template_style = cb_proposal_get_template_style( $post->ID );
	$hero_photo_id  = (int) get_post_meta( $post->ID, 'cb_proposal_hero_photo', true );
	$hero_photo_url = $hero_photo_id ? wp_get_attachment_image_url( $hero_photo_id, 'medium' ) : '';

	$trips = get_posts( array(
		'post_type'      => 'cb_trip',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
	?>
	<style>
		.cb-field { margin-bottom: 16px; }
		.cb-field label { display: block; font-weight: 600; margin-bottom: 4px; }
		.cb-field textarea { width: 100%; max-width: 700px; }
		.cb-field select#cb_proposal_template_style { min-width: 320px; }
		[data-repeater="cb_proposal_trip_ids"] .cb-repeater-row { display: flex; gap: 8px; align-items: center; }
		[data-repeater="cb_proposal_trip_ids"] select { min-width: 320px; }
	</style>

	<div class="cb-field">
		<label>Trip Options Being Compared <span class="description">(2-3 existing trips -- pricing/itinerary/dates are pulled live from each at generation time, never duplicated here)</span></label>
		<div class="cb-repeater" data-repeater="cb_proposal_trip_ids">
			<div class="cb-repeater-rows">
				<?php foreach ( $trip_ids as $i => $trip_id ) : ?>
					<?php cb_render_proposal_trip_row_fields( $i, $trip_id, $trips ); ?>
				<?php endforeach; ?>
			</div>
			<template class="cb-repeater-template">
				<?php cb_render_proposal_trip_row_fields( '__INDEX__', '', $trips ); ?>
			</template>
			<button type="button" class="button cb-repeater-add">+ Add Trip Option</button>
		</div>
	</div>

	<div class="cb-field">
		<label for="cb_proposal_overview">Overview Narrative</label>
		<textarea name="cb_proposal_overview" id="cb_proposal_overview" rows="6" placeholder="Short, client-specific narrative introducing this group's options..."><?php echo esc_textarea( $overview ); ?></textarea>
	</div>

	<div class="cb-field">
		<label for="cb_proposal_template_style">Template Style</label>
		<select name="cb_proposal_template_style" id="cb_proposal_template_style">
			<option value="warm_editorial" <?php selected( $template_style, 'warm_editorial' ); ?>>Warm &amp; Editorial (Cruise / Resort / Retreat)</option>
			<option value="structured_grid" <?php selected( $template_style, 'structured_grid' ); ?>>Structured &amp; Grid-Based (Destination / Other)</option>
		</select>
	</div>

	<div class="cb-field">
		<label>Hero Photo</label>
		<div id="cb-proposal-hero-preview" style="margin-bottom:8px;">
			<?php if ( $hero_photo_url ) : ?>
				<img src="<?php echo esc_url( $hero_photo_url ); ?>" style="max-width:300px;height:auto;border-radius:4px;">
			<?php else : ?>
				<p style="color:#888;"><em>No hero photo set.</em></p>
			<?php endif; ?>
		</div>
		<input type="hidden" name="cb_proposal_hero_photo" id="cb_proposal_hero_photo" value="<?php echo esc_attr( $hero_photo_id ); ?>">
		<button type="button" class="button" id="cb-proposal-select-hero">Select image</button>
		<button type="button" class="button" id="cb-proposal-remove-hero" style="<?php echo $hero_photo_id ? '' : 'display:none;'; ?>">Remove</button>
	</div>
	<script>
	(function () {
		document.getElementById( 'cb-proposal-select-hero' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var frame = wp.media( { title: 'Select Hero Photo', library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				document.getElementById( 'cb_proposal_hero_photo' ).value = attachment.id;
				var url = ( attachment.sizes && attachment.sizes.medium ) ? attachment.sizes.medium.url : attachment.url;
				document.getElementById( 'cb-proposal-hero-preview' ).innerHTML = '<img src="' + url + '" style="max-width:300px;height:auto;border-radius:4px;">';
				document.getElementById( 'cb-proposal-remove-hero' ).style.display = '';
			} );
			frame.open();
		} );
		document.getElementById( 'cb-proposal-remove-hero' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			document.getElementById( 'cb_proposal_hero_photo' ).value = '0';
			document.getElementById( 'cb-proposal-hero-preview' ).innerHTML = '<p style="color:#888;"><em>No hero photo set.</em></p>';
			this.style.display = 'none';
		} );
	})();
	</script>
	<?php
}

/* ==========================================================================
   4. Save handler.
   ========================================================================== */
add_action( 'save_post_cb_proposal', function ( $post_id ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! isset( $_POST['cb_proposal_nonce'] ) || ! wp_verify_nonce( $_POST['cb_proposal_nonce'], 'cb_proposal_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Trip picker: validate every submitted ID actually resolves to a real,
	// existing cb_trip post before trusting it -- these IDs get used to pull
	// live pricing/itinerary data at PDF-generation time.
	$trip_ids = array();
	foreach ( (array) ( $_POST['cb_proposal_trip_ids'] ?? array() ) as $trip_id_row ) {
		$trip_id_row = absint( $trip_id_row );
		if ( $trip_id_row && 'cb_trip' === get_post_type( $trip_id_row ) ) {
			$trip_ids[] = $trip_id_row;
		}
	}
	update_post_meta( $post_id, 'cb_proposal_trip_ids', $trip_ids );

	if ( isset( $_POST['cb_proposal_overview'] ) ) {
		update_post_meta( $post_id, 'cb_proposal_overview', sanitize_textarea_field( wp_unslash( $_POST['cb_proposal_overview'] ) ) );
	}

	if ( isset( $_POST['cb_proposal_template_style'] ) ) {
		$style = sanitize_text_field( wp_unslash( $_POST['cb_proposal_template_style'] ) );
		if ( in_array( $style, array( 'warm_editorial', 'structured_grid' ), true ) ) {
			update_post_meta( $post_id, 'cb_proposal_template_style', $style );
		}
	}

	if ( isset( $_POST['cb_proposal_hero_photo'] ) ) {
		update_post_meta( $post_id, 'cb_proposal_hero_photo', absint( $_POST['cb_proposal_hero_photo'] ) );
	}
} );

/* ==========================================================================
   5. Proposal Boilerplate settings page -- one universal version of each
      static content block (What's Included, Why Travel Insurance Matters,
      Uplift/FlexPay explainer, coordinator next-steps), reused as-is on
      every proposal regardless of Template Style or which trip/vendor is
      involved -- confirmed in scoping, no per-style or per-vendor variant.
      Clone of the Membership Terms settings page
      (checkedbags-trip-invites.php: cbv_render_membership_terms_page) --
      single option, hand-rolled form, wp_kses_post on save, no WP Settings
      API -- minus that page's version-bump/re-acceptance logic, which is
      specific to its terms-of-service gate and doesn't apply here.
   ========================================================================== */
function cb_get_proposal_boilerplate() {
	$defaults = array(
		'whats_included'         => '',
		'insurance_importance'   => '',
		'payment_plan'           => '',
		'coordinator_next_steps' => '',
	);
	return wp_parse_args( get_option( 'cb_proposal_boilerplate', $defaults ), $defaults );
}

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=cb_proposal',
		'Proposal Boilerplate',
		'Boilerplate Content',
		'manage_options',
		'cb-proposal-boilerplate',
		'cb_render_proposal_boilerplate_page'
	);
} );

function cb_render_proposal_boilerplate_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['cb_boilerplate_nonce'] ) && wp_verify_nonce( $_POST['cb_boilerplate_nonce'], 'cb_save_proposal_boilerplate' ) ) {
		$boilerplate = array(
			'whats_included'         => wp_kses_post( wp_unslash( $_POST['whats_included'] ?? '' ) ),
			'insurance_importance'   => wp_kses_post( wp_unslash( $_POST['insurance_importance'] ?? '' ) ),
			'payment_plan'           => wp_kses_post( wp_unslash( $_POST['payment_plan'] ?? '' ) ),
			'coordinator_next_steps' => wp_kses_post( wp_unslash( $_POST['coordinator_next_steps'] ?? '' ) ),
		);
		update_option( 'cb_proposal_boilerplate', $boilerplate, false );
		echo '<div class="notice notice-success"><p>Boilerplate content saved.</p></div>';
	}

	$boilerplate = cb_get_proposal_boilerplate();
	?>
	<div class="wrap">
		<h1>Proposal Boilerplate</h1>
		<p class="description">Reused as-is on every generated proposal, regardless of Template Style or which trip/vendor is involved.</p>
		<form method="post">
			<?php wp_nonce_field( 'cb_save_proposal_boilerplate', 'cb_boilerplate_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="whats_included">What's Included</label></th>
					<td><textarea name="whats_included" id="whats_included" rows="6" class="large-text"><?php echo esc_textarea( $boilerplate['whats_included'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="insurance_importance">Why Travel Insurance Matters</label></th>
					<td><textarea name="insurance_importance" id="insurance_importance" rows="6" class="large-text"><?php echo esc_textarea( $boilerplate['insurance_importance'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="payment_plan">Travel Now, Pay Later (Uplift / FlexPay)</label></th>
					<td><textarea name="payment_plan" id="payment_plan" rows="6" class="large-text"><?php echo esc_textarea( $boilerplate['payment_plan'] ); ?></textarea></td>
				</tr>
				<tr>
					<th><label for="coordinator_next_steps">Coordinator Role &amp; Next Steps</label></th>
					<td><textarea name="coordinator_next_steps" id="coordinator_next_steps" rows="6" class="large-text"><?php echo esc_textarea( $boilerplate['coordinator_next_steps'] ); ?></textarea></td>
				</tr>
			</table>
			<?php submit_button( 'Save Boilerplate Content' ); ?>
		</form>
	</div>
	<?php
}
