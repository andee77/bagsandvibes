<?php
/*
 * Proposal PDF tests (2026-10-13): prices tagged "Not on the price board" are left off the CLIENT proposal
 * entirely (a tier with nothing left goes too, add-ons and all); the Internal Data Sheet keeps everything;
 * the other cabins' totals are unchanged.
 * Run against a temp COPY of the mu-plugins folder with the changed file overlaid:
 *   wp --require=/tmp/cbv_t/define.php eval-file test_proposal_client_prices.php
 * Read only: renders the pricing HTML from arrays and from real trip 181's stored tiers; no writes.
 * Set CBV_VERBOSE=1 to print every passing check.
 */
$GLOBALS['n'] = 0; $GLOBALS['fail'] = 0;
function check( $l, $c, $d = '' ) {
	$GLOBALS['n']++;
	if ( ! $c ) { $GLOBALS['fail']++; echo "FAIL: $l $d\n"; }
	elseif ( getenv( 'CBV_VERBOSE' ) ) { echo '  pass ' . str_pad( $GLOBALS['n'], 3, ' ', STR_PAD_LEFT ) . ": $l\n"; }
}
check( 'running against the temp mu-plugins copy', '/tmp/cbv_mu' === WPMU_PLUGIN_DIR );
check( 'the client filter is loaded', function_exists( 'cb_proposal_client_tiers' ) );

function pt( $n, $fare, $col = '' ) {
	return array( 'occupancy_count' => $n, 'voyage_fare' => $fare, 'taxes_fees' => 400, 'gratuities' => 160, 'insurance' => 150, 'discount' => 0, 'pricing_basis' => 'per_cabin', 'price_column' => $col );
}
function trip_of( $tiers, $single = 0 ) {
	return array( 'pricing_tiers' => $tiers, 'single_price' => $single, 'price_columns' => array( 'Base', 'Essential', 'Premium', '' ), 'planning_fee' => null );
}
$tiers = array(
	array( 'name' => 'Inside', 'capacity_low' => 2, 'capacity_high' => 2, 'occupancy_points' => array( pt( 2, 2000, '3' ) ), 'addons' => array() ),
	array( 'name' => 'Inside Locked In', 'capacity_low' => 2, 'capacity_high' => 2, 'occupancy_points' => array( pt( 2, 1500, 'off' ) ), 'addons' => array( array( 'name' => 'Spa pass', 'qty' => 1 ) ) ),
	array( 'name' => 'Terrace', 'capacity_low' => 2, 'capacity_high' => 3, 'occupancy_points' => array( pt( 2, 3000, '3' ), pt( 3, 3600, 'off' ) ), 'addons' => array() ),
	array( 'name' => 'Lounge Only', 'capacity_low' => 1, 'capacity_high' => 1, 'occupancy_points' => array(), 'addons' => array() ),
);

/* ---- A. the client filter ---- */
$c = cb_proposal_client_tiers( $tiers );
check( 'client: the all-"Not on the price board" tier is gone; the others keep their order', array( 'Inside', 'Terrace', 'Lounge Only' ) === wp_list_pluck( $c, 'name' ) );
check( 'client: a mixed tier keeps only its listed price', 1 === count( $c[1]['occupancy_points'] ) && 2 === $c[1]['occupancy_points'][0]['occupancy_count'] );
check( 'client: a tier with no prices at all is left as it was', array() === $c[2]['occupancy_points'] );
check( 'client: kept prices are untouched', $tiers[0]['occupancy_points'][0] === $c[0]['occupancy_points'][0] && $tiers[2]['occupancy_points'][0] === $c[1]['occupancy_points'][0] );
check( 'no tags at all: nothing removed', $tiers[3] === cb_proposal_client_tiers( array( $tiers[3] ) )[0] && 1 === count( cb_proposal_client_tiers( array( array( 'name' => 'X', 'occupancy_points' => array( pt( 2, 1000 ) ) ) ) ) ) );

/* ---- B. the rendered tables ---- */
$client   = cb_proposal_render_pricing_html( trip_of( $tiers ), true );
$internal = cb_proposal_render_pricing_html( trip_of( $tiers ) );
check( 'client PDF: no Locked In tier, no its add-on, no "Not on price board", no hidden prices ($1,500 / $3,600)', false === strpos( $client, 'Locked In' ) && false === strpos( $client, 'Spa pass' ) && false === strpos( $client, 'Not on price board' ) && false === strpos( $client, '1,500.00' ) && false === strpos( $client, '3,600.00' ), $client );
check( 'client PDF: the listed cabins are there with the Premium label', false !== strpos( $client, 'Inside <span' ) && false !== strpos( $client, 'Terrace <span' ) && 2 === substr_count( $client, '<td>Premium</td>' ) );
check( 'Internal Data Sheet: everything is kept, labelled "Not on price board"', false !== strpos( $internal, 'Inside Locked In' ) && false !== strpos( $internal, 'Spa pass' ) && 2 === substr_count( $internal, '<td>Not on price board</td>' ) && false !== strpos( $internal, '1,500.00' ) && false !== strpos( $internal, '3,600.00' ) );
preg_match_all( '/<tr><td>Premium<\/td>.*?<\/tr>/s', $client, $cm );
preg_match_all( '/<tr><td>Premium<\/td>.*?<\/tr>/s', $internal, $im );
check( "the listed cabins' rows (and totals) are identical in both documents", $cm[0] === $im[0] && 2 === count( $cm[0] ) );
$none = cb_proposal_render_pricing_html( trip_of( array( $tiers[1] ), 1999 ), true );
check( 'client PDF: when every price is unlisted, it falls back to the single price', '<p><strong>Starting at $1,999.00 / person</strong></p>' === $none, $none );
check( 'client PDF: ... or to "Contact us for pricing" when there is no single price', '<p><em>Contact us for pricing.</em></p>' === cb_proposal_render_pricing_html( trip_of( array( $tiers[1] ) ), true ) );
check( 'the default (no second argument) is the Internal Data Sheet view', $internal === cb_proposal_render_pricing_html( trip_of( $tiers ), false ) );

/* ---- C. REAL trip 181 (read only) ---- */
$t181 = array( 'pricing_tiers' => cb_trip_get_pricing_tiers( 181 ), 'single_price' => (float) get_post_meta( 181, 'cb_price', true ), 'price_columns' => cbv_lp_price_columns( 181 ), 'planning_fee' => cbv_lp_planning_fee_shown( 181, 'pdf' ) );
$c181 = cb_proposal_render_pricing_html( $t181, true );
$i181 = cb_proposal_render_pricing_html( $t181 );
check( 'REAL trip 181, client PDF: the 4 Premium cabins only, no Locked In tier', 4 === substr_count( $c181, 'class="cb-tier-name"' ) && 4 === substr_count( $c181, '<td>Premium</td>' ) && false === strpos( $c181, 'Locked In' ) && false === strpos( $c181, 'Not on price board' ) );
check( 'REAL trip 181, Internal Data Sheet: all 7 tiers, the 3 Locked In labelled "Not on price board"', 7 === substr_count( $i181, 'class="cb-tier-name"' ) && 3 === substr_count( $i181, '<td>Not on price board</td>' ) && 3 === substr_count( $i181, 'Locked In' ) );
check( 'REAL trip 181: The Insider $2,552.00 per cabin in both', false !== strpos( $c181, '$2,552.00' ) && false !== strpos( $i181, '$2,552.00' ) );

/* ---- D. which document passes what (source) ---- */
$src = file_get_contents( WPMU_PLUGIN_DIR . '/checkedbags-proposal-pdf.php' );
check( 'the Client Proposal asks for the client view, the Internal Data Sheet for everything', 1 === substr_count( $src, 'cb_proposal_render_pricing_html( $trip, true )' ) && 1 === substr_count( $src, 'cb_proposal_render_pricing_html( $trip );' ) && strpos( $src, 'cb_proposal_render_pricing_html( $trip, true )' ) < strpos( $src, 'function cb_proposal_build_internal_html' ) );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
