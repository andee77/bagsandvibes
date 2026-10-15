#!/bin/bash
# Step B of the member hub plan (owner, 2026-10-15): one switch (Settings > CBGV Member Features) turns the member
# social features off: profile walls, following, the members directory, profile pages, wall posts in the Feed.
# Off by default; nothing deleted; "Who's going" keeps names + photos without profile links. Read only: pages are
# rendered with render_page.php; REST / auto-follow checks run with every database write blocked.
#   bash /tmp/cbv_t/test_member_social.sh   (expects /tmp/cbv_t/define_v2.php, social_on.php, render_page.php)
# Set CBV_VERBOSE=1 to print every passing check.
cd ~/www/bagsandvibes.com/public_html || exit 1
n=0; fail=0
check() { n=$((n+1)); if [ "$2" = "1" ]; then [ -n "$CBV_VERBOSE" ] && echo "  pass $n: $1"; else fail=$((fail+1)); echo "FAIL: $1 $3"; fi; }
DEF=${CBV_DEF:-/tmp/cbv_t/define_v2.php}
render()    { CBV_PATH="$1" CBV_USER="$2" wp --require=$DEF eval-file /tmp/cbv_t/render_page.php 2>/dev/null; }
render_on() { CBV_SO_BASE=$DEF CBV_PATH="$1" CBV_USER="$2" wp --require=/tmp/cbv_t/social_on.php eval-file /tmp/cbv_t/render_page.php 2>/dev/null; }
weval()     { wp --require=$DEF eval "$1" 2>/dev/null; }
weval_on()  { CBV_SO_BASE=$DEF wp --require=/tmp/cbv_t/social_on.php eval "$1" 2>/dev/null; }
has()   { grep -c -- "$2" <<< "$1" | awk '{print ($1>0)?1:0}'; }
hasnt() { grep -c -- "$2" <<< "$1" | awk '{print ($1==0)?1:0}'; }
redir() { grep -o 'REDIRECT [^ <]*' <<< "$1" | head -1 | cut -d' ' -f2; }
roster() { python3 -c 'import sys,re; h=sys.stdin.read(); m=re.search(r"<ul class=\"trip-roster-list\">(.*?)</ul>", h, re.S); print(m.group(1) if m else "")' <<< "$1"; }
cnt()   { grep -o -- "$2" <<< "$1" | wc -l; }
NOWRITE='foreach ( array( "update_user_metadata", "add_user_metadata", "update_post_metadata", "add_post_metadata", "delete_user_metadata" ) as $f ) { add_filter( $f, function ( $c, $id = 0, $key = "" ) { $GLOBALS["cbv_w"][] = 1; $GLOBALS["cbv_keys"][] = $key; return false; }, 0, 3 ); } add_filter( "wp_insert_post_empty_content", function () { $GLOBALS["cbv_w"][] = 1; return true; }, 0 ); add_filter( "pre_delete_post", function () { $GLOBALS["cbv_w"][] = 1; return false; }, 0 );'

check "running against the temp mu-plugins copy" "$(grep -c '/tmp/cbv_mu' /tmp/cbv_t/define.php | awk '{print ($1>0)?1:0}')"
OWNER=$(weval 'echo (int) get_post_meta( 356, "_cb_wall_owner_user_id", true );')      # Laurie W's wall
NICE=$(weval 'echo get_userdata( 105 )->user_nicename;')
WALLURL=$(weval 'echo wp_make_link_relative( get_permalink( 356 ) );')
ROSTER=$(weval 'echo count( cb_trip_get_roster( 181 ) );')

# --- OFF (the default) ---
out=$(weval 'echo (int) cbv_member_social_enabled();')
check "off by default (setting not saved yet = off)" "$( [ "$out" = '0' ] && echo 1 || echo 0)" "($out)"
out=$(weval "$NOWRITE"' wp_set_current_user( 107 ); $r = rest_do_request( new WP_REST_Request( "POST", "/cb/v1/members/1/follow" ) ); $d = rest_do_request( new WP_REST_Request( "DELETE", "/cb/v1/members/1/follow" ) ); $p = new WP_REST_Request( "POST", "/cb/v1/members/1/wall-posts" ); $p->set_param( "title", "x" ); $w = rest_do_request( $p ); echo $r->get_status() . "|" . $d->get_status() . "|" . $w->get_status() . "|" . ( $r->as_error() ? $r->as_error()->get_error_code() : "" ) . "|" . count( $GLOBALS["cbv_w"] ?? array() );')
check "off: follow, unfollow and wall-post requests refused (403 cbv_social_off), nothing written" "$( [ "$out" = '403|403|403|cbv_social_off|0' ] && echo 1 || echo 0)" "($out)"
h=$(render /following/ 107)
check "off: Following page shows the switched-off notice" "$(( $(has "$h" 'This part of the site is switched off for now') * $(has "$h" 'my-passport/">go to My Passport') ))"
h=$(render /following/ 0)
check "off: signed-out visitors still get the sign-in prompt, not the notice" "$(hasnt "$h" 'switched off for now')"
h=$(render /members/ 107)
check "off: Find Members page shows the notice (no member cards, no Follow buttons)" "$(( $(has "$h" 'switched off for now') * $(hasnt "$h" 'cb-follow-btn') ))"
check "off: /user/{name}/ (profile) goes to My Account" "$( [ "$(redir "$(render /user/$NICE/ 107)")" = 'https://bagsandvibes.com/account/general/' ] && echo 1 || echo 0)"
check "off: /members/{name}/ goes to My Account" "$( [ "$(redir "$(render /members/$NICE/ 107)")" = 'https://bagsandvibes.com/account/general/' ] && echo 1 || echo 0)"
check "off: bbPress profile /forums/users/{name}/ goes to My Account (members)" "$( [ "$(redir "$(render /forums/users/$NICE/ 107)")" = 'https://bagsandvibes.com/account/general/' ] && echo 1 || echo 0)"
h=$(render "$WALLURL" 105)
check "off: a wall's forum page is closed to members (sent to My Passport, or not found)" "$( { [ "$(redir "$h")" = 'https://bagsandvibes.com/my-passport/' ] || [ "$(has "$h" '<title>Page not found')" = 1 ]; } && echo 1 || echo 0)"
out=$(weval 'echo (int) user_can( '"$OWNER"', "read_forum", 356 ) . (int) user_can( 97, "read_forum", 356 ) . (int) user_can( 1, "read_forum", 356 );')
check "off: walls readable by moderators only (not the owner, not a trip-mate)" "$( [ "$out" = '001' ] && echo 1 || echo 0)" "(owner/trip-mate/admin: $out)"
out=$(weval 'wp_set_current_user( '"$OWNER"' ); echo (int) in_array( 356, array_map( "intval", bbp_get_excluded_forum_ids() ), true );')
check "off: the owner's own wall stays hidden from forum queries" "$out"
h=$(render /member-feed/ 1)
check "off: Feed has no \"Members Post\" wall section; wording without \"posts your friends\"" "$(( $(hasnt "$h" 'Members Post') * $(hasnt "$h" 'posts your friends') * $(has "$h" 'travel tips and the latest from your trip boards') ))"
ro=$(roster "$(render /trip/annual-family-and-friends/ 1)")
check "off: Who's going = a photo + name for each of the $ROSTER, no profile links" "$(( $( [ "$(cnt "$ro" '<li class="trip-roster-member"><img ')" -eq "$ROSTER" ] && echo 1 || echo 0) * $( [ "$(cnt "$ro" 'trip-roster-name')" -eq "$ROSTER" ] && echo 1 || echo 0) * $(hasnt "$ro" '<a href') ))"
out=$(weval "$NOWRITE"' do_action( "um_registration_complete", 999999, array() ); echo (int) in_array( "_cb_following", $GLOBALS["cbv_keys"] ?? array(), true );')
check "off: new members are not auto-followed (no follow list written)" "$( [ "$out" = '0' ] && echo 1 || echo 0)" "($out)"
out=$(weval 'echo function_exists( "cbv_render_member_features_page" ) ? "page|" : "nopage|"; echo cbv_member_social_sanitize( "1" ) . cbv_member_social_sanitize( "" ) . cbv_member_social_sanitize( "0" );')
check "Settings > CBGV Member Features: page and setting (saves \"1\" or \"0\")" "$( [ "$out" = 'page|100' ] && echo 1 || echo 0)" "($out)"
out=$(weval 'global $wpdb; echo (int) get_user_meta( 1, "_cb_wall_forum_id", true ) . "|" . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = \"topic\" AND post_parent = 226 AND post_status = \"publish\"" );')
check "off: nothing deleted (Andee's wall and its 3 posts still stored)" "$( [ "$out" = '226|3' ] && echo 1 || echo 0)" "($out)"

# --- ON (in memory) ---
check "on: the switch reads on" "$(weval_on 'echo (int) cbv_member_social_enabled();')"
out=$(weval_on 'echo var_export( apply_filters( "rest_pre_dispatch", null, rest_get_server(), new WP_REST_Request( "POST", "/cb/v1/members/1/follow" ) ), true );')
check "on: follow requests are not blocked" "$( [ "$out" = 'NULL' ] && echo 1 || echo 0)" "($out)"
h=$(render_on /following/ 107)
check "on: Following page works as before (no notice)" "$(hasnt "$h" 'switched off for now')"
check "on: /user/{name}/ is not sent to My Account" "$( [ "$(redir "$(render_on /user/$NICE/ 107)")" != 'https://bagsandvibes.com/account/general/' ] && echo 1 || echo 0)"
out=$(weval_on 'echo (int) user_can( '"$OWNER"', "read_forum", 356 ) . (int) user_can( 97, "read_forum", 356 );')
check "on: the owner and trip-mates can read the wall again" "$( [ "$out" = '11' ] && echo 1 || echo 0)" "($out)"
ro=$(roster "$(render_on /trip/annual-family-and-friends/ 1)")
check "on: Who's going names link to profiles again (photos kept)" "$(( $( [ "$(cnt "$ro" '<a href')" -eq "$ROSTER" ] && echo 1 || echo 0) * $( [ "$(cnt "$ro" '<li class="trip-roster-member"><img ')" -eq "$ROSTER" ] && echo 1 || echo 0) ))"

echo "$n checks, $fail failures"
