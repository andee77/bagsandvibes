#!/bin/bash
# Forum design tests (2026-10-13): every bbPress page uses the CBGV gate shell (header, menu, footer, brand
# stylesheet and fonts), carries the "GATE 10" ribbon and the .cbv-forums body class, and does NOT load Kadence's
# bbPress stylesheet; non-forum pages are unchanged; signed-out visitors are still sent to /login/; the new CSS is
# scoped to .cbv-forums. Renders real pages server-side with render_page.php (read only) against the temp copy.
#   bash /tmp/cbv_t/test_forum_design.sh        (expects /tmp/cbv_t/define.php and /tmp/cbv_t/render_page.php)
# Set CBV_VERBOSE=1 to print every passing check.
cd ~/www/bagsandvibes.com/public_html || exit 1
n=0; fail=0
check() { n=$((n+1)); if [ "$2" = "1" ]; then [ -n "$CBV_VERBOSE" ] && echo "  pass $n: $1"; else fail=$((fail+1)); echo "FAIL: $1 $3"; fi; }
render() { CBV_PATH="$1" CBV_USER="$2" wp --require=/tmp/cbv_t/define.php eval-file /tmp/cbv_t/render_page.php 2>/dev/null; }
has() { grep -c -- "$2" <<< "$1" | awk '{print ($1>0)?1:0}'; }
hasnt() { grep -c -- "$2" <<< "$1" | awk '{print ($1==0)?1:0}'; }

check "running against the temp mu-plugins copy" "$(grep -c '/tmp/cbv_mu' /tmp/cbv_t/define.php | awk '{print ($1>0)?1:0}')"
M=$(wp --require=/tmp/cbv_t/define.php eval 'foreach ( cb_trip_get_roster( 320 ) as $u ) { if ( ! user_can( $u, "moderate" ) ) { echo $u; break; } }' 2>/dev/null)
check "a regular member to render as" "$([ -n "$M" ] && echo 1 || echo 0)"

for p in /forums/ /forums/forum/lounge/ /forums/forum/helmets-hearts-high-seas-trip-board/ /topics/ "/forums/search/?bbp_search=trip" /forums/view/popular/; do
  h=$(render "$p" "$M")
  check "$p: CBGV gate shell (brand header + footer)" "$(( $(has "$h" 'class="site-header is-solid"') * $(has "$h" 'class="site-footer"') ))"
  check "$p: no Kadence header" "$(hasnt "$h" 'id="masthead"')"
  check "$p: brand stylesheet and fonts (Fraunces, Work Sans, Space Mono)" "$(( $(has "$h" 'checkedbags/css/styles.css') * $(has "$h" 'family=Fraunces') * $(has "$h" 'Work+Sans') * $(has "$h" 'Space+Mono') ))"
  check "$p: Kadence's bbPress stylesheet not loaded" "$(hasnt "$h" 'kadence-bbpress-css')"
  check "$p: body class cbv-forums and the GATE 10 ribbon" "$(( $(has "$h" 'cbv-forums') * $(has "$h" 'gate-ribbon-number">GATE 10<') ))"
  check "$p: the member menu is there" "$(has "$h" 'primary-nav')"
done
h=$(render /forums/ "$M")
check "/forums/: ribbon title is Discussion Boards" "$(has "$h" 'gate-ribbon-title">Discussion Boards<')"
h=$(render /forums/forum/lounge/ "$M")
check "a forum page: ribbon title is the forum's name" "$(has "$h" 'gate-ribbon-title">Lounge<')"

h=$(render /topics/ "$M")
check "empty states in CBGV words: \"No conversations yet. Be the first to start one!\", no \"Oh, bother!\"" "$(( $(has "$h" 'No conversations yet. Be the first to start one!') * $(hasnt "$h" 'Oh, bother') ))"
check "styles.css hides the stray dash under No Topics" "$(grep -c 'bbp-topic-freshness-author:not(:has(a, img)) { display: none; }' /tmp/cbv_t/styles.css | awk '{print ($1>0)?1:0}')"

h=$(render /forums/forum/lounge/ "$M")
check "empty forum: one notice only (no \"This forum is empty.\"), no Tags field on the new-topic form" "$(( $(hasnt "$h" 'This forum is empty') * $(grep -c 'class="bbp-template-notice' <<< "$h" | awk '{print ($1==1)?1:0}') * $(hasnt "$h" 'bbp_topic_tags') ))"
# This topic is a wall post: with the member social features off (default since 2026-10-15) only moderators can
# open it, so the reply-form check runs with them ON in memory (tests/landing/social_on.php).
h=$(CBV_SO_BASE=$(grep -o "/tmp/cbv_t/define[a-z0-9_]*\.php" <<< "$(declare -f render)" | head -1) CBV_PATH=/forums/topic/good-morning-travel-family/ CBV_USER="$M" wp --require=/tmp/cbv_t/social_on.php eval-file /tmp/cbv_t/render_page.php 2>/dev/null)
check "topic page: no stray | before Subscribe, no Tags field on the reply form" "$(( $(hasnt "$h" 'subscription-toggle">&nbsp;|') * $(has "$h" 'id="subscription-toggle"') * $(hasnt "$h" 'bbp_topic_tags') ))"
check "styles.css: search post numbers hidden, coral reply links, brand toolbar buttons, inline summary avatar" "$(( $(grep -c 'bbp-search-results .bbp-meta a\[class\$="-permalink"\] { display: none; }' /tmp/cbv_t/styles.css | awk '{print ($1>0)?1:0}') * $(grep -c 'a.bbp-topic-reply-link' /tmp/cbv_t/styles.css | awk '{print ($1>0)?1:0}') * $(grep -c 'quicktags-toolbar input.ed_button' /tmp/cbv_t/styles.css | awk '{print ($1>0)?1:0}') * $(grep -c 'bbp-template-notice img.avatar' /tmp/cbv_t/styles.css | awk '{print ($1>0)?1:0}') ))"

# Non-forum pages: unchanged.
h=$(render /gate-10-discussion-boards/ "$M")
check "Gate 10 page: still its own ribbon (GATE 10 / Discussion Boards), no cbv-forums class" "$(( $(has "$h" 'gate-ribbon-number">GATE 10<') * $(hasnt "$h" 'cbv-forums') ))"
h=$(render /gate-09-payments/ "$M")
check "Gate 09 page: GATE 09 ribbon, title Payments, no cbv-forums class" "$(( $(has "$h" 'gate-ribbon-number">GATE 09<') * $(has "$h" 'gate-ribbon-title">Payments<') * $(hasnt "$h" 'cbv-forums') ))"
h=$(render / 0)
check "home page: not touched (no gate shell, no cbv-forums)" "$(( $(hasnt "$h" 'cbv-forums') * $(hasnt "$h" 'gate-ribbon') ))"
h=$(render /trip/annual-family-and-friends/ 0)
check "public trip page: no cbv-forums class" "$(hasnt "$h" 'cbv-forums')"

# Signed out: still sent to /login/ (Step 9a follow-up).
h=$(render /forums/ 0)
check "signed out: /forums/ redirects to /login/" "$(has "$h" 'REDIRECT https://bagsandvibes.com/login/?redirect_to=')"

# CSS: every new rule is scoped to .cbv-forums.
css=/tmp/cbv_t/styles.css
if [ -f "$css" ]; then
  unscoped=$(python3 - "$css" <<'PY'
import re, sys
s = open(sys.argv[1], encoding='utf-8').read().replace('\r\n', '\n')
i = s.find('Forums (bbPress) -- CBGV member-area skin')
block = s[s.find('*/', i) + 2:] if i >= 0 else ''
end = block.find('\n/* ====')  # the forum block ends where the next section starts
block = block[:end] if end >= 0 else block
block = re.sub(r'/\*.*?\*/', '', block, flags=re.S)
bad = []
for sel in re.findall(r'([^{}]+)\{[^{}]*\}', block):
    sel = sel.strip()
    if not sel or sel.startswith('@media'):
        continue
    parts, depth, cur = [], 0, ''
    for ch in sel:  # split on top-level commas only (not inside :has(...) / :not(...))
        depth += (ch == '(') - (ch == ')')
        if ch == ',' and depth == 0:
            parts.append(cur); cur = ''
        else:
            cur += ch
    parts.append(cur)
    for part in parts:
        part = part.strip().replace('@media (max-width: 640px) {', '').strip()
        if part and not part.startswith('.cbv-forums'):
            bad.append(part)
print(len(bad), '|', '; '.join(bad[:3]))
PY
)
  check "styles.css: the forum skin exists and every rule is scoped to .cbv-forums" "$( [ "${unscoped%% *}" = "0" ] && grep -q 'Forums (bbPress) -- CBGV member-area skin' "$css" && echo 1 || echo 0)" "$unscoped"
else
  check "styles.css copy present for the CSS check" 0
fi

echo
echo "$n checks, $fail failures"
