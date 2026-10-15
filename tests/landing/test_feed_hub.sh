#!/bin/bash
# Step C of the member hub plan (owner, 2026-10-15): the Feed becomes the CBGV hub -- trip updates (that trip's
# members only), The Good Vibe Check podcast from its RSS feed (played on the page, four "Listen on" links from a
# settings field, never the Spotify for Creators address), Trip Tips, the member's boards (Lounge + their own trips'
# boards only, never another trip's, never walls), a small open-trips strip; destinations gone. Read only: pages are
# rendered with render_page.php; content variations are held in memory; the save check intercepts writes.
#   bash /tmp/cbv_t/test_feed_hub.sh   (expects /tmp/cbv_t/define_v2.php, social_on.php, render_page.php, styles-new.css)
# Set CBV_VERBOSE=1 to print every passing check.
cd ~/www/bagsandvibes.com/public_html || exit 1
n=0; fail=0
check() { n=$((n+1)); if [ "$2" = "1" ]; then [ -n "$CBV_VERBOSE" ] && echo "  pass $n: $1"; else fail=$((fail+1)); echo "FAIL: $1 $3"; fi; }
DEF=${CBV_DEF:-/tmp/cbv_t/define_v2.php}
render() { CBV_PATH="$1" CBV_USER="$2" wp --require=$DEF eval-file /tmp/cbv_t/render_page.php 2>/dev/null; }
weval()  { wp --require=$DEF eval "$1" 2>/dev/null; }
has()   { grep -c -- "$2" <<< "$1" | awk '{print ($1>0)?1:0}'; }
hasnt() { grep -c -- "$2" <<< "$1" | awk '{print ($1==0)?1:0}'; }
cnt()   { grep -o -- "$2" <<< "$1" | wc -l; }
# Trip 181 updates held in memory: one current, one expired, one ending today.
UPD='$u = array( array( "text" => "Group photo at 7:15 PM sharp", "until" => "" ), array( "text" => "OLD NOTICE", "until" => "2020-01-01" ), array( "text" => "Ends today", "until" => wp_date( "Y-m-d" ) ) ); add_filter( "get_post_metadata", function ( $v, $id, $k ) use ( $u ) { return ( 181 === (int) $id && "cbv_trip_updates" === $k ) ? array( $u ) : $v; }, 0, 3 );'

# Podcast checks keep the feed cache in memory and never save the "last good copy", so nothing is written.
NOCACHE='wp_using_ext_object_cache( true ); add_filter( "pre_update_option_cbv_podcast_last_good", function ( $v, $old ) { return $old; }, 10, 2 );'
check "running against the temp mu-plugins copy" "$(grep -c '/tmp/cbv_mu' /tmp/cbv_t/define.php | awk '{print ($1>0)?1:0}')"

# 1. Podcast settings and data.
out=$(weval '$s = cbv_podcast_settings(); echo $s["rss"] . "|" . count( array_filter( array_intersect_key( $s, cbv_podcast_platforms() ) ) );')
check "settings default to the owner's feed and four links" "$( [ "$out" = 'https://anchor.fm/s/117a25e28/podcast/rss|4' ] && echo 1 || echo 0)" "($out)"
out=$(weval '$c = cbv_podcast_sanitize( array( "rss" => " https://example.com/feed ", "apple" => "javascript:alert(1)", "spotify" => "", "junk" => "x" ) ); echo wp_json_encode( $c );')
check "settings cleaned: http(s) only, blanks allowed, unknown keys dropped" "$( [ "$out" = '{"rss":"https:\/\/example.com\/feed","apple":"","spotify":"","amazon":"","iheart":""}' ] && echo 1 || echo 0)" "($out)"
out=$(weval "$NOCACHE"' $d = cbv_podcast_data(); echo $d ? $d["show"] . "|" . $d["episodes"][ count( $d["episodes"] ) - 1 ]["title"] . "|" . ( false !== strpos( $d["episodes"][0]["audio"], "anchor.fm" ) ? "audio" : "noaudio" ) . "|" . ( $d["image"] ? "art" : "noart" ) : "none";')
check "the RSS feed reads: The Good Vibe Check, first episode \"Cleared for Boarding\", playable audio, artwork" "$( [ "$out" = 'The Good Vibe Check|Cleared for Boarding|audio|art' ] && echo 1 || echo 0)" "($out)"
out=$(weval 'echo cbv_podcast_meta_line( array( "date" => strtotime( "2026-09-27 12:00 UTC" ), "duration" => "00:09:25" ) );')
check "episode line: \"Sep 27, 2026 · 9 min\"" "$( [ "$out" = 'Sep 27, 2026 · 9 min' ] && echo 1 || echo 0)" "($out)"
out=$(weval "$NOCACHE"' add_filter( "pre_http_request", function () { return new WP_Error( "down", "feed down" ); } ); add_filter( "pre_option_cbv_podcast_last_good", function () { return array( "show" => "Saved copy", "image" => "", "episodes" => array( array( "title" => "Kept episode", "date" => 0, "desc" => "", "audio" => "", "image" => "", "duration" => "" ) ) ); } ); $d = cbv_podcast_data(); echo $d ? $d["episodes"][0]["title"] : "none";')
check "feed down: the last good copy is shown" "$( [ "$out" = 'Kept episode' ] && echo 1 || echo 0)" "($out)"
out=$(weval "$NOCACHE"' add_filter( "pre_option_cbv_podcast", function () { return array( "rss" => "" ); } ); echo cbv_podcast_data() ? "shown" : "hidden";')
check "no feed address: the podcast section hides" "$( [ "$out" = 'hidden' ] && echo 1 || echo 0)" "($out)"

# 2. The Feed as a member of trip 181 (97), with in-memory trip updates.
h=$(CBV_PATH=/member-feed/ CBV_USER=97 wp --require=$DEF eval-file /tmp/cbv_t/render_page.php 2>/dev/null)
check "order: podcast, Trip Tips, Your boards, open trips" "$(python3 -c 'import sys; h=sys.stdin.read(); a=[h.find(x) for x in ("hub-pod-title\">","id=\"hub-tips-title\"","id=\"hub-boards-title\"","id=\"hub-open-title\"")]; print(1 if -1 not in a and a==sorted(a) else 0)' <<< "$h")"
check "podcast card: title, date line, play button (loads only when pressed), 4 Listen-on links in new tabs" "$(( $(has "$h" 'hub-pod-title">Cleared for Boarding<') * $(has "$h" 'hub-pod-meta">Sep 27, 2026 · 9 min<') * $(has "$h" '<audio class="hub-pod-audio" controls preload="none"') * $( [ "$(cnt "$h" 'class="hub-pod-link" href="https://[^"]*" target="_blank" rel="noopener">Listen on')" -eq 4 ] && echo 1 || echo 0) ))"
check "never the Spotify for Creators (admin) addresses" "$(( $(hasnt "$h" 'podcasters.spotify.com') * $(hasnt "$h" 'creators.spotify.com') ))"
check "Trip Tips as luggage tags (\"Tip 01\", Check your Passport)" "$(( $(has "$h" 'hub-tip-label">Tip 01<') * $(has "$h" 'hub-tip-title">Check your Passport<') ))"
check "boards for a member of 181 only: the Lounge and Annual Family and Friends, not Helmets, no walls" "$(( $(has "$h" 'hub-board-name" role="cell">Lounge<') * $(has "$h" 'hub-board-name" role="cell">Annual Family and Friends<') * $(hasnt "$h" 'hub-board-name" role="cell">HELMETS') * $(hasnt "$h" 'Wall<') ))"
check "quiet boards invite the first post" "$(has "$h" 'Be the first to post')"
check "destinations gone; no wall posts while social features are off" "$(( $(hasnt "$h" 'Destination Inspiration') * $(hasnt "$h" 'Members Post') ))"
out=$(weval 'echo implode( "|", array_map( "get_the_title", cb_feed_upcoming_trips() ) );')
check "open trips strip, soonest first (Helmets Feb 2027, then Annual Family and Friends Oct 2027)" "$(python3 -c 'import sys; s=sys.stdin.read(); print(1 if s.find("HELMETS")!=-1 and s.find("Annual Family")!=-1 and s.find("HELMETS")<s.find("Annual Family") else 0)' <<< "$out")" "($out)"
out=$(weval "$NOCACHE $UPD"' echo cbv_render_feed_hub( 97 );')
check "trip updates: a member of 181 sees the current ones (not the expired one) as gate notices" "$(( $(has "$out" 'hub-notice-text">Group photo at 7:15 PM sharp<') * $(has "$out" 'Ends today') * $(hasnt "$out" 'OLD NOTICE') * $(has "$out" 'Gate notice · <a href') ))"
out=$(weval "$NOCACHE $UPD"' echo cbv_render_feed_hub( 107 );')
check "trip updates: a member not on 181 sees none" "$(hasnt "$out" 'Group photo at 7:15 PM sharp')"

# 3. Boards per member.
b() { weval 'echo implode( "|", wp_list_pluck( cbv_hub_boards( '"$1"' ), "forum" ) );'; }
L=$(weval 'echo (int) get_option( "cb_lounge_forum_id" );'); B181=$(weval 'echo (int) get_post_meta( 181, "cb_forum_id", true );'); B320=$(weval 'echo (int) get_post_meta( 320, "cb_forum_id", true );')
sorted() { tr '|' '\n' <<< "$1" | sort -n | tr '\n' '|' | sed 's/|$//'; }
check "member on both trips (105): Lounge + both trip boards" "$( [ "$(sorted "$(b 105)")" = "$(sorted "$L|$B181|$B320")" ] && echo 1 || echo 0)"
check "member on no trip (107): the Lounge only" "$( [ "$(b 107)" = "$L" ] && echo 1 || echo 0)"
check "admin: Lounge + every trip board, never a wall" "$(( $( [ "$(sorted "$(b 1)")" = "$(sorted "$L|$B181|$B320")" ] && echo 1 || echo 0) ))"
out=$(weval '$r = cbv_hub_boards( 1 ); $ok = 1; for ( $i = 1; $i < count( $r ); $i++ ) { if ( array( $r[ $i - 1 ]["recent"], $r[ $i - 1 ]["last"] ) < array( $r[ $i ]["recent"], $r[ $i ]["last"] ) ) { $ok = 0; } } echo $ok;')
check "boards ordered by posts in the last 30 days, then last activity" "$out"

# 4. Trip updates box: cleaning, admin box, save writes only its own field.
out=$(weval '$c = cbv_trip_updates_sanitize( array( array( "text" => "  Hi <b>there</b> ", "until" => "2027-01-02" ), array( "text" => "", "until" => "2027-01-01" ), array( "text" => "x", "until" => "bad" ), "junk", array( "text" => str_repeat( "a", 400 ) ), array( "text" => "4" ), array( "text" => "5" ), array( "text" => "6" ) ) ); echo count( $c ) . "|" . $c[0]["text"] . "|" . $c[0]["until"] . "|" . $c[1]["until"] . "|" . mb_strlen( $c[2]["text"] );')
check "updates cleaned: tags stripped, empty rows dropped, bad dates blanked, 300 characters max, 5 at most" "$( [ "$out" = '5|Hi there|2027-01-02||300' ] && echo 1 || echo 0)" "($out)"
out=$(weval '$w = array(); add_filter( "update_post_metadata", function ( $c, $id, $k ) use ( &$w ) { $w[] = $k; return true; }, 0, 3 ); add_filter( "add_post_metadata", function ( $c, $id, $k ) use ( &$w ) { $w[] = $k; return true; }, 0, 3 ); wp_set_current_user( 1 ); $_POST = array( "cbv_trip_updates_nonce" => wp_create_nonce( "cbv_trip_updates_save" ), "cbv_trip_updates" => array( array( "text" => "Hello", "until" => "" ) ) ); do_action( "save_post_cb_trip", 181, get_post( 181 ), true ); echo implode( ",", array_unique( $w ) );')
check "trip updates box saves only its own field (write intercepted)" "$( [ "$out" = 'cbv_trip_updates' ] && echo 1 || echo 0)" "($out)"
out=$(weval 'ob_start(); cbv_render_trip_updates_box( get_post( 181 ) ); $h = ob_get_clean(); echo substr_count( $h, "<textarea name=\"cbv_trip_updates[" ) . "|" . substr_count( $h, "type=\"date\"" );')
check "admin box: 5 update rows, each with a \"show until\" date" "$( [ "$out" = '5|5' ] && echo 1 || echo 0)" "($out)"
out=$(weval 'ob_start(); do_action( "cbv_member_features_fields" ); $h = ob_get_clean(); echo substr_count( $h, "name=\"cbv_podcast[" );')
check "Settings > CBGV Member Features: feed + 4 Listen-on fields" "$( [ "$out" = '5' ] && echo 1 || echo 0)" "($out)"

# 5. CSS.
check "styles.css: hub notices, podcast card, luggage-tag tips, departures board, open-trip chips" "$(( $(grep -c '.hub-notice {' /tmp/cbv_t/styles-new.css | awk '{print ($1>0)?1:0}') * $(grep -c '.hub-pod {' /tmp/cbv_t/styles-new.css | awk '{print ($1>0)?1:0}') * $(grep -c '.hub-tip-hole' /tmp/cbv_t/styles-new.css | awk '{print ($1>0)?1:0}') * $(grep -c '.hub-board-head' /tmp/cbv_t/styles-new.css | awk '{print ($1>0)?1:0}') * $(grep -c '.hub-open-chip' /tmp/cbv_t/styles-new.css | awk '{print ($1>0)?1:0}') ))"

echo "$n checks, $fail failures"
