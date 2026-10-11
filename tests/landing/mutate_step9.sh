#!/bin/bash
# Mutation tests for Step 9: break one behaviour in a throwaway copy, run the suite, expect failures.
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
  sed "s#'/tmp/cbv_mu' === WPMU_PLUGIN_DIR#'/tmp/cbv_mut' === WPMU_PLUGIN_DIR#" /tmp/cbv_t/test_step9.php > /tmp/cbv_t/test_mut.php
  res=$(wp --require=/tmp/cbv_t/define_mut.php eval-file /tmp/cbv_t/test_mut.php 2>&1 | tr -d '\r')
  echo "MUTANT: $name"
  echo "$res" | grep -E '^FAIL|checks,|Fatal|Parse' | cut -c1-150 | head -4
  echo
}
P=checkedbags-lp-priceboard.php
F=checkedbags-lp-fees.php
H=checkedbags-lp-hero.php
C=checkedbags-lp-core.php
run "open group: always the first" $P "				return \$i;" "				return 0;"
run "groups not exclusive (no details name)" $P "name=\"cbv-lp-fare-groups\"" ""
run "every group starts open" $P "<?php echo \$gi === \$d['open'] ? ' open' : ''; ?>" "<?php echo ' open'; ?>"
run "header note: fee clause missing" $P "		\$list .= ', plus the CBGV Group Experience Fee';" "		\$list .= '';"
run "header note: parts with no money still named" $P "		if ( cbv_lp_board_part_use( \$groups, \$key )['any'] ) {" "		if ( true ) {"
run "header note: always 'the cruise line'" $P "( \$is_cruise ? 'the cruise line.' : 'the supplier.' )" "'the cruise line.'"
run "own header note ignored" $P "	if ( '' !== \$own ) {" "	if ( false ) {"
run "own header note not escaped" $P "cbv_lp_inline( \$own ) : esc_html( \$own );" "\$own : \$own;"
run "footer: tips line without checking the prices" $P "	if ( \$is_cruise && cbv_lp_board_part_use( \$board['groups'], 'gratuities' )['all'] && cbv_lp_board_part_use( \$board['groups'], 'protection' )['all'] ) {" "	if ( true ) {"
run "footer: final payment date missing" $P "		\$foot[] = 'Final payment due · ' . \$final;" "		// none"
run "section Off ignored" $P "	if ( function_exists( 'cbv_lp_section_enabled' ) && ! cbv_lp_section_enabled( \$trip_id, 'prices' ) ) {" "	if ( false ) {"
run "empty board not hidden" $P "	if ( empty( \$board['groups'] ) || ( empty( \$board['columns'] ) && ! cbv_lp_board_has_single( \$board['groups'] ) ) ) {" "	if ( false ) {"
run "suite-only board hidden" $P "( empty( \$board['columns'] ) && ! cbv_lp_board_has_single( \$board['groups'] ) )" "empty( \$board['columns'] )"
run "per-person price missing" $P "	\$sub = cbv_lp_board_money( \$cell['per_person'] ) . ' per person';" "	\$sub = '';"
run "headcount other than 2 not said" $P "		\$sub .= ' · for ' . (int) \$cell['headcount'] . ' ' . \$d['people'];" "		\$sub .= '';"
run "cabin name not escaped" $P "<span class=\"cbv-lp-board-cabin\"><?php echo esc_html( \$row['name'] ); ?>" "<span class=\"cbv-lp-board-cabin\"><?php echo \$row['name']; ?>"
run "'Not offered' missing for screen readers" $P "<span class=\"cbv-lp-sr\">Not offered</span>" ""
run "suite price does not span the columns" $P "colspan=\"<?php echo (int) \$ncols; ?>\" data-col" "colspan=\"1\" data-col"
run "breakdown always repeats the column name" $P "<?php if ( ! \$row['single'] && \$ncols > 1 ) : ?>" "<?php if ( true ) : ?>"
run "highlight class missing" $P "\$row['highlight'] ? 'cbv-lp-board-row cbv-lp-board-row--highlight' : 'cbv-lp-board-row'" "'cbv-lp-board-row'"
run "row header missing" $P "									<th scope=\"row\">" "									<td>"
run "fee note not drawn" $P "			<?php if ( \$d['fee_note'] ) : ?>" "			<?php if ( false ) : ?>"
run "fee note not escaped" $P "<p><?php echo esc_html( \$line ); ?></p>" "<p><?php echo \$line; ?></p>"
run "legacy pricing not stripped" $C "		\$legacy = cbv_lp_strip_legacy_pricing( \$legacy );" "		// kept"
run "strip looks for the wrong block" $P "\" cbv-landing-pricing \"" "\" cbv-landing-pricing-x \""
run "Claim your seat: always registration" $H "function_exists( 'cbv_lp_price_board_visible' ) && cbv_lp_price_board_visible( \$trip_id ) ? '#fares'" "false ? '#fares'"
run "Claim your seat: always #fares" $H "&& cbv_lp_price_board_visible( \$trip_id ) ? '#fares'" "|| true ? '#fares'"
run "coin stamp read by screen readers" $H "<span class=\"cbv-lp-pass-stamp\" aria-hidden=\"true\"></span>" "<span class=\"cbv-lp-pass-stamp\"></span>"
run "trip's own fee not shown before live" $F "	if ( 'page' === \$where && 'trip' === \$fee['source'] ) {" "	if ( false ) {"
run "trip's own fee leaks into the PDF" $F "	if ( 'page' === \$where && 'trip' === \$fee['source'] ) {" "	if ( 'trip' === \$fee['source'] ) {"
run "standard fee shown before live" $F "	if ( 'page' === \$where && 'trip' === \$fee['source'] ) {" "	if ( 'page' === \$where ) {"
run "save without nonce check" $P "! wp_verify_nonce( \$_POST['cbv_lp_priceboard_nonce'], 'cbv_lp_priceboard_save' )" "false"
run "save without permission check" $P "	if ( ! current_user_can( 'edit_post', \$post_id ) ) {
		return;
	}
	\$raw = isset( \$_POST['cbv_lp_priceboard'] )" "	if ( false ) {
		return;
	}
	\$raw = isset( \$_POST['cbv_lp_priceboard'] )"
run "save stores raw input" $P "update_post_meta( \$post_id, 'cbv_lp_priceboard', cbv_lp_sanitize_priceboard( \$raw ) );" "update_post_meta( \$post_id, 'cbv_lp_priceboard', \$raw );"
run "photo not checked to be an image" $P "return function_exists( 'cbv_lp_clean_attachment_id' ) ? cbv_lp_clean_attachment_id( \$value, 'image' ) : 0;" "return (int) \$value;"
run "note not capped" $P "mb_substr( \$note, 0, 600 )" "mb_substr( \$note, 0, 6000 )"
rm -rf /tmp/cbv_mut /tmp/cbv_t/define_mut.php /tmp/cbv_t/test_mut.php
