#!/bin/bash
# Design follow-ups (owner, 2026-10-14): Register checkbox names and links both documents, red * on required
# Register fields only, the public Master Traveler Agreement (Gate 12's text, without its members-only part),
# "Master Traveler Agreement" instead of "Membership Terms" (same stored version), the Gate 09 dashboard card,
# full-width Contact fields. Renders real pages server-side with render_page.php (read only) against the temp copy.
#   bash /tmp/cbv_t/test_design_followups.sh     (expects /tmp/cbv_t/define.php, render_page.php, styles.css)
# Optional: CBV_PAGES_LIVE=1 also checks the page-text edits (Privacy section 2, Gate 12 Payments) and the
# Master Traveler Agreement page itself, once they have been written.
# Set CBV_VERBOSE=1 to print every passing check.
cd ~/www/bagsandvibes.com/public_html || exit 1
n=0; fail=0
check() { n=$((n+1)); if [ "$2" = "1" ]; then [ -n "$CBV_VERBOSE" ] && echo "  pass $n: $1"; else fail=$((fail+1)); echo "FAIL: $1 $3"; fi; }
render() { CBV_PATH="$1" CBV_USER="$2" wp --require=/tmp/cbv_t/define.php eval-file /tmp/cbv_t/render_page.php 2>/dev/null; }
weval() { wp --require=/tmp/cbv_t/define.php eval "$1" 2>/dev/null; }
has() { grep -c -- "$2" <<< "$1" | awk '{print ($1>0)?1:0}'; }
hasnt() { grep -c -- "$2" <<< "$1" | awk '{print ($1==0)?1:0}'; }
NAME='Master Traveler Agreement, Code of Conduct &amp; Travel Policies'

check "running against the temp mu-plugins copy" "$(grep -c '/tmp/cbv_mu' /tmp/cbv_t/define.php | awk '{print ($1>0)?1:0}')"
V=$(weval 'echo (int) cbv_get_current_terms_version();')   # the published Master Traveler Agreement version (v2 since 2026-10-14)

# Register.
h=$(render /register/ 0)
check "Register: checkbox names both documents with the version" "$(has "$h" "I have read and agree to the <a href=\"https://bagsandvibes.com/terms-of-service/\" target=\"_blank\" rel=\"noopener\">Terms of Service")"
check "Register: the agreement link goes to /master-traveler-agreement/ (new tab) with its full name, then (v$V)" "$(( $(has "$h" "href=\"https://bagsandvibes.com/master-traveler-agreement/\" target=\"_blank\" rel=\"noopener\">$NAME") * $(has "$h" "(v$V)\.") ))"
check "Register: no \"Membership Terms\" wording, no separate \"Read the Terms of Service\" line, no empty box" "$(( $(hasnt "$h" 'Membership Terms') * $(hasnt "$h" 'Read the Terms of Service') * $(hasnt "$h" 'max-height:150px') ))"
req=$(grep -o 'class="um-req"' <<< "$h" | wc -l)
check "Register: red * on the required fields (username, first name, email, password, confirm = 5)" "$([ "$req" -eq 5 ] && echo 1 || echo 0)" "(found $req)"
check "Register: Last Name (optional) has no *" "$(grep -o '<label for="last_name-45">[^<]*<span class="um-req"' <<< "$h" | wc -l | awk '{print ($1==0)?1:0}')"
h=$(render /login/ 0)
check "Login: no * added (asterisks are for Register only)" "$(hasnt "$h" 'class="um-req"')"
out=$(weval 'UM()->form()->errors = null; do_action( "um_submit_form_errors_hook__registration", array() ); echo implode( "|", (array) UM()->form()->errors );')
check "Register: unticked box -> error names both documents" "$(has "$out" 'You must agree to the Terms of Service and the Master Traveler Agreement, Code of Conduct')"
out=$(weval 'echo (int) cbv_get_current_terms_version();')
check "the stored version is a whole number of at least 1 (v$V)" "$([ "$out" -ge 1 ] 2>/dev/null && echo 1 || echo 0)" "($out)"

# Public agreement (shortcode), signed out.
out=$(weval 'wp_set_current_user( 0 ); echo do_shortcode( "[cbv_master_traveler_agreement]" );')
check "agreement: shows Gate 12's text (Section 1 to Section 8 and the checklist), signed out" "$(( $(has "$out" 'Section 1: Code of Conduct') * $(has "$out" 'Section 8: Destination-Specific Rules Notice') * $(has "$out" 'Pre-Trip Emergency') ))"
check "agreement: never the members-only part ([cb_gate_rules], Rules for Your Trips, sign-in prompt)" "$(( $(hasnt "$out" 'cb_gate_rules') * $(hasnt "$out" 'Rules for Your Trips') * $(hasnt "$out" 'sign in</a> to view travel rules') ))"
check "agreement: \"Version $V\" right under the document heading" "$(has "$out" "Travel Policies</h2><p class=\"cbv-terms-version\">Version $V</p>")"
out=$(weval 'echo implode( ",", cbv_text_page_slugs() );')
check "the agreement page (slug master-traveler-agreement) uses the text-page design" "$(has "$out" 'master-traveler-agreement')"

# Re-accept screen and Gate 12 badge use the new name.
out=$(weval 'wp_set_current_user( 1 ); add_filter( "get_user_metadata", function ( $v, $u, $k ) { return "_accepted_terms_version" === $k ? array( 0 ) : $v; }, 10, 3 ); echo do_shortcode( "[cbv_reaccept_terms]" );')
check "re-accept screen: new name, link to the agreement page, no empty box" "$(( $(has "$out" "Updated $NAME") * $(has "$out" 'master-traveler-agreement/') * $(hasnt "$out" 'Membership Terms') * $(hasnt "$out" 'max-height:300px') ))"
h=$(render /gate-12-travel-rules/ 1)
check "Gate 12: badge says \"Master Traveler Agreement (v...) agreed on\" (or asks to re-accept), never \"Membership Terms\"" "$(( $(has "$h" 'Master Traveler Agreement (v[0-9]*) agreed on\|Your Master Traveler Agreement acceptance is out of date') * $(hasnt "$h" 'Membership Terms (v') ))"
out=$(weval 'echo implode( "|", cbv_build_trip_roster_export_data( 181 )["headers"] );')
check "roster export: \"Master Traveler Agreement Version / Accepted Date\" columns" "$(( $(has "$out" 'Master Traveler Agreement Version|Master Traveler Agreement Accepted Date') * $(hasnt "$out" 'Membership Terms') ))"

# Dashboard card.
h=$(render /dashboard/ 1)
check "dashboard: Gate 09 card says \"Track your CBGV Group Experience Fee payments.\"" "$(( $(has "$h" 'Track your CBGV Group Experience Fee payments.') * $(hasnt "$h" 'Track deposits, balances') ))"

# CSS.
css=/tmp/cbv_t/styles.css
check "styles.css: Contact Name/Email full width; Register * coral; agreement version line" "$(( $(grep -c 'wpforms-field-medium { max-width: 100% !important; width: 100% !important; }' "$css" | awk '{print ($1>0)?1:0}') * $(grep -c '.cbv-um-page .um .um-req { color: var(--coral)' "$css" | awk '{print ($1>0)?1:0}') * $(grep -c '.cbv-master-agreement .cbv-terms-version' "$css" | awk '{print ($1>0)?1:0}') ))"
unscoped=$(python3 - "$css" <<'PY'
import re, sys
s = open(sys.argv[1], encoding='utf-8').read().replace('\r\n', '\n')
i = s.find('Design follow-ups (owner, 2026-10-14)')
block = s[s.find('*/', i) + 2:] if i >= 0 else ''
end = block.find('\n/* ====')
block = re.sub(r'/\*.*?\*/', '', block[:end] if end >= 0 else block, flags=re.S)
bad = [p.strip() for sel in re.findall(r'([^{}]+)\{[^{}]*\}', block) for p in sel.split(',') if p.strip() and not p.strip().startswith(('.cbv-text-page ', '.cbv-um-page '))]
print(len(bad) if i >= 0 else 'none', '|', '; '.join(bad[:3]))
PY
)
check "styles.css: every follow-up rule is scoped to .cbv-text-page / .cbv-um-page" "$( [ "${unscoped%% *}" = "0" ] && echo 1 || echo 0)" "$unscoped"

if [ -n "$CBV_PAGES_LIVE" ]; then
  h=$(render /privacy-policy/ 0)
  check "Privacy section 2: new \"coordinate group arrangements\" line, old \"manage travel bookings\" gone" "$(( $(has "$h" 'Fulfill custom trip quotes and coordinate group arrangements with third-party vendors (e.g., cruise lines, resorts). Travel bookings are handled by your independent travel advisor.') * $(hasnt "$h" 'manage travel bookings') ))"
  h=$(render /gate-12-travel-rules/ 1)
  check "Gate 12 Payments: new wording, old \"forfeiture of your spot\" gone" "$(( $(has "$h" 'Cruise and travel payments are made directly to the cruise line or travel supplier') * $(hasnt "$h" 'forfeiture of your spot') ))"
  h=$(render /master-traveler-agreement/ 0)
  check "/master-traveler-agreement/: public 200 page, text-page design, the agreement text, noindex" "$(( $(hasnt "$h" 'REDIRECT') * $(has "$h" 'cbv-text-page') * $(has "$h" 'Section 8: Destination-Specific Rules Notice') * $(has "$h" 'noindex') ))"
fi

echo
echo "$n checks, $fail failures"
