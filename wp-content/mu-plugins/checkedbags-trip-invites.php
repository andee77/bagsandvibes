<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Trip Invites, Guests & Agreements
 * Description: Extends the existing cb_trip CPT (checkedbags-trips.php) with
 *              the trip-invite system: trip code + visibility (this file),
 *              Trip Guest role + invite tokens, Membership Terms, per-trip
 *              Trip Agreement, cover photo / itinerary PDF, back-office
 *              screens, data export, public marketing page, and QR codes —
 *              built up in phases in this same file. See the phase build
 *              order in the trip-invite spec for what's implemented so far.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-trip-invites.php
 *
 * Loads independently of checkedbags-trips.php by hook order (init/
 * add_meta_boxes both fire after cb_trip is registered), but depends on it
 * for the cb_trip post type and cb_trip_get_roster()/cb_trip_add_member()
 * helpers existing at runtime.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   PHASE 1 — Trip CPT extension: Trip Code + Visibility
   ========================================================================== */

define( 'CBV_TRIP_VISIBILITIES', array(
	'public'  => 'Public',
	'private' => 'Private (invite-only)',
) );

add_action( 'init', function () {

	register_post_meta( 'cb_trip', 'cb_trip_code', array(
		'type'              => 'string',
		'single'            => true,
		'default'           => '',
		'show_in_rest'      => true,
		'sanitize_callback' => 'cbv_sanitize_trip_code',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'cb_trip', 'cb_visibility', array(
		'type'              => 'string',
		'single'            => true,
		'default'           => 'public',
		'show_in_rest'      => true,
		'sanitize_callback' => function ( $value ) {
			return array_key_exists( $value, CBV_TRIP_VISIBILITIES ) ? $value : 'public';
		},
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

}, 20 ); // after checkedbags-trips.php's own init (default priority 10) registers cb_trip

/**
 * Uppercase, strip anything that isn't A-Z/0-9/hyphen. Empty input is left
 * empty so the save handler knows to auto-suggest rather than accepting a
 * blank code.
 */
function cbv_sanitize_trip_code( $value ) {
	$value = strtoupper( trim( (string) $value ) );
	return preg_replace( '/[^A-Z0-9-]/', '', $value );
}

/**
 * Format: CBV-[YEAR]-[3-LETTER-DEST]. Year comes from cb_start_date if set
 * (a trip planned for 2027 should be coded 2027 even if booked in 2026),
 * otherwise falls back to the current year. Destination letters come from
 * the first 3 alphabetic characters of the post title.
 */
function cbv_suggest_trip_code( $post_id ) {
	$start_date = get_post_meta( $post_id, 'cb_start_date', true );
	$year       = $start_date ? substr( $start_date, 0, 4 ) : gmdate( 'Y' );

	$title       = get_the_title( $post_id );
	$letters     = preg_replace( '/[^A-Za-z]/', '', $title );
	$dest        = strtoupper( substr( $letters, 0, 3 ) );
	$dest        = str_pad( $dest, 3, 'X' );

	return "CBV-{$year}-{$dest}";
}

/**
 * Make $candidate unique among cb_trip_code values on other cb_trip posts by
 * appending -B, -C, ... (skipping -A since the bare candidate is tried first).
 */
function cbv_make_trip_code_unique( $candidate, $exclude_post_id ) {
	$code   = $candidate;
	$suffix = 0; // 0 = try bare candidate first

	while ( true ) {
		$existing = get_posts( array(
			'post_type'      => 'cb_trip',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'post__not_in'   => array( $exclude_post_id ),
			'meta_key'       => 'cb_trip_code',
			'meta_value'     => $code,
			'fields'         => 'ids',
		) );

		if ( empty( $existing ) ) {
			return $code;
		}

		++$suffix;
		$letter = chr( 65 + $suffix ); // 1 => 'B', 2 => 'C', ...
		$code   = $candidate . '-' . $letter;
	}
}

add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'cbv_trip_code_visibility',
		'Trip Code & Visibility',
		'cbv_render_trip_code_meta_box',
		'cb_trip',
		'side',
		'high'
	);
} );

function cbv_render_trip_code_meta_box( $post ) {
	wp_nonce_field( 'cbv_trip_code_save', 'cbv_trip_code_nonce' );

	$code       = get_post_meta( $post->ID, 'cb_trip_code', true );
	$visibility = get_post_meta( $post->ID, 'cb_visibility', true ) ?: 'public';
	$suggestion = $code ? $code : ( $post->post_title ? cbv_suggest_trip_code( $post->ID ) : '' );
	?>
	<p>
		<label for="cbv_trip_code"><strong>Trip code</strong></label><br>
		<input type="text" name="cbv_trip_code" id="cbv_trip_code" style="width:100%;"
			value="<?php echo esc_attr( $code ?: $suggestion ); ?>"
			placeholder="CBV-2027-MAL">
	</p>
	<p class="description">
		Auto-suggested from the title + start date on first save if left as-is.
		Must be unique — a duplicate gets <code>-B</code>, <code>-C</code>, etc. appended automatically.
	</p>

	<p>
		<label for="cbv_visibility"><strong>Visibility</strong></label><br>
		<select name="cbv_visibility" id="cbv_visibility" style="width:100%;">
			<?php foreach ( CBV_TRIP_VISIBILITIES as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $visibility, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<p class="description">
		Public trips get a marketing page + teaser QR and normal manual-approval signup.
		Private trips are only reachable via a member's personal invite link.
	</p>
	<?php
}

add_action( 'save_post_cb_trip', function ( $post_id ) {

	if ( ! isset( $_POST['cbv_trip_code_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_trip_code_nonce'], 'cbv_trip_code_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['cbv_visibility'] ) ) {
		$visibility = sanitize_text_field( wp_unslash( $_POST['cbv_visibility'] ) );
		if ( ! array_key_exists( $visibility, CBV_TRIP_VISIBILITIES ) ) {
			$visibility = 'public';
		}
		update_post_meta( $post_id, 'cb_visibility', $visibility );
	}

	$submitted = isset( $_POST['cbv_trip_code'] ) ? cbv_sanitize_trip_code( wp_unslash( $_POST['cbv_trip_code'] ) ) : '';
	$candidate = $submitted ?: cbv_suggest_trip_code( $post_id );
	$final     = cbv_make_trip_code_unique( $candidate, $post_id );

	update_post_meta( $post_id, 'cb_trip_code', $final );

	if ( $final !== $candidate ) {
		set_transient( 'cbv_trip_code_disambiguated_' . get_current_user_id(), $final, 60 );
	}
}, 10, 1 );

add_action( 'admin_notices', function () {
	$user_id   = get_current_user_id();
	$disambig  = get_transient( 'cbv_trip_code_disambiguated_' . $user_id );
	if ( $disambig ) {
		delete_transient( 'cbv_trip_code_disambiguated_' . $user_id );
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			sprintf(
				/* translators: %s: the disambiguated trip code */
				esc_html__( 'That trip code was already in use, so it was saved as %s instead.', 'cbv' ),
				'<strong>' . esc_html( $disambig ) . '</strong>'
			)
		);
	}
} );
