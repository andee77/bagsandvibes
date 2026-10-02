<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Ultimate Member Form Tweaks
 * Description: Small accessibility fixes to Ultimate Member's own forms.
 *
 *              Password reset: UM's built-in "username_b" field (the one
 *              box on /password-reset/) is defined with a placeholder but
 *              no label, so a screen reader announces it as an unnamed
 *              edit box and the field has no visible name once the
 *              placeholder text is replaced by typing. Login and Register
 *              already have a label on every field; this gives the reset
 *              field one through UM's own documented per-field filter, so
 *              it renders through UM's normal label markup and styling
 *              rather than a script or a hand-written <label>.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-um-forms.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'um_get_field__username_b', function ( $field ) {
	if ( is_array( $field ) && empty( $field['label'] ) ) {
		$field['label'] = 'Username or E-mail';
	}
	return $field;
} );
