<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Price notes
 * Description: Two short, optional per-trip notes about prices and payment,
 *              entered in the trip's "Price notes" box:
 *              - Pricing note: shown under the "Choose Your Option" cards on
 *                the CURRENT public trip page (checkedbags-trip-landing.php).
 *                The new design does not show it (its legacy pricing section
 *                is removed there); the new design's Price board "Header
 *                note" does that job.
 *              - Gate 07 card note: one short line under the price on the
 *                trip's Gate 07 card (checkedbags-gate07.php), e.g. "Pay in
 *                full or monthly with Uplift".
 *
 *              Wording rules (owner, 2026-10-08 and 2026-10-11): travel is
 *              booked by the travel advisor and paid directly to the cruise
 *              line or supplier; never name the host agency; never say CBGV
 *              books the cabin. For Virgin Voyages trips, Uplift (Virgin's
 *              financing partner) may be named, always with "subject to
 *              approval"; never monthly amounts, rates or promised approval;
 *              no deposit amounts unless they come from the trip's own data.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-price-notes.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CBV_PRICING_NOTE_MAX', 600 );
define( 'CBV_CARD_NOTE_MAX', 80 );

/* ==========================================================================
   1. Meta.
   ========================================================================== */
add_action( 'init', function () {
	register_post_meta( 'cb_trip', 'cb_pricing_note', array(
		'type'              => 'string',
		'single'            => true,
		'default'           => '',
		'show_in_rest'      => false,
		'sanitize_callback' => 'cbv_clean_pricing_note',
		'auth_callback'     => function () { return current_user_can( 'edit_posts' ); },
	) );
	register_post_meta( 'cb_trip', 'cb_card_note', array(
		'type'              => 'string',
		'single'            => true,
		'default'           => '',
		'show_in_rest'      => false,
		'sanitize_callback' => 'cbv_clean_card_note',
		'auth_callback'     => function () { return current_user_can( 'edit_posts' ); },
	) );
}, 20 );

/* ==========================================================================
   2. Cleaning and reading.
   ========================================================================== */

/** Pricing note: plain text (no HTML), line breaks kept, at most 600 characters. */
function cbv_clean_pricing_note( $value ) {
	$value = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
	$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, CBV_PRICING_NOTE_MAX ) : substr( $value, 0, CBV_PRICING_NOTE_MAX );
	return trim( $value );
}

/** Gate 07 card note: one plain line, at most 80 characters. */
function cbv_clean_card_note( $value ) {
	$value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	$value = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, CBV_CARD_NOTE_MAX ) : substr( $value, 0, CBV_CARD_NOTE_MAX );
	return trim( $value );
}

/**
 * The pricing note as HTML for the current public page, or '' when blank.
 * **bold** and [words](https://link) work (the new design's light markup);
 * everything else is escaped.
 */
function cbv_render_pricing_note( $trip_id ) {
	$note = cbv_clean_pricing_note( get_post_meta( (int) $trip_id, 'cb_pricing_note', true ) );
	if ( '' === $note ) {
		return '';
	}
	$html = function_exists( 'cbv_lp_inline' ) ? cbv_lp_inline( $note ) : esc_html( $note );
	return '<p class="cbv-landing-pricing-note">' . nl2br( $html, false ) . '</p>';
}

/** The Gate 07 card note as HTML, or '' when blank. Plain text only. */
function cbv_render_card_note( $trip_id ) {
	$note = cbv_clean_card_note( get_post_meta( (int) $trip_id, 'cb_card_note', true ) );
	return '' === $note ? '' : '<p class="trip-card-note">' . esc_html( $note ) . '</p>';
}

/* ==========================================================================
   3. Trip edit screen: the "Price notes" box.
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cbv_price_notes', 'Price notes (current page and Gate 07)', 'cbv_render_price_notes_box', 'cb_trip', 'normal', 'default' );
} );

function cbv_render_price_notes_box( $post ) {
	wp_nonce_field( 'cbv_price_notes_save', 'cbv_price_notes_nonce' );
	$pricing = cbv_clean_pricing_note( get_post_meta( $post->ID, 'cb_pricing_note', true ) );
	$card    = cbv_clean_card_note( get_post_meta( $post->ID, 'cb_card_note', true ) );
	?>
	<p>
		<label for="cb_pricing_note"><strong>Pricing note</strong> <span class="description">(optional)</span></label><br>
		<textarea name="cb_pricing_note" id="cb_pricing_note" rows="3" maxlength="<?php echo (int) CBV_PRICING_NOTE_MAX; ?>" class="large-text"><?php echo esc_textarea( $pricing ); ?></textarea>
		<span class="description">Shown under the "Choose Your Option" prices on the current public trip page. Not shown on the new design (use the Price board's Header note there). **bold** and [words](https://link) work. Travel is paid directly to the cruise line or supplier; never name the host agency or say CBGV books the cabin. For Virgin trips, Uplift may be named, always with "subject to approval", never with monthly amounts or rates.</span>
	</p>
	<p>
		<label for="cb_card_note"><strong>Gate 07 card note</strong> <span class="description">(optional, up to <?php echo (int) CBV_CARD_NOTE_MAX; ?> characters)</span></label><br>
		<input type="text" name="cb_card_note" id="cb_card_note" maxlength="<?php echo (int) CBV_CARD_NOTE_MAX; ?>" class="regular-text" value="<?php echo esc_attr( $card ); ?>" placeholder="Pay in full or monthly with Uplift">
		<span class="description">One short line under the price on this trip's Gate 07 card (members only).</span>
	</p>
	<?php
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_price_notes_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_price_notes_nonce'], 'cbv_price_notes_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['cb_pricing_note'] ) ) {
		update_post_meta( $post_id, 'cb_pricing_note', cbv_clean_pricing_note( wp_unslash( $_POST['cb_pricing_note'] ) ) );
	}
	if ( isset( $_POST['cb_card_note'] ) ) {
		update_post_meta( $post_id, 'cb_card_note', cbv_clean_card_note( wp_unslash( $_POST['cb_card_note'] ) ) );
	}
} );
