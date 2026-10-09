#!/bin/bash
# Mutation tests for Step 7: break one behaviour in a throwaway copy, run the suite, expect failures.
cd ~/www/bagsandvibes.com/public_html || exit 1
run() {
  name="$1"; file="$2"; old="$3"; new="$4"
  rm -rf /tmp/cbv_mut && cp -r /tmp/cbv_mu /tmp/cbv_mut
  FILE="$file" OLD="$old" NEW="$new" python3 - <<'PY'
import os
p = "/tmp/cbv_mut/" + os.environ["FILE"]
s = open(p, "rb").read().decode("utf-8")
old, new = os.environ["OLD"], os.environ["NEW"]
assert s.count(old) == 1, ("anchor not found or ambiguous", old, s.count(old))
open(p, "wb").write(s.replace(old, new).encode("utf-8"))
PY
  [ $? -ne 0 ] && { echo "MUTANT $name: COULD NOT APPLY"; return; }
  printf '<?php\ndefine( "WPMU_PLUGIN_DIR", "/tmp/cbv_mut" );\ndefine( "WP_DISABLE_FATAL_ERROR_HANDLER", true );\nini_set( "error_log", "/tmp/cbv_t/test_errors.log" );\n' > /tmp/cbv_t/define_mut.php
  sed "s#'/tmp/cbv_mu' === WPMU_PLUGIN_DIR#'/tmp/cbv_mut' === WPMU_PLUGIN_DIR#" /tmp/cbv_t/test_step7.php > /tmp/cbv_t/test_mut.php
  res=$(wp --require=/tmp/cbv_t/define_mut.php eval-file /tmp/cbv_t/test_mut.php 2>&1 | tr -d '\r')
  echo "MUTANT: $name"
  echo "$res" | grep -E '^FAIL|checks,|Fatal|Parse' | cut -c1-150 | head -4
  echo
}
F=checkedbags-lp-featured.php
run "gallery limit raised to 12" $F "define( 'CBV_LP_GALLERY_MAX', 10 );" "define( 'CBV_LP_GALLERY_MAX', 12 );"
run "gallery limit not enforced" $F "		if ( count( \$photos ) >= CBV_LP_GALLERY_MAX ) {
			break;
		}
	}
	return array(
		'heading'" "	}
	return array(
		'heading'"
run "gallery accepts non-image ids" $F "		\$id = cbv_lp_featured_image( \$row['id'] ?? 0 );" "		\$id = absint( \$row['id'] ?? 0 );"
run "free colour accepted" $F "isset( cbv_lp_featured_colours()[ (string) \$raw['colour'] ] ) ? (string) \$raw['colour'] : 'horizon';" "'' !== (string) \$raw['colour'] ? (string) \$raw['colour'] : 'horizon';"
run "default colour not Horizon" $F "(string) \$raw['colour'] : 'horizon';" "(string) \$raw['colour'] : 'ink';"
run "Day link shown even when the day left the itinerary" $F "if ( \$f['day'] && isset( cbv_lp_featured_day_choices( \$trip_id )[ \$f['day'] ] ) ) {" "if ( \$f['day'] ) {"
run "featured shown without a title" $F "	if ( '' === \$title ) {
		return null;
	}" "	if ( false ) {
		return null;
	}"
run "featured title not escaped" $F "<?php echo esc_html( \$d['title'] ); ?></h2>" "<?php echo \$d['title']; ?></h2>"
run "note text not run through the formatter (raw HTML)" $F "'note_text'  => '' !== \$note && function_exists( 'cbv_lp_inline' ) ? cbv_lp_inline( \$note ) : esc_html( \$note )," "'note_text'  => \$note,"
run "featured gate number not taken (label without GATE)" $F "	\$gate  = function_exists( 'cbv_lp_next_gate' ) ? 'Gate ' . cbv_lp_next_gate() : '';" "	\$gate  = '';"
run "gallery shown with no photos" $F "	if ( ! \$g['photos'] ) {
		return null;
	}" "	if ( false ) {
		return null;
	}"
run "gallery heading not falling back to the section name" $F "'heading' => '' !== \$heading ? \$heading : \$section," "'heading' => \$heading,"
run "empty figcaption printed" $F "							<?php if ( '' !== \$p['caption'] ) : ?>
								<figcaption>" "							<?php if ( true ) : ?>
								<figcaption>"
run "gallery section switch ignored" $F "! cbv_lp_section_enabled( \$trip_id, 'gallery' )" "false"
run "save writes both boxes on one nonce" $F "	if ( isset( \$_POST['cbv_lp_gallery_nonce'] ) && wp_verify_nonce( \$_POST['cbv_lp_gallery_nonce'], 'cbv_lp_gallery_save' ) ) {" "	if ( true ) {"
run "save handler skips the capability check" $F "	if ( ! current_user_can( 'edit_post', \$post_id ) ) {
		return;
	}
	if ( isset( \$_POST['cbv_lp_featured_nonce'] )" "	if ( false ) {
		return;
	}
	if ( isset( \$_POST['cbv_lp_featured_nonce'] )"
run "gallery before featured" checkedbags-lp-core.php "		echo cbv_lp_render_featured( \$trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the renderer
		echo cbv_lp_render_gallery( \$trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the renderer" "		echo cbv_lp_render_gallery( \$trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the renderer
		echo cbv_lp_render_featured( \$trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the renderer"
rm -rf /tmp/cbv_mut /tmp/cbv_t/define_mut.php /tmp/cbv_t/test_mut.php
