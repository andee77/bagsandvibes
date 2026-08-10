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
	// Phase 11: a scannable QR for this trip's public join link -- only
	// for Public trips with a saved code, since a Private trip's code
	// deliberately doesn't resolve via /join/?trip=... (cbv_resolve_trip_code()
	// excludes it) and a QR pointing at a link that just says "not valid"
	// isn't useful. Generated fresh on every screen load (cheap, ~1-2KB),
	// no file written to disk.
	if ( 'public' === $visibility && $code ) :
		$join_url = home_url( '/join/?trip=' . rawurlencode( $code ) );
		$qr_uri   = function_exists( 'cb_generate_qr_data_uri' ) ? cb_generate_qr_data_uri( $join_url ) : '';
		?>
		<hr>
		<p><strong>Public Join QR</strong></p>
		<?php if ( $qr_uri ) : ?>
			<img src="<?php echo esc_attr( $qr_uri ); ?>" alt="QR code linking to this trip's public join page" style="width:100%;max-width:200px;height:auto;display:block;">
		<?php endif; ?>
		<p class="description">
			Right-click to save for flyers or social posts. Links to:<br>
			<code style="word-break:break-all;"><?php echo esc_html( $join_url ); ?></code>
		</p>
	<?php elseif ( 'private' === $visibility ) : ?>
		<hr>
		<p class="description"><em>No QR shown — Private trips aren't reachable via the public join-code link, only a member's personal invite link.</em></p>
	<?php endif; ?>
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
	// Admin bypass: a support/administrative action (e.g. regenerating a
	// lost invite link for a client), not something requiring genuine
	// travel -- same "not on roster, but an admin" carve-out as
	// cbv_user_can_view_trip().
	$has_access = in_array( (int) $user_id, cb_trip_get_roster( $trip_id ), true )
		|| user_can( $user_id, 'manage_options' );
	if ( ! $has_access ) {
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
			array(
				'relation' => 'OR',
				// A trip whose cb_visibility meta row was never explicitly
				// saved has no row at all -- register_post_meta()'s 'public'
				// default only applies when reading via get_post_meta(), not
				// to this meta_query, so a plain != 'private' comparison
				// silently excludes it (SQL NULL != 'private' isn't true).
				// Confirmed directly: a freshly created trip resolved as
				// false here despite get_post_meta() reporting 'public',
				// and only succeeded once cb_visibility was explicitly saved.
				array( 'key' => 'cb_visibility', 'compare' => 'NOT EXISTS' ),
				array( 'key' => 'cb_visibility', 'value' => 'private', 'compare' => '!=' ),
			),
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

			$url = add_query_arg( 'invite', $token, home_url( '/join/' ) );

			return array(
				'token'  => $token,
				'url'    => $url,
				// Phase 11: generated in the same response rather than a
				// second round-trip -- entirely server-side (no third-party
				// QR API), since this URL identifies both the trip and the
				// inviting member and must never leave this server.
				'qr_uri' => function_exists( 'cb_generate_qr_data_uri' ) ? cb_generate_qr_data_uri( $url ) : '',
			);
		},
	) );
} );

/* ==========================================================================
   PHASE 3 — Membership Terms versioning + re-acceptance gate

   Replaces checkedbags-gate11.php's old cb_agreed_to_rules mechanism (a
   single global, unversioned "I agree" checkbox) — see the migration below
   and the corresponding rewrite of checkedbags-gate11.php, which now shows
   read-only acceptance status instead of its own separate checkbox.
   ========================================================================== */

function cbv_get_membership_terms() {
	$default = array( 'version' => 1, 'content' => '', 'updated' => '' );
	return wp_parse_args( get_option( 'cbv_membership_terms', $default ), $default );
}

function cbv_get_current_terms_version() {
	return (int) cbv_get_membership_terms()['version'];
}

function cbv_get_current_terms_content() {
	return cbv_get_membership_terms()['content'];
}

/**
 * True if $user_id hasn't accepted the currently-published version (or has
 * never accepted at all).
 */
function cbv_user_needs_terms_reaccept( $user_id ) {
	$accepted = (int) get_user_meta( $user_id, '_accepted_terms_version', true );
	return $accepted < cbv_get_current_terms_version();
}

/**
 * One-time migration: anyone who already clicked the old Gate 11
 * "I agree to the travel policy" checkbox (cb_agreed_to_rules, a bare
 * timestamp) is carried over as having accepted version 1 of the new
 * system, using that same timestamp as their acceptance date — so they
 * aren't unexpectedly soft-locked the next time they log in. Gated by an
 * option flag so it only ever runs once, regardless of how many times
 * `init` fires.
 */
add_action( 'init', function () {
	if ( get_option( 'cbv_terms_migration_done' ) ) {
		return;
	}

	$legacy_user_ids = get_users( array(
		'meta_key'     => 'cb_agreed_to_rules',
		'meta_compare' => 'EXISTS',
		'fields'       => 'ID',
	) );

	foreach ( $legacy_user_ids as $user_id ) {
		if ( get_user_meta( $user_id, '_accepted_terms_version', true ) ) {
			continue; // already on the new system somehow — don't clobber
		}

		$agreed_date = get_user_meta( $user_id, 'cb_agreed_to_rules', true );
		update_user_meta( $user_id, '_accepted_terms_version', 1 );
		update_user_meta( $user_id, '_accepted_terms_date', $agreed_date ?: current_time( 'mysql' ) );
	}

	update_option( 'cbv_terms_migration_done', true, false );
}, 30 );

/**
 * Admin screen: Settings -> Membership Terms. Editing and saving bumps the
 * version automatically — every member whose acceptance is now behind gets
 * soft-locked to a re-acceptance screen on their next page load.
 */
add_action( 'admin_menu', function () {
	add_options_page( 'Membership Terms', 'Membership Terms', 'manage_options', 'cbv-membership-terms', 'cbv_render_membership_terms_page' );
} );

function cbv_render_membership_terms_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['cbv_terms_nonce'] ) && wp_verify_nonce( $_POST['cbv_terms_nonce'], 'cbv_save_terms' ) ) {
		$terms       = cbv_get_membership_terms();
		$new_content = isset( $_POST['cbv_terms_content'] ) ? wp_kses_post( wp_unslash( $_POST['cbv_terms_content'] ) ) : '';

		if ( trim( $new_content ) !== trim( $terms['content'] ) ) {
			$terms['version'] = $terms['version'] + 1;
			$terms['content'] = $new_content;
			$terms['updated'] = current_time( 'mysql' );
			update_option( 'cbv_membership_terms', $terms, false );
			echo '<div class="notice notice-success"><p>' . sprintf(
				esc_html__( 'Saved as version %d. Anyone who already accepted an earlier version will be asked to re-accept before continuing to use their dashboard.', 'cbv' ),
				(int) $terms['version']
			) . '</p></div>';
		} else {
			echo '<div class="notice notice-info"><p>No changes — version unchanged.</p></div>';
		}
	}

	$terms = cbv_get_membership_terms();
	?>
	<div class="wrap">
		<h1>Membership Terms</h1>
		<p>
			Current version: <strong><?php echo (int) $terms['version']; ?></strong>
			(last updated <?php echo esc_html( $terms['updated'] ?: 'never' ); ?>)
		</p>
		<?php if ( '' === trim( $terms['content'] ) ) : ?>
			<div class="notice notice-warning"><p>No terms content has been entered yet — new signups currently see an empty agreement box. Add real content below before relying on this for new registrations.</p></div>
		<?php endif; ?>
		<p class="description">Saving with changed content automatically bumps the version and prompts re-acceptance from anyone already on an older version.</p>
		<form method="post">
			<?php wp_nonce_field( 'cbv_save_terms', 'cbv_terms_nonce' ); ?>
			<textarea name="cbv_terms_content" rows="20" style="width:100%;max-width:800px;font-family:monospace;"><?php echo esc_textarea( $terms['content'] ); ?></textarea>
			<p><button type="submit" class="button button-primary">Save &amp; publish new version</button></p>
		</form>
	</div>
	<?php
}

/**
 * Public, read-only view of the current Membership Terms -- [cbv_membership_terms].
 * Until now the ONLY place this content appeared was the small 150px
 * scrollable box next to the registration checkbox below, with no separate
 * page to link to; this gives prospective and existing members a real page
 * to read the full terms on (and something for the registration checkbox
 * to link to). No login required, same as any other legal/policy page.
 */
add_shortcode( 'cbv_membership_terms', function () {
	$version = cbv_get_current_terms_version();
	$content = cbv_get_current_terms_content();

	ob_start();
	?>
	<div class="cbv-membership-terms-page">
		<p class="cbv-terms-version">Version <?php echo (int) $version; ?></p>
		<?php if ( '' === trim( $content ) ) : ?>
			<p class="cb-empty">No Membership Terms content has been published yet.</p>
		<?php else : ?>
			<?php echo wp_kses_post( wpautop( $content ) ); ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
} );

/**
 * Intro copy above UM's registration fields -- um_before_register_fields
 * fires before Username/Name/Email/Password, unlike um_after_register_fields
 * below which fires after them (that's why the Membership Terms checkbox
 * uses the "after" hook and this uses "before").
 */
add_action( 'um_before_register_fields', function () {
	echo '<p class="cb-page-hint">Just a few details to get your account set up — you can fill in the rest of your Travel Profile later.</p>';
} );

/**
 * Inject the required Membership Terms checkbox into UM's registration
 * form. Priority 900 — before UM's own submit button, which hooks the same
 * action at priority 1000 (um_add_submit_button_to_register).
 */
add_action( 'um_after_register_fields', function () {
	$version = cbv_get_current_terms_version();
	$content = cbv_get_current_terms_content();
	?>
	<div class="um-field cbv-terms-field" style="margin: 15px 0;">
		<div style="max-height:150px;overflow-y:auto;border:1px solid #ddd;padding:10px;margin-bottom:8px;font-size:13px;">
			<?php echo wp_kses_post( wpautop( $content ) ); ?>
		</div>
		<?php // Points at the site's real, already-written Terms of Service
		// page -- NOT the versioned Membership Terms system below, which
		// this deliberately leaves untouched (no content copy, no version
		// bump, no change to existing acceptance records). Link-destination
		// and label only, per explicit correction: the tester meant the ToS
		// page, not this separate acceptance-tracking mechanism. ?>
		<p style="margin: 0 0 8px;"><a href="<?php echo esc_url( home_url( '/terms-of-service/' ) ); ?>" target="_blank" rel="noopener">Read the Terms of Service</a> (opens in a new tab)</p>
		<label>
			<input type="checkbox" name="cbv_accept_terms" value="1">
			<?php
			printf(
				/* translators: %d: Membership Terms version number */
				esc_html__( 'I have read and agree to the Membership Terms (v%d).', 'cbv' ),
				(int) $version
			);
			?>
			<span class="cbv-required" aria-hidden="true">*</span>
		</label>
	</div>
	<?php
}, 900 );

/**
 * Block registration if the terms checkbox wasn't checked. Fires before the
 * new user is created — um_submit_form_register() bails out entirely if any
 * errors were added here.
 */
add_action( 'um_submit_form_errors_hook__registration', function ( $submitted_data ) {
	if ( empty( $submitted_data['cbv_accept_terms'] ) ) {
		UM()->form()->add_error( 'cbv_accept_terms', __( 'You must agree to the Membership Terms to create an account.', 'cbv' ) );
	}
} );

/**
 * Record acceptance at the version current at the moment of registration —
 * validation above already guarantees the checkbox was checked to get here.
 */
add_action( 'um_registration_complete', function ( $user_id ) {
	update_user_meta( $user_id, '_accepted_terms_version', cbv_get_current_terms_version() );
	update_user_meta( $user_id, '_accepted_terms_date', current_time( 'mysql' ) );
}, 10, 1 );

/**
 * Soft-lock: any logged-in user whose accepted version is behind current
 * gets redirected to the re-acceptance screen on their next page load,
 * except the screen itself and the logout link (so no one gets stuck).
 */
add_action( 'template_redirect', function () {
	if ( ! is_user_logged_in() || is_page( 'reaccept-terms' ) || is_page( 'logout' ) ) {
		return;
	}

	if ( cbv_user_needs_terms_reaccept( get_current_user_id() ) ) {
		wp_safe_redirect( home_url( '/reaccept-terms/' ) );
		exit;
	}
} );

/**
 * Re-acceptance screen — [cbv_reaccept_terms]. Put this on a page with the
 * slug "reaccept-terms" (matches the is_page() checks above).
 */
add_shortcode( 'cbv_reaccept_terms', function () {
	if ( ! is_user_logged_in() ) {
		return '<p class="cb-empty">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to continue.</p>';
	}

	$user_id = get_current_user_id();
	if ( ! cbv_user_needs_terms_reaccept( $user_id ) ) {
		return '<p class="cb-empty">You&#8217;re all caught up. <a href="' . esc_url( home_url( '/dashboard/' ) ) . '">Go to your dashboard</a>.</p>';
	}

	$version = cbv_get_current_terms_version();
	$content = cbv_get_current_terms_content();

	ob_start();
	?>
	<div class="cbv-reaccept-terms">
		<h2>Updated Membership Terms</h2>
		<p>We&#8217;ve updated our Membership Terms (now version <?php echo (int) $version; ?>). Please review and re-accept to continue.</p>
		<div style="max-height:300px;overflow-y:auto;border:1px solid #ddd;padding:15px;margin-bottom:12px;">
			<?php echo wp_kses_post( wpautop( $content ) ); ?>
		</div>
		<label>
			<input type="checkbox" id="cbv-reaccept-checkbox">
			I have read and agree to the updated Membership Terms.
			<span class="cbv-required" aria-hidden="true">*</span>
		</label>
		<p><button type="button" class="btn btn-ticket" id="cbv-reaccept-submit" disabled>Continue</button></p>
		<div id="cbv-reaccept-result" style="margin-top:8px;"></div>
	</div>
	<script>
	(function () {
		var restUrl  = <?php echo wp_json_encode( esc_url_raw( rest_url( 'cb/v1/' ) ) ); ?>;
		var nonce    = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
		var checkbox = document.getElementById( 'cbv-reaccept-checkbox' );
		var submit   = document.getElementById( 'cbv-reaccept-submit' );
		var result   = document.getElementById( 'cbv-reaccept-result' );

		checkbox.addEventListener( 'change', function () { submit.disabled = ! checkbox.checked; } );

		submit.addEventListener( 'click', function () {
			submit.disabled = true;
			submit.textContent = 'Saving…';

			fetch( restUrl + 'accept-terms', { method: 'POST', headers: { 'X-WP-Nonce': nonce } } )
				.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
				.then( function ( res ) {
					if ( res.ok && res.body.accepted ) {
						window.location.href = <?php echo wp_json_encode( home_url( '/dashboard/' ) ); ?>;
					} else {
						submit.disabled = false;
						submit.textContent = 'Continue';
						result.textContent = 'Something went wrong — please try again.';
					}
				} )
				.catch( function () {
					submit.disabled = false;
					submit.textContent = 'Continue';
					result.textContent = 'Request failed — please try again.';
				} );
		} );
	})();
	</script>
	<?php
	return ob_get_clean();
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'cb/v1', '/accept-terms', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function () {
			$user_id = get_current_user_id();
			$version = cbv_get_current_terms_version();

			update_user_meta( $user_id, '_accepted_terms_version', $version );
			update_user_meta( $user_id, '_accepted_terms_date', current_time( 'mysql' ) );

			return array( 'accepted' => true, 'version' => $version );
		},
	) );
} );

/* ==========================================================================
   Admin: read-only usermeta panel — trip-invite build fields

   Shows what this build has recorded on a user so far (Membership Terms
   acceptance, legacy-migration status, invite referral, trip interest).
   Testing/visibility aid for now; Phase 7's Trip Guests back-office screen
   will surface some of the same fields in a dedicated list view.
   ========================================================================== */
add_action( 'show_user_profile', 'cbv_render_user_meta_panel' );
add_action( 'edit_user_profile', 'cbv_render_user_meta_panel' );

function cbv_render_user_meta_panel( $profile_user ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$terms_version = get_user_meta( $profile_user->ID, '_accepted_terms_version', true );
	$terms_date    = get_user_meta( $profile_user->ID, '_accepted_terms_date', true );
	$legacy_agreed = get_user_meta( $profile_user->ID, 'cb_agreed_to_rules', true );
	$invited_by_id = get_user_meta( $profile_user->ID, '_invited_by_user_id', true );
	$invited_trip  = get_user_meta( $profile_user->ID, '_invited_by_trip_id', true );
	$trip_interest = get_user_meta( $profile_user->ID, '_trip_interest', true );
	$requested_membership_date = get_user_meta( $profile_user->ID, '_requested_full_membership_date', true );
	?>
	<h2>Trip-Invite Build — Field Status <span style="font-weight:normal;font-size:13px;">(read-only, for testing)</span></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><label>Membership Terms</label></th>
			<td>
				<?php if ( $terms_version ) : ?>
					Version <strong><?php echo (int) $terms_version; ?></strong>
					accepted <?php echo esc_html( $terms_date ? date_i18n( 'M j, Y g:i a', strtotime( $terms_date ) ) : '(no date on file)' ); ?>
				<?php else : ?>
					<em>Not yet accepted</em>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label>Migration status</label></th>
			<td>
				<?php if ( $legacy_agreed ) : ?>
					Migrated from the old Gate 11 agreement — original timestamp:
					<strong><?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $legacy_agreed ) ) ); ?></strong>
				<?php else : ?>
					<em>No legacy cb_agreed_to_rules record</em>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label>Invited by</label></th>
			<td>
				<?php if ( $invited_by_id ) :
					$inviter = get_userdata( $invited_by_id );
					?>
					<?php if ( $inviter ) : ?>
						<a href="<?php echo esc_url( get_edit_user_link( $invited_by_id ) ); ?>"><?php echo esc_html( $inviter->display_name ); ?></a>
					<?php else : ?>
						<em>User #<?php echo (int) $invited_by_id; ?> (no longer exists)</em>
					<?php endif; ?>

					<?php if ( $invited_trip ) :
						$trip = get_post( $invited_trip );
						?>
						for
						<?php if ( $trip && 'cb_trip' === $trip->post_type ) :
							$trip_code = get_post_meta( $trip->ID, 'cb_trip_code', true );
							?>
							<a href="<?php echo esc_url( get_edit_post_link( $trip->ID ) ); ?>">
								<?php echo esc_html( get_the_title( $trip ) ); ?><?php echo $trip_code ? ' (' . esc_html( $trip_code ) . ')' : ''; ?>
							</a>
						<?php else : ?>
							<em>Trip #<?php echo (int) $invited_trip; ?> (no longer exists)</em>
						<?php endif; ?>
					<?php endif; ?>
				<?php else : ?>
					<em>Not invited — registered directly</em>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label>Trip interest (public path)</label></th>
			<td>
				<?php if ( $trip_interest ) :
					$interest_trip_id = cbv_resolve_trip_code( $trip_interest );
					?>
					Code <strong><?php echo esc_html( $trip_interest ); ?></strong>
					<?php if ( $interest_trip_id ) : ?>
						— <a href="<?php echo esc_url( get_edit_post_link( $interest_trip_id ) ); ?>"><?php echo esc_html( get_the_title( $interest_trip_id ) ); ?></a>
					<?php endif; ?>
				<?php else : ?>
					<em>None on file</em>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th><label>Requested Full Membership</label></th>
			<td>
				<?php if ( $requested_membership_date ) : ?>
					Requested <?php echo esc_html( date_i18n( 'M j, Y g:i a', strtotime( $requested_membership_date ) ) ); ?>
				<?php else : ?>
					<em>No request on file</em>
				<?php endif; ?>
			</td>
		</tr>
	</table>
	<?php
}

/* ==========================================================================
   PHASE 4 — Trip Agreement versioning + per-trip acceptance gate

   Deliberately a dedicated field (cb_trip_agreement), not a repurposing of
   cb_rules_addendum — see the Phase 1 reconciliation: cb_rules_addendum is
   Gate 11's short informational "extra rules" blurb, already displayed
   read-only with no acceptance gate; a binding, versioned Trip Agreement is
   a different kind of content and would conflict if they shared one field.
   ========================================================================== */

function cbv_get_trip_agreement_version( $trip_id ) {
	return (int) ( get_post_meta( $trip_id, 'cb_trip_agreement_version', true ) ?: 1 );
}

function cbv_get_trip_agreement_content( $trip_id ) {
	return get_post_meta( $trip_id, 'cb_trip_agreement', true );
}

/**
 * Stored as one meta value per trip (version + date together) per the
 * spec's _trip_agreement_accepted_{trip_id} naming, rather than two
 * separate meta keys.
 */
function cbv_user_accepted_trip_agreement_version( $user_id, $trip_id ) {
	$accepted = get_user_meta( $user_id, '_trip_agreement_accepted_' . $trip_id, true );
	return ( is_array( $accepted ) && isset( $accepted['version'] ) ) ? (int) $accepted['version'] : 0;
}

function cbv_user_accepted_trip_agreement_date( $user_id, $trip_id ) {
	$accepted = get_user_meta( $user_id, '_trip_agreement_accepted_' . $trip_id, true );
	return ( is_array( $accepted ) && isset( $accepted['date'] ) ) ? $accepted['date'] : '';
}

function cbv_user_needs_trip_agreement_reaccept( $user_id, $trip_id ) {
	return cbv_user_accepted_trip_agreement_version( $user_id, $trip_id ) < cbv_get_trip_agreement_version( $trip_id );
}

/**
 * The gate: roster membership AND this trip's current agreement version
 * accepted. Everywhere a trip's actual details render (today: Gate 07's
 * single-trip view) should check this before showing anything beyond
 * "you don't have access yet." Per spec §6.1, the itinerary PDF download
 * (Phase 6) is deliberately exempt from the agreement half of this check —
 * roster membership alone is enough for that, since it's meant to inform
 * the decision, not reward it.
 *
 * Admin bypass: an admin gets full view access to every trip WITHOUT being
 * added to cb_roster -- but only when they're not actually on it. A roster
 * member who happens to be an admin still goes through the normal rules
 * below (including the agreement check), same as any other traveler.
 */
function cbv_user_can_view_trip( $user_id, $trip_id ) {
	$on_roster = in_array( (int) $user_id, cb_trip_get_roster( $trip_id ), true );
	if ( ! $on_roster ) {
		return user_can( $user_id, 'manage_options' );
	}
	return ! cbv_user_needs_trip_agreement_reaccept( $user_id, $trip_id );
}

/**
 * Per-trip agreement content + version, editable from the same cb_trip
 * edit screen. Auto-bumps version when the saved content actually changes —
 * same pattern as the site-wide Membership Terms screen (Phase 3), but
 * scoped to this one trip rather than a global option.
 */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'cbv_trip_agreement',
		'Trip Agreement',
		'cbv_render_trip_agreement_meta_box',
		'cb_trip',
		'normal',
		'default'
	);
} );

function cbv_render_trip_agreement_meta_box( $post ) {
	wp_nonce_field( 'cbv_trip_agreement_save', 'cbv_trip_agreement_nonce' );

	$content = cbv_get_trip_agreement_content( $post->ID );
	$version = cbv_get_trip_agreement_version( $post->ID );
	?>
	<p>
		Current version: <strong><?php echo (int) $version; ?></strong>.
		Changing the text below and saving bumps the version automatically —
		anyone who already accepted an older version (roster members, invited
		Guests) will be asked to re-accept before they can see this trip's
		details again.
	</p>
	<textarea name="cbv_trip_agreement_content" rows="12" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $content ); ?></textarea>
	<?php
}

add_action( 'save_post_cb_trip', function ( $post_id ) {

	if ( ! isset( $_POST['cbv_trip_agreement_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_trip_agreement_nonce'], 'cbv_trip_agreement_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['cbv_trip_agreement_content'] ) ) {
		return;
	}

	$old_content = cbv_get_trip_agreement_content( $post_id );
	$new_content = wp_kses_post( wp_unslash( $_POST['cbv_trip_agreement_content'] ) );

	if ( trim( $new_content ) === trim( $old_content ) ) {
		return; // unchanged — leave the version alone
	}

	update_post_meta( $post_id, 'cb_trip_agreement', $new_content );
	update_post_meta( $post_id, 'cb_trip_agreement_version', cbv_get_trip_agreement_version( $post_id ) + 1 );
} );

/**
 * Record acceptance of a specific trip's current agreement version. 403s
 * if the caller isn't actually on that trip's roster — accepting an
 * agreement isn't itself how you gain access (cb_trip_add_member is), it's
 * a precondition for viewing details once you already have access.
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'cb/v1', '/trips/(?P<id>\d+)/accept-agreement', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function ( $request ) {
			$trip_id = (int) $request['id'];
			$user_id = get_current_user_id();

			if ( ! in_array( $user_id, cb_trip_get_roster( $trip_id ), true ) ) {
				return new WP_Error( 'cbv_no_access', 'You need access to this trip before you can accept its agreement.', array( 'status' => 403 ) );
			}

			$version = cbv_get_trip_agreement_version( $trip_id );
			update_user_meta( $user_id, '_trip_agreement_accepted_' . $trip_id, array(
				'version' => $version,
				'date'    => current_time( 'mysql' ),
			) );

			return array( 'accepted' => true, 'version' => $version );
		},
	) );
} );

/**
 * Inline "accept this trip's agreement" prompt, shared by any surface that
 * gates on cbv_user_can_view_trip()/cbv_user_needs_trip_agreement_reaccept()
 * — today that's Gate 07's single-trip detail view.
 */
function cbv_render_trip_agreement_prompt( $trip_id ) {
	$version = cbv_get_trip_agreement_version( $trip_id );
	$content = cbv_get_trip_agreement_content( $trip_id );

	ob_start();
	?>
	<div class="trip-detail-section trip-agreement-gate">
		<h3>Trip Agreement</h3>
		<?php if ( '' === trim( $content ) ) : ?>
			<p><em>No agreement text has been added for this trip yet — contact us if you have questions before continuing.</em></p>
			<p><button type="button" class="btn btn-ticket cbv-accept-agreement-btn" data-trip-id="<?php echo (int) $trip_id; ?>">Continue to trip details</button></p>
		<?php else : ?>
			<div style="max-height:300px;overflow-y:auto;border:1px solid #ddd;padding:15px;margin-bottom:12px;">
				<?php echo wp_kses_post( wpautop( $content ) ); ?>
			</div>
			<label>
				<input type="checkbox" class="cbv-agreement-checkbox">
				I have read and agree to this trip&#8217;s agreement (v<?php echo (int) $version; ?>).
				<span class="cbv-required" aria-hidden="true">*</span>
			</label>
			<p><button type="button" class="btn btn-ticket cbv-accept-agreement-btn" data-trip-id="<?php echo (int) $trip_id; ?>" disabled>Continue to trip details</button></p>
		<?php endif; ?>
		<div class="cbv-agreement-result" style="margin-top:8px;"></div>
	</div>
	<script>
	(function () {
		var restUrl  = <?php echo wp_json_encode( esc_url_raw( rest_url( 'cb/v1/' ) ) ); ?>;
		var nonce    = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
		var checkbox = document.querySelector( '.cbv-agreement-checkbox' );
		var button   = document.querySelector( '.cbv-accept-agreement-btn' );
		var result   = document.querySelector( '.cbv-agreement-result' );

		if ( checkbox && button ) {
			checkbox.addEventListener( 'change', function () { button.disabled = ! checkbox.checked; } );
		}

		if ( button ) {
			button.addEventListener( 'click', function () {
				button.disabled = true;
				button.textContent = 'Saving…';

				fetch( restUrl + 'trips/' + button.getAttribute( 'data-trip-id' ) + '/accept-agreement', {
					method: 'POST',
					headers: { 'X-WP-Nonce': nonce }
				} )
					.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
					.then( function ( res ) {
						if ( res.ok && res.body.accepted ) {
							location.reload();
						} else {
							button.disabled = false;
							button.textContent = 'Continue to trip details';
							if ( result ) result.textContent = 'Something went wrong — please try again.';
						}
					} )
					.catch( function () {
						button.disabled = false;
						button.textContent = 'Continue to trip details';
						if ( result ) result.textContent = 'Request failed — please try again.';
					} );
			} );
		}
	})();
	</script>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   PHASE 5 — Dashboard changes (Guest scoped view + Full Member trip highlight)

   The dashboard template itself (mu-plugins/checkedbags-landing/
   template-dashboard.php) owns the actual markup change; this section is
   just the shared mechanism it calls into, consistent with how Gate 07/11
   were retrofitted in earlier phases.

   Passport renewal flagging (spec §5.3) is deliberately NOT part of this
   phase — there is no passport_expiration_date field anywhere yet (that's
   part of Phase 8's expanded data-collection forms), so there is nothing
   real to compare a trip's end date against. Building it now would just be
   an inert stub with nothing to test.
   ========================================================================== */

/**
 * Which trip (if any) a Full Member's dashboard should highlight on load:
 * their public trip-interest code, or — if they were originally a Trip
 * Guest later promoted to Full Member (Phase 7) — the trip they were
 * invited to in the first place. Returns 0 once dismissed, or if neither
 * applies.
 */
function cbv_get_dashboard_highlight_trip_id( $user_id ) {
	if ( get_user_meta( $user_id, '_trip_interest_dismissed', true ) ) {
		return 0;
	}

	$trip_interest = get_user_meta( $user_id, '_trip_interest', true );
	if ( $trip_interest ) {
		$trip_id = cbv_resolve_trip_code( $trip_interest );
		if ( $trip_id ) {
			return $trip_id;
		}
	}

	$invited_trip_id = (int) get_user_meta( $user_id, '_invited_by_trip_id', true );
	if ( $invited_trip_id && get_post( $invited_trip_id ) ) {
		return $invited_trip_id;
	}

	return 0;
}

add_action( 'rest_api_init', function () {

	register_rest_route( 'cb/v1', '/dismiss-trip-highlight', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function () {
			update_user_meta( get_current_user_id(), '_trip_interest_dismissed', 1 );
			return array( 'dismissed' => true );
		},
	) );

	/**
	 * Records intent only — no automatic role change. Phase 7's back-office
	 * screen is where an admin actually reviews and promotes; this just
	 * gives them a signal to act on, surfaced in the admin usermeta panel
	 * below and in the Trip Guests list's "Requested Full Membership"
	 * column (sorted to the top there, since it's the actionable subset).
	 */
	register_rest_route( 'cb/v1', '/request-full-membership', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function () {
			update_user_meta( get_current_user_id(), '_requested_full_membership_date', current_time( 'mysql' ) );
			return array( 'requested' => true );
		},
	) );

} );

/**
 * Renders one card per trip — title, "View trip", "Generate invite link" —
 * shared by the Trip Guest scoped dashboard and the Full Member "Your
 * Trips" section. Same actions either way: any roster member, Guest or
 * Full Member, can invite others to a trip they're already on.
 */
function cbv_render_dashboard_trip_cards( $trips ) {
	$presets = function_exists( 'cbv_get_cover_photo_presets' ) ? cbv_get_cover_photo_presets() : array();

	ob_start();
	foreach ( $trips as $trip ) :
		$cover_url = function_exists( 'cbv_get_trip_cover_photo_url' ) ? cbv_get_trip_cover_photo_url( $trip->ID, 'medium' ) : '';
		?>
		<div class="dashboard-trip-card" id="dashboard-trip-<?php echo (int) $trip->ID; ?>">
			<?php if ( $cover_url ) : ?>
				<div class="dashboard-trip-cover" style="background-image:url('<?php echo esc_url( $cover_url ); ?>');"></div>
			<?php endif; ?>
			<h3 class="dashboard-trip-card-title"><?php echo esc_html( get_the_title( $trip ) ); ?></h3>
			<div class="dashboard-trip-card-actions">
				<a href="<?php echo esc_url( get_permalink( $trip ) ); ?>" class="btn btn-ghost">View trip</a>
				<button type="button" class="btn btn-ticket cbv-invite-btn" data-trip-id="<?php echo (int) $trip->ID; ?>">Generate invite link</button>
				<button type="button" class="btn btn-ghost cbv-cover-toggle-btn" data-trip-id="<?php echo (int) $trip->ID; ?>">
					<?php echo $cover_url ? 'Change cover photo' : 'Add a cover photo'; ?>
				</button>
			</div>
			<div class="dashboard-invite-result" data-trip-id="<?php echo (int) $trip->ID; ?>"></div>

			<div class="dashboard-cover-picker" id="cbv-cover-picker-<?php echo (int) $trip->ID; ?>" style="display:none;">
				<?php if ( ! empty( $presets ) ) : ?>
					<p class="dashboard-cover-picker-label">Choose a preset:</p>
					<div class="dashboard-cover-picker-grid">
						<?php foreach ( $presets as $preset_id ) :
							$thumb = wp_get_attachment_image_url( $preset_id, 'thumbnail' );
							if ( ! $thumb ) {
								continue;
							}
							?>
							<button type="button" class="dashboard-cover-preset-btn" data-trip-id="<?php echo (int) $trip->ID; ?>" data-attachment-id="<?php echo (int) $preset_id; ?>" style="background-image:url('<?php echo esc_url( $thumb ); ?>');" aria-label="Use this preset"></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<p class="dashboard-cover-picker-label">Or upload your own:</p>
				<label class="btn btn-ghost dashboard-cover-upload-label">
					Upload photo
					<input type="file" accept="image/*" class="dashboard-cover-upload-input" data-trip-id="<?php echo (int) $trip->ID; ?>" hidden>
				</label>
				<div class="dashboard-cover-result" data-trip-id="<?php echo (int) $trip->ID; ?>"></div>
			</div>
		</div>
		<?php
	endforeach;
	return ob_get_clean();
}

/**
 * "Full Member" has no distinct WP role (see Phase 2) -- it's just any
 * logged-in user who isn't specifically a Trip Guest. Use this wherever a
 * feature should be Full-Member-exclusive rather than merely "logged in",
 * e.g. Gate 12's vacation-request/suggestion flow.
 */
function cbv_user_is_full_member( $user_id = 0 ) {
	$user_id = $user_id ?: get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return false;
	}
	return ! in_array( 'trip_guest', (array) $user->roles, true );
}

/**
 * The "My Trips" sticky-note widget — a clickable index of trip names that
 * scrolls to/expands the matching card from cbv_render_dashboard_trip_cards()
 * (see the shared JS in template-dashboard.php that collapses those cards
 * by default and wires up these links). Shared by the Guest scoped view and
 * the Full Member "Your Trips" section, same as the cards themselves.
 */
function cbv_render_my_trips_sticky_note( $trips ) {
	if ( empty( $trips ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="dashboard-sticky-note" aria-label="My Trips">
		<span class="sticky-note-pin" aria-hidden="true"></span>
		<p class="sticky-note-heading">My Trips</p>
		<ul class="sticky-note-list">
			<?php foreach ( $trips as $trip ) : ?>
				<li>
					<a href="#dashboard-trip-<?php echo (int) $trip->ID; ?>" class="sticky-note-trip-link" data-trip-id="<?php echo (int) $trip->ID; ?>">
						<?php echo esc_html( get_the_title( $trip ) ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   PHASE 6 — Cover photo picker + Trip Itinerary PDF
   ========================================================================== */

define( 'CBV_COVER_PHOTO_MAX_BYTES', 8 * 1024 * 1024 ); // 8 MB

add_action( 'init', function () {
	register_post_meta( 'cb_trip', 'cb_cover_photo', array(
		'type'              => 'integer',
		'single'            => true,
		'default'           => 0,
		'show_in_rest'      => true,
		'sanitize_callback' => 'absint',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'cb_trip', 'cb_itinerary_pdf', array(
		'type'              => 'integer',
		'single'            => true,
		'default'           => 0,
		'show_in_rest'      => true,
		'sanitize_callback' => 'absint',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );
} );

function cbv_get_trip_cover_photo_url( $trip_id, $size = 'large' ) {
	$attachment_id = (int) get_post_meta( $trip_id, 'cb_cover_photo', true );
	if ( ! $attachment_id ) {
		return '';
	}
	return wp_get_attachment_image_url( $attachment_id, $size ) ?: '';
}

function cbv_get_trip_itinerary_pdf_url( $trip_id ) {
	$attachment_id = (int) get_post_meta( $trip_id, 'cb_itinerary_pdf', true );
	if ( ! $attachment_id ) {
		return '';
	}
	return wp_get_attachment_url( $attachment_id ) ?: '';
}

/**
 * Admin-curated pool of stock cover images, managed on its own settings
 * screen below. Per spec: "or a flat grid if the pool is small" -- there
 * are no real stock photos to seed this with yet, so this ships as the
 * flat-grid variant; category grouping can be layered on later once admin
 * has actually populated a pool worth grouping.
 */
function cbv_get_cover_photo_presets() {
	$presets = get_option( 'cbv_cover_photo_presets', array() );
	return is_array( $presets ) ? array_map( 'absint', $presets ) : array();
}

/* --------------------------------------------------------------------------
   Admin: cover photo + itinerary PDF meta box on the cb_trip edit screen,
   using the native WP media library picker (wp.media) rather than a raw
   file upload -- lets admin reuse an already-uploaded image/PDF instead of
   re-uploading, consistent with how WP handles featured images.
   -------------------------------------------------------------------------- */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cbv_cover_pdf', 'Cover Photo & Itinerary PDF', 'cbv_render_cover_pdf_meta_box', 'cb_trip', 'side', 'default' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	global $post;
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && $post && $post->post_type === 'cb_trip' ) {
		wp_enqueue_media();
	}
	if ( $hook === 'settings_page_cbv-cover-presets' ) {
		wp_enqueue_media();
	}
} );

function cbv_render_cover_pdf_meta_box( $post ) {
	wp_nonce_field( 'cbv_cover_pdf_save', 'cbv_cover_pdf_nonce' );

	$cover_id  = (int) get_post_meta( $post->ID, 'cb_cover_photo', true );
	$pdf_id    = (int) get_post_meta( $post->ID, 'cb_itinerary_pdf', true );
	$cover_url = $cover_id ? wp_get_attachment_image_url( $cover_id, 'medium' ) : '';
	$pdf_url   = $pdf_id ? wp_get_attachment_url( $pdf_id ) : '';
	$pdf_name  = $pdf_id ? basename( get_attached_file( $pdf_id ) ) : '';
	?>
	<p><strong>Cover photo</strong></p>
	<div id="cbv-cover-preview" style="margin-bottom:8px;">
		<?php if ( $cover_url ) : ?>
			<img src="<?php echo esc_url( $cover_url ); ?>" style="max-width:100%;height:auto;border-radius:4px;">
		<?php else : ?>
			<p style="color:#888;"><em>No cover photo set.</em></p>
		<?php endif; ?>
	</div>
	<input type="hidden" name="cbv_cover_photo_id" id="cbv_cover_photo_id" value="<?php echo esc_attr( $cover_id ); ?>">
	<p>
		<button type="button" class="button" id="cbv-select-cover">Select image</button>
		<button type="button" class="button" id="cbv-remove-cover" style="<?php echo $cover_id ? '' : 'display:none;'; ?>">Remove</button>
	</p>

	<hr>

	<p><strong>Itinerary PDF</strong></p>
	<div id="cbv-pdf-preview" style="margin-bottom:8px;">
		<?php if ( $pdf_url ) : ?>
			<a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $pdf_name ); ?></a>
		<?php else : ?>
			<p style="color:#888;"><em>No PDF attached.</em></p>
		<?php endif; ?>
	</div>
	<input type="hidden" name="cbv_itinerary_pdf_id" id="cbv_itinerary_pdf_id" value="<?php echo esc_attr( $pdf_id ); ?>">
	<p>
		<button type="button" class="button" id="cbv-select-pdf">Select PDF</button>
		<button type="button" class="button" id="cbv-remove-pdf" style="<?php echo $pdf_id ? '' : 'display:none;'; ?>">Remove</button>
	</p>

	<script>
	(function () {
		// Programmatically setting .value never fires a native change/input
		// event -- but the Block Editor's legacy-meta-box compatibility layer
		// (which POSTs these fields via its own separate meta-box-loader
		// request, since Gutenberg doesn't submit the classic #post form at
		// all) relies on exactly those events to know a field changed and
		// needs including in that save. Without dispatching them here, the
		// preview updates on screen but the actual value never reaches the
		// server -- confirmed the hard way on Test Trip 5.
		function setFieldValue( id, value ) {
			var el = document.getElementById( id );
			el.value = value;
			el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		}

		function openPicker( inputId, previewId, removeBtnId, type, renderPreview ) {
			var frame = wp.media( { title: 'Select ' + ( type === 'image' ? 'Image' : 'PDF' ), library: { type: type }, multiple: false } );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				setFieldValue( inputId, attachment.id );
				document.getElementById( previewId ).innerHTML = renderPreview( attachment );
				document.getElementById( removeBtnId ).style.display = '';
			} );
			frame.open();
		}

		document.getElementById( 'cbv-select-cover' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			openPicker( 'cbv_cover_photo_id', 'cbv-cover-preview', 'cbv-remove-cover', 'image', function ( a ) {
				var url = ( a.sizes && a.sizes.medium ) ? a.sizes.medium.url : a.url;
				return '<img src="' + url + '" style="max-width:100%;height:auto;border-radius:4px;">';
			} );
		} );
		document.getElementById( 'cbv-remove-cover' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			setFieldValue( 'cbv_cover_photo_id', '0' );
			document.getElementById( 'cbv-cover-preview' ).innerHTML = '<p style="color:#888;"><em>No cover photo set.</em></p>';
			this.style.display = 'none';
		} );

		document.getElementById( 'cbv-select-pdf' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			openPicker( 'cbv_itinerary_pdf_id', 'cbv-pdf-preview', 'cbv-remove-pdf', 'application/pdf', function ( a ) {
				return '<a href="' + a.url + '" target="_blank" rel="noopener">' + a.filename + '</a>';
			} );
		} );
		document.getElementById( 'cbv-remove-pdf' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			setFieldValue( 'cbv_itinerary_pdf_id', '0' );
			document.getElementById( 'cbv-pdf-preview' ).innerHTML = '<p style="color:#888;"><em>No PDF attached.</em></p>';
			this.style.display = 'none';
		} );
	})();
	</script>
	<?php
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_cover_pdf_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_cover_pdf_nonce'], 'cbv_cover_pdf_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['cbv_cover_photo_id'] ) ) {
		update_post_meta( $post_id, 'cb_cover_photo', absint( $_POST['cbv_cover_photo_id'] ) );
	}
	if ( isset( $_POST['cbv_itinerary_pdf_id'] ) ) {
		update_post_meta( $post_id, 'cb_itinerary_pdf', absint( $_POST['cbv_itinerary_pdf_id'] ) );
	}
} );

/* --------------------------------------------------------------------------
   Admin: Settings -> Cover Photo Presets -- the curated pool members pick
   from on their dashboard, separate from uploading their own.
   -------------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_options_page( 'Cover Photo Presets', 'Cover Photo Presets', 'manage_options', 'cbv-cover-presets', 'cbv_render_cover_presets_page' );
} );

function cbv_render_cover_presets_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['cbv_presets_nonce'] ) && wp_verify_nonce( $_POST['cbv_presets_nonce'], 'cbv_save_presets' ) ) {
		$raw = isset( $_POST['cbv_preset_ids'] ) ? wp_unslash( $_POST['cbv_preset_ids'] ) : '';
		$ids = array_values( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );
		update_option( 'cbv_cover_photo_presets', $ids, false );
		echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Saved %d preset image(s).', 'cbv' ), count( $ids ) ) . '</p></div>';
	}

	$presets = cbv_get_cover_photo_presets();
	?>
	<div class="wrap">
		<h1>Cover Photo Presets</h1>
		<p class="description">Members picking a cover photo for their trip from the dashboard can choose from this curated pool instead of uploading their own.</p>
		<form method="post">
			<?php wp_nonce_field( 'cbv_save_presets', 'cbv_presets_nonce' ); ?>
			<div id="cbv-presets-grid" style="display:flex;flex-wrap:wrap;gap:10px;margin:15px 0;">
				<?php foreach ( $presets as $id ) :
					$url = wp_get_attachment_image_url( $id, 'thumbnail' );
					if ( ! $url ) {
						continue;
					}
					?>
					<div class="cbv-preset-item" data-id="<?php echo esc_attr( $id ); ?>" style="position:relative;">
						<img src="<?php echo esc_url( $url ); ?>" style="width:120px;height:90px;object-fit:cover;border-radius:4px;">
						<button type="button" class="button cbv-remove-preset" style="position:absolute;top:2px;right:2px;padding:0 4px;line-height:1.6;">&times;</button>
					</div>
				<?php endforeach; ?>
			</div>
			<input type="hidden" name="cbv_preset_ids" id="cbv_preset_ids" value="<?php echo esc_attr( implode( ',', $presets ) ); ?>">
			<p>
				<button type="button" class="button" id="cbv-add-presets">Add images</button>
				<button type="submit" class="button button-primary">Save presets</button>
			</p>
		</form>
	</div>
	<script>
	(function () {
		function syncHidden() {
			var ids = Array.prototype.map.call( document.querySelectorAll( '.cbv-preset-item' ), function ( el ) { return el.getAttribute( 'data-id' ); } );
			document.getElementById( 'cbv_preset_ids' ).value = ids.join( ',' );
		}
		document.getElementById( 'cbv-presets-grid' ).addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'cbv-remove-preset' ) ) {
				e.target.closest( '.cbv-preset-item' ).remove();
				syncHidden();
			}
		} );
		document.getElementById( 'cbv-add-presets' ).addEventListener( 'click', function ( e ) {
			e.preventDefault();
			var frame = wp.media( { title: 'Select preset images', library: { type: 'image' }, multiple: true } );
			frame.on( 'select', function () {
				var grid = document.getElementById( 'cbv-presets-grid' );
				frame.state().get( 'selection' ).each( function ( attachment ) {
					var a = attachment.toJSON();
					var url = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
					var div = document.createElement( 'div' );
					div.className = 'cbv-preset-item';
					div.setAttribute( 'data-id', a.id );
					div.style.position = 'relative';
					div.innerHTML = '<img src="' + url + '" style="width:120px;height:90px;object-fit:cover;border-radius:4px;">' +
						'<button type="button" class="button cbv-remove-preset" style="position:absolute;top:2px;right:2px;padding:0 4px;line-height:1.6;">&times;</button>';
					grid.appendChild( div );
				} );
				syncHidden();
			} );
			frame.open();
		} );
	})();
	</script>
	<?php
}

/* --------------------------------------------------------------------------
   REST: client-facing cover photo selection/upload, and the preset list.
   -------------------------------------------------------------------------- */
add_action( 'rest_api_init', function () {

	register_rest_route( 'cb/v1', '/cover-photo-presets', array(
		'methods'             => 'GET',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function () {
			$presets = cbv_get_cover_photo_presets();
			return array_values( array_filter( array_map( function ( $id ) {
				$url = wp_get_attachment_image_url( $id, 'medium' );
				return $url ? array( 'id' => $id, 'url' => $url ) : null;
			}, $presets ) ) );
		},
	) );

	register_rest_route( 'cb/v1', '/trips/(?P<id>\d+)/cover-photo', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function ( $request ) {
			$trip_id       = (int) $request['id'];
			$user_id       = get_current_user_id();
			$attachment_id = (int) $request->get_param( 'attachment_id' );

			// Admin bypass: cover photo is a trip-level management setting any
			// roster member can already change, not personal traveler data --
			// same "not on roster, but an admin" carve-out used elsewhere.
			if ( ! in_array( $user_id, cb_trip_get_roster( $trip_id ), true ) && ! user_can( $user_id, 'manage_options' ) ) {
				return new WP_Error( 'cbv_no_access', 'You need access to this trip to change its cover photo.', array( 'status' => 403 ) );
			}
			if ( ! $attachment_id || ! wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) ) {
				return new WP_Error( 'cbv_invalid_attachment', 'That image could not be found.', array( 'status' => 400 ) );
			}

			// Only allow admin-curated presets or an attachment this same
			// member uploaded themselves -- not an arbitrary attachment ID
			// belonging to someone else's media.
			$presets       = cbv_get_cover_photo_presets();
			$is_preset     = in_array( $attachment_id, $presets, true );
			$attachment    = get_post( $attachment_id );
			$is_own_upload = $attachment && (int) $attachment->post_author === $user_id;

			if ( ! $is_preset && ! $is_own_upload ) {
				return new WP_Error( 'cbv_not_allowed', 'You can only use preset images or your own uploads.', array( 'status' => 403 ) );
			}

			update_post_meta( $trip_id, 'cb_cover_photo', $attachment_id );

			return array( 'success' => true, 'url' => wp_get_attachment_image_url( $attachment_id, 'large' ) );
		},
	) );

	register_rest_route( 'cb/v1', '/trips/(?P<id>\d+)/cover-photo/upload', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function ( $request ) {
			$trip_id = (int) $request['id'];
			$user_id = get_current_user_id();

			// Admin bypass: cover photo is a trip-level management setting any
			// roster member can already change, not personal traveler data --
			// same "not on roster, but an admin" carve-out used elsewhere.
			if ( ! in_array( $user_id, cb_trip_get_roster( $trip_id ), true ) && ! user_can( $user_id, 'manage_options' ) ) {
				return new WP_Error( 'cbv_no_access', 'You need access to this trip to change its cover photo.', array( 'status' => 403 ) );
			}

			$files = $request->get_file_params();
			if ( empty( $files['photo'] ) ) {
				return new WP_Error( 'cbv_no_file', 'No photo was received.', array( 'status' => 400 ) );
			}
			if ( (int) $files['photo']['size'] > CBV_COVER_PHOTO_MAX_BYTES ) {
				return new WP_Error( 'cbv_file_too_large', 'That image is too large — please use a file under 8 MB.', array( 'status' => 400 ) );
			}

			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';

			// Explicit mimes override -- media_handle_upload() alone only
			// enforces WP's sitewide "not a dangerous extension" allowlist
			// (which includes non-image types like PDFs/zips/docs), not
			// "must be an image." This restricts wp_handle_upload()'s own
			// validation to just these image types, rejecting anything else
			// before the file is even moved into the uploads directory.
			$attachment_id = media_handle_upload( 'photo', $trip_id, array(), array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'gif'          => 'image/gif',
					'png'          => 'image/png',
					'webp'         => 'image/webp',
				),
			) );
			if ( is_wp_error( $attachment_id ) ) {
				return new WP_Error( 'cbv_upload_failed', $attachment_id->get_error_message(), array( 'status' => 400 ) );
			}

			update_post_meta( $trip_id, 'cb_cover_photo', $attachment_id );

			return array( 'success' => true, 'url' => wp_get_attachment_image_url( $attachment_id, 'large' ) );
		},
	) );

} );

/* ==========================================================================
   PHASE 7 — Back-office Trip Guests screen + promote action

   A single list view of every Trip Guest, instead of checking users one at
   a time via the read-only panel above. "Promote to Full Member" is purely
   additive: it swaps the trip_guest role for subscriber (WP's normal
   baseline role, same as any directly-registered member gets) and touches
   nothing else -- roster membership, invite history, and trip access all
   stay exactly as they were, since none of that is gated on the role name
   itself (see cbv_user_is_full_member() and cb_trip_get_roster() above).
   ========================================================================== */
add_action( 'admin_menu', function () {
	add_users_page(
		'Trip Guests',
		'Trip Guests',
		'manage_options',
		'cbv-trip-guests',
		'cbv_render_trip_guests_page'
	);
} );

function cbv_render_trip_guests_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$guests = get_users( array(
		'role'    => 'trip_guest',
		'orderby' => 'registered',
		'order'   => 'DESC',
	) );

	// Guests who've asked to be promoted float to the top -- that's the
	// actionable subset an admin lands on this screen to find; everyone
	// else keeps the existing newest-registered-first order beneath them.
	// The "surfaced... in Phase 7's Trip Guests list" this file's own
	// earlier doc comments promised, never actually built until now.
	usort( $guests, function ( $a, $b ) {
		$a_requested = (bool) get_user_meta( $a->ID, '_requested_full_membership_date', true );
		$b_requested = (bool) get_user_meta( $b->ID, '_requested_full_membership_date', true );
		if ( $a_requested === $b_requested ) {
			return 0;
		}
		return $a_requested ? -1 : 1;
	} );
	?>
	<div class="wrap">
		<h1>Trip Guests</h1>

		<?php if ( isset( $_GET['cbv_promoted'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>That guest has been promoted to Full Member.</p></div>
		<?php endif; ?>

		<?php if ( empty( $guests ) ) : ?>
			<p><em>No Trip Guests right now.</em></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Name</th>
						<th>Email</th>
						<th>Trip</th>
						<th>Invited by</th>
						<th>Join date</th>
						<th>Requested Full Membership</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $guests as $guest ) :
						$invited_by_id = get_user_meta( $guest->ID, '_invited_by_user_id', true );
						$invited_trip  = get_user_meta( $guest->ID, '_invited_by_trip_id', true );
						$inviter       = $invited_by_id ? get_userdata( $invited_by_id ) : false;
						$trip          = $invited_trip ? get_post( $invited_trip ) : false;
						$requested     = get_user_meta( $guest->ID, '_requested_full_membership_date', true );
						?>
						<tr<?php echo $requested ? ' style="background-color:#fff8e5;"' : ''; ?>>
							<td><a href="<?php echo esc_url( get_edit_user_link( $guest->ID ) ); ?>"><?php echo esc_html( $guest->display_name ); ?></a></td>
							<td><?php echo esc_html( $guest->user_email ); ?></td>
							<td>
								<?php if ( $trip && 'cb_trip' === $trip->post_type ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $trip->ID ) ); ?>"><?php echo esc_html( get_the_title( $trip ) ); ?></a>
								<?php else : ?>
									<em>&#8212;</em>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $inviter ) : ?>
									<a href="<?php echo esc_url( get_edit_user_link( $inviter->ID ) ); ?>"><?php echo esc_html( $inviter->display_name ); ?></a>
								<?php else : ?>
									<em>&#8212;</em>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( date_i18n( 'M j, Y', strtotime( $guest->user_registered ) ) ); ?></td>
							<td>
								<?php if ( $requested ) : ?>
									<strong style="color:#b45900;">&#9733; Requested <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $requested ) ) ); ?></strong>
								<?php else : ?>
									<em>&#8212;</em>
								<?php endif; ?>
							</td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( wp_nonce_url(
									admin_url( 'admin-post.php?action=cbv_promote_trip_guest&user_id=' . $guest->ID ),
									'cbv_promote_trip_guest_' . $guest->ID
								) ); ?>">Promote to Full Member</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_post_cbv_promote_trip_guest', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient permissions.' );
	}

	$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;

	if ( ! $user_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'cbv_promote_trip_guest_' . $user_id ) ) {
		wp_die( 'Invalid request.' );
	}

	$user = get_userdata( $user_id );
	if ( $user ) {
		$user->remove_role( 'trip_guest' );
		if ( ! in_array( 'subscriber', (array) $user->roles, true ) ) {
			$user->add_role( 'subscriber' );
		}
	}

	wp_safe_redirect( add_query_arg(
		'cbv_promoted', '1',
		admin_url( 'users.php?page=cbv-trip-guests' )
	) );
	exit;
} );

/* ==========================================================================
   PHASE 8a — Member Profile (one-time, account-level, not trip-specific)

   Lives as a custom tab on UM's existing /account/ page rather than a new
   page -- "wherever makes sense on the member's account area" is literally
   the account area UM already provides. UM wraps every tab's content in
   ONE shared <form> for the whole page (see templates/account.php), so this
   tab's markup deliberately has NO <form> of its own and no name= attributes
   on its inputs -- exactly the nested-form trap that broke the roster
   Remove button earlier in this build. Fields are read by id and posted via
   fetch(), matching every other REST-backed form in this codebase.

   Storage: plain usermeta, unregistered -- same convention as
   _invited_by_user_id / _accepted_terms_version elsewhere in this file.
   No passport number field, ever (per spec).
   ========================================================================== */
define( 'CBV_ACTIVITY_INTERESTS', array(
	'Sightseeing/History', 'Culture/Arts', 'Beach/Sun', 'Active/Sports', 'Wine/Culinary', 'Shopping', 'Spa',
) );

// Seat position only -- a subset of Gate 12's broader Air Travel seat-
// preference checkbox list, so a trip-wide fallback value here is still
// correctly-domained (just occasionally imprecise if the organizer also
// checked fare-class boxes alongside position ones).
define( 'CBV_SEAT_POSITIONS', array( 'Aisle', 'Middle', 'Window' ) );

// Flight fare class -- deliberately NOT the same option set as Gate 12's
// Cruise Vacation cabin-class dropdown (Interior/Oceanview/Balcony/Suite).
// Both fields were originally named "Cabin Class" but describe different
// things (this is about a flight seat, Gate 12's is about a cruise cabin),
// so they're exported as two separate columns with no fallback between
// them -- see cbv_build_trip_roster_export_data().
define( 'CBV_FLIGHT_CABIN_CLASSES', array( 'Economy', 'Extra Leg Room/Premium', 'Business Class', 'First Class' ) );

// Shared airline list -- every "airline preference" field sitewide (Gate 12,
// Per-Traveler Intake) uses this same fixed set via cbv_render_airline_field()
// below, so adding an airline means changing it in exactly one place.
define( 'CBV_AIRLINES', array(
	'Delta Air Lines',
	'American Airlines',
	'United Airlines',
	'Southwest Airlines',
	'Alaska Airlines',
	'Qatar Airways',
	'Singapore Airlines',
	'Emirates',
	'Turkish Airlines',
	'Air France',
	'Other',
) );

/**
 * Renders an airline <select> (fixed CBV_AIRLINES list) plus a free-text
 * field that only appears once "Other" is chosen -- shared by every airline
 * field sitewide so both forms stay in sync automatically. The select keeps
 * $id as its own id (so existing JS that reads the field by that id doesn't
 * need to know about the "Other" field at all -- callers combine the two
 * themselves, same as the existing cbv-intake-insurance-decision/waiver-row
 * pattern already does for a different field).
 */
function cbv_render_airline_field( $id, $label, $current_value = '', $required = false ) {
	$is_known = in_array( $current_value, CBV_AIRLINES, true );
	$is_other = '' !== $current_value && ! $is_known;
	?>
	<label><?php echo esc_html( $label ); ?> <?php if ( $required ) : ?><span class="cbv-required" aria-hidden="true">*</span><?php endif; ?>
		<select id="<?php echo esc_attr( $id ); ?>" class="cbv-airline-select" <?php echo $required ? 'required' : ''; ?>>
			<option value="">—</option>
			<?php foreach ( CBV_AIRLINES as $airline ) : ?>
				<option value="<?php echo esc_attr( $airline ); ?>" <?php selected( $is_other ? 'Other' : $current_value, $airline ); ?>><?php echo esc_html( $airline ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<input type="text" id="<?php echo esc_attr( $id . '-other' ); ?>" class="cbv-airline-other" placeholder="Enter airline name" value="<?php echo esc_attr( $is_other ? $current_value : '' ); ?>" style="<?php echo $is_other ? '' : 'display:none;'; ?>">
	<?php
}

// Same option set as Gate 12's Cruise Vacation cabin-class dropdown, on
// purpose -- unlike flight cabin class, a traveler's own cruise room-type
// answer and the organizer's trip-wide answer describe the exact same
// real-world thing, just at different granularity, so a fallback between
// them is meaningful (see the " *" marking in cbv_build_trip_roster_export_data()).
define( 'CBV_CRUISE_CABIN_CLASSES', array( 'Interior', 'Oceanview', 'Balcony', 'Suite' ) );

// Cruise Duration replaces the old free-text Cruise Length field going
// forward on both Gate 12 and Per-Traveler Intake -- Cruise Length is no
// longer a form input on either, but its stored data (old cb_req_ meta and
// any old per-traveler intake values) stays readable as a fallback, see
// cbv_build_trip_roster_export_data().
define( 'CBV_CRUISE_DURATIONS', array( '3–5 days', '7 days', '10–21 days', '30 days' ) );

define( 'CBV_CRUISE_REGIONS', array(
	'The Caribbean, Bahamas & Mexico' => array(
		'Western Caribbean',
		'Eastern Caribbean',
		'Southern Caribbean',
		'Mexican Riviera',
	),
	'Alaska & The Pacific Northwest' => array(
		'Inside Passage Roundtrip',
		'Gulf of Alaska / One-Way',
	),
	'Europe & The Mediterranean' => array(
		'Western Mediterranean',
		'Eastern Mediterranean & Greek Isles',
		'Northern Europe & Norwegian Fjords',
	),
	'Australasia' => array(
		'Sydney, Australia',
		'Melbourne, Australia',
		'Auckland, New Zealand',
	),
) );

define( 'CBV_CRUISE_DEPARTURE_PORTS', array(
	'North America & The Caribbean' => array(
		'Miami, Florida (Port Miami)',
		'Port Canaveral (Orlando area)',
		'San Juan, Puerto Rico',
		'New York City, New York',
		'Los Angeles, California',
		'Seattle, Washington',
		'Vancouver, British Columbia, Canada',
		'Port of Nassau',
		'Port of Galveston (Texas)',
		'Port Everglades (Fort Lauderdale)',
		'Port of Cozumel',
	),
	'Europe & The Mediterranean' => array(
		'Barcelona, Spain',
		'Athens (Piraeus), Greece',
		'Rome (Civitavecchia), Italy',
		'Portsmouth, United Kingdom',
		'Port of Southampton',
		'Port of Marseille',
		'Reykjavik, Iceland',
	),
	'Australasia' => array(
		'Sydney, Australia',
		'Melbourne, Australia',
		'Auckland, New Zealand',
	),
) );

/** Renders <optgroup>/<option> markup for a grouped dropdown array. */
function cbv_render_optgroup_options( $groups, $selected ) {
	foreach ( $groups as $group_label => $options ) {
		echo '<optgroup label="' . esc_attr( $group_label ) . '">';
		foreach ( $options as $option ) {
			echo '<option value="' . esc_attr( $option ) . '" ' . selected( $selected, $option, false ) . '>' . esc_html( $option ) . '</option>';
		}
		echo '</optgroup>';
	}
}

/** Flattens a grouped dropdown array into a plain list of valid values. */
function cbv_flatten_grouped_options( $groups ) {
	return array_merge( ...array_values( $groups ) );
}

/**
 * Whether this trip has a specific cb_trip_type term assigned (matched by
 * term name, e.g. 'Cruise', 'Flight', 'Resort' -- see checkedbags-trips.php).
 */
function cbv_trip_has_type( $trip_id, $type_name ) {
	$terms = get_the_terms( $trip_id, 'cb_trip_type' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return false;
	}
	foreach ( $terms as $term ) {
		if ( $term->name === $type_name ) {
			return true;
		}
	}
	return false;
}

add_filter( 'um_account_page_default_tabs_hook', function ( $tabs ) {
	$tabs[250]['travel-profile'] = array(
		'icon'        => 'um-faicon-suitcase',
		'title'       => 'Travel Profile',
		'custom'      => true,
		// UM would otherwise render its own default submit button (labeled
		// with the tab title, i.e. "Travel Profile") below our own custom
		// "Save Travel Profile" button -- both post the same form, so it's
		// a redundant duplicate specific to this tab, not needed elsewhere.
		'show_button' => false,
	);
	return $tabs;
} );

// The Account page (real WP page, slug "account", content is just the
// [ultimatemember_account] shortcode) had no visible way to log out --
// only the shared header nav's Logout link, easy to miss (especially
// behind the mobile "Menu" toggle). Appends a direct Logout button after
// UM's own rendered tabs, same /logout/ URL already used everywhere else
// in this codebase's nav (a real page UM itself handles, not a raw
// wp_logout_url() call, so behavior matches every other Logout link
// exactly). Same is_page() + late-priority the_content idiom already used
// for the reaccept-terms/logout redirect above, just additive instead of
// a redirect.
add_filter( 'the_content', function ( $content ) {
	if ( ! is_page( 'account' ) || ! is_user_logged_in() ) {
		return $content;
	}
	return $content . '<p class="cb-account-logout"><a href="' . esc_url( home_url( '/logout/' ) ) . '" class="btn btn-ghost">Log Out</a></p>';
}, 20 );

// The Account page's title is the one shared WP Page title ("Account") used
// for every UM tab (General, Password, Privacy, etc.) -- only override it
// when the Travel Profile tab specifically is active, via the same um_tab
// query var UM itself uses to pick the tab (see tab_link() in UM's
// class-account.php), so every other tab keeps seeing "Account".
add_filter( 'the_title', function ( $title ) {
	if ( in_the_loop() && is_page( 'account' ) && 'travel-profile' === get_query_var( 'um_tab' ) ) {
		return 'Travel Profile';
	}
	return $title;
} );

/**
 * Falls back to the legacy single _legal_name field for anyone who filled
 * that in before First/Last Name replaced it, so nothing already entered
 * gets silently lost.
 */
function cbv_get_member_full_name( $user_id ) {
	$first = get_user_meta( $user_id, '_first_name', true );
	$last  = get_user_meta( $user_id, '_last_name', true );
	if ( $first || $last ) {
		return trim( $first . ' ' . $last );
	}
	return get_user_meta( $user_id, '_legal_name', true );
}

/**
 * @return array{0: string, 1: string} [first name, last name]. Falls back
 * to a naive split of the legacy single _legal_name field (first word vs.
 * the rest) for anyone who filled that in before this was two fields.
 */
function cbv_get_member_name_parts( $user_id ) {
	$first = get_user_meta( $user_id, '_first_name', true );
	$last  = get_user_meta( $user_id, '_last_name', true );
	if ( $first || $last ) {
		return array( $first, $last );
	}
	$legacy = trim( get_user_meta( $user_id, '_legal_name', true ) );
	if ( ! $legacy ) {
		return array( '', '' );
	}
	$parts = explode( ' ', $legacy, 2 );
	return array( $parts[0], $parts[1] ?? '' );
}

/**
 * @return array{0: string, 1: string, 2: string, 3: string} [street, city, state, zip].
 * Falls back to putting the legacy single _address field entirely into
 * "street" for anyone who filled that in before this was four fields --
 * there's no reliable way to split a free-text address into parts.
 */
function cbv_get_member_address_parts( $user_id ) {
	$street = get_user_meta( $user_id, '_address_street', true );
	$city   = get_user_meta( $user_id, '_address_city', true );
	$state  = get_user_meta( $user_id, '_address_state', true );
	$zip    = get_user_meta( $user_id, '_address_zip', true );
	if ( $street || $city || $state || $zip ) {
		return array( $street, $city, $state, $zip );
	}
	return array( get_user_meta( $user_id, '_address', true ), '', '', '' );
}

function cbv_user_profile_is_complete( $user_id ) {
	return (bool) cbv_get_member_full_name( $user_id );
}

add_filter( 'um_account_content_hook_travel-profile', function ( $output, $args ) {
	$user_id = get_current_user_id();

	// Falls back to the name captured at registration (WP/UM's own native
	// first_name/last_name usermeta) the first time this page is opened --
	// _first_name/_last_name are this app's own separate Travel-Profile-
	// specific fields (see CBV_AIRLINES-style constants above for why this
	// codebase keeps its own meta keys rather than reusing UM's), so
	// without this fallback a brand new Travel Profile showed blank name
	// fields despite the member having already typed their name once.
	$user          = get_userdata( $user_id );
	$first_name    = get_user_meta( $user_id, '_first_name', true ) ?: ( $user ? $user->first_name : '' );
	$last_name     = get_user_meta( $user_id, '_last_name', true ) ?: ( $user ? $user->last_name : '' );
	$dob           = get_user_meta( $user_id, '_date_of_birth', true );
	$phone         = get_user_meta( $user_id, '_phone', true );
	$address_street = get_user_meta( $user_id, '_address_street', true );
	$address_city  = get_user_meta( $user_id, '_address_city', true );
	$address_state = get_user_meta( $user_id, '_address_state', true );
	$address_zip   = get_user_meta( $user_id, '_address_zip', true );
	$ec_name       = get_user_meta( $user_id, '_emergency_contact_name', true );
	$ec_phone      = get_user_meta( $user_id, '_emergency_contact_phone', true );
	$has_passport  = get_user_meta( $user_id, '_has_passport', true );
	$passport_country = get_user_meta( $user_id, '_passport_country', true );
	$passport_exp  = get_user_meta( $user_id, '_passport_expiration', true );
	$dietary       = get_user_meta( $user_id, '_dietary_restrictions', true );
	$medical       = get_user_meta( $user_id, '_medical_mobility_needs', true );
	$travel_history = get_user_meta( $user_id, '_travel_history', true );
	$interests     = get_user_meta( $user_id, '_activity_interests', true );
	$interests     = is_array( $interests ) ? $interests : array();

	ob_start();
	?>
	<div class="cbv-travel-profile">
		<p class="cb-page-hint">The more we know, the smoother your trips go — this info helps us book flights, arrange rooms, and plan for any needs ahead of time.</p>
		<fieldset>
			<legend>Personal Details</legend>
			<div class="cbv-tp-row">
				<label>First name <input type="text" id="cbv-tp-first-name" value="<?php echo esc_attr( $first_name ); ?>"></label>
				<label>Last name <input type="text" id="cbv-tp-last-name" value="<?php echo esc_attr( $last_name ); ?>"></label>
			</div>
			<label>Date of birth <input type="date" id="cbv-tp-dob" value="<?php echo esc_attr( $dob ); ?>"></label>
			<label class="cbv-tp-inline">Phone <input type="tel" id="cbv-tp-phone" value="<?php echo esc_attr( $phone ); ?>"></label>
			<label>Street address <input type="text" id="cbv-tp-address-street" value="<?php echo esc_attr( $address_street ); ?>"></label>
			<div class="cbv-tp-row">
				<label>City <input type="text" id="cbv-tp-address-city" value="<?php echo esc_attr( $address_city ); ?>"></label>
				<label>State <input type="text" id="cbv-tp-address-state" value="<?php echo esc_attr( $address_state ); ?>"></label>
				<label>Zip code <input type="text" id="cbv-tp-address-zip" value="<?php echo esc_attr( $address_zip ); ?>"></label>
			</div>
		</fieldset>

		<fieldset>
			<legend>Emergency Contact</legend>
			<label>Name <input type="text" id="cbv-tp-ec-name" value="<?php echo esc_attr( $ec_name ); ?>"></label>
			<label>Phone <input type="tel" id="cbv-tp-ec-phone" value="<?php echo esc_attr( $ec_phone ); ?>"></label>
		</fieldset>

		<fieldset>
			<legend>Passport</legend>
			<label>Do you have a valid passport?
				<select id="cbv-tp-has-passport">
					<option value="" <?php selected( $has_passport, '' ); ?>>—</option>
					<option value="yes" <?php selected( $has_passport, 'yes' ); ?>>Yes</option>
					<option value="no" <?php selected( $has_passport, 'no' ); ?>>No</option>
				</select>
			</label>
			<label>Issuing country <input type="text" id="cbv-tp-passport-country" value="<?php echo esc_attr( $passport_country ); ?>"></label>
			<label>Expiration date <input type="date" id="cbv-tp-passport-exp" value="<?php echo esc_attr( $passport_exp ); ?>"></label>
			<p class="description">We never ask for or store your passport number.</p>
		</fieldset>

		<fieldset>
			<legend>Health &amp; Accessibility</legend>
			<label>Dietary restrictions / allergies <textarea id="cbv-tp-dietary" rows="2"><?php echo esc_textarea( $dietary ); ?></textarea></label>
			<label>Medical or mobility needs <textarea id="cbv-tp-medical" rows="2"><?php echo esc_textarea( $medical ); ?></textarea></label>
		</fieldset>

		<fieldset>
			<legend>Travel Preferences</legend>
			<label>Hotels/cruiselines you've enjoyed before <textarea id="cbv-tp-travel-history" rows="2"><?php echo esc_textarea( $travel_history ); ?></textarea></label>
			<p class="description">Activities you enjoy when traveling:</p>
			<?php foreach ( CBV_ACTIVITY_INTERESTS as $option ) : ?>
				<label class="check-row">
					<input type="checkbox" class="cbv-tp-interest" value="<?php echo esc_attr( $option ); ?>" <?php checked( in_array( $option, $interests, true ) ); ?>>
					<?php echo esc_html( $option ); ?>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<button type="button" class="btn btn-ticket" id="cbv-tp-save">Save Travel Profile</button>
		<span id="cbv-tp-result" style="margin-left:10px;"></span>
	</div>
	<script>
	(function () {
		var btn = document.getElementById( 'cbv-tp-save' );
		if ( ! btn ) { return; }
		btn.addEventListener( 'click', function () {
			var interests = Array.prototype.map.call(
				document.querySelectorAll( '.cbv-tp-interest:checked' ),
				function ( el ) { return el.value; }
			);
			var payload = {
				first_name: document.getElementById( 'cbv-tp-first-name' ).value,
				last_name: document.getElementById( 'cbv-tp-last-name' ).value,
				date_of_birth: document.getElementById( 'cbv-tp-dob' ).value,
				phone: document.getElementById( 'cbv-tp-phone' ).value,
				address_street: document.getElementById( 'cbv-tp-address-street' ).value,
				address_city: document.getElementById( 'cbv-tp-address-city' ).value,
				address_state: document.getElementById( 'cbv-tp-address-state' ).value,
				address_zip: document.getElementById( 'cbv-tp-address-zip' ).value,
				emergency_contact_name: document.getElementById( 'cbv-tp-ec-name' ).value,
				emergency_contact_phone: document.getElementById( 'cbv-tp-ec-phone' ).value,
				has_passport: document.getElementById( 'cbv-tp-has-passport' ).value,
				passport_country: document.getElementById( 'cbv-tp-passport-country' ).value,
				passport_expiration: document.getElementById( 'cbv-tp-passport-exp' ).value,
				dietary_restrictions: document.getElementById( 'cbv-tp-dietary' ).value,
				medical_mobility_needs: document.getElementById( 'cbv-tp-medical' ).value,
				travel_history: document.getElementById( 'cbv-tp-travel-history' ).value,
				activity_interests: interests
			};
			btn.disabled = true;
			btn.textContent = 'Saving...';
			fetch( <?php echo wp_json_encode( esc_url_raw( rest_url( 'cb/v1/profile' ) ) ); ?>, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?> },
				body: JSON.stringify( payload )
			} )
				.then( function ( res ) { return res.json(); } )
				.then( function ( data ) {
					btn.disabled = false;
					btn.textContent = 'Save Travel Profile';
					document.getElementById( 'cbv-tp-result' ).textContent = data.saved ? 'Saved!' : 'Could not save.';
				} )
				.catch( function () {
					btn.disabled = false;
					btn.textContent = 'Save Travel Profile';
					document.getElementById( 'cbv-tp-result' ).textContent = 'Something went wrong.';
				} );
		} );
	})();
	</script>
	<?php
	return ob_get_clean();
}, 10, 2 );

add_action( 'rest_api_init', function () {
	register_rest_route( 'cb/v1', '/profile', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => 'cbv_save_member_profile',
	) );

	register_rest_route( 'cb/v1', '/dismiss-profile-nudge', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function () {
			update_user_meta( get_current_user_id(), '_profile_nudge_dismissed', 1 );
			return array( 'dismissed' => true );
		},
	) );
} );

function cbv_save_member_profile( $request ) {
	$body    = $request->get_json_params();
	$user_id = get_current_user_id();

	$str = function ( $key ) use ( $body ) {
		return isset( $body[ $key ] ) ? sanitize_text_field( $body[ $key ] ) : '';
	};
	$txt = function ( $key ) use ( $body ) {
		return isset( $body[ $key ] ) ? sanitize_textarea_field( $body[ $key ] ) : '';
	};

	update_user_meta( $user_id, '_first_name', $str( 'first_name' ) );
	update_user_meta( $user_id, '_last_name', $str( 'last_name' ) );
	update_user_meta( $user_id, '_date_of_birth', $str( 'date_of_birth' ) );
	update_user_meta( $user_id, '_phone', $str( 'phone' ) );
	update_user_meta( $user_id, '_address_street', $str( 'address_street' ) );
	update_user_meta( $user_id, '_address_city', $str( 'address_city' ) );
	update_user_meta( $user_id, '_address_state', $str( 'address_state' ) );
	update_user_meta( $user_id, '_address_zip', $str( 'address_zip' ) );
	update_user_meta( $user_id, '_emergency_contact_name', $str( 'emergency_contact_name' ) );
	update_user_meta( $user_id, '_emergency_contact_phone', $str( 'emergency_contact_phone' ) );

	$has_passport = $str( 'has_passport' );
	update_user_meta( $user_id, '_has_passport', in_array( $has_passport, array( 'yes', 'no' ), true ) ? $has_passport : '' );
	update_user_meta( $user_id, '_passport_country', $str( 'passport_country' ) );
	update_user_meta( $user_id, '_passport_expiration', $str( 'passport_expiration' ) );

	update_user_meta( $user_id, '_dietary_restrictions', $txt( 'dietary_restrictions' ) );
	update_user_meta( $user_id, '_medical_mobility_needs', $txt( 'medical_mobility_needs' ) );
	update_user_meta( $user_id, '_travel_history', $txt( 'travel_history' ) );

	$submitted_interests = isset( $body['activity_interests'] ) && is_array( $body['activity_interests'] )
		? array_map( 'sanitize_text_field', $body['activity_interests'] )
		: array();
	$valid_interests = array_values( array_intersect( $submitted_interests, CBV_ACTIVITY_INTERESTS ) );
	update_user_meta( $user_id, '_activity_interests', $valid_interests );

	return array( 'saved' => true );
}

/* ==========================================================================
   PHASE 8c — Per-Traveler Trip Intake (once a user is on a trip's roster)

   One record per (user, trip) pair, stored as a JSON blob on usermeta --
   same pattern as _trip_agreement_accepted_{trip_id} elsewhere in this
   file. Gated on roster membership only, not agreement re-acceptance --
   booking logistics like seat/cabin preference and the insurance decision
   aren't a legal-terms concern, so an unrelated Agreement version bump
   shouldn't block filling this in.

   The Credit Card Authorization form and the Allianz Insurance Waiver are
   NOT collected on this site at all: the CC auth form asks for the full
   card number, CVV, and a photo of the physical card, and the Allianz
   waiver needs a wet signature plus agent-computed dollar figures (amount
   at risk, plan cost) that only make sense filled in by hand per booking.
   Both are hosted as static downloads; this form only tracks whether each
   was downloaded, completed, and returned.
   ========================================================================== */
function cbv_get_traveler_intake( $user_id, $trip_id ) {
	$raw  = get_user_meta( $user_id, '_traveler_intake_' . $trip_id, true );
	$data = $raw ? json_decode( $raw, true ) : array();
	return is_array( $data ) ? $data : array();
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'cb/v1', '/trips/(?P<id>\d+)/traveler-intake', array(
		'methods'             => 'POST',
		'permission_callback' => function ( $request ) {
			$trip_id = (int) $request['id'];
			return is_user_logged_in() && in_array( get_current_user_id(), cb_trip_get_roster( $trip_id ), true );
		},
		'callback'            => function ( $request ) {
			$trip_id = (int) $request['id'];
			$user_id = get_current_user_id();
			$body    = $request->get_json_params();

			$str = function ( $key ) use ( $body ) {
				return isset( $body[ $key ] ) ? sanitize_text_field( $body[ $key ] ) : '';
			};
			$txt = function ( $key ) use ( $body ) {
				return isset( $body[ $key ] ) ? sanitize_textarea_field( $body[ $key ] ) : '';
			};
			$arr = function ( $key ) use ( $body ) {
				return ( ! empty( $body[ $key ] ) && is_array( $body[ $key ] ) ) ? array_values( array_map( 'sanitize_text_field', $body[ $key ] ) ) : array();
			};

			$insurance_decision   = $str( 'insurance_decision' );
			$seat_preference      = $str( 'seat_preference' );
			$flight_cabin_class   = $str( 'flight_cabin_class' );
			$cruise_cabin_class   = $str( 'cruise_cabin_class' );
			$pre_post_nights      = $str( 'pre_post_cruise_nights' );
			$beverage_plan        = $str( 'beverage_plan' );
			$cruise_duration      = $str( 'cruise_duration' );
			$cruise_region        = $str( 'cruise_region' );
			$cruise_departure_port = $str( 'cruise_departure_port' );

			$data = array(
				// Air Travel
				'seat_preference'         => in_array( $seat_preference, CBV_SEAT_POSITIONS, true ) ? $seat_preference : '',
				'departure_airport'       => $str( 'departure_airport' ),
				'flight_cabin_class'      => in_array( $flight_cabin_class, CBV_FLIGHT_CABIN_CLASSES, true ) ? $flight_cabin_class : '',
				'preferred_airline'       => $str( 'preferred_airline' ),
				'frequent_flyer_number'   => $str( 'frequent_flyer_number' ),

				// Cruise Vacation. cruise_itinerary is legacy -- no longer a
				// form input on this or Gate 12 (Duration/Region/Port
				// replace it), so it's deliberately not written here; any
				// already-stored value stays readable via
				// cbv_get_trip_request_field()'s fallback and the export.
				'cruise_company'          => $str( 'cruise_company' ),
				'cruise_program_number'   => $str( 'cruise_program_number' ),
				'cruise_start_date'       => $str( 'cruise_start_date' ),
				'cruise_end_date'         => $str( 'cruise_end_date' ),
				'cruise_duration'         => in_array( $cruise_duration, CBV_CRUISE_DURATIONS, true ) ? $cruise_duration : '',
				'cruise_region'           => in_array( $cruise_region, cbv_flatten_grouped_options( CBV_CRUISE_REGIONS ), true ) ? $cruise_region : '',
				'cruise_departure_port'   => in_array( $cruise_departure_port, cbv_flatten_grouped_options( CBV_CRUISE_DEPARTURE_PORTS ), true ) ? $cruise_departure_port : '',
				'pre_post_cruise_nights'  => in_array( $pre_post_nights, array( 'Yes', 'No' ), true ) ? $pre_post_nights : '',
				'cruise_cabin_class'      => in_array( $cruise_cabin_class, CBV_CRUISE_CABIN_CLASSES, true ) ? $cruise_cabin_class : '',
				'beverage_plan'           => in_array( $beverage_plan, array( 'Yes', 'No' ), true ) ? $beverage_plan : '',
				'beverage_plan_type'      => $str( 'beverage_plan_type' ),

				// Hotel and Resort Vacation
				'hotel_nights'            => $str( 'hotel_nights' ),
				'hotel_preferences'       => $str( 'hotel_preferences' ),
				'hotel_rooms_arrangement' => $str( 'hotel_rooms_arrangement' ),
				'hotel_room_type'         => $arr( 'hotel_room_type' ),
				// hotel_features / hotel_concierge_notes deliberately no longer
				// saved from here (Trip Details item 3) -- that's now Gate 12's
				// organizer-only decision (cb_req_hotel_features), not re-asked
				// per traveler. Any already-saved per-traveler value from before
				// this change stays readable (not deleted), just no longer
				// written going forward.

				// Car Rental
				'car_preferences'         => $str( 'car_preferences' ),
				'car_addons'              => $str( 'car_addons' ),
				'car_category'            => $arr( 'car_category' ),

				// Package Tour
				'package_countries'       => $str( 'package_countries' ),
				'package_style'           => $arr( 'package_style' ),
				'package_activity_level'  => $str( 'package_activity_level' ),

				'traveling_companions'    => $txt( 'traveling_companions' ),
				'insurance_decision'      => in_array( $insurance_decision, array( 'accepted', 'declined' ), true ) ? $insurance_decision : '',
				'allianz_waiver_returned' => ! empty( $body['allianz_waiver_returned'] ),
				'cc_auth_completed'       => ! empty( $body['cc_auth_completed'] ),
				'additional_adults'       => absint( $body['additional_adults'] ?? 0 ),
				'additional_children'     => absint( $body['additional_children'] ?? 0 ),
				'children_ages'           => $str( 'children_ages' ),
			);

			update_user_meta( $user_id, '_traveler_intake_' . $trip_id, wp_json_encode( $data ) );

			return array( 'saved' => true );
		},
	) );
} );

/**
 * Renders the per-traveler intake section for one trip. Called from Gate
 * 07's single trip detail page for any current roster member. No <form>
 * element -- plain button + fetch(), same pattern used throughout this
 * build, id-only fields (no name= attributes) so nothing here could ever
 * bleed into an unrelated form even if this markup were ever reused
 * somewhere with an ambient wrapping form.
 */
function cbv_render_traveler_intake_form( $trip_id ) {
	$user_id = get_current_user_id();

	// Once admin has marked BOTH received in the back office (roster screen
	// in checkedbags-trips.php), this section disappears entirely for this
	// traveler -- these are two separate admin-only flags, distinct from the
	// client's own self-report checkboxes below, and are the ONLY thing that
	// can trigger this.
	$insurance_received = get_user_meta( $user_id, '_insurance_waiver_received_' . $trip_id, true );
	$cc_auth_received   = get_user_meta( $user_id, '_cc_auth_received_' . $trip_id, true );
	if ( 'yes' === $insurance_received && 'yes' === $cc_auth_received ) {
		return '';
	}

	$intake = cbv_get_traveler_intake( $user_id, $trip_id );

	// Same boilerplate block the Proposal PDF already uses (Trip Details
	// item 5) -- reused verbatim, not duplicated content, so an admin
	// editing it in Settings -> Boilerplate Content updates both places.
	$insurance_overview = function_exists( 'cb_get_proposal_boilerplate' ) ? cb_get_proposal_boilerplate()['insurance_importance'] : '';

	// Which sections apply is driven by the trip's own cb_trip_type
	// (admin-assigned), not the client's original Gate 12 "Trip Elements"
	// checkboxes -- what was requested and what the trip actually became
	// can differ. Car Rental and Package Tour are now real cb_trip_type
	// terms (Trip Details item 1) gated exactly like Cruise/Hotel/Resort --
	// previously neither had a corresponding term, so both showed whenever
	// the trip had any type assigned at all, which in practice meant every
	// real trip. Train and Retreat are real taxonomy terms but have no
	// dedicated section yet in either this form or Gate 12 -- left out
	// until an actual trip needs one, per instruction. "Hotel" and "Resort"
	// are separate terms (a plain city hotel stay isn't a resort trip),
	// but both share the same "Hotel and Resort Vacation" field set.
	$show_air          = cbv_trip_has_type( $trip_id, 'Flight' );
	$show_cruise       = cbv_trip_has_type( $trip_id, 'Cruise' );
	$show_hotel        = cbv_trip_has_type( $trip_id, 'Hotel' ) || cbv_trip_has_type( $trip_id, 'Resort' );
	$show_car_rental   = cbv_trip_has_type( $trip_id, 'Car Rental' );
	$show_package_tour = cbv_trip_has_type( $trip_id, 'Package Tour' );

	$cc_auth_url = content_url( 'uploads/checkedbags/documents/CC_authorization.pdf' );
	$waiver_url  = content_url( 'uploads/checkedbags/documents/Allianz_Waiver_form.pdf' );
	$insurance            = $intake['insurance_decision'] ?? '';
	$seat_pref            = $intake['seat_preference'] ?? '';
	$flight_cabin_class   = $intake['flight_cabin_class'] ?? '';
	$cruise_cabin_class   = $intake['cruise_cabin_class'] ?? '';
	$pre_post_nights      = $intake['pre_post_cruise_nights'] ?? '';
	$beverage_plan        = $intake['beverage_plan'] ?? '';
	$cruise_duration      = $intake['cruise_duration'] ?? '';
	$cruise_region        = $intake['cruise_region'] ?? '';
	$cruise_departure_port = $intake['cruise_departure_port'] ?? '';
	$hotel_room_type      = (array) ( $intake['hotel_room_type'] ?? array() );
	$car_category       = (array) ( $intake['car_category'] ?? array() );
	$package_style      = (array) ( $intake['package_style'] ?? array() );

	ob_start();
	?>
	<div class="trip-detail-section trip-detail-traveler-intake" id="cbv-traveler-intake">
		<h3>Trip Registration</h3>
		<p class="cb-page-hint">A few details we need from you individually — seat and cabin preferences, your travel insurance decision, and required paperwork — so everything&#8217;s ready well before departure.</p>

		<?php if ( $show_air ) : ?>
		<h4>Air Travel</h4>
		<div class="cbv-intake-field cbv-intake-field-row">
			<label>Seat preference <span class="cbv-required" aria-hidden="true">*</span>
				<select id="cbv-intake-seat-preference" required>
					<option value="" <?php selected( $seat_pref, '' ); ?>>—</option>
					<?php foreach ( CBV_SEAT_POSITIONS as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $seat_pref, $option ); ?>><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Departure airport <span class="cbv-required" aria-hidden="true">*</span> <input type="text" id="cbv-intake-departure-airport" required value="<?php echo esc_attr( $intake['departure_airport'] ?? '' ); ?>"></label>
			<label>Flight cabin class <span class="cbv-required" aria-hidden="true">*</span>
				<select id="cbv-intake-flight-cabin-class" required>
					<option value="" <?php selected( $flight_cabin_class, '' ); ?>>—</option>
					<?php foreach ( CBV_FLIGHT_CABIN_CLASSES as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $flight_cabin_class, $option ); ?>><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>

		<div class="cbv-intake-field cbv-intake-field-row">
			<?php cbv_render_airline_field( 'cbv-intake-preferred-airline', 'Preferred airline', $intake['preferred_airline'] ?? '', true ); ?>
			<label>Frequent flyer / loyalty number <span class="cbv-required" aria-hidden="true">*</span> <input type="text" id="cbv-intake-frequent-flyer-number" required value="<?php echo esc_attr( $intake['frequent_flyer_number'] ?? '' ); ?>"></label>
		</div>
		<?php endif; ?>

		<?php if ( $show_cruise ) : ?>
		<h4>Cruise Vacation</h4>
		<div class="cbv-intake-field">
			<div class="cbv-intake-field-row">
				<label>Cruise company <span class="cbv-required" aria-hidden="true">*</span> <input type="text" id="cbv-intake-cruise-company" required value="<?php echo esc_attr( $intake['cruise_company'] ?? '' ); ?>"></label>
				<label>Cruise program number <span class="cbv-required" aria-hidden="true">*</span> <input type="text" id="cbv-intake-cruise-program-number" required value="<?php echo esc_attr( $intake['cruise_program_number'] ?? '' ); ?>"></label>
			</div>
			<div class="cbv-intake-field-row">
				<label>Cruise start date <span class="cbv-required" aria-hidden="true">*</span> <input type="date" id="cbv-intake-cruise-start-date" required value="<?php echo esc_attr( $intake['cruise_start_date'] ?? '' ); ?>"></label>
				<label>Cruise end date <span class="cbv-required" aria-hidden="true">*</span> <input type="date" id="cbv-intake-cruise-end-date" required value="<?php echo esc_attr( $intake['cruise_end_date'] ?? '' ); ?>"></label>
			</div>
			<div class="cbv-intake-field-row">
				<label>Cruise duration <span class="cbv-required" aria-hidden="true">*</span>
					<select id="cbv-intake-cruise-duration" required>
						<option value="" <?php selected( $cruise_duration, '' ); ?>>—</option>
						<?php foreach ( CBV_CRUISE_DURATIONS as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $cruise_duration, $option ); ?>><?php echo esc_html( $option ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Cruise region <span class="cbv-required" aria-hidden="true">*</span>
					<select id="cbv-intake-cruise-region" required>
						<option value="" <?php selected( $cruise_region, '' ); ?>>—</option>
						<?php cbv_render_optgroup_options( CBV_CRUISE_REGIONS, $cruise_region ); ?>
					</select>
				</label>
				<label>Cruise departure port <span class="cbv-required" aria-hidden="true">*</span>
					<select id="cbv-intake-cruise-departure-port" required>
						<option value="" <?php selected( $cruise_departure_port, '' ); ?>>—</option>
						<?php cbv_render_optgroup_options( CBV_CRUISE_DEPARTURE_PORTS, $cruise_departure_port ); ?>
					</select>
				</label>
			</div>
			<div class="cbv-intake-field-row">
				<label>Pre/post cruise nights <span class="cbv-required" aria-hidden="true">*</span>
					<select id="cbv-intake-pre-post-cruise-nights" required>
						<option value="" <?php selected( $pre_post_nights, '' ); ?>>—</option>
						<option value="Yes" <?php selected( $pre_post_nights, 'Yes' ); ?>>Yes</option>
						<option value="No" <?php selected( $pre_post_nights, 'No' ); ?>>No</option>
					</select>
				</label>
				<label>Cabin class <span class="cbv-required" aria-hidden="true">*</span>
					<select id="cbv-intake-cruise-cabin-class" required>
						<option value="" <?php selected( $cruise_cabin_class, '' ); ?>>—</option>
						<?php foreach ( CBV_CRUISE_CABIN_CLASSES as $option ) : ?>
							<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $cruise_cabin_class, $option ); ?>><?php echo esc_html( $option ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Beverage plan <span class="cbv-required" aria-hidden="true">*</span>
					<select id="cbv-intake-beverage-plan" required>
						<option value="" <?php selected( $beverage_plan, '' ); ?>>—</option>
						<option value="Yes" <?php selected( $beverage_plan, 'Yes' ); ?>>Yes</option>
						<option value="No" <?php selected( $beverage_plan, 'No' ); ?>>No</option>
					</select>
				</label>
				<label>Beverage plan type <span class="cbv-required" aria-hidden="true">*</span> <input type="text" id="cbv-intake-beverage-plan-type" required value="<?php echo esc_attr( $intake['beverage_plan_type'] ?? '' ); ?>"></label>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( $show_hotel ) : ?>
		<h4>Hotel and Resort Vacation</h4>
		<div class="cbv-intake-field">
			<div class="cbv-intake-field-row">
				<label># of nights <span class="cbv-required" aria-hidden="true">*</span> <input type="text" id="cbv-intake-hotel-nights" required value="<?php echo esc_attr( $intake['hotel_nights'] ?? '' ); ?>"></label>
				<label>Hotel preferences / frequent guest programs <span class="cbv-required" aria-hidden="true">*</span> <input type="text" id="cbv-intake-hotel-preferences" required value="<?php echo esc_attr( $intake['hotel_preferences'] ?? '' ); ?>"></label>
				<label># of rooms/arrangement <span class="cbv-required" aria-hidden="true">*</span> <input type="text" id="cbv-intake-hotel-rooms-arrangement" required value="<?php echo esc_attr( $intake['hotel_rooms_arrangement'] ?? '' ); ?>"></label>
			</div>
			<p class="requests-check-group-label">Room (check all that apply): <span class="cbv-required" aria-hidden="true">*</span></p>
			<label class="check-row"><input type="checkbox" name="cbv_intake_hotel_room_type" value="Standard Room" <?php checked( in_array( 'Standard Room', $hotel_room_type, true ) ); ?>> Standard Room</label>
			<label class="check-row"><input type="checkbox" name="cbv_intake_hotel_room_type" value="Garden View" <?php checked( in_array( 'Garden View', $hotel_room_type, true ) ); ?>> Garden View</label>
			<label class="check-row"><input type="checkbox" name="cbv_intake_hotel_room_type" value="Ocean View/Front" <?php checked( in_array( 'Ocean View/Front', $hotel_room_type, true ) ); ?>> Ocean View/Front</label>
			<label class="check-row"><input type="checkbox" name="cbv_intake_hotel_room_type" value="Other" <?php checked( in_array( 'Other', $hotel_room_type, true ) ); ?>> Other</label>
			<!-- Hotel Features + Concierge level notes removed (Trip Details item 3)
			     -- that's a trip-wide decision the organizer already makes on Gate 12
			     (cb_req_hotel_features / cb_req_hotel_concierge_notes), not something
			     that needs re-asking of every individual traveler. Still read via the
			     export's existing fallback to the Gate 12 value -- see
			     cbv_build_trip_roster_export_data() in checkedbags-roster-export.php. -->
		</div>
		<?php endif; ?>

		<?php if ( $show_car_rental ) : ?>
		<div class="cbv-intake-field">
			<h4>Car Rental</h4>
			<div class="cbv-intake-field-row">
				<label>Car preferences / frequent renter programs <span class="cbv-required" aria-hidden="true">*</span> <input type="text" id="cbv-intake-car-preferences" required value="<?php echo esc_attr( $intake['car_preferences'] ?? '' ); ?>"></label>
				<label>Add-ons <span class="cbv-required" aria-hidden="true">*</span> <input type="text" id="cbv-intake-car-addons" required value="<?php echo esc_attr( $intake['car_addons'] ?? '' ); ?>"></label>
			</div>
			<p class="requests-check-group-label">Car category (check all that apply): <span class="cbv-required" aria-hidden="true">*</span></p>
			<?php foreach ( array( 'Compact', 'Mid Size', 'Full Size', 'Luxury', 'Other' ) as $category ) : ?>
				<label class="check-row"><input type="checkbox" name="cbv_intake_car_category" value="<?php echo esc_attr( $category ); ?>" <?php checked( in_array( $category, $car_category, true ) ); ?>> <?php echo esc_html( $category ); ?></label>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php if ( $show_package_tour ) : ?>
		<div class="cbv-intake-field">
			<h4>Package Tour</h4>
			<label>Country or countries of interest <span class="cbv-required" aria-hidden="true">*</span> <input type="text" id="cbv-intake-package-countries" required value="<?php echo esc_attr( $intake['package_countries'] ?? '' ); ?>"></label>
			<p class="requests-check-group-label">Style <span class="cbv-required" aria-hidden="true">*</span></p>
			<label class="check-row"><input type="checkbox" name="cbv_intake_package_style" value="Escorted" <?php checked( in_array( 'Escorted', $package_style, true ) ); ?>> Escorted</label>
			<label class="check-row"><input type="checkbox" name="cbv_intake_package_style" value="Independent" <?php checked( in_array( 'Independent', $package_style, true ) ); ?>> Independent</label>
			<label>Activity level <span class="cbv-required" aria-hidden="true">*</span> <input type="text" id="cbv-intake-package-activity-level" required value="<?php echo esc_attr( $intake['package_activity_level'] ?? '' ); ?>"></label>
		</div>
		<?php endif; ?>

		<?php if ( trim( $insurance_overview ) ) : ?>
		<div class="cbv-intake-field cbv-intake-insurance-overview">
			<h4>Why Travel Insurance Matters</h4>
			<?php echo wp_kses_post( wpautop( $insurance_overview ) ); ?>
		</div>
		<?php endif; ?>

		<div class="cbv-intake-field">
			<label>Travel insurance <span class="cbv-required" aria-hidden="true">*</span>
				<select id="cbv-intake-insurance-decision" required>
					<option value="" <?php selected( $insurance, '' ); ?>>—</option>
					<option value="accepted" <?php selected( $insurance, 'accepted' ); ?>>Accepted</option>
					<option value="declined" <?php selected( $insurance, 'declined' ); ?>>Declined</option>
				</select>
			</label>
			<div id="cbv-intake-waiver-row" <?php echo ( 'declined' !== $insurance ) ? 'style="display:none;"' : ''; ?>>
				<p class="cbv-intake-warning">Declining travel insurance means you assume financial responsibility for any trip cancellation, interruption, delay, or medical costs that may arise during this trip.</p>
				<p><a href="<?php echo esc_url( $waiver_url ); ?>" target="_blank" rel="noopener">Download the Allianz Waiver of Travel Insurance form</a>.</p>
				<label class="check-row"><input type="checkbox" id="cbv-intake-waiver-returned" <?php checked( ! empty( $intake['allianz_waiver_returned'] ) ); ?>> I have downloaded, completed waiver. Will email to travel@journeywellglobal.com within 48 hours.</label>
			</div>
		</div>

		<div class="cbv-intake-field">
			<p class="cb-page-hint">Some trip vendors require a signed card authorization for incidental charges during your trip. We never collect card details on this site — download the form, sign it, and email it to us directly instead.</p>
			<p><a href="<?php echo esc_url( $cc_auth_url ); ?>" target="_blank" rel="noopener">Download the Credit Card Authorization form</a>. This is required for every trip. We never collect card details on this site.</p>
			<label class="check-row"><input type="checkbox" id="cbv-intake-cc-auth-completed" required <?php checked( ! empty( $intake['cc_auth_completed'] ) ); ?>> I have downloaded, completed form. Will email to travel@journeywellglobal.com within 48 hours. <span class="cbv-required" aria-hidden="true">*</span></label>
		</div>

		<div class="cbv-intake-field">
			<h4>Trip Companions</h4>
			<div class="cbv-intake-companions-columns">
				<div>
					<label>Additional adults traveling with you <input type="number" id="cbv-intake-additional-adults" min="0" value="<?php echo esc_attr( $intake['additional_adults'] ?? 0 ); ?>"></label>
					<label>List their names <textarea id="cbv-intake-traveling-companions" rows="2"><?php echo esc_textarea( $intake['traveling_companions'] ?? '' ); ?></textarea></label>
				</div>
				<div>
					<label>Additional children traveling with you <input type="number" id="cbv-intake-additional-children" min="0" value="<?php echo esc_attr( $intake['additional_children'] ?? 0 ); ?>"></label>
					<label>Children's ages, if any <input type="text" id="cbv-intake-children-ages" placeholder="e.g. 5, 8, 12" value="<?php echo esc_attr( $intake['children_ages'] ?? '' ); ?>"></label>
				</div>
			</div>
		</div>

		<button type="button" class="btn btn-ticket" id="cbv-intake-save-btn" data-trip-id="<?php echo (int) $trip_id; ?>">Save Travel Details</button>
		<span id="cbv-intake-result" style="margin-left:10px;"></span>
	</div>
	<script>
	(function () {
		function fieldVal( id ) {
			var el = document.getElementById( id );
			return el ? el.value : '';
		}
		function fieldChecked( id ) {
			var el = document.getElementById( id );
			return el ? el.checked : false;
		}
		function checkedValuesFor( name ) {
			var boxes = document.querySelectorAll( 'input[name="' + name + '"]:checked' );
			return Array.prototype.map.call( boxes, function ( b ) { return b.value; } );
		}

		var insuranceSelect = document.getElementById( 'cbv-intake-insurance-decision' );
		var waiverRow = document.getElementById( 'cbv-intake-waiver-row' );
		if ( insuranceSelect && waiverRow ) {
			insuranceSelect.addEventListener( 'change', function () {
				waiverRow.style.display = ( insuranceSelect.value === 'declined' ) ? '' : 'none';
			} );
		}

		// Airline "Other" reveal -- shared markup from cbv_render_airline_field(),
		// so this same block also covers any future airline field added here.
		function airlineValue( selectId ) {
			var select = document.getElementById( selectId );
			if ( ! select ) { return ''; }
			if ( select.value === 'Other' ) {
				var other = document.getElementById( selectId + '-other' );
				return other ? other.value : '';
			}
			return select.value;
		}
		document.querySelectorAll( '.cbv-airline-select' ).forEach( function ( select ) {
			var other = document.getElementById( select.id + '-other' );
			if ( ! other ) { return; }
			select.addEventListener( 'change', function () {
				other.style.display = ( select.value === 'Other' ) ? '' : 'none';
			} );
		} );

		// Required-field validation (Trip Details item 7) -- no <form> here
		// (see this function's own doc comment), so there's no native
		// submit event to hook; this runs by hand on the Save click, before
		// the fetch(). checkRequired()/checkGroup() no-op for any field
		// that doesn't exist in the DOM, which is exactly right here: PHP
		// only renders a trip-type section's fields when that section is
		// actually shown, so "required" naturally only ever applies to
		// whatever's on screen for this trip.
		function validateTravelerIntake() {
			var missing = [];
			document.querySelectorAll( '#cbv-traveler-intake .cbv-field-invalid' ).forEach( function ( el ) {
				el.classList.remove( 'cbv-field-invalid' );
			} );

			function checkRequired( id, label ) {
				var el = document.getElementById( id );
				if ( ! el || el.offsetParent === null ) { return; } // not rendered, or currently hidden (e.g. airline "Other" field when a named airline is picked)
				if ( ! el.value || ! el.value.trim() ) {
					el.classList.add( 'cbv-field-invalid' );
					missing.push( { el: el, label: label } );
				}
			}

			function checkGroup( name, label ) {
				var boxes = document.querySelectorAll( 'input[name="' + name + '"]' );
				if ( ! boxes.length ) { return; }
				var anyChecked = Array.prototype.some.call( boxes, function ( b ) { return b.checked; } );
				if ( ! anyChecked ) {
					var group = boxes[ 0 ].closest( '.cbv-intake-field' );
					if ( group ) { group.classList.add( 'cbv-field-invalid' ); }
					missing.push( { el: boxes[ 0 ], label: label } );
				}
			}

			// Air Travel
			checkRequired( 'cbv-intake-seat-preference', 'Seat preference' );
			checkRequired( 'cbv-intake-departure-airport', 'Departure airport' );
			checkRequired( 'cbv-intake-flight-cabin-class', 'Flight cabin class' );
			checkRequired( 'cbv-intake-preferred-airline', 'Preferred airline' );
			checkRequired( 'cbv-intake-preferred-airline-other', 'Airline name' );
			checkRequired( 'cbv-intake-frequent-flyer-number', 'Frequent flyer / loyalty number' );

			// Cruise Vacation
			checkRequired( 'cbv-intake-cruise-company', 'Cruise company' );
			checkRequired( 'cbv-intake-cruise-program-number', 'Cruise program number' );
			checkRequired( 'cbv-intake-cruise-start-date', 'Cruise start date' );
			checkRequired( 'cbv-intake-cruise-end-date', 'Cruise end date' );
			checkRequired( 'cbv-intake-cruise-duration', 'Cruise duration' );
			checkRequired( 'cbv-intake-cruise-region', 'Cruise region' );
			checkRequired( 'cbv-intake-cruise-departure-port', 'Cruise departure port' );
			checkRequired( 'cbv-intake-pre-post-cruise-nights', 'Pre/post cruise nights' );
			checkRequired( 'cbv-intake-cruise-cabin-class', 'Cabin class' );
			checkRequired( 'cbv-intake-beverage-plan', 'Beverage plan' );
			checkRequired( 'cbv-intake-beverage-plan-type', 'Beverage plan type' );

			// Hotel and Resort Vacation
			checkRequired( 'cbv-intake-hotel-nights', '# of nights' );
			checkRequired( 'cbv-intake-hotel-preferences', 'Hotel preferences / frequent guest programs' );
			checkRequired( 'cbv-intake-hotel-rooms-arrangement', '# of rooms/arrangement' );
			checkGroup( 'cbv_intake_hotel_room_type', 'Room' );

			// Car Rental
			checkRequired( 'cbv-intake-car-preferences', 'Car preferences / frequent renter programs' );
			checkRequired( 'cbv-intake-car-addons', 'Add-ons' );
			checkGroup( 'cbv_intake_car_category', 'Car category' );

			// Package Tour
			checkRequired( 'cbv-intake-package-countries', 'Country or countries of interest' );
			checkGroup( 'cbv_intake_package_style', 'Style' );
			checkRequired( 'cbv-intake-package-activity-level', 'Activity level' );

			// Always required, regardless of trip type
			checkRequired( 'cbv-intake-insurance-decision', 'Travel insurance decision' );

			var ccAuth = document.getElementById( 'cbv-intake-cc-auth-completed' );
			if ( ccAuth && ! ccAuth.checked ) {
				ccAuth.closest( 'label' ).classList.add( 'cbv-field-invalid' );
				missing.push( { el: ccAuth, label: 'Credit Card Authorization confirmation' } );
			}

			return missing;
		}

		var saveBtn = document.getElementById( 'cbv-intake-save-btn' );
		if ( saveBtn ) {
			saveBtn.addEventListener( 'click', function () {
				var tripId = saveBtn.getAttribute( 'data-trip-id' );

				var missing = validateTravelerIntake();
				var resultEl = document.getElementById( 'cbv-intake-result' );
				if ( missing.length ) {
					if ( resultEl ) {
						resultEl.textContent = 'Please complete: ' + missing.map( function ( m ) { return m.label; } ).join( ', ' ) + '.';
						resultEl.style.color = 'var(--coral)';
					}
					missing[ 0 ].el.scrollIntoView( { behavior: 'smooth', block: 'center' } );
					missing[ 0 ].el.focus();
					return;
				}
				if ( resultEl ) { resultEl.style.color = ''; }

				var payload = {
					seat_preference: fieldVal( 'cbv-intake-seat-preference' ),
					departure_airport: fieldVal( 'cbv-intake-departure-airport' ),
					flight_cabin_class: fieldVal( 'cbv-intake-flight-cabin-class' ),
					preferred_airline: airlineValue( 'cbv-intake-preferred-airline' ),
					frequent_flyer_number: fieldVal( 'cbv-intake-frequent-flyer-number' ),

					cruise_company: fieldVal( 'cbv-intake-cruise-company' ),
					cruise_program_number: fieldVal( 'cbv-intake-cruise-program-number' ),
					cruise_start_date: fieldVal( 'cbv-intake-cruise-start-date' ),
					cruise_end_date: fieldVal( 'cbv-intake-cruise-end-date' ),
					cruise_duration: fieldVal( 'cbv-intake-cruise-duration' ),
					cruise_region: fieldVal( 'cbv-intake-cruise-region' ),
					cruise_departure_port: fieldVal( 'cbv-intake-cruise-departure-port' ),
					pre_post_cruise_nights: fieldVal( 'cbv-intake-pre-post-cruise-nights' ),
					cruise_cabin_class: fieldVal( 'cbv-intake-cruise-cabin-class' ),
					beverage_plan: fieldVal( 'cbv-intake-beverage-plan' ),
					beverage_plan_type: fieldVal( 'cbv-intake-beverage-plan-type' ),

					hotel_nights: fieldVal( 'cbv-intake-hotel-nights' ),
					hotel_preferences: fieldVal( 'cbv-intake-hotel-preferences' ),
					hotel_rooms_arrangement: fieldVal( 'cbv-intake-hotel-rooms-arrangement' ),
					hotel_room_type: checkedValuesFor( 'cbv_intake_hotel_room_type' ),

					car_preferences: fieldVal( 'cbv-intake-car-preferences' ),
					car_addons: fieldVal( 'cbv-intake-car-addons' ),
					car_category: checkedValuesFor( 'cbv_intake_car_category' ),

					package_countries: fieldVal( 'cbv-intake-package-countries' ),
					package_style: checkedValuesFor( 'cbv_intake_package_style' ),
					package_activity_level: fieldVal( 'cbv-intake-package-activity-level' ),

					traveling_companions: fieldVal( 'cbv-intake-traveling-companions' ),
					insurance_decision: fieldVal( 'cbv-intake-insurance-decision' ),
					allianz_waiver_returned: fieldChecked( 'cbv-intake-waiver-returned' ),
					cc_auth_completed: fieldChecked( 'cbv-intake-cc-auth-completed' ),
					additional_adults: fieldVal( 'cbv-intake-additional-adults' ),
					additional_children: fieldVal( 'cbv-intake-additional-children' ),
					children_ages: fieldVal( 'cbv-intake-children-ages' )
				};
				saveBtn.disabled = true;
				saveBtn.textContent = 'Saving...';
				fetch( <?php echo wp_json_encode( esc_url_raw( rest_url( 'cb/v1/' ) ) ); ?> + 'trips/' + tripId + '/traveler-intake', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?> },
					body: JSON.stringify( payload )
				} )
					.then( function ( res ) { return res.json(); } )
					.then( function ( data ) {
						saveBtn.disabled = false;
						saveBtn.textContent = 'Save Travel Details';
						document.getElementById( 'cbv-intake-result' ).textContent = data.saved ? 'Saved!' : 'Could not save.';
					} )
					.catch( function () {
						saveBtn.disabled = false;
						saveBtn.textContent = 'Save Travel Details';
						document.getElementById( 'cbv-intake-result' ).textContent = 'Something went wrong.';
					} );
			} );
		}
	})();
	</script>
	<?php
	return ob_get_clean();
}
