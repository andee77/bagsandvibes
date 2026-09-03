<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Public Trip Landing Page
 * Description: PHASE 12 of the trip-invite build -- revives the original
 *              "public trip marketing page" concept (dropped early in this
 *              build in favor of the internal PDF Proposal Generator),
 *              scoped differently this time: opt-in per trip (admin
 *              checkbox in checkedbags-trip-invites.php's Trip Code &
 *              Visibility box), rendered at the trip's own existing
 *              /trip/{slug}/ permalink (not a separate URL scheme) so
 *              Yoast SEO's existing per-post indexing/sitemap handling for
 *              the cb_trip post type applies with zero extra code. Wired
 *              into checkedbags-gate07.php's the_content filter -- shown to
 *              anyone who isn't a genuine roster member (or admin bypass)
 *              on a trip that has this enabled, in place of the usual
 *              sign-in/no-access message; the logged-in member experience
 *              is completely untouched.
 *
 *              Every data source here is reused, not new: Day-by-Day
 *              Itinerary (first row's port/country as the trip's
 *              "departs from"), Pricing Tiers/Occupancy Points (the
 *              pricing comparison strip), cover photo, cb_trip_code (the
 *              "Reserve Your Spot" CTA, reusing the existing [cbv_join]
 *              manual-approval registration flow verbatim -- no new
 *              signup mechanism), and the itinerary PDF. Only the hero
 *              tagline, Highlights repeater, and disclaimer text
 *              (checkedbags-trip-invites.php Phase 12 admin fields) are
 *              genuinely new content.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-trip-landing.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the full public landing page for a trip with its landing page
 * enabled. Called from checkedbags-gate07.php's the_content filter in
 * place of the sign-in/no-access message -- genuinely public, no login
 * check of any kind here.
 */
function cbv_render_public_trip_landing( $trip_id ) {
	$title      = get_the_title( $trip_id );
	$tagline    = get_post_meta( $trip_id, 'cb_public_landing_tagline', true );
	$disclaimer = get_post_meta( $trip_id, 'cb_public_landing_disclaimer', true );
	$trip_code  = get_post_meta( $trip_id, 'cb_trip_code', true );
	$start      = get_post_meta( $trip_id, 'cb_start_date', true );
	$end        = get_post_meta( $trip_id, 'cb_end_date', true );

	// Cover photo first (the real Phase 6 field), featured image as a
	// fallback -- standardizing on the same order [cbv_join] should
	// arguably also use, rather than that page's own cover-photo-unaware
	// featured-image-only approach.
	$cover_url = function_exists( 'cbv_get_trip_cover_photo_url' ) ? cbv_get_trip_cover_photo_url( $trip_id, 'large' ) : '';
	if ( ! $cover_url ) {
		$cover_url = get_the_post_thumbnail_url( $trip_id, 'large' ) ?: '';
	}

	// "Departs from" -- no dedicated trip-level field exists for this; the
	// Day-by-Day Itinerary's first row is the natural source, same data an
	// admin already fills in for planning purposes.
	$itinerary = function_exists( 'cb_trip_get_itinerary' ) ? cb_trip_get_itinerary( $trip_id ) : array();
	$departure = '';
	if ( ! empty( $itinerary ) ) {
		$first_stop = reset( $itinerary );
		$departure  = trim( implode( ', ', array_filter( array( $first_stop['port'] ?? '', $first_stop['country'] ?? '' ) ) ) );
	}

	$highlights = function_exists( 'cbv_get_trip_highlights' ) ? cbv_get_trip_highlights( $trip_id ) : array();
	$tiers      = function_exists( 'cb_trip_get_pricing_tiers' ) ? cb_trip_get_pricing_tiers( $trip_id ) : array();

	$join_url = $trip_code
		? home_url( '/join/?trip=' . rawurlencode( $trip_code ) )
		: home_url( '/join/' );

	$pdf_url = function_exists( 'cbv_get_trip_itinerary_pdf_url' ) ? cbv_get_trip_itinerary_pdf_url( $trip_id ) : '';

	// Real <img> with descriptive alt text, not a CSS background -- a
	// background-image is invisible to image search and screen readers
	// alike, confirmed as a genuine gap in the SEO audit (item 5).
	$cover_alt = $tagline ? ( $title . ' — ' . $tagline ) : $title;

	ob_start();
	?>
	<div class="cbv-public-landing">

		<div class="cbv-landing-hero">
			<?php if ( $cover_url ) : ?>
				<img class="cbv-landing-hero-img" src="<?php echo esc_url( $cover_url ); ?>" alt="<?php echo esc_attr( $cover_alt ); ?>">
			<?php endif; ?>
			<div class="cbv-landing-hero-scrim">
				<h1 class="cbv-landing-title"><?php echo esc_html( $title ); ?></h1>
				<?php if ( $tagline ) : ?>
					<p class="cbv-landing-tagline"><?php echo esc_html( $tagline ); ?></p>
				<?php endif; ?>
			</div>
			<div class="phase-tag cbv-landing-badge">
				<span class="phase-tag-gate">JOIN US</span>
				<span class="phase-tag-label">Reserve Your Spot</span>
			</div>
		</div>

		<?php if ( $departure || $start ) : ?>
		<div class="cbv-landing-basics">
			<?php if ( $departure ) : ?>
				<div class="cbv-landing-basic">
					<span class="cbv-landing-basic-label">Departs from</span>
					<span class="cbv-landing-basic-value"><?php echo esc_html( $departure ); ?></span>
				</div>
			<?php endif; ?>
			<?php if ( $start ) : ?>
				<div class="cbv-landing-basic">
					<span class="cbv-landing-basic-label">Dates</span>
					<span class="cbv-landing-basic-value"><?php echo esc_html( function_exists( 'cb_format_date_range' ) ? cb_format_date_range( $start, $end ) : $start ); ?></span>
				</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $highlights ) ) : ?>
		<div class="cbv-landing-section">
			<h2>Highlights</h2>
			<div class="cbv-landing-highlights">
				<?php foreach ( $highlights as $highlight ) : ?>
					<div class="cbv-landing-highlight-card">
						<i class="ti ti-<?php echo esc_attr( $highlight['icon'] ?: 'star' ); ?>" aria-hidden="true"></i>
						<h3><?php echo esc_html( $highlight['title'] ?? '' ); ?></h3>
						<p><?php echo esc_html( $highlight['description'] ?? '' ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( ! empty( $tiers ) ) : ?>
		<div class="cbv-landing-section">
			<h2>Choose Your Option</h2>
			<div class="cbv-landing-pricing">
				<?php foreach ( $tiers as $tier ) :
					$points = (array) ( $tier['occupancy_points'] ?? array() );
					$totals = array_map( 'cb_pricing_occupancy_point_total', $points );
					$from   = ! empty( $totals ) ? min( $totals ) : null;
					?>
					<div class="cbv-landing-pricing-card">
						<h3><?php echo esc_html( $tier['name'] ?? '' ); ?></h3>
						<?php if ( ! empty( $tier['description'] ) ) : ?>
							<p class="cbv-landing-pricing-desc"><?php echo esc_html( $tier['description'] ); ?></p>
						<?php endif; ?>
						<?php if ( null !== $from ) : ?>
							<p class="cbv-landing-pricing-amount">From $<?php echo esc_html( number_format_i18n( $from ) ); ?> <span>/ person</span></p>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<div class="cbv-landing-cta">
			<a class="btn btn-ticket" href="<?php echo esc_url( $join_url ); ?>">Reserve Your Spot</a>
			<p class="cbv-landing-cta-note">This submits a request to join — our team reviews new members before confirming your spot.</p>
			<?php if ( $pdf_url ) : ?>
				<a class="btn btn-ghost" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener">Download Full Itinerary PDF <i class="ti ti-download" aria-hidden="true"></i></a>
			<?php endif; ?>
		</div>

		<?php if ( $disclaimer ) : ?>
			<p class="cbv-landing-disclaimer">*<?php echo esc_html( $disclaimer ); ?></p>
		<?php endif; ?>

	</div>
	<?php
	return ob_get_clean();
}

/**
 * A trip without its public landing page enabled shows a thin "please sign
 * in" / "you don't have access" message to any non-member visitor -- not
 * useful content for search engines to index, especially since some trips
 * are now deliberately public. noindex those specifically, via WordPress
 * core's own wp_robots filter (not a hand-rolled competing <meta> tag)
 * so it merges cleanly with whatever Yoast SEO is already outputting.
 */
add_filter( 'wp_robots', function ( $robots ) {
	if ( is_singular( 'cb_trip' ) ) {
		$trip_id = get_queried_object_id();
		if ( ! get_post_meta( $trip_id, 'cb_public_landing_enabled', true ) ) {
			$robots['noindex'] = true;
		}
	}
	return $robots;
} );

/* ==========================================================================
   SEO audit fixes (items 1, 2, 6) -- Yoast has nothing to auto-generate a
   meta description or OG/Twitter image from here: the real content is
   injected via checkedbags-gate07.php's the_content filter rather than
   stored in post_content, cb_trip doesn't support excerpts, and the cover
   photo lives in the custom cb_cover_photo field, not WordPress's native
   featured image. Rather than fighting Yoast (faking post_content,
   calling set_post_thumbnail()), this feeds it through its own documented
   override filters, using data that already exists on the trip. Only
   applies to trips with the landing page enabled -- a non-opted-in trip is
   noindex'd above and shouldn't get a rich social preview for a page that
   actually shows "please sign in" when visited. Each filter respects
   whatever Yoast/the admin already produced (a manually-set per-trip Yoast
   field always wins) -- the computed fallback only fires when genuinely
   empty.
   ========================================================================== */
function cbv_trip_seo_description( $trip_id ) {
	$tagline = get_post_meta( $trip_id, 'cb_public_landing_tagline', true );
	$start   = get_post_meta( $trip_id, 'cb_start_date', true );
	$end     = get_post_meta( $trip_id, 'cb_end_date', true );

	$parts = array();
	if ( $tagline ) {
		$parts[] = $tagline;
	}
	if ( $start && function_exists( 'cb_format_date_range' ) ) {
		$parts[] = cb_format_date_range( $start, $end );
	}

	return trim( implode( ' — ', $parts ) );
}

/**
 * True only for a cb_trip with its public landing page enabled -- the
 * shared eligibility check every filter below uses.
 */
function cbv_trip_seo_eligible( $trip_id ) {
	return $trip_id && is_singular( 'cb_trip' ) && get_post_meta( $trip_id, 'cb_public_landing_enabled', true );
}

add_filter( 'wpseo_metadesc', function ( $description ) {
	if ( ! empty( $description ) ) {
		return $description;
	}
	$trip_id = get_queried_object_id();
	if ( ! cbv_trip_seo_eligible( $trip_id ) ) {
		return $description;
	}
	return cbv_trip_seo_description( $trip_id ) ?: $description;
} );

// Not explicitly requested, but the same data -- an OG image with no
// accompanying description reads as an incomplete social preview.
add_filter( 'wpseo_opengraph_desc', function ( $description ) {
	if ( ! empty( $description ) ) {
		return $description;
	}
	$trip_id = get_queried_object_id();
	if ( ! cbv_trip_seo_eligible( $trip_id ) ) {
		return $description;
	}
	return cbv_trip_seo_description( $trip_id ) ?: $description;
} );

/**
 * wpseo_opengraph_image only MODIFIES images Yoast already found for this
 * indexable (Presenters\Open_Graph\Image_Presenter::get() iterates
 * $presentation->open_graph_images and only calls this filter per existing
 * entry) -- with none found (no featured image, nothing in post_content),
 * that loop is empty and the filter never fires at all. Confirmed by
 * reading Yoast's own Open_Graph_Image_Generator: the correct, documented
 * way to ADD an image when Yoast found none is wpseo_add_opengraph_images,
 * which hands over the actual Images container to push a real image (with
 * the URL resolved back to its attachment ID, for correct width/height/type
 * metadata) onto before Yoast's own empty-check even runs.
 */
add_filter( 'wpseo_add_opengraph_images', function ( $image_container ) {
	$trip_id = get_queried_object_id();
	if ( ! cbv_trip_seo_eligible( $trip_id ) || ! function_exists( 'cbv_get_trip_cover_photo_url' ) ) {
		return;
	}
	$cover_url = cbv_get_trip_cover_photo_url( $trip_id, 'large' );
	if ( $cover_url ) {
		$image_container->add_image_by_url( $cover_url );
	}
} );

add_filter( 'wpseo_twitter_image', function ( $image ) {
	if ( ! empty( $image ) ) {
		return $image;
	}
	$trip_id = get_queried_object_id();
	if ( ! cbv_trip_seo_eligible( $trip_id ) || ! function_exists( 'cbv_get_trip_cover_photo_url' ) ) {
		return $image;
	}
	return cbv_get_trip_cover_photo_url( $trip_id, 'large' ) ?: $image;
} );

/**
 * Event schema (with a nested Offer for the cheapest current tier price),
 * appended to Yoast's own assembled graph via its documented
 * wpseo_schema_graph filter -- deliberately not a full custom
 * Abstract_Schema_Piece class, which ties into Yoast's internal class API
 * surface and would be more fragile across Yoast versions than this plain
 * array-in/array-out filter. Only emitted when the trip has a start date;
 * Event schema without one is incomplete/invalid, worse than omitting it.
 * location is Place-with-name-only, since no structured street address is
 * collected anywhere on a trip -- valid schema, just not as rich as Google's
 * own guidance recommends for full Event rich-result eligibility.
 */
add_filter( 'wpseo_schema_graph', function ( $graph, $context ) {
	$trip_id = get_queried_object_id();
	if ( ! cbv_trip_seo_eligible( $trip_id ) ) {
		return $graph;
	}

	$start = get_post_meta( $trip_id, 'cb_start_date', true );
	if ( ! $start ) {
		return $graph;
	}

	$end       = get_post_meta( $trip_id, 'cb_end_date', true );
	$cover_url = function_exists( 'cbv_get_trip_cover_photo_url' ) ? cbv_get_trip_cover_photo_url( $trip_id, 'large' ) : '';
	$itinerary = function_exists( 'cb_trip_get_itinerary' ) ? cb_trip_get_itinerary( $trip_id ) : array();
	$departure = '';
	if ( ! empty( $itinerary ) ) {
		$first_stop = reset( $itinerary );
		$departure  = trim( implode( ', ', array_filter( array( $first_stop['port'] ?? '', $first_stop['country'] ?? '' ) ) ) );
	}

	$event = array(
		'@type'               => 'Event',
		'@id'                 => get_permalink( $trip_id ) . '#event',
		'name'                => get_the_title( $trip_id ),
		'startDate'           => $start,
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'organizer'           => array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url(),
		),
	);
	if ( $end ) {
		$event['endDate'] = $end;
	}
	// Confirmed live: Yoast's schema JSON-LD pipeline double-encodes the
	// em/en-dash characters cbv_trip_seo_description() and
	// cb_format_date_range() produce (mojibake -- "—" becomes "â€"" in the
	// rendered <script type="application/ld+json">), even though the exact
	// same string renders correctly in the plain meta-description tag.
	// Sidestepping it here rather than patching Yoast's own pipeline --
	// only this schema-bound copy needs the plain-hyphen substitution.
	$description = str_replace( array( '—', '–' ), '-', cbv_trip_seo_description( $trip_id ) );
	if ( $description ) {
		$event['description'] = $description;
	}
	if ( $cover_url ) {
		$event['image'] = array( $cover_url );
	}
	if ( $departure ) {
		$event['location'] = array(
			'@type' => 'Place',
			'name'  => $departure,
		);
	}

	if ( function_exists( 'cb_trip_get_pricing_tiers' ) && function_exists( 'cb_pricing_occupancy_point_total' ) ) {
		$totals = array();
		foreach ( cb_trip_get_pricing_tiers( $trip_id ) as $tier ) {
			foreach ( (array) ( $tier['occupancy_points'] ?? array() ) as $point ) {
				$totals[] = cb_pricing_occupancy_point_total( $point );
			}
		}
		if ( ! empty( $totals ) ) {
			$trip_code       = get_post_meta( $trip_id, 'cb_trip_code', true );
			$event['offers'] = array(
				'@type'         => 'Offer',
				'price'         => (string) min( $totals ),
				'priceCurrency' => 'USD',
				'availability'  => 'https://schema.org/InStock',
				'url'           => $trip_code ? home_url( '/join/?trip=' . rawurlencode( $trip_code ) ) : home_url( '/join/' ),
			);
		}
	}

	$graph[] = $event;
	return $graph;
}, 10, 2 );
