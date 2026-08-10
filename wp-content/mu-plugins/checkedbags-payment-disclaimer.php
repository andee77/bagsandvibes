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

To bring you an unforgettable group travel experience, Checked Bags & Good Vibes partners with InteleTravel to handle your official travel bookings, while we curate, host, and manage all your exclusive group events, private gatherings, and on-the-ground coordination.

To ensure complete transparency, here is exactly how your payments and bookings are structured:

1. The CBGV Commitment Fee (Event Coordination & Group Perks)
What it is: A commitment fee paid directly to Checked Bags & Good Vibes.
What it covers: This fee secures your registration for our exclusive, sponsored group events—such as private meet-and-greets, custom watch parties, welcome mixers, and dedicated on-site coordination.
How it's paid: Paid directly to CBGV upon initial sign-up to lock in your space within our private group block.

2. Remaining Travel Payments (InteleTravel)
What it is: The balance for your actual travel package (such as your cruise fare, cabin accommodations, taxes, port fees, and optional add-ons).
How it's paid: Processed securely through our official travel partner, InteleTravel, ensuring your travel booking is officially registered, fully protected, and tied directly into your itinerary.
Payment Flexibility: You can pay your InteleTravel balance via standard scheduled installments or utilize available "Book Now, Pay Later" financing options.

Why This Structure Benefits You
By dividing the process this way, you receive the best of both worlds: specialized, high-touch group events, custom itineraries, and personal coordination tailored by Checked Bags & Good Vibes, combined with the robust, secure booking and licensing infrastructure of a major travel provider through InteleTravel.
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
			I have read and understand how CBGV Commitment Fee and Travel Payment are handled.
			<span class="cbv-required" aria-hidden="true">*</span>
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
