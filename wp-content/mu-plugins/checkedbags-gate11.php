<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Gate 11: Travel Rules
 * Description: "I agree" acknowledgment (stored as user meta) plus a
 *              per-member listing of trip-specific rules addendums, pulling
 *              from the existing cb_rules_addendum / cb_min_group_size
 *              meta already defined on cb_trip since checkedbags-trips.php.
 *              Base policy text is static content already living in the
 *              WordPress page itself, above the [cb_gate_rules] shortcode.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-gate11.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'cb/v1', '/agree-to-rules', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return is_user_logged_in(); },
		'callback'            => function () {
			update_user_meta( get_current_user_id(), 'cb_agreed_to_rules', current_time( 'mysql' ) );
			return array( 'agreed' => true, 'date' => current_time( 'mysql' ) );
		},
	) );
} );

add_shortcode( 'cb_gate_rules', function () {

	if ( ! is_user_logged_in() ) {
		return '<p class="cb-empty">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to view travel rules.</p>';
	}

	$user_id     = get_current_user_id();
	$agreed_date = get_user_meta( $user_id, 'cb_agreed_to_rules', true );

	ob_start();
	?>
	<div class="rules-agreement">
		<?php if ( $agreed_date ) : ?>
			<span class="rules-agreed-badge">
				<i class="ti ti-check" aria-hidden="true"></i>
				Agreed on <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $agreed_date ) ) ); ?>
			</span>
		<?php else : ?>
			<label class="rules-agree-checkbox">
				<input type="checkbox" id="cb-agree-checkbox">
				I have read and agree to the travel policy above.
			</label>
			<button class="btn btn-ticket" id="cb-agree-submit" disabled>Confirm agreement</button>
		<?php endif; ?>
	</div>

	<?php
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

	if ( ! empty( $my_trips ) ) : ?>
		<h3 class="rules-section-title">Rules for Your Trips</h3>
		<?php foreach ( $my_trips as $trip ) :
			$addendum = get_post_meta( $trip->ID, 'cb_rules_addendum', true );
			$min_size = (int) get_post_meta( $trip->ID, 'cb_min_group_size', true ) ?: 4;
			$roster   = cb_trip_get_roster( $trip->ID );
			?>
			<div class="rules-trip-card">
				<h4 class="rules-trip-title"><?php echo esc_html( get_the_title( $trip ) ); ?></h4>
				<span class="rules-group-size">Minimum group size: <?php echo esc_html( $min_size ); ?> (currently <?php echo esc_html( count( $roster ) ); ?>)</span>
				<?php if ( $addendum ) : ?>
					<p class="rules-addendum-text"><?php echo nl2br( esc_html( $addendum ) ); ?></p>
				<?php else : ?>
					<p class="rules-addendum-text rules-addendum-none">No additional rules for this trip.</p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
	<?php

	return ob_get_clean();
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_script( 'cb-gate11', content_url( 'uploads/checkedbags/js/gate11.js' ), array(), '1.0.0', true );
	wp_localize_script( 'cb-gate11', 'cbGate11', array(
		'restUrl' => esc_url_raw( rest_url( 'cb/v1/' ) ),
		'nonce'   => wp_create_nonce( 'wp_rest' ),
	) );
} );
