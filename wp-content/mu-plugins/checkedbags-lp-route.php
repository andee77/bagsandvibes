<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Landing Redesign Route
 * Description: Step 6 of the public trip landing redesign: the Route
 *              section (one card per day, worked out from the Day-by-Day
 *              Itinerary), the port-code chain, the automatic time line, and
 *              the "Route days" trip box that adds a title, text, sticker
 *              and photo to any day. Only the new design calls any of this
 *              (preview with ?preview=new, or live per trip once switched on
 *              in checkedbags-lp-core.php); the current public design and
 *              the itinerary editor / proposal PDF are untouched.
 *
 *              Days: itinerary rows are grouped by date (rows without a date
 *              by their Day number). "DAY 01" counts from the trip's start
 *              date, so the typed Day box (trip 181 starts at 0) does not
 *              matter. Each day's extras are stored by that day number, so
 *              moving the whole trip to new dates keeps them with the right
 *              day.
 *
 *              Media rule: Virgin images are used UNALTERED (see
 *              docs/media-sources.md); the focus point keeps the subject in
 *              view and a frame never hides Virgin's logo or branding.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-route.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Meta. Presentation-only; nothing here is read by the current design.
   ========================================================================== */
add_action( 'init', function () {
	$auth = function () {
		return current_user_can( 'edit_posts' );
	};
	register_post_meta( 'cb_trip', 'cbv_lp_route_heading', array(
		'type'              => 'string',
		'single'            => true,
		'default'           => '',
		'show_in_rest'      => false,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => $auth,
	) );
	register_post_meta( 'cb_trip', 'cbv_lp_route_days', array(
		'type'          => 'array',
		'single'        => true,
		'default'       => array(),
		'show_in_rest'  => false,
		'auth_callback' => $auth,
	) );
} );

/* ==========================================================================
   2. Small pure helpers (no globals, no writes), tested directly.
   ========================================================================== */

/** The section heading when the trip has not typed one. */
function cbv_lp_route_default_heading( $event_type ) {
	$map = array(
		'cruise'      => 'The Route',
		'destination' => 'The Route',
		'resort'      => 'The Plan',
		'corporate'   => 'The Agenda',
	);
	return $map[ $event_type ] ?? 'The Route';
}

/** "17:00" -> "5:00 pm" ('' when it is not a usable time). */
function cbv_lp_route_time( $value ) {
	$value = trim( (string) $value );
	if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)/', $value, $m ) ) {
		return '';
	}
	$h = (int) $m[1];
	return ( 0 === $h % 12 ? 12 : $h % 12 ) . ':' . $m[2] . ( $h < 12 ? ' am' : ' pm' );
}

/** An itinerary row's type, normalised: embarkation / arrival / departure / sea / disembarkation / ''. */
function cbv_lp_route_row_type( $row ) {
	$desc = strtolower( trim( (string) ( $row['description'] ?? '' ) ) );
	$port = strtolower( trim( (string) ( $row['port'] ?? '' ) ) );
	if ( 'at sea' === $desc || 'at sea' === $port ) {
		return 'sea';
	}
	return in_array( $desc, array( 'embarkation', 'arrival', 'departure', 'disembarkation' ), true ) ? $desc : '';
}

/**
 * The days of a trip, worked out from its itinerary rows.
 *   - Rows with a date are grouped by date; "n" counts from the start date
 *     (the start date is day 1). With no start date, the earliest row date is
 *     day 1.
 *   - Rows without a date are grouped by their Day number, shifted so the
 *     lowest Day number is day 1 (trip 181 numbers its rows from 0).
 *   - Rows with neither a usable date nor a Day number are skipped.
 * Returns a list ordered by day: each item is
 *   array( 'n' => 3, 'date' => '2027-10-27', 'rows' => [...], 'ports' => ['Puerto Plata ...'],
 *          'at_sea' => bool, 'place' => 'Puerto Plata ...' or 'At sea' )
 */
function cbv_lp_route_days( $itinerary, $start = '' ) {
	$rows = array();
	foreach ( (array) $itinerary as $row ) {
		if ( is_array( $row ) ) {
			$rows[] = $row;
		}
	}
	if ( ! $rows ) {
		return array();
	}

	$to_ts = function ( $ymd ) {
		$ymd = trim( (string) $ymd );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
			return false;
		}
		return strtotime( $ymd . ' 00:00:00 UTC' );
	};

	$start_ts = $to_ts( $start );
	if ( false === $start_ts ) {
		foreach ( $rows as $row ) {
			$ts = $to_ts( $row['date'] ?? '' );
			if ( false !== $ts && ( false === $start_ts || $ts < $start_ts ) ) {
				$start_ts = $ts;
			}
		}
	}
	$min_day = null;
	foreach ( $rows as $row ) {
		if ( false === $to_ts( $row['date'] ?? '' ) && isset( $row['day'] ) && is_numeric( $row['day'] ) ) {
			$min_day = null === $min_day ? (int) $row['day'] : min( $min_day, (int) $row['day'] );
		}
	}

	$days = array();
	foreach ( $rows as $row ) {
		$ts = $to_ts( $row['date'] ?? '' );
		if ( false !== $ts && false !== $start_ts ) {
			$n    = (int) round( ( $ts - $start_ts ) / DAY_IN_SECONDS ) + 1;
			$date = gmdate( 'Y-m-d', $ts );
		} elseif ( isset( $row['day'] ) && is_numeric( $row['day'] ) && null !== $min_day ) {
			$n    = (int) $row['day'] - $min_day + 1;
			$date = '';
		} else {
			continue;
		}
		if ( $n < 1 || $n > 366 ) {
			continue;
		}
		if ( ! isset( $days[ $n ] ) ) {
			$days[ $n ] = array( 'n' => $n, 'date' => $date, 'rows' => array() );
		}
		$days[ $n ]['rows'][] = $row;
	}
	ksort( $days );

	foreach ( $days as $n => $day ) {
		$ports = array();
		$seen  = array();
		foreach ( $day['rows'] as $row ) {
			$port = trim( (string) ( $row['port'] ?? '' ) );
			if ( '' === $port || 'sea' === cbv_lp_route_row_type( $row ) ) {
				continue;
			}
			if ( ! isset( $seen[ strtolower( $port ) ] ) ) {
				$seen[ strtolower( $port ) ] = true;
				$ports[]                     = $port;
			}
		}
		$days[ $n ]['ports']  = $ports;
		$days[ $n ]['at_sea'] = ! $ports;
		$days[ $n ]['place']  = $ports ? implode( ' · ', $ports ) : 'At sea';
	}
	return array_values( $days );
}

/**
 * The automatic time line for one day (approved wording, 2026-10-05).
 * $next is the following day (for spotting an overnight stay); $cruise
 * switches the cruise words (Sail-away, Docked, Tender port, In port).
 */
function cbv_lp_route_time_line( $day, $next = null, $cruise = true ) {
	$by = array();
	foreach ( (array) ( $day['rows'] ?? array() ) as $row ) {
		$type = cbv_lp_route_row_type( $row );
		if ( '' !== $type && ! isset( $by[ $type ] ) ) {
			$by[ $type ] = $row;
		}
	}
	if ( ! $by || array( 'sea' ) === array_keys( $by ) ) {
		return '';
	}

	if ( isset( $by['embarkation'] ) ) {
		$t = cbv_lp_route_time( $by['embarkation']['time'] ?? '' );
		return '' !== $t ? ( $cruise ? 'Sail-away ' : 'Departs ' ) . $t : '';
	}
	if ( isset( $by['disembarkation'] ) ) {
		$port = trim( (string) ( $by['disembarkation']['port'] ?? '' ) );
		$t    = cbv_lp_route_time( $by['disembarkation']['time'] ?? '' );
		if ( '' === $port ) {
			return '' !== $t ? 'Back at ' . $t : '';
		}
		return 'Back in ' . $port . ( '' !== $t ? ' at ' . $t : '' );
	}

	$arrive = isset( $by['arrival'] ) ? cbv_lp_route_time( $by['arrival']['time'] ?? '' ) : '';
	$depart = isset( $by['departure'] ) ? cbv_lp_route_time( $by['departure']['time'] ?? '' ) : '';

	if ( isset( $by['arrival'], $by['departure'] ) ) {
		$tender = '';
		foreach ( (array) $day['rows'] as $row ) {
			$mode = strtolower( trim( (string) ( $row['tender_mode'] ?? '' ) ) );
			if ( 'tender' === $mode ) {
				$tender = 'tender';
				break;
			}
			if ( 'dock' === $mode ) {
				$tender = 'dock';
			}
		}
		if ( '' !== $arrive && '' !== $depart ) {
			$span = $arrive . ' – ' . $depart;
		} elseif ( '' !== $arrive ) {
			$span = 'from ' . $arrive;
		} elseif ( '' !== $depart ) {
			$span = 'until ' . $depart;
		} else {
			$span = '';
		}
		if ( ! $cruise ) {
			return $span;
		}
		if ( 'tender' === $tender ) {
			return 'Tender port' . ( '' !== $span ? ' · ' . $span : '' );
		}
		if ( '' === $span ) {
			return '';
		}
		return ( 'dock' === $tender ? 'Docked ' : 'In port ' ) . $span;
	}

	if ( isset( $by['arrival'] ) ) {
		if ( '' === $arrive ) {
			return '';
		}
		// Overnight: the next day leaves from the same port without arriving again.
		$overnight = false;
		$port      = strtolower( trim( (string) ( $by['arrival']['port'] ?? '' ) ) );
		if ( $next && '' !== $port ) {
			$next_types = array();
			$next_ports = array();
			foreach ( (array) ( $next['rows'] ?? array() ) as $row ) {
				$next_types[] = cbv_lp_route_row_type( $row );
				if ( 'departure' === cbv_lp_route_row_type( $row ) ) {
					$next_ports[] = strtolower( trim( (string) ( $row['port'] ?? '' ) ) );
				}
			}
			$overnight = in_array( $port, $next_ports, true ) && ! in_array( 'arrival', $next_types, true );
		}
		return 'Arrives ' . $arrive . ( $overnight ? ' · overnight' : '' );
	}
	if ( isset( $by['departure'] ) ) {
		return '' !== $depart ? 'Departs ' . $depart : '';
	}
	return '';
}

/**
 * "MIA → SEA → POP → SEA → BIM → MIA": one entry per day ("SEA" for a day at
 * sea, every port's code for a port day). A code typed on any row for a port
 * is used for every row with that port name (as on the boarding pass).
 * Returns the list of codes, or null when any port day has no code or there
 * are fewer than two entries.
 */
function cbv_lp_route_chain( $days, $itinerary ) {
	$codes = array();
	foreach ( (array) $itinerary as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$key  = strtolower( trim( (string) ( $row['port'] ?? '' ) ) );
		$code = function_exists( 'cbv_lp_clean_stop_code' ) ? cbv_lp_clean_stop_code( $row['stop_code'] ?? '' ) : '';
		if ( '' !== $key && '' !== $code && ! isset( $codes[ $key ] ) ) {
			$codes[ $key ] = $code;
		}
	}
	$chain = array();
	foreach ( (array) $days as $day ) {
		if ( ! empty( $day['at_sea'] ) ) {
			$chain[] = 'SEA';
			continue;
		}
		foreach ( (array) $day['ports'] as $port ) {
			$key = strtolower( $port );
			if ( ! isset( $codes[ $key ] ) ) {
				return null;
			}
			$chain[] = $codes[ $key ];
		}
	}
	return count( $chain ) >= 2 ? $chain : null;
}

/** One posted day's extras -> what gets stored ('' / 0 / false for empty fields). */
function cbv_lp_sanitize_route_day( $raw ) {
	$raw  = is_array( $raw ) ? $raw : array();
	$text = function ( $key, $max ) use ( $raw ) {
		$v = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? sanitize_text_field( (string) $raw[ $key ] ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
	};
	$body = isset( $raw['text'] ) && is_scalar( $raw['text'] ) ? sanitize_textarea_field( (string) $raw['text'] ) : '';
	$body = function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 1500 ) : substr( $body, 0, 1500 );
	$focus = isset( $raw['focus'] ) && is_scalar( $raw['focus'] ) ? sanitize_key( (string) $raw['focus'] ) : '';
	if ( ! function_exists( 'cbv_lp_focus_options' ) || ! isset( cbv_lp_focus_options()[ $focus ] ) ) {
		$focus = '';
	}
	$style = isset( $raw['sticker_style'] ) && 'highlight' === $raw['sticker_style'] ? 'highlight' : 'standard';

	return array(
		'title'         => $text( 'title', 80 ),
		'text'          => $body,
		'sticker'       => $text( 'sticker', 60 ),
		'sticker_style' => $style,
		'photo'         => function_exists( 'cbv_lp_clean_attachment_id' ) ? cbv_lp_clean_attachment_id( $raw['photo'] ?? 0, 'image' ) : 0,
		'focus'         => $focus,
		'hide_time'     => ! empty( $raw['hide_time'] ),
	);
}

/** True when a sanitized day has nothing the admin typed or chose. */
function cbv_lp_route_day_is_empty( $day ) {
	return '' === $day['title'] && '' === $day['text'] && '' === $day['sticker'] && ! $day['photo'] && '' === $day['focus'] && ! $day['hide_time'] && 'standard' === $day['sticker_style'];
}

/**
 * Posted days merged into the stored ones: posted days replace (and are
 * dropped when empty); stored days that were not posted (no longer in the
 * itinerary) are kept, so taking a day out of the itinerary and putting it
 * back does not lose its content.
 */
function cbv_lp_merge_route_days( $stored, $posted ) {
	$out = array();
	foreach ( (array) $stored as $n => $day ) {
		if ( is_numeric( $n ) && (int) $n >= 1 && (int) $n <= 366 && is_array( $day ) ) {
			$out[ (int) $n ] = cbv_lp_sanitize_route_day( $day );
		}
	}
	foreach ( (array) $posted as $n => $raw ) {
		if ( ! is_numeric( $n ) || (int) $n < 1 || (int) $n > 366 ) {
			continue;
		}
		$clean = cbv_lp_sanitize_route_day( $raw );
		if ( cbv_lp_route_day_is_empty( $clean ) ) {
			unset( $out[ (int) $n ] );
		} else {
			$out[ (int) $n ] = $clean;
		}
	}
	ksort( $out );
	return $out;
}

/* ==========================================================================
   3. What the route shows for a trip.
   ========================================================================== */
function cbv_lp_route_data( $trip_id ) {
	$trip_id = (int) $trip_id;
	if ( function_exists( 'cbv_lp_section_enabled' ) && ! cbv_lp_section_enabled( $trip_id, 'route' ) ) {
		return null;
	}
	if ( ! get_post_meta( $trip_id, 'cb_public_landing_show_itinerary', true ) ) {
		return null;
	}
	$itinerary = function_exists( 'cb_trip_get_itinerary' ) ? cb_trip_get_itinerary( $trip_id ) : array();
	$days      = cbv_lp_route_days( $itinerary, get_post_meta( $trip_id, 'cb_start_date', true ) );
	if ( ! $days ) {
		return null;
	}

	$type    = function_exists( 'cbv_lp_event_type' ) ? cbv_lp_event_type( $trip_id ) : 'cruise';
	$labels  = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
	$cruise  = 'cruise' === $type;
	$extras  = get_post_meta( $trip_id, 'cbv_lp_route_days', true );
	$extras  = is_array( $extras ) ? $extras : array();
	$tok     = function ( $text ) use ( $trip_id ) {
		return trim( function_exists( 'cbv_lp_apply_tokens' ) ? cbv_lp_apply_tokens( (string) $text, $trip_id ) : (string) $text );
	};
	$heading = $tok( get_post_meta( $trip_id, 'cbv_lp_route_heading', true ) );

	$cards = array();
	foreach ( $days as $i => $day ) {
		$x     = cbv_lp_sanitize_route_day( $extras[ $day['n'] ] ?? array() );
		$auto  = cbv_lp_route_time_line( $day, $days[ $i + 1 ] ?? null, $cruise );
		$ts    = '' !== $day['date'] ? strtotime( $day['date'] . ' 12:00:00 UTC' ) : false;
		$parts = array( 'Day ' . str_pad( (string) $day['n'], 2, '0', STR_PAD_LEFT ) );
		if ( $ts ) {
			$parts[] = gmdate( 'D M j', $ts );
		}
		$parts[] = $day['place'];
		$title   = $tok( $x['title'] );

		$cards[] = array(
			'n'             => $day['n'],
			'eyebrow'       => implode( ' · ', $parts ),
			'title'         => '' !== $title ? $title : $day['place'],
			'time'          => $x['hide_time'] ? '' : $auto,
			'auto_time'     => $auto,
			'text'          => '' !== trim( $x['text'] ) && function_exists( 'cbv_lp_blocks' ) ? cbv_lp_blocks( $x['text'], $trip_id ) : '',
			'sticker'       => $tok( $x['sticker'] ),
			'sticker_style' => $x['sticker_style'],
			'photo_id'      => $x['photo'],
			'focus'         => function_exists( 'cbv_lp_focus_position' ) ? cbv_lp_focus_position( $x['focus'] ) : 'center center',
		);
	}

	return array(
		'section' => $labels['sec_route'] ?? 'The route',
		'heading' => '' !== $heading ? $heading : cbv_lp_route_default_heading( $type ),
		'chain'   => cbv_lp_route_chain( $days, $itinerary ),
		'cards'   => $cards,
	);
}

/* ==========================================================================
   4. Markup.
   ========================================================================== */
function cbv_lp_render_route( $trip_id ) {
	$d = cbv_lp_route_data( $trip_id );
	if ( ! $d ) {
		return '';
	}
	$gate = function_exists( 'cbv_lp_gate_label' ) ? cbv_lp_gate_label( $d['section'] ) : $d['section'];

	ob_start();
	?>
	<section class="cbv-lp-route" id="route" aria-labelledby="cbv-lp-route-title">
		<div class="cbv-lp-inner cbv-lp-route-inner">
			<div class="cbv-lp-route-head">
				<div class="cbv-lp-route-copy">
					<p class="cbv-lp-gate"><?php echo esc_html( $gate ); ?></p>
					<h2 class="cbv-lp-h2" id="cbv-lp-route-title"><?php echo esc_html( $d['heading'] ); ?></h2>
				</div>
				<?php if ( $d['chain'] ) : ?>
					<p class="cbv-lp-route-chain">
						<span class="cbv-lp-sr"><?php echo esc_html( 'Route: ' . implode( ', ', $d['chain'] ) ); ?></span>
						<span aria-hidden="true"><?php echo esc_html( implode( ' → ', $d['chain'] ) ); ?></span>
					</p>
				<?php endif; ?>
			</div>
			<ol class="cbv-lp-days">
				<?php foreach ( $d['cards'] as $c ) : ?>
					<li class="cbv-lp-day">
						<?php
						$img = $c['photo_id'] ? wp_get_attachment_image( $c['photo_id'], 'large', false, array(
							'class'   => 'cbv-lp-day-img',
							'style'   => 'object-position:' . $c['focus'] . ';',
							'loading' => 'lazy',
						) ) : '';
						if ( '' !== $img ) {
							echo $img; // phpcs:ignore WordPress.Security.EscapeOutput -- core image markup
						} else {
							echo '<span class="cbv-lp-day-strip" aria-hidden="true"></span>';
						}
						?>
						<div class="cbv-lp-day-body">
							<p class="cbv-lp-day-eyebrow"><?php echo esc_html( $c['eyebrow'] ); ?></p>
							<h3 class="cbv-lp-day-title"><?php echo esc_html( $c['title'] ); ?></h3>
							<?php if ( '' !== $c['time'] ) : ?>
								<p class="cbv-lp-day-time"><?php echo esc_html( $c['time'] ); ?></p>
							<?php endif; ?>
							<?php if ( '' !== $c['text'] ) : ?>
								<div class="cbv-lp-day-text"><?php echo $c['text']; // phpcs:ignore WordPress.Security.EscapeOutput -- cbv_lp_blocks escapes first ?></div>
							<?php endif; ?>
							<?php if ( '' !== $c['sticker'] ) : ?>
								<p class="cbv-lp-sticker cbv-lp-sticker--<?php echo esc_attr( $c['sticker_style'] ); ?>"><?php echo esc_html( $c['sticker'] ); ?></p>
							<?php endif; ?>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Today's itinerary table is the route's job in the new design, so it is
 * removed from the legacy content underneath (as the old hero was in Step
 * 4). If the markup can't be parsed, or the table isn't found, the content
 * is returned unchanged.
 */
function cbv_lp_strip_legacy_itinerary( $html ) {
	if ( '' === trim( (string) $html ) || ! class_exists( 'DOMDocument' ) ) {
		return $html;
	}
	$prev = libxml_use_internal_errors( true );
	$dom  = new DOMDocument();
	$ok   = $dom->loadHTML( '<?xml encoding="utf-8" ?><div id="cbv-lp-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	if ( ! $ok ) {
		return $html;
	}

	$xp      = new DOMXPath( $dom );
	$nodes   = $xp->query( '//div[contains(concat(" ", normalize-space(@class), " "), " cbv-landing-section ")][.//div[contains(concat(" ", normalize-space(@class), " "), " cbv-landing-itinerary ")]]' );
	$removed = 0;
	foreach ( iterator_to_array( $nodes ) as $node ) {
		if ( $node->parentNode ) {
			$node->parentNode->removeChild( $node );
			$removed++;
		}
	}
	if ( ! $removed ) {
		return $html;
	}
	$found = $xp->query( '//div[@id="cbv-lp-root"]' );
	$root  = $found->length ? $found->item( 0 ) : null;
	if ( ! $root ) {
		return $html;
	}
	$out = '';
	foreach ( $root->childNodes as $child ) {
		$out .= $dom->saveHTML( $child );
	}
	return $out;
}

/* ==========================================================================
   5. Trip edit screen: the "Route days" box. The media chooser and its
      script come from checkedbags-lp-hero.php (cbv_lp_media_field).
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cbv_lp_route', 'Route days (new design)', 'cbv_lp_render_route_box', 'cb_trip', 'normal', 'default' );
} );

function cbv_lp_render_route_box( $post ) {
	wp_nonce_field( 'cbv_lp_route_save', 'cbv_lp_route_nonce' );
	$trip_id   = (int) $post->ID;
	$type      = function_exists( 'cbv_lp_event_type' ) ? cbv_lp_event_type( $trip_id ) : 'cruise';
	$heading   = (string) get_post_meta( $trip_id, 'cbv_lp_route_heading', true );
	$itinerary = function_exists( 'cb_trip_get_itinerary' ) ? cb_trip_get_itinerary( $trip_id ) : array();
	$days      = cbv_lp_route_days( $itinerary, get_post_meta( $trip_id, 'cb_start_date', true ) );
	$extras    = get_post_meta( $trip_id, 'cbv_lp_route_days', true );
	$extras    = is_array( $extras ) ? $extras : array();
	$options   = function_exists( 'cbv_lp_focus_options' ) ? cbv_lp_focus_options() : array( '' => 'Centre (default)' );
	?>
	<p class="description">Used only by the new landing design (preview with <code>?preview=new</code>). The days come from the <strong>Day-by-Day Itinerary</strong> as last saved: after adding or changing itinerary rows, click Update / Save, then the days here update. Shown only when "Show Itinerary on Public Landing Page" is ticked.</p>
	<p>
		<label for="cbv_lp_route_heading"><strong>Route heading</strong></label><br>
		<input type="text" name="cbv_lp_route_heading" id="cbv_lp_route_heading" value="<?php echo esc_attr( $heading ); ?>" maxlength="80" class="regular-text">
	</p>
	<p class="description">Leave blank to use "<?php echo esc_html( cbv_lp_route_default_heading( $type ) ); ?>" (the default for this event type). Grey placeholder text is not a saved value.</p>

	<?php if ( ! $days ) : ?>
		<p><em>No itinerary days yet. Add rows (with dates) in the Day-by-Day Itinerary box and click Update / Save.</em></p>
		<?php
		return;
	endif;

	foreach ( $days as $i => $day ) :
		$n    = (int) $day['n'];
		$x    = cbv_lp_sanitize_route_day( $extras[ $n ] ?? array() );
		$auto = cbv_lp_route_time_line( $day, $days[ $i + 1 ] ?? null, 'cruise' === $type );
		$base = 'cbv_lp_route_days[' . $n . ']';
		$id   = 'cbv_lp_route_day_' . $n;
		$ts   = '' !== $day['date'] ? strtotime( $day['date'] . ' 12:00:00 UTC' ) : false;
		?>
		<fieldset style="border:1px solid #dcdcde;padding:10px 14px 4px;margin:14px 0;">
			<legend style="padding:0 6px;"><strong><?php echo esc_html( 'Day ' . str_pad( (string) $n, 2, '0', STR_PAD_LEFT ) . ( $ts ? ' · ' . gmdate( 'D M j', $ts ) : '' ) . ' · ' . $day['place'] ); ?></strong></legend>
			<p class="description" style="margin-top:0;">Automatic time line: <?php echo '' !== $auto ? '<strong>' . esc_html( $auto ) . '</strong>' : '<em>none</em>'; ?></p>
			<p>
				<label for="<?php echo esc_attr( $id ); ?>_title">Title</label><br>
				<input type="text" name="<?php echo esc_attr( $base ); ?>[title]" id="<?php echo esc_attr( $id ); ?>_title" value="<?php echo esc_attr( $x['title'] ); ?>" maxlength="80" class="regular-text">
				<span class="description">Blank = "<?php echo esc_html( $day['place'] ); ?>".</span>
			</p>
			<p>
				<label for="<?php echo esc_attr( $id ); ?>_text">Text</label><br>
				<textarea name="<?php echo esc_attr( $base ); ?>[text]" id="<?php echo esc_attr( $id ); ?>_text" rows="3" class="large-text"><?php echo esc_textarea( $x['text'] ); ?></textarea>
			</p>
			<p>
				<label for="<?php echo esc_attr( $id ); ?>_sticker">Sticker</label>
				<input type="text" name="<?php echo esc_attr( $base ); ?>[sticker]" id="<?php echo esc_attr( $id ); ?>_sticker" value="<?php echo esc_attr( $x['sticker'] ); ?>" maxlength="60" class="regular-text" placeholder="e.g. Tonight: Scarlet Night">
				<select name="<?php echo esc_attr( $base ); ?>[sticker_style]" aria-label="Sticker style">
					<option value="standard" <?php selected( $x['sticker_style'], 'standard' ); ?>>Standard (cream)</option>
					<option value="highlight" <?php selected( $x['sticker_style'], 'highlight' ); ?>>Highlight (coral)</option>
				</select>
			</p>
			<?php
			if ( function_exists( 'cbv_lp_media_field' ) ) {
				cbv_lp_media_field( $base . '[photo]', $x['photo'], 'image', 'Photo (optional)', 'Shown across the top of the card. Virgin pictures from the First Mates toolkit only, unaltered; set Alt Text in the Media Library.' );
			}
			?>
			<p>
				<label for="<?php echo esc_attr( $id ); ?>_focus">Photo focus point</label>
				<select name="<?php echo esc_attr( $base ); ?>[focus]" id="<?php echo esc_attr( $id ); ?>_focus">
					<?php foreach ( $options as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $x['focus'], $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				&nbsp; <label><input type="checkbox" name="<?php echo esc_attr( $base ); ?>[hide_time]" value="1" <?php checked( $x['hide_time'] ); ?>> Hide the automatic time line</label>
			</p>
		</fieldset>
	<?php endforeach;
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_lp_route_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_lp_route_nonce'], 'cbv_lp_route_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$heading = isset( $_POST['cbv_lp_route_heading'] ) && is_scalar( $_POST['cbv_lp_route_heading'] ) ? sanitize_text_field( wp_unslash( $_POST['cbv_lp_route_heading'] ) ) : '';
	$heading = function_exists( 'mb_substr' ) ? mb_substr( $heading, 0, 80 ) : substr( $heading, 0, 80 );
	$posted  = isset( $_POST['cbv_lp_route_days'] ) && is_array( $_POST['cbv_lp_route_days'] ) ? wp_unslash( $_POST['cbv_lp_route_days'] ) : array();
	$stored  = get_post_meta( $post_id, 'cbv_lp_route_days', true );

	update_post_meta( $post_id, 'cbv_lp_route_heading', $heading );
	update_post_meta( $post_id, 'cbv_lp_route_days', cbv_lp_merge_route_days( is_array( $stored ) ? $stored : array(), $posted ) );
} );
