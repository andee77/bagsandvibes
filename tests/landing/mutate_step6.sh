#!/bin/bash
# Mutation tests for Step 6: break one behaviour in a throwaway copy, run the suite, expect failures.
cd ~/www/bagsandvibes.com/public_html || exit 1
run() {
  name="$1"; file="$2"; old="$3"; new="$4"
  rm -rf /tmp/cbv_mut && cp -r /tmp/cbv_mu /tmp/cbv_mut
  FILE="$file" OLD="$old" NEW="$new" python3 - <<'PY'
import os
p = "/tmp/cbv_mut/" + os.environ["FILE"]
s = open(p, "rb").read().decode("utf-8")
old, new = os.environ["OLD"], os.environ["NEW"]
assert s.count(old) == 1, ("anchor not found or ambiguous", old, s.count(old))
open(p, "wb").write(s.replace(old, new).encode("utf-8"))
PY
  [ $? -ne 0 ] && { echo "MUTANT $name: COULD NOT APPLY"; return; }
  printf '<?php\ndefine( "WPMU_PLUGIN_DIR", "/tmp/cbv_mut" );\ndefine( "WP_DISABLE_FATAL_ERROR_HANDLER", true );\nini_set( "error_log", "/tmp/cbv_t/test_errors.log" );\n' > /tmp/cbv_t/define_mut.php
  sed "s#'/tmp/cbv_mu' === WPMU_PLUGIN_DIR#'/tmp/cbv_mut' === WPMU_PLUGIN_DIR#" /tmp/cbv_t/test_step6.php > /tmp/cbv_t/test_mut.php
  res=$(wp --require=/tmp/cbv_t/define_mut.php eval-file /tmp/cbv_t/test_mut.php 2>&1 | tr -d '\r')
  echo "MUTANT: $name"
  echo "$res" | grep -E '^FAIL|checks,|Fatal|Parse' | cut -c1-150 | head -4
  echo
}
F=checkedbags-lp-route.php
run "day numbers from the typed Day box instead of the start date" $F "\$n    = (int) round( ( \$ts - \$start_ts ) / DAY_IN_SECONDS ) + 1;" "\$n    = isset( \$row['day'] ) && is_numeric( \$row['day'] ) ? (int) \$row['day'] : 0;"
run "day numbers off by one (start date is day 0)" $F "\$n    = (int) round( ( \$ts - \$start_ts ) / DAY_IN_SECONDS ) + 1;" "\$n    = (int) round( ( \$ts - \$start_ts ) / DAY_IN_SECONDS );"
run "Sail-away wording lost (Boarding from)" $F "( \$cruise ? 'Sail-away ' : 'Departs ' )" "( \$cruise ? 'Boarding from ' : 'Departs ' )"
run "tender wording back to Tendered" $F "return 'Tender port' . ( '' !== \$span ? ' · ' . \$span : '' );" "return 'Tendered ' . \$span;"
run "tender ignored when only one row says Tender" $F "				\$tender = 'tender';
				break;" "				\$tender = 'tender';"
run "12-hour clock broken (17:00 -> 17:00 pm)" $F "return ( 0 === \$h % 12 ? 12 : \$h % 12 ) . ':'" "return \$h . ':'"
run "overnight never detected" $F "\$overnight = in_array( \$port, \$next_ports, true ) && ! in_array( 'arrival', \$next_types, true );" "\$overnight = false;"
run "disembarkation port missing" $F "return 'Back in ' . \$port . ( '' !== \$t ? ' at ' . \$t : '' );" "return 'Back' . ( '' !== \$t ? ' at ' . \$t : '' );"
run "chain shown with a missing code" $F "			if ( ! isset( \$codes[ \$key ] ) ) {
				return null;
			}" "			if ( ! isset( \$codes[ \$key ] ) ) {
				continue;
			}"
run "chain codes not shared between rows of a port" $F "if ( '' !== \$key && '' !== \$code && ! isset( \$codes[ \$key ] ) ) {" "if ( false ) {"
run "Show Itinerary toggle ignored" $F "if ( ! get_post_meta( \$trip_id, 'cb_public_landing_show_itinerary', true ) ) {
		return null;
	}" "if ( false ) {
		return null;
	}"
run "hide time line ignored" $F "'time'          => \$x['hide_time'] ? '' : \$auto," "'time'          => \$auto,"
run "route heading not escaped" $F "<?php echo esc_html( \$d['heading'] ); ?></h2>" "<?php echo \$d['heading']; ?></h2>"
run "legacy itinerary table not removed" $F "if ( '' === trim( (string) \$html ) || ! class_exists( 'DOMDocument' ) ) {
		return \$html;
	}
	\$prev" "if ( true ) {
		return \$html;
	}
	\$prev"
run "merge drops stored days that were not posted" $F "	foreach ( (array) \$stored as \$n => \$day ) {" "	foreach ( array() as \$n => \$day ) {"
run "photo kind not checked" $F "'photo'         => function_exists( 'cbv_lp_clean_attachment_id' ) ? cbv_lp_clean_attachment_id( \$raw['photo'] ?? 0, 'image' ) : 0," "'photo'         => absint( \$raw['photo'] ?? 0 ),"
run "save handler skips the capability check" $F "if ( ! current_user_can( 'edit_post', \$post_id ) ) {
		return;
	}

	\$heading" "if ( false ) {
		return;
	}

	\$heading"
run "route rendered before the intro" checkedbags-lp-core.php "	if ( function_exists( 'cbv_lp_render_status' ) ) {" "	if ( function_exists( 'cbv_lp_render_route' ) ) { echo cbv_lp_render_route( \$trip_id ); }
	if ( function_exists( 'cbv_lp_render_status' ) ) {"
run "resort default heading wrong" $F "'resort'      => 'The Plan'," "'resort'      => 'The Route',"
rm -rf /tmp/cbv_mut /tmp/cbv_t/define_mut.php /tmp/cbv_t/test_mut.php
