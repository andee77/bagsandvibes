<?php
/*
 * Virgin Voyages provider (seeded 2026-10-14 from revision 4 + owner decisions; seed_virgin_provider.php) and
 * trip 181 on it. Read only.
 *   wp [--require=define.php] eval-file test_virgin_seed.php
 * Set CBV_VERBOSE=1 to print every passing check.
 */
$GLOBALS['n'] = 0; $GLOBALS['fail'] = 0;
function check( $l, $c, $d = '' ) {
	$GLOBALS['n']++;
	if ( ! $c ) { $GLOBALS['fail']++; echo "FAIL: $l $d\n"; }
	elseif ( getenv( 'CBV_VERBOSE' ) ) { echo '  pass ' . str_pad( $GLOBALS['n'], 3, ' ', STR_PAD_LEFT ) . ": $l\n"; }
}
$found = get_posts( array( 'post_type' => 'cb_provider', 'post_status' => 'publish', 'title' => 'Virgin Voyages', 'numberposts' => -1, 'fields' => 'ids' ) );
check( 'exactly one published "Virgin Voyages" provider', 1 === count( $found ), json_encode( $found ) );
$v = (int) ( $found[0] ?? 0 );
$g = function ( $group ) use ( $v ) { return cbv_lp_provider_group( $v, $group ); };

check( 'Adults only (18+) ticked on the provider', cbv_provider_is_adults_only( $v ) );
check( 'price columns Base | Essential | Premium | (blank)', array( 'Base', 'Essential', 'Premium', '' ) === $g( 'columns' ) );
check( 'no How-to-book steps (trips use the site\'s 4)', array() === $g( 'steps' ) );
check( 'included footnote blank', '' === (string) get_post_meta( $v, 'cbv_lp_included_footnote', true ) );

$cards = $g( 'included' );
check( '5 What\'s included cards in order', array( 'Base', 'Essential', 'Premium', 'RockStar Quarters', 'Mega RockStar' ) === wp_list_pluck( $cards, 'title' ) );
check( 'card line counts 1, 1, 1, 2, 5 (verified lines only)', array( 1, 1, 1, 2, 5 ) === array_map( function ( $c ) { return count( explode( "\n", $c['bullets'] ) ); }, $cards ) );
check( 'card styles light, light, gold, dark wide, dark wide', array( '', '', 'gold', 'dark', 'dark' ) === wp_list_pluck( $cards, 'style' ) && array( 0, 0, 0, 1, 1 ) === array_map( 'intval', wp_list_pluck( $cards, 'wide' ) ) );
check( 'Mega RockStar keeps "Transfers & complimentary parking" and "Daily spa access"', false !== strpos( $cards[4]['bullets'] ?? '', 'Transfers & complimentary parking' ) && false !== strpos( $cards[4]['bullets'] ?? '', 'Daily spa access' ) );

$dates = $g( 'key_dates' );
check( '4 key dates: final_payment 120, date_change_deadline 46, funds_final 45, name_change_cutoff 2', array( 'final_payment' => 120, 'date_change_deadline' => 46, 'funds_final' => 45, 'name_change_cutoff' => 2 ) === array_combine( wp_list_pluck( $dates, 'key' ), array_map( 'intval', wp_list_pluck( $dates, 'offset' ) ) ) );
check( 'final payment row: coral dot, "{days:final_payment} days out." only', 'coral' === ( $dates[0]['dot'] ?? '' ) && '{days:final_payment} days out.' === ( $dates[0]['description'] ?? '' ) );

$docs = $g( 'docs' );
check( '4 documents in order', array( 'Cancellation policy', 'Payment plans & deadlines', 'ID required for boarding', 'Accessibility, upgrades & changes' ) === wp_list_pluck( $docs, 'title' ) );
check( 'document bullet counts 7, 3, 2, 2', array( 7, 3, 2, 2 ) === array_map( function ( $d ) { return count( explode( "\n", $d['body'] ) ); }, $docs ) );
check( 'owner\'s accessibility wording; the old "Tell us early so we can hold" is gone', false !== strpos( $docs[3]['body'] ?? '', 'Accessible cabins are limited. Ask your travel advisor to check availability when you book.' ) && false === strpos( $docs[3]['body'] ?? '', 'hold the right one' ) );
check( 'last reviewed: Cancellation 2026-10-03, the others 2026-10-02', array( '2026-10-03', '2026-10-02', '2026-10-02', '2026-10-02' ) === wp_list_pluck( $docs, 'reviewed' ) );

$all = wp_json_encode( get_post_meta( $v ) );
check( 'no "CBGV books your cabin", no InteleTravel, no [UNVERIFIED], no children wording', false === stripos( $all, 'CBGV books your cabin' ) && false === stripos( $all, 'InteleTravel' ) && false === stripos( $all, 'UNVERIFIED' ) && false === stripos( $all, 'child' ) );

// Trip 181 on it.
check( 'trip 181 uses the Virgin Voyages provider', $v === cbv_lp_provider_id( 181 ) );
check( 'trip 181 is adults only (from the provider)', cbv_trip_is_adults_only( 181 ) );
$eff = array();
foreach ( (array) cbv_lp_effective_key_dates( 181 ) as $d ) { $eff[ $d['key'] ] = $d['ymd'] ?? ''; }
check( 'trip 181 key dates: Jun 27, Sep 9, Sep 10, Oct 23 2027', array( 'final_payment' => '2027-06-27', 'date_change_deadline' => '2027-09-09', 'funds_final' => '2027-09-10', 'name_change_cutoff' => '2027-10-23' ) === array_intersect_key( $eff, array_flip( array( 'final_payment', 'date_change_deadline', 'funds_final', 'name_change_cutoff' ) ) ), json_encode( $eff ) );
check( 'trip 181 How to book = the site\'s 4 steps', 4 === count( cbv_howto_steps( 181 ) ) );
$tp = get_post( 402 );
check( 'Test Provider (402) is in the trash, and no trip points at it', $tp && 'trash' === $tp->post_status && ! get_posts( array( 'post_type' => 'cb_trip', 'post_status' => 'any', 'meta_key' => 'cbv_lp_provider_id', 'meta_value' => '402', 'fields' => 'ids', 'numberposts' => 1 ) ) );

echo "\n{$GLOBALS['n']} checks, {$GLOBALS['fail']} failures\n";
