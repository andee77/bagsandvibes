<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Landing Redesign Price Board
 * Description: Step 9 of the public trip landing redesign: draws the price
 *              board from the Step 8 board data (checkedbags-lp-prices.php)
 *              and the Step 8b CBGV Group Experience Fee
 *              (checkedbags-lp-fees.php), plus the trip's "Price board" box
 *              (an optional header note and two optional photos). Only the
 *              new design calls any of this (preview with ?preview=new, or
 *              live per trip once switched on in checkedbags-lp-core.php);
 *              the current public design and Gate 07 are untouched.
 *
 *              The board: one collapsible bar per cabin group (only one open
 *              at a time; the group with the highlighted row starts open,
 *              else the first), a real table inside each with the all-in
 *              price per cabin for 2 travelers and the per-person price
 *              under it, and a "What's in this price?" toggle under each
 *              cabin with the itemized breakdown (the CBGV Group Experience
 *              Fee is its fifth line when the trip shows a fee). On phones
 *              each cabin becomes a small card. Under the board: the fee
 *              note (refund wording, "Children pay half ..." for
 *              per-traveler fees), only when a fee is shown.
 *
 *              Wording rules: travel is booked by the travel advisor and
 *              paid directly to the cruise line (or supplier); no deposit
 *              amount (unless it comes from the trip's own data) and no
 *              general "payment plans" in the header note; the host agency
 *              is never named here. Refined 2026-10-11: for Virgin Voyages
 *              trips, Uplift (Virgin's financing partner) may be named,
 *              always with "subject to approval"; never monthly amounts,
 *              rates or promised approval.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-priceboard.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Meta. Presentation-only; nothing here is read by the current design.
   ========================================================================== */
add_action( 'init', function () {
	register_post_meta( 'cb_trip', 'cbv_lp_priceboard', array(
		'type'          => 'array',
		'single'        => true,
		'default'       => array(),
		'show_in_rest'  => false,
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	) );
} );

/* ==========================================================================
   2. Small pure helpers (no globals, no writes), tested directly.
   ========================================================================== */

/** Posted "Price board" box values -> what gets stored. */
function cbv_lp_sanitize_priceboard( $raw ) {
	$raw  = is_array( $raw ) ? $raw : array();
	$note = isset( $raw['note'] ) && is_scalar( $raw['note'] ) ? sanitize_textarea_field( (string) $raw['note'] ) : '';
	$note = function_exists( 'mb_substr' ) ? mb_substr( $note, 0, 600 ) : substr( $note, 0, 600 );
	$img  = function ( $value ) {
		return function_exists( 'cbv_lp_clean_attachment_id' ) ? cbv_lp_clean_attachment_id( $value, 'image' ) : 0;
	};
	$focus = function ( $key ) use ( $raw ) {
		return function_exists( 'cbv_lp_featured_focus' ) ? cbv_lp_featured_focus( $raw, $key ) : '';
	};
	// Other fares available on request (owner, 2026-10-15): fare names, checked against the provider's fare columns when shown.
	$other = array();
	foreach ( (array) ( $raw['other'] ?? array() ) as $name ) {
		$name = is_scalar( $name ) ? trim( sanitize_text_field( (string) $name ) ) : '';
		if ( '' !== $name && ! in_array( $name, $other, true ) && count( $other ) < 4 ) {
			$other[] = $name;
		}
	}
	return array(
		'note'   => trim( $note ),
		'photo1' => $img( $raw['photo1'] ?? 0 ),
		'focus1' => $focus( 'focus1' ),
		'photo2' => $img( $raw['photo2'] ?? 0 ),
		'focus2' => $focus( 'focus2' ),
		'other'  => $other,
	);
}

/** "A", "A and B", "A, B and C". */
function cbv_lp_join_names( $names ) {
	$names = array_values( array_filter( array_map( 'strval', (array) $names ), 'strlen' ) );
	if ( count( $names ) < 2 ) {
		return (string) ( $names[0] ?? '' );
	}
	$last = array_pop( $names );
	return implode( ', ', $names ) . ' and ' . $last;
}

/**
 * The trip's "other fares on request": the ticked fares that are one of the provider's fare columns and not already
 * on the board, richest first (the provider lists its fares cheapest to richest, e.g. Base, Essential, Premium).
 */
function cbv_lp_board_other_fares( $trip_id, $board_columns = null ) {
	$box = cbv_lp_sanitize_priceboard( get_post_meta( $trip_id, 'cbv_lp_priceboard', true ) );
	if ( ! $box['other'] ) {
		return array();
	}
	if ( null === $board_columns ) {
		$board         = function_exists( 'cbv_lp_price_board_data' ) ? cbv_lp_price_board_data( $trip_id ) : null;
		$board_columns = (array) ( $board['columns'] ?? array() );
	}
	$norm  = function ( $s ) { return strtolower( trim( (string) $s ) ); };
	$shown = array_map( $norm, (array) $board_columns );
	$ticked = array_map( $norm, $box['other'] );
	$out   = array();
	foreach ( array_reverse( (array) ( function_exists( 'cbv_lp_group' ) ? cbv_lp_group( $trip_id, 'columns' ) : array() ) ) as $fare ) {
		$fare = trim( (string) $fare );
		if ( '' !== $fare && in_array( $norm( $fare ), $ticked, true ) && ! in_array( $norm( $fare ), $shown, true ) ) {
			$out[] = $fare;
		}
	}
	return $out;
}

/** "$2,552" for whole dollars, "$2,552.50" otherwise. */
function cbv_lp_board_money( $value ) {
	$value = (float) $value;
	return '$' . number_format_i18n( $value, floor( $value ) == $value ? 0 : 2 ); // phpcs:ignore Universal.Operators.StrictComparisons -- comparing a float to its own floor
}

/**
 * Which group starts open: the first group with a highlighted row, else the
 * first group (decided 2026-10-09, the Step 9 plan's recommendation).
 */
function cbv_lp_board_open_group( $groups ) {
	foreach ( array_values( (array) $groups ) as $i => $group ) {
		foreach ( (array) ( $group['rows'] ?? array() ) as $row ) {
			if ( ! empty( $row['highlight'] ) ) {
				return $i;
			}
		}
	}
	return 0;
}

/**
 * Does any price on the board carry this part (> $0)? The header note only
 * names parts the prices really contain, and "Tips & protection included"
 * is only said when every price contains both.
 */
function cbv_lp_board_part_use( $groups, $part ) {
	$any = false;
	$all = true;
	foreach ( (array) $groups as $group ) {
		foreach ( (array) ( $group['rows'] ?? array() ) as $row ) {
			foreach ( (array) ( $row['cells'] ?? array() ) as $cell ) {
				$has = (float) ( $cell['parts'][ $part ] ?? 0 ) > 0;
				$any = $any || $has;
				$all = $all && $has;
			}
		}
	}
	return array( 'any' => $any, 'all' => $any && $all );
}

/**
 * The automatic header note, as array( lead, rest ) of plain text (the lead
 * is drawn in bold):
 *   "All-in prices per cabin for 2 travelers:" "cruise fare, taxes & fees,
 *   prepaid gratuities and Voyage Protection, plus the CBGV Group Experience
 *   Fee. Travel is booked by your travel advisor and paid directly to the
 *   cruise line."
 * No deposit amount and no payment plans (compliance update 2026-10-09); a
 * trip's own header note may name Uplift on Virgin trips, always "subject to
 * approval" (refined 2026-10-11, see the file header).
 */
function cbv_lp_board_auto_note( $groups, $labels, $has_fee, $is_cruise ) {
	$parts = array( 'cruise fare' );
	foreach ( array( 'taxes_fees' => 'taxes & fees', 'gratuities' => 'prepaid gratuities', 'protection' => 'Voyage Protection' ) as $key => $words ) {
		if ( cbv_lp_board_part_use( $groups, $key )['any'] ) {
			$parts[] = $words;
		}
	}
	$last = array_pop( $parts );
	$list = $parts ? implode( ', ', $parts ) . ' and ' . $last : $last;
	if ( $has_fee ) {
		$list .= ', plus the CBGV Group Experience Fee';
	}
	$lead = sprintf(
		'All-in prices per %1$s for %2$d %3$s:',
		strtolower( (string) ( $labels['accommodation'] ?? 'cabin' ) ),
		defined( 'CBV_LP_PRICE_HEADCOUNT' ) ? CBV_LP_PRICE_HEADCOUNT : 2,
		strtolower( (string) ( $labels['party'] ?? 'travelers' ) )
	);
	$rest = $list . '. Travel is booked by your travel advisor and paid directly to ' . ( $is_cruise ? 'the cruise line.' : 'the supplier.' );
	return array( $lead, $rest );
}

/** The trip's registration link (/join/?trip=CODE), the board's "Reserve" button. */
function cbv_lp_board_registration_url( $trip_id ) {
	$code = trim( (string) get_post_meta( (int) $trip_id, 'cb_trip_code', true ) );
	return '' !== $code ? home_url( '/join/?trip=' . rawurlencode( $code ) ) : home_url( '/join/' );
}

/* ==========================================================================
   3. What the price board shows for a trip.
   ========================================================================== */

/**
 * Everything the board draws, or null when it is hidden (section Off, or no
 * price left on the board).
 */
function cbv_lp_priceboard_data( $trip_id ) {
	$trip_id = (int) $trip_id;
	if ( function_exists( 'cbv_lp_section_enabled' ) && ! cbv_lp_section_enabled( $trip_id, 'prices' ) ) {
		return null;
	}
	if ( ! function_exists( 'cbv_lp_price_board_data' ) ) {
		return null;
	}
	$board = cbv_lp_price_board_data( $trip_id );
	if ( empty( $board['groups'] ) || ( empty( $board['columns'] ) && ! cbv_lp_board_has_single( $board['groups'] ) ) ) {
		return null;
	}

	$labels    = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
	$is_cruise = ! function_exists( 'cbv_lp_event_type' ) || 'cruise' === cbv_lp_event_type( $trip_id );
	$box       = cbv_lp_sanitize_priceboard( get_post_meta( $trip_id, 'cbv_lp_priceboard', true ) );
	$tok       = function ( $text ) use ( $trip_id ) {
		return trim( function_exists( 'cbv_lp_apply_tokens' ) ? cbv_lp_apply_tokens( (string) $text, $trip_id ) : (string) $text );
	};

	// Header note: the trip's own (tokens + light markup), else the automatic one.
	$own = $tok( $box['note'] );
	if ( '' !== $own ) {
		$note_html = function_exists( 'cbv_lp_inline' ) ? cbv_lp_inline( $own ) : esc_html( $own );
	} else {
		$auto      = cbv_lp_board_auto_note( $board['groups'], $labels, ! empty( $board['fee'] ), $is_cruise );
		$note_html = '<strong>' . esc_html( $auto[0] ) . '</strong> ' . esc_html( $auto[1] );
	}

	$photos = array();
	foreach ( array( 1, 2 ) as $i ) {
		if ( $box[ 'photo' . $i ] ) {
			$photos[] = array( 'id' => $box[ 'photo' . $i ], 'focus' => function_exists( 'cbv_lp_focus_position' ) ? cbv_lp_focus_position( $box[ 'focus' . $i ] ) : 'center center' );
		}
	}

	// Footer bar: final payment date (if the trip has one) and, for cruises
	// whose every price includes them, "Tips & protection included".
	$foot  = array();
	$final = $tok( '{date:final_payment}' );
	if ( '' !== $final ) {
		$foot[] = 'Final payment due · ' . $final;
	}
	if ( $is_cruise && cbv_lp_board_part_use( $board['groups'], 'gratuities' )['all'] && cbv_lp_board_part_use( $board['groups'], 'protection' )['all'] ) {
		$foot[] = 'Tips & protection included';
	}

	return array(
		'section'   => $labels['sec_prices'] ?? 'Prices',
		'heading'   => $labels['price_heading'] ?? 'Prices',
		'note_html' => $note_html,
		'photos'    => $photos,
		'columns'   => $board['columns'],
		'headcount' => (int) $board['headcount'],
		'people'    => strtolower( (string) ( $labels['party'] ?? 'travelers' ) ),
		'cabin'     => (string) ( $labels['accommodation'] ?? 'cabin' ),
		'groups'    => $board['groups'],
		'open'      => cbv_lp_board_open_group( $board['groups'] ),
		'single'    => $is_cruise ? 'Suite fare' : 'One price',
		'foot'      => implode( ' · ', $foot ),
		'cta_label' => 'Reserve a ' . strtolower( (string) ( $labels['accommodation'] ?? 'cabin' ) ),
		'cta_url'   => cbv_lp_board_registration_url( $trip_id ),
		'fee_note'  => (array) $board['fee_note'],
		'shown'     => array_values( array_filter( array_map( 'strval', (array) $board['columns'] ), 'strlen' ) ),
		'other'     => cbv_lp_board_other_fares( $trip_id, (array) $board['columns'] ),
	);
}

/** The "other fares on request" note under the board, or ''. */
function cbv_lp_board_request_text( $shown, $other ) {
	if ( ! $other ) {
		return '';
	}
	$text = $shown ? 'Prices shown are ' . cbv_lp_join_names( $shown ) . ' fares. ' : '';
	return $text . cbv_lp_join_names( $other ) . " fares are also available. Ask us for a quote and we'll compare them for you. Some fares book outside our group block; your travel advisor will explain what that means for you.";
}

/** A board whose only rows are "one price only" rows has no columns but still has prices. */
function cbv_lp_board_has_single( $groups ) {
	foreach ( (array) $groups as $group ) {
		foreach ( (array) ( $group['rows'] ?? array() ) as $row ) {
			if ( ! empty( $row['single'] ) && ! empty( $row['cells'] ) ) {
				return true;
			}
		}
	}
	return false;
}

/** Is the board drawn for this trip? (The boarding pass's "Claim your seat" jumps to it when it is.) */
function cbv_lp_price_board_visible( $trip_id ) {
	return null !== cbv_lp_priceboard_data( $trip_id );
}

/* ==========================================================================
   4. Markup.
   ========================================================================== */

/** One price: the all-in total, the per-person price under it, and the headcount when it is not the usual 2. */
function cbv_lp_board_price_html( $cell, $d ) {
	$out = '<span class="cbv-lp-board-price">' . esc_html( cbv_lp_board_money( $cell['total'] ) ) . '</span>';
	$sub = cbv_lp_board_money( $cell['per_person'] ) . ' per person';
	if ( (int) $cell['headcount'] !== $d['headcount'] ) {
		$sub .= ' · for ' . (int) $cell['headcount'] . ' ' . $d['people'];
	}
	return $out . '<span class="cbv-lp-board-pp">' . esc_html( $sub ) . '</span>';
}

function cbv_lp_render_priceboard( $trip_id ) {
	$d = cbv_lp_priceboard_data( $trip_id );
	if ( ! $d ) {
		return '';
	}
	$gate  = function_exists( 'cbv_lp_gate_label' ) ? cbv_lp_gate_label( $d['section'] ) : $d['section'];
	$ncols = max( 1, count( $d['columns'] ) );

	ob_start();
	?>
	<section class="cbv-lp-prices" id="fares" aria-labelledby="cbv-lp-prices-title">
		<div class="cbv-lp-inner cbv-lp-prices-inner">
			<div class="cbv-lp-prices-head">
				<div class="cbv-lp-prices-titles">
					<p class="cbv-lp-gate"><?php echo esc_html( $gate ); ?></p>
					<h2 class="cbv-lp-h2" id="cbv-lp-prices-title"><?php echo esc_html( $d['heading'] ); ?></h2>
				</div>
				<p class="cbv-lp-prices-lede"><?php echo $d['note_html']; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped when built ?></p>
			</div>

			<div class="<?php echo $d['photos'] ? 'cbv-lp-prices-body' : 'cbv-lp-prices-body cbv-lp-prices-body--solo'; ?>">
				<?php if ( $d['photos'] ) : ?>
					<div class="cbv-lp-prices-photos">
						<?php
						foreach ( $d['photos'] as $p ) {
							echo wp_get_attachment_image( $p['id'], 'large', false, array( // phpcs:ignore WordPress.Security.EscapeOutput -- core image markup
								'class'   => 'cbv-lp-prices-img',
								'style'   => 'object-position:' . $p['focus'] . ';',
								'loading' => 'lazy',
							) );
						}
						?>
					</div>
				<?php endif; ?>

				<div class="cbv-lp-board" style="--cbv-lp-board-cols:<?php echo (int) $ncols; ?>">
					<div class="cbv-lp-board-cols" aria-hidden="true">
						<span><?php echo esc_html( $d['cabin'] . ' · tap a type to see prices' ); ?></span>
						<?php foreach ( $d['columns'] as $name ) : ?>
							<span><?php echo esc_html( $name ); ?></span>
						<?php endforeach; ?>
					</div>

					<?php foreach ( $d['groups'] as $gi => $group ) : ?>
						<details class="cbv-lp-board-group" name="cbv-lp-fare-groups"<?php echo $gi === $d['open'] ? ' open' : ''; ?>>
							<summary>
								<span class="cbv-lp-board-group-name"><?php echo esc_html( $group['name'] ); ?></span>
								<span class="cbv-lp-board-group-end">
									<span class="cbv-lp-board-from"><?php echo esc_html( 'From ' . cbv_lp_board_money( $group['from'] ) ); ?></span>
									<span class="cbv-lp-board-chev" aria-hidden="true">+</span>
								</span>
							</summary>
							<table class="cbv-lp-board-table">
								<caption class="cbv-lp-board-caption"><span class="cbv-lp-sr"><?php echo esc_html( $group['name'] . ': all-in prices per ' . $d['cabin'] . ' for ' . $d['headcount'] . ' ' . $d['people'] ); ?></span></caption>
								<colgroup><col class="cbv-lp-board-col-cabin"><col span="<?php echo (int) $ncols; ?>"></colgroup>
								<?php // The header row stays in the table (so screen readers pair each price with its column); only its text is hidden: the coloured bar above shows the names. ?>
								<thead class="cbv-lp-board-head">
									<tr>
										<th scope="col"><span class="cbv-lp-sr"><?php echo esc_html( ucfirst( $d['cabin'] ) ); ?></span></th>
										<?php foreach ( $d['columns'] as $name ) : ?>
											<th scope="col"><span class="cbv-lp-sr"><?php echo esc_html( $name ); ?></span></th>
										<?php endforeach; ?>
									</tr>
								</thead>
								<tbody>
								<?php foreach ( $group['rows'] as $ri => $row ) : ?>
									<tr class="<?php echo $row['highlight'] ? 'cbv-lp-board-row cbv-lp-board-row--highlight' : 'cbv-lp-board-row'; ?>">
										<th scope="row">
											<span class="cbv-lp-board-cabin"><?php echo esc_html( $row['name'] ); ?></span>
											<?php if ( '' !== $row['badge'] ) : ?>
												<span class="cbv-lp-board-badge"><?php echo esc_html( $row['badge'] ); ?></span>
											<?php endif; ?>
											<?php if ( '' !== $row['note'] ) : ?>
												<span class="cbv-lp-board-note"><?php echo esc_html( $row['note'] ); ?></span>
											<?php endif; ?>
										</th>
										<?php if ( $row['single'] ) : ?>
											<?php $cell = reset( $row['cells'] ); ?>
											<td class="cbv-lp-board-cell cbv-lp-board-cell--single" colspan="<?php echo (int) $ncols; ?>" data-col="<?php echo esc_attr( $d['single'] ); ?>">
												<?php echo cbv_lp_board_price_html( $cell, $d ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper ?>
												<span class="cbv-lp-board-tag"><?php echo esc_html( $d['single'] ); ?></span>
											</td>
										<?php else : ?>
											<?php foreach ( $d['columns'] as $col => $name ) : ?>
												<td class="cbv-lp-board-cell" data-col="<?php echo esc_attr( $name ); ?>">
													<?php if ( isset( $row['cells'][ $col ] ) ) : ?>
														<?php echo cbv_lp_board_price_html( $row['cells'][ $col ], $d ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper ?>
													<?php else : ?>
														<span class="cbv-lp-board-none" aria-hidden="true">&mdash;</span><span class="cbv-lp-sr">Not offered</span>
													<?php endif; ?>
												</td>
											<?php endforeach; ?>
										<?php endif; ?>
									</tr>
									<tr class="cbv-lp-board-more-row">
										<td colspan="<?php echo (int) ( $ncols + 1 ); ?>">
											<details class="cbv-lp-board-more">
												<summary>What's in this price?<span class="cbv-lp-sr"> (<?php echo esc_html( $row['name'] ); ?>)</span></summary>
												<ul>
													<?php foreach ( $row['cells'] as $col => $cell ) : ?>
														<li>
															<?php if ( ! $row['single'] && $ncols > 1 ) : ?>
																<strong><?php echo esc_html( ( $d['columns'][ $col ] ?? '' ) . ':' ); ?></strong>
															<?php endif; ?>
															<?php echo esc_html( function_exists( 'cbv_lp_price_breakdown_line' ) ? cbv_lp_price_breakdown_line( $cell, $d['people'] ) : '' ); ?>
														</li>
													<?php endforeach; ?>
												</ul>
											</details>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</details>
					<?php endforeach; ?>

					<div class="cbv-lp-board-foot">
						<?php if ( '' !== $d['foot'] ) : ?>
							<p class="cbv-lp-board-foot-text"><?php echo esc_html( $d['foot'] ); ?></p>
						<?php endif; ?>
						<a class="cbv-lp-board-cta" href="<?php echo esc_url( $d['cta_url'] ); ?>"><?php echo esc_html( $d['cta_label'] ); ?> <span aria-hidden="true">&rarr;</span></a>
					</div>
				</div>
			</div>

			<?php if ( $d['other'] ) : ?>
				<div class="cbv-lp-prices-request">
					<p><?php echo esc_html( cbv_lp_board_request_text( $d['shown'], $d['other'] ) ); ?></p>
					<a class="cbv-lp-prices-request-cta" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Request a quote <span aria-hidden="true">&rarr;</span></a>
				</div>
			<?php endif; ?>

			<?php if ( $d['fee_note'] ) : ?>
				<div class="cbv-lp-prices-notes">
					<?php foreach ( $d['fee_note'] as $line ) : ?>
						<p><?php echo esc_html( $line ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Today's "Choose Your Option" pricing cards are the board's job in the new
 * design, so they are removed from its markup (as Steps 4 and 6 removed the
 * old hero and itinerary table). If the markup can't be parsed, or the block
 * isn't found, the content is returned unchanged.
 */
function cbv_lp_strip_legacy_pricing( $html ) {
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
	$nodes   = $xp->query( '//div[contains(concat(" ", normalize-space(@class), " "), " cbv-landing-section ")][.//div[contains(concat(" ", normalize-space(@class), " "), " cbv-landing-pricing ")]]' );
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

/**
 * The legacy "Reserve Your Spot" block (owner, 2026-10-11): on the new design
 * the price board and "Claim your seat" are the one way to book, so the old
 * Reserve button and its "submits a request to join" note go. The "Download
 * Full Itinerary PDF" button stays (to move into the route section later);
 * the block itself goes only when nothing is left in it. Same DOMDocument
 * approach as cbv_lp_strip_legacy_pricing(); unchanged HTML when there is
 * nothing to remove.
 */
function cbv_lp_strip_legacy_cta( $html ) {
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
	$cta     = '//div[contains(concat(" ", normalize-space(@class), " "), " cbv-landing-cta ")]';
	$nodes   = $xp->query( $cta . '/a[contains(concat(" ", normalize-space(@class), " "), " btn-ticket ")] | ' . $cta . '/p[contains(concat(" ", normalize-space(@class), " "), " cbv-landing-cta-note ")]' );
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
	foreach ( iterator_to_array( $xp->query( $cta ) ) as $block ) {
		if ( ! $xp->query( './*', $block )->length && $block->parentNode ) {
			$block->parentNode->removeChild( $block );
		}
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
   5. Trip edit screen: the "Price board" box. The prices themselves stay in
      the Pricing Tiers box; the media chooser comes from
      checkedbags-lp-hero.php and the focus select from
      checkedbags-lp-featured.php.
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cbv_lp_priceboard', 'Price board (new design)', 'cbv_lp_render_priceboard_box', 'cb_trip', 'normal', 'default' );
} );

function cbv_lp_render_priceboard_box( $post ) {
	wp_nonce_field( 'cbv_lp_priceboard_save', 'cbv_lp_priceboard_nonce' );
	$b = cbv_lp_sanitize_priceboard( get_post_meta( $post->ID, 'cbv_lp_priceboard', true ) );
	?>
	<p class="description">Used only by the new landing design (preview with <code>?preview=new</code>). The prices themselves come from the <strong>Pricing Tiers</strong> box (Group, Badge, Note, Highlight, One price only, and each price's Price board column).</p>
	<p>
		<label for="cbv_lp_priceboard_note"><strong>Header note</strong> <span class="description">(optional)</span></label><br>
		<textarea name="cbv_lp_priceboard[note]" id="cbv_lp_priceboard_note" rows="3" maxlength="600" class="large-text"><?php echo esc_textarea( $b['note'] ); ?></textarea>
		<span class="description">Blank = the automatic note, e.g. "<strong>All-in prices per cabin for 2 travelers:</strong> cruise fare, taxes &amp; fees, prepaid gratuities and Voyage Protection, plus the CBGV Group Experience Fee. Travel is booked by your travel advisor and paid directly to the cruise line." Tokens such as {date:final_payment} work. Never name the host agency, and never say CBGV books the cabin. For Virgin trips, Uplift may be named, always with "subject to approval", never with monthly amounts or rates.</span>
	</p>
	<?php
	if ( function_exists( 'cbv_lp_media_field' ) && function_exists( 'cbv_lp_featured_focus_select' ) ) {
		cbv_lp_media_field( 'cbv_lp_priceboard[photo1]', $b['photo1'], 'image', 'Photo 1 (optional, e.g. a cabin)', 'Shown beside the board on a computer, above it on smaller screens. Virgin pictures from the First Mates toolkit only, unaltered. Give it Alt Text in the Media Library.' );
		echo '<p>';
		cbv_lp_featured_focus_select( 'cbv_lp_priceboard[focus1]', 'cbv_lp_priceboard_focus1', $b['focus1'], 'Photo 1 focus point' );
		echo '</p>';
		cbv_lp_media_field( 'cbv_lp_priceboard[photo2]', $b['photo2'], 'image', 'Photo 2 (optional)', 'Shown under photo 1. Same rules.' );
		echo '<p>';
		cbv_lp_featured_focus_select( 'cbv_lp_priceboard[focus2]', 'cbv_lp_priceboard_focus2', $b['focus2'], 'Photo 2 focus point' );
		echo '</p>';
	}
	?>
	<p class="description">No photos = the board runs full width. The board hides itself when the Price board section is Off or no price is left on it ("Not on the price board" everywhere).</p>
	<?php
	$fares = array_values( array_filter( array_map( 'trim', array_map( 'strval', (array) ( function_exists( 'cbv_lp_group' ) ? cbv_lp_group( $post->ID, 'columns' ) : array() ) ) ), 'strlen' ) );
	if ( $fares ) :
		?>
		<p><strong>Other fares available on request</strong> <span class="description">(optional; fares the board doesn't show that we can quote. Adds a note and a "Request a quote" button under the board, and shows those fares' cards in What's included, tagged "Available on request".)</span><br>
			<?php foreach ( $fares as $fare ) : ?>
				<label style="margin-right:16px;"><input type="checkbox" name="cbv_lp_priceboard[other][]" value="<?php echo esc_attr( $fare ); ?>" <?php checked( in_array( $fare, $b['other'], true ) ); ?>> <?php echo esc_html( $fare ); ?></label>
			<?php endforeach; ?>
		</p>
		<?php
	endif;
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_lp_priceboard_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_lp_priceboard_nonce'], 'cbv_lp_priceboard_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$raw = isset( $_POST['cbv_lp_priceboard'] ) && is_array( $_POST['cbv_lp_priceboard'] ) ? wp_unslash( $_POST['cbv_lp_priceboard'] ) : array();
	update_post_meta( $post_id, 'cbv_lp_priceboard', cbv_lp_sanitize_priceboard( $raw ) );
} );
