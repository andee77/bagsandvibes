#!/bin/bash
# Mutation tests for Step 8: break one behaviour in a throwaway copy, run the suite, expect failures.
cd ~/www/bagsandvibes.com/public_html || exit 1
run() {
  name="$1"; file="$2"; old="$3"; new="$4"
  rm -rf /tmp/cbv_mut && cp -r /tmp/cbv_mu /tmp/cbv_mut
  FILE="$file" OLD="$old" NEW="$new" python3 - <<'PY'
import os
p = "/tmp/cbv_mut/" + os.environ["FILE"]
s = open(p, "rb").read().decode("utf-8")
crlf = "\r\n" in s
if crlf:
    s = s.replace("\r\n", "\n")
old, new = os.environ["OLD"], os.environ["NEW"]
assert s.count(old) == 1, ("anchor not found or ambiguous", old, s.count(old))
s = s.replace(old, new)
if crlf:
    s = s.replace("\n", "\r\n")
open(p, "wb").write(s.encode("utf-8"))
PY
  [ $? -ne 0 ] && { echo "MUTANT $name: COULD NOT APPLY"; return; }
  printf '<?php\ndefine( "WPMU_PLUGIN_DIR", "/tmp/cbv_mut" );\ndefine( "WP_DISABLE_FATAL_ERROR_HANDLER", true );\n' > /tmp/cbv_t/define_mut.php
  sed "s#'/tmp/cbv_mu' === WPMU_PLUGIN_DIR#'/tmp/cbv_mut' === WPMU_PLUGIN_DIR#" /tmp/cbv_t/test_step8.php > /tmp/cbv_t/test_mut.php
  res=$(wp --require=/tmp/cbv_t/define_mut.php eval-file /tmp/cbv_t/test_mut.php 2>&1 | tr -d '\r')
  echo "MUTANT: $name"
  echo "$res" | grep -E '^FAIL|checks,|Fatal|Parse' | cut -c1-150 | head -4
  echo
}
P=checkedbags-lp-prices.php
T=checkedbags-trips.php
run "off ignored (Lock-It-In shows on the board)" $P "	if ( 'off' === \$v ) {
		return 0;
	}" "	if ( false ) {
		return 0;
	}"
run "unknown column treated as off" $P "return in_array( \$v, array( '2', '3', '4' ), true ) ? (int) \$v : 1;" "return in_array( \$v, array( '2', '3', '4' ), true ) ? (int) \$v : 0;"
run "discount not taken off the cruise fare" $P "'cruise_fare' => round( ( \$money( 'voyage_fare' ) - \$money( 'discount' ) ) * \$times, 2 )," "'cruise_fare' => round( \$money( 'voyage_fare' ) * \$times, 2 ),"
run "per-person parts not multiplied up to the cabin" $P "	\$times     = \$per_cabin ? 1 : \$n;" "	\$times     = 1;"
run "per person not divided by headcount" $P "'per_person' => round( \$total / \$n, 2 )," "'per_person' => round( \$total, 2 ),"
run "lead headcount ignored (first point used)" $P "				if ( CBV_LP_PRICE_HEADCOUNT === (int) ( \$p['occupancy_count'] ?? 0 ) ) {" "				if ( true ) {"
run "fallback picks the largest headcount" $P "return (int) ( \$a['occupancy_count'] ?? 0 ) <=> (int) ( \$b['occupancy_count'] ?? 0 );" "return (int) ( \$b['occupancy_count'] ?? 0 ) <=> (int) ( \$a['occupancy_count'] ?? 0 );"
run "groups not joined by name" $P "		\$key   = '' !== \$group ? 'g:' . strtolower( \$group ) : 't:' . \$i;" "		\$key   = 't:' . \$i;"
run "group match is case-sensitive" $P "'g:' . strtolower( \$group )" "'g:' . \$group"
run "one price only shows every column" $P "			if ( \$single ) {
				break; // one price only: the first column that has a price
			}" "			if ( false ) {
				break;
			}"
run "suite rows add columns" $P "		if ( ! \$single ) {
			foreach ( array_keys( \$cells ) as \$col ) {" "		if ( true ) {
			foreach ( array_keys( \$cells ) as \$col ) {"
run "'from' takes the highest price" $P "		if ( null === \$groups[ \$key ]['from'] || \$low < \$groups[ \$key ]['from'] ) {" "		if ( null === \$groups[ \$key ]['from'] || \$low > \$groups[ \$key ]['from'] ) {"
run "no 'Price' fallback name" $P "		\$cols[0] = 'Price';" "		\$cols[0] = '';"
run "save drops the price column" $T "				'price_column'    => in_array( \$price_column, array( '', '2', '3', '4', 'off' ), true ) ? \$price_column : ''," "				'price_column'    => '',"
run "save accepts any price column" $T "in_array( \$price_column, array( '', '2', '3', '4', 'off' ), true ) ? \$price_column : ''" "\$price_column"
run "save drops the tier group" $T "			'group'            => mb_substr( sanitize_text_field( wp_unslash( \$tier_row['group'] ?? '' ) ), 0, 60 )," "			'group'            => '',"
run "badge not capped" $T "mb_substr( sanitize_text_field( wp_unslash( \$tier_row['badge'] ?? '' ) ), 0, 40 )" "sanitize_text_field( wp_unslash( \$tier_row['badge'] ?? '' ) )"
run "editor select default not blank (new rows never blank)" $P "	\$choices = array( '' => 'Column 1: '" "	\$choices = array( '1' => 'Column 1: '"
run "PDF Fare column missing when tagged" checkedbags-proposal-pdf.php "( \$show_fare ? '<th>Fare</th>' : '' )" "''"
run "PDF Fare column shown even when untagged" checkedbags-proposal-pdf.php "		\$show_fare = function_exists( 'cbv_lp_trip_has_price_tags' ) && cbv_lp_trip_has_price_tags( \$trip['pricing_tiers'] );" "		\$show_fare = true;"
run "tag helper counts the untouched default as a tag" $P "			if ( in_array( \$v, array( '2', '3', '4', 'off' ), true ) ) {
				return true;" "			if ( in_array( \$v, array( '', '2', '3', '4', 'off' ), true ) ) {
				return true;"
run "tag helper ignores off" $P "			if ( in_array( \$v, array( '2', '3', '4', 'off' ), true ) ) {
				return true;" "			if ( in_array( \$v, array( '2', '3', '4' ), true ) ) {
				return true;"
run "save no longer uses the new function" $T "update_post_meta( \$post_id, 'cb_pricing_tiers', cb_sanitize_pricing_tiers( \$_POST['cb_pricing_tiers'] ?? array() ) );" "update_post_meta( \$post_id, 'cb_pricing_tiers', \$_POST['cb_pricing_tiers'] ?? array() );"
rm -rf /tmp/cbv_mut /tmp/cbv_t/define_mut.php /tmp/cbv_t/test_mut.php
