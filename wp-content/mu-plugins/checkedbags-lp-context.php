<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Landing Redesign Context
 * Description: The "what kind of event is this" layer for the redesigned
 *              public trip landing page. ONE template serves every event
 *              type; the type only decides three things: which sections are
 *              on by default, what the page calls things (cabin / room /
 *              villa, Travelers / Guests ...), and a little starter text.
 *              Also holds the two small helpers every section renderer
 *              shares: {tokens} that pull values from the trip, and a
 *              "light markup" formatter (**bold**, bullet lines, links)
 *              that is safe by construction -- text is escaped first, and
 *              only a fixed set of tags can ever be produced.
 *
 *              Nothing in this file outputs anything by itself; it is all
 *              functions the new design calls. The existing cb_trip_type
 *              taxonomy (Cruise, Resort, Hotel ...) is left alone: the
 *              event type below is separate presentation-only data that
 *              defaults from it.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-context.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Sections. hero and footer are always present and aren't toggles.
   ========================================================================== */
function cbv_lp_sections() {
	return array(
		'status'   => 'Status board (minimum travelers)',
		'intro'    => 'Intro',
		'route'    => 'Route / itinerary',
		'featured' => 'Featured moment',
		'gallery'  => 'Photo gallery',
		'prices'   => 'Price board',
		'included' => 'What\'s included',
		'upgrades' => 'Upgrades',
		'pack'     => 'Pack list',
		'how'      => 'How to book',
		'perks'    => 'Group perks',
		'member'   => 'Member hint',
		'timeline' => 'Timeline / key dates',
		'docs'     => 'Travel documents',
		'story'    => 'Couple\'s story (wedding)',
		'schedule' => 'Event schedule (wedding)',
		'rsvp'     => 'RSVP (wedding)',
	);
}

/* ==========================================================================
   2. Event types: default-on sections + label overrides. 'labels' only lists
      what differs from the cruise base set below.
   ========================================================================== */
function cbv_lp_event_types() {
	$types = array(
		'cruise'      => array(
			'label'    => 'Cruise',
			'sections' => array( 'status', 'intro', 'route', 'featured', 'gallery', 'prices', 'included', 'upgrades', 'pack', 'how', 'perks', 'member', 'timeline', 'docs' ),
			'labels'   => array(),
		),
		'resort'      => array(
			'label'    => 'Resort',
			'sections' => array( 'status', 'intro', 'route', 'gallery', 'prices', 'included', 'upgrades', 'pack', 'how', 'perks', 'member', 'timeline', 'docs' ),
			'labels'   => array(
				'accommodation' => 'room', 'accommodation_plural' => 'rooms',
				'party' => 'Guests', 'party_one' => 'Guest',
				'price_heading' => 'Pick your room', 'eyebrow' => 'Now booking',
				'pass_start' => 'Check-in', 'pass_end' => 'Check-out', 'pass_place' => 'Property',
				'status_suffix' => 'to confirm', 'status_before' => 'Open', 'status_after' => 'Confirmed',
				'timeline_heading' => 'Countdown to check-in', 'nav_prices' => 'Rooms',
				'sec_route' => 'The plan', 'sec_gallery' => 'The place', 'sec_prices' => 'Room types',
				'how_title' => 'Three steps to check-in', 'cleared' => 'You\'re all set',
			),
		),
		'rental'      => array(
			'label'    => 'Cabin / Villa Rental',
			'sections' => array( 'status', 'intro', 'gallery', 'prices', 'included', 'upgrades', 'pack', 'how', 'member', 'timeline', 'docs' ),
			'labels'   => array(
				'accommodation' => 'villa', 'accommodation_plural' => 'villas',
				'party' => 'Guests', 'party_one' => 'Guest',
				'price_heading' => 'Pick your villa', 'eyebrow' => 'Now booking',
				'pass_start' => 'Check-in', 'pass_end' => 'Check-out', 'pass_place' => 'Property',
				'status_suffix' => 'to confirm', 'status_before' => 'Open', 'status_after' => 'Confirmed',
				'timeline_heading' => 'Countdown to check-in', 'nav_prices' => 'Villas',
				'sec_gallery' => 'The place', 'sec_prices' => 'Stay options',
				'how_title' => 'Three steps to your stay', 'cleared' => 'You\'re all set',
			),
		),
		'destination' => array(
			'label'    => 'Destination / Land Trip',
			'sections' => array( 'status', 'intro', 'route', 'featured', 'gallery', 'prices', 'included', 'upgrades', 'pack', 'how', 'perks', 'member', 'timeline', 'docs' ),
			'labels'   => array(
				'accommodation' => 'room', 'accommodation_plural' => 'rooms',
				'party' => 'Travelers', 'party_one' => 'Traveler',
				'price_heading' => 'Pick your package', 'eyebrow' => 'Now booking',
				'pass_start' => 'Starts', 'pass_end' => 'Ends', 'pass_place' => 'Stay',
				'status_suffix' => 'to confirm', 'status_before' => 'Open', 'status_after' => 'Confirmed',
				'timeline_heading' => 'Countdown to takeoff', 'nav_prices' => 'Packages',
				'sec_route' => 'The route', 'sec_gallery' => 'The destination', 'sec_prices' => 'Packages',
				'how_title' => 'Three steps to takeoff', 'cleared' => 'You\'re cleared for takeoff',
			),
		),
		'wedding'     => array(
			'label'    => 'Wedding',
			'sections' => array( 'gallery', 'prices', 'pack', 'perks', 'timeline', 'docs', 'story', 'schedule', 'rsvp' ),
			'labels'   => array(
				'accommodation' => 'room', 'accommodation_plural' => 'rooms',
				'party' => 'Guests', 'party_one' => 'Guest',
				'price_heading' => 'Pick your room', 'eyebrow' => 'Save the date',
				'pass_start' => 'Date', 'pass_end' => 'Ends', 'pass_place' => 'Venue',
				'status_suffix' => 'to confirm', 'status_before' => 'Open', 'status_after' => 'Confirmed',
				'timeline_heading' => 'Countdown to the day', 'nav_prices' => 'Rooms',
				'sec_gallery' => 'Moments', 'sec_prices' => 'Room blocks', 'sec_pack' => 'What to wear',
				'how_title' => 'How to book your stay', 'cleared' => 'You\'re on the list',
			),
		),
		'party'       => array(
			'label'    => 'Party / Add-on',
			'sections' => array( 'intro', 'featured', 'prices', 'included', 'pack', 'how', 'member', 'timeline', 'docs' ),
			'labels'   => array(
				'accommodation' => 'spot', 'accommodation_plural' => 'spots',
				'party' => 'Guests', 'party_one' => 'Guest',
				'price_heading' => 'Pick your package', 'eyebrow' => 'You\'re invited',
				'pass_start' => 'Date', 'pass_end' => 'Ends', 'pass_place' => 'Venue',
				'status_suffix' => 'to confirm', 'status_before' => 'Open', 'status_after' => 'Confirmed',
				'timeline_heading' => 'Countdown', 'nav_prices' => 'Packages',
				'sec_prices' => 'Packages',
				'how_title' => 'Three steps to the party', 'cleared' => 'You\'re on the list',
			),
		),
		'corporate'   => array(
			'label'    => 'Corporate',
			'sections' => array( 'status', 'intro', 'route', 'prices', 'included', 'how', 'timeline', 'docs' ),
			'labels'   => array(
				'accommodation' => 'room', 'accommodation_plural' => 'rooms',
				'party' => 'Attendees', 'party_one' => 'Attendee',
				'price_heading' => 'Pick your package', 'eyebrow' => 'Registration open',
				'pass_start' => 'Starts', 'pass_end' => 'Ends', 'pass_place' => 'Venue',
				'status_suffix' => 'to confirm', 'status_before' => 'Open', 'status_after' => 'Confirmed',
				'timeline_heading' => 'Key dates', 'nav_prices' => 'Packages',
				'sec_route' => 'Agenda', 'sec_prices' => 'Packages',
				'how_title' => 'Three steps to register', 'cleared' => 'You\'re registered',
			),
		),
	);

	return apply_filters( 'cbv_lp_event_types', $types );
}

/** The cruise wording; every other type overrides only what differs. */
function cbv_lp_base_labels() {
	return array(
		'accommodation'        => 'cabin',
		'accommodation_plural' => 'cabins',
		'party'                => 'Travelers', // not "Sailors": the cruise wording must suit every cruise line
		'party_one'            => 'Traveler',
		'price_heading'        => 'Pick your cabin',
		'eyebrow'              => 'Now boarding',
		'pass_start'           => 'Departs',
		'pass_end'             => 'Returns',
		'pass_place'           => 'Vessel',
		'status_suffix'        => 'to depart',
		'status_before'        => 'Boarding',
		'status_after'         => 'On time',
		'timeline_heading'     => 'Countdown to boarding',
		'nav_prices'           => 'Fares',
		'sec_intro'            => 'The trip',
		'sec_route'            => 'Flight path',
		'sec_featured'         => 'After dark',
		'sec_gallery'          => 'Life on board',
		'sec_prices'           => 'Fare classes',
		'sec_included'         => 'What\'s included',
		'sec_upgrades'         => 'Upgrades',
		'sec_pack'             => 'Pack list',
		'sec_how'              => 'Check-in',
		'sec_perks'            => 'Group perks',
		'sec_timeline'         => 'Countdown',
		'sec_docs'             => 'Travel documents',
		'sec_story'            => 'The couple',
		'sec_schedule'         => 'The schedule',
		'sec_rsvp'             => 'RSVP',
		'how_title'            => 'Three steps to the gangway',
		'cleared'              => 'You\'re cleared for boarding',
	);
}

/* ==========================================================================
   3. Resolving a trip's event type, labels and sections.
   ========================================================================== */
/**
 * The existing cb_trip_type term (Cruise, Resort, Hotel, Retreat, Destination,
 * Package Tour, Flight, Train, Car Rental, Other) mapped onto an event type.
 * Only a default -- the trip's own Event Type select always wins.
 */
function cbv_lp_event_type_from_trip_type( $trip_id ) {
	$map = array(
		'cruise'       => 'cruise',
		'resort'       => 'resort',
		'hotel'        => 'resort',
		'retreat'      => 'resort',
		'destination'  => 'destination',
		'package tour' => 'destination',
		'flight'       => 'destination',
		'train'        => 'destination',
		'car rental'   => 'destination',
		'other'        => 'destination',
	);

	$terms = get_the_terms( (int) $trip_id, 'cb_trip_type' );
	if ( is_array( $terms ) ) {
		foreach ( $terms as $term ) {
			$key = strtolower( $term->name );
			if ( isset( $map[ $key ] ) ) {
				return $map[ $key ];
			}
		}
	}
	return 'destination';
}

function cbv_lp_event_type( $trip_id ) {
	$saved = get_post_meta( (int) $trip_id, 'cbv_lp_event_type', true );
	if ( $saved && isset( cbv_lp_event_types()[ $saved ] ) ) {
		return $saved;
	}
	return cbv_lp_event_type_from_trip_type( $trip_id );
}

function cbv_lp_event_type_is_automatic( $trip_id ) {
	$saved = get_post_meta( (int) $trip_id, 'cbv_lp_event_type', true );
	return ! ( $saved && isset( cbv_lp_event_types()[ $saved ] ) );
}

/**
 * Every label the page uses, for this trip: cruise base, the type's
 * overrides, then the trip's own accommodation-noun override (if any).
 */
function cbv_lp_labels( $trip_id ) {
	$type   = cbv_lp_event_type( $trip_id );
	$types  = cbv_lp_event_types();
	$labels = array_merge( cbv_lp_base_labels(), (array) ( $types[ $type ]['labels'] ?? array() ) );

	$noun = trim( (string) get_post_meta( (int) $trip_id, 'cbv_lp_accommodation_noun', true ) );
	if ( '' !== $noun ) {
		// "Pick your cabin" style headings follow the noun; "Pick your package" does not.
		if ( 'Pick your ' . $labels['accommodation'] === $labels['price_heading'] ) {
			$labels['price_heading'] = 'Pick your ' . $noun;
		}
		$labels['accommodation']        = $noun;
		$labels['accommodation_plural'] = $noun . 's';
	}

	return apply_filters( 'cbv_lp_labels', $labels, (int) $trip_id, $type );
}

/**
 * Is a section on for this trip? The trip's own On / Off choice wins;
 * otherwise the event type's default. (A section that is "on" still hides
 * itself when it has no content -- that's the renderer's job.)
 */
function cbv_lp_section_enabled( $trip_id, $section ) {
	if ( ! isset( cbv_lp_sections()[ $section ] ) ) {
		return false;
	}

	$overrides = get_post_meta( (int) $trip_id, 'cbv_lp_sections', true );
	if ( is_array( $overrides ) && isset( $overrides[ $section ] ) ) {
		if ( 'on' === $overrides[ $section ] ) {
			return true;
		}
		if ( 'off' === $overrides[ $section ] ) {
			return false;
		}
	}

	$types = cbv_lp_event_types();
	$type  = cbv_lp_event_type( $trip_id );
	return in_array( $section, (array) ( $types[ $type ]['sections'] ?? array() ), true );
}

function cbv_lp_enabled_sections( $trip_id ) {
	$on = array();
	foreach ( array_keys( cbv_lp_sections() ) as $key ) {
		if ( cbv_lp_section_enabled( $trip_id, $key ) ) {
			$on[] = $key;
		}
	}
	return $on;
}

/** Whether a section is on by the event type's default (used by the admin box). */
function cbv_lp_section_default_on( $event_type, $section ) {
	$types = cbv_lp_event_types();
	return in_array( $section, (array) ( $types[ $event_type ]['sections'] ?? array() ), true );
}

/** One line of plain text for the admin preview banner. */
function cbv_lp_context_summary( $trip_id ) {
	$types = cbv_lp_event_types();
	$type  = cbv_lp_event_type( $trip_id );
	$names = array();
	foreach ( cbv_lp_enabled_sections( $trip_id ) as $key ) {
		$names[] = cbv_lp_sections()[ $key ];
	}
	$provider = function_exists( 'cbv_lp_provider_name' ) ? cbv_lp_provider_name( $trip_id ) : '';

	return sprintf(
		'Event type: %1$s (%2$s). Provider: %3$s. Sections on: %4$s.',
		$types[ $type ]['label'],
		cbv_lp_event_type_is_automatic( $trip_id ) ? 'automatic, from Trip Type' : 'set on this trip',
		'' !== $provider ? $provider : 'none',
		$names ? implode( ', ', $names ) : 'none'
	);
}

/* ==========================================================================
   4. Starter text -- used only when the trip (and later its provider) has
      not entered its own.
   ========================================================================== */
function cbv_lp_starter( $trip_id ) {
	$l = cbv_lp_labels( $trip_id );

	$starter = array(
		'how_steps'   => array(
			array(
				'title' => 'Register for the trip',
				'body'  => 'Tell us who\'s coming and which ' . $l['accommodation'] . ' you want.',
			),
			array(
				'title' => 'Pay your deposit',
				'body'  => 'We book your ' . $l['accommodation'] . ' inside our group through InteleTravel, our official booking partner, then you get a secure payment link for your deposit. Pay in full or spread it out with a plan.',
			),
			array(
				'title' => $l['cleared'],
				'body'  => 'Log in anytime to see your trip, payments and updates in your boarding-pass wallet.',
			),
		),
		'member_hint' => 'Full Members get first dibs on ' . $l['accommodation_plural'] . ' before trips go public.',
	);

	return apply_filters( 'cbv_lp_starter', $starter, (int) $trip_id );
}

/* ==========================================================================
   5. {tokens}: values pulled from the trip into any text field, so reusable
      text (later: the provider library) never hard-codes a trip's dates or
      deposit. Unknown tokens render nothing and are remembered so the admin
      preview can warn about them.
   ========================================================================== */
function cbv_lp_unknown_tokens( $new = null ) {
	static $seen = array();
	if ( null === $new ) {
		return array_keys( $seen );
	}
	if ( false === $new ) {
		$seen = array();
		return array();
	}
	$seen[ $new ] = true;
	return array_keys( $seen );
}

function cbv_lp_format_date( $ymd ) {
	$ts = $ymd ? strtotime( $ymd ) : false;
	return $ts ? date_i18n( 'F j, Y', $ts ) : '';
}

function cbv_lp_tokens( $trip_id ) {
	$trip_id = (int) $trip_id;
	$l       = cbv_lp_labels( $trip_id );

	$deposit = (float) get_post_meta( $trip_id, 'cb_deposit_amount', true );
	if ( $deposit > 0 ) {
		$deposit_text = '$' . number_format_i18n( $deposit, floor( $deposit ) === $deposit ? 0 : 2 );
	} else {
		$deposit_text = '';
	}

	$tokens = array(
		'trip_title'           => get_the_title( $trip_id ),
		'trip_code'            => (string) get_post_meta( $trip_id, 'cb_trip_code', true ),
		'start'                => cbv_lp_format_date( get_post_meta( $trip_id, 'cb_start_date', true ) ),
		'end'                  => cbv_lp_format_date( get_post_meta( $trip_id, 'cb_end_date', true ) ),
		'deposit'              => $deposit_text,
		'vessel'               => (string) get_post_meta( $trip_id, 'cbv_lp_venue_name', true ),
		'accommodation'        => $l['accommodation'],
		'accommodation_plural' => $l['accommodation_plural'],
		'party'                => $l['party'],
	);

	// Key dates (e.g. date:final_payment) come from the provider library and the
	// trip's own overrides (checkedbags-lp-providers.php), computed from this
	// trip's start date.
	foreach ( (array) apply_filters( 'cbv_lp_key_dates', array(), $trip_id ) as $key => $text ) {
		$tokens[ 'date:' . $key ] = (string) $text;
	}

	// {days:key}: whole days from that key date to the trip's start (so wording
	// like "120 days before sailing" stays true when a trip changes the date).
	foreach ( (array) apply_filters( 'cbv_lp_key_days', array(), $trip_id ) as $key => $n ) {
		$tokens[ 'days:' . $key ] = (string) (int) $n;
	}

	return $tokens;
}

function cbv_lp_apply_tokens( $text, $trip_id ) {
	if ( false === strpos( (string) $text, '{' ) ) {
		return (string) $text;
	}
	$tokens = cbv_lp_tokens( $trip_id );

	return preg_replace_callback(
		'/\{([a-z0-9_:\-]+)\}/i',
		function ( $m ) use ( $tokens ) {
			$name = strtolower( $m[1] );
			if ( array_key_exists( $name, $tokens ) ) {
				return $tokens[ $name ];
			}
			cbv_lp_unknown_tokens( $name );
			return '';
		},
		(string) $text
	);
}

/* ==========================================================================
   6. Light markup. Everything returned is HTML that is safe to echo:
      the text is escaped FIRST, then only these are turned back on --
        **bold**   *italic*   [text](https://link)   (http, https, mailto, tel)
      Block level (cbv_lp_blocks): blank line = new paragraph, lines starting
      "- " = bullets, lines starting "> " = small print.
   ========================================================================== */
function cbv_lp_inline( $text ) {
	$s = esc_html( (string) $text );

	$s = preg_replace_callback(
		'/\[([^\]\n]+)\]\(([^)\s]+)\)/',
		function ( $m ) {
			$url = esc_url( wp_specialchars_decode( $m[2] ), array( 'http', 'https', 'mailto', 'tel' ) );
			if ( '' === $url ) {
				return $m[1]; // disallowed or malformed link: keep the words, drop the link
			}
			return '<a href="' . $url . '" rel="noopener">' . $m[1] . '</a>';
		},
		$s
	);

	$s = preg_replace( '/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $s );
	$s = preg_replace( '/(?<![\*\w])\*(?=[^\s*])([^*\n]+?)(?<=[^\s*])\*(?![\*\w])/', '<em>$1</em>', $s );

	return $s;
}

/** Plain-text lines -> array of safe HTML strings, one per non-empty line. */
function cbv_lp_lines( $text, $trip_id = 0 ) {
	if ( $trip_id ) {
		$text = cbv_lp_apply_tokens( $text, $trip_id );
	}
	$out = array();
	foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
		$line = trim( preg_replace( '/^(?:[-•]\s+)/u', '', trim( $line ) ) );
		if ( '' !== $line ) {
			$out[] = cbv_lp_inline( $line );
		}
	}
	return $out;
}

/** Multi-paragraph text -> safe HTML (paragraphs, bullet lists, small print). */
function cbv_lp_blocks( $text, $trip_id = 0 ) {
	if ( $trip_id ) {
		$text = cbv_lp_apply_tokens( $text, $trip_id );
	}

	$html   = '';
	$para   = array();
	$bullets = array();
	$flush  = function () use ( &$html, &$para, &$bullets ) {
		if ( $para ) {
			$html .= '<p>' . implode( '<br>', $para ) . '</p>';
			$para  = array();
		}
		if ( $bullets ) {
			$html   .= '<ul><li>' . implode( '</li><li>', $bullets ) . '</li></ul>';
			$bullets = array();
		}
	};

	foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			$flush();
		} elseif ( 0 === strpos( $line, '- ' ) ) {
			if ( $para ) {
				$flush();
			}
			$bullets[] = cbv_lp_inline( substr( $line, 2 ) );
		} elseif ( 0 === strpos( $line, '> ' ) ) {
			$flush();
			$html .= '<p class="cbv-lp-small">' . cbv_lp_inline( substr( $line, 2 ) ) . '</p>';
		} else {
			if ( $bullets ) {
				$flush();
			}
			$para[] = cbv_lp_inline( $line );
		}
	}
	$flush();

	return $html;
}
