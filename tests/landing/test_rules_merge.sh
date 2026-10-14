#!/bin/bash
# Per-trip rules merge (owner, 2026-10-14): the Trip Agreement is each trip's one rules text (versioned; a change
# asks members to re-accept). Gate 12's "Rules for Your Trips" and the trip page show it with its version and the
# member's acceptance; the "Trip-specific rules addendum" box is retired (no field, not saved, not shown).
# Renders real pages server-side with render_page.php (read only) against the temp copy.
#   bash /tmp/cbv_t/test_rules_merge.sh     (expects /tmp/cbv_t/define.php, render_page.php, styles.css)
# Set CBV_VERBOSE=1 to print every passing check.
cd ~/www/bagsandvibes.com/public_html || exit 1
n=0; fail=0
check() { n=$((n+1)); if [ "$2" = "1" ]; then [ -n "$CBV_VERBOSE" ] && echo "  pass $n: $1"; else fail=$((fail+1)); echo "FAIL: $1 $3"; fi; }
render() { CBV_PATH="$1" CBV_USER="$2" wp --require=/tmp/cbv_t/define.php eval-file /tmp/cbv_t/render_page.php 2>/dev/null; }
weval() { wp --require=/tmp/cbv_t/define.php eval "$1" 2>/dev/null; }
has() { grep -c -- "$2" <<< "$1" | awk '{print ($1>0)?1:0}'; }
hasnt() { grep -c -- "$2" <<< "$1" | awk '{print ($1==0)?1:0}'; }
OLD_RULE='Trip must be paid in full 120 days prior to sailing.'
V4='I will pay all cruise costs directly to Virgin Voyages by the final payment date'

check "running against the temp mu-plugins copy" "$(grep -c '/tmp/cbv_mu' /tmp/cbv_t/define.php | awk '{print ($1>0)?1:0}')"
v=$(weval 'echo cbv_get_trip_agreement_version( 181 );')
check "trip 181's agreement is still version 4 (nothing re-versioned)" "$([ "$v" = "4" ] && echo 1 || echo 0)" "($v)"

# Gate 12, a member on 181 who accepted an older version (105 accepted v3).
h=$(render /gate-12-travel-rules/ 105)
check "Gate 12 (member): 181 card shows the Trip Agreement text with \"Version 4\"" "$(( $(has "$h" 'Trip Agreement &middot; Version 4') * $(has "$h" "$V4") ))"
check "Gate 12 (member, accepted v3): \"Not accepted yet\" with a link to the trip page" "$(has "$h" 'Not accepted yet. <a href="https://bagsandvibes.com/trip/annual-family-and-friends/">Review and accept it on the trip page</a>')"
check "Gate 12: the retired rule text is gone, and so is \"No additional rules\"" "$(( $(hasnt "$h" "$OLD_RULE") * $(hasnt "$h" 'No additional rules') ))"
check "Gate 12: a trip with no agreement text says \"No trip agreement for this trip.\" (Helmets)" "$(has "$h" 'No trip agreement for this trip.')"
min=$(weval 'echo (int) get_post_meta( 181, "cb_min_group_size", true ) ?: 4;')
check "Gate 12: minimum group size line still there (181: $min)" "$(has "$h" "Minimum group size: $min (")"
h=$(render /gate-12-travel-rules/ 1)
check "Gate 12 (admin, accepted v4): \"You accepted version 4 on Oct 14, 2026.\"" "$(has "$h" 'You accepted version 4 on Oct 14, 2026.')"

# Trip page (member view of 181 as a traveler who accepted v4).
h=$(render /trip/annual-family-and-friends/ 1)
check "trip page: \"Rules for this trip\" shows the Trip Agreement v4 and the acceptance" "$(( $(has "$h" 'Trip Agreement &middot; Version 4') * $(has "$h" "$V4") * $(has "$h" 'You accepted version 4') ))"
check "trip page: the retired rule text is not shown" "$(hasnt "$h" "$OLD_RULE")"
h=$(render /trip/annual-family-and-friends/ 105)
check "trip page (member who must re-accept v4): still the agreement prompt (gate unchanged)" "$(( $(has "$h" 'trip-agreement-gate') * $(has "$h" "this trip&#8217;s agreement (v4)") ))"

# Admin: the box is retired.
out=$(weval 'wp_set_current_user( 1 ); $p = get_post( 181 ); ob_start(); foreach ( $GLOBALS["wp_meta_boxes"] ?? array() as $x ) {} do_action( "add_meta_boxes", "cb_trip", $p ); global $wp_meta_boxes; foreach ( $wp_meta_boxes["cb_trip"] as $ctx ) { foreach ( $ctx as $pri ) { foreach ( $pri as $b ) { if ( $b && is_callable( $b["callback"] ) ) { call_user_func( $b["callback"], $p, $b ); } } } } echo ob_get_clean();')
check "trip edit screen: no \"Trip-specific rules addendum\" field" "$(( $(hasnt "$out" 'name="cb_rules_addendum"') * $(hasnt "$out" 'Trip-specific rules addendum') ))"
check "trip edit screen: points to the Trip Agreement box, which says it is the one rules text" "$(( $(has "$out" 'write them in the <strong>Trip Agreement</strong> box') * $(has "$out" 'This is the trip&#8217;s one rules text') ))"
# Static check (running the real save handlers against a live trip is not safe): the save list no longer has the field.
src=$(sed -n "/\$text_fields = array(/,/);/p" /tmp/cbv_mu/checkedbags-trips.php)
check "trip save: cb_rules_addendum is no longer in the saved fields, so it is never written" "$(( $(has "$src" "'cb_quote_notes'") * $(hasnt "$src" 'cb_rules_addendum') ))"
out=$(weval 'echo get_post_meta( 181, "cb_rules_addendum", true );')
check "the old stored rule on 181 is kept in the database (not deleted), just unused" "$(has "$out" "$OLD_RULE")"

# CSS.
check "styles.css: agreement block styled for the dark Gate 12 card and the light trip page" "$(( $(grep -c '.rules-trip-card .cbv-trip-agreement-version' /tmp/cbv_t/styles.css | awk '{print ($1>0)?1:0}') * $(grep -c '.trip-detail-rules .cbv-trip-agreement-version' /tmp/cbv_t/styles.css | awk '{print ($1>0)?1:0}') ))"

echo
echo "$n checks, $fail failures"
