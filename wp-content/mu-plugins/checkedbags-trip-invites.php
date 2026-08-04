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

/* ==========================================================================
   PHASE 2 — Trip Guest role + invite tokens + registration hooks

   Registration on this site runs through Ultimate Member (UM) 2.12.1 — the
   role every normal signup gets today is 'subscriber' (there is no distinct
   "Full Member" WP role; "Full Member" in the spec = subscriber + UM's own
   account_status meta = 'approved' after manual review). Trip Guest is a
   genuinely new role, sitting alongside subscriber, not replacing it.
   ========================================================================== */

define( 'CBV_INVITE_COOKIE', 'cbv_invite_token' );
define( 'CBV_TRIP_INTEREST_COOKIE', 'cbv_trip_interest_code' );

/**
 * Trip Guest mirrors Subscriber's own WP capabilities (just 'read' — UM and
 * bbPress layer in the rest at runtime based on role slug, same as they do
 * for Subscriber). Also given bbp_participant directly on the user object
 * below so Guests can post in their trip's discussion board (Gate 10),
 * matching how every existing Subscriber is paired with bbp_participant.
 */
add_action( 'init', function () {
	if ( ! get_role( 'trip_guest' ) ) {
		add_role( 'trip_guest', 'Trip Guest', array( 'read' => true ) );
	}

	// UM's own per-role behavior config, for consistency if ever viewed/edited
	// in UM's admin UI. The actual auto-approval on invite redemption below
	// happens explicitly via UM()->common()->users()->approve() regardless
	// of this default, so this isn't load-bearing for the auto-approve path.
	if ( false === get_option( 'um_role_trip_guest_meta' ) ) {
		update_option( 'um_role_trip_guest_meta', array(
			'_um_can_access_wpadmin'   => false,
			'_um_can_not_see_adminbar' => true,
			'_um_can_edit_profile'     => true,
			'_um_can_delete_profile'   => true,
			'_um_can_view_all'         => true,
			'_um_default_homepage'     => true,
			'_um_status'               => 'approved',
			'_um_after_login'          => 'redirect_profile',
			'_um_login_redirect_url'   => home_url( '/dashboard/' ),
			'_um_after_logout'         => 'redirect_home',
		), '', false );
	}
} );

/**
 * Get this member's existing invite link token for a trip, or create one.
 * Reusable/non-expiring by design (per spec) — regenerating never
 * invalidates a link already shared with someone.
 */
function cbv_get_or_create_invite_token( $user_id, $trip_id ) {
	if ( ! in_array( (int) $user_id, cb_trip_get_roster( $trip_id ), true ) ) {
		return new WP_Error( 'cbv_no_access', 'You need access to this trip before you can invite others to it.' );
	}

	$existing = get_user_meta( $user_id, 'cbv_invite_token_' . $trip_id, true );
	if ( $existing ) {
		return $existing;
	}

	$token  = bin2hex( random_bytes( 20 ) ); // opaque — not a readable trip/user concatenation
	$lookup = get_option( 'cbv_invite_tokens', array() );

	$lookup[ $token ] = array(
		'trip_id'         => (int) $trip_id,
		'inviter_user_id' => (int) $user_id,
	);
	update_option( 'cbv_invite_tokens', $lookup, false );

	update_user_meta( $user_id, 'cbv_invite_token_' . $trip_id, $token );

	return $token;
}

/**
 * Resolve an invite token to its trip/inviter, or false if unknown.
 */
function cbv_resolve_invite_token( $token ) {
	$token  = sanitize_text_field( $token );
	$lookup = get_option( 'cbv_invite_tokens', array() );
	return isset( $lookup[ $token ] ) ? $lookup[ $token ] : false;
}

/**
 * Resolve a public trip code (e.g. CBV-2027-MAL) to its post ID. Only
 * matches Public trips — a Private trip's code is not a valid public entry
 * point, only its holders' invite tokens are (see cbv_resolve_invite_token).
 */
function cbv_resolve_trip_code( $code ) {
	$code = cbv_sanitize_trip_code( $code );
	if ( ! $code ) {
		return false;
	}

	$posts = get_posts( array(
		'post_type'      => 'cb_trip',
		'posts_per_page' => 1,
		'meta_query'     => array(
			array( 'key' => 'cb_trip_code', 'value' => $code ),
			array( 'key' => 'cb_visibility', 'value' => 'private', 'compare' => '!=' ),
		),
		'fields'         => 'ids',
	) );

	return $posts ? (int) $posts[0] : false;
}

/**
 * Stash ?invite=TOKEN or ?trip=CODE into short-lived cookies the moment they
 * show up on any page load. This decouples "visited the join/teaser page"
 * from "submitted the registration form" so the referral survives however
 * UM's own redirects route the visitor between the two — the alternative
 * (threading the param through UM's registration form fields) is fragile
 * against UM version changes and doesn't survive a login-first detour.
 */
add_action( 'init', function () {
	if ( headers_sent() ) {
		return;
	}

	if ( ! empty( $_GET['invite'] ) ) {
		$token = sanitize_text_field( wp_unslash( $_GET['invite'] ) );
		if ( cbv_resolve_invite_token( $token ) ) {
			setcookie( CBV_INVITE_COOKIE, $token, time() + DAY_IN_SECONDS, '/' );
			$_COOKIE[ CBV_INVITE_COOKIE ] = $token; // available this request too
		}
	}

	if ( ! empty( $_GET['trip'] ) ) {
		$code = sanitize_text_field( wp_unslash( $_GET['trip'] ) );
		if ( cbv_resolve_trip_code( $code ) ) {
			setcookie( CBV_TRIP_INTEREST_COOKIE, $code, time() + DAY_IN_SECONDS, '/' );
			$_COOKIE[ CBV_TRIP_INTEREST_COOKIE ] = $code;
		}
	}
}, 1 );

/**
 * Fires right after UM creates a new user via its registration form.
 * Valid invite token  -> Trip Guest, auto-approved, added to that trip's
 *                        roster via the existing cb_trip_add_member() —
 *                        the roster IS the access grant, nothing parallel.
 * Trip-interest code  -> role/approval untouched (normal manual-approval
 *                        Subscriber path), just remembers the interest for
 *                        the Phase 5 dashboard highlight.
 */
add_action( 'um_registration_complete', function ( $user_id, $args ) {

	$invite_token  = isset( $_COOKIE[ CBV_INVITE_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ CBV_INVITE_COOKIE ] ) ) : '';
	$trip_interest = isset( $_COOKIE[ CBV_TRIP_INTEREST_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ CBV_TRIP_INTEREST_COOKIE ] ) ) : '';

	if ( $invite_token ) {
		$resolved = cbv_resolve_invite_token( $invite_token );

		if ( $resolved ) {
			$user = new WP_User( $user_id );
			$user->set_role( 'trip_guest' );
			$user->add_role( 'bbp_participant' );

			cb_trip_add_member( $resolved['trip_id'], $user_id );

			update_user_meta( $user_id, '_invited_by_user_id', $resolved['inviter_user_id'] );
			update_user_meta( $user_id, '_invited_by_trip_id', $resolved['trip_id'] );

			if ( function_exists( 'UM' ) ) {
				// $force = true: at this point in the request the new user is
				// often already the "current user" (UM auto-logs-in on
				// registration), and can_be_approved() refuses self-approval
				// unless forced.
				UM()->common()->users()->approve( $user_id, true, false );
			}
		}

		if ( ! headers_sent() ) {
			setcookie( CBV_INVITE_COOKIE, '', time() - HOUR_IN_SECONDS, '/' );
		}
	} elseif ( $trip_interest ) {
		update_user_meta( $user_id, '_trip_interest', $trip_interest );

		if ( ! headers_sent() ) {
			setcookie( CBV_TRIP_INTEREST_COOKIE, '', time() - HOUR_IN_SECONDS, '/' );
		}
	}

}, 10, 2 );

/* ==========================================================================
   Join landing page — [cbv_join] — the teaser a QR code or shared link
   points to (bagsandvibes.com/join?trip=CODE or ?invite=TOKEN). Put this
   shortcode on a page with the slug "join".
   ========================================================================== */
add_shortcode( 'cbv_join', function () {

	$token = isset( $_GET['invite'] ) ? sanitize_text_field( wp_unslash( $_GET['invite'] ) ) : '';
	$code  = isset( $_GET['trip'] ) ? sanitize_text_field( wp_unslash( $_GET['trip'] ) ) : '';

	$trip_id        = null;
	$register_query = array();

	if ( $token ) {
		$resolved = cbv_resolve_invite_token( $token );
		if ( $resolved ) {
			$trip_id                  = $resolved['trip_id'];
			$register_query['invite'] = $token;
		}
	} elseif ( $code ) {
		$found = cbv_resolve_trip_code( $code );
		if ( $found ) {
			$trip_id                = $found;
			$register_query['trip'] = $code;
		}
	}

	if ( ! $trip_id ) {
		return '<p class="cb-empty">This invite link isn&#8217;t valid or has expired. <a href="' . esc_url( um_get_core_page( 'register' ) ) . '">Sign up here</a> instead.</p>';
	}

	$trip         = get_post( $trip_id );
	$cover        = get_the_post_thumbnail_url( $trip_id, 'large' ); // Phase 6 adds a dedicated cover-photo field; featured image is the stand-in until then
	$excerpt      = get_the_excerpt( $trip );
	$register_url = add_query_arg( $register_query, um_get_core_page( 'register' ) );

	ob_start();
	?>
	<div class="trip-join-teaser">
		<?php if ( $cover ) : ?>
			<div class="trip-join-cover" style="background-image:url('<?php echo esc_url( $cover ); ?>');"></div>
		<?php endif; ?>
		<h2 class="trip-join-title"><?php echo esc_html( get_the_title( $trip ) ); ?></h2>
		<?php if ( $excerpt ) : ?><p class="trip-join-excerpt"><?php echo esc_html( $excerpt ); ?></p><?php endif; ?>
		<a class="btn btn-ticket" href="<?php echo esc_url( $register_url ); ?>">
			<?php echo $token
				? esc_html__( "You're invited — join to see this trip", 'cbv' )
				: esc_html__( 'Sign up to see this trip', 'cbv' ); ?>
		</a>
	</div>
	<?php
	return ob_get_clean();
} );

/* ==========================================================================
   REST: generate (or fetch the existing) invite link for a trip. The
   dashboard "Generate invite link" button (Phase 5) calls this; exposing it
   now since token generation itself is Phase 2's job, per the build order.
   ========================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'cb/v1', '/trips/(?P<id>\d+)/invite-link', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function ( $request ) {
			$trip_id = (int) $request['id'];
			$token   = cbv_get_or_create_invite_token( get_current_user_id(), $trip_id );

			if ( is_wp_error( $token ) ) {
				return new WP_Error( $token->get_error_code(), $token->get_error_message(), array( 'status' => 403 ) );
			}

			return array(
				'token' => $token,
				'url'   => add_query_arg( 'invite', $token, home_url( '/join/' ) ),
			);
		},
	) );
} );
