<?php
/*
 * Virgin Voyages provider seed (revision 4 + owner decisions of 2026-10-14). Source text:
 * docs/design/virgin-voyages-seed-text-rev4.md; plan: docs/design/virgin-provider-seed-plan.md.
 * Verified lines only; [UNVERIFIED] lines are skipped and listed. No How-to-book steps (owner).
 *
 *   wp eval-file seed_virgin_provider.php                 dry run: prints what would be written, writes nothing
 *   CBV_SEED_WRITE=1 wp eval-file seed_virgin_provider.php creates the provider (owner's OK required)
 */
$data = array(
	'title'     => 'Virgin Voyages',
	'adults'    => '1',
	'columns'   => array( 'Base', 'Essential', 'Premium', '' ),
	'footnote'  => '',
	'steps'     => array(),
	'included'  => array(
		array( 'label' => 'FARE', 'title' => 'Base', 'subline' => 'INSIDER · SEA VIEW · SEA TERRACE', 'style' => '', 'wide' => 0,
			'bullets' => "Non-refundable, no changes" ),
		array( 'label' => 'FARE', 'title' => 'Essential', 'subline' => 'INSIDER · SEA VIEW · SEA TERRACE', 'style' => '', 'wide' => 0,
			'bullets' => "Date & cabin changes; cancel for a Future Voyage Certificate" ),
		array( 'label' => 'FARE · MOST PERKS', 'title' => 'Premium', 'subline' => 'INSIDER · SEA VIEW · SEA TERRACE', 'style' => 'gold', 'wide' => 0,
			'bullets' => "Date & cabin changes; transfer your booking to someone else" ),
		array( 'label' => 'SUITES', 'title' => 'RockStar Quarters', 'subline' => 'SERIOUSLY · BRILLIANT · CHEEKY CORNER · SWEET AFT', 'style' => 'dark', 'wide' => 1,
			'bullets' => "Curated in-room bar, first round on Virgin\nMembers-only Richard's Rooftop" ),
		array( 'label' => 'SUITES', 'title' => 'Mega RockStar', 'subline' => 'GORGEOUS · POSH · FAB · MASSIVE', 'style' => 'dark', 'wide' => 1,
			'bullets' => "Everything in RockStar, plus:\nBottomless in-room bar + daily bar tab\nYour own dedicated Mega RockStar agent\nTransfers & complimentary parking\nDaily spa access" ),
	),
	'key_dates' => array(
		array( 'key' => 'final_payment', 'title' => 'Final payment due', 'description' => '{days:final_payment} days out.', 'offset' => 120, 'dot' => 'coral', 'date_text' => '', 'hidden' => 0 ),
		array( 'key' => 'date_change_deadline', 'title' => 'Last day to change dates or cabins', 'description' => '', 'offset' => 46, 'dot' => '', 'date_text' => '', 'hidden' => 0 ),
		array( 'key' => 'funds_final', 'title' => 'Payments become final', 'description' => '', 'offset' => 45, 'dot' => '', 'date_text' => '', 'hidden' => 0 ),
		array( 'key' => 'name_change_cutoff', 'title' => 'Name changes and transfers close', 'description' => '', 'offset' => 2, 'dot' => '', 'date_text' => '', 'hidden' => 0 ),
	),
	'docs'      => array(
		array( 'title' => 'Cancellation policy', 'reviewed' => '2026-10-03', 'body' => implode( "\n", array(
			'- **Base fares are non-refundable** and can\'t be changed. Taxes and fees are refunded.',
			'- The schedule below applies to Essential, Premium and RockStar fares:',
			'- **More than 120 days out:** cancel within 7 days of booking for a full refund, deposit included. After that, payments beyond the deposit are refunded; the deposit is non-refundable.',
			'- **120 to 46 days out:** no cash refund. Take a Future Voyage Certificate for what you\'ve paid, or move to a new sail date, either within 1 year of the original sail date.',
			'- **45 days or less:** no refund or credit. Essential fares can change co-travelers\' names; Premium and RockStar fares can transfer the booking. Both up to 48 hours before sailing.',
			'- Certificates are issued per sailor and can\'t be transferred. Taxes and fees are refunded to the card on file.',
			'- If you have Voyage Protection and cancel for a covered reason, it may reimburse you.',
		) ) ),
		array( 'title' => 'Payment plans & deadlines', 'reviewed' => '2026-10-02', 'body' => implode( "\n", array(
			'- **Final payment is due {date:final_payment}**, {days:final_payment} days before sailing.',
			'- Pay-later plans may be available through Virgin\'s financing partner, subject to approval.',
			'- Booking after {date:final_payment}? Full payment is due at booking.',
		) ) ),
		array( 'title' => 'ID required for boarding', 'reviewed' => '2026-10-02', 'body' => implode( "\n", array(
			'- Bring a valid, government-issued passport. Virgin only accepts passports as ID for check-in.',
			'- Complete online check-in in the Virgin app as early as you can.',
		) ) ),
		array( 'title' => 'Accessibility, upgrades & changes', 'reviewed' => '2026-10-02', 'body' => implode( "\n", array(
			'- Accessible cabins are limited. Ask your travel advisor to check availability when you book.',
			'- Name changes depend on your fare: none on Base, co-travelers on Essential, everyone on Premium and RockStar (up to 48 hours before sailing). Date changes on Essential and up are allowed until 46 days out.',
		) ) ),
	),
);

$skipped = array(
	'Card lines (19)'      => array(
		'Base: Basic Wi-Fi, 1 device', 'Base: Dining reservations open {date:dining_base}', 'Base: **Sea Terrace cabins add $50 onboard credit**',
		'Essential: Faster Wi-Fi with calling, 1 device', 'Essential: Dining reservations open {date:dining_essential}', 'Essential: **Sea Terrace cabins add $50 onboard credit**',
		'Premium: Premium Wi-Fi with streaming, 2 devices', 'Premium: $75 premium bar tab per sailor', 'Premium: Dining reservations open {date:dining_premium}', 'Premium: Priority pre-voyage support', 'Premium: **Sea Terrace cabins add $50 onboard credit**',
		'RockStar Quarters: VIP early boarding', 'RockStar Quarters: First to book: dining & experiences open {date:dining_rockstar}', 'RockStar Quarters: 24/7 RockStar Agents & room delivery', 'RockStar Quarters: $50 onboard credit', 'RockStar Quarters: Wi-Fi with calling, 1 device',
		'Mega RockStar: Work-from-sea Wi-Fi, 2 devices, plus a Classic Wi-Fi pass', 'Mega RockStar: $50 onboard credit', 'Mega RockStar: Outdoor terrace shower',
	),
	'Document bullets (7)' => array(
		'Payment plans: saved-card auto-charge + Apple Pay/PayPal', 'Payment plans: Pay in full any time before then to skip the auto-charge', 'Payment plans: Cruise protection must be added before final payment',
		'ID: passport name, birth date and nationality must match the booking', 'ID: $250 card hold',
		'Accessibility: egg-chair bullet', 'Accessibility: Level Upgrade bullet',
	),
	'Replaced (owner)'     => array( 'Accessibility: "Need an accessible cabin? ... Tell us early so we can hold the right one." -> owner\'s wording' ),
	'Key-date rows (8)'    => array( 'dining', 'shore_things', 'checkin', 'health_check', 'dining_rockstar', 'dining_premium', 'dining_essential', 'dining_base' ),
	'Key-date sentence (1)' => array( 'final_payment: "Saved cards are charged automatically."' ),
	'Not seeded'           => array( 'How-to-book steps (3; owner: use the site\'s 4)', 'Voyage Protection document (waiting on Aon)' ),
);

// Checks before anything is written.
$all  = wp_json_encode( $data );
$fail = array();
foreach ( array( 'CBGV books your cabin', 'InteleTravel', 'UNVERIFIED', 'child', 'kids' ) as $bad ) {
	if ( false !== stripos( $all, $bad ) ) { $fail[] = "contains \"$bad\""; }
}
$keys = wp_list_pluck( $data['key_dates'], 'key' );
preg_match_all( '/\{(?:date|days):([a-z0-9_]+)\}/', $all, $m );
foreach ( array_unique( $m[1] ) as $k ) {
	if ( ! in_array( $k, $keys, true ) ) { $fail[] = "token {$k} has no key date"; }
}
// What the provider screens would store (the same cleaning as a save through wp-admin).
$clean = array(
	'columns'   => cbv_lp_clean_columns( $data['columns'] ),
	'included'  => cbv_lp_clean_included( $data['included'] ),
	'steps'     => cbv_lp_clean_steps( $data['steps'] ),
	'docs'      => cbv_lp_clean_docs( $data['docs'] ),
	'key_dates' => cbv_lp_clean_key_dates( $data['key_dates'] ),
);
foreach ( array( 'included', 'docs', 'key_dates' ) as $g ) {
	if ( count( $clean[ $g ] ) !== count( $data[ $g ] ) ) { $fail[] = "$g: cleaning dropped rows"; }
}
$existing = get_posts( array( 'post_type' => 'cb_provider', 'post_status' => 'any', 'title' => 'Virgin Voyages', 'numberposts' => 1, 'fields' => 'ids' ) );
if ( $existing ) { $fail[] = 'a "Virgin Voyages" provider already exists (ID ' . $existing[0] . ')'; }

echo "VIRGIN VOYAGES PROVIDER, seed revision 4 + owner decisions 2026-10-14\n";
echo "Adults only (18+): ticked\n";
echo "Price columns: " . implode( ' | ', array_map( function ( $c ) { return '' === $c ? '(blank)' : $c; }, $clean['columns'] ) ) . "\n";
echo "Included footnote: (blank)\nHow-to-book steps: none (trips use Settings > How to Book & Disclosure)\n\n";
echo "WHAT'S INCLUDED CARDS (" . count( $clean['included'] ) . ")\n";
foreach ( $clean['included'] as $c ) {
	echo "  [{$c['label']}] {$c['title']} / {$c['subline']}  (style: " . ( $c['style'] ?: 'light' ) . ( $c['wide'] ? ', wide' : '' ) . ")\n";
	foreach ( explode( "\n", $c['bullets'] ) as $b ) { echo "     - $b\n"; }
}
echo "\nKEY DATES (" . count( $clean['key_dates'] ) . "; days before the start; for trip 181, start 2027-10-25)\n";
foreach ( $clean['key_dates'] as $d ) {
	$ymd = function_exists( 'cbv_lp_offset_date' ) ? cbv_lp_offset_date( '2027-10-25', $d['offset'] ) : '';
	echo "  {$d['key']}: \"{$d['title']}\", {$d['offset']} days -> $ymd" . ( $d['dot'] ? ", {$d['dot']} dot" : ', gold dot' ) . ( '' !== $d['description'] ? ", description \"{$d['description']}\"" : '' ) . "\n";
}
echo "\nTRAVEL DOCUMENTS (" . count( $clean['docs'] ) . ")\n";
foreach ( $clean['docs'] as $d ) {
	echo "  {$d['title']}  (last reviewed {$d['reviewed']})\n";
	foreach ( explode( "\n", $d['body'] ) as $b ) { echo "     $b\n"; }
}
echo "\nSKIP REPORT (not written)\n";
foreach ( $skipped as $group => $rows ) {
	echo "  $group\n";
	foreach ( $rows as $r ) { echo "     - $r\n"; }
}
echo "\nCHECKS: " . ( $fail ? 'FAILED: ' . implode( '; ', $fail ) : 'passed (no banned wording, no UNVERIFIED markers, no children wording, every token has a key date, cleaning keeps every row, no existing Virgin Voyages provider)' ) . "\n";

if ( ! getenv( 'CBV_SEED_WRITE' ) ) {
	echo "\nDRY RUN: nothing written.\n";
	return;
}
if ( $fail ) {
	echo "NOT WRITTEN: checks failed.\n";
	return;
}
$id = wp_insert_post( array( 'post_type' => 'cb_provider', 'post_status' => 'publish', 'post_title' => $data['title'], 'post_author' => 1 ), true );
if ( is_wp_error( $id ) ) { echo 'ERROR ' . $id->get_error_message() . "\n"; return; }
foreach ( array( 'columns', 'included', 'steps', 'docs', 'key_dates' ) as $g ) {
	update_post_meta( $id, 'cbv_lp_' . $g, $clean[ $g ] );
}
update_post_meta( $id, 'cbv_lp_included_footnote', '' );
update_post_meta( $id, 'cbv_lp_adults_only', '1' );
$back = array();
foreach ( array( 'columns', 'included', 'steps', 'docs', 'key_dates' ) as $g ) {
	$back[ $g ] = get_post_meta( $id, 'cbv_lp_' . $g, true );
}
echo "WRITTEN: provider ID $id. Readback " . ( $back === $clean && '1' === get_post_meta( $id, 'cbv_lp_adults_only', true ) ? 'matches the dry run exactly' : 'DIFFERS: check' ) . ".\n";
