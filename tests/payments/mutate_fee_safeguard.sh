#!/bin/bash
# Mutation tests for the payment safeguard: break one behaviour in a throwaway copy, run the suite, expect failures.
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
  sed "s#'/tmp/cbv_mu' === WPMU_PLUGIN_DIR#'/tmp/cbv_mut' === WPMU_PLUGIN_DIR#" /tmp/cbv_t/test_fee_safeguard.php > /tmp/cbv_t/test_mut.php
  res=$(wp --require=/tmp/cbv_t/define_mut.php eval-file /tmp/cbv_t/test_mut.php 2>&1 | tr -d '\r')
  echo "MUTANT: $name"
  echo "$res" | grep -E '^FAIL|checks,|Fatal|Parse' | cut -c1-150 | head -4
  echo
}
G=checkedbags-gate09.php
run "fee total adds the Travel price (the old behaviour)" $G "	return round( \$part + cb_trip_approved_extras( \$trip_id ), 2 );" "	return round( \$part + cb_trip_approved_extras( \$trip_id ) + (float) get_post_meta( \$trip_id, 'cb_price', true ), 2 );"
run "ceiling adds the Travel price" $G "	return round( \$fee_part + cb_trip_approved_extras( \$trip_id ), 2 );" "	return round( \$fee_part + cb_trip_approved_extras( \$trip_id ) + (float) get_post_meta( \$trip_id, 'cb_price', true ), 2 );"
run "Stripe amount adds the Travel price" $G "'unit_amount'  => (int) round( \$amount * 100 )," "'unit_amount'  => (int) round( ( \$amount + (float) get_post_meta( \$trip_id, 'cb_price', true ) ) * 100 ),"
run "Stripe amount uses the quoted travel price" $G "'unit_amount'  => (int) round( \$amount * 100 )," "'unit_amount'  => (int) round( max( \$amount, (float) get_post_meta( \$trip_id, 'cb_quoted_price', true ) ) * 100 ),"
run "ceiling check removed" $G "	if ( \$amount > \$room + 0.005 ) {" "	if ( false ) {"
run "refusal not logged" $G "		cb_log_payment_guard( 'checkout refused', \$trip_id, \$user_id, \$amount, max( 0, \$room ) );" "		// not logged"
run "ceiling ignores what is already paid" $G "	\$room = cb_trip_charge_ceiling( \$trip_id, \$user_id ) - cb_trip_amount_paid( \$trip_id, \$user_id );" "	\$room = cb_trip_charge_ceiling( \$trip_id, \$user_id );"
run "webhook over-limit not logged" $G "				cb_log_payment_guard( 'webhook over limit', \$trip_id, \$user_id, \$amount, \$ceiling );" "				// not logged"
run "additional adults ignored" $G "		'adults'   => 1 + min( 20, max( 0, (int) ( \$intake['additional_adults'] ?? 0 ) ) )," "		'adults'   => 1,"
run "children not counted" $G "		'children' => min( 20, max( 0, (int) ( \$intake['additional_children'] ?? 0 ) ) )," "		'children' => 0,"
run "children charged the full fee" checkedbags-lp-fees.php "	return is_array( \$fee ) && ! empty( \$fee['amount'] ) ? round( max( 0, (float) \$fee['amount'] ) / 2, 2 ) : 0.0;" "	return is_array( \$fee ) && ! empty( \$fee['amount'] ) ? round( max( 0, (float) \$fee['amount'] ), 2 ) : 0.0;"
run "ceiling charges children the full fee" $G "\$amount * \$party['adults'] + round( \$amount / 2, 2 ) * \$party['children']" "\$amount * ( \$party['adults'] + \$party['children'] )"
run "per-cabin fee multiplied by the party" checkedbags-lp-fees.php "	return \$adults + \$children > 0 ? round( (float) \$fee['amount'], 2 ) : 0.0;" "	return round( (float) \$fee['amount'] * ( \$adults + \$children ), 2 );"
run "standard fee billed before it is live" $G "	if ( 'trip' === \$fee['source'] || ( function_exists( 'cbv_lp_planning_fee_is_live' ) && cbv_lp_planning_fee_is_live() ) ) {" "	if ( true ) {"
run "trip's own fee not billed" $G "	if ( 'trip' === \$fee['source'] || ( function_exists" "	if ( false || ( function_exists"
run "approved extras not added" $G "	return round( \$part + cb_trip_approved_extras( \$trip_id ), 2 );" "	return round( \$part, 2 );"
run "negative extras not cleared" $G "	return max( 0.0, round( (float) get_post_meta( \$trip_id, 'cb_extras_cost', true ), 2 ) );" "	return round( (float) get_post_meta( \$trip_id, 'cb_extras_cost', true ), 2 );"
run "deposit setting not capped at the fee" $G "		return min( \$deposit > 0 ? \$deposit : \$balance, \$balance );" "		return \$deposit > 0 ? \$deposit : \$balance;"
run "Stripe label is the bare trip name again" $G "	\$label = 'CBGV Group Experience Fee: ' . html_entity_decode( get_the_title( \$trip_id ), ENT_QUOTES, 'UTF-8' );" "	\$label = get_the_title( \$trip_id );"
run "Stripe label keeps HTML entities" $G "html_entity_decode( get_the_title( \$trip_id ), ENT_QUOTES, 'UTF-8' )" "get_the_title( \$trip_id )"
run "no charge description on the payment" $G "			'payment_intent_data'                 => array(
				'description' => \$label,
			)," ""
run "Payment page shows the Travel price again" $G "<p class=\"cbv-payment-detail-line\"><strong>Total owed to CBGV:" "<p>Price per person: \$<?php echo esc_html( number_format( (float) get_post_meta( \$trip->ID, 'cb_price', true ), 2 ) ); ?></p><p class=\"cbv-payment-detail-line\"><strong>Total owed to CBGV:"
run "Payment card keeps the old fee name" $G "<span class=\"cbv-payment-card-label\">CBGV Group Experience Fee</span>" "<span class=\"cbv-payment-card-label\">CBGV Commitment Fee</span>"
run "Gate 12 acceptance also sets the extras to the quoted price" checkedbags-gate12.php "		update_post_meta( \$trip_id, 'cb_price', \$quoted_price );" "		update_post_meta( \$trip_id, 'cb_price', \$quoted_price ); update_post_meta( \$trip_id, 'cb_extras_cost', \$quoted_price );"
run "Gate 12 confirmation says pay your deposit on Gate 09 again" checkedbags-gate12.php "Accepted! Your independent travel advisor will be in touch to book it; the travel price is paid directly to the cruise line or supplier, never to CBGV. Any CBGV Group Experience Fee is on" "Accepted! Head to Gate 09 to pay your deposit. Any CBGV Group Experience Fee is on"
run "admin label back to Price per person" checkedbags-trips.php "<label for=\"cb_price\">Travel price (reference only, never charged here) (\$)</label>" "<label for=\"cb_price\">Price per person (\$)</label>"
run "roster export: Paid in Full judged on the travel price" checkedbags-roster-export.php "( \$fee_total > 0 && \$balance_due <= 0 ) ? 'Paid in Full'" "( (float) get_post_meta( \$trip_id, 'cb_price', true ) > 0 && \$balance_due <= 0 ) ? 'Paid in Full'"
rm -rf /tmp/cbv_mut /tmp/cbv_t/define_mut.php /tmp/cbv_t/test_mut.php
