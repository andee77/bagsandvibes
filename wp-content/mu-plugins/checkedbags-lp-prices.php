<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Landing Redesign Price Board Data
 * Description: Step 8 of the public trip landing redesign: the data behind
 *              the price board (drawn in Step 9). Reads the trip's existing
 *              Pricing Tiers (checkedbags-trips.php) plus the Step 8 tags
 *              entered there -- per tier: Group, Badge, Note, Highlight,
 *              One price only; per occupancy point: Price column -- and the
 *              price column names inherited from the trip's provider.
 *
 *              Prices are ALL-IN by default (decided 2026-10-03): cruise
 *              fare (voyage_fare less discount) + taxes & fees + prepaid
 *              gratuities + Voyage Protection (insurance), itemized. The
 *              lead price is the all-in total per cabin for 2 travelers,
 *              with the per-person price underneath. The per-person /
 *              per-cabin maths is the existing one in checkedbags-trips.php
 *              (cb_pricing_occupancy_point_total / _cabin_total).
 *
 *              Nothing here is drawn on any page yet; the current public
 *              design and Gate 07 are untouched.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-prices.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The headcount the lead price is quoted for (decided 2026-10-05: per cabin for 2). */
define( 'CBV_LP_PRICE_HEADCOUNT', 2 );

/* ==========================================================================
   1. Price columns.
   ========================================================================== */

/**
 * The four price-column names for a trip: its own, else its provider's (the
 * Provider Library rule). With none entered anywhere, column 1 is "Price".
 */
function cbv_lp_price_columns( $trip_id ) {
	$cols = function_exists( 'cbv_lp_group' ) ? (array) cbv_lp_group( (int) $trip_id, 'columns' ) : array();
	$cols = array_slice( array_pad( array_map( 'strval', array_values( $cols ) ), 4, '' ), 0, 4 );
	if ( '' === implode( '', array_map( 'trim', $cols ) ) ) {
		$cols[0] = 'Price';
	}
	return $cols;
}

/**
 * A point's stored Price column -> column number 1-4, or 0 for "Not on the
 * price board". Blank / missing / unknown = 1, so data entered before Step 8
 * shows as column 1.
 */
function cbv_lp_point_column( $point ) {
	$v = is_array( $point ) && isset( $point['price_column'] ) && is_scalar( $point['price_column'] ) ? (string) $point['price_column'] : '';
	if ( 'off' === $v ) {
		return 0;
	}
	return in_array( $v, array( '2', '3', '4' ), true ) ? (int) $v : 1;
}

/** The Price column choices for the Pricing Tiers editor (stored value => label). */
function cbv_lp_price_column_choices( $names ) {
	$names   = array_slice( array_pad( array_values( (array) $names ), 4, '' ), 0, 4 );
	$choices = array( '' => 'Column 1: ' . ( '' !== trim( (string) $names[0] ) ? $names[0] : 'Price' ) );
	foreach ( array( 2, 3, 4 ) as $n ) {
		$name = trim( (string) $names[ $n - 1 ] );
		if ( '' !== $name ) {
			$choices[ (string) $n ] = 'Column ' . $n . ': ' . $name;
		}
	}
	$choices['off'] = 'Not on the price board';
	return $choices;
}

/** The label for a point's column in the proposal PDF's "Fare" column. */
function cbv_lp_point_fare_label( $point, $names ) {
	$col = cbv_lp_point_column( $point );
	if ( 0 === $col ) {
		return 'Not on price board';
	}
	$names = array_values( (array) $names );
	$name  = trim( (string) ( $names[ $col - 1 ] ?? '' ) );
	return '' !== $name ? $name : ( 1 === $col ? 'Price' : 'Column ' . $col );
}

/* ==========================================================================
   2. One price, all-in and itemized.
   ========================================================================== */

/**
 * The all-in price of one occupancy point, for the whole cabin and per
 * person, with the four parts. Amounts entered per person are multiplied up
 * to the cabin; amounts entered per cabin are used as typed. The total is
 * the existing cabin total (never below zero).
 */
function cbv_lp_point_breakdown( $point ) {
	$point     = is_array( $point ) ? $point : array();
	$n         = max( 1, (int) ( $point['occupancy_count'] ?? 0 ) );
	$per_cabin = 'per_cabin' === ( $point['pricing_basis'] ?? 'per_person' );
	$times     = $per_cabin ? 1 : $n;
	$money     = function ( $key ) use ( $point ) {
		return (float) ( $point[ $key ] ?? 0 );
	};

	$parts = array(
		'cruise_fare' => round( ( $money( 'voyage_fare' ) - $money( 'discount' ) ) * $times, 2 ),
		'taxes_fees'  => round( $money( 'taxes_fees' ) * $times, 2 ),
		'gratuities'  => round( $money( 'gratuities' ) * $times, 2 ),
		'protection'  => round( $money( 'insurance' ) * $times, 2 ),
	);
	$total = function_exists( 'cb_pricing_occupancy_point_cabin_total' ) ? (float) cb_pricing_occupancy_point_cabin_total( $point ) : max( 0.0, array_sum( $parts ) );

	return array(
		'headcount'  => $n,
		'total'      => round( $total, 2 ),
		'per_person' => round( $total / $n, 2 ),
		'parts'      => $parts,
		'basis'      => $per_cabin ? 'per_cabin' : 'per_person',
	);
}

/* ==========================================================================
   3. The whole board.
   ========================================================================== */

/**
 * The price board for a trip, ready to draw:
 *   array(
 *     'columns'   => array( 1 => 'Essential', 2 => 'Premium' ),  // only columns some row uses
 *     'headcount' => 2,
 *     'groups'    => array(
 *       array( 'name' => 'Sea Terrace · Balcony + Hammock', 'from' => 2456.0, 'rows' => array(
 *         array( 'name' => 'The Sea Terrace', 'badge' => '★ Group favorite', 'note' => '',
 *                'highlight' => true, 'single' => false,
 *                'cells' => array( 1 => breakdown, 2 => breakdown ) ),
 *       ) ),
 *     ),
 *   )
 * Each cell is the point for that column quoted for the lead headcount (2);
 * if a column has no point for 2, its smallest headcount is used. Points
 * tagged "Not on the price board" are left out; a tier with nothing left is
 * left out; a group with no rows is left out. Tiers keep their order; a
 * tier with no Group is its own group, named after the tier.
 */
function cbv_lp_price_board_data( $trip_id ) {
	$tiers = function_exists( 'cb_trip_get_pricing_tiers' ) ? cb_trip_get_pricing_tiers( (int) $trip_id ) : array();
	$names = cbv_lp_price_columns( $trip_id );

	$groups = array();
	$used   = array();
	foreach ( (array) $tiers as $i => $tier ) {
		if ( ! is_array( $tier ) ) {
			continue;
		}
		$by_col = array();
		foreach ( (array) ( $tier['occupancy_points'] ?? array() ) as $point ) {
			if ( ! is_array( $point ) ) {
				continue;
			}
			$col = cbv_lp_point_column( $point );
			if ( $col ) {
				$by_col[ $col ][] = $point;
			}
		}
		if ( ! $by_col ) {
			continue;
		}
		$single = ! empty( $tier['single_price'] );
		$cells  = array();
		ksort( $by_col );
		foreach ( $by_col as $col => $points ) {
			$pick = null;
			foreach ( $points as $p ) {
				if ( CBV_LP_PRICE_HEADCOUNT === (int) ( $p['occupancy_count'] ?? 0 ) ) {
					$pick = $p;
					break;
				}
			}
			if ( ! $pick ) {
				usort( $points, function ( $a, $b ) {
					return (int) ( $a['occupancy_count'] ?? 0 ) <=> (int) ( $b['occupancy_count'] ?? 0 );
				} );
				$pick = $points[0];
			}
			$cells[ $col ] = cbv_lp_point_breakdown( $pick );
			if ( $single ) {
				break; // one price only: the first column that has a price
			}
		}
		if ( ! $single ) {
			foreach ( array_keys( $cells ) as $col ) {
				$used[ $col ] = true;
			}
		}

		$name  = trim( (string) ( $tier['name'] ?? '' ) );
		$group = trim( (string) ( $tier['group'] ?? '' ) );
		$key   = '' !== $group ? 'g:' . strtolower( $group ) : 't:' . $i;
		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = array( 'name' => '' !== $group ? $group : $name, 'from' => null, 'rows' => array() );
		}
		$groups[ $key ]['rows'][] = array(
			'name'      => $name,
			'badge'     => trim( (string) ( $tier['badge'] ?? '' ) ),
			'note'      => trim( (string) ( $tier['note'] ?? '' ) ),
			'highlight' => ! empty( $tier['highlight'] ),
			'single'    => $single,
			'cells'     => $cells,
		);
		$low = min( wp_list_pluck( $cells, 'total' ) );
		if ( null === $groups[ $key ]['from'] || $low < $groups[ $key ]['from'] ) {
			$groups[ $key ]['from'] = $low;
		}
	}

	ksort( $used );
	$columns = array();
	foreach ( array_keys( $used ) as $col ) {
		$columns[ $col ] = '' !== trim( (string) $names[ $col - 1 ] ) ? $names[ $col - 1 ] : ( 1 === $col ? 'Price' : 'Column ' . $col );
	}

	return array(
		'columns'   => $columns,
		'headcount' => CBV_LP_PRICE_HEADCOUNT,
		'groups'    => array_values( $groups ),
	);
}

/** "All-in for 2 travelers: cruise fare $X · taxes & fees $X · prepaid gratuities $X · Voyage Protection $X" (plain text). */
function cbv_lp_price_breakdown_line( $cell, $people_word = 'travelers' ) {
	$money = function ( $v ) {
		return '$' . number_format_i18n( (float) $v, floor( (float) $v ) == (float) $v ? 0 : 2 ); // phpcs:ignore Universal.Operators.StrictComparisons -- comparing a float to its own floor
	};
	$p = $cell['parts'];
	return sprintf(
		'All-in for %1$d %2$s: cruise fare %3$s · taxes & fees %4$s · prepaid gratuities %5$s · Voyage Protection %6$s',
		(int) $cell['headcount'],
		strtolower( (string) $people_word ),
		$money( $p['cruise_fare'] ),
		$money( $p['taxes_fees'] ),
		$money( $p['gratuities'] ),
		$money( $p['protection'] )
	);
}
