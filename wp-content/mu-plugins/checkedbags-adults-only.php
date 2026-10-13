<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Adults only (18+)
 * Description: An "Adults only (18+)" setting on the cruise line / provider
 *              (Provider Library), with a per-trip override. On an
 *              adults-only trip nothing mentions children: the price board's
 *              "Children pay half the CBGV Group Experience Fee." line and
 *              the proposal PDF's copy of it are left out, and the traveler
 *              intake form has no "Additional children" / "Children's ages"
 *              fields (so a member's party is adults only).
 *              Owner, 2026-10-13: set for Virgin Voyages (adults only, 18+).
 *
 *              Also: replies to Ultimate Member's rejection email go to a
 *              monitored inbox (Reply-To, owner 2026-10-13); every other
 *              email is untouched.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-adults-only.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CBV_REJECTION_REPLY_TO', 'LaDon@journeywellglobal.com' );

/* ==========================================================================
   1. The rule.
   ========================================================================== */

/** The trip's own choice: 'inherit' (follow the provider), 'yes' or 'no'. */
function cbv_clean_adults_only_choice( $value ) {
	return in_array( $value, array( 'yes', 'no' ), true ) ? $value : 'inherit';
}

/** True when the provider is marked Adults only (18+). */
function cbv_provider_is_adults_only( $provider_id ) {
	return $provider_id && '1' === (string) get_post_meta( (int) $provider_id, 'cbv_lp_adults_only', true );
}

/**
 * True when this trip is adults only: the trip's own Yes/No wins; otherwise
 * it follows its provider; a trip with no provider and no choice is not.
 */
function cbv_trip_is_adults_only( $trip_id ) {
	$trip_id = (int) $trip_id;
	if ( ! $trip_id ) {
		return false;
	}
	$own = cbv_clean_adults_only_choice( get_post_meta( $trip_id, 'cbv_lp_adults_only', true ) );
	if ( 'inherit' !== $own ) {
		return 'yes' === $own;
	}
	$provider = function_exists( 'cbv_lp_provider_id' ) ? cbv_lp_provider_id( $trip_id ) : 0;
	return cbv_provider_is_adults_only( $provider );
}

/* ==========================================================================
   2. Admin: a checkbox on the provider, a choice on the trip.
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cbv_adults_only_provider', 'Who can travel', 'cbv_render_adults_only_provider_box', 'cb_provider', 'side', 'default' );
	add_meta_box( 'cbv_adults_only_trip', 'Adults only (18+)', 'cbv_render_adults_only_trip_box', 'cb_trip', 'side', 'default' );
} );

function cbv_render_adults_only_provider_box( $post ) {
	wp_nonce_field( 'cbv_adults_only_save', 'cbv_adults_only_nonce' );
	?>
	<p><label><input type="checkbox" name="cbv_lp_adults_only" value="1" <?php checked( cbv_provider_is_adults_only( $post->ID ) ); ?>> <strong>Adults only (18+)</strong></label></p>
	<p class="description">Trips with this provider never mention children: no "Children pay half" line on the price board or proposal, and no children fields on the traveler form. A trip can override this.</p>
	<?php
}

function cbv_render_adults_only_trip_box( $post ) {
	wp_nonce_field( 'cbv_adults_only_save', 'cbv_adults_only_nonce' );
	$choice   = cbv_clean_adults_only_choice( get_post_meta( $post->ID, 'cbv_lp_adults_only', true ) );
	$provider = function_exists( 'cbv_lp_provider_id' ) ? cbv_lp_provider_id( $post->ID ) : 0;
	$from     = $provider ? ( cbv_provider_is_adults_only( $provider ) ? 'adults only' : 'not adults only' ) : 'no provider set: not adults only';
	?>
	<p><label for="cbv_lp_adults_only_trip" class="screen-reader-text">Adults only (18+)</label>
	<select name="cbv_lp_adults_only" id="cbv_lp_adults_only_trip" style="width:100%;">
		<option value="inherit" <?php selected( $choice, 'inherit' ); ?>>Same as the provider (<?php echo esc_html( $from ); ?>)</option>
		<option value="yes" <?php selected( $choice, 'yes' ); ?>>Yes, adults only (18+)</option>
		<option value="no" <?php selected( $choice, 'no' ); ?>>No, children can travel</option>
	</select></p>
	<p class="description">Adults only hides "Children pay half" and the children fields on the traveler form.</p>
	<?php
}

add_action( 'save_post', function ( $post_id, $post ) {
	if ( ! isset( $_POST['cbv_adults_only_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_adults_only_nonce'], 'cbv_adults_only_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( 'cb_provider' === $post->post_type ) {
		update_post_meta( $post_id, 'cbv_lp_adults_only', empty( $_POST['cbv_lp_adults_only'] ) ? '' : '1' );
	} elseif ( 'cb_trip' === $post->post_type && isset( $_POST['cbv_lp_adults_only'] ) ) {
		update_post_meta( $post_id, 'cbv_lp_adults_only', cbv_clean_adults_only_choice( sanitize_key( wp_unslash( $_POST['cbv_lp_adults_only'] ) ) ) );
	}
}, 10, 2 );

/* ==========================================================================
   3. Reply-To on Ultimate Member's rejection email only. UM announces each
      email (um_before_email_notification_sending) just before calling
      wp_mail(); the flag set there is used and cleared by the next wp_mail()
      call, so no other email ever gets this header.
   ========================================================================== */
add_action( 'um_before_email_notification_sending', function ( $email, $template ) {
	$GLOBALS['cbv_um_mail_template'] = (string) $template;
}, 10, 2 );

add_filter( 'wp_mail', function ( $atts ) {
	$template = $GLOBALS['cbv_um_mail_template'] ?? '';
	unset( $GLOBALS['cbv_um_mail_template'] );
	if ( 'rejected_email' !== $template ) {
		return $atts;
	}
	$headers = $atts['headers'] ?? array();
	if ( is_string( $headers ) ) {
		$headers = '' === trim( $headers ) ? array() : preg_split( "/\r\n|\n/", trim( $headers ) );
	}
	$headers   = array_values( array_filter( (array) $headers, function ( $h ) { return 0 !== stripos( ltrim( (string) $h ), 'reply-to:' ); } ) );
	$headers[] = 'Reply-To: Checked Bags & Good Vibes <' . CBV_REJECTION_REPLY_TO . '>';
	$atts['headers'] = $headers;
	return $atts;
} );
