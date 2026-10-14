<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — What's included + Pack list
 * Description: Two sections from the original trip-page mockup, after
 *              Travel documents (owner, 2026-10-14), on the new design and
 *              the members' trip page:
 *                What's included -- (a) "In your all-in price", computed from
 *                  the trip's prices (a part is listed only when every price
 *                  on the price board includes it); (b) "Included by the
 *                  cruise line", the provider's What's included cards
 *                  (verified lines only) + its footnote + an optional "On
 *                  every fare" line; (c) "The CBGV group program", per trip.
 *                Pack list -- the trip's luggage tags + forecast chip, and
 *                  "Leave at home": the provider's lines (each with its
 *                  source, admin only) + the trip's own + a link to the
 *                  provider's full list.
 *              Also hides the old "Highlights" block on the new design only
 *              (the group program list replaces it; the old page keeps it).
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-included.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CBV_INC_MAX_TAGS = 6;

/* ==========================================================================
   1. Cleaning (shared by the admin saves and the readers).
   ========================================================================== */
/** Text with one item per line -> array of non-empty plain lines (at most $max, each at most 200 chars). */
function cbv_inc_clean_lines( $raw, $max = 20 ) {
	$lines = is_array( $raw ) ? $raw : preg_split( '/\R/', (string) ( is_scalar( $raw ) ? $raw : '' ) );
	$out   = array();
	foreach ( (array) $lines as $line ) {
		if ( ! is_scalar( $line ) ) {
			continue;
		}
		$line = trim( sanitize_text_field( (string) $line ) );
		$line = function_exists( 'mb_substr' ) ? mb_substr( $line, 0, 200 ) : substr( $line, 0, 200 );
		if ( '' !== $line ) {
			$out[] = $line;
		}
		if ( count( $out ) >= $max ) {
			break;
		}
	}
	return $out;
}

/** Pack tags: rows of category + title + items (lines); empty rows dropped; at most CBV_INC_MAX_TAGS. */
function cbv_inc_clean_tags( $raw ) {
	$out = array();
	foreach ( (array) $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$tag = array(
			'category' => sanitize_text_field( (string) ( $row['category'] ?? '' ) ),
			'title'    => sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
			'items'    => cbv_inc_clean_lines( $row['items'] ?? '', 12 ),
		);
		if ( '' === $tag['category'] && '' === $tag['title'] && ! $tag['items'] ) {
			continue;
		}
		$out[] = $tag;
		if ( count( $out ) >= CBV_INC_MAX_TAGS ) {
			break;
		}
	}
	return $out;
}

/** Provider "Leave at home" rows: public text + source link + checked date (both admin only). */
function cbv_inc_clean_leave_rows( $raw ) {
	$out = array();
	foreach ( (array) $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$text = trim( sanitize_text_field( (string) ( $row['text'] ?? '' ) ) );
		if ( '' === $text ) {
			continue;
		}
		$checked = trim( (string) ( $row['checked'] ?? '' ) );
		$out[]   = array(
			'text'    => function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 200 ) : substr( $text, 0, 200 ),
			'source'  => esc_url_raw( (string) ( $row['source'] ?? '' ), array( 'http', 'https' ) ),
			'checked' => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $checked ) ? $checked : '',
		);
		if ( count( $out ) >= 20 ) {
			break;
		}
	}
	return $out;
}

/* ==========================================================================
   2. Data.
   ========================================================================== */
/** Is this trip's event type a cruise? */
function cbv_inc_is_cruise( $trip_id ) {
	return ! function_exists( 'cbv_lp_event_type' ) || 'cruise' === cbv_lp_event_type( $trip_id );
}

/**
 * "In your all-in price": the parts every price on the price board includes
 * (owner: only parts the trip really includes). Each item is a plain label.
 */
function cbv_inc_allin_parts( $trip_id ) {
	$board = function_exists( 'cbv_lp_price_board_data' ) ? cbv_lp_price_board_data( $trip_id ) : null;
	$cells = array();
	foreach ( (array) ( $board['groups'] ?? array() ) as $g ) {
		foreach ( (array) ( $g['rows'] ?? array() ) as $r ) {
			foreach ( (array) ( $r['cells'] ?? array() ) as $c ) {
				$cells[] = (array) ( $c['parts'] ?? array() );
			}
		}
	}
	if ( ! $cells ) {
		return array();
	}
	$every = function ( $key ) use ( $cells ) {
		foreach ( $cells as $p ) {
			if ( (float) ( $p[ $key ] ?? 0 ) <= 0 ) {
				return false;
			}
		}
		return true;
	};

	// Group rate: every on-board price point has a discount.
	$discount = true;
	$seen     = false;
	foreach ( (array) ( function_exists( 'cb_trip_get_pricing_tiers' ) ? cb_trip_get_pricing_tiers( $trip_id ) : array() ) as $tier ) {
		foreach ( (array) ( $tier['occupancy_points'] ?? array() ) as $pt ) {
			if ( ! is_array( $pt ) || ( function_exists( 'cbv_lp_point_column' ) && ! cbv_lp_point_column( $pt ) ) ) {
				continue;
			}
			$seen = true;
			if ( (float) ( $pt['discount'] ?? 0 ) <= 0 ) {
				$discount = false;
			}
		}
	}
	$cruise = cbv_inc_is_cruise( $trip_id );
	$out    = array();
	if ( $every( 'cruise_fare' ) ) {
		$out[] = ( $cruise ? 'Cruise fare' : 'Travel price' ) . ( $seen && $discount ? ', at our group rate' : '' );
	}
	if ( $every( 'taxes_fees' ) ) {
		$out[] = 'Taxes & fees';
	}
	if ( $every( 'gratuities' ) ) {
		$out[] = 'Prepaid gratuities';
	}
	if ( $every( 'protection' ) ) {
		$out[] = $cruise ? 'Voyage Protection' : 'Travel protection';
	}
	if ( $every( 'planning_fee' ) ) {
		$fee   = function_exists( 'cbv_lp_planning_fee_shown' ) ? cbv_lp_planning_fee_shown( $trip_id, 'page' ) : null;
		$basis = array( 'per_traveler' => 'per traveler', 'per_cabin' => $cruise ? 'per cabin' : 'per room' );
		$amt   = $fee ? (float) $fee['amount'] : 0;
		$out[] = 'CBGV Group Experience Fee' . ( $amt > 0 ? ' ($' . ( floor( $amt ) == $amt ? number_format( $amt ) : number_format( $amt, 2 ) ) . ( isset( $basis[ $fee['basis'] ] ) ? ' ' . $basis[ $fee['basis'] ] : '' ) . ')' : '' );
	}
	return $out;
}

/** The provider's What's included cards, footnote and "On every fare" line (verified content only lives there). */
function cbv_inc_cruise_line( $trip_id ) {
	$prov = function_exists( 'cbv_lp_provider_id' ) ? cbv_lp_provider_id( $trip_id ) : 0;
	return array(
		'cards'      => function_exists( 'cbv_lp_group' ) ? (array) cbv_lp_group( $trip_id, 'included' ) : array(),
		'footnote'   => function_exists( 'cbv_lp_group' ) ? trim( (string) cbv_lp_group( $trip_id, 'included_footnote' ) ) : '',
		'every_fare' => $prov ? cbv_inc_clean_lines( get_post_meta( $prov, 'cbv_lp_every_fare', true ), 10 ) : array(),
	);
}

function cbv_inc_program( $trip_id ) {
	return array(
		'items' => cbv_inc_clean_lines( get_post_meta( $trip_id, 'cbv_lp_program', true ) ),
		'note'  => trim( sanitize_text_field( (string) get_post_meta( $trip_id, 'cbv_lp_program_note', true ) ) ),
	);
}

/** Pack list: tags + forecast + "Leave at home" (provider's lines, then the trip's) + the provider's full-list link. */
function cbv_inc_pack( $trip_id ) {
	$prov  = function_exists( 'cbv_lp_provider_id' ) ? cbv_lp_provider_id( $trip_id ) : 0;
	$leave = array();
	if ( $prov ) {
		foreach ( cbv_inc_clean_leave_rows( get_post_meta( $prov, 'cbv_lp_leave_home', true ) ) as $r ) {
			$leave[] = $r['text'];
		}
	}
	$leave = array_merge( $leave, cbv_inc_clean_lines( get_post_meta( $trip_id, 'cbv_lp_leave_home_trip', true ), 10 ) );
	$link  = $prov ? esc_url_raw( (string) get_post_meta( $prov, 'cbv_lp_leave_home_link', true ), array( 'http', 'https' ) ) : '';
	return array(
		'tags'     => cbv_inc_clean_tags( get_post_meta( $trip_id, 'cbv_lp_pack_tags', true ) ),
		'forecast' => trim( sanitize_text_field( (string) get_post_meta( $trip_id, 'cbv_lp_pack_forecast', true ) ) ),
		'leave'    => $leave,
		'link'     => $link,
		'link_label' => $link ? ( trim( sanitize_text_field( (string) get_post_meta( $prov, 'cbv_lp_leave_home_link_label', true ) ) ) ?: 'Full list of prohibited items' ) : '',
	);
}

/**
 * The line under "Every fare comes with extras", naming only the parts the
 * section actually shows: "What your all-in price covers, what Virgin
 * Voyages includes with each fare, and what our group adds."
 */
function cbv_inc_lede( $trip_id ) {
	$prov  = function_exists( 'cbv_lp_provider_name' ) ? cbv_lp_provider_name( $trip_id ) : '';
	$line  = cbv_inc_cruise_line( $trip_id );
	$bits  = array();
	if ( cbv_inc_allin_parts( $trip_id ) ) {
		$bits[] = 'what your all-in price covers';
	}
	if ( $line['cards'] || $line['every_fare'] ) {
		$bits[] = 'what ' . ( '' !== $prov ? $prov : 'the cruise line' ) . ' includes with each fare';
	}
	if ( cbv_inc_program( $trip_id )['items'] ) {
		$bits[] = 'what our group adds';
	}
	if ( ! $bits ) {
		return '';
	}
	$last = array_pop( $bits );
	$text = $bits ? implode( ', ', $bits ) . ( count( $bits ) > 1 ? ',' : '' ) . ' and ' . $last : $last;
	return ucfirst( $text ) . '.';
}

/* ==========================================================================
   3. Shared markup pieces (both pages).
   ========================================================================== */
function cbv_inc_list_html( $items, $class, $trip_id ) {
	$out = '<ul class="' . esc_attr( $class ) . '">';
	foreach ( $items as $it ) {
		$out .= '<li>' . ( function_exists( 'cbv_lp_inline' ) ? cbv_lp_inline( function_exists( 'cbv_lp_apply_tokens' ) ? cbv_lp_apply_tokens( $it, $trip_id ) : $it ) : esc_html( $it ) ) . '</li>';
	}
	return $out . '</ul>';
}

function cbv_inc_cards_html( $cards, $trip_id ) {
	$out = '<div class="cbv-inc-cards">';
	foreach ( $cards as $c ) {
		$cls  = 'cbv-inc-card' . ( 'gold' === $c['style'] ? ' cbv-inc-card--gold' : '' ) . ( 'dark' === $c['style'] ? ' cbv-inc-card--dark' : '' ) . ( ! empty( $c['wide'] ) ? ' cbv-inc-card--wide' : '' );
		$out .= '<div class="' . esc_attr( $cls ) . '">';
		if ( '' !== $c['label'] ) {
			$out .= '<span class="cbv-inc-card-label">' . esc_html( $c['label'] ) . '</span>';
		}
		$out .= '<span class="cbv-inc-card-title">' . esc_html( $c['title'] ) . '</span>';
		if ( '' !== $c['subline'] ) {
			$out .= '<span class="cbv-inc-card-sub">' . esc_html( $c['subline'] ) . '</span>';
		}
		$lines = function_exists( 'cbv_lp_lines' ) ? cbv_lp_lines( $c['bullets'], $trip_id ) : array_map( 'esc_html', preg_split( '/\R/', $c['bullets'] ) );
		if ( $lines ) {
			$out .= '<ul><li>' . implode( '</li><li>', $lines ) . '</li></ul>';
		}
		$out .= '</div>';
	}
	return $out . '</div>';
}

/** The three parts of What's included (shared). '' when there is nothing at all. */
function cbv_inc_included_body( $trip_id ) {
	$parts = cbv_inc_allin_parts( $trip_id );
	$line  = cbv_inc_cruise_line( $trip_id );
	$prog  = cbv_inc_program( $trip_id );
	$prov  = function_exists( 'cbv_lp_provider_name' ) ? cbv_lp_provider_name( $trip_id ) : '';
	$out   = '';
	if ( $parts ) {
		$out .= '<p class="cbv-inc-sub">In your all-in price</p><ul class="cbv-inc-allin">';
		foreach ( $parts as $p ) {
			$out .= '<li><span class="cbv-inc-tick" aria-hidden="true">✓</span>' . esc_html( $p ) . '</li>';
		}
		$out .= '</ul>';
	}
	if ( $line['cards'] || $line['every_fare'] ) {
		$out .= '<p class="cbv-inc-sub">Included by ' . esc_html( '' !== $prov ? $prov : 'the cruise line' ) . '</p>';
		if ( $line['every_fare'] ) {
			$out .= '<p class="cbv-inc-every"><strong>On every fare:</strong> ' . esc_html( implode( ', ', $line['every_fare'] ) ) . '</p>';
		}
		if ( $line['cards'] ) {
			$out .= cbv_inc_cards_html( $line['cards'], $trip_id );
		}
		if ( '' !== $line['footnote'] ) {
			$out .= '<p class="cbv-inc-foot">' . ( function_exists( 'cbv_lp_inline' ) ? cbv_lp_inline( function_exists( 'cbv_lp_apply_tokens' ) ? cbv_lp_apply_tokens( $line['footnote'], $trip_id ) : $line['footnote'] ) : esc_html( $line['footnote'] ) ) . '</p>';
		}
	}
	if ( $prog['items'] ) {
		$out .= '<p class="cbv-inc-sub">The CBGV group program</p><div class="cbv-inc-program">' . cbv_inc_list_html( $prog['items'], 'cbv-inc-program-list', $trip_id ) . '</div>';
		if ( '' !== $prog['note'] ) {
			$out .= '<p class="cbv-inc-foot">' . esc_html( $prog['note'] ) . '</p>';
		}
	}
	return $out;
}

/** The pack list body (shared). '' when there is nothing. */
function cbv_inc_pack_body( $trip_id ) {
	$pk = cbv_inc_pack( $trip_id );
	if ( ! $pk['tags'] && ! $pk['leave'] ) {
		return '';
	}
	$out = '<div class="cbv-inc-tags">';
	foreach ( $pk['tags'] as $i => $t ) {
		$label = sprintf( 'Tag %02d', $i + 1 ) . ( '' !== $t['category'] ? ' · ' . $t['category'] : '' );
		$out  .= '<div class="cbv-inc-tag"><span class="cbv-inc-tag-hole" aria-hidden="true"></span><span class="cbv-inc-tag-label">' . esc_html( $label ) . '</span>';
		if ( '' !== $t['title'] ) {
			$out .= '<span class="cbv-inc-tag-title">' . esc_html( $t['title'] ) . '</span>';
		}
		if ( $t['items'] ) {
			$out .= cbv_inc_list_html( $t['items'], 'cbv-inc-tag-list', $trip_id );
		}
		$out .= '</div>';
	}
	if ( $pk['leave'] ) {
		$out .= '<div class="cbv-inc-leave"><span class="cbv-inc-leave-label"><span aria-hidden="true">✕ </span>Leave at home</span><span class="cbv-inc-tag-title">Not boarding</span>' . cbv_inc_list_html( $pk['leave'], 'cbv-inc-leave-list', $trip_id );
		if ( $pk['link'] ) {
			$out .= '<p class="cbv-inc-leave-more"><a href="' . esc_url( $pk['link'] ) . '" target="_blank" rel="noopener">' . esc_html( $pk['link_label'] ) . '<span class="screen-reader-text"> (opens in a new tab)</span></a></p>';
		}
		$out .= '</div>';
	}
	return $out . '</div>';
}

/* ==========================================================================
   4. New design sections (after Travel documents).
   ========================================================================== */
function cbv_lp_render_included( $trip_id ) {
	if ( ! function_exists( 'cbv_lp_section_enabled' ) || ! cbv_lp_section_enabled( $trip_id, 'included' ) ) {
		return '';
	}
	$body = cbv_inc_included_body( $trip_id );
	if ( '' === $body ) {
		return '';
	}
	$labels = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
	$gate   = function_exists( 'cbv_lp_gate_label' ) ? cbv_lp_gate_label( $labels['sec_included'] ?? 'What\'s included' ) : '';
	$lede   = cbv_inc_lede( $trip_id );
	return '<section class="cbv-lp-included" id="included" aria-labelledby="cbv-lp-included-title"><div class="cbv-lp-inner cbv-lp-included-inner">'
		. '<div class="cbv-lp-included-head"><p class="cbv-lp-gate">' . esc_html( $gate ) . '</p><h2 class="cbv-lp-h2" id="cbv-lp-included-title">Every fare comes with extras</h2>' . ( '' !== $lede ? '<p class="cbv-lp-included-lede">' . esc_html( $lede ) . '</p>' : '' ) . '</div>'
		. $body . '</div></section>';
}

function cbv_lp_render_pack( $trip_id ) {
	if ( ! function_exists( 'cbv_lp_section_enabled' ) || ! cbv_lp_section_enabled( $trip_id, 'pack' ) ) {
		return '';
	}
	$body = cbv_inc_pack_body( $trip_id );
	if ( '' === $body ) {
		return '';
	}
	$labels = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
	$gate   = function_exists( 'cbv_lp_gate_label' ) ? cbv_lp_gate_label( $labels['sec_pack'] ?? 'Pack list' ) : '';
	$fc     = cbv_inc_pack( $trip_id )['forecast'];
	return '<section class="cbv-lp-pack" id="pack" aria-labelledby="cbv-lp-pack-title"><div class="cbv-lp-inner cbv-lp-pack-inner">'
		. '<div class="cbv-lp-pack-head"><div class="cbv-lp-pack-titles"><p class="cbv-lp-gate">' . esc_html( $gate ) . '</p><h2 class="cbv-lp-h2" id="cbv-lp-pack-title">Pack List: Vibes Only</h2></div>'
		. ( '' !== $fc ? '<span class="cbv-lp-pack-forecast">' . esc_html( $fc ) . '</span>' : '' ) . '</div>'
		. $body . '</div></section>';
}

/* ==========================================================================
   5. Members' trip page (gate07 calls this after Countdown + Travel
      documents; same switches as the new design).
   ========================================================================== */
function cbv_render_member_included_pack( $trip_id ) {
	$out = '';
	if ( function_exists( 'cbv_lp_section_enabled' ) && cbv_lp_section_enabled( $trip_id, 'included' ) ) {
		$body = cbv_inc_included_body( $trip_id );
		if ( '' !== $body ) {
			$out .= '<div class="trip-detail-section cbv-inc-member cbv-inc-member--included"><h3>What&#8217;s included</h3>' . $body . '</div>';
		}
	}
	if ( function_exists( 'cbv_lp_section_enabled' ) && cbv_lp_section_enabled( $trip_id, 'pack' ) ) {
		$body = cbv_inc_pack_body( $trip_id );
		if ( '' !== $body ) {
			$fc   = cbv_inc_pack( $trip_id )['forecast'];
			$out .= '<div class="trip-detail-section cbv-inc-member cbv-inc-member--pack"><h3>Pack list</h3>' . ( '' !== $fc ? '<p class="cbv-inc-forecast">' . esc_html( $fc ) . '</p>' : '' ) . $body . '</div>';
		}
	}
	return $out;
}

/* ==========================================================================
   6. The old "Highlights" block: hidden on the new design only (the group
      program list replaces it). Same DOMDocument approach as the other
      legacy strips; unchanged HTML when there is nothing to remove.
   ========================================================================== */
function cbv_lp_strip_legacy_highlights( $html ) {
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
	$nodes   = $xp->query( '//div[contains(concat(" ", normalize-space(@class), " "), " cbv-landing-section ")][.//div[contains(concat(" ", normalize-space(@class), " "), " cbv-landing-highlights ")]]' );
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
   7. Admin: provider box (Leave at home + On every fare) and trip box
      (group program, pack tags, forecast, trip's own Leave-at-home lines).
      Own nonces; no nested <form>.
   ========================================================================== */
add_action( 'add_meta_boxes_cb_provider', function () {
	add_meta_box( 'cbv_inc_prov', 'Pack list: Leave at home · and "On every fare"', 'cbv_inc_render_provider_box', 'cb_provider', 'normal', 'default' );
} );

function cbv_inc_leave_row( $i, $row ) {
	$p = 'cbv_inc_leave[' . $i . ']';
	?>
	<div class="cb-repeater-row cbv-lp-card">
		<div class="cbv-lp-grid" style="grid-template-columns: 2fr 1.4fr 130px auto;">
			<div><label class="cbv-lp-lbl">Line (shown)</label><input type="text" name="<?php echo esc_attr( $p ); ?>[text]" value="<?php echo esc_attr( $row['text'] ?? '' ); ?>" style="width:100%;"></div>
			<div><label class="cbv-lp-lbl">Source link (internal, never shown)</label><input type="url" name="<?php echo esc_attr( $p ); ?>[source]" value="<?php echo esc_attr( $row['source'] ?? '' ); ?>" style="width:100%;"></div>
			<div><label class="cbv-lp-lbl">Checked on (internal)</label><input type="date" name="<?php echo esc_attr( $p ); ?>[checked]" value="<?php echo esc_attr( $row['checked'] ?? '' ); ?>"></div>
			<div><button type="button" class="button-link cb-repeater-remove" style="color:#b32d2e;">Remove</button></div>
		</div>
	</div>
	<?php
}

function cbv_inc_render_provider_box( $post ) {
	wp_nonce_field( 'cbv_inc_provider_save', 'cbv_inc_provider_nonce' );
	$rows = cbv_inc_clean_leave_rows( get_post_meta( $post->ID, 'cbv_lp_leave_home', true ) );
	?>
	<p class="description"><strong>Leave at home</strong>: the "Not boarding" tag of the Pack list on every trip of this provider. Only rules checked on the provider's own site; keep the source link and date so the list can be re-checked.</p>
	<div class="cb-repeater" data-repeater="cbv_inc_leave" data-index-token="__LEAVE_INDEX__">
		<div class="cb-repeater-rows"><?php foreach ( $rows as $i => $row ) { cbv_inc_leave_row( $i, $row ); } ?></div>
		<template class="cb-repeater-template"><?php cbv_inc_leave_row( '__LEAVE_INDEX__', array() ); ?></template>
		<button type="button" class="button cb-repeater-add">+ Add line</button>
	</div>
	<p><label class="cbv-lp-lbl" for="cbv_inc_leave_link">Link to the provider's full list</label><input type="url" id="cbv_inc_leave_link" name="cbv_inc_leave_link" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, 'cbv_lp_leave_home_link', true ) ); ?>" style="width:100%;max-width:640px;"></p>
	<p><label class="cbv-lp-lbl" for="cbv_inc_leave_link_label">Link text</label><input type="text" id="cbv_inc_leave_link_label" name="cbv_inc_leave_link_label" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, 'cbv_lp_leave_home_link_label', true ) ); ?>" style="width:100%;max-width:640px;" placeholder="Full list of prohibited items"></p>
	<hr>
	<p><label class="cbv-lp-lbl" for="cbv_inc_every_fare">On every fare (optional; one per line; verified only)</label><textarea id="cbv_inc_every_fare" name="cbv_inc_every_fare" rows="3" style="width:100%;max-width:640px;"><?php echo esc_textarea( implode( "\n", cbv_inc_clean_lines( get_post_meta( $post->ID, 'cbv_lp_every_fare', true ), 10 ) ) ); ?></textarea></p>
	<?php
}

add_action( 'save_post_cb_provider', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_inc_provider_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cbv_inc_provider_nonce'] ) ), 'cbv_inc_provider_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'cbv_lp_leave_home', cbv_inc_clean_leave_rows( wp_unslash( $_POST['cbv_inc_leave'] ?? array() ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned row by row
	update_post_meta( $post_id, 'cbv_lp_leave_home_link', esc_url_raw( wp_unslash( (string) ( $_POST['cbv_inc_leave_link'] ?? '' ) ), array( 'http', 'https' ) ) );
	update_post_meta( $post_id, 'cbv_lp_leave_home_link_label', sanitize_text_field( wp_unslash( (string) ( $_POST['cbv_inc_leave_link_label'] ?? '' ) ) ) );
	update_post_meta( $post_id, 'cbv_lp_every_fare', cbv_inc_clean_lines( wp_unslash( $_POST['cbv_inc_every_fare'] ?? '' ), 10 ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned
}, 20 );

add_action( 'add_meta_boxes_cb_trip', function () {
	add_meta_box( 'cbv_inc_trip', 'What\'s included & pack list (new design + members\' page)', 'cbv_inc_render_trip_box', 'cb_trip', 'normal', 'default' );
} );

function cbv_inc_tag_row( $i, $row ) {
	$p = 'cbv_inc_tags[' . $i . ']';
	?>
	<div class="cb-repeater-row cbv-lp-card">
		<div class="cbv-lp-grid" style="grid-template-columns: 1fr 1.4fr auto;">
			<div><label class="cbv-lp-lbl">Category (after "TAG 01 ·")</label><input type="text" name="<?php echo esc_attr( $p ); ?>[category]" value="<?php echo esc_attr( $row['category'] ?? '' ); ?>" placeholder="Theme nights"></div>
			<div><label class="cbv-lp-lbl">Title</label><input type="text" name="<?php echo esc_attr( $p ); ?>[title]" value="<?php echo esc_attr( $row['title'] ?? '' ); ?>" placeholder="Must-pack outfits"></div>
			<div><button type="button" class="button-link cb-repeater-remove" style="color:#b32d2e;">Remove tag</button></div>
		</div>
		<div style="margin-top:8px;"><label class="cbv-lp-lbl">Items (one per line; **bold** works)</label><textarea name="<?php echo esc_attr( $p ); ?>[items]" rows="4"><?php echo esc_textarea( implode( "\n", (array) ( $row['items'] ?? array() ) ) ); ?></textarea></div>
	</div>
	<?php
}

function cbv_inc_render_trip_box( $post ) {
	wp_nonce_field( 'cbv_inc_trip_save', 'cbv_inc_trip_nonce' );
	$prog = cbv_inc_program( $post->ID );
	$tags = cbv_inc_clean_tags( get_post_meta( $post->ID, 'cbv_lp_pack_tags', true ) );
	?>
	<p class="description">"In your all-in price" is worked out from the Pricing Tiers (a part shows only when every price on the price board includes it). "Included by the cruise line" and "Leave at home" come from the trip's <strong>provider</strong>. The rest is here. Each part hides when empty; the sections follow the Sections table switches ("What's included", "Pack list").</p>
	<p><label class="cbv-lp-lbl" for="cbv_inc_program">The CBGV group program (one per line)</label><textarea id="cbv_inc_program" name="cbv_inc_program" rows="6" style="width:100%;"><?php echo esc_textarea( implode( "\n", $prog['items'] ) ); ?></textarea></p>
	<p><label class="cbv-lp-lbl" for="cbv_inc_program_note">Note under the program (optional)</label><input type="text" id="cbv_inc_program_note" name="cbv_inc_program_note" value="<?php echo esc_attr( $prog['note'] ); ?>" style="width:100%;" placeholder="All events and times are subject to change."></p>
	<hr>
	<p><label class="cbv-lp-lbl" for="cbv_inc_forecast">Pack list forecast chip (optional)</label><input type="text" id="cbv_inc_forecast" name="cbv_inc_forecast" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, 'cbv_lp_pack_forecast', true ) ); ?>" style="width:100%;" placeholder="FORECAST · LOW 80s °F · WARM & TROPICAL"></p>
	<p class="cbv-lp-lbl">Pack tags (up to <?php echo (int) CBV_INC_MAX_TAGS; ?>; numbered automatically)</p>
	<div class="cb-repeater" data-repeater="cbv_inc_tags" data-index-token="__TAG_INDEX__">
		<div class="cb-repeater-rows"><?php foreach ( $tags as $i => $row ) { cbv_inc_tag_row( $i, $row ); } ?></div>
		<template class="cb-repeater-template"><?php cbv_inc_tag_row( '__TAG_INDEX__', array() ); ?></template>
		<button type="button" class="button cb-repeater-add">+ Add tag</button>
	</div>
	<p><label class="cbv-lp-lbl" for="cbv_inc_leave_trip">Extra "Leave at home" lines for this trip only (one per line; added after the provider's)</label><textarea id="cbv_inc_leave_trip" name="cbv_inc_leave_trip" rows="3" style="width:100%;"><?php echo esc_textarea( implode( "\n", cbv_inc_clean_lines( get_post_meta( $post->ID, 'cbv_lp_leave_home_trip', true ), 10 ) ) ); ?></textarea></p>
	<?php
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_inc_trip_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cbv_inc_trip_nonce'] ) ), 'cbv_inc_trip_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'cbv_lp_program', cbv_inc_clean_lines( wp_unslash( $_POST['cbv_inc_program'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned
	update_post_meta( $post_id, 'cbv_lp_program_note', sanitize_text_field( wp_unslash( (string) ( $_POST['cbv_inc_program_note'] ?? '' ) ) ) );
	update_post_meta( $post_id, 'cbv_lp_pack_forecast', sanitize_text_field( wp_unslash( (string) ( $_POST['cbv_inc_forecast'] ?? '' ) ) ) );
	update_post_meta( $post_id, 'cbv_lp_pack_tags', cbv_inc_clean_tags( wp_unslash( $_POST['cbv_inc_tags'] ?? array() ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned row by row
	update_post_meta( $post_id, 'cbv_lp_leave_home_trip', cbv_inc_clean_lines( wp_unslash( $_POST['cbv_inc_leave_trip'] ?? '' ), 10 ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned
} );
