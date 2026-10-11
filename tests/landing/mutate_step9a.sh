#!/bin/bash
# Mutation tests for Step 9a: break one behaviour in a throwaway copy, run the suite, expect failures.
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
  sed "s#'/tmp/cbv_mu' === WPMU_PLUGIN_DIR#'/tmp/cbv_mut' === WPMU_PLUGIN_DIR#" /tmp/cbv_t/test_step9a.php > /tmp/cbv_t/test_mut.php
  res=$(wp --require=/tmp/cbv_t/define_mut.php eval-file /tmp/cbv_t/test_mut.php 2>&1 | tr -d '\r')
  echo "MUTANT: $name"
  echo "$res" | grep -E '^FAIL|checks,|Fatal|Parse' | cut -c1-150 | head -4
  echo
}
N=checkedbags-price-notes.php
P=checkedbags-lp-priceboard.php
C=checkedbags-lp-core.php
L=checkedbags-trip-landing.php
G=checkedbags-gate07.php
T=checkedbags-gate10.php
run "pricing note not capped" $N "mb_substr( \$value, 0, CBV_PRICING_NOTE_MAX ) : substr" "mb_substr( \$value, 0, 6000 ) : substr"
run "pricing note keeps tags" $N "\$value = is_scalar( \$value ) ? sanitize_textarea_field( (string) \$value ) : '';" "\$value = is_scalar( \$value ) ? (string) \$value : '';"
run "card note not capped" $N "mb_substr( \$value, 0, CBV_CARD_NOTE_MAX ) : substr" "mb_substr( \$value, 0, 800 ) : substr"
run "pricing note not escaped" $N "\$html = function_exists( 'cbv_lp_inline' ) ? cbv_lp_inline( \$note ) : esc_html( \$note );" "\$html = \$note;"
run "pricing note loses line breaks" $N "nl2br( \$html, false )" "\$html"
run "card note not escaped" $N "'<p class=\"trip-card-note\">' . esc_html( \$note ) . '</p>'" "'<p class=\"trip-card-note\">' . \$note . '</p>'"
run "pricing note not on the current page" $L "			<?php echo function_exists( 'cbv_render_pricing_note' ) ? cbv_render_pricing_note( \$trip_id ) : '';" "			<?php echo '';"
run "card note not on Gate 07" $G "				<?php echo function_exists( 'cbv_render_card_note' ) ? cbv_render_card_note( \$trip->ID ) : '';" "				<?php echo '';"
run "Reserve block not stripped on the new design" $C "			\$legacy = cbv_lp_strip_legacy_cta( \$legacy );" "			// kept"
run "Reserve block stripped even with the board Off" $C "&& null !== cbv_lp_priceboard_data( \$trip_id ) ) {" ") {"
run "strip also removes the PDF button" $P "\$cta . '/a[contains(concat(\" \", normalize-space(@class), \" \"), \" btn-ticket \")]" "\$cta . '/a[contains(concat(\" \", normalize-space(@class), \" \"), \" btn \")]"
run "strip leaves the note" $P " | ' . \$cta . '/p[contains(concat(\" \", normalize-space(@class), \" \"), \" cbv-landing-cta-note \")]'" "'"
run "strip removes ticket buttons anywhere" $P "\$cta . '/a[contains(concat(\" \", normalize-space(@class), \" \"), \" btn-ticket \")] | '" "'//a[contains(concat(\" \", normalize-space(@class), \" \"), \" btn-ticket \")] | '"
run "empty block kept" $P "		if ( ! \$xp->query( './*', \$block )->length && \$block->parentNode ) {" "		if ( false ) {"
run "forum rule lets guests in" $T "	return \$is_forum_request && ! \$logged_in;" "	return false;"
run "forum rule blocks members" $T "	return \$is_forum_request && ! \$logged_in;" "	return \$is_forum_request;"
run "forum rule not hooked" $T "add_action( 'template_redirect', function () {
	if ( ! cb_gate10_guest_must_sign_in(" "add_action( 'template_redirect_x', function () {
	if ( ! cb_gate10_guest_must_sign_in("
run "save without nonce check" $N "! wp_verify_nonce( \$_POST['cbv_price_notes_nonce'], 'cbv_price_notes_save' )" "false"
run "save without permission check" $N "	if ( ! current_user_can( 'edit_post', \$post_id ) ) {
		return;
	}
	if ( isset( \$_POST['cb_pricing_note'] ) ) {" "	if ( false ) {
		return;
	}
	if ( isset( \$_POST['cb_pricing_note'] ) ) {"
rm -rf /tmp/cbv_mut /tmp/cbv_t/define_mut.php /tmp/cbv_t/test_mut.php
