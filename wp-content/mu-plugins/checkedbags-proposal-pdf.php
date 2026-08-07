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
   2. Shared data gatherer -- the ONE place that decides which trip/proposal
      fields feed the PDFs. Both templates call this; only the internal one
      passes $include_internal_notes = true, so cb_trip_get_internal_notes()
      is never even invoked while building the client-facing document --
      there's no shared code path for that data to leak through.
   ========================================================================== */
function cb_proposal_build_pdf_data( $proposal_id, $include_internal_notes = false ) {
	$proposal = get_post( $proposal_id );

	$hero_photo_id = (int) get_post_meta( $proposal_id, 'cb_proposal_hero_photo', true );

	$data = array(
		'client_name'     => $proposal->post_title,
		'overview'        => cb_proposal_get_overview( $proposal_id ),
		'template_style'  => cb_proposal_get_template_style( $proposal_id ),
		'hero_photo_path' => $hero_photo_id ? get_attached_file( $hero_photo_id ) : '',
		'boilerplate'     => cb_get_proposal_boilerplate(),
		'generated_date'  => date_i18n( 'F j, Y' ),
		'trips'           => array(),
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
			'cover_photo_path' => $cover_id ? get_attached_file( $cover_id ) : '',
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
