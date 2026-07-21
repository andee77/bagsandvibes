<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Gate 09: Payments (Stripe)
 * Description: Direct Stripe Checkout Session integration — no WP Simple Pay
 *              dependency, since pricing is dynamic per trip/member (admin
 *              sets full-vs-deposit per trip, member chooses full vs manual
 *              installment). Requires CB_STRIPE_SECRET_KEY,
 *              CB_STRIPE_PUBLISHABLE_KEY, and CB_STRIPE_WEBHOOK_SECRET
 *              defined in wp-config.php before payments will work.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-gate09.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Extra meta: payment mode + installment count, admin-editable per trip.
   ========================================================================== */
add_action( 'init', function () {

	register_post_meta( 'cb_trip', 'cb_payment_mode', array(
		'type'              => 'string',
		'single'            => true,
		'default'           => 'full_only', // 'full_only' | 'deposit_installments'
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () { return current_user_can( 'edit_posts' ); },
	) );

	register_post_meta( 'cb_trip', 'cb_num_installments', array(
		'type'              => 'number',
		'single'            => true,
		'default'           => 1,
		'show_in_rest'      => true,
		'sanitize_callback' => 'absint',
		'auth_callback'     => function () { return current_user_can( 'edit_posts' ); },
	) );

	// Payment log — array of records: user_id, amount, date, session_id, type.
	register_post_meta( 'cb_trip', 'cb_payments', array(
		'type'         => 'array',
		'single'       => true,
		'default'      => array(),
		'show_in_rest' => array( 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) ),
		'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
	) );

} );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cb_gate09_payment', 'Gate 09 — Payment Settings', 'cb_render_payment_meta_box', 'cb_trip', 'side', 'default' );
} );

function cb_render_payment_meta_box( $post ) {
	wp_nonce_field( 'cb_gate09_save', 'cb_gate09_nonce' );
	$mode     = get_post_meta( $post->ID, 'cb_payment_mode', true ) ?: 'full_only';
	$installs = get_post_meta( $post->ID, 'cb_num_installments', true ) ?: 1;
	?>
	<p>
		<label><strong>Payment mode</strong></label><br>
		<select name="cb_payment_mode" style="width:100%;">
			<option value="full_only" <?php selected( $mode, 'full_only' ); ?>>Full payment only</option>
			<option value="deposit_installments" <?php selected( $mode, 'deposit_installments' ); ?>>Deposit + installments</option>
		</select>
	</p>
	<p>
		<label><strong>Number of installments</strong> (after deposit)</label><br>
		<input type="number" name="cb_num_installments" min="1" value="<?php echo esc_attr( $installs ); ?>" style="width:100%;">
	</p>
	<p><em>Deposit amount and full price are set in the Trip Details box above.</em></p>
	<?php
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cb_gate09_nonce'] ) || ! wp_verify_nonce( $_POST['cb_gate09_nonce'], 'cb_gate09_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( isset( $_POST['cb_payment_mode'] ) ) {
		update_post_meta( $post_id, 'cb_payment_mode', sanitize_text_field( wp_unslash( $_POST['cb_payment_mode'] ) ) );
	}
	if ( isset( $_POST['cb_num_installments'] ) ) {
		update_post_meta( $post_id, 'cb_num_installments', absint( $_POST['cb_num_installments'] ) );
	}
} );

/* ==========================================================================
   2. Payment math helpers
   ========================================================================== */
function cb_trip_payments_for_user( $trip_id, $user_id ) {
	$all = get_post_meta( $trip_id, 'cb_payments', true );
	$all = is_array( $all ) ? $all : array();
	return array_values( array_filter( $all, function ( $p ) use ( $user_id ) {
		return isset( $p['user_id'] ) && (int) $p['user_id'] === (int) $user_id;
	} ) );
}

function cb_trip_amount_paid( $trip_id, $user_id ) {
	$sum = 0;
	foreach ( cb_trip_payments_for_user( $trip_id, $user_id ) as $p ) {
		$sum += (float) $p['amount'];
	}
	return $sum;
}

function cb_trip_balance_due( $trip_id, $user_id ) {
	$price = (float) get_post_meta( $trip_id, 'cb_price', true );
	return max( 0, $price - cb_trip_amount_paid( $trip_id, $user_id ) );
}

/**
 * Returns the dollar amount for the member's *next* payment action.
 * full_only trips: always the full remaining balance.
 * deposit_installments trips: the deposit first, then remaining balance
 * spread across whatever installments are left.
 */
function cb_trip_next_payment_amount( $trip_id, $user_id ) {
	$mode    = get_post_meta( $trip_id, 'cb_payment_mode', true ) ?: 'full_only';
	$balance = cb_trip_balance_due( $trip_id, $user_id );

	if ( $balance <= 0 ) {
		return 0;
	}
	if ( $mode === 'full_only' ) {
		return $balance;
	}

	$deposit     = (float) get_post_meta( $trip_id, 'cb_deposit_amount', true );
	$paid_count  = count( cb_trip_payments_for_user( $trip_id, $user_id ) );
	$installs    = max( 1, (int) get_post_meta( $trip_id, 'cb_num_installments', true ) );

	if ( $paid_count === 0 ) {
		return min( $deposit > 0 ? $deposit : $balance, $balance );
	}

	$remaining_installments = max( 1, $installs - ( $paid_count - 1 ) );
	return round( $balance / $remaining_installments, 2 );
}

function cb_trip_allows_installments( $trip_id ) {
	return get_post_meta( $trip_id, 'cb_payment_mode', true ) === 'deposit_installments';
}

/* ==========================================================================
   3. REST: create a Stripe Checkout Session for the current user's next
      payment on a trip, and record completed payments via webhook.
   ========================================================================== */
add_action( 'rest_api_init', function () {

	register_rest_route( 'cb/v1', '/trips/(?P<id>\d+)/checkout', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return is_user_logged_in(); },
		'callback'            => 'cb_create_checkout_session',
	) );

	register_rest_route( 'cb/v1', '/stripe-webhook', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true', // verified via Stripe signature instead
		'callback'            => 'cb_handle_stripe_webhook',
	) );

} );

function cb_create_checkout_session( $request ) {
	if ( ! defined( 'CB_STRIPE_SECRET_KEY' ) ) {
		return new WP_Error( 'cb_stripe_not_configured', 'Payments are not yet configured.', array( 'status' => 500 ) );
	}

	$trip_id = (int) $request['id'];
	$trip    = get_post( $trip_id );
	$user_id = get_current_user_id();

	if ( ! $trip || $trip->post_type !== 'cb_trip' ) {
		return new WP_Error( 'cb_not_found', 'Trip not found.', array( 'status' => 404 ) );
	}
	if ( ! in_array( $user_id, cb_trip_get_roster( $trip_id ), true ) ) {
		return new WP_Error( 'cb_not_on_trip', 'You are not on this trip.', array( 'status' => 403 ) );
	}

	$amount = cb_trip_next_payment_amount( $trip_id, $user_id );
	if ( $amount <= 0 ) {
		return new WP_Error( 'cb_nothing_due', 'No balance due.', array( 'status' => 400 ) );
	}

	$permalink = get_permalink( $trip_id );

	$response = wp_remote_post( 'https://api.stripe.com/v1/checkout/sessions', array(
		'headers' => array(
			'Authorization' => 'Bearer ' . CB_STRIPE_SECRET_KEY,
			'Content-Type'  => 'application/x-www-form-urlencoded',
		),
		'body'    => array(
			'mode'                              => 'payment',
			'payment_method_types'               => array( 'card' ),
			'client_reference_id'                => $user_id,
			'success_url'                         => add_query_arg( 'cb_payment', 'success', $permalink ),
			'cancel_url'                          => add_query_arg( 'cb_payment', 'cancelled', $permalink ),
			'line_items'                          => array(
				array(
					'quantity'   => 1,
					'price_data' => array(
						'currency'     => 'usd',
						'unit_amount'  => (int) round( $amount * 100 ),
						'product_data' => array(
							'name' => get_the_title( $trip_id ),
						),
					),
				),
			),
			'metadata'                            => array(
				'trip_id' => $trip_id,
				'user_id' => $user_id,
			),
		),
		'timeout' => 15,
	) );

	if ( is_wp_error( $response ) ) {
		return new WP_Error( 'cb_stripe_error', $response->get_error_message(), array( 'status' => 502 ) );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( empty( $body['url'] ) ) {
		return new WP_Error( 'cb_stripe_error', isset( $body['error']['message'] ) ? $body['error']['message'] : 'Stripe did not return a checkout URL.', array( 'status' => 502 ) );
	}

	return array( 'checkout_url' => $body['url'] );
}

function cb_handle_stripe_webhook( $request ) {
	if ( ! defined( 'CB_STRIPE_WEBHOOK_SECRET' ) ) {
		return new WP_Error( 'cb_stripe_not_configured', 'Webhook secret not set.', array( 'status' => 500 ) );
	}

	$payload   = $request->get_body();
	$sig_header = $request->get_header( 'stripe_signature' );

	if ( ! $sig_header || ! cb_verify_stripe_signature( $payload, $sig_header, CB_STRIPE_WEBHOOK_SECRET ) ) {
		return new WP_Error( 'cb_invalid_signature', 'Signature verification failed.', array( 'status' => 400 ) );
	}

	$event = json_decode( $payload, true );

	if ( isset( $event['type'] ) && $event['type'] === 'checkout.session.completed' ) {
		$session  = $event['data']['object'];
		$trip_id  = isset( $session['metadata']['trip_id'] ) ? (int) $session['metadata']['trip_id'] : 0;
		$user_id  = isset( $session['metadata']['user_id'] ) ? (int) $session['metadata']['user_id'] : 0;
		$amount   = isset( $session['amount_total'] ) ? $session['amount_total'] / 100 : 0;

		if ( $trip_id && $user_id && $amount > 0 ) {
			$payments   = get_post_meta( $trip_id, 'cb_payments', true );
			$payments   = is_array( $payments ) ? $payments : array();
			$payments[] = array(
				'user_id'    => $user_id,
				'amount'     => $amount,
				'date'       => current_time( 'mysql' ),
				'session_id' => isset( $session['id'] ) ? $session['id'] : '',
			);
			update_post_meta( $trip_id, 'cb_payments', $payments );

			do_action( 'cb_trip_payment_recorded', $trip_id, $user_id, $amount );
		}
	}

	return array( 'received' => true );
}

/**
 * Manual HMAC-SHA256 verification of Stripe's webhook signature — avoids
 * needing the full Stripe PHP SDK just for this one check.
 */
function cb_verify_stripe_signature( $payload, $sig_header, $secret ) {
	$parts = array();
	foreach ( explode( ',', $sig_header ) as $part ) {
		$kv = explode( '=', $part, 2 );
		if ( count( $kv ) === 2 ) {
			$parts[ $kv[0] ] = $kv[1];
		}
	}
	if ( empty( $parts['t'] ) || empty( $parts['v1'] ) ) {
		return false;
	}
	$signed_payload = $parts['t'] . '.' . $payload;
	$expected       = hash_hmac( 'sha256', $signed_payload, $secret );
	return hash_equals( $expected, $parts['v1'] );
}

/* ==========================================================================
   4. Shortcode: [cb_gate_payments] — balance, history, pay buttons.
   ========================================================================== */
add_shortcode( 'cb_gate_payments', function () {

	if ( ! is_user_logged_in() ) {
		return '<p class="cb-empty">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to view payments.</p>';
	}

	$user_id = get_current_user_id();

	$trips = get_posts( array(
		'post_type'   => 'cb_trip',
		'numberposts' => -1,
		'meta_query'  => array(
			array( 'key' => 'cb_status', 'value' => array( 'active', 'accepted' ), 'compare' => 'IN' ),
		),
	) );

	$my_trips = array_filter( $trips, function ( $t ) use ( $user_id ) {
		return in_array( $user_id, cb_trip_get_roster( $t->ID ), true );
	} );

	if ( empty( $my_trips ) ) {
		return '<p class="cb-empty">You\'re not on any trips with payments due yet.</p>';
	}

	ob_start();
	foreach ( $my_trips as $trip ) :
		$balance  = cb_trip_balance_due( $trip->ID, $user_id );
		$next_amt = cb_trip_next_payment_amount( $trip->ID, $user_id );
		$history  = cb_trip_payments_for_user( $trip->ID, $user_id );
		$paid     = cb_trip_amount_paid( $trip->ID, $user_id );
		?>
		<div class="payment-card">
			<h3 class="payment-card-title"><?php echo esc_html( get_the_title( $trip ) ); ?></h3>
			<div class="payment-card-balance">
				<span class="payment-card-balance-label">Balance due</span>
				<span class="payment-card-balance-amount">$<?php echo esc_html( number_format( $balance, 2 ) ); ?></span>
			</div>
			<?php if ( $balance > 0 ) : ?>
				<button class="btn btn-ticket cb-pay-btn" data-trip-id="<?php echo esc_attr( $trip->ID ); ?>">
					Pay $<?php echo esc_html( number_format( $next_amt, 2 ) ); ?><?php echo cb_trip_allows_installments( $trip->ID ) && $next_amt < $balance ? ' (installment)' : ''; ?>
				</button>
			<?php else : ?>
				<span class="payment-card-paid-badge">Paid in full <i class="ti ti-check" aria-hidden="true"></i></span>
			<?php endif; ?>

			<?php if ( ! empty( $history ) ) : ?>
			<div class="payment-history">
				<h4>Payment history</h4>
				<ul>
					<?php foreach ( $history as $p ) : ?>
						<li>$<?php echo esc_html( number_format( (float) $p['amount'], 2 ) ); ?> — <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $p['date'] ) ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>
		</div>
	<?php
	endforeach;
	return ob_get_clean();
} );

/* ==========================================================================
   5. Front-end JS — pay button click handler
   ========================================================================== */
add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_script( 'cb-gate09', content_url( 'uploads/checkedbags/js/gate09.js' ), array(), '1.0.0', true );
	wp_localize_script( 'cb-gate09', 'cbGate09', array(
		'restUrl' => esc_url_raw( rest_url( 'cb/v1/' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
	) );
} );
