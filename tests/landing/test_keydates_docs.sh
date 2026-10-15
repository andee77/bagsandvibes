#!/bin/bash
# Countdown (key dates) + Travel documents (owner, 2026-10-14; go-live R4/R5): new design sections after How to book,
# and the members' trip page after "Rules for this trip" (only once the trip agreement is accepted). Past dates
# dimmed with "Passed"; heading "The fine print, folded neatly"; lede "Key {provider} policies, in short. The cruise
# line's own terms always apply."; "Last reviewed" dates never shown publicly. Read only (render_page.php + read
# filters), against the temp copy.
#   bash /tmp/cbv_t/test_keydates_docs.sh   (expects /tmp/cbv_t/define.php, define_v2.php, render_page.php, styles)
# Set CBV_VERBOSE=1 to print every passing check.
cd ~/www/bagsandvibes.com/public_html || exit 1
n=0; fail=0
check() { n=$((n+1)); if [ "$2" = "1" ]; then [ -n "$CBV_VERBOSE" ] && echo "  pass $n: $1"; else fail=$((fail+1)); echo "FAIL: $1 $3"; fi; }
DEF=${CBV_DEFINE:-/tmp/cbv_t/define_v2.php}   # define.php + v2ok.php (renders count as having accepted the current Master Traveler Agreement)
render() { CBV_PATH="$1" CBV_USER="$2" wp --require=$DEF eval-file /tmp/cbv_t/render_page.php 2>/dev/null; }
weval() { wp --require=$DEF eval "$1" 2>/dev/null; }
has() { grep -c -- "$2" <<< "$1" | awk '{print ($1>0)?1:0}'; }
hasnt() { grep -c -- "$2" <<< "$1" | awk '{print ($1==0)?1:0}'; }
count() { grep -o -- "$2" <<< "$1" | wc -l; }
banned() { echo "$(( $(hasnt "$1" 'CBGV books your cabin') * $(hasnt "$1" 'InteleTravel') * $(hasnt "$1" 'Last reviewed') ))"; }
ROWS='echo implode( "|", array_map( function ( $r ) { return $r["sort"] . "~" . $r["title"] . "~" . ( $r["dot"] ?: "gold" ) . "~" . $r["description"] . "~" . ( $r["passed"] ? "passed" : "-" ); }, cbv_kd_rows( 181 ) ) );'
TRIPDATES() { echo 'add_filter( "get_post_metadata", function ( $v, $id, $k ) { return ( 181 === (int) $id && "cbv_lp_key_dates" === $k ) ? array( array( '"$1"' ) ) : $v; }, 1, 3 );'; }

check "running against the temp mu-plugins copy" "$(grep -c '/tmp/cbv_mu' /tmp/cbv_t/define.php | awk '{print ($1>0)?1:0}')"

# 1. Trip 181's rows (Virgin provider, start 2027-10-25).
out=$(weval "$ROWS")
check "181: 4 rows, oldest first, with titles, dots and the \"120 days out.\" text" "$([ "$out" = "2027-06-27~Final payment due~coral~120 days out.~-|2027-09-09~Last day to change dates or cabins~gold~~-|2027-09-10~Payments become final~gold~~-|2027-10-23~Name changes and transfers close~gold~~-" ] && echo 1 || echo 0)" "($out)"

# 2. Trip overrides (simulated): a moved date re-sorts; "not applicable" removes; hidden is left out.
out=$(weval "$(TRIPDATES 'array( "key" => "funds_final", "date" => "2027-06-01" )') $ROWS")
check "a trip date moving \"Payments become final\" to Jun 1 puts it first" "$(has "$out" '^2027-06-01~Payments become final~')" "($out)"
out=$(weval "$(TRIPDATES 'array( "key" => "name_change_cutoff", "off" => 1 )') $ROWS")
check "\"not applicable\" on the trip removes that provider date (3 rows, no name-change row)" "$(( $([ "$(tr '|' '\n' <<< "$out" | wc -l)" -eq 3 ] && echo 1 || echo 0) * $(hasnt "$out" 'Name changes') ))" "($out)"
out=$(weval "$(TRIPDATES 'array( "key" => "date_change_deadline", "offset" => 46, "hidden" => 1 )') $ROWS")
check "a hidden date is left out of the countdown" "$(hasnt "$out" 'Last day to change')" "($out)"

# 3. Past dates: dimmed with "Passed" (today simulated as 2027-09-10).
out=$(weval 'add_filter( "cbv_kd_today", function () { return "2027-09-10"; } ); '"$ROWS"' echo "#"; echo cbv_lp_render_countdown( 181 );')
check "today = Sep 10, 2027: Jun 27 and Sep 9 passed, Sep 10 and Oct 23 not" "$(has "$out" '2027-06-27~Final payment due~coral~120 days out.~passed|2027-09-09~Last day to change dates or cabins~gold~~passed|2027-09-10~Payments become final~gold~~-|')" "($out)"
check "passed rows render dimmed (is-passed) with a \"Passed\" tag, twice" "$(( $([ "$(count "$out" 'cbv-kd-row is-passed')" -eq 2 ] && echo 1 || echo 0) * $([ "$(count "$out" 'class="cbv-kd-passed">Passed<')" -eq 2 ] && echo 1 || echo 0) ))"

# 4. New design (admin preview of 181).
h=$(render "/trip/annual-family-and-friends/?preview=new" 1)
check "preview: \"Gate nn · Countdown\" + \"Countdown to boarding\" with 4 rows" "$(( $(has "$h" 'class="cbv-lp-gate">Gate [0-9]* · Countdown<') * $(has "$h" '>Countdown to boarding<') * $([ "$(count "$h" 'class="cbv-kd-row')" -eq 4 ] && echo 1 || echo 0) ))"
check "preview: Countdown comes after How to book and before Travel documents, then \"Who does what\"" "$(python3 -c 'import sys; h=sys.stdin.read(); a=[h.find(x) for x in ("class=\"cbv-lp-how\"","class=\"cbv-lp-countdown\"","class=\"cbv-lp-docs\"","class=\"cbv-lp-who\"")]; print(1 if -1 not in a and a==sorted(a) else 0)' <<< "$h")"
check "preview: \"Gate nn · Travel documents\", \"The fine print, folded neatly\" and the Virgin lede" "$(( $(has "$h" 'class="cbv-lp-gate">Gate [0-9]* · Travel documents<') * $(has "$h" '>The fine print, folded neatly<') * $(has "$h" "Key Virgin Voyages policies, in short. The cruise line&#039;s own terms always apply.") ))"
check "preview: 4 document panels, all closed, in order" "$(( $([ "$(count "$h" '<details class="cbv-kd-doc">')" -eq 4 ] && echo 1 || echo 0) * $(python3 -c 'import sys,re; h=sys.stdin.read(); t=re.findall(r"<span class=\"cbv-kd-doc-title\">([^<]*)", h); print(1 if t==["Cancellation policy","Payment plans &amp; deadlines","ID required for boarding","Accessibility, upgrades &amp; changes"] else 0)' <<< "$h") ))"
check "preview: the date token is filled in (\"Final payment is due June 27, 2027\", bold)" "$(has "$h" '<strong>Final payment is due June 27, 2027</strong>, 120 days before sailing.')"
check "preview: pay-later line keeps \"subject to approval\"; owner's accessibility wording" "$(( $(has "$h" 'financing partner, subject to approval.') * $(has "$h" 'Accessible cabins are limited. Ask your travel advisor to check availability when you book.') ))"
check "preview: no banned wording, no \"Last reviewed\"" "$(banned "$h")"

# 5. Switches and empty states.
out=$(weval 'add_filter( "get_post_metadata", function ( $v, $id, $k ) { return ( 181 === (int) $id && "cbv_lp_sections" === $k ) ? array( array( "timeline" => "off", "docs" => "off" ) ) : $v; }, 1, 3 ); echo "[" . cbv_lp_render_countdown( 181 ) . "][" . cbv_lp_render_docs( 181 ) . "][" . cbv_render_member_countdown_docs( 181 ) . "]";')
check "both switches off: no Countdown, no Travel documents, on either page" "$([ "$out" = "[][][]" ] && echo 1 || echo 0)" "($(cut -c1-80 <<< "$out"))"
out=$(weval 'echo cbv_kd_docs_lede( 320 ) . "|[" . cbv_lp_render_docs( 320 ) . "][" . cbv_lp_render_countdown( 320 ) . "]";')
check "no provider (Helmets): lede \"Key policies, in short.\", and both sections hide with nothing to show" "$(has "$out" '^Key policies, in short.|\[\]\[\]')" "($out)"

# 6. Members' trip page.
h=$(render /trip/annual-family-and-friends/ 1)
check "members' page (agreement accepted): Countdown and The fine print, after \"Rules for this trip\", before \"Who's going\"" "$(python3 -c 'import sys; h=sys.stdin.read(); a=[h.find(x) for x in ("trip-detail-rules","cbv-kd-member--countdown","cbv-kd-member--docs","Who&#039;s going")]; b=[h.find(x) for x in ("trip-detail-rules","cbv-kd-member--countdown","cbv-kd-member--docs","Who'"'"'s going")]; ok=lambda v: -1 not in v and v==sorted(v); print(1 if ok(a) or ok(b) else 0)' <<< "$h")"
check "members' page: 4 rows, 4 panels, the Virgin lede, no banned wording" "$(( $([ "$(count "$h" 'class="cbv-kd-row')" -eq 4 ] && echo 1 || echo 0) * $([ "$(count "$h" '<details class="cbv-kd-doc">')" -eq 4 ] && echo 1 || echo 0) * $(has "$h" 'Key Virgin Voyages policies') * $(banned "$h") ))"
h=$(render /trip/annual-family-and-friends/ 105)
check "a traveler who still has to accept the trip agreement sees neither" "$(( $(has "$h" 'trip-agreement-gate') * $(hasnt "$h" 'cbv-kd-member') ))"
h=$(CBV_MO_BASE=$DEF CBV_PATH=/trip/annual-family-and-friends/ CBV_USER=0 wp --require=/tmp/cbv_t/master_off.php eval-file /tmp/cbv_t/render_page.php 2>/dev/null)   # master switch off in memory (181 is live since 2026-10-15)
check "signed-out old public page: unchanged (no Countdown or documents)" "$(hasnt "$h" 'cbv-kd-')"

# 7. CSS.
check "trip-landing.css: countdown rows, passed state and document panels; styles.css: members' page versions" "$(( $(grep -c 'body.cbv-lp .cbv-kd-row.is-passed' /tmp/cbv_t/trip-landing-new.css | awk '{print ($1>0)?1:0}') * $(grep -c 'body.cbv-lp .cbv-kd-doc\[open\] .cbv-kd-chev::before' /tmp/cbv_t/trip-landing-new.css | awk '{print ($1>0)?1:0}') * $(grep -c '.cbv-kd-member .cbv-kd-doc summary' /tmp/cbv_t/styles.css | awk '{print ($1>0)?1:0}') ))"

echo
echo "$n checks, $fail failures"
