#!/bin/bash
# Mutation tests for Step 9a follow-up: break one behaviour in a throwaway copy, run the suite, expect failures.
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
  sed "s#'/tmp/cbv_mu' === WPMU_PLUGIN_DIR#'/tmp/cbv_mut' === WPMU_PLUGIN_DIR#" /tmp/cbv_t/test_step9a_login.php > /tmp/cbv_t/test_mut.php
  res=$(wp --require=/tmp/cbv_t/define_mut.php eval-file /tmp/cbv_t/test_mut.php 2>&1 | tr -d '\r')
  echo "MUTANT: $name"
  echo "$res" | grep -E '^FAIL|checks,|Fatal|Parse' | cut -c1-150 | head -4
  echo
}
V=checkedbags-nav.php
T=checkedbags-gate10.php
run "helper ignores the return page" $V "	return '' !== (string) \$redirect ? add_query_arg( 'redirect_to', rawurlencode( (string) \$redirect ), \$base ) : \$base;" "	return \$base;"
run "helper does not encode the return page" $V "add_query_arg( 'redirect_to', rawurlencode( (string) \$redirect ), \$base )" "\$base . '?redirect_to=' . \$redirect"
run "helper always wp-login.php" $V "	if ( '' === \$base ) {
		return wp_login_url( \$redirect );" "	if ( true ) {
		return wp_login_url( \$redirect );"
run "a gate link back on wp-login.php" checkedbags-gate09.php "esc_url( cbv_login_url( get_permalink() ) )" "esc_url( wp_login_url( get_permalink() ) )"
run "Gate 10 link back on wp-login.php" $T "esc_url( cbv_login_url( get_permalink() ) ) . '\">sign in</a> to see discussion boards" "esc_url( wp_login_url( get_permalink() ) ) . '\">sign in</a> to see discussion boards"
run "forum redirect back on wp-login.php" $T "	wp_safe_redirect( cbv_login_url( \$here ) );" "	wp_safe_redirect( wp_login_url( \$here ) );"
run "feed check ignores topics and replies" $T "array( bbp_get_forum_post_type(), bbp_get_topic_post_type(), bbp_get_reply_post_type() ) );
}

add_filter( 'bbp_request'" "array( bbp_get_forum_post_type() ) );
}

add_filter( 'bbp_request'"
run "feed check treats every feed as a forum feed" $T "	if ( ! is_array( \$query_vars ) || ! isset( \$query_vars['feed'] ) || ! isset( \$query_vars['post_type'] ) || ! function_exists( 'bbp_get_forum_post_type' ) ) {" "	if ( ! is_array( \$query_vars ) ) {"
run "feed check runs after bbPress" $T "	return \$query_vars;
}, 5 );" "	return \$query_vars;
}, 20 );"
run "feed check not hooked" $T "add_filter( 'bbp_request', function ( \$query_vars ) {" "add_filter( 'bbp_request_x', function ( \$query_vars ) {"
rm -rf /tmp/cbv_mut /tmp/cbv_t/define_mut.php /tmp/cbv_t/test_mut.php
