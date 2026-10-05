#!/bin/bash
# Mutation tests for Step 5: break one behaviour in a throwaway copy, run the suite, expect failures.
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
  printf '<?php\ndefine( "WPMU_PLUGIN_DIR", "/tmp/cbv_mut" );\ndefine( "WP_DISABLE_FATAL_ERROR_HANDLER", true );\n' > /tmp/cbv_t/define_mut.php
  sed "s#'/tmp/cbv_mu' === WPMU_PLUGIN_DIR#'/tmp/cbv_mut' === WPMU_PLUGIN_DIR#" /tmp/cbv_t/test_step5.php > /tmp/cbv_t/test_mut.php
  res=$(wp --require=/tmp/cbv_t/define_mut.php eval-file /tmp/cbv_t/test_mut.php 2>&1 | tr -d '\r')
  echo "MUTANT: $name"
  echo "$res" | grep -E '^FAIL|checks,|Fatal|Parse' | cut -c1-150 | head -4
  echo
}
F=checkedbags-lp-intro.php
run "full state ignores capacity" $F "if ( (int) \$capacity > 0 && \$booked >= (int) \$capacity ) {" "if ( false ) {"
run "minimum reached needs one more (> instead of >=)" $F "return \$booked >= (int) \$min ? 'after' : 'before';" "return \$booked > (int) \$min ? 'after' : 'before';"
run "blank number shown as 0 tiles" $F "if ( '' !== \$booked ) {
		\$width" "if ( true ) {
		\$width"
run "completed trips still show the board" $F "array( 'completed', 'declined' ), true )" "array( 'declined' ), true )"
run "section switch ignored for status" $F "! cbv_lp_section_enabled( \$trip_id, 'status' )" "false"
run "tiles not hidden from screen readers" $F "class=\"cbv-lp-status-tiles\" aria-hidden=\"true\"" "class=\"cbv-lp-status-tiles\""
run "intro heading not escaped" $F "<?php echo esc_html( '' !== \$d['heading'] ? \$d['heading'] : \$d['section'] ); ?>" "<?php echo '' !== \$d['heading'] ? \$d['heading'] : \$d['section']; ?>"
run "intro shown with no heading and no text" $F "if ( '' === \$heading && '' === trim( wp_strip_all_tags( \$body ) ) ) {
		return null;
	}" "if ( false ) {
		return null;
	}"
run "automatic chips after the typed ones" $F "\$chips  = array();
	\$nights" "\$chips  = cbv_lp_clean_chips( get_post_meta( \$trip_id, 'cbv_lp_intro_chips', true ) );
	\$nights"
run "GATE counter starts at 15" $F "static \$n = 13;" "static \$n = 14;"
run "GATE reset goes back to 12 (first gate 13)" $F "if ( \$reset ) {
		\$n = 13;" "if ( \$reset ) {
		\$n = 12;"
run "GATE counter not reset per render" checkedbags-lp-core.php "cbv_lp_next_gate( true );" "/* no reset */"
run "photo kind not checked" $F "'photo'   => function_exists( 'cbv_lp_clean_attachment_id' ) ? cbv_lp_clean_attachment_id( \$raw['photo'] ?? 0, 'image' ) : 0," "'photo'   => absint( \$raw['photo'] ?? 0 ),"
run "save handler skips the capability check" $F "if ( ! current_user_can( 'edit_post', \$post_id ) ) {
		return;
	}

	\$clean = cbv_lp_sanitize_intro" "if ( false ) {
		return;
	}

	\$clean = cbv_lp_sanitize_intro"
run "blank number saved as 0" $F "if ( '' === \$value || ! preg_match( '/^\d{1,4}\$/', \$value ) ) {
		return '';
	}" "if ( '' === \$value || ! preg_match( '/^\d{1,4}\$/', \$value ) ) {
		return '0';
	}"
run "nights counted inclusively (6 for Oct 25-30)" $F "return (int) round( ( \$e - \$s ) / DAY_IN_SECONDS );" "return (int) round( ( \$e - \$s ) / DAY_IN_SECONDS ) + 1;"
run "status board rendered after the intro" checkedbags-lp-core.php "		echo cbv_lp_render_status( \$trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the renderer
		echo cbv_lp_render_intro( \$trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the renderer" "		echo cbv_lp_render_intro( \$trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the renderer
		echo cbv_lp_render_status( \$trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the renderer"
run "stage 2 tiles keep the minimum (not capacity)" $F "		\$total = \$capacity;
	} else {" "		\$total = \$min;
	} else {"
run "spots left off by one" $F "\$left  = \$capacity - (int) \$booked;" "\$left  = \$capacity - (int) \$booked - 1;"
run "full stage shows the minimum as the total" $F "\$total = 'full' === \$state ? \$capacity : \$min;" "\$total = \$min;"
run "spots-left line also used when no capacity" $F "} elseif ( 'after' === \$state && \$capacity > 0 ) {" "} elseif ( 'after' === \$state ) {"
run "cruise people-word back to Sailors" checkedbags-lp-context.php "'party'                => 'Travelers'," "'party'                => 'Sailors',"
run "non-cruise confirmed wording same as cruise" $F "( \$departs ? 'Departure confirmed' : 'Group confirmed' )" "'Departure confirmed'"
rm -rf /tmp/cbv_mut /tmp/cbv_t/define_mut.php /tmp/cbv_t/test_mut.php
