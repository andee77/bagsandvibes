#!/bin/bash
# Mutation tests for wall privacy: break one behaviour in a throwaway copy, run the suite, expect failures.
cd ~/www/bagsandvibes.com/public_html || exit 1
run() {
  name="$1"; file="$2"; old="$3"; new="$4"
  rm -rf /tmp/cbv_mut && cp -r /tmp/cbv_mu /tmp/cbv_mut
  FILE="$file" OLD="$old" NEW="$new" python3 - <<'PY'
import os
p = "/tmp/cbv_mut/" + os.environ["FILE"]
s = open(p, "rb").read().decode("utf-8")
crlf = "\r\n" in s
if crlf:
    s = s.replace("\r\n", "\n")
old, new = os.environ["OLD"], os.environ["NEW"]
assert s.count(old) == 1, ("anchor not found or ambiguous", old, s.count(old))
s = s.replace(old, new)
if crlf:
    s = s.replace("\n", "\r\n")
open(p, "wb").write(s.encode("utf-8"))
PY
  [ $? -ne 0 ] && { echo "MUTANT $name: COULD NOT APPLY"; return; }
  printf '<?php\ndefine( "WPMU_PLUGIN_DIR", "/tmp/cbv_mut" );\ndefine( "WP_DISABLE_FATAL_ERROR_HANDLER", true );\nini_set( "error_log", "/tmp/cbv_t/test_errors.log" );\n' > /tmp/cbv_t/define_mut.php
  sed "s#'/tmp/cbv_mu' === WPMU_PLUGIN_DIR#'/tmp/cbv_mut' === WPMU_PLUGIN_DIR#" /tmp/cbv_t/test_wall_privacy.php > /tmp/cbv_t/test_mut.php
  res=$(wp --require=/tmp/cbv_t/define_mut.php eval-file /tmp/cbv_t/test_mut.php 2>&1 | tr -d '\r')
  echo "MUTANT: $name"
  echo "$res" | grep -E '^FAIL|checks,|Fatal|Parse' | cut -c1-150 | head -4
  echo
}
W=checkedbags-member-wall.php
run "walls listed in /forums/ again" $W "add_filter( 'bbp_after_has_forums_parse_args', function ( \$args ) {" "add_filter( 'bbp_after_has_forums_parse_args_x', function ( \$args ) {"
run "wall posts listed in /topics/ again" $W "add_filter( 'bbp_after_has_topics_parse_args', function ( \$args ) {" "add_filter( 'bbp_after_has_topics_parse_args_x', function ( \$args ) {"
run "walls found by forum search again" $W "add_filter( 'bbp_after_has_search_results_parse_args', function ( \$args ) {" "add_filter( 'bbp_after_has_search_results_parse_args_x', function ( \$args ) {"
run "search hides wall forums but not their posts" $W "cb_wall_merge_ids( \$args['post__not_in'] ?? array(), array_merge( \$walls, \$content ) );" "cb_wall_merge_ids( \$args['post__not_in'] ?? array(), \$walls );"
# (No mutant for the single-wall exception: WP_Query ignores post_parent__not_in whenever post_parent is set,
#  so removing the exception changes nothing; the check in the suite still guards the behaviour.)
run "existing exclusions dropped" $W "		\$args['post__not_in'] = cb_wall_merge_ids( \$args['post__not_in'] ?? array(), \$walls );" "		\$args['post__not_in'] = array();"
run "wall page not redirected" $W "	return \$owner && function_exists( 'cb_member_profile_url' ) ? (string) cb_member_profile_url( \$owner ) : '';" "	return '';"
run "every forum page redirected" $W "	return \$owner && function_exists( 'cb_member_profile_url' ) ? (string) cb_member_profile_url( \$owner ) : '';" "	return (string) home_url( '/' );"
run "walls never found" $W "			'_cb_wall_owner_user_id'
		) ) );" "			'_cb_no_such_key'
		) ) );"
rm -rf /tmp/cbv_mut /tmp/cbv_t/define_mut.php /tmp/cbv_t/test_mut.php
