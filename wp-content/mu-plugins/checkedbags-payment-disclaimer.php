<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Payment Disclaimer
 * Description: Site-wide versioned-acceptance disclaimer for the Payment
 *              page (Section 6 of the trip-invite build's post-launch
 *              list) — structurally a straight clone of the Membership
 *              Terms system (checkedbags-trip-invites.php, Phase 3): one
 *              option holding version/content/updated, two flat usermeta
 *              keys tracking acceptance, a Settings page to edit it, and a
 *              REST endpoint to accept it. Deliberately NOT cloning Phase
 *              3's site-wide template_redirect hard-lock, though -- the
 *              Payment page shows this inline (full text + checkbox on
 *              first visit, collapsing to a persistent link after
 *              acceptance) rather than redirecting the member away from
 *              everything else on the site until they accept.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-payment-disclaimer.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cbv_get_payment_disclaimer() {
	$default = array( 'version' => 1, 'content' => '', 'updated' => '' );
	return wp_parse_args( get_option( 'cbv_payment_disclaimer', $default ), $default );
}

function cbv_get_current_payment_disclaimer_version() {
	return (int) cbv_get_payment_disclaimer()['version'];
}

function cbv_get_current_payment_disclaimer_content() {
	return cbv_get_payment_disclaimer()['content'];
}

/**
 * True if $user_id hasn't accepted the currently-published version (or has
 * never accepted at all).
 */
function cbv_user_needs_payment_disclaimer_reaccept( $user_id ) {
	$accepted = (int) get_user_meta( $user_id, '_accepted_payment_disclaimer_version', true );
	return $accepted < cbv_get_current_payment_disclaimer_version();
}

/**
 * One-time seed of the real disclaimer copy (provided at build time), so
 * this launches with actual content from day one instead of an empty
 * textarea an admin has to remember to fill in -- same "gated by a done
 * flag on init" idiom as the Membership Terms legacy migration.
 */
add_action( 'init', function () {
	if ( get_option( 'cbv_payment_disclaimer_seeded' ) ) {
		return;
	}

	$seed_content = <<<'TEXT'
How Your Booking & Payment Work

To bring you an unforgettable group travel experience, Checked Bags & Good Vibes works with an independent travel advisor, Andrea M. Peaten, who handles your official travel bookings, while we curate, host, and manage all your exclusive group events, private gatherings, and on-the-ground coordination.

To ensure complete transparency, here is exactly how your payments and bookings are structured:

1. The CBGV Group Experience Fee (Event Coordination & Group Perks)
What it is: A fee paid directly to Checked Bags & Good Vibes.
What it covers: This fee secures your registration for our exclusive, sponsored group events—such as private meet-and-greets, custom watch parties, welcome mixers, and dedicated on-site coordination.
How it's paid: Paid directly to CBGV upon initial sign-up to lock in your space within our private group block.

2. Remaining Travel Payments (your travel advisor)
What it is: The balance for your actual travel package (such as your cruise fare, cabin accommodations, taxes, port fees, and optional add-ons).
How it's paid: Booked by your independent travel advisor, Andrea M. Peaten, and paid directly to the cruise line, ensuring your travel booking is officially registered, fully protected, and tied directly into your itinerary.
Payment Flexibility: You can pay your travel balance via standard scheduled installments or utilize available "Book Now, Pay Later" financing options.

Why This Structure Benefits You
By dividing the process this way, you receive the best of both worlds: specialized, high-touch group events, custom itineraries, and personal coordination tailored by Checked Bags & Good Vibes, combined with the secure booking of an independent travel advisor. Host agency details are available on request.
TEXT;

	$disclaimer = cbv_get_payment_disclaimer();
	if ( '' === trim( $disclaimer['content'] ) ) {
		$disclaimer['content'] = $seed_content;
		$disclaimer['updated'] = current_time( 'mysql' );
		update_option( 'cbv_payment_disclaimer', $disclaimer, false );
	}

	update_option( 'cbv_payment_disclaimer_seeded', true, false );
}, 30 );

/**
 * Admin screen: Settings -> Payment Disclaimer. Editing and saving bumps
 * the version automatically -- anyone who already accepted an earlier
 * version sees the full disclaimer again on their next Payment page visit.
 */
add_action( 'admin_menu', function () {
	add_options_page( 'Payment Disclaimer', 'Payment Disclaimer', 'manage_options', 'cbv-payment-disclaimer', 'cbv_render_payment_disclaimer_admin_page' );
} );

function cbv_render_payment_disclaimer_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( isset( $_POST['cbv_payment_disclaimer_nonce'] ) && wp_verify_nonce( $_POST['cbv_payment_disclaimer_nonce'], 'cbv_save_payment_disclaimer' ) ) {
		$disclaimer  = cbv_get_payment_disclaimer();
		$new_content = isset( $_POST['cbv_payment_disclaimer_content'] ) ? wp_kses_post( wp_unslash( $_POST['cbv_payment_disclaimer_content'] ) ) : '';

		if ( trim( $new_content ) !== trim( $disclaimer['content'] ) ) {
			$disclaimer['version'] = $disclaimer['version'] + 1;
			$disclaimer['content'] = $new_content;
			$disclaimer['updated'] = current_time( 'mysql' );
			update_option( 'cbv_payment_disclaimer', $disclaimer, false );
			echo '<div class="notice notice-success"><p>' . sprintf(
				esc_html__( 'Saved as version %d. Anyone who already accepted an earlier version will see the full disclaimer again on their next Payment page visit.', 'cbv' ),
				(int) $disclaimer['version']
			) . '</p></div>';
		} else {
			echo '<div class="notice notice-info"><p>No changes — version unchanged.</p></div>';
		}
	}

	$disclaimer = cbv_get_payment_disclaimer();
	?>
	<div class="wrap">
		<h1>Payment Disclaimer</h1>
		<p>
			Current version: <strong><?php echo (int) $disclaimer['version']; ?></strong>
			(last updated <?php echo esc_html( $disclaimer['updated'] ?: 'never' ); ?>)
		</p>
		<p class="description">Shown in full (with an acceptance checkbox) the first time a member visits the Payment page, and on the standalone Payment Disclaimer page. Saving with changed content automatically bumps the version and prompts re-acceptance from anyone already on an older version.</p>
		<form method="post">
			<?php wp_nonce_field( 'cbv_save_payment_disclaimer', 'cbv_payment_disclaimer_nonce' ); ?>
			<textarea name="cbv_payment_disclaimer_content" rows="24" style="width:100%;max-width:800px;font-family:monospace;"><?php echo esc_textarea( $disclaimer['content'] ); ?></textarea>
			<p><button type="submit" class="button button-primary">Save &amp; publish new version</button></p>
		</form>
	</div>
	<?php
}

/**
 * Public, read-only view of the current disclaimer -- [cbv_payment_disclaimer].
 * Meant for the real standalone "Payment Disclaimer" WP Page (linked from
 * the collapsed banner on the Payment page and from the site footer), same
 * idiom as [cbv_membership_terms]'s own dedicated page.
 */
add_shortcode( 'cbv_payment_disclaimer', function () {
	$version = cbv_get_current_payment_disclaimer_version();
	$content = cbv_get_current_payment_disclaimer_content();

	ob_start();
	?>
	<div class="cbv-payment-disclaimer-page">
		<p class="cbv-terms-version">Version <?php echo (int) $version; ?></p>
		<?php if ( '' === trim( $content ) ) : ?>
			<p class="cb-empty">No Payment Disclaimer content has been published yet.</p>
		<?php else : ?>
			<?php echo wp_kses_post( wpautop( $content ) ); ?>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'cb/v1', '/accept-payment-disclaimer', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return is_user_logged_in();
		},
		'callback'            => function () {
			$user_id = get_current_user_id();
			$version = cbv_get_current_payment_disclaimer_version();

			update_user_meta( $user_id, '_accepted_payment_disclaimer_version', $version );
			update_user_meta( $user_id, '_accepted_payment_disclaimer_date', current_time( 'mysql' ) );

			return array( 'accepted' => true, 'version' => $version );
		},
	) );
} );

/**
 * Shared banner the Payment page (checkedbags-gate09.php's [cb_gate_payments]
 * shortcode) renders at the very top: full text + checkbox + Accept button
 * on first visit (or after a version bump), collapsing to a small
 * persistent link once accepted. Kept here, not in gate09.php, since it's
 * purely a function of disclaimer-acceptance state, not payment data.
 */
function cbv_render_payment_disclaimer_banner( $user_id ) {
	$disclaimer_page_url = home_url( '/payment-disclaimer/' );

	if ( ! cbv_user_needs_payment_disclaimer_reaccept( $user_id ) ) {
		ob_start();
		?>
		<p class="cbv-payment-disclaimer-collapsed">
			<a href="<?php echo esc_url( $disclaimer_page_url ); ?>">Payment &amp; Booking Disclaimer</a>
		</p>
		<?php
		return ob_get_clean();
	}

	$version = cbv_get_current_payment_disclaimer_version();
	$content = cbv_get_current_payment_disclaimer_content();

	ob_start();
	?>
	<div class="cbv-payment-disclaimer-banner">
		<div class="cbv-payment-disclaimer-text">
			<?php echo wp_kses_post( wpautop( $content ) ); ?>
		</div>
		<label>
			<input type="checkbox" id="cbv-payment-disclaimer-checkbox">
			<span class="cbv-required" aria-hidden="true">*</span>
			I have read and understand how the CBGV Group Experience Fee and Travel Payment are handled.
		</label>
		<p><button type="button" class="btn btn-ticket" id="cbv-payment-disclaimer-accept" disabled>Continue to Payments</button></p>
		<div id="cbv-payment-disclaimer-result"></div>
	</div>
	<script>
	(function () {
		var restUrl  = <?php echo wp_json_encode( esc_url_raw( rest_url( 'cb/v1/' ) ) ); ?>;
		var nonce    = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;
		var checkbox = document.getElementById( 'cbv-payment-disclaimer-checkbox' );
		var accept   = document.getElementById( 'cbv-payment-disclaimer-accept' );
		var result   = document.getElementById( 'cbv-payment-disclaimer-result' );

		checkbox.addEventListener( 'change', function () { accept.disabled = ! checkbox.checked; } );

		accept.addEventListener( 'click', function () {
			accept.disabled = true;
			accept.textContent = 'Saving…';

			fetch( restUrl + 'accept-payment-disclaimer', { method: 'POST', headers: { 'X-WP-Nonce': nonce } } )
				.then( function ( r ) { return r.json().then( function ( body ) { return { ok: r.ok, body: body }; } ); } )
				.then( function ( res ) {
					if ( res.ok && res.body.accepted ) {
						location.reload();
					} else {
						accept.disabled = false;
						accept.textContent = 'Continue to Payments';
						result.textContent = 'Something went wrong — please try again.';
					}
				} )
				.catch( function () {
					accept.disabled = false;
					accept.textContent = 'Continue to Payments';
					result.textContent = 'Request failed — please try again.';
				} );
		} );
	})();
	</script>
	<?php
	return ob_get_clean();
}

/**
 * Staff screen: Users -> Disclaimer Acceptance. One row per member with the
 * Payment Disclaimer version and date they accepted, flagging anyone not on
 * the current version, plus a CSV download of the same rows. Read-only: it
 * never changes acceptance data.
 */
add_action( 'admin_menu', function () {
	add_users_page( 'Payment Disclaimer Acceptance', 'Disclaimer Acceptance', 'manage_options', 'cbv-disclaimer-acceptance', 'cbv_render_disclaimer_acceptance_page' );
} );

/**
 * Rows for the screen and the CSV, members needing to accept first, then by
 * name. Administrators are left out unless they're on a trip roster.
 */
function cbv_disclaimer_acceptance_rows() {
	$current = cbv_get_current_payment_disclaimer_version();

	// user ID => trip titles, from every trip's roster.
	$trips_by_user = array();
	$trip_ids      = get_posts( array(
		'post_type'      => 'cb_trip',
		'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	) );
	foreach ( $trip_ids as $trip_id ) {
		foreach ( cb_trip_get_roster( $trip_id ) as $user_id ) {
			$trips_by_user[ (int) $user_id ][] = get_the_title( $trip_id );
		}
	}

	$rows = array();
	foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $user ) {
		$is_admin = in_array( 'administrator', (array) $user->roles, true );
		if ( $is_admin && empty( $trips_by_user[ $user->ID ] ) ) {
			continue;
		}

		$version = (int) get_user_meta( $user->ID, '_accepted_payment_disclaimer_version', true );
		$date    = get_user_meta( $user->ID, '_accepted_payment_disclaimer_date', true );

		if ( ! $version ) {
			$status = 'Never accepted';
		} elseif ( $version < $current ) {
			$status = 'Older version';
		} else {
			$status = 'Current';
		}

		if ( $is_admin ) {
			$type = 'Staff';
		} elseif ( function_exists( 'cbv_user_is_full_member' ) && cbv_user_is_full_member( $user->ID ) ) {
			$type = 'Full Member';
		} else {
			$type = 'Trip Guest';
		}

		$rows[] = array(
			'user_id' => $user->ID,
			'name'    => $user->display_name,
			'email'   => $user->user_email,
			'type'    => $type,
			'trips'   => implode( '; ', $trips_by_user[ $user->ID ] ?? array() ),
			'version' => $version ? $version : '',
			'date'    => $date ? $date : '',
			'status'  => $status,
		);
	}

	usort( $rows, function ( $a, $b ) {
		$a_ok = 'Current' === $a['status'];
		$b_ok = 'Current' === $b['status'];
		if ( $a_ok !== $b_ok ) {
			return $a_ok ? 1 : -1;
		}
		return strcasecmp( $a['name'], $b['name'] );
	} );

	return $rows;
}

add_action( 'admin_post_cbv_disclaimer_acceptance_csv', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	check_admin_referer( 'cbv_disclaimer_acceptance_csv' );

	$filename = 'payment-disclaimer-acceptance-' . current_time( 'Y-m-d' ) . '.csv';
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	$out = fopen( 'php://output', 'w' );
	fwrite( $out, "\xEF\xBB\xBF" ); // So Excel reads accented names correctly.
	fputcsv( $out, array( 'Member', 'Email', 'Type', 'Trips', 'Accepted Version', 'Accepted Date', 'Status (current version ' . cbv_get_current_payment_disclaimer_version() . ')' ) );
	foreach ( cbv_disclaimer_acceptance_rows() as $row ) {
		$cells = array( $row['name'], $row['email'], $row['type'], $row['trips'], $row['version'], $row['date'], $row['status'] );
		// Stop Excel treating a cell that starts with =, +, - or @ as a formula.
		$cells = array_map( function ( $cell ) {
			return preg_match( '/^[=+\-@]/', (string) $cell ) ? "'" . $cell : $cell;
		}, $cells );
		fputcsv( $out, $cells );
	}
	fclose( $out );
	exit;
} );

function cbv_render_disclaimer_acceptance_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$current  = cbv_get_current_payment_disclaimer_version();
	$rows     = cbv_disclaimer_acceptance_rows();
	$show_all = ! empty( $_GET['show'] ) && 'all' === $_GET['show'];
	$pending  = count( array_filter( $rows, function ( $row ) {
		return 'Current' !== $row['status'];
	} ) );
	$shown    = $show_all ? $rows : array_filter( $rows, function ( $row ) {
		return 'Current' !== $row['status'];
	} );

	$page_url = admin_url( 'users.php?page=cbv-disclaimer-acceptance' );
	$csv_url  = wp_nonce_url( admin_url( 'admin-post.php?action=cbv_disclaimer_acceptance_csv' ), 'cbv_disclaimer_acceptance_csv' );
	?>
	<div class="wrap">
		<h1>Payment Disclaimer Acceptance</h1>
		<p>
			Current version: <strong><?php echo (int) $current; ?></strong>
			(<a href="<?php echo esc_url( admin_url( 'options-general.php?page=cbv-payment-disclaimer' ) ); ?>">edit the disclaimer</a>).
			<strong><?php echo (int) $pending; ?></strong> of <?php echo count( $rows ); ?> members have not accepted the current version.
		</p>
		<p class="description">Members accept the disclaimer the first time they open the Payment page. Staff accounts are only listed when they're on a trip roster.</p>
		<p>
			<?php if ( $show_all ) : ?>
				<a href="<?php echo esc_url( $page_url ); ?>">Show only members who need to accept</a>
			<?php else : ?>
				<a href="<?php echo esc_url( add_query_arg( 'show', 'all', $page_url ) ); ?>">Show all members</a>
			<?php endif; ?>
			&nbsp;|&nbsp;
			<a class="button" href="<?php echo esc_url( $csv_url ); ?>">Download CSV (all members)</a>
		</p>
		<table class="widefat striped">
			<thead>
				<tr><th>Member</th><th>Email</th><th>Type</th><th>Trips</th><th>Accepted version</th><th>Accepted date</th><th>Status</th></tr>
			</thead>
			<tbody>
				<?php if ( ! $shown ) : ?>
					<tr><td colspan="7">Everyone has accepted version <?php echo (int) $current; ?>.</td></tr>
				<?php endif; ?>
				<?php foreach ( $shown as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( get_edit_user_link( $row['user_id'] ) ); ?>"><?php echo esc_html( $row['name'] ); ?></a></td>
						<td><?php echo esc_html( $row['email'] ); ?></td>
						<td><?php echo esc_html( $row['type'] ); ?></td>
						<td><?php echo esc_html( $row['trips'] ); ?></td>
						<td><?php echo esc_html( $row['version'] ); ?></td>
						<td><?php echo esc_html( $row['date'] ? date_i18n( 'M j, Y g:i a', strtotime( $row['date'] ) ) : '' ); ?></td>
						<td><?php echo 'Current' === $row['status'] ? esc_html( $row['status'] ) : '<strong>' . esc_html( $row['status'] ) . '</strong>'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
