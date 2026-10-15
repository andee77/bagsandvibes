#!/bin/bash
# Step A of the member hub plan (owner, 2026-10-15): "Dashboard" renamed "My Passport" (page at /my-passport/, the
# old /dashboard/ redirects), and the member menu: Feed | My Passport | Navigation | Account (My Account, Traveler
# info, Log out); "My Profile" gone; Change Password / Privacy / Delete Account only as My Account tabs.
# Renders real pages server-side with render_page.php (read only) against the temp copy (agreement v2 accepted in
# memory so renders aren't sent to /reaccept-terms/).
#   bash /tmp/cbv_t/test_my_passport.sh   (expects /tmp/cbv_t/define_v2.php, render_page.php, styles-new.css)
# Set CBV_VERBOSE=1 to print every passing check.
cd ~/www/bagsandvibes.com/public_html || exit 1
n=0; fail=0
check() { n=$((n+1)); if [ "$2" = "1" ]; then [ -n "$CBV_VERBOSE" ] && echo "  pass $n: $1"; else fail=$((fail+1)); echo "FAIL: $1 $3"; fi; }
DEF=${CBV_DEF:-/tmp/cbv_t/define_v2.php}
render() { CBV_PATH="$1" CBV_USER="$2" wp --require=$DEF eval-file /tmp/cbv_t/render_page.php 2>/dev/null; }
weval() { wp --require=$DEF eval "$1" 2>/dev/null; }
has() { grep -c -- "$2" <<< "$1" | awk '{print ($1>0)?1:0}'; }
hasnt() { grep -c -- "$2" <<< "$1" | awk '{print ($1==0)?1:0}'; }
nav() { python3 -c 'import sys,re; h=sys.stdin.read(); m=re.search(r"<nav class=\"primary-nav\".*?</nav>", h, re.S); print(m.group(0) if m else "")' <<< "$1"; }

check "running against the temp mu-plugins copy" "$(grep -c '/tmp/cbv_mu' /tmp/cbv_t/define.php | awk '{print ($1>0)?1:0}')"
HOME_PATH=$(weval 'echo get_page_by_path( "my-passport" ) ? "/my-passport/" : "/dashboard/";')   # the page is renamed at deploy

# 1. The menu, as an admin (1), a full member (107) and a trip guest (105).
for u in 1 107 105; do
  h=$(render "$HOME_PATH" $u); m=$(nav "$h")
  order=$(python3 -c 'import sys; h=sys.stdin.read(); a=[h.find(x) for x in (">Feed<","My Passport</a>",">Navigation<",">Account<")]; print(1 if -1 not in a and a==sorted(a) else 0)' <<< "$m")
  check "user $u: menu order Feed | My Passport | Navigation | Account" "$order"
  check "user $u: My Passport links to /my-passport/ with the passport icon" "$(( $(has "$m" 'class="nav-passport" href="https://bagsandvibes.com/my-passport/"><svg class="nav-passport-icon"') * $(hasnt "$m" '/dashboard/') * $(hasnt "$m" '>Dashboard<') ))"
  check "user $u: Account = My Account, Traveler info, Log out (in that order)" "$(python3 -c 'import sys; h=sys.stdin.read(); a=[h.find(x) for x in ("account/general/\">My Account<","account/travel-profile/\">Traveler info<","logout/\">Log out<")]; print(1 if -1 not in a and a==sorted(a) else 0)' <<< "$m")"
  check "user $u: no My Profile / Following / Find Members, no Change Password / Privacy / Delete Account, no top-level Logout" "$(( $(hasnt "$m" 'My Profile') * $(hasnt "$m" '>Following<') * $(hasnt "$m" 'Find Members') * $(hasnt "$m" 'Change Password') * $(hasnt "$m" '>Privacy<') * $(hasnt "$m" 'Delete Account') * $(hasnt "$m" '>Logout<') ))"
  check "user $u: the six Gates still in Navigation" "$(( $(has "$m" 'Planned Vacations') * $(has "$m" 'Photo Gallery') * $(has "$m" '>Payments<') * $(has "$m" 'Discussion Boards') * $(has "$m" 'Vacation Requests') * $(has "$m" 'Travel Rules') ))"
done

# 2. The My Passport page itself.
h=$(render "$HOME_PATH" 1)
check "page: label \"My Passport\" (with the icon), logo link to /my-passport/ named \"My Passport\"" "$(( $(has "$h" '<p class="dashboard-hero-pagelabel"><svg class="nav-passport-icon"') * $(has "$h" 'My Passport</p>') * $(has "$h" 'href="https://bagsandvibes.com/my-passport/" class="brand brand-logo-only" aria-label="My Passport"') * $(hasnt "$h" '>Dashboard<') ))"
h=$(render "$HOME_PATH" 107)   # the reminder shows for members who haven't filled in their traveler info
check "page: reminder says \"Add your traveler info\" / \"Add traveler info\" (no \"Travel Profile\")" "$(( $(has "$h" 'Add your traveler info so we have what we need') * $(has "$h" '>Add traveler info</a>') * $(hasnt "$h" 'Travel Profile') ))"

# 3. A Gate page's logo, the new trip page header (signed-in full member, not on the trip), the re-accept page.
h=$(render /gate-09-payments/ 107)
check "Gate page: logo goes to /my-passport/ (\"My Passport\")" "$(( $(has "$h" 'href="https://bagsandvibes.com/my-passport/" class="brand brand-logo-only" aria-label="My Passport"') * $(hasnt "$h" 'aria-label="Dashboard"') ))"
h=$(render /trip/annual-family-and-friends/ 107)
check "trip landing page (new design, signed in): header link \"My Passport\" to /my-passport/" "$(( $(has "$h" 'https://bagsandvibes.com/my-passport/') * $(has "$h" '>My Passport<') * $(hasnt "$h" '>Dashboard<') ))"
h=$(render /reaccept-terms/ 1)
check "re-accept page (already up to date): \"Go to My Passport\"" "$(( $(has "$h" 'my-passport/">Go to My Passport</a>') * $(hasnt "$h" 'Go to your dashboard') ))"

# 4. The address move, the account tab, sign-in landing default, SEO.
out=$(weval 'echo cbv_legacy_page_redirects()["dashboard"] ?? "";')
check "redirect map: dashboard -> my-passport" "$([ "$out" = 'my-passport' ] && echo 1 || echo 0)" "($out)"
out=$(weval 'echo get_page_by_path( "my-passport" ) ? cbv_legacy_redirect_target( "/dashboard/?x=1" ) : "page-not-renamed-yet";')
check "/dashboard/ redirects to /my-passport/ once the page is renamed (query kept)" "$( { [ "$out" = 'https://bagsandvibes.com/my-passport/?x=1' ] || [ "$out" = 'page-not-renamed-yet' ]; } && echo 1 || echo 0)" "($out)"
out=$(weval '$t = apply_filters( "um_account_page_default_tabs_hook", array() ); echo $t[250]["travel-profile"]["title"] ?? "";')
check "My Account tab renamed \"Traveler info\" (distinct from each trip's Travel Details form)" "$([ "$out" = 'Traveler info' ] && echo 1 || echo 0)" "($out)"
check "trip guest sign-in default (code): redirect to My Passport, not the profile" "$(( $(grep -c "'_um_after_login'          => 'redirect_url'" /tmp/cbv_mu/checkedbags-trip-invites.php | awk '{print ($1>0)?1:0}') * $(grep -c "home_url( '/my-passport/' )" /tmp/cbv_mu/checkedbags-trip-invites.php | awk '{print ($1>2)?1:0}') ))"
out=$(weval 'echo (int) in_array( "my-passport", cbv_login_gated_page_slugs(), true ) . (int) in_array( "dashboard", cbv_login_gated_page_slugs(), true );')
check "SEO: my-passport (and the old dashboard) kept out of search" "$([ "$out" = '11' ] && echo 1 || echo 0)" "($out)"
check "no \"/dashboard/\" link left in the member code" "$(grep -l "home_url( '/dashboard/' )" /tmp/cbv_mu/*.php /tmp/cbv_mu/checkedbags-landing/*.php 2>/dev/null | wc -l | awk '{print ($1==0)?1:0}')"

# 5. CSS.
check "styles.css: passport icon centred on the menu text line (same baseline as the other items) and on the page label" "$(( $(grep -c '.nav-passport-icon { display: inline-block; vertical-align: middle' /tmp/cbv_t/styles-new.css | awk '{print ($1>0)?1:0}') * $(grep -c '.dashboard-hero-pagelabel { display: inline-flex' /tmp/cbv_t/styles-new.css | awk '{print ($1>0)?1:0}') ))"

echo "$n checks, $fail failures"
