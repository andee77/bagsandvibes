#!/bin/bash
# Mutation tests for Step 8b: break one behaviour in a throwaway copy, run the suite, expect failures.
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
  printf '<?php\ndefine( "WPMU_PLUGIN_DIR", "/tmp/cbv_mut" );\ndefine( "WP_DISABLE_FATAL_ERROR_HANDLER", true );\nini_set( "error_log", "/tmp/cbv_t/test_errors.log" );\n' > /tmp/cbv_t/define_mut.php
  sed "s#'/tmp/cbv_mu' === WPMU_PLUGIN_DIR#'/tmp/cbv_mut' === WPMU_PLUGIN_DIR#" /tmp/cbv_t/test_step8b.php > /tmp/cbv_t/test_mut.php
  res=$(wp --require=/tmp/cbv_t/define_mut.php eval-file /tmp/cbv_t/test_mut.php 2>&1 | tr -d '\r')
  echo "MUTANT: $name"
  echo "$res" | grep -E '^FAIL|checks,|Fatal|Parse' | cut -c1-150 | head -4
  echo
}
F=checkedbags-lp-fees.php
P=checkedbags-lp-prices.php
D=checkedbags-proposal-pdf.php
run "per traveler fee not multiplied by headcount" $F "return round( \$amount * max( 1, (int) \$headcount ), 2 );" "return round( \$amount, 2 );"
run "every basis treated as per traveler" $F "	\$amount = (float) \$fee['amount'];
	if ( 'per_traveler' === ( \$fee['basis'] ?? 'per_traveler' ) ) {" "	\$amount = (float) \$fee['amount'];
	if ( true ) {"
run "party: every basis treated as per traveler" $F "	\$children = max( 0, (int) \$children );
	if ( 'per_traveler' === ( \$fee['basis'] ?? 'per_traveler' ) ) {" "	\$children = max( 0, (int) \$children );
	if ( true ) {"
run "negative amounts allowed" $F "round( min( 100000, max( 0, (float) \$value ) ), 2 )" "round( min( 100000, (float) \$value ), 2 )"
run "no cap on amounts" $F "round( min( 100000, max( 0, (float) \$value ) ), 2 )" "round( max( 0, (float) \$value ), 2 )"
run "unknown basis kept" $F "isset( cbv_lp_fee_bases()[ (string) \$value ] ) ? (string) \$value : 'per_traveler'" "(string) \$value"
run "trip box keeps an amount when not custom" $F "'amount' => 'custom' === \$mode ? cbv_lp_clean_fee_amount( \$raw['amount'] ?? '' ) : 0.0," "'amount' => cbv_lp_clean_fee_amount( \$raw['amount'] ?? '' ),"
run "waiver ignored" $F "	if ( 'none' === \$trip['mode'] ) {
		return array( 'amount' => 0.0" "	if ( false ) {
		return array( 'amount' => 0.0"
run "different fee for a trip ignored" $F "	if ( 'custom' === \$trip['mode'] ) {
		return array(" "	if ( false ) {
		return array("
run "event type ignored (always the cruise row)" $F "\$all[ \$type ] ) && is_array( \$all[ \$type ] ) ? \$all[ \$type ] : array();" "\$all['cruise'] ) && is_array( \$all['cruise'] ) ? \$all['cruise'] : array();"
run "live switch ignored (always live)" $F "	if ( cbv_lp_planning_fee_is_live() ) {
		return \$fee;" "	if ( true ) {
		return \$fee;"
run "preview fee leaks into the PDF" $F "if ( 'page' === \$where && function_exists( 'cbv_lp_preview_requested' )" "if ( function_exists( 'cbv_lp_preview_requested' )"
run "preview without admin check" $F "cbv_lp_preview_requested() && current_user_can( 'manage_options' ) ) {" "cbv_lp_preview_requested() ) {"
run "preview not required" $F "function_exists( 'cbv_lp_preview_requested' ) && cbv_lp_preview_requested() && current_user_can" "current_user_can"
run "settings page heading keeps the old name" $F "<h1>Group Experience Fees</h1>" "<h1>Group planning fees</h1>"
run "note: old wording" $F "for the group program; travel payments go directly to the cruise line or supplier." "for group coordination; cruise payments go directly to the cruise line."
run "note: per-booking line missing" $F "	if ( 'per_booking' === \$fee['basis'] ) {
		\$lines[]" "	if ( false ) {
		\$lines[]"
run "note: date not filled in" $F "\$until  = '' !== \$date ? \$date : \"the trip's final payment date\";" "\$until  = \"the trip's final payment date\";"
run "note shown when no fee" $F "	if ( ! \$fee ) {
		return array();
	}
	\$labels" "	if ( false ) {
		return array();
	}
	\$fee = \$fee ?: array( 'basis' => 'per_traveler' );
	\$labels"
run "save without admin check" $F "if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', \$post_id ) ) {" "if ( ! current_user_can( 'edit_post', \$post_id ) ) {"
run "save without nonce check" $F "if ( ! isset( \$_POST['cbv_lp_fee_nonce'] ) || ! wp_verify_nonce( \$_POST['cbv_lp_fee_nonce'], 'cbv_lp_fee_save' ) ) {" "if ( ! isset( \$_POST['cbv_lp_fee_nonce'] ) ) {"
run "save stores raw input" $F "update_post_meta( \$post_id, 'cbv_lp_planning_fee', cbv_lp_sanitize_trip_fee( \$raw ) );" "update_post_meta( \$post_id, 'cbv_lp_planning_fee', \$raw );"
run "settings page open to non-admins" $F "function cbv_lp_render_fees_page() {
	if ( ! current_user_can( 'manage_options' ) ) {" "function cbv_lp_render_fees_page() {
	if ( false ) {"
run "breakdown: fee not added to the total" $P "		\$total                += \$plan;" "		\$total                += 0;"
run "breakdown: fee folded into the cruise fare" $P "		\$parts['planning_fee'] = \$plan;" "		\$parts['cruise_fare'] += \$plan;"
run "board: fee not passed to the cells" $P "\$cells[ \$col ] = cbv_lp_point_breakdown( \$pick, \$fee );" "\$cells[ \$col ] = cbv_lp_point_breakdown( \$pick );"
run "breakdown line: fee item missing" $P "		\$line .= ' · CBGV Group Experience Fee ' . \$money( \$p['planning_fee'] );" "		\$line .= '';"
run "PDF: fee not in the cabin total" $D "						\$cabin_total      += \$fee_cabin;" "						\$cabin_total      += 0;"
run "PDF: fee not in the per-person total" $D "						\$per_person_total += \$fee_cabin / \$headcount;" "						\$per_person_total += 0;"
run "PDF: fee column always shown" $D "\$show_fee = is_array( \$fee ) && ! empty( \$fee['amount'] ) && function_exists( 'cbv_lp_fee_for_cabin' );" "\$show_fee = function_exists( 'cbv_lp_fee_for_cabin' );"
run "PDF: uses the page (preview) check" $D "cbv_lp_planning_fee_shown( \$trip_id, 'pdf' )" "cbv_lp_planning_fee_shown( \$trip_id, 'page' )"
run "board note: children line missing" $F "		\$lines[] = 'Children pay half the CBGV Group Experience Fee.';" "		// missing"
run "board note: children line on every basis" $F "	if ( 'per_traveler' === \$fee['basis'] ) {
		\$lines[] = 'Children" "	if ( true ) {
		\$lines[] = 'Children"
run "PDF: children note missing" $D "			\$html .= '<p class=\"cb-fee-note\">Children pay half the CBGV Group Experience Fee.</p>';" "			// missing"
run "intake: age not shown" checkedbags-trip-invites.php "esc_html( ' (under ' . cbv_lp_child_age_under() . ')' )" "''"
run "child age not limited to 1-21" $F "	return \$age >= 1 && \$age <= 21 ? \$age : 0;" "	return \$age;"
rm -rf /tmp/cbv_mut /tmp/cbv_t/define_mut.php /tmp/cbv_t/test_mut.php
