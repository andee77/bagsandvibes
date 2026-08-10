<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Appointment Requests
 * Description: "Schedule Appointment" request mechanism for the Payment
 *              page (Section 6 of the trip-invite build's post-launch
 *              list) -- Travel Payment itself is never processed on this
 *              site (InteleTravel handles it off-site), so this just lets
 *              a member ask the advisor for a time to arrange it. Built as
 *              a custom post type specifically to AVOID repeating the
 *              earlier "Request Full Membership" mistake, where a request
 *              was recorded (a single usermeta timestamp) with nowhere for
 *              admin to actually see it until a later cleanup pass bolted
 *              on a bespoke, role-specific admin screen. A CPT gets a real
 *              admin list screen (sortable/filterable columns, no custom
 *              markup to maintain) for free, and isn't hardwired to any
 *              one WP role the way that later fix was.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-appointment-requests.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. CPT registration -- internal-only (no public front-end template),
      but a full admin list/edit screen via show_ui.
   ========================================================================== */
add_action( 'init', function () {
	register_post_type( 'cb_appt_request', array(
		'label'        => 'Appointment Requests',
		'labels'       => array(
			'name'          => 'Appointment Requests',
			'singular_name' => 'Appointment Request',
			'edit_item'     => 'View Appointment Request',
		),
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => true,
		'menu_icon'    => 'dashicons-calendar-alt',
		'supports'     => array( 'title' ),
		'capabilities' => array(
			'create_posts' => 'do_not_allow', // created only via the REST endpoint below, never from wp-admin's own "Add New"
		),
		'map_meta_cap' => true,
	) );
} );

/* ==========================================================================
   2. REST: submit a request. trip_id is optional -- the page-level
      "Schedule Appointment" button (not tied to any one trip) omits it,
      a per-trip-card button includes it.
   ========================================================================== */
add_action( 'rest_api_init', function () {
	register_rest_route( 'cb/v1', '/appointment-request', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => 'cbv_handle_appointment_request',
	) );
} );

function cbv_handle_appointment_request( $request ) {
	$user_id       = get_current_user_id();
	$user          = get_userdata( $user_id );
	$trip_id       = absint( $request->get_param( 'trip_id' ) );
	$preferred_time = sanitize_text_field( wp_unslash( $request->get_param( 'preferred_time' ) ?? '' ) );
	$notes         = sanitize_textarea_field( wp_unslash( $request->get_param( 'notes' ) ?? '' ) );

	if ( '' === trim( $preferred_time ) ) {
		return new WP_Error( 'cbv_missing_preferred_time', 'Please provide a preferred time.', array( 'status' => 400 ) );
	}

	$trip = $trip_id ? get_post( $trip_id ) : null;
	if ( $trip_id && ( ! $trip || $trip->post_type !== 'cb_trip' ) ) {
		return new WP_Error( 'cbv_not_found', 'Trip not found.', array( 'status' => 404 ) );
	}

	$title = $user->display_name . ' — ' . ( $trip ? get_the_title( $trip ) : 'General inquiry' );

	$post_id = wp_insert_post( array(
		'post_type'   => 'cb_appt_request',
		'post_title'  => $title,
		'post_status' => 'publish',
		'post_author' => $user_id,
	), true );

	if ( is_wp_error( $post_id ) ) {
		return new WP_Error( 'cbv_request_failed', 'Could not save the request — please try again.', array( 'status' => 500 ) );
	}

	update_post_meta( $post_id, 'cbv_appt_trip_id', $trip_id );
	update_post_meta( $post_id, 'cbv_appt_preferred_time', $preferred_time );
	update_post_meta( $post_id, 'cbv_appt_notes', $notes );
	update_post_meta( $post_id, 'cbv_appt_status', 'new' );

	cbv_notify_admin_of_appointment_request( $post_id, $user, $trip, $preferred_time, $notes );

	return array( 'requested' => true );
}

/**
 * Best-effort email alongside the always-reliable admin list screen below --
 * this is a secondary notification, not the sole surfacing mechanism (the
 * exact gap that made the original Request Full Membership feature a dead
 * end), so a failed/undelivered email here doesn't lose the request itself.
 */
function cbv_notify_admin_of_appointment_request( $post_id, $user, $trip, $preferred_time, $notes ) {
	$admin_email = get_option( 'admin_email' );
	if ( ! $admin_email ) {
		return;
	}

	$subject = 'New appointment request: ' . $user->display_name;
	$body    = "A member requested a Travel Payment appointment.\n\n"
		. 'Member: ' . $user->display_name . ' (' . $user->user_email . ")\n"
		. 'Trip: ' . ( $trip ? get_the_title( $trip ) : 'General inquiry (not tied to a specific trip)' ) . "\n"
		. 'Preferred time: ' . $preferred_time . "\n"
		. 'Notes: ' . ( $notes !== '' ? $notes : '(none)' ) . "\n\n"
		. 'View in wp-admin: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' );

	wp_mail( $admin_email, $subject, $body );
}

/* ==========================================================================
   3. Admin list screen: custom columns + a Status/internal-notes meta box
      on the (view-only, since create_posts is disabled) edit screen.
   ========================================================================== */
add_filter( 'manage_cb_appt_request_posts_columns', function ( $columns ) {
	$columns = array(
		'cb_appt' => __( 'Requested By' ),
	);
	$columns['cb_appt_trip']    = 'Trip';
	$columns['cb_appt_time']    = 'Preferred Time';
	$columns['cb_appt_status']  = 'Status';
	$columns['date']            = __( 'Date' );
	return $columns;
} );

add_action( 'manage_cb_appt_request_posts_custom_column', function ( $column, $post_id ) {
	switch ( $column ) {
		case 'cb_appt':
			$author = get_userdata( get_post_field( 'post_author', $post_id ) );
			echo $author ? esc_html( $author->display_name ) : '<em>&#8212;</em>';
			break;
		case 'cb_appt_trip':
			$trip_id = (int) get_post_meta( $post_id, 'cbv_appt_trip_id', true );
			echo $trip_id ? esc_html( get_the_title( $trip_id ) ) : '<em>General inquiry</em>';
			break;
		case 'cb_appt_time':
			echo esc_html( get_post_meta( $post_id, 'cbv_appt_preferred_time', true ) );
			break;
		case 'cb_appt_status':
			$status = get_post_meta( $post_id, 'cbv_appt_status', true ) ?: 'new';
			$labels = array( 'new' => 'New', 'contacted' => 'Contacted', 'scheduled' => 'Scheduled', 'done' => 'Done' );
			echo '<strong' . ( 'new' === $status ? ' style="color:#b45900;"' : '' ) . '>' . esc_html( $labels[ $status ] ?? $status ) . '</strong>';
			break;
	}
}, 10, 2 );

add_filter( 'manage_edit-cb_appt_request_sortable_columns', function ( $columns ) {
	$columns['cb_appt_status'] = 'cb_appt_status';
	return $columns;
} );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cbv_appt_details', 'Request Details', 'cbv_render_appt_request_meta_box', 'cb_appt_request', 'normal', 'high' );
} );

function cbv_render_appt_request_meta_box( $post ) {
	wp_nonce_field( 'cbv_appt_save', 'cbv_appt_nonce' );

	$trip_id        = (int) get_post_meta( $post->ID, 'cbv_appt_trip_id', true );
	$preferred_time = get_post_meta( $post->ID, 'cbv_appt_preferred_time', true );
	$notes          = get_post_meta( $post->ID, 'cbv_appt_notes', true );
	$status         = get_post_meta( $post->ID, 'cbv_appt_status', true ) ?: 'new';
	$author         = get_userdata( $post->post_author );
	?>
	<p><strong>Requested by:</strong> <?php echo $author ? esc_html( $author->display_name . ' (' . $author->user_email . ')' ) : '—'; ?></p>
	<p><strong>Trip:</strong> <?php echo $trip_id ? esc_html( get_the_title( $trip_id ) ) : 'General inquiry (not tied to a specific trip)'; ?></p>
	<p><strong>Preferred time:</strong> <?php echo esc_html( $preferred_time ); ?></p>
	<p><strong>Member's notes:</strong><br><?php echo $notes !== '' ? nl2br( esc_html( $notes ) ) : '<em>(none)</em>'; ?></p>
	<hr>
	<p>
		<label for="cbv_appt_status"><strong>Status</strong></label><br>
		<select name="cbv_appt_status" id="cbv_appt_status">
			<option value="new" <?php selected( $status, 'new' ); ?>>New</option>
			<option value="contacted" <?php selected( $status, 'contacted' ); ?>>Contacted</option>
			<option value="scheduled" <?php selected( $status, 'scheduled' ); ?>>Scheduled</option>
			<option value="done" <?php selected( $status, 'done' ); ?>>Done</option>
		</select>
	</p>
	<?php
}

/* ==========================================================================
   4. Payment page card helper -- this member's most recent request for a
      given trip (or a general/no-trip request when $trip_id is 0), so the
      card can show a "Travel Payment" status instead of always the same
      generic prompt.
   ========================================================================== */
function cbv_get_latest_appointment_request( $user_id, $trip_id ) {
	$posts = get_posts( array(
		'post_type'   => 'cb_appt_request',
		'author'      => $user_id,
		'numberposts' => 1,
		'orderby'     => 'date',
		'order'       => 'DESC',
		'meta_query'  => array(
			array( 'key' => 'cbv_appt_trip_id', 'value' => (int) $trip_id ),
		),
	) );

	if ( empty( $posts ) ) {
		return null;
	}

	return array(
		'status'         => get_post_meta( $posts[0]->ID, 'cbv_appt_status', true ) ?: 'new',
		'preferred_time' => get_post_meta( $posts[0]->ID, 'cbv_appt_preferred_time', true ),
		'date'           => $posts[0]->post_date,
	);
}

function cbv_appointment_status_label( $status ) {
	$labels = array( 'new' => 'Requested', 'contacted' => 'Advisor reached out', 'scheduled' => 'Scheduled', 'done' => 'Complete' );
	return $labels[ $status ] ?? ucfirst( $status );
}

add_action( 'save_post_cb_appt_request', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_appt_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_appt_nonce'], 'cbv_appt_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( isset( $_POST['cbv_appt_status'] ) ) {
		update_post_meta( $post_id, 'cbv_appt_status', sanitize_text_field( wp_unslash( $_POST['cbv_appt_status'] ) ) );
	}
} );
