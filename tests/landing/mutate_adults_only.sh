#!/bin/bash
# Mutation tests for adults only (18+): break one behaviour in a throwaway copy, run the suite, expect failures.
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
  sed "s#'/tmp/cbv_mu' === WPMU_PLUGIN_DIR#'/tmp/cbv_mut' === WPMU_PLUGIN_DIR#" /tmp/cbv_t/test_adults_only.php > /tmp/cbv_t/test_mut.php
  res=$(wp --require=/tmp/cbv_t/define_mut.php eval-file /tmp/cbv_t/test_mut.php 2>&1 | tr -d '\r')
  echo "MUTANT: $name"
  echo "$res" | grep -E '^FAIL|checks,|Fatal|Parse' | cut -c1-150 | head -4
  echo
}
A=checkedbags-adults-only.php
F=checkedbags-lp-fees.php
D=checkedbags-proposal-pdf.php
I=checkedbags-trip-invites.php
run "trip choice ignored" $A "	if ( 'inherit' !== \$own ) {
		return 'yes' === \$own;
	}" "	if ( false ) {
		return 'yes' === \$own;
	}"
run "trip Yes/No inverted" $A "		return 'yes' === \$own;" "		return 'no' === \$own;"
run "provider flag ignored" $A "	return cbv_provider_is_adults_only( \$provider );
}" "	return false;
}"
run "provider flag always on" $A "	return \$provider_id && '1' === (string) get_post_meta( (int) \$provider_id, 'cbv_lp_adults_only', true );" "	return true;"
run "junk choice not cleaned" $A "	return in_array( \$value, array( 'yes', 'no' ), true ) ? \$value : 'inherit';" "	return (string) \$value;"
run "board note still says children" $F "&& ! ( function_exists( 'cbv_trip_is_adults_only' ) && cbv_trip_is_adults_only( \$trip_id ) ) ) {
		\$lines[] = 'Children pay half" ") {
		\$lines[] = 'Children pay half"
run "proposal still says children" $D "&& ! \$adults_only ) {" ") {"
run "intake still shows children" $I "				<?php if ( ! ( function_exists( 'cbv_trip_is_adults_only' ) && cbv_trip_is_adults_only( \$trip_id ) ) ) :" "				<?php if ( true ) :"
run "save without nonce check" $A "! wp_verify_nonce( \$_POST['cbv_adults_only_nonce'], 'cbv_adults_only_save' )" "false"
run "save without permission check" $A "|| ! current_user_can( 'edit_post', \$post_id ) ) {" ") {"
run "reply-to on every UM email" $A "	if ( 'rejected_email' !== \$template ) {" "	if ( '' === \$template ) {"
run "reply-to never added" $A "	\$headers[] = 'Reply-To: Checked Bags & Good Vibes <' . CBV_REJECTION_REPLY_TO . '>';" "	// none"
run "flag not cleared (leaks to the next email)" $A "	unset( \$GLOBALS['cbv_um_mail_template'] );" "	// kept"
run "duplicate Reply-To not removed" $A "	\$headers   = array_values( array_filter( (array) \$headers, function ( \$h ) { return 0 !== stripos( ltrim( (string) \$h ), 'reply-to:' ); } ) );
	\$headers[] = 'Reply-To: Checked Bags & Good Vibes <' . CBV_REJECTION_REPLY_TO . '>';" "	\$headers[] = 'Reply-To: Checked Bags & Good Vibes <' . CBV_REJECTION_REPLY_TO . '>';
	\$headers[] = 'Reply-To: Checked Bags & Good Vibes <' . CBV_REJECTION_REPLY_TO . '>';"
rm -rf /tmp/cbv_mut /tmp/cbv_t/define_mut.php /tmp/cbv_t/test_mut.php
