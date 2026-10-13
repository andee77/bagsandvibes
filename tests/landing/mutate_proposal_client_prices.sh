#!/bin/bash
# Mutation tests for the client proposal prices: break one behaviour in a throwaway copy, run the suite, expect failures.
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
  sed "s#'/tmp/cbv_mu' === WPMU_PLUGIN_DIR#'/tmp/cbv_mut' === WPMU_PLUGIN_DIR#" /tmp/cbv_t/test_proposal_client_prices.php > /tmp/cbv_t/test_mut.php
  res=$(wp --require=/tmp/cbv_t/define_mut.php eval-file /tmp/cbv_t/test_mut.php 2>&1 | tr -d '\r')
  echo "MUTANT: $name"
  echo "$res" | grep -E '^FAIL|checks,|Fatal|Parse' | cut -c1-150 | head -4
  echo
}
D=checkedbags-proposal-pdf.php
run "client PDF asks for everything" $D "			. cb_proposal_render_pricing_html( \$trip, true );" "			. cb_proposal_render_pricing_html( \$trip );"
run "filter keeps unlisted prices" $D "			return 0 !== cbv_lp_point_column( \$point );" "			return true;"
run "empty tier kept" $D "		if ( \$kept ) {
			\$tier['occupancy_points'] = \$kept;" "		if ( true ) {
			\$tier['occupancy_points'] = \$kept;"
run "tier with no prices dropped" $D "			\$out[] = \$tier; // a tier with no prices at all prints as before
			continue;" "			continue;"
run "render ignores the client flag" $D "	if ( \$for_client && ! empty( \$trip['pricing_tiers'] ) ) {" "	if ( false && ! empty( \$trip['pricing_tiers'] ) ) {"
run "internal sheet filtered too" $D "function cb_proposal_render_pricing_html( \$trip, \$for_client = false ) {" "function cb_proposal_render_pricing_html( \$trip, \$for_client = true ) {"
rm -rf /tmp/cbv_mut /tmp/cbv_t/define_mut.php /tmp/cbv_t/test_mut.php
