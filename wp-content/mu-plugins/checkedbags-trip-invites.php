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
		<label>
			<input type="checkbox" name="cbv_accept_terms" value="1">
			<?php
			printf(
				/* translators: %d: Membership Terms version number */
				esc_html__( 'I have read and agree to the Membership Terms (v%d).', 'cbv' ),
				(int) $version
			);
			?>
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
 */
function cbv_user_can_view_trip( $user_id, $trip_id ) {
	if ( ! in_array( (int) $user_id, cb_trip_get_roster( $trip_id ), true ) ) {
		return false;
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
	 * below and (in Phase 7) the dedicated Trip Guests list.
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

			if ( ! in_array( $user_id, cb_trip_get_roster( $trip_id ), true ) ) {
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

			if ( ! in_array( $user_id, cb_trip_get_roster( $trip_id ), true ) ) {
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
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $guests as $guest ) :
						$invited_by_id = get_user_meta( $guest->ID, '_invited_by_user_id', true );
						$invited_trip  = get_user_meta( $guest->ID, '_invited_by_trip_id', true );
						$inviter       = $invited_by_id ? get_userdata( $invited_by_id ) : false;
						$trip          = $invited_trip ? get_post( $invited_trip ) : false;
						?>
						<tr>
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
