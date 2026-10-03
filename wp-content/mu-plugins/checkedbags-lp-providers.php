<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Landing Redesign Provider Library
 * Description: Reusable content for the redesigned public landing page, stored
 *              ONCE per provider (a cruise line, a resort brand ...) instead of
 *              retyped on every trip: the price-column names, the "what's
 *              included" cards, the how-to-book steps, the travel documents
 *              (cancellation, protection ...) and a set of key dates defined
 *              as an offset from the trip's start date ("final payment = 120
 *              days before sailing"). A trip picks its provider and inherits
 *              all of it.
 *
 *              Inheritance rules (decided, and deliberately predictable):
 *                - Price columns, "what's included" cards, the included
 *                  footnote and how-to-book steps: if the TRIP has its own,
 *                  they REPLACE the provider's entirely. Rows are never mixed.
 *                - Travel documents: a trip's own documents are APPENDED
 *                  after the provider's (a trip usually adds a document, e.g.
 *                  hotels for its departure port; it rarely replaces them all).
 *                - Key dates: a trip date with the SAME KEY as a provider date
 *                  replaces that one date (a group contract with a different
 *                  final-payment deadline) and nothing else; new keys are
 *                  added; a "not applicable" tick removes a provider date.
 *                  Tokens and the timeline both read the one merged list.
 *                - The timeline additionally merges in the trip's free-form
 *                  rows and sorts everything by date.
 *
 *              The trip-side fields for these groups arrive in later steps
 *              (same meta names, prefixed cbv_lp_, same row shapes); the
 *              resolvers below already honor them, so nothing here changes
 *              when they land. Nothing in this file outputs anything on the
 *              public site by itself.
 *
 *              Storage: a custom post type (cb_provider) so it gets a real
 *              admin list / edit screen for free -- the same reasoning as the
 *              Appointment Requests post type. Not public: no front-end URL,
 *              not in search, not in any sitemap.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-providers.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. The post type.
   ========================================================================== */
function cbv_lp_register_provider_post_type() {
	register_post_type( 'cb_provider', array(
		'labels'              => array(
			'name'          => 'Provider Library',
			'singular_name' => 'Provider',
			'add_new'       => 'Add Provider',
			'add_new_item'  => 'Add New Provider',
			'edit_item'     => 'Edit Provider',
			'all_items'     => 'All Providers',
			'search_items'  => 'Search Providers',
			'not_found'     => 'No providers yet.',
		),
		'description'         => 'Cruise lines, resort brands and other providers whose reusable landing-page content is stored once and inherited by trips.',
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => false,
		'exclude_from_search' => true,
		'has_archive'         => false,
		'rewrite'             => false,
		'query_var'           => false,
		'menu_position'       => 26,
		'menu_icon'           => 'dashicons-building',
		'supports'            => array( 'title' ),
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
	) );
}
add_action( 'init', 'cbv_lp_register_provider_post_type' );

/* ==========================================================================
   2. Row cleaners. One per group, used when SAVING (from posted, already
      unslashed data) and when READING (so hand-edited or older meta can never
      hand a malformed row to a renderer). Every one returns a plain list of
      plain-text rows; nothing here returns HTML.
   ========================================================================== */
function cbv_lp_provider_groups() {
	return array( 'columns', 'included', 'steps', 'docs' );
}

function cbv_lp_text( $row, $key ) {
	return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? sanitize_text_field( (string) $row[ $key ] ) : '';
}

function cbv_lp_area( $row, $key ) {
	return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? sanitize_textarea_field( (string) $row[ $key ] ) : '';
}

/** A row the admin never touched (every leaf blank) -- same rule as every other repeater on the site. */
function cbv_lp_row_untouched( $row ) {
	if ( ! is_array( $row ) ) {
		return true;
	}
	return function_exists( 'cb_repeater_row_is_blank' ) ? cb_repeater_row_is_blank( $row ) : ! array_filter( array_map( 'trim', array_map( 'strval', $row ) ), 'strlen' );
}

/** Four fixed name slots (Base / Essential / Premium ...); positions matter, blanks are kept. */
function cbv_lp_clean_columns( $raw ) {
	$out = array();
	foreach ( array_slice( array_values( (array) $raw ), 0, 4 ) as $value ) {
		$out[] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}
	while ( count( $out ) < 4 ) {
		$out[] = '';
	}
	return $out;
}

function cbv_lp_clean_included( $raw ) {
	$out = array();
	foreach ( (array) $raw as $row ) {
		if ( cbv_lp_row_untouched( $row ) ) {
			continue;
		}
		$style = isset( $row['style'] ) && is_scalar( $row['style'] ) ? sanitize_key( (string) $row['style'] ) : '';
		$card  = array(
			'label'   => cbv_lp_text( $row, 'label' ),
			'title'   => cbv_lp_text( $row, 'title' ),
			'subline' => cbv_lp_text( $row, 'subline' ),
			'bullets' => cbv_lp_area( $row, 'bullets' ),
			'style'   => in_array( $style, array( 'gold', 'dark' ), true ) ? $style : '', // '' = light
			'wide'    => ! empty( $row['wide'] ) ? 1 : 0,
		);
		if ( '' === $card['label'] && '' === $card['title'] && '' === $card['subline'] && '' === trim( $card['bullets'] ) ) {
			continue; // only a tick-box was touched
		}
		$out[] = $card;
	}
	return $out;
}

function cbv_lp_clean_steps( $raw ) {
	$out = array();
	foreach ( (array) $raw as $row ) {
		if ( cbv_lp_row_untouched( $row ) ) {
			continue;
		}
		$step = array( 'title' => cbv_lp_text( $row, 'title' ), 'body' => cbv_lp_area( $row, 'body' ) );
		if ( '' === $step['title'] && '' === trim( $step['body'] ) ) {
			continue;
		}
		$out[] = $step;
	}
	return $out;
}

function cbv_lp_clean_docs( $raw ) {
	$out = array();
	foreach ( (array) $raw as $row ) {
		if ( cbv_lp_row_untouched( $row ) ) {
			continue;
		}
		$reviewed = cbv_lp_text( $row, 'reviewed' );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $reviewed, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			$reviewed = '';
		}
		$doc = array( 'title' => cbv_lp_text( $row, 'title' ), 'body' => cbv_lp_area( $row, 'body' ), 'reviewed' => $reviewed );
		if ( '' === $doc['title'] && '' === trim( $doc['body'] ) ) {
			continue;
		}
		$out[] = $doc;
	}
	return $out;
}

/**
 * Key dates: a slug used as the token ({date:final_payment}), a title and
 * description for the timeline, an offset in days BEFORE the trip's start date
 * (negative = after), an optional dot colour, an optional display text (may use
 * tokens, e.g. a range "{date:a} - {date:b}"), and "hidden" for dates that are
 * only ever used inside other text, never shown as a timeline row.
 */
function cbv_lp_clean_key_dates( $raw ) {
	$out  = array();
	$seen = array();
	foreach ( (array) $raw as $row ) {
		if ( cbv_lp_row_untouched( $row ) ) {
			continue;
		}
		$title = cbv_lp_text( $row, 'title' );
		$key   = isset( $row['key'] ) && is_scalar( $row['key'] ) ? sanitize_key( str_replace( ' ', '_', (string) $row['key'] ) ) : '';
		if ( '' === $key ) {
			$key = str_replace( '-', '_', sanitize_title( $title ) );
		}
		$key = preg_replace( '/[^a-z0-9_]/', '', strtolower( $key ) );
		if ( '' === $key && '' === $title ) {
			continue;
		}
		if ( '' === $key ) {
			$key = 'date';
		}
		$base = $key;
		for ( $n = 2; isset( $seen[ $key ] ); $n++ ) {
			$key = $base . '_' . $n;
		}
		$seen[ $key ] = true;

		$offset = isset( $row['offset'] ) && is_scalar( $row['offset'] ) && '' !== trim( (string) $row['offset'] ) && is_numeric( $row['offset'] ) ? (int) $row['offset'] : '';
		$dot    = isset( $row['dot'] ) && is_scalar( $row['dot'] ) ? sanitize_key( (string) $row['dot'] ) : '';

		$out[] = array(
			'key'         => $key,
			'title'       => $title,
			'description' => cbv_lp_area( $row, 'description' ),
			'offset'      => $offset,
			'dot'         => in_array( $dot, array( 'coral', 'green' ), true ) ? $dot : '', // '' = gold
			'date_text'   => cbv_lp_text( $row, 'date_text' ),
			'hidden'      => ! empty( $row['hidden'] ) ? 1 : 0,
		);
	}
	return $out;
}

/** A trip's own timeline rows (the trip-side editor arrives in a later step; shape fixed now). */
function cbv_lp_clean_timeline( $raw ) {
	$out = array();
	foreach ( (array) $raw as $row ) {
		if ( cbv_lp_row_untouched( $row ) ) {
			continue;
		}
		$sort = cbv_lp_text( $row, 'sort_date' );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $sort, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
			$sort = '';
		}
		$dot = isset( $row['dot'] ) && is_scalar( $row['dot'] ) ? sanitize_key( (string) $row['dot'] ) : '';
		$r   = array(
			'date_text'   => cbv_lp_text( $row, 'date_text' ),
			'title'       => cbv_lp_text( $row, 'title' ),
			'description' => cbv_lp_area( $row, 'description' ),
			'dot'         => in_array( $dot, array( 'coral', 'green' ), true ) ? $dot : '',
			'sort_date'   => $sort,
		);
		if ( '' === $r['title'] && '' === $r['date_text'] && '' === trim( $r['description'] ) ) {
			continue;
		}
		$out[] = $r;
	}
	return $out;
}

/** The one optional footnote under the "what's included" cards (plain text; light markup is applied at render). */
function cbv_lp_clean_footnote( $raw ) {
	return is_scalar( $raw ) ? sanitize_textarea_field( (string) $raw ) : '';
}

function cbv_lp_valid_ymd( $value ) {
	return is_string( $value ) && preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
}

/** A token-safe key from the typed key, else from the title. */
function cbv_lp_key_from( $key, $title ) {
	$key = is_scalar( $key ) ? sanitize_key( str_replace( ' ', '_', (string) $key ) ) : '';
	if ( '' === $key ) {
		$key = str_replace( '-', '_', sanitize_title( $title ) );
	}
	return preg_replace( '/[^a-z0-9_]/', '', strtolower( $key ) );
}

/**
 * A TRIP's own key-date rows. Same shape as a provider's, plus:
 *   date  -- a fixed Y-m-d (wins over "days before start"; a contract deadline),
 *   off   -- "not applicable on this trip": removes the provider's date.
 * A row whose key matches a provider date replaces that date; blank text fields
 * inherit the provider's wording. Duplicate keys on one trip are NOT renamed:
 * the later row wins when the list is merged.
 */
function cbv_lp_clean_trip_key_dates( $raw ) {
	$out = array();
	foreach ( (array) $raw as $row ) {
		if ( cbv_lp_row_untouched( $row ) ) {
			continue;
		}
		$title = cbv_lp_text( $row, 'title' );
		$key   = cbv_lp_key_from( $row['key'] ?? '', $title );
		if ( '' === $key ) {
			continue; // no key and no title to make one from: nothing to attach it to
		}
		$date   = cbv_lp_text( $row, 'date' );
		$offset = isset( $row['offset'] ) && is_scalar( $row['offset'] ) && '' !== trim( (string) $row['offset'] ) && is_numeric( $row['offset'] ) ? (int) $row['offset'] : '';
		$dot    = isset( $row['dot'] ) && is_scalar( $row['dot'] ) ? sanitize_key( (string) $row['dot'] ) : '';
		$out[]  = array(
			'key'         => $key,
			'title'       => $title,
			'description' => cbv_lp_area( $row, 'description' ),
			'offset'      => $offset,
			'date'        => cbv_lp_valid_ymd( $date ) ? $date : '',
			'dot'         => in_array( $dot, array( 'gold', 'coral', 'green' ), true ) ? $dot : '', // '' = inherit
			'date_text'   => cbv_lp_text( $row, 'date_text' ),
			'hidden'      => ! empty( $row['hidden'] ) ? 1 : 0,
			'off'         => ! empty( $row['off'] ) ? 1 : 0,
		);
	}
	return $out;
}

function cbv_lp_clean_group( $group, $raw ) {
	switch ( $group ) {
		case 'columns':
			return cbv_lp_clean_columns( $raw );
		case 'included':
			return cbv_lp_clean_included( $raw );
		case 'steps':
			return cbv_lp_clean_steps( $raw );
		case 'docs':
			return cbv_lp_clean_docs( $raw );
		case 'key_dates':
			return cbv_lp_clean_key_dates( $raw );
		case 'timeline':
			return cbv_lp_clean_timeline( $raw );
		case 'included_footnote':
			return cbv_lp_clean_footnote( $raw );
		case 'trip_key_dates':
			return cbv_lp_clean_trip_key_dates( $raw );
	}
	return array();
}

/** Is a cleaned group "nothing entered"? (columns: all four slots blank.) */
function cbv_lp_group_is_empty( $group, $value ) {
	if ( 'columns' === $group ) {
		return '' === trim( implode( '', (array) $value ) );
	}
	if ( ! is_array( $value ) ) {
		return '' === trim( (string) $value ); // the footnote
	}
	return empty( $value );
}

/* ==========================================================================
   3. Provider + inheritance resolvers.
   ========================================================================== */
/** The trip's chosen provider, only if it still exists, is a provider and is published. */
function cbv_lp_provider_id( $trip_id ) {
	$id = (int) get_post_meta( (int) $trip_id, 'cbv_lp_provider_id', true );
	if ( $id <= 0 ) {
		return 0;
	}
	$post = get_post( $id );
	return ( $post && 'cb_provider' === $post->post_type && 'publish' === $post->post_status ) ? $id : 0;
}

function cbv_lp_provider_name( $trip_id ) {
	$id = cbv_lp_provider_id( $trip_id );
	return $id ? get_the_title( $id ) : '';
}

function cbv_lp_provider_group( $provider_id, $group ) {
	return cbv_lp_clean_group( $group, get_post_meta( (int) $provider_id, 'cbv_lp_' . $group, true ) );
}

/**
 * A group for rendering.
 *   - Most groups (columns, included, steps, included_footnote): the trip's own
 *     entries if it has any -- they REPLACE the provider's, no mixing --
 *     otherwise the provider's, otherwise empty.
 *   - 'docs': the provider's documents first, then the trip's own APPENDED.
 */
function cbv_lp_group( $trip_id, $group ) {
	$own       = cbv_lp_clean_group( $group, get_post_meta( (int) $trip_id, 'cbv_lp_' . $group, true ) );
	$provider  = cbv_lp_provider_id( $trip_id );
	$inherited = $provider ? cbv_lp_provider_group( $provider, $group ) : cbv_lp_clean_group( $group, array() );

	if ( 'docs' === $group ) {
		return array_merge( $inherited, $own );
	}
	return ! cbv_lp_group_is_empty( $group, $own ) ? $own : $inherited;
}

/** 'trip' | 'provider' | 'both' (docs only) | 'none' -- where cbv_lp_group() got its answer (admin notes, tests). */
function cbv_lp_group_source( $trip_id, $group ) {
	$own      = cbv_lp_clean_group( $group, get_post_meta( (int) $trip_id, 'cbv_lp_' . $group, true ) );
	$provider = cbv_lp_provider_id( $trip_id );
	$has_own  = ! cbv_lp_group_is_empty( $group, $own );
	$has_prov = $provider && ! cbv_lp_group_is_empty( $group, cbv_lp_provider_group( $provider, $group ) );

	if ( 'docs' === $group && $has_own && $has_prov ) {
		return 'both';
	}
	if ( $has_own ) {
		return 'trip';
	}
	return $has_prov ? 'provider' : 'none';
}

/* ==========================================================================
   4. Key dates: provider offsets computed from this trip's start date.
   ========================================================================== */
/** Y-m-d for "$offset days before $start_ymd" (negative = after), or '' if the start date is missing/invalid. */
function cbv_lp_offset_date( $start_ymd, $offset ) {
	if ( '' === $offset || null === $offset || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $start_ymd, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
		return '';
	}
	// Noon, in the site's timezone, so a daylight-saving change can never shift the day.
	$d = new DateTimeImmutable( $start_ymd . ' 12:00:00', wp_timezone() );
	return $d->modify( '-' . (int) $offset . ' days' )->format( 'Y-m-d' );
}

/** Provider key-date rows for this trip, each with its computed 'ymd' ('' when it can't be computed). */
function cbv_lp_provider_key_dates( $trip_id ) {
	$provider = cbv_lp_provider_id( $trip_id );
	if ( ! $provider ) {
		return array();
	}
	$start = get_post_meta( (int) $trip_id, 'cb_start_date', true );
	$rows  = cbv_lp_clean_group( 'key_dates', get_post_meta( $provider, 'cbv_lp_key_dates', true ) );
	foreach ( $rows as &$row ) {
		$row['ymd'] = cbv_lp_offset_date( $start, $row['offset'] );
	}
	unset( $row );
	return $rows;
}

/** This trip's own key-date rows (meta cbv_lp_key_dates on the trip; same name as the provider's, different post). */
function cbv_lp_trip_key_dates( $trip_id ) {
	return cbv_lp_clean_trip_key_dates( get_post_meta( (int) $trip_id, 'cbv_lp_key_dates', true ) );
}

/** Whole days from $ymd up to the start date (positive = before the start, negative = after), or '' if either is missing. */
function cbv_lp_days_before( $start_ymd, $ymd ) {
	if ( ! cbv_lp_valid_ymd( $start_ymd ) || ! cbv_lp_valid_ymd( $ymd ) ) {
		return '';
	}
	$a = new DateTimeImmutable( $start_ymd . ' 12:00:00', wp_timezone() );
	$b = new DateTimeImmutable( $ymd . ' 12:00:00', wp_timezone() );
	return (int) round( ( $a->getTimestamp() - $b->getTimestamp() ) / DAY_IN_SECONDS );
}

/**
 * THE key-date list for a trip -- tokens, {days:key} and the timeline all read
 * this, so they can never disagree. Provider dates first (their order), then:
 *   - a trip row with the SAME KEY replaces that date's date (fixed date wins
 *     over an offset); its text fields override only when filled in, so
 *     changing just the deadline keeps the provider's title and description;
 *   - a trip row ticked "not applicable" removes the provider's date;
 *   - a trip row with a new key is added;
 *   - a trip row with neither a date nor an offset is "no override".
 * Each row: key, title, description, offset, dot, date_text, hidden, ymd,
 * source ('provider' = inherited untouched, 'trip' = set or replaced by the trip).
 */
function cbv_lp_effective_key_dates( $trip_id ) {
	$start = get_post_meta( (int) $trip_id, 'cb_start_date', true );
	$rows  = array();

	foreach ( cbv_lp_provider_key_dates( $trip_id ) as $row ) {
		$row['source']       = 'provider';
		$rows[ $row['key'] ] = $row;
	}

	foreach ( cbv_lp_trip_key_dates( $trip_id ) as $t ) {
		$key = $t['key'];
		if ( $t['off'] ) {
			unset( $rows[ $key ] );
			continue;
		}
		if ( '' === $t['date'] && '' === $t['offset'] ) {
			continue;
		}
		$ymd  = '' !== $t['date'] ? $t['date'] : cbv_lp_offset_date( $start, $t['offset'] );
		$base = $rows[ $key ] ?? null;
		$dot  = 'gold' === $t['dot'] ? '' : $t['dot'];

		$rows[ $key ] = array(
			'key'         => $key,
			'title'       => '' !== $t['title'] ? $t['title'] : ( $base['title'] ?? '' ),
			'description' => '' !== trim( $t['description'] ) ? $t['description'] : ( $base['description'] ?? '' ),
			'offset'      => '' !== $t['date'] ? '' : $t['offset'],
			'dot'         => '' !== $t['dot'] ? $dot : ( $base['dot'] ?? '' ),
			'date_text'   => '' !== $t['date_text'] ? $t['date_text'] : ( $base['date_text'] ?? '' ),
			'hidden'      => ( $t['hidden'] || ! empty( $base['hidden'] ) ) ? 1 : 0,
			'ymd'         => $ymd,
			'source'      => 'trip',
		);
	}

	return array_values( $rows );
}

// Feed the {date:key} and {days:key} tokens (checkedbags-lp-context.php) from the merged list.
add_filter( 'cbv_lp_key_dates', function ( $dates, $trip_id ) {
	foreach ( cbv_lp_effective_key_dates( $trip_id ) as $row ) {
		if ( '' !== $row['ymd'] ) {
			$dates[ $row['key'] ] = cbv_lp_format_date( $row['ymd'] );
		}
	}
	return $dates;
}, 10, 2 );

add_filter( 'cbv_lp_key_days', function ( $days, $trip_id ) {
	$start = get_post_meta( (int) $trip_id, 'cb_start_date', true );
	foreach ( cbv_lp_effective_key_dates( $trip_id ) as $row ) {
		$n = cbv_lp_days_before( $start, $row['ymd'] );
		if ( '' !== $n ) {
			$days[ $row['key'] ] = $n;
		}
	}
	return $days;
}, 10, 2 );

/**
 * The merged timeline: the effective key dates that can be computed (and
 * aren't hidden -- see cbv_lp_effective_key_dates) plus the trip's free-form
 * timeline rows, oldest first. Rows with no sortable date
 * come after the dated ones, in the order entered. {tokens} are already
 * applied; values are plain text for the renderer to escape.
 */
function cbv_lp_timeline_rows( $trip_id ) {
	$rows = array();

	foreach ( cbv_lp_effective_key_dates( $trip_id ) as $row ) {
		if ( $row['hidden'] || '' === $row['ymd'] ) {
			continue;
		}
		$rows[] = array(
			'sort'        => $row['ymd'],
			'when'        => '' !== $row['date_text'] ? cbv_lp_apply_tokens( $row['date_text'], $trip_id ) : cbv_lp_format_date( $row['ymd'] ),
			'title'       => cbv_lp_apply_tokens( $row['title'], $trip_id ),
			'description' => cbv_lp_apply_tokens( $row['description'], $trip_id ),
			'dot'         => $row['dot'],
			'source'      => $row['source'],
		);
	}

	foreach ( cbv_lp_clean_group( 'timeline', get_post_meta( (int) $trip_id, 'cbv_lp_timeline', true ) ) as $row ) {
		$rows[] = array(
			'sort'        => $row['sort_date'],
			'when'        => '' !== $row['date_text'] ? cbv_lp_apply_tokens( $row['date_text'], $trip_id ) : ( '' !== $row['sort_date'] ? cbv_lp_format_date( $row['sort_date'] ) : '' ),
			'title'       => cbv_lp_apply_tokens( $row['title'], $trip_id ),
			'description' => cbv_lp_apply_tokens( $row['description'], $trip_id ),
			'dot'         => $row['dot'],
			'source'      => 'trip',
		);
	}

	$indexed = array();
	foreach ( $rows as $i => $row ) {
		$indexed[] = array( $i, $row );
	}
	usort( $indexed, function ( $a, $b ) {
		$sa = $a[1]['sort'];
		$sb = $b[1]['sort'];
		if ( '' !== $sa && '' !== $sb ) {
			return $sa === $sb ? $a[0] <=> $b[0] : strcmp( $sa, $sb );
		}
		if ( '' !== $sa ) {
			return -1; // dated before undated
		}
		if ( '' !== $sb ) {
			return 1;
		}
		return $a[0] <=> $b[0];
	} );

	return array_map( function ( $pair ) {
		return $pair[1];
	}, $indexed );
}

/* ==========================================================================
   5. Provider edit screen: five boxes. One nonce (in the first box) covers
      the whole form; the shared Add/Remove-row script in checkedbags-trips.php
      already loads on every post edit screen, so these repeaters reuse it.
      No nested <form> anywhere.
   ========================================================================== */
add_action( 'add_meta_boxes_cb_provider', function () {
	add_meta_box( 'cbv_lp_prov_columns', 'Price column names', 'cbv_lp_render_columns_box', 'cb_provider', 'normal', 'high' );
	add_meta_box( 'cbv_lp_prov_included', 'What\'s included cards', 'cbv_lp_render_included_box', 'cb_provider', 'normal', 'default' );
	add_meta_box( 'cbv_lp_prov_steps', 'How to book steps', 'cbv_lp_render_steps_box', 'cb_provider', 'normal', 'default' );
	add_meta_box( 'cbv_lp_prov_docs', 'Travel documents', 'cbv_lp_render_docs_box', 'cb_provider', 'normal', 'default' );
	add_meta_box( 'cbv_lp_prov_dates', 'Key dates', 'cbv_lp_render_key_dates_box', 'cb_provider', 'normal', 'default' );
} );

add_action( 'admin_head', function () {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, array( 'cb_provider', 'cb_trip' ), true ) ) {
		return;
	}
	?>
	<style>
		.cbv-lp-card { border: 1px solid #ccd0d4; border-radius: 4px; padding: 10px; background: #fff; margin-bottom: 8px; }
		.cbv-lp-card .cbv-lp-grid { display: grid; gap: 8px; align-items: end; }
		.cbv-lp-card label.cbv-lp-lbl { display: block; font-size: 12px; font-weight: 600; margin: 0 0 2px; }
		.cbv-lp-card textarea { width: 100%; }
		.cbv-lp-card input[type="checkbox"] { width: auto; margin-right: 4px; }
		.cbv-lp-card .cbv-lp-check { font-size: 12px; align-self: center; }
		.cbv-lp-g4 { grid-template-columns: 1fr 1fr 1fr; }
		.cbv-lp-g3 { grid-template-columns: 2fr 1fr 1fr; }
		.cbv-lp-g5 { grid-template-columns: 1fr 2fr 90px 110px; }
		.cbv-lp-g6 { grid-template-columns: 1fr 2fr 90px 150px 110px; }
		.cbv-lp-cols { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; max-width: 760px; }
	</style>
	<?php
} );

function cbv_lp_provider_meta( $post, $group ) {
	return cbv_lp_clean_group( $group, get_post_meta( $post->ID, 'cbv_lp_' . $group, true ) );
}

function cbv_lp_render_columns_box( $post ) {
	wp_nonce_field( 'cbv_lp_provider_save', 'cbv_lp_provider_nonce' );
	$cols = cbv_lp_provider_meta( $post, 'columns' );
	?>
	<p class="description">Up to four names for the price columns on the price board, e.g. Base, Essential, Premium. A trip inherits these unless it enters its own. Leave unused slots blank.</p>
	<div class="cbv-lp-cols">
		<?php foreach ( $cols as $i => $name ) : ?>
			<p><label class="cbv-lp-lbl" for="cbv_lp_columns_<?php echo (int) $i; ?>">Column <?php echo (int) $i + 1; ?></label>
			<input type="text" id="cbv_lp_columns_<?php echo (int) $i; ?>" name="cbv_lp_columns[<?php echo (int) $i; ?>]" value="<?php echo esc_attr( $name ); ?>" maxlength="30" style="width:100%;"></p>
		<?php endforeach; ?>
	</div>
	<?php
}

/* ---- included cards ---- */
function cbv_lp_included_row( $i, $row ) {
	$p = 'cbv_lp_included[' . $i . ']';
	?>
	<div class="cb-repeater-row cbv-lp-card">
		<div class="cbv-lp-grid cbv-lp-g4">
			<div><label class="cbv-lp-lbl">Label</label><input type="text" name="<?php echo esc_attr( $p ); ?>[label]" value="<?php echo esc_attr( $row['label'] ?? '' ); ?>" placeholder="FARE"></div>
			<div><label class="cbv-lp-lbl">Title</label><input type="text" name="<?php echo esc_attr( $p ); ?>[title]" value="<?php echo esc_attr( $row['title'] ?? '' ); ?>" placeholder="Base"></div>
			<div><label class="cbv-lp-lbl">Subline (which cabins)</label><input type="text" name="<?php echo esc_attr( $p ); ?>[subline]" value="<?php echo esc_attr( $row['subline'] ?? '' ); ?>" placeholder="INSIDER · SEA VIEW"></div>
		</div>
		<div class="cbv-lp-grid cbv-lp-g4" style="margin-top:8px;">
			<div><label class="cbv-lp-lbl">Style</label>
				<select name="<?php echo esc_attr( $p ); ?>[style]">
					<option value="" <?php selected( $row['style'] ?? '', '' ); ?>>Light</option>
					<option value="gold" <?php selected( $row['style'] ?? '', 'gold' ); ?>>Gold (featured)</option>
					<option value="dark" <?php selected( $row['style'] ?? '', 'dark' ); ?>>Dark</option>
				</select></div>
			<label class="cbv-lp-check"><input type="checkbox" name="<?php echo esc_attr( $p ); ?>[wide]" value="1" <?php checked( ! empty( $row['wide'] ) ); ?>> Wide card (stacks under the regular cards)</label>
			<div style="text-align:right;"><button type="button" class="button-link cb-repeater-remove" style="color:#b32d2e;">Remove card</button></div>
		</div>
		<div style="margin-top:8px;"><label class="cbv-lp-lbl">Bullets (one per line; **bold** allowed)</label>
			<textarea name="<?php echo esc_attr( $p ); ?>[bullets]" rows="5"><?php echo esc_textarea( $row['bullets'] ?? '' ); ?></textarea></div>
	</div>
	<?php
}

function cbv_lp_render_included_box( $post ) {
	$rows = cbv_lp_provider_meta( $post, 'included' );
	?>
	<p class="description">Cards for the "What's included" section. A trip that enters its own cards replaces these entirely (and its own footnote replaces this one).</p>
	<div class="cb-repeater" data-repeater="cbv_lp_included" data-index-token="__INC_INDEX__">
		<div class="cb-repeater-rows"><?php foreach ( $rows as $i => $row ) { cbv_lp_included_row( $i, $row ); } ?></div>
		<template class="cb-repeater-template"><?php cbv_lp_included_row( '__INC_INDEX__', array() ); ?></template>
		<button type="button" class="button cb-repeater-add">+ Add card</button>
	</div>
	<p style="margin-top:14px;"><label for="cbv_lp_included_footnote"><strong>Footnote under the cards</strong> <span class="description">(optional; one short note, e.g. change rules. **bold** and {tokens} work.)</span></label>
		<textarea id="cbv_lp_included_footnote" name="cbv_lp_included_footnote" rows="3" style="width:100%;"><?php echo esc_textarea( cbv_lp_provider_meta( $post, 'included_footnote' ) ); ?></textarea></p>
	<?php
}

/* ---- how-to-book steps ---- */
function cbv_lp_step_row( $i, $row ) {
	$p = 'cbv_lp_steps[' . $i . ']';
	?>
	<div class="cb-repeater-row cbv-lp-card">
		<div class="cbv-lp-grid" style="grid-template-columns: 1fr auto;">
			<div><label class="cbv-lp-lbl">Step title</label><input type="text" name="<?php echo esc_attr( $p ); ?>[title]" value="<?php echo esc_attr( $row['title'] ?? '' ); ?>"></div>
			<div><button type="button" class="button-link cb-repeater-remove" style="color:#b32d2e;">Remove step</button></div>
		</div>
		<div style="margin-top:8px;"><label class="cbv-lp-lbl">Text</label><textarea name="<?php echo esc_attr( $p ); ?>[body]" rows="3"><?php echo esc_textarea( $row['body'] ?? '' ); ?></textarea></div>
	</div>
	<?php
}

function cbv_lp_render_steps_box( $post ) {
	$rows = cbv_lp_provider_meta( $post, 'steps' );
	?>
	<p class="description">The numbered steps in "How to book", in order (01, 02, 03 ...). Booking-partner wording differs by provider, which is why it lives here. {tokens} like {deposit} and {date:final_payment} work in the text.</p>
	<div class="cb-repeater" data-repeater="cbv_lp_steps" data-index-token="__STEP_INDEX__">
		<div class="cb-repeater-rows"><?php foreach ( $rows as $i => $row ) { cbv_lp_step_row( $i, $row ); } ?></div>
		<template class="cb-repeater-template"><?php cbv_lp_step_row( '__STEP_INDEX__', array() ); ?></template>
		<button type="button" class="button cb-repeater-add">+ Add step</button>
	</div>
	<?php
}

/* ---- travel documents ---- */
function cbv_lp_doc_row( $i, $row ) {
	$p = 'cbv_lp_docs[' . $i . ']';
	?>
	<div class="cb-repeater-row cbv-lp-card">
		<div class="cbv-lp-grid cbv-lp-g3">
			<div><label class="cbv-lp-lbl">Document title</label><input type="text" name="<?php echo esc_attr( $p ); ?>[title]" value="<?php echo esc_attr( $row['title'] ?? '' ); ?>" placeholder="Cancellation policy"></div>
			<div><label class="cbv-lp-lbl">Last reviewed (internal, never shown)</label><input type="date" name="<?php echo esc_attr( $p ); ?>[reviewed]" value="<?php echo esc_attr( $row['reviewed'] ?? '' ); ?>"></div>
			<div style="text-align:right;"><button type="button" class="button-link cb-repeater-remove" style="color:#b32d2e;">Remove document</button></div>
		</div>
		<div style="margin-top:8px;"><label class="cbv-lp-lbl">Text (blank line = new paragraph; "- " = bullet; "&gt; " = small print; **bold**, [link](https://...))</label>
			<textarea name="<?php echo esc_attr( $p ); ?>[body]" rows="9"><?php echo esc_textarea( $row['body'] ?? '' ); ?></textarea></div>
	</div>
	<?php
}

function cbv_lp_render_docs_box( $post ) {
	$rows = cbv_lp_provider_meta( $post, 'docs' );
	?>
	<p class="description">Policy accordions at the bottom of the page. A trip's own documents are added AFTER these (they don't replace them). These are a copy of the provider's terms: check them against the provider's current policy, and use "last reviewed" to keep track.</p>
	<div class="cb-repeater" data-repeater="cbv_lp_docs" data-index-token="__DOC_INDEX__">
		<div class="cb-repeater-rows"><?php foreach ( $rows as $i => $row ) { cbv_lp_doc_row( $i, $row ); } ?></div>
		<template class="cb-repeater-template"><?php cbv_lp_doc_row( '__DOC_INDEX__', array() ); ?></template>
		<button type="button" class="button cb-repeater-add">+ Add document</button>
	</div>
	<?php
}

/* ---- key dates ---- */
function cbv_lp_key_date_row( $i, $row ) {
	$p = 'cbv_lp_key_dates[' . $i . ']';
	?>
	<div class="cb-repeater-row cbv-lp-card">
		<div class="cbv-lp-grid cbv-lp-g5">
			<div><label class="cbv-lp-lbl">Key (the {date:key} token)</label><input type="text" name="<?php echo esc_attr( $p ); ?>[key]" value="<?php echo esc_attr( $row['key'] ?? '' ); ?>" placeholder="final_payment"></div>
			<div><label class="cbv-lp-lbl">Title</label><input type="text" name="<?php echo esc_attr( $p ); ?>[title]" value="<?php echo esc_attr( $row['title'] ?? '' ); ?>" placeholder="Final payment due"></div>
			<div><label class="cbv-lp-lbl">Days before start</label><input type="number" name="<?php echo esc_attr( $p ); ?>[offset]" value="<?php echo esc_attr( $row['offset'] ?? '' ); ?>" placeholder="120"></div>
			<div><label class="cbv-lp-lbl">Dot colour</label>
				<select name="<?php echo esc_attr( $p ); ?>[dot]">
					<option value="" <?php selected( $row['dot'] ?? '', '' ); ?>>Gold</option>
					<option value="coral" <?php selected( $row['dot'] ?? '', 'coral' ); ?>>Coral</option>
					<option value="green" <?php selected( $row['dot'] ?? '', 'green' ); ?>>Green</option>
				</select></div>
		</div>
		<div class="cbv-lp-grid" style="grid-template-columns: 2fr 1fr auto; margin-top:8px;">
			<div><label class="cbv-lp-lbl">Date text override (optional; tokens allowed, e.g. a range)</label><input type="text" name="<?php echo esc_attr( $p ); ?>[date_text]" value="<?php echo esc_attr( $row['date_text'] ?? '' ); ?>" placeholder="{date:dining_rockstar} - {date:dining_base}"></div>
			<label class="cbv-lp-check"><input type="checkbox" name="<?php echo esc_attr( $p ); ?>[hidden]" value="1" <?php checked( ! empty( $row['hidden'] ) ); ?>> Token only (not a timeline row)</label>
			<div><button type="button" class="button-link cb-repeater-remove" style="color:#b32d2e;">Remove date</button></div>
		</div>
		<div style="margin-top:8px;"><label class="cbv-lp-lbl">Description</label><textarea name="<?php echo esc_attr( $p ); ?>[description]" rows="2"><?php echo esc_textarea( $row['description'] ?? '' ); ?></textarea></div>
	</div>
	<?php
}

function cbv_lp_render_key_dates_box( $post ) {
	$rows = cbv_lp_provider_meta( $post, 'key_dates' );
	?>
	<p class="description">Dates worked out from each trip's start date: "days before start" 120 on a trip starting Oct 25, 2027 gives June 27, 2027 (use a minus number for after the start). A trip with no start date shows none of these. The key becomes a token usable anywhere in this provider's text, like <code>{date:final_payment}</code>.</p>
	<div class="cb-repeater" data-repeater="cbv_lp_key_dates" data-index-token="__DATE_INDEX__">
		<div class="cb-repeater-rows"><?php foreach ( $rows as $i => $row ) { cbv_lp_key_date_row( $i, $row ); } ?></div>
		<template class="cb-repeater-template"><?php cbv_lp_key_date_row( '__DATE_INDEX__', array() ); ?></template>
		<button type="button" class="button cb-repeater-add">+ Add key date</button>
	</div>
	<?php
}

/** Everything posted for a provider -> the five values stored. Pure (no globals/writes) so it is testable. */
function cbv_lp_sanitize_provider( $posted ) {
	$posted = is_array( $posted ) ? $posted : array();
	$out    = array();
	foreach ( array( 'columns', 'included', 'included_footnote', 'steps', 'docs', 'key_dates' ) as $group ) {
		$out[ $group ] = cbv_lp_clean_group( $group, $posted[ $group ] ?? ( 'included_footnote' === $group ? '' : array() ) );
	}
	return $out;
}

add_action( 'save_post_cb_provider', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_lp_provider_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_lp_provider_nonce'], 'cbv_lp_provider_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$clean = cbv_lp_sanitize_provider( array(
		'columns'   => wp_unslash( $_POST['cbv_lp_columns'] ?? array() ),
		'included'  => wp_unslash( $_POST['cbv_lp_included'] ?? array() ),
		'included_footnote' => wp_unslash( $_POST['cbv_lp_included_footnote'] ?? '' ),
		'steps'     => wp_unslash( $_POST['cbv_lp_steps'] ?? array() ),
		'docs'      => wp_unslash( $_POST['cbv_lp_docs'] ?? array() ),
		'key_dates' => wp_unslash( $_POST['cbv_lp_key_dates'] ?? array() ),
	) );

	foreach ( $clean as $group => $value ) {
		update_post_meta( $post_id, 'cbv_lp_' . $group, $value );
	}
} );

/* ==========================================================================
   6. Trip edit screen: "Key dates for this trip". Overrides one provider date
      (same key), adds a trip-only date, or marks a provider date not
      applicable. Reuses the provider screen's repeater look and the shared
      Add/Remove-row script. One nonce, no nested <form>.
   ========================================================================== */
add_action( 'add_meta_boxes_cb_trip', function () {
	add_meta_box( 'cbv_lp_trip_dates', 'Key dates for this trip (new landing design)', 'cbv_lp_render_trip_dates_box', 'cb_trip', 'normal', 'default' );
} );

function cbv_lp_trip_date_row( $i, $row ) {
	$p = 'cbv_lp_trip_key_dates[' . $i . ']';
	?>
	<div class="cb-repeater-row cbv-lp-card">
		<div class="cbv-lp-grid cbv-lp-g6">
			<div><label class="cbv-lp-lbl">Key</label><input type="text" name="<?php echo esc_attr( $p ); ?>[key]" value="<?php echo esc_attr( $row['key'] ?? '' ); ?>" placeholder="final_payment"></div>
			<div><label class="cbv-lp-lbl">Title (blank = keep the provider's)</label><input type="text" name="<?php echo esc_attr( $p ); ?>[title]" value="<?php echo esc_attr( $row['title'] ?? '' ); ?>"></div>
			<div><label class="cbv-lp-lbl">Days before start</label><input type="number" name="<?php echo esc_attr( $p ); ?>[offset]" value="<?php echo esc_attr( $row['offset'] ?? '' ); ?>"></div>
			<div><label class="cbv-lp-lbl">OR a fixed date (wins)</label><input type="date" name="<?php echo esc_attr( $p ); ?>[date]" value="<?php echo esc_attr( $row['date'] ?? '' ); ?>"></div>
			<div><label class="cbv-lp-lbl">Dot colour</label>
				<select name="<?php echo esc_attr( $p ); ?>[dot]">
					<option value="" <?php selected( $row['dot'] ?? '', '' ); ?>>Keep provider's</option>
					<option value="gold" <?php selected( $row['dot'] ?? '', 'gold' ); ?>>Gold</option>
					<option value="coral" <?php selected( $row['dot'] ?? '', 'coral' ); ?>>Coral</option>
					<option value="green" <?php selected( $row['dot'] ?? '', 'green' ); ?>>Green</option>
				</select></div>
		</div>
		<div class="cbv-lp-grid" style="grid-template-columns: 2fr auto auto auto; margin-top:8px;">
			<div><label class="cbv-lp-lbl">Date text override (optional; tokens allowed)</label><input type="text" name="<?php echo esc_attr( $p ); ?>[date_text]" value="<?php echo esc_attr( $row['date_text'] ?? '' ); ?>"></div>
			<label class="cbv-lp-check"><input type="checkbox" name="<?php echo esc_attr( $p ); ?>[off]" value="1" <?php checked( ! empty( $row['off'] ) ); ?>> Not applicable on this trip</label>
			<label class="cbv-lp-check"><input type="checkbox" name="<?php echo esc_attr( $p ); ?>[hidden]" value="1" <?php checked( ! empty( $row['hidden'] ) ); ?>> Token only</label>
			<div><button type="button" class="button-link cb-repeater-remove" style="color:#b32d2e;">Remove</button></div>
		</div>
		<div style="margin-top:8px;"><label class="cbv-lp-lbl">Description (blank = keep the provider's)</label><textarea name="<?php echo esc_attr( $p ); ?>[description]" rows="2"><?php echo esc_textarea( $row['description'] ?? '' ); ?></textarea></div>
	</div>
	<?php
}

function cbv_lp_render_trip_dates_box( $post ) {
	wp_nonce_field( 'cbv_lp_trip_dates_save', 'cbv_lp_trip_dates_nonce' );
	$rows      = cbv_lp_trip_key_dates( $post->ID );
	$inherited = cbv_lp_provider_key_dates( $post->ID );
	$effective = array();
	foreach ( cbv_lp_effective_key_dates( $post->ID ) as $e ) {
		$effective[ $e['key'] ] = $e;
	}
	$trip_keys = array();
	foreach ( $rows as $r ) {
		$trip_keys[ $r['key'] ] = $r;
	}
	?>
	<p class="description">Used only by the new landing design. A row with the <strong>same key</strong> as one of the provider's dates replaces just that date (for example a group contract with a different final-payment deadline); fill in only what changes, the rest is inherited. A new key adds a date. Tick "Not applicable" to drop a provider date from this trip. A row with no date and no offset changes nothing. Dates need the trip's start date unless you give a fixed date.</p>

	<?php if ( $inherited ) : ?>
		<table class="widefat striped" style="max-width:760px; margin-bottom:12px;">
			<thead><tr><th>Provider date (key)</th><th>Provider's date for this trip</th><th>On this trip</th></tr></thead>
			<tbody>
			<?php foreach ( $inherited as $p ) :
				$eff  = $effective[ $p['key'] ] ?? null;
				$trip = $trip_keys[ $p['key'] ] ?? null;
				$prov = '' !== $p['ymd'] ? cbv_lp_format_date( $p['ymd'] ) : '(needs a start date)';
				if ( $trip && $trip['off'] ) {
					$now = 'Not applicable';
				} elseif ( ! $eff ) {
					$now = '&mdash;';
				} elseif ( 'trip' === $eff['source'] ) {
					$now = esc_html( '' !== $eff['ymd'] ? cbv_lp_format_date( $eff['ymd'] ) : '(needs a start date)' ) . ' <em>(overridden below)</em>';
				} else {
					$now = esc_html( $prov ) . ' <em>(inherited)</em>';
				}
				?>
				<tr>
					<td><strong><?php echo esc_html( $p['title'] ? $p['title'] : $p['key'] ); ?></strong> <code><?php echo esc_html( $p['key'] ); ?></code><?php echo $p['hidden'] ? ' <em>(token only)</em>' : ''; ?></td>
					<td><?php echo esc_html( $prov ); ?></td>
					<td><?php echo $now; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php elseif ( ! cbv_lp_provider_id( $post->ID ) ) : ?>
		<p class="description"><em>No provider chosen on this trip (Landing Page Settings box), so there are no inherited dates; any dates added here are this trip's own.</em></p>
	<?php endif; ?>

	<div class="cb-repeater" data-repeater="cbv_lp_trip_key_dates" data-index-token="__TDATE_INDEX__">
		<div class="cb-repeater-rows"><?php foreach ( $rows as $i => $row ) { cbv_lp_trip_date_row( $i, $row ); } ?></div>
		<template class="cb-repeater-template"><?php cbv_lp_trip_date_row( '__TDATE_INDEX__', array() ); ?></template>
		<button type="button" class="button cb-repeater-add">+ Add / override a date</button>
	</div>
	<?php
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_lp_trip_dates_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_lp_trip_dates_nonce'], 'cbv_lp_trip_dates_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	update_post_meta( $post_id, 'cbv_lp_key_dates', cbv_lp_clean_trip_key_dates( wp_unslash( $_POST['cbv_lp_trip_key_dates'] ?? array() ) ) );
} );
