#!/bin/bash
# Step 12a (owner, 2026-10-14): How to book (new design) + "Who does what" disclosure on every trip page and the
# Terms of Service page; no-deposit installments on Gate 09. Renders real pages server-side with render_page.php
# (read only) against the temp copy; data variations are simulated with read filters, nothing is written.
#   bash /tmp/cbv_t/test_step12a.sh     (expects /tmp/cbv_t/define.php, render_page.php, preview12a.php)
# CBV_TERMS_LIVE=1 checks the Terms page itself (after the page write) instead of the preview.
# Set CBV_VERBOSE=1 to print every passing check.
cd ~/www/bagsandvibes.com/public_html || exit 1
n=0; fail=0
check() { n=$((n+1)); if [ "$2" = "1" ]; then [ -n "$CBV_VERBOSE" ] && echo "  pass $n: $1"; else fail=$((fail+1)); echo "FAIL: $1 $3"; fi; }
render() { CBV_PATH="$1" CBV_USER="$2" wp ${3:---require=/tmp/cbv_t/define.php} eval-file /tmp/cbv_t/render_page.php 2>/dev/null; }
weval() { wp --require=/tmp/cbv_t/define.php eval "$1" 2>/dev/null; }
has() { grep -c -- "$2" <<< "$1" | awk '{print ($1>0)?1:0}'; }
hasnt() { grep -c -- "$2" <<< "$1" | awk '{print ($1==0)?1:0}'; }
count() { grep -o -- "$2" <<< "$1" | wc -l; }
banned() { echo "$(( $(hasnt "$1" 'CBGV books your cabin') * $(hasnt "$1" 'InteleTravel') ))"; }

check "running against the temp mu-plugins copy" "$(grep -c '/tmp/cbv_mu' /tmp/cbv_t/define.php | awk '{print ($1>0)?1:0}')"

# 1. The owner's text, word for word (defaults, nothing saved yet).
out=$(weval 'foreach ( cbv_howto_site_steps() as $s ) { echo $s["title"] . "|" . $s["body"] . "|" . ( $s["fee"] ? "fee" : "-" ) . "\n"; } echo cbv_who_does_what()["text"];')
check "step 1 word for word" "$(has "$out" "Register for the trip|Tell us who's coming and which cabin you'd like.|-")"
check "step 2 word for word (the travel advisor books the cabin)" "$(has "$out" 'Book your cabin with our travel advisor|Andrea M. Peaten, our independent travel advisor, books your cabin, and the cruise line sends you a secure link to pay your deposit directly to them.|-')"
check "step 3 word for word, marked as the fee step" "$(has "$out" 'Pay the CBGV Group Experience Fee|This is paid separately to Checked Bags & Good Vibes, for the group program.|fee')"
check "step 4 word for word" "$(has "$out" "You're cleared for boarding|Log in anytime to see your trip, payments and updates.|-")"
check "disclosure word for word" "$(has "$out" 'Checked Bags & Good Vibes is a d/b/a of JourneyWell Global LLC, run by co-founder and COO LaDon Headen. CBGV organizes the group program and charges the CBGV Group Experience Fee. Travel is booked separately by Andrea M. Peaten, an independent travel advisor, and paid directly to the cruise line or travel supplier. Andrea M. Peaten is also an owner of JourneyWell Global LLC. She receives standard commissions from travel suppliers for the travel she books, separate from the CBGV Group Experience Fee. CBGV never collects or holds payments for travel. When a CBGV host travels with the group, their travel is paid for from CBGV Group Experience Fees, or with a complimentary cabin, room or seat provided to the group by the travel supplier.')"
check "no step or disclosure text says \"CBGV books your cabin\" or names InteleTravel" "$(banned "$out")"

# 2. New design (admin preview), Helmets: no provider -> the site's 4 steps, it has a fee.
h=$(render "/trip/helmets-hearts-high-seas-2/?preview=new" 1)
check "Helmets preview: How to book with \"Four steps to the gangway\" and 4 numbered steps" "$(( $(has "$h" '>Four steps to the gangway<') * $([ "$(count "$h" 'class="cbv-lp-how-step"')" -eq 4 ] && echo 1 || echo 0) * $(has "$h" '>04<') ))"
check "Helmets preview: the gate label is \"· Check-in\"" "$(has "$h" 'Gate [0-9]* · Check-in')"
code=$(weval 'echo get_post_meta( 320, "cb_trip_code", true );')
check "Register now goes to this trip's registration link; \"Booking 5+ cabins? Talk to us\" goes to Contact" "$(( $(has "$h" "class=\"cbv-lp-how-btn\" href=\"https://bagsandvibes.com/join/?trip=$code\"") * $(has "$h" 'href="https://bagsandvibes.com/contact/">Booking 5+ cabins? Talk to us') ))"
check "Helmets preview: the disclosure once, with \"Last updated October 14, 2026\"" "$(( $([ "$(count "$h" 'class="cbv-who ')" -eq 1 ] && echo 1 || echo 0) * $(has "$h" 'Last updated October 14, 2026') ))"
check "Helmets preview: no banned wording" "$(banned "$h")"

# 3. Provider steps REPLACE the site default (trip 181 is on Test Provider 402, 2 steps).
h=$(render "/trip/annual-family-and-friends/?preview=new" 1)
check "181 preview: the provider's own steps replace the default (\"Two steps to the gangway\")" "$(( $(has "$h" '>Two steps to the gangway<') * $(hasnt "$h" 'Book your cabin with our travel advisor') ))"
check "181 preview: the disclosure once" "$([ "$(count "$h" 'class="cbv-who ')" -eq 1 ] && echo 1 || echo 0)"

# 4. No fee -> the fee step is left out and the count adjusts; other types say "to your trip".
out=$(weval 'add_filter( "get_post_metadata", function ( $v, $id, $k ) { return ( 320 === (int) $id && "cbv_lp_planning_fee" === $k ) ? array( array( "mode" => "none", "amount" => 0, "basis" => "per_traveler" ) ) : $v; }, 1, 3 ); $s = cbv_howto_steps( 320 ); echo count( $s ) . "|" . cbv_howto_heading( 320, count( $s ) ) . "|" . implode( ",", wp_list_pluck( $s, "title" ) );')
check "no fee: 3 steps, \"Three steps to the gangway\", no fee step" "$(( $(has "$out" '^3|Three steps to the gangway|') * $(hasnt "$out" 'Pay the CBGV') ))" "($out)"
out=$(weval 'add_filter( "get_post_metadata", function ( $v, $id, $k ) { return ( 320 === (int) $id && "cbv_lp_event_type" === $k ) ? array( "resort" ) : $v; }, 1, 3 ); echo cbv_howto_heading( 320, 4 ) . "|" . cbv_howto_heading( 320, 1 ) . "|"; echo cbv_lp_render_how( 320 );')
check "other types: \"Four steps to your trip\" (\"One step to your trip\" for one); the button says rooms" "$(( $(has "$out" '^Four steps to your trip|One step to your trip|') * $(has "$out" 'Booking 5+ rooms? Talk to us') ))"
out=$(weval 'add_filter( "get_post_metadata", function ( $v, $id, $k ) { return ( 320 === (int) $id && "cbv_lp_sections" === $k ) ? array( array( "how" => "off" ) ) : $v; }, 1, 3 ); echo "[" . cbv_lp_render_how( 320 ) . "]" . cbv_lp_render_who( 320 );')
check "How to book switched off: the section is gone, the disclosure stays" "$(( $(has "$out" '^\[\]') * $(has "$out" 'class="cbv-who cbv-who--lp"') ))"

# 5. The disclosure on every other trip page (once each) and never on unrelated pages.
for row in "/trip/annual-family-and-friends/|0|old public page" "/trip/annual-family-and-friends/|1|members' page" "/trip/annual-family-and-friends/|105|agreement screen" "/trip/helmets-hearts-high-seas-2/|107|no-access page"; do
  IFS='|' read -r p u label <<< "$row"
  h=$(render "$p" "$u")
  check "$label (user $u): the disclosure once, no banned wording" "$(( $([ "$(count "$h" 'class="cbv-who ')" -eq 1 ] && echo 1 || echo 0) * $(banned "$h") ))"
done
h=$(render /gate-09-payments/ 1)
check "not on non-trip pages (Gate 09)" "$(hasnt "$h" 'class="cbv-who ')"

# 6. Terms of Service: right after section 2, before section 3.
if [ -n "$CBV_TERMS_LIVE" ]; then h=$(render /terms-of-service/ 0); else h=$(render /terms-of-service/ 0 --require=/tmp/cbv_t/preview12a.php); fi
order=$(python3 -c 'import sys; h=sys.stdin.read(); a=h.find("2. Bookings and Payments"); w=h.find("class=\"cbv-who cbv-who--terms\""); b=h.find("3. Custom Trip Requests"); print(1 if 0 <= a < w < b else 0)' <<< "$h")
check "Terms: \"Who does what\" sits between section 2 and section 3" "$order"

# 7. Settings are cleaned and escaped.
out=$(weval '$c = cbv_howto_clean_steps( array( array( "title" => "<b>Hi</b><script>x</script>", "body" => "a <i>b</i>", "fee" => "1" ), array( "title" => "", "body" => "" ) ) ); echo count( $c ) . "|" . $c[0]["title"] . "|" . ( $c[0]["fee"] ? "fee" : "-" );')
check "settings: tags stripped, empty rows dropped, fee flag kept" "$(( $(has "$out" '^1|Hi|fee') * $(hasnt "$out" '<script') ))" "($out)"
out=$(weval 'add_filter( "pre_option_cbv_who_does_what", function () { return array( "text" => "A <script>bad</script> & B", "updated" => "2026-11-01" ); } ); echo cbv_render_who_does_what( "page" );')
check "disclosure output is escaped; its date shows (\"Last updated November 1, 2026\")" "$(( $(hasnt "$out" '<script>') * $(has "$out" '&lt;script&gt;') * $(has "$out" 'Last updated November 1, 2026') ))"
out=$(weval 'wp_set_current_user( 1 ); ob_start(); cbv_render_howto_settings_page(); echo ob_get_clean();')
check "settings page: shows the 4 steps, the fee tick on step 3 and the disclosure box" "$(( $(has "$out" 'value="Book your cabin with our travel advisor"') * $(has "$out" 'name="cbv_howto_steps\[2\]\[fee\]" value="1"  checked') * $(has "$out" 'name="cbv_who_text"') ))"

# 8. Gate 09: no deposit, up to 3 equal installments (trip 181 settings simulated).
pay='$GLOBALS["pays"] = array(); add_filter( "get_post_metadata", function ( $v, $id, $k ) { if ( 181 !== (int) $id ) { return $v; } if ( "cb_deposit_amount" === $k ) { return array( 0 ); } if ( "cb_num_installments" === $k ) { return array( 3 ); } if ( "cb_payments" === $k ) { return array( $GLOBALS["pays"] ); } return $v; }, 1, 3 ); $u = 105; $a = array(); for ( $i = 0; $i < 4; $i++ ) { $x = cb_trip_next_payment_amount( 181, $u ); $a[] = $x; if ( $x <= 0 ) { break; } $GLOBALS["pays"][] = array( "user_id" => $u, "amount" => $x ); } echo implode( ",", $a );'
out=$(weval "$pay")
check "no deposit, 3 installments, \$225: pays 75, 75, 75, then nothing" "$([ "$out" = "75,75,75,0" ] && echo 1 || echo 0)" "($out)"
out=$(weval 'add_filter( "get_post_metadata", function ( $v, $id, $k ) { if ( 181 !== (int) $id ) { return $v; } if ( "cb_deposit_amount" === $k ) { return array( 500 ); } if ( "cb_num_installments" === $k ) { return array( 10 ); } if ( "cb_payments" === $k ) { return array( array() ); } return $v; }, 1, 3 ); echo cb_trip_next_payment_amount( 181, 105 );')
check "with a deposit the old rule is unchanged (deposit 500 > fee 225: first payment 225)" "$([ "$out" = "225" ] && echo 1 || echo 0)" "($out)"

# 9. CSS.
check "trip-landing.css: How to book + disclosure styles; styles.css: disclosure on trip and Terms pages" "$(( $(grep -c 'body.cbv-lp .cbv-lp-how-step {' /tmp/cbv_t/trip-landing-new.css | awk '{print ($1>0)?1:0}') * $(grep -c '.cbv-who--terms .cbv-who-title' /tmp/cbv_t/styles.css | awk '{print ($1>0)?1:0}') ))"

echo
echo "$n checks, $fail failures"
