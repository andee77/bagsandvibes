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

	$additional_photo_paths = array();
	foreach ( cb_proposal_get_additional_photo_ids( $proposal_id ) as $photo_id ) {
		$path = get_attached_file( $photo_id );
		if ( $path ) {
			$additional_photo_paths[] = cb_proposal_resolve_pdf_image_path( $path );
		}
	}

	$data = array(
		'client_name'            => $proposal->post_title,
		'overview'               => cb_proposal_get_overview( $proposal_id ),
		'template_style'         => cb_proposal_get_template_style( $proposal_id ),
		'header_banner_path'     => $banner_id ? cb_proposal_resolve_pdf_image_path( get_attached_file( $banner_id ) ) : '',
		'additional_photo_paths' => $additional_photo_paths,
		'boilerplate'            => $boilerplate,
		'generated_date'         => date_i18n( 'F j, Y' ),
		'trips'                  => array(),
	);

	foreach ( cb_proposal_get_trip_ids( $proposal_id ) as $trip_id ) {
		// A trip referenced when this proposal was built may have since
		// been deleted -- skip stale references rather than trusting them.
		if ( 'cb_trip' !== get_post_type( $trip_id ) ) {
			continue;
		}

		$cover_id   = (int) get_post_meta( $trip_id, 'cb_cover_photo', true );
		$terms      = get_the_terms( $trip_id, 'cb_trip_type' );
		$type_label = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';

		$trip_data = array(
			'id'               => $trip_id,
			'title'            => get_the_title( $trip_id ),
			'type_label'       => $type_label,
			'start_date'       => get_post_meta( $trip_id, 'cb_start_date', true ),
			'end_date'         => get_post_meta( $trip_id, 'cb_end_date', true ),
			'cover_photo_path' => $cover_id ? cb_proposal_resolve_pdf_image_path( get_attached_file( $cover_id ) ) : '',
			'itinerary'        => cb_trip_get_itinerary( $trip_id ),
			'pricing_tiers'    => cb_trip_get_pricing_tiers( $trip_id ),
			'single_price'     => (float) get_post_meta( $trip_id, 'cb_price', true ),
		);

		if ( $include_internal_notes ) {
			$trip_data['internal_notes'] = cb_trip_get_internal_notes( $trip_id );
		}

		$data['trips'][] = $trip_data;
	}

	return $data;
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
		.cb-option-card { padding: 14px; margin-bottom: 18px; page-break-inside: avoid; overflow: hidden; }
		.cb-cover-photo { float: left; width: 46%; height: auto; margin: 0 14px 8px 0; border-radius: 6px; }
		.cb-option-meta { font-family: "Courier New", monospace; font-size: 9px; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
		table.cb-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 10px; }
		table.cb-table th, table.cb-table td { padding: 5px 7px; text-align: left; border-bottom: 1px solid #ddd; }
		table.cb-table th { font-family: "Courier New", monospace; font-size: 8px; text-transform: uppercase; letter-spacing: 0.04em; }
		.cb-tier-name { font-weight: bold; margin-top: 10px; margin-bottom: 4px; }
		.cb-addon-list { font-size: 9px; color: #444; margin: 4px 0 10px; }
		.cb-boilerplate-block { margin-top: 18px; page-break-inside: avoid; }
		.cb-disclaimer { font-size: 8px; color: #888; }
		.cb-gallery { margin-top: 18px; }
		.cb-gallery img { width: 32%; height: 110px; object-fit: cover; margin: 0 1.3% 8px 0; display: inline-block; }
	';

	if ( 'structured_grid' === $style ) {
		return $shared . '
			h1, h2, h3 { font-family: "Helvetica", "Arial", sans-serif; font-weight: bold; text-transform: uppercase; letter-spacing: 0.03em; color: #1B3A4B; }
			.cb-section-title { border-bottom: 2px solid #1B3A4B; padding-bottom: 4px; }
			.cb-option-card { border: 1px solid #1B3A4B; background: #fff; }
			.cb-option-meta { color: #2E7D6E; }
			table.cb-table th { background: #1B3A4B; color: #FBF3E7; }
			.cb-tier-name { color: #1B3A4B; }
		';
	}

	// Default: warm_editorial
	return $shared . '
		h1, h2, h3 { font-style: italic; color: #FF6B4A; }
		.cb-section-title { color: #FF6B4A; }
		.cb-option-card { border-radius: 10px; background: #FBF3E7; }
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

function cb_proposal_render_boilerplate_block_html( $title, $content ) {
	if ( '' === trim( (string) $content ) ) {
		return '';
	}
	return '<div class="cb-boilerplate-block"><h2 class="cb-section-title">' . esc_html( $title ) . '</h2><div>' . wpautop( esc_html( $content ) ) . '</div></div>';
}

/* ==========================================================================
   5. Client Proposal PDF -- full HTML document assembler.
   ========================================================================== */
function cb_proposal_build_client_html( $data ) {
	$logo_id  = get_theme_mod( 'custom_logo' );
	$logo_src = $logo_id ? cb_proposal_resolve_pdf_image_path( get_attached_file( $logo_id ) ) : '';

	$options_html = '';
	foreach ( $data['trips'] as $trip ) {
		$dates = cb_format_date_range( $trip['start_date'], $trip['end_date'] );
		$cover_html = $trip['cover_photo_path']
			? '<img class="cb-cover-photo" src="' . esc_attr( $trip['cover_photo_path'] ) . '">'
			: '';

		$options_html .= '<div class="cb-option-card">'
			. $cover_html
			. '<h2>' . esc_html( $trip['title'] ) . '</h2>'
			. '<div class="cb-option-meta">' . esc_html( $trip['type_label'] ) . ' &middot; ' . esc_html( $dates ) . '</div>'
			. '<div style="clear:both;"></div>'
			. cb_proposal_render_itinerary_html( $trip )
			. cb_proposal_render_pricing_html( $trip )
			. '</div>';
	}

	$boilerplate_html = cb_proposal_render_boilerplate_block_html( "What's Included", $data['boilerplate']['whats_included'] )
		. cb_proposal_render_boilerplate_block_html( 'Why Travel Insurance Matters', $data['boilerplate']['insurance_importance'] )
		. cb_proposal_render_boilerplate_block_html( 'Travel Now, Pay Later', $data['boilerplate']['payment_plan'] )
		. cb_proposal_render_boilerplate_block_html( 'Your Coordinator', $data['boilerplate']['coordinator_next_steps'] );

	// Fixed global banner (Boilerplate Content settings page) -- the same
	// photo on every generated proposal, unlike each trip's own cover photo
	// above (unchanged, per-trip) or the gallery below (per-proposal, varies).
	$banner_html = $data['header_banner_path']
		? '<img class="cb-hero" src="' . esc_attr( $data['header_banner_path'] ) . '">'
		: '';

	// Additional Photos: a per-proposal gallery for visual variety, placed
	// after the trip comparison and before the closing boilerplate content --
	// not interleaved per-trip, since there's no per-photo-to-trip mapping.
	$gallery_html = '';
	if ( ! empty( $data['additional_photo_paths'] ) ) {
		$gallery_html .= '<div class="cb-gallery">';
		foreach ( $data['additional_photo_paths'] as $photo_path ) {
			$gallery_html .= '<img src="' . esc_attr( $photo_path ) . '">';
		}
		$gallery_html .= '</div>';
	}

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
		<?php if ( $data['overview'] ) : ?>
			<div><?php echo wpautop( esc_html( $data['overview'] ) ); ?></div>
		<?php endif; ?>

		<h2 class="cb-section-title">Your Options</h2>
		<?php echo $options_html; ?>

		<?php echo $gallery_html; ?>

		<?php echo $boilerplate_html; ?>
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

/* ==========================================================================
   7. "Generate PDFs" meta box on the Proposal edit screen.
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cb_proposal_generate_pdfs', 'Generate PDFs', 'cb_render_proposal_generate_pdfs_meta_box', 'cb_proposal', 'side', 'default' );
} );

function cb_render_proposal_generate_pdfs_meta_box( $post ) {
	$trip_count = count( cb_proposal_get_trip_ids( $post->ID ) );

	if ( $trip_count < 2 ) {
		echo '<p style="color:#b32d2e;"><em>Add at least 2 trip options above for a meaningful comparison.</em></p>';
	}
	?>
	<p>
		<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url(
			admin_url( 'admin-post.php?action=cb_generate_client_proposal&proposal_id=' . $post->ID ),
			'cb_generate_client_proposal_' . $post->ID
		) ); ?>">Download Client Proposal PDF</a>
	</p>
	<?php
}
