<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — CBGV Group Experience Fee
 * Description: Step 8b of the public trip landing redesign: Checked Bags &
 *              Good Vibes' own GROUP EXPERIENCE FEE (CBGV does the group
 *              planning; it is not a travel agent fee and not part of the
 *              cruise fare). A standard fee per event type (Settings >
 *              Group Experience Fees), a per-trip choice (standard / a
 *              different fee / no fee), and the helpers the price board
 *              (Step 9) and the proposal PDF use.
 *
 *              The fee is the fifth line of the all-in breakdown ("CBGV
 *              Group Experience Fee"), included in the all-in total, never
 *              folded into the cruise fare, and not opt-out. Basis: per
 *              traveler, per cabin, or flat per booking (counted once in
 *              each cabin's price).
 *
 *              NOT PUBLIC UNTIL SWITCHED ON: while "Group Experience Fee is live" is
 *              off (the default) the fee shows only in admin previews
 *              (?preview=new) and the admin boxes, never in proposal PDFs or
 *              any public view. Refund policy: docs/policies/
 *              cbgv-group-experience-fee-refunds.md.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-fees.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CBV_LP_FEES_OPTION', 'cbv_lp_planning_fees' );
define( 'CBV_LP_FEE_LIVE_OPTION', 'cbv_lp_planning_fee_live' );
define( 'CBV_LP_CHILD_AGE_OPTION', 'cbv_lp_child_age_under' );

/* ==========================================================================
   1. Small pure helpers (tested directly).
   ========================================================================== */

/** The three bases (stored value => admin label). */
function cbv_lp_fee_bases() {
	return array(
		'per_traveler' => 'Per traveler',
		'per_cabin'    => 'Per cabin / room',
		'per_booking'  => 'Flat per booking',
	);
}

/** A typed amount -> a float of dollars and cents, never negative, at most 100,000 ('' / junk = 0). */
function cbv_lp_clean_fee_amount( $value ) {
	if ( ! is_scalar( $value ) ) {
		return 0.0;
	}
	$value = str_replace( array( '$', ',', ' ' ), '', trim( (string) $value ) );
	if ( '' === $value || ! is_numeric( $value ) ) {
		return 0.0;
	}
	return round( min( 100000, max( 0, (float) $value ) ), 2 );
}

function cbv_lp_clean_fee_basis( $value ) {
	return is_scalar( $value ) && isset( cbv_lp_fee_bases()[ (string) $value ] ) ? (string) $value : 'per_traveler';
}

/** Posted settings page rows -> the stored option (one row per event type). */
function cbv_lp_sanitize_planning_fees( $raw ) {
	$raw   = is_array( $raw ) ? $raw : array();
	$types = function_exists( 'cbv_lp_event_types' ) ? array_keys( cbv_lp_event_types() ) : array();
	$out   = array();
	foreach ( $types as $type ) {
		$row          = isset( $raw[ $type ] ) && is_array( $raw[ $type ] ) ? $raw[ $type ] : array();
		$out[ $type ] = array(
			'amount' => cbv_lp_clean_fee_amount( $row['amount'] ?? '' ),
			'basis'  => cbv_lp_clean_fee_basis( $row['basis'] ?? '' ),
		);
	}
	return $out;
}

/** Posted trip box values -> what gets stored on the trip. */
function cbv_lp_sanitize_trip_fee( $raw ) {
	$raw  = is_array( $raw ) ? $raw : array();
	$mode = isset( $raw['mode'] ) && in_array( $raw['mode'], array( 'standard', 'custom', 'none' ), true ) ? $raw['mode'] : 'standard';
	return array(
		'mode'   => $mode,
		'amount' => 'custom' === $mode ? cbv_lp_clean_fee_amount( $raw['amount'] ?? '' ) : 0.0,
		'basis'  => 'custom' === $mode ? cbv_lp_clean_fee_basis( $raw['basis'] ?? '' ) : 'per_traveler',
	);
}

/** The fee for one cabin quoted for $headcount travelers. */
function cbv_lp_fee_for_cabin( $fee, $headcount ) {
	if ( ! is_array( $fee ) || empty( $fee['amount'] ) || (float) $fee['amount'] <= 0 ) {
		return 0.0;
	}
	$amount = (float) $fee['amount'];
	if ( 'per_traveler' === ( $fee['basis'] ?? 'per_traveler' ) ) {
		return round( $amount * max( 1, (int) $headcount ), 2 );
	}
	return round( $amount, 2 ); // per cabin, or flat per booking counted once in each cabin's price
}

/** A child's share of a per-traveler fee: always half (LaDon's rule, 2026-10-08). */
function cbv_lp_fee_child_amount( $fee ) {
	return is_array( $fee ) && ! empty( $fee['amount'] ) ? round( max( 0, (float) $fee['amount'] ) / 2, 2 ) : 0.0;
}

/**
 * The fee for a party of $adults adults and $children children. Per traveler:
 * the full fee per adult and half per child. Per cabin and flat per booking
 * are not affected (charged once).
 */
function cbv_lp_fee_for_party( $fee, $adults, $children = 0 ) {
	if ( ! is_array( $fee ) || empty( $fee['amount'] ) || (float) $fee['amount'] <= 0 ) {
		return 0.0;
	}
	$adults   = max( 0, (int) $adults );
	$children = max( 0, (int) $children );
	if ( 'per_traveler' === ( $fee['basis'] ?? 'per_traveler' ) ) {
		return round( (float) $fee['amount'] * $adults + cbv_lp_fee_child_amount( $fee ) * $children, 2 );
	}
	return $adults + $children > 0 ? round( (float) $fee['amount'], 2 ) : 0.0;
}

/** "Children are under N" (years) as set on the settings page; 0 until LaDon confirms the age. */
function cbv_lp_child_age_under() {
	$age = (int) get_option( CBV_LP_CHILD_AGE_OPTION, 0 );
	return $age >= 1 && $age <= 21 ? $age : 0;
}

/* ==========================================================================
   2. The fee for a trip.
   ========================================================================== */

/** Is the Group Experience Fee switched on for proposals and public pages? */
function cbv_lp_planning_fee_is_live() {
	return (bool) get_option( CBV_LP_FEE_LIVE_OPTION, 0 );
}

/**
 * The trip's configured fee, regardless of the live switch:
 *   array( 'amount' => 250.0, 'basis' => 'per_traveler', 'source' => 'standard'|'trip'|'none' )
 * 'none' (amount 0) when the trip waives it, or the standard for its event type is blank / $0.
 */
function cbv_lp_planning_fee( $trip_id ) {
	$trip = get_post_meta( (int) $trip_id, 'cbv_lp_planning_fee', true );
	$trip = cbv_lp_sanitize_trip_fee( is_array( $trip ) ? $trip : array() );
	if ( 'none' === $trip['mode'] ) {
		return array( 'amount' => 0.0, 'basis' => 'per_traveler', 'source' => 'none' );
	}
	if ( 'custom' === $trip['mode'] ) {
		return array( 'amount' => $trip['amount'], 'basis' => $trip['basis'], 'source' => $trip['amount'] > 0 ? 'trip' : 'none' );
	}
	$type = function_exists( 'cbv_lp_event_type' ) ? cbv_lp_event_type( $trip_id ) : 'cruise';
	$all  = get_option( CBV_LP_FEES_OPTION, array() );
	$row  = is_array( $all ) && isset( $all[ $type ] ) && is_array( $all[ $type ] ) ? $all[ $type ] : array();
	$amt  = cbv_lp_clean_fee_amount( $row['amount'] ?? '' );
	return array( 'amount' => $amt, 'basis' => cbv_lp_clean_fee_basis( $row['basis'] ?? '' ), 'source' => $amt > 0 ? 'standard' : 'none' );
}

/**
 * The fee to SHOW in a given place, or null for none.
 *   'page': the new-design page. Before the fee is live, only in an admin's ?preview=new.
 *   'pdf' : the proposal PDF. Only once the fee is live.
 */
function cbv_lp_planning_fee_shown( $trip_id, $where = 'page' ) {
	$fee = cbv_lp_planning_fee( $trip_id );
	if ( $fee['amount'] <= 0 ) {
		return null;
	}
	if ( cbv_lp_planning_fee_is_live() ) {
		return $fee;
	}
	if ( 'page' === $where && function_exists( 'cbv_lp_preview_requested' ) && cbv_lp_preview_requested() && current_user_can( 'manage_options' ) ) {
		return $fee;
	}
	return null;
}

/** "$250 per traveler" / "$150 per cabin" / "$100 per booking" in the trip's own words. */
function cbv_lp_fee_description( $fee, $labels = array() ) {
	if ( ! is_array( $fee ) || empty( $fee['amount'] ) ) {
		return 'No Group Experience Fee';
	}
	$money = '$' . number_format_i18n( (float) $fee['amount'], floor( (float) $fee['amount'] ) == (float) $fee['amount'] ? 0 : 2 ); // phpcs:ignore Universal.Operators.StrictComparisons -- float vs its own floor
	switch ( $fee['basis'] ?? 'per_traveler' ) {
		case 'per_cabin':
			return $money . ' per ' . strtolower( (string) ( $labels['accommodation'] ?? 'cabin' ) );
		case 'per_booking':
			return $money . ' per booking';
		default:
			return $money . ' per ' . strtolower( (string) ( $labels['party_one'] ?? 'traveler' ) );
	}
}

/**
 * The note under the price board (plain text sentences, escaped when drawn),
 * or array() when the trip shows no fee. Wording approved 2026-10-08
 * (compliance update: Group Experience Fee, same note for every trip type).
 */
function cbv_lp_planning_fee_note( $trip_id ) {
	$fee = cbv_lp_planning_fee_shown( $trip_id, 'page' );
	if ( ! $fee ) {
		return array();
	}
	$labels = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
	$date   = function_exists( 'cbv_lp_apply_tokens' ) ? trim( cbv_lp_apply_tokens( '{date:final_payment}', $trip_id ) ) : '';
	$until  = '' !== $date ? $date : "the trip's final payment date";

	$lines   = array();
	$lines[] = 'The CBGV Group Experience Fee is paid to Checked Bags & Good Vibes (a d/b/a of JourneyWell Global LLC) for the group program; travel payments go directly to the cruise line or supplier. It\'s fully refundable within 7 days of paying, 50% refundable until ' . $until . ', and non-refundable after that. Full refund if the trip is cancelled.';
	if ( 'per_traveler' === $fee['basis'] ) {
		$lines[] = 'Children pay half the CBGV Group Experience Fee.';
	}
	if ( 'per_booking' === $fee['basis'] ) {
		$lines[] = 'One fee per booking, however many ' . strtolower( (string) ( $labels['accommodation_plural'] ?? 'cabins' ) ) . ' you book together.';
	}
	return $lines;
}

/* ==========================================================================
   3. Settings > Group Experience Fees (administrators only).
   ========================================================================== */
add_action( 'admin_init', function () {
	register_setting( 'cbv_lp_planning_fees_group', CBV_LP_FEES_OPTION, array(
		'type'              => 'array',
		'sanitize_callback' => 'cbv_lp_sanitize_planning_fees',
		'default'           => array(),
	) );
	register_setting( 'cbv_lp_planning_fees_group', CBV_LP_CHILD_AGE_OPTION, array(
		'type'              => 'integer',
		'sanitize_callback' => function ( $v ) {
			$v = absint( $v );
			return $v >= 1 && $v <= 21 ? $v : 0;
		},
		'default'           => 0,
	) );
	register_setting( 'cbv_lp_planning_fees_group', CBV_LP_FEE_LIVE_OPTION, array(
		'type'              => 'boolean',
		'sanitize_callback' => function ( $v ) {
			return empty( $v ) ? 0 : 1;
		},
		'default'           => 0,
	) );
} );

add_action( 'admin_menu', function () {
	add_options_page( 'Group Experience Fees', 'Group Experience Fees', 'manage_options', 'cbv-experience-fees', 'cbv_lp_render_fees_page' );
} );

function cbv_lp_render_fees_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$types = function_exists( 'cbv_lp_event_types' ) ? cbv_lp_event_types() : array();
	$saved = cbv_lp_sanitize_planning_fees( get_option( CBV_LP_FEES_OPTION, array() ) );
	$live  = cbv_lp_planning_fee_is_live();
	?>
	<div class="wrap">
		<h1>Group Experience Fees</h1>
		<p>Checked Bags &amp; Good Vibes' own <strong>Group Experience Fee</strong> (not a travel agent fee, not part of the cruise fare). It is added to the all-in price on the new landing design as its own line, "CBGV Group Experience Fee", and cannot be opted out of. Each trip uses the standard fee for its event type unless the trip's <strong>Group Experience Fee</strong> box says otherwise. Leave an amount blank (or 0) for no fee.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'cbv_lp_planning_fees_group' ); ?>
			<table class="widefat striped" style="max-width:760px;">
				<thead><tr><th>Event type</th><th>Amount ($)</th><th>Basis</th></tr></thead>
				<tbody>
				<?php foreach ( $types as $key => $type ) :
					$row = $saved[ $key ] ?? array( 'amount' => 0, 'basis' => 'per_traveler' );
					?>
					<tr>
						<td><label for="cbv_fee_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $type['label'] ); ?></label></td>
						<td><input type="text" inputmode="decimal" id="cbv_fee_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( CBV_LP_FEES_OPTION . '[' . $key . '][amount]' ); ?>" value="<?php echo $row['amount'] > 0 ? esc_attr( number_format( $row['amount'], 2, '.', '' ) ) : ''; ?>" class="small-text" style="width:8em;"></td>
						<td>
							<select name="<?php echo esc_attr( CBV_LP_FEES_OPTION . '[' . $key . '][basis]' ); ?>" aria-label="<?php echo esc_attr( $type['label'] . ' basis' ); ?>">
								<?php foreach ( cbv_lp_fee_bases() as $b => $label ) : ?>
									<option value="<?php echo esc_attr( $b ); ?>" <?php selected( $row['basis'], $b ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">Flat per booking is counted once in each cabin's all-in price, with the note "one fee per booking, however many cabins you book together".</p>
			<h2>Children</h2>
			<p>When the fee is charged <strong>per traveler</strong>, children always pay <strong>half</strong> (for example $250 per adult, $125 per child). Per cabin and flat per booking fees are not affected.</p>
			<p><label for="cbv_child_age">Children are under</label> <input type="number" min="1" max="21" step="1" id="cbv_child_age" name="<?php echo esc_attr( CBV_LP_CHILD_AGE_OPTION ); ?>" value="<?php echo cbv_lp_child_age_under() ? (int) cbv_lp_child_age_under() : ''; ?>" class="small-text"> years old</p>
			<p class="description">Shown on the traveler intake form next to "Additional adults" and "Additional children", so members choose the right box. Leave blank until the age is confirmed.</p>
			<h2>Show the fee to clients</h2>
			<p><label><input type="checkbox" name="<?php echo esc_attr( CBV_LP_FEE_LIVE_OPTION ); ?>" value="1" <?php checked( $live ); ?>> <strong>Group Experience Fee is live</strong></label></p>
			<p class="description">Off: the fee only shows in admin previews of the new design (<code>?preview=new</code>) and on these admin screens; proposals and public pages do not include it. Turn it on only after the go-live business checks are done (InteleTravel terms, Seller-of-Travel registration, how CBGV collects the fee).</p>
			<p class="description">Refund policy (applies to every trip type): <code>docs/policies/cbgv-group-experience-fee-refunds.md</code>. The refund cutoff is each trip's own final payment date.</p>
			<?php submit_button( 'Save Group Experience Fees' ); ?>
		</form>
		<?php
		$guard_log = get_option( 'cb_payment_guard_log', array() );
		$guard_log = is_array( $guard_log ) ? $guard_log : array();
		?>
		<h2>Payment safeguard log</h2>
		<p class="description">Charges the Payment page refused because they were above the limit (fee x travelers + approved extras, less what was already paid), and payments Stripe reported above that limit. Check this during the monthly reconciliation. Last 100 entries.</p>
		<?php if ( empty( $guard_log ) ) : ?>
			<p>Nothing logged.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:760px;">
				<thead><tr><th>When</th><th>What</th><th>Trip</th><th>Member</th><th>Amount</th><th>Limit</th></tr></thead>
				<tbody>
				<?php foreach ( $guard_log as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['time'] ?? '' ); ?></td>
						<td><?php echo esc_html( $row['where'] ?? '' ); ?></td>
						<td><?php echo esc_html( get_the_title( (int) ( $row['trip_id'] ?? 0 ) ) . ' (' . (int) ( $row['trip_id'] ?? 0 ) . ')' ); ?></td>
						<td><?php $u = get_userdata( (int) ( $row['user_id'] ?? 0 ) ); echo esc_html( $u ? $u->display_name : '#' . (int) ( $row['user_id'] ?? 0 ) ); ?></td>
						<td>$<?php echo esc_html( number_format( (float) ( $row['amount'] ?? 0 ), 2 ) ); ?></td>
						<td>$<?php echo esc_html( number_format( (float) ( $row['limit'] ?? 0 ), 2 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/* ==========================================================================
   4. Trip edit screen: the "Group Experience Fee" box (administrators only).
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	if ( current_user_can( 'manage_options' ) ) {
		add_meta_box( 'cbv_lp_planning_fee', 'Group Experience Fee (new design)', 'cbv_lp_render_trip_fee_box', 'cb_trip', 'normal', 'default' );
	}
} );

function cbv_lp_render_trip_fee_box( $post ) {
	wp_nonce_field( 'cbv_lp_fee_save', 'cbv_lp_fee_nonce' );
	$trip     = get_post_meta( $post->ID, 'cbv_lp_planning_fee', true );
	$trip     = cbv_lp_sanitize_trip_fee( is_array( $trip ) ? $trip : array() );
	$labels   = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $post->ID ) : array();
	$type     = function_exists( 'cbv_lp_event_type' ) ? cbv_lp_event_type( $post->ID ) : 'cruise';
	$types    = function_exists( 'cbv_lp_event_types' ) ? cbv_lp_event_types() : array();
	$all      = cbv_lp_sanitize_planning_fees( get_option( CBV_LP_FEES_OPTION, array() ) );
	$standard = $all[ $type ] ?? array( 'amount' => 0, 'basis' => 'per_traveler' );
	$std_text = cbv_lp_fee_description( $standard, $labels );
	?>
	<p class="description">Checked Bags &amp; Good Vibes' own Group Experience Fee, added to the all-in price on the new landing design as "CBGV Group Experience Fee". Standard fees: <a href="<?php echo esc_url( admin_url( 'options-general.php?page=cbv-experience-fees' ) ); ?>">Settings &gt; Group Experience Fees</a>. <?php echo cbv_lp_planning_fee_is_live() ? '<strong>The fee is live.</strong>' : 'The fee is <strong>not live yet</strong>: it only shows in admin previews.'; ?></p>
	<p><label><input type="radio" name="cbv_lp_planning_fee[mode]" value="standard" <?php checked( $trip['mode'], 'standard' ); ?>> Use the standard fee (<?php echo esc_html( ( $types[ $type ]['label'] ?? 'This type' ) . ': ' . $std_text ); ?>)</label></p>
	<p>
		<label><input type="radio" name="cbv_lp_planning_fee[mode]" value="custom" <?php checked( $trip['mode'], 'custom' ); ?>> Use a different fee for this trip:</label>
		$ <input type="text" inputmode="decimal" name="cbv_lp_planning_fee[amount]" value="<?php echo $trip['amount'] > 0 ? esc_attr( number_format( $trip['amount'], 2, '.', '' ) ) : ''; ?>" style="width:7em;" aria-label="Fee amount for this trip">
		<select name="cbv_lp_planning_fee[basis]" aria-label="Fee basis for this trip">
			<?php foreach ( cbv_lp_fee_bases() as $b => $label ) : ?>
				<option value="<?php echo esc_attr( $b ); ?>" <?php selected( $trip['basis'], $b ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p><label><input type="radio" name="cbv_lp_planning_fee[mode]" value="none" <?php checked( $trip['mode'], 'none' ); ?>> No Group Experience Fee on this trip</label></p>
	<?php
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_lp_fee_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_lp_fee_nonce'], 'cbv_lp_fee_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$raw = isset( $_POST['cbv_lp_planning_fee'] ) && is_array( $_POST['cbv_lp_planning_fee'] ) ? wp_unslash( $_POST['cbv_lp_planning_fee'] ) : array();
	update_post_meta( $post_id, 'cbv_lp_planning_fee', cbv_lp_sanitize_trip_fee( $raw ) );
} );
