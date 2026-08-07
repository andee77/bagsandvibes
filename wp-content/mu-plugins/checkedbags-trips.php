<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Trips & Groups Core
 * Description: Registers the core "Trip" data model (doubles as a Travel
 *              Group — curated or member-built). This is the spine that
 *              Gates 07–12 on the Member Dashboard all hang off of:
 *                - Gate 07 (All Planned Vacations) lists/queries cb_trip posts
 *                - Gate 08 (Photo Gallery) reads the gallery_privacy meta
 *                - Gate 09 (Payments) reads price/deposit meta, writes payment status
 *                - Gate 10 (Discussion Boards) creates one board per cb_trip
 *                - Gate 11 (Travel Rules) reads rules_addendum + min_group_size
 *                - Gate 12 (Vacation Request) creates a cb_trip in "requested"
 *                  status, admin quotes it, member accepts, status -> active
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-trips.php
 *
 * Loads independently of checkedbags-landing.php — no load-order dependency
 * between the two, both just need to be directly inside mu-plugins/.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Constants — the canonical lists used everywhere else in the codebase.
      If you ever add a trip type or a status, add it here first.
   ========================================================================== */

define( 'CB_TRIP_STATUSES', array(
	'requested' => 'Requested',       // Gate 12 form submitted, awaiting admin quote
	'quoted'    => 'Quoted',          // Admin has set price/plan, waiting on member
	'accepted'  => 'Accepted',        // Member accepted the quote, deposit pending
	'active'    => 'Active',          // Live trip — shows on Gate 07, has board/album
	'completed' => 'Completed',       // Trip has happened — archived but still viewable
	'declined'  => 'Declined',        // Admin or member declined the request
) );

define( 'CB_TRIP_SOURCES', array(
	'curated'      => 'Curated (company-planned)',
	'member_built' => 'Member-built (Gate 12 request)',
) );

/* ==========================================================================
   2. Custom Post Type: cb_trip
   ========================================================================== */
add_action( 'init', function () {

	register_post_type( 'cb_trip', array(
		'label'        => 'Trips',
		'labels'       => array(
			'name'          => 'Trips',
			'singular_name' => 'Trip',
			'add_new_item'  => 'Add New Trip',
			'edit_item'     => 'Edit Trip',
		),
		'public'       => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-airplane',
		'supports'     => array( 'title', 'editor', 'thumbnail' ),
		'show_in_rest' => true, // needed so front-end JS (Gate 07/12) can read/write via REST
		'has_archive'  => false, // Gate 07 renders its own listing, not a theme archive
		'rewrite'      => array( 'slug' => 'trip' ),
	) );

	register_taxonomy( 'cb_trip_type', 'cb_trip', array(
		'label'        => 'Trip Type',
		'public'       => true,
		'show_in_rest' => true,
		'hierarchical' => true,
	) );

} );

/**
 * On first load, make sure these trip-type terms exist so the taxonomy
 * dropdown in the admin meta box isn't empty. Safe to run repeatedly —
 * term_exists() short-circuits if it's already there.
 */
add_action( 'init', function () {
	$types = array( 'Cruise', 'Destination', 'Flight', 'Train', 'Other', 'Resort', 'Retreat', 'Hotel' );
	foreach ( $types as $type ) {
		if ( ! term_exists( $type, 'cb_trip_type' ) ) {
			wp_insert_term( $type, 'cb_trip_type' );
		}
	}
}, 11 ); // priority 11: must run after the taxonomy is registered at default priority 10

/* ==========================================================================
   3. Meta field registration
      register_post_meta (rather than raw update_post_meta calls scattered
      around) gets us automatic REST API exposure + sanitization in one place.
   ========================================================================== */
add_action( 'init', function () {

	$string_fields = array(
		'cb_status'          => 'requested', // one of CB_TRIP_STATUSES keys
		'cb_source'          => 'curated',   // one of CB_TRIP_SOURCES keys
		'cb_start_date'      => '',          // Y-m-d
		'cb_end_date'        => '',          // Y-m-d
		'cb_gallery_privacy' => 'public',    // 'public' | 'private'
		'cb_rules_addendum'  => '',          // free text, trip-specific rules (e.g. international docs)
		'cb_quote_notes'     => '',          // admin's proposed plan, shown back to requester
	);

	foreach ( $string_fields as $key => $default ) {
		register_post_meta( 'cb_trip', $key, array(
			'type'              => 'string',
			'single'            => true,
			'default'           => $default,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}

	$number_fields = array(
		'cb_capacity'        => 0,  // total spots
		'cb_price'           => 0,  // full price per person, in dollars
		'cb_deposit_amount'  => 0,  // required deposit per person, in dollars
		'cb_quoted_price'    => 0,  // admin's quoted price for member-built requests
		'cb_min_group_size'  => 4,  // company-wide default is 4, but stored per-trip in case it changes
		'cb_price_range_low'  => 0, // manual low end of the summary price range (trips with no Pricing Tiers)
		'cb_price_range_high' => 0, // manual high end of the summary price range
	);

	foreach ( $number_fields as $key => $default ) {
		register_post_meta( 'cb_trip', $key, array(
			'type'              => 'number',
			'single'            => true,
			'default'           => $default,
			'show_in_rest'      => true,
			'sanitize_callback' => function ( $value ) {
				return floatval( $value );
			},
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}

	// Roster: array of user IDs. Stored as a single serialized meta value —
	// fine at this scale (dozens of members per trip, not thousands).
	register_post_meta( 'cb_trip', 'cb_roster', array(
		'type'         => 'array',
		'single'       => true,
		'default'      => array(),
		'show_in_rest' => array(
			'schema' => array(
				'type'  => 'array',
				'items' => array( 'type' => 'integer' ),
			),
		),
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

} );

/* ==========================================================================
   4. Roster helper functions
      Gate 07's "I'm in" button and Gate 12's acceptance flow call these
      instead of touching post meta directly, so the "is this trip full,
      does this satisfy the 4-person minimum" logic lives in one place.
   ========================================================================== */

function cb_trip_get_roster( $trip_id ) {
	$roster = get_post_meta( $trip_id, 'cb_roster', true );
	return is_array( $roster ) ? $roster : array();
}

function cb_trip_get_itinerary( $trip_id ) {
	$itinerary = get_post_meta( $trip_id, 'cb_itinerary', true );
	return is_array( $itinerary ) ? $itinerary : array();
}

function cb_trip_get_pricing_tiers( $trip_id ) {
	$tiers = get_post_meta( $trip_id, 'cb_pricing_tiers', true );
	return is_array( $tiers ) ? $tiers : array();
}

// Counts only roster IDs that still resolve to a real WP user -- a raw
// count( cb_trip_get_roster() ) can be inflated by orphaned IDs left behind
// when a user account is deleted outside of the "Remove" button.
function cb_trip_get_valid_roster_count( $trip_id ) {
	$count = 0;
	foreach ( cb_trip_get_roster( $trip_id ) as $user_id ) {
		if ( get_userdata( $user_id ) ) {
			$count++;
		}
	}
	return $count;
}

function cb_trip_add_member( $trip_id, $user_id ) {
	$roster = cb_trip_get_roster( $trip_id );

	if ( in_array( (int) $user_id, $roster, true ) ) {
		return true; // already on the trip, nothing to do
	}

	$capacity = (int) get_post_meta( $trip_id, 'cb_capacity', true );
	if ( $capacity > 0 && count( $roster ) >= $capacity ) {
		return new WP_Error( 'cb_trip_full', 'This trip has no spots remaining.' );
	}

	$roster[] = (int) $user_id;
	update_post_meta( $trip_id, 'cb_roster', $roster );

	do_action( 'cb_trip_member_added', $trip_id, $user_id );

	return true;
}

function cb_trip_remove_member( $trip_id, $user_id ) {
	$roster = cb_trip_get_roster( $trip_id );
	$roster = array_values( array_diff( $roster, array( (int) $user_id ) ) );
	update_post_meta( $trip_id, 'cb_roster', $roster );

	do_action( 'cb_trip_member_removed', $trip_id, $user_id );
}

// Strip a deleted WP user's ID out of every trip roster that still lists
// them, so a deleted account doesn't linger as an orphaned ID (inflating
// raw roster counts) with no way to remove it via the admin UI anymore.
add_action( 'delete_user', function ( $user_id ) {
	$trip_ids = get_posts( array(
		'post_type'      => 'cb_trip',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );

	foreach ( $trip_ids as $trip_id ) {
		if ( in_array( (int) $user_id, cb_trip_get_roster( $trip_id ), true ) ) {
			cb_trip_remove_member( $trip_id, $user_id );
		}
	}
} );

function cb_trip_spots_remaining( $trip_id ) {
	$capacity = (int) get_post_meta( $trip_id, 'cb_capacity', true );
	if ( $capacity <= 0 ) {
		return null; // uncapped trip
	}
	return max( 0, $capacity - count( cb_trip_get_roster( $trip_id ) ) );
}

function cb_trip_meets_minimum_group_size( $trip_id ) {
	$min = (int) get_post_meta( $trip_id, 'cb_min_group_size', true );
	if ( $min <= 0 ) {
		$min = 4; // company default
	}
	return count( cb_trip_get_roster( $trip_id ) ) >= $min;
}

// Quick low-high summary price for a trip, for display anywhere a single
// number would be misleading (trip cards, later the Phase 10 marketing
// hero). Falls back in order: Pricing Tiers (once that field exists) ->
// manual range fields -> null, meaning "no range, show the single price."
//
// The Pricing Tiers branch is a placeholder -- that repeater field (name,
// price, inclusions, featured flag) is Phase 10 scope and doesn't exist on
// cb_trip yet. Wire real tier-scanning logic in here once it's built; every
// caller of this function already expects and handles a 'tiers' source.
function cb_trip_get_price_range( $trip_id ) {
	$tiers        = cb_trip_get_pricing_tiers( $trip_id );
	$point_totals = array();
	foreach ( $tiers as $tier ) {
		foreach ( (array) ( $tier['occupancy_points'] ?? array() ) as $point ) {
			$point_totals[] = (float) ( $point['voyage_fare'] ?? 0 )
				+ (float) ( $point['taxes_fees'] ?? 0 )
				+ (float) ( $point['gratuities'] ?? 0 )
				+ (float) ( $point['insurance'] ?? 0 )
				- (float) ( $point['discount'] ?? 0 );
		}
	}
	if ( ! empty( $point_totals ) ) {
		return array(
			'low'    => min( $point_totals ),
			'high'   => max( $point_totals ),
			'source' => 'tiers',
		);
	}

	$range_low  = (float) get_post_meta( $trip_id, 'cb_price_range_low', true );
	$range_high = (float) get_post_meta( $trip_id, 'cb_price_range_high', true );
	if ( $range_low > 0 && $range_high > 0 ) {
		return array(
			'low'    => min( $range_low, $range_high ),
			'high'   => max( $range_low, $range_high ),
			'source' => 'manual',
		);
	}

	return null;
}

/**
 * Convenience: move a trip through the status flow, firing an action other
 * files (Stripe integration, board creation, email notifications) can hook.
 */
function cb_trip_set_status( $trip_id, $new_status ) {
	if ( ! array_key_exists( $new_status, CB_TRIP_STATUSES ) ) {
		return new WP_Error( 'cb_invalid_status', 'Unknown trip status: ' . $new_status );
	}

	$old_status = get_post_meta( $trip_id, 'cb_status', true );
	update_post_meta( $trip_id, 'cb_status', $new_status );

	do_action( 'cb_trip_status_changed', $trip_id, $old_status, $new_status );

	return true;
}

/* ==========================================================================
   5. Admin meta box — lets you (as admin) edit every field above from the
      normal WP editor screen for a Trip, including setting the quote back
      to a Gate 12 requester.
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'cb_trip_details',
		'Trip Details',
		'cb_render_trip_meta_box',
		'cb_trip',
		'normal',
		'high'
	);
} );

function cb_render_trip_meta_box( $post ) {
	wp_nonce_field( 'cb_trip_save', 'cb_trip_nonce' );

	$status      = get_post_meta( $post->ID, 'cb_status', true ) ?: 'requested';
	$source      = get_post_meta( $post->ID, 'cb_source', true ) ?: 'curated';
	$start_date  = get_post_meta( $post->ID, 'cb_start_date', true );
	$end_date    = get_post_meta( $post->ID, 'cb_end_date', true );
	$capacity    = get_post_meta( $post->ID, 'cb_capacity', true );
	$price       = get_post_meta( $post->ID, 'cb_price', true );
	$deposit     = get_post_meta( $post->ID, 'cb_deposit_amount', true );
	$quoted      = get_post_meta( $post->ID, 'cb_quoted_price', true );
	$quote_notes = get_post_meta( $post->ID, 'cb_quote_notes', true );
	$privacy     = get_post_meta( $post->ID, 'cb_gallery_privacy', true ) ?: 'public';
	$min_size    = get_post_meta( $post->ID, 'cb_min_group_size', true ) ?: 4;
	$addendum    = get_post_meta( $post->ID, 'cb_rules_addendum', true );
	$range_low   = get_post_meta( $post->ID, 'cb_price_range_low', true );
	$range_high  = get_post_meta( $post->ID, 'cb_price_range_high', true );
	$roster      = cb_trip_get_roster( $post->ID );
	?>
	<style>
		.cb-field { margin-bottom: 14px; }
		.cb-field label { display: block; font-weight: 600; margin-bottom: 4px; }
		.cb-field input[type=text],
		.cb-field input[type=number],
		.cb-field input[type=date],
		.cb-field select,
		.cb-field textarea { width: 100%; max-width: 420px; }
		.cb-row { display: flex; gap: 24px; flex-wrap: wrap; }
		.cb-roster-admin-fields { display: flex; gap: 16px; flex-wrap: wrap; margin: 6px 0 0 0; }
		.cb-roster-admin-fields label { font-size: 12px; font-weight: 600; display: flex; flex-direction: column; gap: 2px; }
	</style>

	<div class="cb-row">
		<div class="cb-field">
			<label for="cb_status">Status</label>
			<select name="cb_status" id="cb_status">
				<?php foreach ( CB_TRIP_STATUSES as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="cb-field">
			<label for="cb_source">Source</label>
			<select name="cb_source" id="cb_source">
				<?php foreach ( CB_TRIP_SOURCES as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $source, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<div class="cb-row">
		<div class="cb-field">
			<label for="cb_start_date">Start date</label>
			<input type="date" name="cb_start_date" id="cb_start_date" value="<?php echo esc_attr( $start_date ); ?>">
		</div>
		<div class="cb-field">
			<label for="cb_end_date">End date</label>
			<input type="date" name="cb_end_date" id="cb_end_date" value="<?php echo esc_attr( $end_date ); ?>">
		</div>
		<div class="cb-field">
			<label for="cb_capacity">Capacity (spots)</label>
			<input type="number" name="cb_capacity" id="cb_capacity" value="<?php echo esc_attr( $capacity ); ?>">
		</div>
		<div class="cb-field">
			<label for="cb_min_group_size">Minimum group size</label>
			<input type="number" name="cb_min_group_size" id="cb_min_group_size" value="<?php echo esc_attr( $min_size ); ?>">
		</div>
	</div>

	<div class="cb-row">
		<div class="cb-field">
			<label for="cb_price">Price per person ($)</label>
			<input type="number" step="0.01" name="cb_price" id="cb_price" value="<?php echo esc_attr( $price ); ?>">
		</div>
		<div class="cb-field">
			<label for="cb_deposit_amount">Deposit per person ($)</label>
			<input type="number" step="0.01" name="cb_deposit_amount" id="cb_deposit_amount" value="<?php echo esc_attr( $deposit ); ?>">
		</div>
	</div>

	<div class="cb-row">
		<div class="cb-field">
			<label for="cb_price_range_low">Price range summary -- low ($)</label>
			<input type="number" step="0.01" name="cb_price_range_low" id="cb_price_range_low" value="<?php echo esc_attr( $range_low ); ?>">
		</div>
		<div class="cb-field">
			<label for="cb_price_range_high">Price range summary -- high ($)</label>
			<input type="number" step="0.01" name="cb_price_range_high" id="cb_price_range_high" value="<?php echo esc_attr( $range_high ); ?>">
			<p class="description">Optional. Used for the quick "$low&#8211;$high" summary shown on trip cards. Leave both blank to show the single Price per person above instead. Once this trip has Pricing Tiers, the range is computed from the tiers automatically and these two fields are ignored.</p>
		</div>
	</div>

	<div class="cb-field">
		<label for="cb_gallery_privacy">Photo gallery privacy</label>
		<select name="cb_gallery_privacy" id="cb_gallery_privacy">
			<option value="public" <?php selected( $privacy, 'public' ); ?>>Public</option>
			<option value="private" <?php selected( $privacy, 'private' ); ?>>Private (trip members only)</option>
		</select>
	</div>

	<div class="cb-field">
		<label for="cb_rules_addendum">Trip-specific rules addendum</label>
		<textarea name="cb_rules_addendum" id="cb_rules_addendum" rows="3" placeholder="e.g. Passport must be valid 6 months past return date. Visa required for entry."><?php echo esc_textarea( $addendum ); ?></textarea>
	</div>

	<hr>
	<h4>Gate 12 quote (only relevant for member-built requests)</h4>
	<div class="cb-row">
		<div class="cb-field">
			<label for="cb_quoted_price">Quoted price per person ($)</label>
			<input type="number" step="0.01" name="cb_quoted_price" id="cb_quoted_price" value="<?php echo esc_attr( $quoted ); ?>">
		</div>
	</div>
	<div class="cb-field">
		<label for="cb_quote_notes">Quote / plan notes (shown to the requester)</label>
		<textarea name="cb_quote_notes" id="cb_quote_notes" rows="3" placeholder="What's included, proposed dates, payment schedule, etc."><?php echo esc_textarea( $quote_notes ); ?></textarea>
	</div>

	<hr>
	<?php $valid_roster_count = cb_trip_get_valid_roster_count( $post->ID ); ?>
	<div class="cb-field">
		<label>Roster (<?php echo $valid_roster_count; ?> member<?php echo 1 === $valid_roster_count ? '' : 's'; ?>)</label>
		<?php if ( isset( $_GET['cb_roster_removed'] ) ) : ?>
			<p style="color:#2a7a2a;"><em>Member removed.</em></p>
		<?php endif; ?>
		<?php if ( empty( $roster ) ) : ?>
			<p><em>No members yet.</em></p>
		<?php else : ?>
			<ul>
				<?php foreach ( $roster as $user_id ) :
					$user = get_userdata( $user_id );
					if ( ! $user ) { continue; }
					?>
					<li>
						<?php echo esc_html( $user->display_name ); ?> (<?php echo esc_html( $user->user_email ); ?>)
						<button
							type="button"
							class="button-link cb-roster-remove-btn"
							style="color:#b32d2e;margin-left:8px;"
							data-trip-id="<?php echo (int) $post->ID; ?>"
							data-user-id="<?php echo (int) $user_id; ?>"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'cb_remove_roster_member_' . $post->ID . '_' . $user_id ) ); ?>"
							data-confirm="<?php echo esc_attr( sprintf( "Remove %s from this trip's roster?", $user->display_name ) ); ?>"
						>Remove</button>
						<?php
						// Admin-only per-traveler status for THIS trip. Deliberately not
						// exposed anywhere on the client-facing side -- only this back-
						// office control can mark these, and only marking BOTH received
						// fields true hides the client's "Your Travel Details" section
						// (see cbv_render_traveler_intake_form() in checkedbags-trip-invites.php).
						$paid_in_full        = get_user_meta( $user_id, '_paid_in_full_' . $post->ID, true ) ?: 'no';
						$insurance_received  = get_user_meta( $user_id, '_insurance_waiver_received_' . $post->ID, true ) ?: 'no';
						$cc_auth_received    = get_user_meta( $user_id, '_cc_auth_received_' . $post->ID, true ) ?: 'no';
						?>
						<span class="cb-roster-admin-fields">
							<label>Paid in Full
								<select name="cbv_paid_in_full[<?php echo (int) $user_id; ?>]">
									<option value="no" <?php selected( $paid_in_full, 'no' ); ?>>No</option>
									<option value="yes" <?php selected( $paid_in_full, 'yes' ); ?>>Yes</option>
								</select>
							</label>
							<label>Insurance Waiver Received
								<select name="cbv_insurance_waiver_received[<?php echo (int) $user_id; ?>]">
									<option value="no" <?php selected( $insurance_received, 'no' ); ?>>No</option>
									<option value="yes" <?php selected( $insurance_received, 'yes' ); ?>>Yes</option>
								</select>
							</label>
							<label>CC Auth Received
								<select name="cbv_cc_auth_received[<?php echo (int) $user_id; ?>]">
									<option value="no" <?php selected( $cc_auth_received, 'no' ); ?>>No</option>
									<option value="yes" <?php selected( $cc_auth_received, 'yes' ); ?>>Yes</option>
								</select>
							</label>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
			<script>
			/*
			 * A real nested <form> here gets silently collapsed by the browser's
			 * HTML parser (this meta box already sits inside Gutenberg's own
			 * metabox-location-normal <form>), leaving its hidden inputs as loose
			 * elements that Gutenberg's meta-box-loader then sweeps into ITS
			 * serialization on every Update click — including a colliding
			 * name="action" field that stomps WordPress's own action=editpost
			 * and breaks the Update save entirely. Building a detached form at
			 * click-time and appending it straight to <body> avoids that.
			 */
			document.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '.cb-roster-remove-btn' );
				if ( ! btn ) { return; }
				e.preventDefault();
				if ( ! window.confirm( btn.getAttribute( 'data-confirm' ) ) ) { return; }

				var form = document.createElement( 'form' );
				form.method = 'post';
				form.action = <?php echo wp_json_encode( admin_url( 'admin-post.php' ) ); ?>;
				form.style.display = 'none';

				var fields = {
					action: 'cb_remove_roster_member',
					trip_id: btn.getAttribute( 'data-trip-id' ),
					user_id: btn.getAttribute( 'data-user-id' ),
					cb_remove_roster_nonce: btn.getAttribute( 'data-nonce' )
				};
				Object.keys( fields ).forEach( function ( name ) {
					var input = document.createElement( 'input' );
					input.type = 'hidden';
					input.name = name;
					input.value = fields[ name ];
					form.appendChild( input );
				} );

				document.body.appendChild( form );
				form.submit();
			} );
			</script>
		<?php endif; ?>
	</div>
	<?php
}

/* ==========================================================================
   3a. Day-by-Day Itinerary -- the first add/remove-row repeater field in
       this codebase. Rows are stored as a plain indexed array of assoc
       arrays under cb_itinerary (no register_post_meta -- same as
       cb_roster -- since nothing here needs REST/Gutenberg exposure).

       Mechanism (validate here once, then reuse unmodified for Pricing
       Tiers + its nested Occupancy Points / Add-ons):
        - Every row's fields share one PHP renderer, cb_render_itinerary_row_fields(),
          called once per saved row (real integer index) and once inside a
          <template> (literal string index "__INDEX__") -- one canonical
          row layout, no drift between "saved" and "freshly added" markup.
        - Bracket-array field names (cb_itinerary[N][field]) let PHP parse
          $_POST into a nested array natively -- no hand-rolled reindexing.
        - Remove just deletes the row's <div> from the DOM. Indices are
          allowed to have gaps after a removal; the save handler rebuilds
          the array by appending (not by key), which closes the gaps for
          free without any explicit reindex step.
        - A single delegated-click script (admin_footer, further below)
          handles Add/Remove for every repeater on the page, so adding a
          second or third repeater field needs zero new JS.
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'cb_trip_itinerary',
		'Day-by-Day Itinerary',
		'cb_render_trip_itinerary_meta_box',
		'cb_trip',
		'normal',
		'default'
	);
} );

function cb_render_itinerary_row_fields( $index, $row ) {
	$day         = $row['day'] ?? '';
	$date        = $row['date'] ?? '';
	$port        = $row['port'] ?? '';
	$country     = $row['country'] ?? '';
	$description = $row['description'] ?? '';
	$time        = $row['time'] ?? '';
	$tender_mode = $row['tender_mode'] ?? '';
	?>
	<div class="cb-repeater-row">
		<input type="number" name="cb_itinerary[<?php echo esc_attr( $index ); ?>][day]" placeholder="Day #" value="<?php echo esc_attr( $day ); ?>">
		<input type="date" name="cb_itinerary[<?php echo esc_attr( $index ); ?>][date]" value="<?php echo esc_attr( $date ); ?>">
		<input type="text" name="cb_itinerary[<?php echo esc_attr( $index ); ?>][port]" placeholder="Port" value="<?php echo esc_attr( $port ); ?>">
		<input type="text" name="cb_itinerary[<?php echo esc_attr( $index ); ?>][country]" placeholder="Country" value="<?php echo esc_attr( $country ); ?>">
		<select name="cb_itinerary[<?php echo esc_attr( $index ); ?>][description]">
			<option value="">-- Type --</option>
			<?php foreach ( array( 'Embarkation', 'Arrival', 'Departure', 'At Sea', 'Disembarkation' ) as $opt ) : ?>
				<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $description, $opt ); ?>><?php echo esc_html( $opt ); ?></option>
			<?php endforeach; ?>
		</select>
		<input type="time" name="cb_itinerary[<?php echo esc_attr( $index ); ?>][time]" value="<?php echo esc_attr( $time ); ?>">
		<select name="cb_itinerary[<?php echo esc_attr( $index ); ?>][tender_mode]">
			<option value="">-- Tender --</option>
			<option value="Dock" <?php selected( $tender_mode, 'Dock' ); ?>>Dock</option>
			<option value="Tender" <?php selected( $tender_mode, 'Tender' ); ?>>Tender</option>
		</select>
		<button type="button" class="button-link cb-repeater-remove" style="color:#b32d2e;">Remove</button>
	</div>
	<?php
}

function cb_render_trip_itinerary_meta_box( $post ) {
	$itinerary = get_post_meta( $post->ID, 'cb_itinerary', true );
	$itinerary = is_array( $itinerary ) ? $itinerary : array();
	?>
	<div class="cb-repeater" data-repeater="cb_itinerary">
		<div class="cb-repeater-row cb-repeater-header">
			<span>Day</span><span>Date</span><span>Port</span><span>Country</span><span>Type</span><span>Time</span><span>Tender</span><span></span>
		</div>
		<div class="cb-repeater-rows">
			<?php foreach ( $itinerary as $i => $row ) : ?>
				<?php cb_render_itinerary_row_fields( $i, $row ); ?>
			<?php endforeach; ?>
		</div>
		<template class="cb-repeater-template">
			<?php cb_render_itinerary_row_fields( '__INDEX__', array() ); ?>
		</template>
		<button type="button" class="button cb-repeater-add">+ Add Day</button>
	</div>
	<?php
}

/* ==========================================================================
   3b. Pricing Tiers -- reuses the exact mechanism validated above (bracket-
       array POST parsing, template-clone JS, append-based save). The only
       new wrinkle is nesting: each Tier contains two of its own nested
       repeaters (Occupancy Price Points, Add-ons), and a brand-new Tier's
       own template already contains THEIR templates too. If "Add Tier"
       naively replaced every "__INDEX__" occurrence in the cloned block,
       it would also clobber the nested templates' own placeholders before
       they ever get used. Each nesting level therefore gets its own
       distinct placeholder token (__TIER_INDEX__ / __POINT_INDEX__ /
       __ADDON_INDEX__), read from a data-index-token attribute the shared
       script falls back from to "__INDEX__" -- Day-by-Day Itinerary's
       markup is untouched and keeps working exactly as already tested.
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'cb_trip_pricing_tiers',
		'Pricing Tiers',
		'cb_render_trip_pricing_tiers_meta_box',
		'cb_trip',
		'normal',
		'default'
	);
} );

function cb_render_occupancy_point_row_fields( $tier_index, $point_index, $point ) {
	$occupancy_count = $point['occupancy_count'] ?? '';
	$voyage_fare     = $point['voyage_fare'] ?? '';
	$taxes_fees      = $point['taxes_fees'] ?? '';
	$gratuities      = $point['gratuities'] ?? '';
	$insurance       = $point['insurance'] ?? '';
	$discount        = $point['discount'] ?? '';
	$prefix          = 'cb_pricing_tiers[' . $tier_index . '][occupancy_points][' . $point_index . ']';
	?>
	<div class="cb-repeater-row cb-occupancy-point-row">
		<input type="number" name="<?php echo esc_attr( $prefix ); ?>[occupancy_count]" placeholder="# Sailors" value="<?php echo esc_attr( $occupancy_count ); ?>">
		<input type="number" step="0.01" name="<?php echo esc_attr( $prefix ); ?>[voyage_fare]" placeholder="Voyage Fare" value="<?php echo esc_attr( $voyage_fare ); ?>">
		<input type="number" step="0.01" name="<?php echo esc_attr( $prefix ); ?>[taxes_fees]" placeholder="Taxes & Fees" value="<?php echo esc_attr( $taxes_fees ); ?>">
		<input type="number" step="0.01" name="<?php echo esc_attr( $prefix ); ?>[gratuities]" placeholder="Gratuities" value="<?php echo esc_attr( $gratuities ); ?>">
		<input type="number" step="0.01" name="<?php echo esc_attr( $prefix ); ?>[insurance]" placeholder="Insurance" value="<?php echo esc_attr( $insurance ); ?>">
		<input type="number" step="0.01" name="<?php echo esc_attr( $prefix ); ?>[discount]" placeholder="Discount" value="<?php echo esc_attr( $discount ); ?>">
		<button type="button" class="button-link cb-repeater-remove" style="color:#b32d2e;">Remove</button>
	</div>
	<?php
}

function cb_render_addon_row_fields( $tier_index, $addon_index, $addon ) {
	$name = $addon['name'] ?? '';
	$qty  = $addon['qty'] ?? '';
	$prefix = 'cb_pricing_tiers[' . $tier_index . '][addons][' . $addon_index . ']';
	?>
	<div class="cb-repeater-row cb-addon-row">
		<input type="text" name="<?php echo esc_attr( $prefix ); ?>[name]" placeholder="Add-on name (e.g. Beverage Package)" value="<?php echo esc_attr( $name ); ?>">
		<input type="number" name="<?php echo esc_attr( $prefix ); ?>[qty]" placeholder="Qty" value="<?php echo esc_attr( $qty ); ?>">
		<button type="button" class="button-link cb-repeater-remove" style="color:#b32d2e;">Remove</button>
	</div>
	<?php
}

function cb_render_pricing_tier_row_fields( $tier_index, $tier ) {
	$name             = $tier['name'] ?? '';
	$capacity_low     = $tier['capacity_low'] ?? '';
	$capacity_high    = $tier['capacity_high'] ?? '';
	$occupancy_points = is_array( $tier['occupancy_points'] ?? null ) ? $tier['occupancy_points'] : array();
	$addons           = is_array( $tier['addons'] ?? null ) ? $tier['addons'] : array();
	?>
	<div class="cb-repeater-row cb-tier-row">
		<div class="cb-tier-row-header">
			<input type="text" name="cb_pricing_tiers[<?php echo esc_attr( $tier_index ); ?>][name]" class="cb-tier-name" placeholder="Tier name (e.g. Sea Terrace Cabin)" value="<?php echo esc_attr( $name ); ?>">
			<label>Sleeps
				<input type="number" name="cb_pricing_tiers[<?php echo esc_attr( $tier_index ); ?>][capacity_low]" class="cb-tier-capacity" placeholder="Low" value="<?php echo esc_attr( $capacity_low ); ?>">
				to
				<input type="number" name="cb_pricing_tiers[<?php echo esc_attr( $tier_index ); ?>][capacity_high]" class="cb-tier-capacity" placeholder="High" value="<?php echo esc_attr( $capacity_high ); ?>">
				sailors
			</label>
			<button type="button" class="button-link cb-repeater-remove" style="color:#b32d2e;">Remove Tier</button>
		</div>

		<div class="cb-tier-section">
			<h4>Occupancy Price Points <span class="description">(one row per headcount sharing this cabin -- e.g. 2 sailors vs. 4 sailors -- with its own explicit per-person price, not a computed split)</span></h4>
			<div class="cb-repeater" data-repeater="occupancy_points" data-index-token="__POINT_INDEX__">
				<div class="cb-repeater-row cb-occupancy-point-row cb-repeater-header">
					<span># Sailors</span><span>Voyage Fare</span><span>Taxes &amp; Fees</span><span>Gratuities</span><span>Insurance</span><span>Discount</span><span></span>
				</div>
				<div class="cb-repeater-rows">
					<?php foreach ( $occupancy_points as $p => $point ) : ?>
						<?php cb_render_occupancy_point_row_fields( $tier_index, $p, $point ); ?>
					<?php endforeach; ?>
				</div>
				<template class="cb-repeater-template">
					<?php cb_render_occupancy_point_row_fields( $tier_index, '__POINT_INDEX__', array() ); ?>
				</template>
				<button type="button" class="button cb-repeater-add">+ Add Occupancy Price Point</button>
			</div>
		</div>

		<div class="cb-tier-section">
			<h4>Add-ons <span class="description">(optional extras, e.g. a beverage package)</span></h4>
			<div class="cb-repeater" data-repeater="addons" data-index-token="__ADDON_INDEX__">
				<div class="cb-repeater-rows">
					<?php foreach ( $addons as $a => $addon ) : ?>
						<?php cb_render_addon_row_fields( $tier_index, $a, $addon ); ?>
					<?php endforeach; ?>
				</div>
				<template class="cb-repeater-template">
					<?php cb_render_addon_row_fields( $tier_index, '__ADDON_INDEX__', array() ); ?>
				</template>
				<button type="button" class="button cb-repeater-add">+ Add Add-on</button>
			</div>
		</div>
	</div>
	<?php
}

function cb_render_trip_pricing_tiers_meta_box( $post ) {
	$tiers = get_post_meta( $post->ID, 'cb_pricing_tiers', true );
	$tiers = is_array( $tiers ) ? $tiers : array();
	?>
	<div class="cb-repeater" data-repeater="cb_pricing_tiers" data-index-token="__TIER_INDEX__">
		<div class="cb-repeater-rows">
			<?php foreach ( $tiers as $t => $tier ) : ?>
				<?php cb_render_pricing_tier_row_fields( $t, $tier ); ?>
			<?php endforeach; ?>
		</div>
		<template class="cb-repeater-template">
			<?php cb_render_pricing_tier_row_fields( '__TIER_INDEX__', array() ); ?>
		</template>
		<button type="button" class="button button-primary cb-repeater-add">+ Add Pricing Tier</button>
	</div>
	<?php
}

/* ==========================================================================
   3c. Internal Notes -- vendor contacts, margin/profit notes, coordinator
       checklist. Gated stricter than the rest of Trip Details: everything
       else on this screen only needs edit_post, this needs manage_options
       specifically (rendered AND saved), since it's the source content for
       Piece 5's Internal Data Sheet PDF and must never appear to anyone
       who isn't cleared to see margin/vendor information.
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'cb_trip_internal_notes',
		'Internal Notes (Admin Only)',
		'cb_render_trip_internal_notes_meta_box',
		'cb_trip',
		'normal',
		'default'
	);
} );

function cb_trip_get_internal_notes( $trip_id ) {
	return array(
		'vendor_contacts'        => (string) get_post_meta( $trip_id, 'cb_internal_vendor_contacts', true ),
		'margin_notes'           => (string) get_post_meta( $trip_id, 'cb_internal_margin_notes', true ),
		'coordinator_checklist'  => (string) get_post_meta( $trip_id, 'cb_internal_coordinator_checklist', true ),
	);
}

function cb_render_trip_internal_notes_meta_box( $post ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		echo '<p><em>Visible to administrators only.</em></p>';
		return;
	}
	wp_nonce_field( 'cb_trip_internal_notes_save', 'cb_trip_internal_notes_nonce' );
	$notes = cb_trip_get_internal_notes( $post->ID );
	?>
	<div class="cb-field">
		<label for="cb_internal_vendor_contacts">Vendor Contacts</label>
		<textarea name="cb_internal_vendor_contacts" id="cb_internal_vendor_contacts" rows="4" style="width:100%;max-width:700px;" placeholder="Names, emails, phone numbers, account/booking reference numbers..."><?php echo esc_textarea( $notes['vendor_contacts'] ); ?></textarea>
	</div>
	<div class="cb-field">
		<label for="cb_internal_margin_notes">Margin / Profit Notes</label>
		<textarea name="cb_internal_margin_notes" id="cb_internal_margin_notes" rows="4" style="width:100%;max-width:700px;" placeholder="Commission, net cost vs. quoted price, anything not meant for the client..."><?php echo esc_textarea( $notes['margin_notes'] ); ?></textarea>
	</div>
	<div class="cb-field">
		<label for="cb_internal_coordinator_checklist">Coordinator Checklist</label>
		<textarea name="cb_internal_coordinator_checklist" id="cb_internal_coordinator_checklist" rows="4" style="width:100%;max-width:700px;" placeholder="Deposit deadlines, final payment due dates, docs to collect, follow-up tasks..."><?php echo esc_textarea( $notes['coordinator_checklist'] ); ?></textarea>
	</div>
	<?php
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! isset( $_POST['cb_trip_internal_notes_nonce'] ) || ! wp_verify_nonce( $_POST['cb_trip_internal_notes_nonce'], 'cb_trip_internal_notes_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	$fields = array( 'cb_internal_vendor_contacts', 'cb_internal_margin_notes', 'cb_internal_coordinator_checklist' );
	foreach ( $fields as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, $field, sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) );
		}
	}
} );

// Recursively treats a repeater row as blank only if every leaf value is an
// empty string after trimming -- a legitimate "0" (e.g. a $0 Discount or
// Insurance line, once Pricing Tiers/Occupancy Points reuse this) must NOT
// be treated the same as never having filled the row in, unlike PHP's own
// array_filter() which drops "0" as falsy.
function cb_repeater_row_is_blank( $row ) {
	foreach ( (array) $row as $value ) {
		if ( is_array( $value ) ) {
			if ( ! cb_repeater_row_is_blank( $value ) ) {
				return false;
			}
			continue;
		}
		if ( '' !== trim( (string) $value ) ) {
			return false;
		}
	}
	return true;
}

// Shared Add/Remove-row script for every repeater field on any post edit
// screen -- written once here, reused unmodified by any repeater added
// later, on any post type (Day-by-Day Itinerary and Pricing Tiers on
// cb_trip, the trip picker on cb_proposal, and anything still to come).
// Deliberately NOT gated to specific post types: this was originally
// gated to 'cb_trip' only, which silently broke the cb_proposal trip
// picker's Add button (script never loaded on that screen at all). Gating
// on $screen->base === 'post' instead -- i.e. "some post edit/new screen,
// any post type" -- is exactly as cheap when no .cb-repeater exists on the
// page (the delegated click listener simply never matches anything) and
// needs no maintenance when a repeater is added to a new post type later.
add_action( 'admin_footer', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base ) {
		return;
	}
	?>
	<style>
		.cb-repeater-row { margin-bottom: 6px; }
		.cb-repeater-header { font-weight: 600; font-size: 12px; }
		.cb-repeater-row input, .cb-repeater-row select { width: 100%; }
		.cb-repeater-template { display: none; }

		/* Day-by-Day Itinerary */
		[data-repeater="cb_itinerary"] .cb-repeater-row { display: grid; grid-template-columns: 70px 130px 1fr 1fr 140px 90px 100px auto; gap: 8px; align-items: center; }

		/* Pricing Tiers + its nested Occupancy Points / Add-ons */
		.cb-tier-row { border: 1px solid #ccd0d4; border-radius: 4px; padding: 12px; background: #fff; }
		.cb-tier-row-header { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; }
		.cb-tier-name { max-width: 260px; }
		.cb-tier-capacity { width: 60px; }
		.cb-tier-section { margin-top: 10px; }
		.cb-tier-section h4 { margin: 0 0 6px; }
		.cb-tier-section h4 .description { font-weight: normal; font-size: 12px; color: #666; }
		[data-repeater="occupancy_points"] .cb-repeater-row { display: grid; grid-template-columns: 90px 1fr 1fr 1fr 1fr 1fr auto; gap: 8px; align-items: center; }
		[data-repeater="addons"] .cb-repeater-row { display: grid; grid-template-columns: 1fr 80px auto; gap: 8px; align-items: center; }
	</style>
	<script>
	(function () {
		document.addEventListener( 'click', function ( e ) {
			var addBtn = e.target.closest( '.cb-repeater-add' );
			if ( addBtn ) {
				var repeater   = addBtn.closest( '.cb-repeater' );
				var template   = repeater.querySelector( ':scope > .cb-repeater-template' );
				var rows       = repeater.querySelector( ':scope > .cb-repeater-rows' );
				var indexToken = repeater.dataset.indexToken || '__INDEX__';
				var nextIndex  = repeater.dataset.nextIndex ? parseInt( repeater.dataset.nextIndex, 10 ) : rows.children.length;
				var html = template.innerHTML.split( indexToken ).join( nextIndex );
				var wrapper = document.createElement( 'div' );
				wrapper.innerHTML = html.trim();
				while ( wrapper.firstChild ) {
					rows.appendChild( wrapper.firstChild );
				}
				repeater.dataset.nextIndex = String( nextIndex + 1 );
				return;
			}
			var removeBtn = e.target.closest( '.cb-repeater-remove' );
			if ( removeBtn ) {
				var row = removeBtn.closest( '.cb-repeater-row' );
				if ( row ) { row.remove(); }
			}
		} );
	})();
	</script>
	<?php
} );

add_action( 'admin_post_cb_remove_roster_member', function () {
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Insufficient permissions.' );
	}

	$trip_id = isset( $_POST['trip_id'] ) ? (int) $_POST['trip_id'] : 0;
	$user_id = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;

	if ( ! $trip_id || ! $user_id
		|| ! isset( $_POST['cb_remove_roster_nonce'] )
		|| ! wp_verify_nonce( $_POST['cb_remove_roster_nonce'], 'cb_remove_roster_member_' . $trip_id . '_' . $user_id )
	) {
		wp_die( 'Invalid request.' );
	}

	if ( ! current_user_can( 'edit_post', $trip_id ) ) {
		wp_die( 'Insufficient permissions for this trip.' );
	}

	cb_trip_remove_member( $trip_id, $user_id );

	wp_safe_redirect( add_query_arg(
		array( 'post' => $trip_id, 'action' => 'edit', 'cb_roster_removed' => '1' ),
		admin_url( 'post.php' )
	) );
	exit;
} );

add_action( 'save_post_cb_trip', function ( $post_id ) {

	if ( ! isset( $_POST['cb_trip_nonce'] ) || ! wp_verify_nonce( $_POST['cb_trip_nonce'], 'cb_trip_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$old_status = get_post_meta( $post_id, 'cb_status', true );

	$text_fields = array(
		'cb_status', 'cb_source', 'cb_start_date', 'cb_end_date',
		'cb_gallery_privacy', 'cb_rules_addendum', 'cb_quote_notes',
	);
	foreach ( $text_fields as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
		}
	}

	if ( isset( $_POST['cb_status'] ) ) {
		$new_status = sanitize_text_field( wp_unslash( $_POST['cb_status'] ) );
		if ( $new_status !== $old_status ) {
			do_action( 'cb_trip_status_changed', $post_id, $old_status, $new_status );
		}
	}

	$number_fields = array(
		'cb_capacity', 'cb_price', 'cb_deposit_amount',
		'cb_quoted_price', 'cb_min_group_size',
		'cb_price_range_low', 'cb_price_range_high',
	);
	foreach ( $number_fields as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, $field, floatval( $_POST[ $field ] ) );
		}
	}

	// Day-by-Day Itinerary repeater. Rebuilding via append (not by original
	// $_POST key) closes any gaps left by removed rows for free -- see the
	// mechanism notes above cb_render_trip_itinerary_meta_box().
	$itinerary = array();
	foreach ( (array) ( $_POST['cb_itinerary'] ?? array() ) as $row ) {
		if ( cb_repeater_row_is_blank( $row ) ) {
			continue;
		}
		$itinerary[] = array(
			'day'         => sanitize_text_field( wp_unslash( $row['day'] ?? '' ) ),
			'date'        => sanitize_text_field( wp_unslash( $row['date'] ?? '' ) ),
			'port'        => sanitize_text_field( wp_unslash( $row['port'] ?? '' ) ),
			'country'     => sanitize_text_field( wp_unslash( $row['country'] ?? '' ) ),
			'description' => sanitize_text_field( wp_unslash( $row['description'] ?? '' ) ),
			'time'        => sanitize_text_field( wp_unslash( $row['time'] ?? '' ) ),
			'tender_mode' => sanitize_text_field( wp_unslash( $row['tender_mode'] ?? '' ) ),
		);
	}
	update_post_meta( $post_id, 'cb_itinerary', $itinerary );

	// Pricing Tiers repeater, with two levels of nested repeaters
	// (Occupancy Price Points, Add-ons). Same append-based rebuild as
	// Itinerary above, one level deeper per tier; cb_repeater_row_is_blank()
	// already recurses into nested arrays, so a tier with a name filled in
	// but no pricing yet still survives, and a $0 Discount/Insurance value
	// still counts as "filled in" rather than blank.
	$tiers = array();
	foreach ( (array) ( $_POST['cb_pricing_tiers'] ?? array() ) as $tier_row ) {
		if ( cb_repeater_row_is_blank( $tier_row ) ) {
			continue;
		}

		$occupancy_points = array();
		foreach ( (array) ( $tier_row['occupancy_points'] ?? array() ) as $point_row ) {
			if ( cb_repeater_row_is_blank( $point_row ) ) {
				continue;
			}
			$occupancy_points[] = array(
				'occupancy_count' => absint( $point_row['occupancy_count'] ?? 0 ),
				'voyage_fare'     => floatval( $point_row['voyage_fare'] ?? 0 ),
				'taxes_fees'      => floatval( $point_row['taxes_fees'] ?? 0 ),
				'gratuities'      => floatval( $point_row['gratuities'] ?? 0 ),
				'insurance'       => floatval( $point_row['insurance'] ?? 0 ),
				'discount'        => floatval( $point_row['discount'] ?? 0 ),
			);
		}

		$addons = array();
		foreach ( (array) ( $tier_row['addons'] ?? array() ) as $addon_row ) {
			if ( cb_repeater_row_is_blank( $addon_row ) ) {
				continue;
			}
			$addons[] = array(
				'name' => sanitize_text_field( wp_unslash( $addon_row['name'] ?? '' ) ),
				'qty'  => absint( $addon_row['qty'] ?? 0 ),
			);
		}

		$tiers[] = array(
			'name'             => sanitize_text_field( wp_unslash( $tier_row['name'] ?? '' ) ),
			'capacity_low'     => absint( $tier_row['capacity_low'] ?? 0 ),
			'capacity_high'    => absint( $tier_row['capacity_high'] ?? 0 ),
			'occupancy_points' => $occupancy_points,
			'addons'           => $addons,
		);
	}
	update_post_meta( $post_id, 'cb_pricing_tiers', $tiers );

	// Admin-only per-traveler-per-trip status (Paid in Full, Insurance
	// Waiver Received, CC Auth Received) -- rendered as plain named inputs
	// alongside the roster list above, submitted through this same form, so
	// no separate button/form is needed. Only roster members on THIS trip
	// are ever written, regardless of what a crafted request might include.
	$roster = cb_trip_get_roster( $post_id );
	$roster_admin_fields = array(
		'cbv_paid_in_full'             => '_paid_in_full_',
		'cbv_insurance_waiver_received' => '_insurance_waiver_received_',
		'cbv_cc_auth_received'          => '_cc_auth_received_',
	);
	foreach ( $roster_admin_fields as $post_key => $meta_prefix ) {
		if ( empty( $_POST[ $post_key ] ) || ! is_array( $_POST[ $post_key ] ) ) {
			continue;
		}
		foreach ( $_POST[ $post_key ] as $roster_user_id => $value ) {
			$roster_user_id = absint( $roster_user_id );
			if ( ! in_array( $roster_user_id, $roster, true ) ) {
				continue;
			}
			update_user_meta( $roster_user_id, $meta_prefix . $post_id, ( 'yes' === $value ) ? 'yes' : 'no' );
		}
	}

} );
