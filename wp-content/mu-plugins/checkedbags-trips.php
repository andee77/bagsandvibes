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
 * On first load, make sure the five trip-type terms exist so the taxonomy
 * dropdown in the admin meta box isn't empty. Safe to run repeatedly —
 * term_exists() short-circuits if it's already there.
 */
add_action( 'init', function () {
	$types = array( 'Cruise', 'Destination', 'Flight', 'Train', 'Other' );
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
	);

	foreach ( $number_fields as $key => $default ) {
		register_post_meta( 'cb_trip', $key, array(
			'type'              => 'number',
			'single'            => true,
			'default'           => $default,
			'show_in_rest'      => true,
			'sanitize_callback' => 'floatval',
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
	<div class="cb-field">
		<label>Roster (<?php echo count( $roster ); ?> member<?php echo count( $roster ) === 1 ? '' : 's'; ?>)</label>
		<?php if ( empty( $roster ) ) : ?>
			<p><em>No members yet.</em></p>
		<?php else : ?>
			<ul>
				<?php foreach ( $roster as $user_id ) :
					$user = get_userdata( $user_id );
					if ( ! $user ) { continue; }
					?>
					<li><?php echo esc_html( $user->display_name ); ?> (<?php echo esc_html( $user->user_email ); ?>)</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}

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
	);
	foreach ( $number_fields as $field ) {
		if ( isset( $_POST[ $field ] ) ) {
			update_post_meta( $post_id, $field, floatval( $_POST[ $field ] ) );
		}
	}

} );
