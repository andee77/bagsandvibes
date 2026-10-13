#!/bin/bash
# Runs travel_details_browser_test.js in a real (headless) browser against a rendered trip page that shows the
# member's Travel Details form, and prints "N checks, N failures".
# Usage: bash run_travel_details_browser_test.sh <rendered-page.html> [browser.exe]
#   The page comes from tests/landing/render_page.php on the server (as a roster member, on a trip whose
#   optional sections are all offered as checkboxes, e.g. the $1 test trip), copied here.
# Nothing is sent anywhere: the test replaces fetch() with a recorder.
set -e
page="$1"
browser="${2:-/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe}"
here="$(cd "$(dirname "$0")" && pwd)"
work="$(mktemp -d)"
python -I - "$page" "$here/travel_details_browser_test.js" "$work/page.html" <<'PY'
import sys, re
html = open(sys.argv[1], encoding='utf-8', errors='replace').read()
js = open(sys.argv[2], encoding='utf-8').read()
# Run after the page's own scripts (the form's handlers are inline, at the end of the form).
html = html.replace('</body>', '<script>window.addEventListener("load", function () {\n' + js + '\n});</script>\n</body>', 1)
open(sys.argv[3], 'w', encoding='utf-8').write(html)
PY
url="file:///$(cygpath -m "$work/page.html" 2>/dev/null || echo "$work/page.html")"
timeout 90 "$browser" --headless=new --disable-gpu --user-data-dir="$work/profile" --virtual-time-budget=8000 --dump-dom "$url" > "$work/dom.html" 2>/dev/null || true
python -I - "$work/dom.html" <<'PY'
import sys, json, re, html as h
dom = open(sys.argv[1], encoding='utf-8', errors='replace').read()
tag = '<pre id="cbv-test-result">'
i = dom.rfind(tag)  # the last one is the result (the test script's own comment mentions the tag)
m = dom[i + len(tag):dom.find('</pre>', i)] if i >= 0 else None
if not m:
    print('FAIL: the test did not run (no result on the page)')
    print('\n1 checks, 1 failures'); sys.exit(0)
data = json.loads(h.unescape(m))
fails = 0
for c in data['checks']:
    if not c['ok']:
        fails += 1
        print('FAIL:', c['label'], c['detail'])
    else:
        print('  pass:', c['label'])
if data.get('fatal'):
    fails += 1
    print('FAIL: script error:', data['fatal'][:300])
print('\n%d checks, %d failures' % (len(data['checks']) + (1 if data.get('fatal') else 0), fails))
PY
rm -rf "$work"
