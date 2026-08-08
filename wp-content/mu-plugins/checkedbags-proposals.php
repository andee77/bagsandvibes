<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Phase 10: PDF Proposal Generator
 * Description: Admin-only tool for building a branded PDF proposal that
 *              compares 2-3 existing Trips for one prospective group. A
 *              Proposal never duplicates trip data -- it only stores which
 *              trips it references plus proposal-specific fields (client
 *              name via post_title, overview narrative, Additional Photos
 *              gallery, Template Style); pricing/itinerary/dates are always
 *              pulled live from the referenced cb_trip posts at generation
 *              time. The PDF's top banner photo is NOT proposal-specific --
 *              it's one fixed global image set once on the Proposal
 *              Boilerplate settings page (below), the same on every
 *              generated proposal.
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
 * media pickers below do NOT need the input/change event-dispatch
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
function cb_proposal_get_trip_id( $proposal_id ) {
	return (int) get_post_meta( $proposal_id, 'cb_proposal_trip_id', true );
}

function cb_proposal_get_overview( $proposal_id ) {
	return (string) get_post_meta( $proposal_id, 'cb_proposal_overview', true );
}

function cb_proposal_get_template_style( $proposal_id ) {
	return get_post_meta( $proposal_id, 'cb_proposal_template_style', true ) ?: 'warm_editorial';
}

// The 5 places a photo can be assigned to, one image max each (enforced at
// save time below) -- matches the real section headings in
// cb_proposal_build_client_html() exactly. "Your Options" and "From Your
// Travel Advisor's Desk" deliberately have NO entry here -- Your Options
// is table/grid content with no natural paragraph to pair an image with,
// and the Advisor's Desk closing note is text-only by design, no
// exception. 4 of these 5 keys match the existing cb_get_proposal_boilerplate()
// array keys 1:1 -- no parallel naming scheme.
function cb_proposal_get_photo_sections() {
	return array(
		'overview'               => 'Overview',
		'whats_included'         => "What's Included",
		'insurance_importance'   => 'Why Travel Insurance Matters',
		'payment_plan'           => 'Travel Now, Pay Later',
		'coordinator_next_steps' => 'Your Coordinator',
	);
}

function cb_proposal_get_additional_photos( $proposal_id ) {
	$photos = get_post_meta( $proposal_id, 'cb_proposal_additional_photos', true );
	if ( ! is_array( $photos ) ) {
		return array();
	}
	$sections = array_keys( cb_proposal_get_photo_sections() );
	$result   = array();
	foreach ( $photos as $photo ) {
		$id      = absint( $photo['id'] ?? 0 );
		$section = $photo['section'] ?? '';
		if ( $id && in_array( $section, $sections, true ) ) {
			$result[] = array( 'id' => $id, 'section' => $section );
		}
	}
	return $result;
}

/* ==========================================================================
   3. Meta box: trip picker (reuses the validated flat-repeater mechanism
      from Day-by-Day Itinerary -- one <select> per row instead of several
      text fields, everything else identical), overview narrative, Template
      Style, Additional Photos gallery.
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
		wp_enqueue_media(); // Additional Photos gallery picker
		return;
	}
	if ( 'cb_proposal_page_cb-proposal-boilerplate' === $hook ) {
		wp_enqueue_media(); // Proposal Header Banner picker
	}
} );

// Reuses .cb-repeater-row/.cb-repeater-remove so the shared Remove handler
// (checkedbags-trips.php admin_footer) works for free -- but "Add" is NOT
// the template-clone mechanism, since a photo row's content (the image)
// arrives already-chosen from wp.media, not typed in blank by the admin.
// See cb_render_proposal_meta_box()'s inline script below for the add flow.
function cb_render_proposal_photo_row_fields( $index, $photo_id, $section ) {
	$thumb_url = wp_get_attachment_image_url( $photo_id, 'thumbnail' );
	?>
	<div class="cb-repeater-row cb-proposal-photo-row">
		<?php if ( $thumb_url ) : ?>
			<img src="<?php echo esc_url( $thumb_url ); ?>" style="width:60px;height:60px;object-fit:cover;border-radius:4px;">
		<?php endif; ?>
		<input type="hidden" name="cb_proposal_additional_photos[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $photo_id ); ?>">
		<select name="cb_proposal_additional_photos[<?php echo esc_attr( $index ); ?>][section]">
			<?php foreach ( cb_proposal_get_photo_sections() as $slug => $label ) : ?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $section, $slug ); ?>><?php echo esc_html( $label ); ?></option>
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

	$trip_id           = cb_proposal_get_trip_id( $post->ID );
	$overview          = cb_proposal_get_overview( $post->ID );
	$template_style    = cb_proposal_get_template_style( $post->ID );
	$additional_photos = cb_proposal_get_additional_photos( $post->ID );

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
		.cb-field select#cb_proposal_trip_id { min-width: 320px; }
		.cb-proposal-photo-row { display: flex; gap: 8px; align-items: center; margin-bottom: 6px; }
	</style>

	<div class="cb-field">
		<label for="cb_proposal_trip_id">Trip <span class="description">(the one trip this proposal is for -- pricing/itinerary/dates are pulled live from it at generation time, never duplicated here)</span></label>
		<select name="cb_proposal_trip_id" id="cb_proposal_trip_id">
			<option value="">-- Choose a trip --</option>
			<?php foreach ( $trips as $trip ) : ?>
				<option value="<?php echo esc_attr( $trip->ID ); ?>" <?php selected( $trip_id, $trip->ID ); ?>><?php echo esc_html( $trip->post_title ); ?></option>
			<?php endforeach; ?>
		</select>
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
		<label>Additional Photos <span class="description">(optional -- assign each photo to one section below; one image max per section, shown side-by-side with that section's text in the generated Client Proposal PDF. If you assign a second photo to a section that already has one, only the first will be used. "Your Options" and the closing Advisor's Desk note don't take a photo. The fixed banner photo at the top of every proposal is set once on the Boilerplate Content settings page, not here.)</span></label>
		<div id="cb-proposal-photo-rows">
			<?php foreach ( $additional_photos as $i => $photo ) : ?>
				<?php cb_render_proposal_photo_row_fields( $i, $photo['id'], $photo['section'] ); ?>
			<?php endforeach; ?>
		</div>
		<button type="button" class="button" id="cb-proposal-select-gallery">Choose Photos</button>
	</div>
	<script>
	(function () {
		var rowsContainer = document.getElementById( 'cb-proposal-photo-rows' );
		var nextIndex      = rowsContainer.children.length;
		var sections       = <?php echo wp_json_encode( cb_proposal_get_photo_sections() ); ?>;

		function existingPhotoIds() {
			var ids = [];
			rowsContainer.querySelectorAll( '.cb-proposal-photo-row input[type="hidden"]' ).forEach( function ( input ) {
				ids.push( String( input.value ) );
			} );
			return ids;
		}

		function addPhotoRow( id, thumbUrl ) {
			var row = document.createElement( 'div' );
			row.className = 'cb-repeater-row cb-proposal-photo-row';

			var options = '';
			Object.keys( sections ).forEach( function ( slug ) {
				options += '<option value="' + slug + '"' + ( 'overview' === slug ? ' selected' : '' ) + '>' + sections[ slug ] + '</option>';
			} );

			row.innerHTML = ( thumbUrl ? '<img src="' + thumbUrl + '" style="width:60px;height:60px;object-fit:cover;border-radius:4px;">' : '' )
				+ '<input type="hidden" name="cb_proposal_additional_photos[' + nextIndex + '][id]" value="' + id + '">'
				+ '<select name="cb_proposal_additional_photos[' + nextIndex + '][section]">' + options + '</select>'
				+ '<button type="button" class="button-link cb-repeater-remove" style="color:#b32d2e;">Remove</button>';
			rowsContainer.appendChild( row );
			nextIndex++; // never reused, even after removals -- same principle as the template-clone repeaters
		}

		document.getElementById( 'cb-proposal-select-gallery' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var frame = wp.media( { title: 'Choose Additional Photos', library: { type: 'image' }, multiple: true } );
			frame.on( 'select', function () {
				var existing = existingPhotoIds();
				frame.state().get( 'selection' ).toJSON().forEach( function ( attachment ) {
					var id = String( attachment.id );
					if ( existing.indexOf( id ) !== -1 ) {
						return; // already added -- reopening the picker doesn't duplicate
					}
					existing.push( id );
					var url = ( attachment.sizes && attachment.sizes.thumbnail ) ? attachment.sizes.thumbnail.url : attachment.url;
					addPhotoRow( id, url );
				} );
			} );
			frame.open();
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

	// Validate the submitted ID actually resolves to a real, existing
	// cb_trip post before trusting it -- it gets used to pull live
	// pricing/itinerary data at PDF-generation time.
	$trip_id = absint( $_POST['cb_proposal_trip_id'] ?? 0 );
	if ( ! $trip_id || 'cb_trip' !== get_post_type( $trip_id ) ) {
		$trip_id = 0;
	}
	update_post_meta( $post_id, 'cb_proposal_trip_id', $trip_id );

	if ( isset( $_POST['cb_proposal_overview'] ) ) {
		update_post_meta( $post_id, 'cb_proposal_overview', sanitize_textarea_field( wp_unslash( $_POST['cb_proposal_overview'] ) ) );
	}

	if ( isset( $_POST['cb_proposal_template_style'] ) ) {
		$style = sanitize_text_field( wp_unslash( $_POST['cb_proposal_template_style'] ) );
		if ( in_array( $style, array( 'warm_editorial', 'structured_grid' ), true ) ) {
			update_post_meta( $post_id, 'cb_proposal_template_style', $style );
		}
	}

	// Additional Photos: bracket-array rows (id + section), same
	// append-based rebuild and blank-row skip as Itinerary/Pricing Tiers.
	// Each ID must resolve to a real attachment; a row whose section isn't
	// currently recognized is dropped outright (not reassigned anywhere --
	// there's no longer a catch-all section to fall back to). One image
	// max per section: if a second photo targets an already-used section,
	// only the first one (in submitted row order) is kept.
	$photo_sections = array_keys( cb_proposal_get_photo_sections() );
	$photos = array();
	$used_sections = array();
	foreach ( (array) ( $_POST['cb_proposal_additional_photos'] ?? array() ) as $photo_row ) {
		if ( cb_repeater_row_is_blank( $photo_row ) ) {
			continue;
		}
		$photo_id = absint( $photo_row['id'] ?? 0 );
		if ( ! $photo_id || 'attachment' !== get_post_type( $photo_id ) ) {
			continue;
		}
		$section = sanitize_text_field( wp_unslash( $photo_row['section'] ?? '' ) );
		if ( ! in_array( $section, $photo_sections, true ) ) {
			continue;
		}
		if ( in_array( $section, $used_sections, true ) ) {
			continue;
		}
		$used_sections[] = $section;
		$photos[] = array( 'id' => $photo_id, 'section' => $section );
	}
	update_post_meta( $post_id, 'cb_proposal_additional_photos', $photos );
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
		'overview_fallback'      => '', // used only when a proposal's own overview narrative is blank
		'whats_included'         => '',
		'insurance_importance'   => '',
		'payment_plan'           => '',
		'coordinator_next_steps' => '',
		'travel_advisor_desk'    => '', // closing personal note -- text-only, never takes a photo
		'header_banner_photo'    => 0, // attachment ID -- one fixed image atop every Client Proposal PDF
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
			'overview_fallback'      => wp_kses_post( wp_unslash( $_POST['overview_fallback'] ?? '' ) ),
			'whats_included'         => wp_kses_post( wp_unslash( $_POST['whats_included'] ?? '' ) ),
			'insurance_importance'   => wp_kses_post( wp_unslash( $_POST['insurance_importance'] ?? '' ) ),
			'payment_plan'           => wp_kses_post( wp_unslash( $_POST['payment_plan'] ?? '' ) ),
			'coordinator_next_steps' => wp_kses_post( wp_unslash( $_POST['coordinator_next_steps'] ?? '' ) ),
			'travel_advisor_desk'    => wp_kses_post( wp_unslash( $_POST['travel_advisor_desk'] ?? '' ) ),
			'header_banner_photo'    => absint( $_POST['header_banner_photo'] ?? 0 ),
		);
		update_option( 'cb_proposal_boilerplate', $boilerplate, false );
		echo '<div class="notice notice-success"><p>Boilerplate content saved.</p></div>';
	}

	$boilerplate = cb_get_proposal_boilerplate();
	$banner_id   = (int) $boilerplate['header_banner_photo'];
	$banner_url  = $banner_id ? wp_get_attachment_image_url( $banner_id, 'medium' ) : '';
	?>
	<div class="wrap">
		<h1>Proposal Boilerplate</h1>
		<p class="description">Reused as-is on every generated proposal, regardless of Template Style or which trip/vendor is involved.</p>
		<form method="post">
			<?php wp_nonce_field( 'cb_save_proposal_boilerplate', 'cb_boilerplate_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th><label>Header Banner Photo</label></th>
					<td>
						<div id="cb-banner-preview" style="margin-bottom:8px;">
							<?php if ( $banner_url ) : ?>
								<img src="<?php echo esc_url( $banner_url ); ?>" style="max-width:400px;height:auto;border-radius:4px;">
							<?php else : ?>
								<p style="color:#888;"><em>No banner photo set -- the Client Proposal PDF will render without one.</em></p>
							<?php endif; ?>
						</div>
						<input type="hidden" name="header_banner_photo" id="header_banner_photo" value="<?php echo esc_attr( $banner_id ); ?>">
						<button type="button" class="button" id="cb-select-banner">Select image</button>
						<button type="button" class="button" id="cb-remove-banner" style="<?php echo $banner_id ? '' : 'display:none;'; ?>">Remove</button>
						<p class="description">The same fixed image at the top of every generated Client Proposal PDF -- not proposal-specific. Compare with each proposal's own "Additional Photos" gallery, which varies per proposal.</p>
					</td>
				</tr>
				<tr>
					<th><label for="overview_fallback">Overview (fallback)</label></th>
					<td>
						<textarea name="overview_fallback" id="overview_fallback" rows="6" class="large-text"><?php echo esc_textarea( $boilerplate['overview_fallback'] ); ?></textarea>
						<p class="description">Shown only when a proposal's own Overview Narrative is left blank -- a proposal-specific narrative always takes priority over this.</p>
					</td>
				</tr>
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
				<tr>
					<th><label for="travel_advisor_desk">From Your Travel Advisor's Desk</label></th>
					<td>
						<textarea name="travel_advisor_desk" id="travel_advisor_desk" rows="6" class="large-text"><?php echo esc_textarea( $boilerplate['travel_advisor_desk'] ); ?></textarea>
						<p class="description">Closing personal note/signoff -- the final section of every Client Proposal PDF, text-only (never takes a photo).</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Boilerplate Content' ); ?>
		</form>
	</div>
	<script>
	(function () {
		document.getElementById( 'cb-select-banner' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var frame = wp.media( { title: 'Select Header Banner Photo', library: { type: 'image' }, multiple: false } );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				document.getElementById( 'header_banner_photo' ).value = attachment.id;
				var url = ( attachment.sizes && attachment.sizes.medium ) ? attachment.sizes.medium.url : attachment.url;
				document.getElementById( 'cb-banner-preview' ).innerHTML = '<img src="' + url + '" style="max-width:400px;height:auto;border-radius:4px;">';
				document.getElementById( 'cb-remove-banner' ).style.display = '';
			} );
			frame.open();
		} );
		document.getElementById( 'cb-remove-banner' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			document.getElementById( 'header_banner_photo' ).value = '0';
			document.getElementById( 'cb-banner-preview' ).innerHTML = '<p style="color:#888;"><em>No banner photo set -- the Client Proposal PDF will render without one.</em></p>';
			this.style.display = 'none';
		} );
	})();
	</script>
	<?php
}
