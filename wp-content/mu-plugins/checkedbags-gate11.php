<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Gate 11: Travel Rules
 * Description: Per-member listing of trip-specific rules addendums, pulling
 *              from the existing cb_rules_addendum / cb_min_group_size
 *              meta already defined on cb_trip since checkedbags-trips.php.
 *              Base policy text is static content already living in the
 *              WordPress page itself, above the [cb_gate_rules] shortcode.
 *
 *              The site-wide "I agree" checkbox that used to live here
 *              (cb_agreed_to_rules — a single global, unversioned
 *              timestamp) was replaced by the versioned Membership Terms
 *              system in checkedbags-trip-invites.php (Phase 3), which
 *              gates registration and re-prompts on version changes
 *              site-wide. This shortcode now just shows read-only
 *              acceptance status pulled from that system.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-gate11.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'cb_gate_rules', function () {

	if ( ! is_user_logged_in() ) {
		return '<p class="cb-empty">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">sign in</a> to view travel rules.</p>';
	}

	$user_id       = get_current_user_id();
	$terms_version = (int) get_user_meta( $user_id, '_accepted_terms_version', true );
	$terms_date    = get_user_meta( $user_id, '_accepted_terms_date', true );

	ob_start();
	?>
	<div class="rules-agreement">
		<?php if ( $terms_date && ! cbv_user_needs_terms_reaccept( $user_id ) ) : ?>
			<span class="rules-agreed-badge">
				<i class="ti ti-check" aria-hidden="true"></i>
				Membership Terms (v<?php echo esc_html( $terms_version ); ?>) agreed on <?php echo esc_html( date_i18n( 'M j, Y', strtotime( $terms_date ) ) ); ?>
			</span>
		<?php else : ?>
			<p class="rules-addendum-none">
				Your Membership Terms acceptance is out of date or missing.
				<a href="<?php echo esc_url( home_url( '/reaccept-terms/' ) ); ?>">Review and accept the current terms</a>.
			</p>
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
