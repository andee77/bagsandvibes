#!/bin/bash
# Text pages + Ultimate Member form pages design tests (2026-10-14, design plan Group A + Group B part 1):
# Privacy, Terms and Contact use the CBGV gate shell (header, footer, ribbon with the page title, no gate number)
# with the .cbv-text-page body class instead of the home page's landing template; Login, Register and Password Reset
# carry .cbv-um-page and keep their working forms; Register no longer prints an empty Membership Terms box (it
# still prints the box when the terms have text); the home page and other pages are unchanged; the new CSS is
# scoped to .cbv-text-page / .cbv-um-page. Renders real pages server-side with render_page.php (read only) against
# the temp copy.
#   bash /tmp/cbv_t/test_text_um_pages.sh        (expects /tmp/cbv_t/define.php, render_page.php and styles.css)
# Set CBV_VERBOSE=1 to print every passing check.
cd ~/www/bagsandvibes.com/public_html || exit 1
n=0; fail=0
check() { n=$((n+1)); if [ "$2" = "1" ]; then [ -n "$CBV_VERBOSE" ] && echo "  pass $n: $1"; else fail=$((fail+1)); echo "FAIL: $1 $3"; fi; }
render() { CBV_PATH="$1" CBV_USER="$2" wp --require=/tmp/cbv_t/define.php eval-file /tmp/cbv_t/render_page.php 2>/dev/null; }
has() { grep -c -- "$2" <<< "$1" | awk '{print ($1>0)?1:0}'; }
hasnt() { grep -c -- "$2" <<< "$1" | awk '{print ($1==0)?1:0}'; }
body() { grep -o '<body[^>]*class="[^"]*"' <<< "$1" | head -1; }

check "running against the temp mu-plugins copy" "$(grep -c '/tmp/cbv_mu' /tmp/cbv_t/define.php | awk '{print ($1>0)?1:0}')"
M=$(wp --require=/tmp/cbv_t/define.php eval 'foreach ( cb_trip_get_roster( 320 ) as $u ) { if ( ! user_can( $u, "moderate" ) ) { echo $u; break; } }' 2>/dev/null)
check "a regular member to render as" "$([ -n "$M" ] && echo 1 || echo 0)"

# Group A: Privacy, Terms, Contact -- signed out and as a member.
for u in 0 "$M"; do
  for row in "/privacy-policy/|Privacy Policy|Last updated:" "/terms-of-service/|Terms of Service|Last updated:" "/contact/|Contact|wpforms-form"; do
    IFS='|' read -r p title marker <<< "$row"
    h=$(render "$p" "$u"); b=$(body "$h")
    check "$p (user $u): CBGV gate shell (brand header + footer), not the landing template" "$(( $(has "$h" 'class="site-header') * $(has "$h" 'class="site-footer"') * $(has "$b" 'checkedbags-gate') * $(hasnt "$b" 'checkedbags-landing') ))" "$b"
    check "$p (user $u): body class cbv-text-page (not cbv-um-page)" "$(( $(has "$b" 'cbv-text-page') * $(hasnt "$b" 'cbv-um-page') ))"
    check "$p (user $u): ribbon shows \"$title\" and no gate number" "$(( $(has "$h" "gate-ribbon-title\">$title<") * $(hasnt "$h" 'gate-ribbon-number') ))"
    check "$p (user $u): page text inside the text column" "$(( $(has "$h" 'class="gate-page-content"') * $(has "$h" "$marker") ))"
    check "$p (user $u): brand stylesheet and fonts, no Kadence header" "$(( $(has "$h" 'checkedbags/css/styles.css') * $(has "$h" 'family=Fraunces') * $(hasnt "$h" 'id="masthead"') ))"
    check "$p (user $u): still a 200 page (no redirect)" "$(hasnt "$h" 'REDIRECT ')"
  done
done
h=$(render /contact/ 0)
check "/contact/: the WPForms contact form is there with its Submit button" "$(( $(has "$h" 'class="wpforms-submit') * $(has "$h" 'wpforms-container') ))"

# Group B part 1: Login, Register, Password Reset (signed out).
for row in "/login/|Login|um-login" "/register/|Register|um-register" "/password-reset/|Password Reset|um-password"; do
  IFS='|' read -r p title form <<< "$row"
  h=$(render "$p" 0); b=$(body "$h")
  check "$p: gate shell, body class cbv-um-page (not cbv-text-page)" "$(( $(has "$b" 'checkedbags-gate') * $(has "$b" 'cbv-um-page') * $(hasnt "$b" 'cbv-text-page') ))" "$b"
  check "$p: ribbon \"$title\"" "$(has "$h" "gate-ribbon-title\">$title<")"
  check "$p: the UM form is there ($form) with its submit button" "$(( $(has "$h" "class=\"um $form") * $(has "$h" 'type="submit"') ))"
  check "$p: still a 200 page (no redirect)" "$(hasnt "$h" 'REDIRECT ')"
done
h=$(render /login/ 0)
check "/login/: username, password, keep-signed-in and forgot-password link" "$(( $(has "$h" 'name="username-46"') * $(has "$h" 'name="user_password-46"') * $(has "$h" 'um-field-checkbox') * $(has "$h" 'um-link-alt') ))"
h=$(render /register/ 0)
check "/register/: the agreement checkbox and the Terms of Service link are there" "$(( $(has "$h" 'name="cbv_accept_terms"') * $(has "$h" 'terms-of-service/" target="_blank" rel="noopener">Terms of Service') ))"
check "/register/: no empty Membership Terms box (the terms have no text yet)" "$(hasnt "$h" 'max-height:150px')"
out=$(wp --require=/tmp/cbv_t/define.php eval 'add_filter( "pre_option_cbv_membership_terms", function () { return array( "version" => 1, "content" => "Test terms text" ); } ); ob_start(); do_action( "um_after_register_fields", array(), 45 ); echo ob_get_clean();' 2>/dev/null)
check "/register/: the terms box still shows when the Membership Terms have text" "$(( $(has "$out" 'max-height:150px') * $(has "$out" 'Test terms text') ))"
out=$(wp --require=/tmp/cbv_t/define.php eval 'add_filter( "pre_option_cbv_membership_terms", function () { return array( "version" => 1, "content" => "  <p> </p> " ); } ); ob_start(); do_action( "um_after_register_fields", array(), 45 ); echo ob_get_clean();' 2>/dev/null)
check "/register/: terms that are only spaces or empty tags count as empty (no box)" "$(( $(hasnt "$out" 'max-height:150px') * $(has "$out" 'cbv_accept_terms') ))"

# Unchanged pages.
h=$(render / 0); b=$(body "$h")
check "home: still the landing design (no gate shell, no new classes)" "$(( $(has "$b" 'checkedbags-landing') * $(hasnt "$b" 'checkedbags-gate') * $(hasnt "$b" 'cbv-text-page') * $(hasnt "$h" 'gate-ribbon') ))" "$b"
h=$(render /payment-disclaimer/ "$M"); b=$(body "$h")
check "Payment Disclaimer: not touched (no new classes)" "$(( $(hasnt "$b" 'cbv-text-page') * $(hasnt "$b" 'cbv-um-page') ))"
h=$(render /account/ "$M"); b=$(body "$h")
check "Account page: not in this step (no cbv-um-page)" "$(hasnt "$b" 'cbv-um-page')"
h=$(render /gate-09-payments/ "$M"); b=$(body "$h")
check "Gate 09: GATE 09 ribbon, no new classes" "$(( $(has "$h" 'gate-ribbon-number">GATE 09<') * $(hasnt "$b" 'cbv-text-page') * $(hasnt "$b" 'cbv-um-page') ))"
h=$(render /forums/ "$M"); b=$(body "$h")
check "Forums: still cbv-forums with GATE 10, no new classes" "$(( $(has "$b" 'cbv-forums') * $(hasnt "$b" 'cbv-text-page') ))"

# CSS: the new block exists and every rule is scoped to .cbv-text-page / .cbv-um-page.
css=/tmp/cbv_t/styles.css
if [ -f "$css" ]; then
  unscoped=$(python3 - "$css" <<'PY'
import re, sys
s = open(sys.argv[1], encoding='utf-8').read().replace('\r\n', '\n')
i = s.find('Text pages (Privacy, Terms, Contact) and Ultimate Member form pages')
block = s[s.find('*/', i) + 2:] if i >= 0 else ''
end = block.find('\n/* ====')
block = block[:end] if end >= 0 else block
block = re.sub(r'/\*.*?\*/', '', block, flags=re.S).replace('@media (max-width: 640px) {', '')
bad, rules = [], 0
for sel in re.findall(r'([^{}]+)\{[^{}]*\}', block):
    for part in sel.split(','):
        part = part.strip()
        if not part:
            continue
        rules += 1
        if not (part.startswith('.cbv-text-page ') or part.startswith('.cbv-um-page ')):
            bad.append(part)
print(len(bad) if rules else 'none', '|', '; '.join(bad[:3]))
PY
)
  check "styles.css: the new block exists and every rule is scoped to .cbv-text-page / .cbv-um-page" "$( [ "${unscoped%% *}" = "0" ] && echo 1 || echo 0)" "$unscoped"
  check "styles.css: UM buttons uppercase over UM's !important; duplicate page heading kept for screen readers only" "$(( $(grep -c 'text-transform: uppercase !important' "$css" | awk '{print ($1>0)?1:0}') * $(grep -c '.cbv-text-page .gate-page-content > h2:first-child {' "$css" | awk '{print ($1>0)?1:0}') ))"
  # Trip pages + admin bar (owner, 2026-10-14).
  check "styles.css: trip-page ghost buttons (\"Discuss this trip\") get an ink outline, scoped to single trip pages" "$(grep -c '^body.single-cb_trip .trip-detail-section .btn-ghost {' "$css" | awk '{print ($1>0)?1:0}')"
  check "styles.css: header below the admin bar (32px; 46px at <= 782px), phone menu too, bar kept fixed on phones" "$(( $(grep -c 'body.admin-bar .site-header { top: 32px; }' "$css" | awk '{print ($1>0)?1:0}') * $(grep -c 'body.admin-bar .site-header { top: 46px; }' "$css" | awk '{print ($1>0)?1:0}') * $(grep -c 'body.admin-bar .primary-nav { top: calc(var(--header-h) + 46px); }' "$css" | awk '{print ($1>0)?1:0}') * $(grep -c 'body.admin-bar #wpadminbar { position: fixed; }' "$css" | awk '{print ($1>0)?1:0}') ))"
else
  check "styles.css copy present for the CSS check" 0
fi
h=$(render /trip/helmets-hearts-high-seas-2/ 1); b=$(body "$h")
check "Helmets trip page (admin): single-cb_trip body class and the \"Discuss this trip\" ghost button inside a trip-detail-section" "$(( $(has "$b" 'single-cb_trip') * $(has "$h" 'trip-detail-section"><a class="btn btn-ghost"') ))"
check "renderer wrote no posts while rendering" "$(hasnt "$h" 'RENDER WROTE A POST')"

echo
echo "$n checks, $fail failures"
