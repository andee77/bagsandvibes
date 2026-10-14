#!/bin/bash
# "Publish a new version" button on Settings > Master Traveler Agreement (owner, 2026-10-14). Read only: the option
# write is intercepted (pre_update_option returns the old value), so nothing is saved.
#   bash /tmp/cbv_t/test_mta_publish.sh     (expects /tmp/cbv_t/define.php)
# Set CBV_VERBOSE=1 to print every passing check.
cd ~/www/bagsandvibes.com/public_html || exit 1
n=0; fail=0
check() { n=$((n+1)); if [ "$2" = "1" ]; then [ -n "$CBV_VERBOSE" ] && echo "  pass $n: $1"; else fail=$((fail+1)); echo "FAIL: $1 $3"; fi; }
weval() { wp --require=/tmp/cbv_t/define.php eval "$1" 2>/dev/null; }
has() { grep -c -- "$2" <<< "$1" | awk '{print ($1>0)?1:0}'; }
hasnt() { grep -c -- "$2" <<< "$1" | awk '{print ($1==0)?1:0}'; }
CAP='$GLOBALS["w"] = array(); add_filter( "pre_update_option_cbv_membership_terms", function ( $new, $old ) { $GLOBALS["w"][] = $new; return $old; }, 1, 2 ); wp_set_current_user( 1 );'

check "running against the temp mu-plugins copy" "$(grep -c '/tmp/cbv_mu' /tmp/cbv_t/define.php | awk '{print ($1>0)?1:0}')"
v=$(weval 'echo cbv_get_current_terms_version();')
next=$((v + 1))

out=$(weval "$CAP"' ob_start(); cbv_render_membership_terms_page(); echo ob_get_clean(); echo "|W=" . count( $GLOBALS["w"] );')
check "the screen shows \"Publish version $next\" with a confirm box" "$(( $(has "$out" ">Publish version $next</button>") * $(has "$out" 'name="cbv_publish_version_confirm"') ))"
check "just opening the screen saves nothing" "$(has "$out" '|W=0')"

out=$(weval "$CAP"' $_POST = array( "cbv_publish_version_nonce" => wp_create_nonce( "cbv_publish_terms_version" ) ); ob_start(); cbv_render_membership_terms_page(); $h = ob_get_clean(); echo ( false !== strpos( $h, "Not published" ) ? "refused" : "?" ) . "|W=" . count( $GLOBALS["w"] );')
check "without the confirm tick: refused, nothing saved" "$(has "$out" 'refused|W=0')" "($out)"

out=$(weval "$CAP"' $old = cbv_get_membership_terms(); $_POST = array( "cbv_publish_version_nonce" => wp_create_nonce( "cbv_publish_terms_version" ), "cbv_publish_version_confirm" => "1" ); ob_start(); cbv_render_membership_terms_page(); ob_get_clean(); $w = $GLOBALS["w"][0] ?? array(); echo count( $GLOBALS["w"] ) . "|" . ( $w["version"] ?? "" ) . "|" . ( ( $w["content"] ?? null ) === $old["content"] ? "same-content" : "content-changed" ) . "|" . ( ! empty( $w["updated"] ) ? "dated" : "no-date" );')
check "confirmed: one save, version $next, the box's content unchanged, dated" "$(has "$out" "^1|$next|same-content|dated")" "($out)"

out=$(weval "$CAP"' $_POST = array( "cbv_publish_version_nonce" => "bad", "cbv_publish_version_confirm" => "1" ); ob_start(); cbv_render_membership_terms_page(); ob_get_clean(); echo "W=" . count( $GLOBALS["w"] );')
check "a bad security token saves nothing" "$(has "$out" 'W=0')"

out=$(weval 'echo cbv_get_current_terms_version();')
check "the real stored version is untouched by this test (still v$v)" "$([ "$out" = "$v" ] && echo 1 || echo 0)"

echo
echo "$n checks, $fail failures"
