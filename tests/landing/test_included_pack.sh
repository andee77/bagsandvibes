#!/bin/bash
# What's included + Pack list (owner, 2026-10-14), the route note, the old Highlights hidden on the new design, and
# the trip's own social image winning over the cover photo. Read only: renders with render_page.php against the temp
# copy; trip 181 / provider content is supplied in memory by preview_inc.php (the content proposed for the writes).
#   bash /tmp/cbv_t/test_included_pack.sh   (expects /tmp/cbv_t/define.php, define_v2.php, preview_inc.php, render_page.php)
# Set CBV_VERBOSE=1 to print every passing check.
cd ~/www/bagsandvibes.com/public_html || exit 1
n=0; fail=0
check() { n=$((n+1)); if [ "$2" = "1" ]; then [ -n "$CBV_VERBOSE" ] && echo "  pass $n: $1"; else fail=$((fail+1)); echo "FAIL: $1 $3"; fi; }
PV=${CBV_PREVIEW:-/tmp/cbv_t/preview_inc.php}
export CBV_DEF=${CBV_DEF:-/tmp/cbv_t/define_v2.php}
render() { CBV_PATH="$1" CBV_USER="$2" wp --require=$PV eval-file /tmp/cbv_t/render_page.php 2>/dev/null; }
weval() { wp --require=$PV eval "$1" 2>/dev/null; }
has() { grep -c -- "$2" <<< "$1" | awk '{print ($1>0)?1:0}'; }
hasnt() { grep -c -- "$2" <<< "$1" | awk '{print ($1==0)?1:0}'; }
count() { grep -o -- "$2" <<< "$1" | wc -l; }
TIERS() { echo '$t = get_post_meta( 181, "cb_pricing_tiers", true ); '"$1"' add_filter( "get_post_metadata", function ( $v, $id, $k ) use ( $t ) { return ( 181 === (int) $id && "cb_pricing_tiers" === $k ) ? array( $t ) : $v; }, 1, 3 );'; }

check "running against the temp mu-plugins copy" "$(grep -c '/tmp/cbv_mu' /tmp/cbv_t/define.php | awk '{print ($1>0)?1:0}')"

# 1. "In your all-in price".
out=$(weval 'echo implode( "|", cbv_inc_allin_parts( 181 ) );')
check "181: the five parts, in order, the fee as its own ticket with its amount (owner, 2026-10-15)" "$([ "$out" = 'Cruise fare|Taxes & fees|Prepaid gratuities|Voyage Protection|CBGV Group Experience Fee ($225 per traveler)' ] && echo 1 || echo 0)" "($out)"
out=$(weval "$(TIERS '$t[0]["occupancy_points"][0]["gratuities"] = 0;') echo implode( '|', cbv_inc_allin_parts( 181 ) );")
check "a part one price doesn't include is left out (gratuities 0 on one price)" "$(( $(hasnt "$out" 'Prepaid gratuities') * $(has "$out" 'Taxes & fees') ))" "($out)"
out=$(weval "$(TIERS 'foreach ( $t as &$tt ) { foreach ( $tt["occupancy_points"] as &$pp ) { $pp["discount"] = 50; } }') echo implode( '|', cbv_inc_allin_parts( 181 ) );")
check "never \"at our group rate\", even with a discount on every price (owner, 2026-10-14)" "$(( $(has "$out" '^Cruise fare|') * $(hasnt "$out" 'group rate') ))" "($out)"
out=$(weval 'add_filter( "get_post_metadata", function ( $v, $id, $k ) { return ( 181 === (int) $id && "cbv_lp_planning_fee" === $k ) ? array( array( "mode" => "none", "amount" => 0, "basis" => "per_traveler" ) ) : $v; }, 1, 3 ); echo implode( "|", cbv_inc_allin_parts( 181 ) );')
check "no CBGV fee on the trip: no fee line" "$(hasnt "$out" 'CBGV Group Experience Fee')" "($out)"
out=$(weval 'add_filter( "get_post_metadata", function ( $v, $id, $k ) { return ( 181 === (int) $id && "cbv_lp_event_type" === $k ) ? array( "resort" ) : $v; }, 1, 3 ); echo implode( "|", cbv_inc_allin_parts( 181 ) );')
check "other trip types say \"Travel price\" and \"Travel protection\"" "$(( $(has "$out" '^Travel price|') * $(has "$out" 'Travel protection') ))" "($out)"

# 1b. Cruise-line cards follow the price board (owner, 2026-10-14).
NOOTHER='$pb0 = get_post_meta( 181, "cbv_lp_priceboard", true ); $pb0 = is_array( $pb0 ) ? $pb0 : array(); $pb0["other"] = array(); add_filter( "get_post_metadata", function ( $v, $id, $k ) use ( $pb0 ) { return ( 181 === (int) $id && "cbv_lp_priceboard" === $k ) ? array( $pb0 ) : $v; }, 0, 3 );'   # no fares on request (181 has Essential + Base since 2026-10-15)
CARDS="$NOOTHER"'$c = (array) cbv_lp_group( 181, "included" ); $b = function ( $cols, $groups ) { $g = array(); foreach ( $groups as $n ) { $g[] = array( "name" => $n, "rows" => array( array( "name" => $n ) ) ); } return array( "columns" => $cols, "groups" => $g ); }; $t = function ( $x ) { return implode( "|", wp_list_pluck( $x, "title" ) ); };'
out=$(weval "$CARDS"' echo $t( cbv_inc_cards_for_board( 181, $c ) );')
check "181 live board (Premium + one-price suites, Oct 15 invoices): the Premium card and both suite cards" "$([ "$out" = 'Premium|RockStar Quarters|Mega RockStar' ] && echo 1 || echo 0)" "($out)"
out=$(weval "$CARDS"' echo $t( cbv_inc_cards_for_board( 181, $c, $b( array( 1 => "Base", 3 => "Premium" ), array( "The Insider", "Sea View" ) ) ) );')
check "board Base + Premium, no suites: Base and Premium cards only" "$([ "$out" = 'Base|Premium' ] && echo 1 || echo 0)" "($out)"
out=$(weval "$CARDS"' echo $t( cbv_inc_cards_for_board( 181, $c, $b( array( 3 => "Premium" ), array( "Sea Terrace", "RockStar Quarters" ) ) ) );')
check "board lists RockStar Quarters: that suite card shows, Mega RockStar doesn't" "$([ "$out" = 'Premium|RockStar Quarters' ] && echo 1 || echo 0)" "($out)"
out=$(weval "$CARDS"' echo $t( cbv_inc_cards_for_board( 181, $c, $b( array( 2 => "Essential" ), array( "Sea View", "Massive Suite" ) ) ) );')
check "a suite row named by a card's subline (\"Massive\") shows Mega RockStar" "$([ "$out" = 'Essential|Mega RockStar' ] && echo 1 || echo 0)" "($out)"
out=$(weval "$CARDS"' echo count( cbv_inc_cards_for_board( 181, $c, array( "columns" => array(), "groups" => array() ) ) );')
check "no price board yet: every card shows" "$([ "$out" = '5' ] && echo 1 || echo 0)" "($out)"

# 1c. Other fares on request (owner, 2026-10-15): set in memory to Essential + Base (Premium ticked too: already on the board, ignored).
OTHER='$pb = get_post_meta( 181, "cbv_lp_priceboard", true ); $pb = is_array( $pb ) ? $pb : array(); $pb["other"] = array( "Base", "Premium", "Essential" ); add_filter( "get_post_metadata", function ( $v, $id, $k ) use ( $pb ) { return ( 181 === (int) $id && "cbv_lp_priceboard" === $k ) ? array( $pb ) : $v; }, 0, 3 );'
out=$(weval "$OTHER"' echo implode( "|", cbv_lp_board_other_fares( 181 ) );')
check "on request: Essential, Base (richest first; Premium skipped, it is on the board)" "$([ "$out" = 'Essential|Base' ] && echo 1 || echo 0)" "($out)"
out=$(weval "$OTHER"' echo cbv_lp_render_priceboard( 181 );')
check "on request: the note under the board, word for word, and \"Request a quote\" to the Contact page" "$(( $(has "$out" "Prices shown are Premium fares. Essential and Base fares are also available. Ask us for a quote and we&#039;ll compare them for you. Some fares book outside our group block; your travel advisor will explain what that means for you.") * $(has "$out" 'class="cbv-lp-prices-request-cta" href="https://bagsandvibes.com/contact/">Request a quote') ))"
out=$(weval "$OTHER"' echo implode( "|", array_map( function ( $c ) { return $c["title"] . ( empty( $c["on_request"] ) ? "" : "*" ); }, cbv_inc_cruise_line( 181 )["cards"] ) );')
check "on request: What's included adds the Base and Essential cards, marked on request" "$([ "$out" = 'Base*|Essential*|Premium|RockStar Quarters|Mega RockStar' ] && echo 1 || echo 0)" "($out)"
out=$(weval "$OTHER"' echo cbv_inc_cards_html( cbv_inc_cruise_line( 181 )["cards"], 181 );')
check "on request: the cards carry the \"Available on request\" tag (2 of them)" "$([ "$(grep -o 'cbv-inc-card-request">Available on request' <<< "$out" | wc -l)" -eq 2 ] && echo 1 || echo 0)"
out=$(weval "$NOOTHER"' echo "[" . cbv_lp_render_priceboard( 181 ) . "]";')
check "nothing ticked: no note, no button, no on-request cards" "$(( $(hasnt "$out" 'cbv-lp-prices-request') * $(hasnt "$(weval "$NOOTHER"' echo cbv_inc_cards_html( cbv_inc_cruise_line( 181 )["cards"], 181 );')" 'Available on request') ))"
out=$(weval 'echo cbv_lp_join_names( array( "A" ) ) . "|" . cbv_lp_join_names( array( "A", "B" ) ) . "|" . cbv_lp_join_names( array( "A", "B", "C" ) ) . "|" . wp_json_encode( cbv_lp_sanitize_priceboard( array( "other" => array( " Base ", "Base", "<b>X</b>", array( 1 ) ) ) )["other"] );')
check "helpers: name lists read naturally; ticked fares cleaned and de-duplicated" "$([ "$out" = 'A|A and B|A, B and C|["Base","X"]' ] && echo 1 || echo 0)" "($out)"

# 2. New design (181 preview with the proposed content).
h=$(render "/trip/annual-family-and-friends/?preview=new" 1)
check "preview: mockup order: fare classes, \"Gate 19 · What's included\", \"Gate 20 · Pack list\", How to book, Countdown, then Travel documents last (Gate 23), then Who does what" "$(python3 -c 'import sys; h=sys.stdin.read(); a=[h.find(x) for x in ("class=\"cbv-lp-prices\"","class=\"cbv-lp-included\"","class=\"cbv-lp-pack\"","class=\"cbv-lp-how\"","class=\"cbv-lp-countdown\"","class=\"cbv-lp-docs\"","class=\"cbv-lp-who\"")]; print(1 if -1 not in a and a==sorted(a) and "Gate 19 · What&#039;s included" in h and "Gate 20 · Pack list" in h and "Gate 23 · Travel documents" in h and "Gate 24" not in h else 0)' <<< "$h")"

check "preview: heading \"Every fare comes with extras\" and the three-part lede" "$(( $(has "$h" '>Every fare comes with extras<') * $(has "$h" 'What your all-in price covers, what Virgin Voyages includes with each fare, and what our group adds.') ))"
check "preview: 5 cruise-line cards (Base + Essential on request, Premium, the two suites) with the mockup's lines; dining dates from the sail date; no leftover tokens" "$(( $([ "$(grep -o 'class="cbv-inc-card[" ]' <<< "$h" | wc -l)" -eq 5 ] && echo 1 || echo 0) * $(has "$h" 'Premium Wi-Fi with streaming, 2 devices') * $(has "$h" '\$75 premium bar tab per sailor') * $(has "$h" 'Dining reservations open August 26, 2027') * $(has "$h" '<strong>Sea Terrace cabins add \$50 onboard credit</strong>') * $(has "$h" 'First to book: dining &amp; experiences open June 27, 2027') * $(has "$h" 'Free parking at Terminal V (Miami sailings)') * $(hasnt "$h" '{date:') * $([ "$(grep -o 'Available on request' <<< "$h" | wc -l)" -eq 2 ] && echo 1 || echo 0) ))"
check "preview: each all-in part is its own ticket (label, title, stub), the fee included" "$(( $([ "$(grep -o 'class="cbv-inc-ticket"' <<< "$h" | wc -l)" -eq 5 ] && echo 1 || echo 0) * $(has "$h" '<span class="cbv-inc-ticket-label">Included · 01</span><span class="cbv-inc-ticket-title">Cruise fare</span>') * $(has "$h" '<span class="cbv-inc-ticket-label">Included · 05</span><span class="cbv-inc-ticket-title">CBGV Group Experience Fee</span><span class="cbv-inc-ticket-sub">$225 per traveler</span>') * $(has "$h" 'class="cbv-inc-ticket-stub" aria-hidden="true"') ))"
check "preview: no empty legacy band above the footer (nothing of the old page left)" "$(hasnt "$h" 'class="cbv-lp-legacy-wrap"')"
check "preview: the provider footnote \"Perks vary by fare...\"" "$(has "$h" 'Perks vary by fare. Your travel advisor will confirm exactly what&#039;s included when you book.')"
check "preview: the 6-line group program and its note; no swag, no host line" "$(( $(python3 -c 'import sys,re; h=sys.stdin.read(); m=re.search(r"<ul class=\"cbv-inc-program-list\">(.*?)</ul>", h, re.S); print(1 if m and m.group(1).count("<li>")==6 else 0)' <<< "$h") * $(has "$h" 'Coffee and goodbyes (Day 6)') * $(has "$h" 'All events and times are subject to change.') * $(hasnt "$h" 'swag') * $(hasnt "$h" 'On-trip CBGV host\|An on-trip CBGV host') ))"
check "preview: 4 tags numbered TAG 01-04, the forecast chip" "$(( $(has "$h" '>Tag 01 · Theme nights<') * $(has "$h" '>Tag 04 · Cabin kit<') * $(hasnt "$h" '>Tag 05') * $(has "$h" 'class="cbv-lp-pack-forecast">FORECAST · LOW 80s °F · WARM &amp; TROPICAL<') ))"
check "preview: no PJ Party / 80s Workout; \"something red\" for Scarlet Night (Day 2); magnetic hooks kept" "$(( $(hasnt "$h" 'PJ Party') * $(hasnt "$h" '80s Workout') * $(has "$h" 'Scarlet Night (Day 2):</strong> something red') * $(has "$h" 'Magnetic hooks') ))"
check "preview: Leave at home = the provider's 6 lines + the link to Virgin's full list (new tab); sources never shown" "$(( $(python3 -c 'import sys,re; h=sys.stdin.read(); m=re.search(r"<ul class=\"cbv-inc-leave-list\">(.*?)</ul>", h, re.S); print(1 if m and m.group(1).count("<li>")==6 else 0)' <<< "$h") * $(has "$h" 'href="https://www.virginvoyages.com/safety-security/prohibited-items" target="_blank" rel="noopener">Virgin Voyages&#039; full prohibited items list') * $(hasnt "$h" 'pre-voyage-policies') * $(hasnt "$h" '2026-10-14') ))"
check "preview: the old Highlights block is gone on the new design" "$(hasnt "$h" 'cbv-landing-highlights')"
check "preview: no InteleTravel, no \"CBGV books your cabin\", no catamaran" "$(( $(hasnt "$h" 'InteleTravel') * $(hasnt "$h" 'CBGV books your cabin') * $(hasnt "$h" 'atamaran') ))"
check "preview: route note \"All events and times are subject to change.\" under the day cards" "$(has "$h" '<p class="cbv-lp-route-note">All events and times are subject to change.</p>')"

# 3. Old page and members' page.
h=$(CBV_MO_BASE=$PV CBV_PATH=/trip/annual-family-and-friends/ CBV_USER=0 wp --require=/tmp/cbv_t/master_off.php eval-file /tmp/cbv_t/render_page.php 2>/dev/null)   # master switch off in memory (181 is live since 2026-10-15)
check "old public page: keeps Highlights (new Bimini wording), no new sections" "$(( $(has "$h" 'cbv-landing-highlights') * $(has "$h" 'lounging in a private cabana at The Beach Club at Bimini') * $(hasnt "$h" 'cbv-inc-') ))"
h=$(render /trip/annual-family-and-friends/ 1)
check "members' page: What's included and Pack list, then the travel documents (fine print last), before Who's going" "$(python3 -c 'import sys; h=sys.stdin.read(); a=[h.find(x) for x in ("cbv-inc-member--included","cbv-inc-member--pack","cbv-kd-member--docs")]; w=max(h.find("Who&#039;s going"), h.find("Who'"'"'s going")); print(1 if -1 not in a and a==sorted(a) and w>a[-1] else 0)' <<< "$h")"
h=$(render /trip/annual-family-and-friends/ 105)
check "a traveler who still has to accept the trip agreement sees neither" "$(( $(has "$h" 'trip-agreement-gate') * $(hasnt "$h" 'cbv-inc-member') ))"

# 4. Empty states and switches.
out=$(weval 'add_filter( "get_post_metadata", function ( $v, $id, $k ) { return ( 181 === (int) $id && "cbv_lp_sections" === $k ) ? array( array( "included" => "off", "pack" => "off" ) ) : $v; }, 1, 3 ); echo "[" . cbv_lp_render_included( 181 ) . "][" . cbv_lp_render_pack( 181 ) . "][" . cbv_render_member_included_pack( 181 ) . "]";')
check "both switches off: neither section on either page" "$([ "$out" = "[][][]" ] && echo 1 || echo 0)" "($(cut -c1-60 <<< "$out"))"
out=$(weval 'echo "[" . cbv_lp_render_pack( 320 ) . "]|" . cbv_inc_lede( 320 );')
check "Helmets (no provider, no pack data): no Pack list; the lede names only the price part" "$(has "$out" '^\[\]|What your all-in price covers.$')" "($out)"

# 5. Cleaning.
out=$(weval '$t = cbv_inc_clean_tags( array( array( "category" => "", "title" => "", "items" => "" ), array( "category" => "<b>A</b>", "title" => "T", "items" => "one\n\n two " ) ) ); echo count( $t ) . "|" . $t[0]["category"] . "|" . implode( ",", $t[0]["items"] ); $r = cbv_inc_clean_leave_rows( array( array( "text" => "X", "source" => "javascript:alert(1)", "checked" => "bad" ), array( "text" => "", "source" => "https://a.b" ) ) ); echo "#" . count( $r ) . "|" . $r[0]["source"] . "|" . $r[0]["checked"]; echo "#" . count( cbv_inc_clean_tags( array_fill( 0, 9, array( "title" => "x" ) ) ) );')
check "cleaning: empty tags dropped, tags stripped, blank item lines dropped; unsafe source links and bad dates emptied; at most 6 tags" "$([ "$out" = '1|A|one,two#1||#6' ] && echo 1 || echo 0)" "($out)"

# 6. Admin boxes and saves (writes intercepted).
out=$(weval 'wp_set_current_user( 1 ); ob_start(); cbv_inc_render_trip_box( get_post( 181 ) ); cbv_inc_render_provider_box( get_post( cbv_lp_provider_id( 181 ) ) ); cbv_lp_render_route_box( get_post( 181 ) ); echo ob_get_clean();')
check "admin: the trip box (program, note, forecast, tags, extra lines), the provider box (lines + source + date, link) and the route note field" "$(( $(has "$out" 'name="cbv_inc_program"') * $(has "$out" 'name="cbv_inc_tags\[0\]\[items\]"') * $(has "$out" 'name="cbv_inc_leave\[0\]\[source\]"') * $(has "$out" 'name="cbv_inc_leave_link"') * $(has "$out" 'name="cbv_lp_route_note"') ))"
out=$(weval '$GLOBALS["w"] = array(); add_filter( "update_post_metadata", function ( $c, $id, $k, $v ) { $GLOBALS["w"][ $k ] = $v; return true; }, 1, 4 ); add_filter( "add_post_metadata", function ( $c, $id, $k, $v ) { $GLOBALS["w"][ $k ] = $v; return true; }, 1, 4 ); wp_set_current_user( 1 ); $_POST = wp_slash( array( "cbv_inc_trip_nonce" => wp_create_nonce( "cbv_inc_trip_save" ), "cbv_inc_program" => "A\nB", "cbv_inc_program_note" => "N", "cbv_inc_forecast" => "F", "cbv_inc_tags" => array( array( "category" => "C", "title" => "T", "items" => "i1\ni2" ) ), "cbv_inc_leave_trip" => "L1" ) ); do_action( "save_post_cb_trip", 181, get_post( 181 ), true ); echo implode( ",", array_keys( $GLOBALS["w"] ) ) . "|" . json_encode( $GLOBALS["w"]["cbv_lp_pack_tags"] ?? null );')
check "trip box save writes only its 5 fields (intercepted)" "$(( $(has "$out" '^cbv_lp_program,cbv_lp_program_note,cbv_lp_pack_forecast,cbv_lp_pack_tags,cbv_lp_leave_home_trip|') * $(has "$out" '"items":\["i1","i2"\]') ))" "($out)"

# 7. Social image: the trip's own wins.
h=$(render /trip/annual-family-and-friends/ 0)
ogs=$(grep -o '<meta property="og:image" content="[^"]*"' <<< "$h")
check "share image: exactly one og:image, the hero poster (the trip's Yoast Social image)" "$(( $([ "$(wc -l <<< "$ogs")" -eq 1 ] && echo 1 || echo 0) * $(has "$ogs" 'terminal-v-hero-poster-ship.jpg') ))" "($ogs)"

# 8. CSS.
check "trip-landing.css: included cards, luggage tags, Leave at home, route note; styles.css: members' versions" "$(( $(grep -c 'body.cbv-lp .cbv-inc-tag-hole' /tmp/cbv_t/trip-landing-new.css | awk '{print ($1>0)?1:0}') * $(grep -c 'body.cbv-lp .cbv-inc-leave {' /tmp/cbv_t/trip-landing-new.css | awk '{print ($1>0)?1:0}') * $(grep -c 'body.cbv-lp .cbv-lp-route-note' /tmp/cbv_t/trip-landing-new.css | awk '{print ($1>0)?1:0}') * $(grep -c '.cbv-inc-member .cbv-inc-tag ' /tmp/cbv_t/styles.css | awk '{print ($1>0)?1:0}') ))"

echo
echo "$n checks, $fail failures"
