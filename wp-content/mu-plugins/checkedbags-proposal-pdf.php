<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Phase 10 Piece 5: Proposal PDF Generation
 * Description: Turns a cb_proposal into two downloadable PDFs -- a branded
 *              Client Proposal (Template-Style-dependent visual treatment)
 *              and a plain Internal Data Sheet (adds vendor/margin/
 *              coordinator content, never shown to a client). Both pull
 *              pricing/itinerary/dates live from the proposal's referenced
 *              cb_trip posts at generation time -- nothing is duplicated
 *              or cached on the proposal itself.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-proposal-pdf.php
 *
 * Uses Dompdf (wp-content/vendor, installed via Composer -- see
 * wp-content/composer.json). Images are embedded from local file paths
 * (get_attached_file()), not public URLs, so Dompdf never has to make an
 * HTTP round-trip back to this same server to fetch them.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WP_CONTENT_DIR . '/vendor/autoload.php';

/* ==========================================================================
   1. Formatting helpers.
   ========================================================================== */
function cb_proposal_format_money( $amount ) {
	return '$' . number_format( (float) $amount, 2 );
}

/* ==========================================================================
   1a. Image format compatibility -- Dompdf has no built-in awareness of
       newer formats like AVIF/WebP even when PHP's own GD extension can
       decode them (confirmed by grepping Dompdf's own vendor source: zero
       AVIF references anywhere in it). Rather than trying to precisely
       track which formats Dompdf does/doesn't support, anything outside a
       small known-safe allowlist (JPEG/PNG/GIF) gets converted to a temp
       PNG via GD before it's ever handed to Dompdf. Applies everywhere a
       local image path reaches the PDF: logo, banner, each trip's cover
       photo, and the Additional Photos gallery -- one shared choke point,
       not four separate ad-hoc conversions.
   ========================================================================== */
function cb_proposal_track_temp_image( $path ) {
	global $cb_proposal_temp_images;
	$cb_proposal_temp_images[] = $path;
}

function cb_proposal_cleanup_temp_images() {
	global $cb_proposal_temp_images;
	foreach ( (array) $cb_proposal_temp_images as $path ) {
		if ( file_exists( $path ) ) {
			@unlink( $path );
		}
	}
	$cb_proposal_temp_images = array();
}

function cb_proposal_resolve_pdf_image_path( $file_path ) {
	if ( ! $file_path || ! file_exists( $file_path ) ) {
		return $file_path;
	}

	$mime = wp_check_filetype( $file_path )['type'] ?? '';
	if ( in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif' ), true ) ) {
		return $file_path;
	}

	if ( 'image/avif' === $mime && function_exists( 'imagecreatefromavif' ) ) {
		$image = imagecreatefromavif( $file_path );
	} elseif ( 'image/webp' === $mime && function_exists( 'imagecreatefromwebp' ) ) {
		$image = imagecreatefromwebp( $file_path );
	} else {
		$image = @imagecreatefromstring( file_get_contents( $file_path ) );
	}

	if ( ! $image ) {
		return $file_path; // couldn't convert -- Dompdf will just skip a broken image rather than fatal
	}

	// Every source this function actually converts (banner/gallery/cover
	// photos) is a photograph, never a transparent asset like a logo --
	// JPEG at high quality is far smaller than PNG for that content with
	// no visible loss (confirmed: PNG output ballooned a 3-image test PDF
	// from ~4.8MB to ~18MB). Flatten onto white first anyway, since JPEG
	// has no alpha channel and a source AVIF/WebP could in principle carry
	// transparency even though none of the real usage here does.
	$width     = imagesx( $image );
	$height    = imagesy( $image );
	$flattened = imagecreatetruecolor( $width, $height );
	imagefill( $flattened, 0, 0, imagecolorallocate( $flattened, 255, 255, 255 ) );
	imagecopy( $flattened, $image, 0, 0, 0, 0, $width, $height );
	imagedestroy( $image );

	// wp_tempnam() defaults to the system temp directory (e.g. /tmp), which
	// falls OUTSIDE the Dompdf chroot set in cb_proposal_render_pdf() --
	// Dompdf then silently refuses to read the converted image at all, by
	// design (that's exactly what the chroot is for). Forcing the temp
	// file into wp-content/uploads keeps it within the allowed paths.
	// It also itself creates (reserves) a real empty file at its returned
	// path -- appending ".jpg" to build the actual write target means BOTH
	// that empty reservation file and the real JPEG need cleanup after.
	$temp_base = wp_tempnam( 'cb-proposal-pdf-image', WP_CONTENT_DIR . '/uploads/' );
	$temp_path = $temp_base . '.jpg';
	imagejpeg( $flattened, $temp_path, 85 );
	imagedestroy( $flattened );
	cb_proposal_track_temp_image( $temp_base );
	cb_proposal_track_temp_image( $temp_path );

	return $temp_path;
}

/* ==========================================================================
   2. Shared data gatherer -- the ONE place that decides which trip/proposal
      fields feed the PDFs. Both templates call this; only the internal one
      passes $include_internal_notes = true, so cb_trip_get_internal_notes()
      is never even invoked while building the client-facing document --
      there's no shared code path for that data to leak through.
   ========================================================================== */
function cb_proposal_build_pdf_data( $proposal_id, $include_internal_notes = false ) {
	$proposal    = get_post( $proposal_id );
	$boilerplate = cb_get_proposal_boilerplate();

	// The header banner is global (one fixed photo, set once on the
	// Boilerplate Content settings page) -- NOT proposal-specific. Additional
	// Photos, by contrast, is a per-proposal gallery the admin curates.
	$banner_id = (int) $boilerplate['header_banner_photo'];

	// One photo max per section (Overview + the 4 photo-eligible boilerplate
	// blocks -- "Your Options" and the Advisor's Desk closing note never
	// take one). The save handler already enforces the cap, but keeping
	// only the first match here too is a harmless defensive backstop.
	$photos_by_section = array_fill_keys( array_keys( cb_proposal_get_photo_sections() ), '' );
	foreach ( cb_proposal_get_additional_photos( $proposal_id ) as $photo ) {
		if ( '' !== $photos_by_section[ $photo['section'] ] ) {
			continue;
		}
		$path = get_attached_file( $photo['id'] );
		if ( $path ) {
			$photos_by_section[ $photo['section'] ] = cb_proposal_resolve_pdf_image_path( $path );
		}
	}

	$data = array(
		'client_name'        => $proposal->post_title,
		'overview'           => cb_proposal_get_overview( $proposal_id ),
		'template_style'     => cb_proposal_get_template_style( $proposal_id ),
		'header_banner_path' => $banner_id ? cb_proposal_resolve_pdf_image_path( get_attached_file( $banner_id ) ) : '',
		'photos_by_section'  => $photos_by_section,
		'boilerplate'        => $boilerplate,
		'generated_date'     => date_i18n( 'F j, Y' ),
		'trip'               => null,
	);

	$trip_id = cb_proposal_get_trip_id( $proposal_id );
	// The referenced trip may have been deleted since this proposal was
	// built -- skip stale references rather than trusting them; 'trip'
	// stays null and the HTML builder renders nothing for that section.
	if ( $trip_id && 'cb_trip' === get_post_type( $trip_id ) ) {
		$terms      = get_the_terms( $trip_id, 'cb_trip_type' );
		$type_label = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';

		$trip_data = array(
			'id'            => $trip_id,
			'title'         => get_the_title( $trip_id ),
			'type_label'    => $type_label,
			'start_date'    => get_post_meta( $trip_id, 'cb_start_date', true ),
			'end_date'      => get_post_meta( $trip_id, 'cb_end_date', true ),
			'itinerary'     => cb_trip_get_itinerary( $trip_id ),
			'pricing_tiers' => cb_trip_get_pricing_tiers( $trip_id ),
			'single_price'  => (float) get_post_meta( $trip_id, 'cb_price', true ),
		);

		if ( $include_internal_notes ) {
			$trip_data['internal_notes']  = cb_trip_get_internal_notes( $trip_id );
			$trip_data['point_person']    = cb_trip_get_point_person( $trip_id );
			$trip_data['roster_summary']  = cb_proposal_get_roster_summary_rows( $trip_id );
		}

		$data['trip'] = $trip_data;
	}

	if ( $include_internal_notes ) {
		$data['proposal_next_steps'] = cb_proposal_get_next_steps( $proposal_id );
	}

	return $data;
}

/* ==========================================================================
   2a. Already-Signed-Up Clients table data -- Internal Data Sheet only.
       Deliberately reuses the same underlying helpers the Phase 9 roster
       export already relies on (cb_trip_get_roster, cb_trip_amount_paid/
       cb_trip_balance_due, cbv_get_traveler_intake, the raw per-trip
       "Received" flag meta reads) rather than re-deriving any of this --
       one source of truth. Per-traveler add-ons are deliberately NOT
       included: add-ons are tracked per Pricing Tier, not per traveler, so
       there's no data linking a specific add-on to a specific person yet.
   ========================================================================== */
function cb_proposal_get_roster_summary_rows( $trip_id ) {
	$rows = array();

	foreach ( cb_trip_get_roster( $trip_id ) as $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			continue;
		}

		$intake = function_exists( 'cbv_get_traveler_intake' ) ? cbv_get_traveler_intake( $user_id, $trip_id ) : array();

		$flight_cabin_class = $intake['flight_cabin_class'] ?? '';

		// Same trip-wide fallback treatment as the roster export's cruise
		// cabin class column: prefer the traveler's own answer, falling
		// back to the organizer's trip-wide answer (marked " *") only when
		// the traveler never filled in their own.
		$cruise_cabin_class   = $intake['cruise_cabin_class'] ?? '';
		$trip_wide_cruise_cabin_class = function_exists( 'cbv_get_trip_request_field' ) ? cbv_get_trip_request_field( $trip_id, 'cruise_cabin_class' ) : '';
		if ( '' === $cruise_cabin_class && '' !== (string) $trip_wide_cruise_cabin_class ) {
			$cruise_cabin_class = $trip_wide_cruise_cabin_class . ' *';
		}

		$cabin_room = array_filter( array( $flight_cabin_class, $cruise_cabin_class ) );
		$cabin_room = implode( ' / ', $cabin_room );

		$balance_due = function_exists( 'cb_trip_balance_due' ) ? cb_trip_balance_due( $trip_id, $user_id ) : 0;

		$rows[] = array(
			'name'               => $user->display_name,
			'cabin_room'         => $cabin_room,
			'balance_due'        => (float) $balance_due,
			'paid_in_full'       => 'yes' === get_user_meta( $user_id, '_paid_in_full_' . $trip_id, true ),
			'insurance_received' => 'yes' === get_user_meta( $user_id, '_insurance_waiver_received_' . $trip_id, true ),
			'cc_auth_received'   => 'yes' === get_user_meta( $user_id, '_cc_auth_received_' . $trip_id, true ),
		);
	}

	return $rows;
}

/* ==========================================================================
   3. Template CSS -- one shared "locked" foundation (page size/margins,
      footer/page-number mechanics, table resets, disclaimer) plus a
      per-Template-Style block that only changes colors/type treatment/
      card decoration. Both draw from the site's real documented palette
      and font list (project-info.md) -- no invented brand. Real font
      files (Fraunces/Work Sans/Space Mono) aren't bundled anywhere in
      this repo, so this deliberately uses safe generic serif/sans-serif/
      monospace substitutes for now, per confirmed scope -- brand comes
      through via color and layout, not exact typeface.
   ========================================================================== */
function cb_proposal_get_template_css( $style ) {
	$shared = '
		* { box-sizing: border-box; }
		body { font-family: "Helvetica", "Arial", sans-serif; color: #16232B; font-size: 11px; line-height: 1.5; margin: 0; }
		h1, h2, h3 { font-family: Georgia, "Times New Roman", serif; margin: 0 0 8px; }
		p { margin: 0 0 8px; }
		@page { margin: 70px 40px 60px 40px; }
		.cb-header { position: fixed; top: -55px; left: 0; right: 0; height: 40px; }
		.cb-header img { max-height: 40px; }
		.cb-footer { position: fixed; bottom: -45px; left: 0; right: 0; font-size: 8px; color: #666; text-align: center; border-top: 1px solid #ddd; padding-top: 6px; }
		.cb-footer .cb-page-number:after { content: "Page " counter(page); }
		.cb-hero { width: 100%; max-height: 260px; margin-bottom: 16px; }
		.cb-section-title { font-size: 16px; margin-top: 22px; margin-bottom: 10px; }
		.cb-trip-heading { margin-top: 16px; margin-bottom: 8px; page-break-inside: avoid; }
		.cb-trip-heading h3 { margin: 0 0 4px; font-size: 13px; }
		.cb-option-meta { font-family: "Courier New", monospace; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
		table.cb-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 10px; }
		table.cb-table th, table.cb-table td { padding: 5px 7px; text-align: left; border-bottom: 1px solid #ddd; }
		table.cb-table th { font-family: "Courier New", monospace; font-size: 8px; text-transform: uppercase; letter-spacing: 0.04em; }
		.cb-tier-name { font-weight: bold; margin-top: 10px; margin-bottom: 4px; }
		.cb-addon-list { font-size: 9px; color: #444; margin: 4px 0 10px; }
		/* No page-break-inside: avoid here -- confirmed directly (before/
		   after test against this Dompdf install) that on a block this
		   size (heading + wrapped image + full-length copy), "avoid"
		   pushes the ENTIRE block to the next page whenever it cannot
		   fully fit, leaving the remainder of the current page blank.
		   Letting it split naturally means whatever fits stays, only the
		   overflow continues -- normal reflow, no gap. */
		.cb-boilerplate-block { margin-top: 18px; }
		.cb-disclaimer { font-size: 8px; color: #888; }
		/* Genuine wrap-around: a floated figure with text flowing beside it
		   for the height of the figure, then continuing at full width once
		   past it -- not a rigid two-column table. Verified directly against
		   this Dompdf install before building this (both a tall-figure/
		   short-text case and a short-figure/long-text case correctly wrap
		   and then go full-width, with no overlap into what follows). Every
		   call site pairs this with an explicit .cb-clear div immediately
		   after the content, closing the float before the next section --
		   that clearing, not the float itself, is what the earlier overlap
		   bug (a different, unrelated inline-block gallery) was missing. */
		.cb-figure { float: left; width: 42%; margin: 0 16px 8px 0; }
		.cb-figure.cb-image-right { float: right; margin: 0 0 8px 16px; }
		.cb-figure img { width: 100%; height: auto; border-radius: 6px; }
		.cb-clear { clear: both; }
	';

	if ( 'structured_grid' === $style ) {
		return $shared . '
			h1, h2, h3 { font-family: "Helvetica", "Arial", sans-serif; font-weight: bold; text-transform: uppercase; letter-spacing: 0.03em; color: #1B3A4B; }
			.cb-section-title { border-bottom: 2px solid #1B3A4B; padding-bottom: 4px; }
			.cb-trip-heading h3 { color: #1B3A4B; }
			.cb-option-meta { color: #2E7D6E; }
			table.cb-table th { background: #1B3A4B; color: #FBF3E7; }
			.cb-tier-name { color: #1B3A4B; }
		';
	}

	// Default: warm_editorial
	return $shared . '
		h1, h2, h3 { font-style: italic; color: #FF6B4A; }
		.cb-section-title { color: #FF6B4A; }
		.cb-trip-heading h3 { color: #FF6B4A; }
		.cb-option-meta { color: #E8A94E; }
		table.cb-table th { background: #FBF3E7; color: #16232B; border-bottom: 2px solid #E8A94E; }
		.cb-tier-name { color: #FF6B4A; }
	';
}

/* ==========================================================================
   4. HTML partial builders -- one per repeating section, so the main
      document assembler (below) stays readable.
   ========================================================================== */
function cb_proposal_render_itinerary_html( $trip ) {
	if ( empty( $trip['itinerary'] ) ) {
		return '';
	}
	$rows = '';
	foreach ( $trip['itinerary'] as $day ) {
		$rows .= '<tr>'
			. '<td>' . esc_html( $day['day'] ) . '</td>'
			. '<td>' . esc_html( $day['date'] ) . '</td>'
			. '<td>' . esc_html( $day['port'] ) . '</td>'
			. '<td>' . esc_html( $day['country'] ) . '</td>'
			. '<td>' . esc_html( $day['description'] ) . '</td>'
			. '<td>' . esc_html( $day['time'] ) . '</td>'
			. '<td>' . esc_html( $day['tender_mode'] ) . '</td>'
			. '</tr>';
	}
	return '<table class="cb-table"><thead><tr>'
		. '<th>Day</th><th>Date</th><th>Port</th><th>Country</th><th>Description</th><th>Time</th><th>Tender</th>'
		. '</tr></thead><tbody>' . $rows . '</tbody></table>';
}

function cb_proposal_render_pricing_html( $trip ) {
	if ( ! empty( $trip['pricing_tiers'] ) ) {
		$html = '';
		foreach ( $trip['pricing_tiers'] as $tier ) {
			$html .= '<div class="cb-tier-name">' . esc_html( $tier['name'] ) . ' <span style="font-weight:normal;">(sleeps ' . (int) $tier['capacity_low'] . '&#8211;' . (int) $tier['capacity_high'] . ')</span></div>';

			if ( ! empty( $tier['occupancy_points'] ) ) {
				$rows = '';
				foreach ( $tier['occupancy_points'] as $point ) {
					$total = cb_pricing_occupancy_point_total( $point );
					$rows .= '<tr>'
						. '<td>' . (int) $point['occupancy_count'] . '</td>'
						. '<td>' . cb_proposal_format_money( $point['voyage_fare'] ) . '</td>'
						. '<td>' . cb_proposal_format_money( $point['taxes_fees'] ) . '</td>'
						. '<td>' . cb_proposal_format_money( $point['gratuities'] ) . '</td>'
						. '<td>' . cb_proposal_format_money( $point['insurance'] ) . '</td>'
						. '<td>' . cb_proposal_format_money( $point['discount'] ) . '</td>'
						. '<td><strong>' . cb_proposal_format_money( $total ) . '</strong></td>'
						. '</tr>';
				}
				$html .= '<table class="cb-table"><thead><tr>'
					. '<th># Sailors</th><th>Voyage Fare</th><th>Taxes &amp; Fees</th><th>Gratuities</th><th>Insurance</th><th>Discount</th><th>Total / Person</th>'
					. '</tr></thead><tbody>' . $rows . '</tbody></table>';
			}

			if ( ! empty( $tier['addons'] ) ) {
				$addon_labels = array();
				foreach ( $tier['addons'] as $addon ) {
					$addon_labels[] = esc_html( $addon['name'] ) . ( $addon['qty'] > 1 ? ' &times; ' . (int) $addon['qty'] : '' );
				}
				$html .= '<div class="cb-addon-list">Add-ons: ' . implode( ', ', $addon_labels ) . '</div>';
			}
		}
		return $html;
	}

	if ( $trip['single_price'] > 0 ) {
		return '<p><strong>Starting at ' . cb_proposal_format_money( $trip['single_price'] ) . ' / person</strong></p>';
	}

	return '<p><em>Contact us for pricing.</em></p>';
}

// Genuine wrap-around: a floated figure with content flowing beside it for
// the figure's height, continuing at full width once past it -- not a
// rigid two-column split. The trailing .cb-clear div is what actually
// closes the float before whatever comes next; confirmed directly against
// this install before building this, in both a tall-figure/short-content
// case and a short-figure/long-content case, that the wrap is genuine (text
// narrows beside the figure, then widens back out) with no overlap either
// into the figure or into subsequent content. Degrades to plain content,
// full width, when no photo is assigned.
function cb_proposal_render_wrap_html( $photo_path, $content_html, $image_side = 'left' ) {
	if ( ! $photo_path ) {
		return $content_html;
	}
	$figure = '<div class="cb-figure cb-image-' . esc_attr( $image_side ) . '"><img src="' . esc_attr( $photo_path ) . '"></div>';
	return $figure . $content_html . '<div class="cb-clear"></div>';
}

function cb_proposal_render_boilerplate_block_html( $title, $content, $photo_path = '', $image_side = 'left' ) {
	if ( '' === trim( (string) $content ) ) {
		return ''; // no text -> skip the whole block, including any photo assigned to it
	}
	$body = cb_proposal_render_wrap_html( $photo_path, wpautop( esc_html( $content ) ), $image_side );
	return '<div class="cb-boilerplate-block"><h2 class="cb-section-title">' . esc_html( $title ) . '</h2>' . $body . '</div>';
}

/* ==========================================================================
   5. Client Proposal PDF -- full HTML document assembler.
   ========================================================================== */
function cb_proposal_build_client_html( $data ) {
	$logo_id  = get_theme_mod( 'custom_logo' );
	$logo_src = $logo_id ? cb_proposal_resolve_pdf_image_path( get_attached_file( $logo_id ) ) : '';

	// "Your Trip": the one referenced trip's full detail -- title, type/
	// dates, complete itinerary table, complete pricing table(s) -- plain
	// heading (no card, no photo, no price-teaser box). Never takes an
	// Additional Photos image (title+meta+tables isn't a paragraph a photo
	// can wrap against).
	$trip_html = '';
	if ( $data['trip'] ) {
		$trip  = $data['trip'];
		$dates = cb_format_date_range( $trip['start_date'], $trip['end_date'] );

		$trip_html = '<div class="cb-trip-heading">'
			. '<h3>' . esc_html( $trip['title'] ) . '</h3>'
			. '<div class="cb-option-meta">' . esc_html( $trip['type_label'] ) . ' &middot; ' . esc_html( $dates ) . '</div>'
			. '</div>'
			. cb_proposal_render_itinerary_html( $trip )
			. cb_proposal_render_pricing_html( $trip );
	}

	$photos = $data['photos_by_section'];

	// Alternates image-left/copy-right, copy-left/image-right for visual
	// variety across the 4 photo-eligible boilerplate blocks, per mockup.
	// The closing Advisor's Desk note is text-only -- no photo argument at
	// all, so cb_proposal_render_wrap_html() degrades to plain content.
	$boilerplate_html = cb_proposal_render_boilerplate_block_html( "What's Included", $data['boilerplate']['whats_included'], $photos['whats_included'], 'left' )
		. cb_proposal_render_boilerplate_block_html( 'Why Travel Insurance Matters', $data['boilerplate']['insurance_importance'], $photos['insurance_importance'], 'right' )
		. cb_proposal_render_boilerplate_block_html( 'Travel Now, Pay Later', $data['boilerplate']['payment_plan'], $photos['payment_plan'], 'left' )
		. cb_proposal_render_boilerplate_block_html( 'Your Coordinator', $data['boilerplate']['coordinator_next_steps'], $photos['coordinator_next_steps'], 'right' )
		. cb_proposal_render_boilerplate_block_html( "From Your Travel Advisor's Desk", $data['boilerplate']['travel_advisor_desk'] );

	// Fixed global banner (Boilerplate Content settings page) -- the same
	// photo on every generated proposal, unlike the per-section Additional
	// Photos below (per-proposal, one per eligible section).
	$banner_html = $data['header_banner_path']
		? '<img class="cb-hero" src="' . esc_attr( $data['header_banner_path'] ) . '">'
		: '';

	// Overview: the proposal's own narrative always wins; the universal
	// fallback text (Boilerplate Content settings) only appears when a
	// proposal's own Overview Narrative was left blank.
	$overview_text = $data['overview'] ?: $data['boilerplate']['overview_fallback'];

	ob_start();
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="utf-8">
		<style><?php echo cb_proposal_get_template_css( $data['template_style'] ); ?></style>
	</head>
	<body>
		<div class="cb-header"><?php if ( $logo_src ) : ?><img src="<?php echo esc_attr( $logo_src ); ?>"><?php endif; ?></div>
		<div class="cb-footer">
			<span class="cb-disclaimer">Prices and availability subject to change. This proposal is not a booking confirmation. Generated <?php echo esc_html( $data['generated_date'] ); ?>.</span>
			&nbsp;&nbsp;<span class="cb-page-number"></span>
		</div>

		<?php echo $banner_html; ?>
		<h1><?php echo esc_html( $data['client_name'] ); ?></h1>
		<?php // The Overview photo shows independently of whether any overview text exists -- writing the narrative and choosing a photo are two separate admin decisions, per explicit confirmation, unlike the boilerplate blocks (which always have persistent content by design). ?>
		<?php if ( $overview_text || $photos['overview'] ) : ?>
			<?php echo cb_proposal_render_wrap_html( $photos['overview'], $overview_text ? wpautop( esc_html( $overview_text ) ) : '', 'left' ); ?>
		<?php endif; ?>

		<?php if ( $data['trip'] ) : ?>
			<h2 class="cb-section-title">Your Trip</h2>
			<?php echo $trip_html; ?>
		<?php endif; ?>

		<?php echo $boilerplate_html; ?>
	</body>
	</html>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   5a. Internal Data Sheet -- plain, no branding: no logo/banner, no
       Template Style, no sales-copy boilerplate (Overview, What's
       Included, Insurance, Payment Plan, Advisor's Desk closing note --
       none of that belongs in an internal operational document). Keeps
       Coordinator Role & Next Steps (still operationally useful for
       staff) and adds the one thing this document exists for: Internal
       Notes (vendor contacts, margin/profit notes, coordinator
       checklist), gathered via cb_proposal_build_pdf_data(..., true) --
       a separate call from the client PDF's (..., false), so
       cb_trip_get_internal_notes() is never even invoked while building
       the client-facing document.
   ========================================================================== */
function cb_proposal_get_internal_template_css() {
	return '
		* { box-sizing: border-box; }
		body { font-family: "Helvetica", "Arial", sans-serif; color: #16232B; font-size: 11px; line-height: 1.5; margin: 0; }
		h1, h2, h3 { font-family: Georgia, "Times New Roman", serif; margin: 0 0 8px; color: #16232B; }
		p { margin: 0 0 8px; }
		@page { margin: 65px 40px 55px 40px; }
		.cb-internal-banner { position: fixed; top: -45px; left: 0; right: 0; background: #16232B; color: #FBF3E7; padding: 6px 0; text-align: center; font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em; }
		.cb-footer { position: fixed; bottom: -40px; left: 0; right: 0; font-size: 8px; color: #666; text-align: center; border-top: 1px solid #ddd; padding-top: 6px; }
		.cb-footer .cb-page-number:after { content: "Page " counter(page); }
		.cb-section-title { font-size: 16px; margin-top: 22px; margin-bottom: 10px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
		.cb-trip-heading { margin-top: 16px; margin-bottom: 8px; page-break-inside: avoid; }
		.cb-trip-heading h3 { margin: 0 0 4px; font-size: 13px; }
		.cb-option-meta { font-family: "Courier New", monospace; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; color: #666; }
		table.cb-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 10px; }
		table.cb-table th, table.cb-table td { padding: 5px 7px; text-align: left; border-bottom: 1px solid #ddd; }
		table.cb-table th { font-family: "Courier New", monospace; font-size: 8px; text-transform: uppercase; letter-spacing: 0.04em; background: #eee; }
		.cb-tier-name { font-weight: bold; margin-top: 10px; margin-bottom: 4px; }
		.cb-addon-list { font-size: 9px; color: #444; margin: 4px 0 10px; }
		.cb-internal-block { margin-top: 18px; }
		.cb-internal-block h2 { font-size: 13px; }
	';
}

function cb_proposal_render_internal_block_html( $title, $content ) {
	if ( '' === trim( (string) $content ) ) {
		return '';
	}
	return '<div class="cb-internal-block"><h2>' . esc_html( $title ) . '</h2><div>' . wpautop( esc_html( $content ) ) . '</div></div>';
}

function cb_proposal_render_point_person_html( $point_person ) {
	$parts = array_filter( array(
		$point_person['name'] ?? '',
		$point_person['phone'] ?? '',
		$point_person['email'] ?? '',
	) );
	if ( ! $parts ) {
		return '';
	}
	return '<p><strong>Point Person:</strong> ' . implode( ' &middot; ', array_map( 'esc_html', $parts ) ) . '</p>';
}

function cb_proposal_render_roster_summary_html( $rows ) {
	if ( ! $rows ) {
		return '';
	}
	$body = '';
	foreach ( $rows as $row ) {
		$body .= '<tr>'
			. '<td>' . esc_html( $row['name'] ) . '</td>'
			. '<td>' . esc_html( $row['cabin_room'] ) . '</td>'
			. '<td>' . esc_html( cb_proposal_format_money( $row['balance_due'] ) ) . '</td>'
			. '<td>' . ( $row['paid_in_full'] ? 'Yes' : 'No' ) . '</td>'
			. '<td>' . ( $row['insurance_received'] ? 'Yes' : 'No' ) . '</td>'
			. '<td>' . ( $row['cc_auth_received'] ? 'Yes' : 'No' ) . '</td>'
			. '</tr>';
	}
	return '<h2 class="cb-section-title">Already-Signed-Up Clients</h2>'
		. '<table class="cb-table"><thead><tr>'
		. '<th>Name</th><th>Cabin/Room</th><th>Balance Due</th><th>Paid in Full</th><th>Insurance Waiver</th><th>CC Auth</th>'
		. '</tr></thead><tbody>' . $body . '</tbody></table>';
}

function cb_proposal_build_internal_html( $data ) {
	$trip_html = '';
	$roster_summary_html = '';
	if ( $data['trip'] ) {
		$trip  = $data['trip'];
		$dates = cb_format_date_range( $trip['start_date'], $trip['end_date'] );

		$trip_html = '<div class="cb-trip-heading">'
			. '<h3>' . esc_html( $trip['title'] ) . '</h3>'
			. '<div class="cb-option-meta">' . esc_html( $trip['type_label'] ) . ' &middot; ' . esc_html( $dates ) . '</div>'
			. '</div>'
			. cb_proposal_render_point_person_html( $trip['point_person'] ?? array() )
			. cb_proposal_render_itinerary_html( $trip )
			. cb_proposal_render_pricing_html( $trip );

		$roster_summary_html = cb_proposal_render_roster_summary_html( $trip['roster_summary'] ?? array() );
	}

	$notes = $data['trip']['internal_notes'] ?? array();

	$internal_notes_html = cb_proposal_render_internal_block_html( 'Vendor Contacts', $notes['vendor_contacts'] ?? '' )
		. cb_proposal_render_internal_block_html( 'Margin / Profit Notes', $notes['margin_notes'] ?? '' )
		. cb_proposal_render_internal_block_html( 'Coordinator Checklist', $notes['coordinator_checklist'] ?? '' )
		. cb_proposal_render_internal_block_html( "What's Needed From the Party", $notes['needed_from_party'] ?? '' )
		. cb_proposal_render_internal_block_html( 'Notes', $notes['general_notes'] ?? '' );

	$next_steps_html = cb_proposal_render_internal_block_html( 'Next Steps', $data['proposal_next_steps'] ?? '' );

	ob_start();
	?>
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="utf-8">
		<style><?php echo cb_proposal_get_internal_template_css(); ?></style>
	</head>
	<body>
		<div class="cb-internal-banner">Internal Data Sheet -- Not For Client Distribution</div>
		<div class="cb-footer">
			<span>Internal use only. Generated <?php echo esc_html( $data['generated_date'] ); ?>.</span>
			&nbsp;&nbsp;<span class="cb-page-number"></span>
		</div>

		<h1><?php echo esc_html( $data['client_name'] ); ?></h1>

		<?php if ( $data['trip'] ) : ?>
			<h2 class="cb-section-title">Trip Details</h2>
			<?php echo $trip_html; ?>
		<?php endif; ?>

		<?php echo $roster_summary_html; ?>

		<?php if ( $internal_notes_html ) : ?>
			<h2 class="cb-section-title">Internal Notes</h2>
			<?php echo $internal_notes_html; ?>
		<?php endif; ?>

		<?php echo $next_steps_html; ?>
	</body>
	</html>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   6. admin_post_ handler + streaming.
   ========================================================================== */
function cb_proposal_render_pdf( $html, $filename ) {
	$dompdf_options = new \Dompdf\Options();
	$dompdf_options->setChroot( array( ABSPATH, WP_CONTENT_DIR ) );
	$dompdf_options->setIsRemoteEnabled( false );

	$dompdf = new \Dompdf\Dompdf( $dompdf_options );
	$dompdf->loadHtml( $html );
	$dompdf->setPaper( 'letter', 'portrait' );
	$dompdf->render();
	$output = $dompdf->output();

	// Only safe to delete converted temp images after render() has actually
	// read them into the PDF -- not before.
	cb_proposal_cleanup_temp_images();

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	echo $output;
	exit;
}

add_action( 'admin_post_cb_generate_client_proposal', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions.' );
	}

	$proposal_id = isset( $_GET['proposal_id'] ) ? (int) $_GET['proposal_id'] : 0;
	if ( ! $proposal_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'cb_generate_client_proposal_' . $proposal_id ) ) {
		wp_die( 'Invalid request.' );
	}
	if ( 'cb_proposal' !== get_post_type( $proposal_id ) ) {
		wp_die( 'Proposal not found.' );
	}

	$data     = cb_proposal_build_pdf_data( $proposal_id, false );
	$html     = cb_proposal_build_client_html( $data );
	$filename = sanitize_file_name( $data['client_name'] . '-Client-Proposal.pdf' );

	cb_proposal_render_pdf( $html, $filename );
} );

add_action( 'admin_post_cb_generate_internal_data_sheet', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions.' );
	}

	$proposal_id = isset( $_GET['proposal_id'] ) ? (int) $_GET['proposal_id'] : 0;
	if ( ! $proposal_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'cb_generate_internal_data_sheet_' . $proposal_id ) ) {
		wp_die( 'Invalid request.' );
	}
	if ( 'cb_proposal' !== get_post_type( $proposal_id ) ) {
		wp_die( 'Proposal not found.' );
	}

	$data     = cb_proposal_build_pdf_data( $proposal_id, true );
	$html     = cb_proposal_build_internal_html( $data );
	$filename = sanitize_file_name( $data['client_name'] . '-Internal-Data-Sheet.pdf' );

	cb_proposal_render_pdf( $html, $filename );
} );

/* ==========================================================================
   7. "Generate PDFs" meta box on the Proposal edit screen.
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cb_proposal_generate_pdfs', 'Generate PDFs', 'cb_render_proposal_generate_pdfs_meta_box', 'cb_proposal', 'side', 'default' );
} );

function cb_render_proposal_generate_pdfs_meta_box( $post ) {
	if ( ! cb_proposal_get_trip_id( $post->ID ) ) {
		echo '<p style="color:#b32d2e;"><em>Choose a trip above before generating.</em></p>';
	}
	?>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url(
			admin_url( 'admin-post.php?action=cb_generate_client_proposal&proposal_id=' . $post->ID ),
			'cb_generate_client_proposal_' . $post->ID
		) ); ?>">Download Client Proposal PDF</a>
	</p>
	<p>
		<a class="button" href="<?php echo esc_url( wp_nonce_url(
			admin_url( 'admin-post.php?action=cb_generate_internal_data_sheet&proposal_id=' . $post->ID ),
			'cb_generate_internal_data_sheet_' . $post->ID
		) ); ?>">Download Internal Data Sheet</a>
		<br><span class="description">Includes vendor/margin/coordinator notes -- never share this one with the client.</span>
	</p>
	<?php
}
