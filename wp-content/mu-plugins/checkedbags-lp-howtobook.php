<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — How to book + "Who does what" (Step 12a)
 * Description: The How-to-book section of the new trip landing design and
 *              the "Who does what" disclosure on every trip page and the
 *              Terms of Service page ([cbv_who_does_what]). Both texts are
 *              settings (Settings > How to Book & Disclosure), so their
 *              wording can change without code (the disclosure may be
 *              updated after the InteleTravel and attorney replies).
 *              Rules: never "CBGV books your cabin", never InteleTravel in
 *              public text; the travel advisor books the cabin.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-howtobook.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CBV_HOWTO_STEPS_OPTION = 'cbv_how_to_book_steps';
const CBV_WHO_OPTION         = 'cbv_who_does_what';

/* ==========================================================================
   1. The texts: owner's wording (2026-10-14) as the defaults, saved
      settings when an admin has changed them.
   ========================================================================== */
/** Owner's 4 steps. 'fee' marks the step about the CBGV Group Experience Fee (hidden on a trip with no fee). */
function cbv_howto_default_steps() {
	return array(
		array( 'title' => 'Register for the trip', 'body' => 'Tell us who\'s coming and which cabin you\'d like.', 'fee' => false ),
		array( 'title' => 'Book your cabin with our travel advisor', 'body' => 'Andrea M. Peaten, our independent travel advisor, books your cabin, and the cruise line sends you a secure link to pay your deposit directly to them.', 'fee' => false ),
		array( 'title' => 'Pay the CBGV Group Experience Fee', 'body' => 'This is paid separately to Checked Bags & Good Vibes, for the group program.', 'fee' => true ),
		array( 'title' => 'You\'re cleared for boarding', 'body' => 'Log in anytime to see your trip, payments and updates.', 'fee' => false ),
	);
}

function cbv_who_default() {
	return array(
		'text'    => 'Checked Bags & Good Vibes is a d/b/a of JourneyWell Global LLC, run by co-founder and COO LaDon Headen. CBGV organizes the group program and charges the CBGV Group Experience Fee. Travel is booked separately by Andrea M. Peaten, an independent travel advisor, and paid directly to the cruise line or travel supplier. Andrea M. Peaten is also an owner of JourneyWell Global LLC. She receives standard commissions from travel suppliers for the travel she books, separate from the CBGV Group Experience Fee. CBGV never collects or holds payments for travel. When a CBGV host travels with the group, their travel is paid for from CBGV Group Experience Fees, or with a complimentary cabin, room or seat provided to the group by the travel supplier.',
		'updated' => '2026-10-14',
	);
}

/** Saved rows -> clean rows (title + body + fee flag); empty rows dropped. */
function cbv_howto_clean_steps( $raw ) {
	$out = array();
	foreach ( (array) $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
		$body  = sanitize_textarea_field( (string) ( $row['body'] ?? '' ) );
		if ( '' === $title && '' === $body ) {
			continue;
		}
		$out[] = array( 'title' => $title, 'body' => $body, 'fee' => ! empty( $row['fee'] ) );
	}
	return $out;
}

/** The site's default steps: the saved setting, or the owner's 4 when nothing is saved. */
function cbv_howto_site_steps() {
	$saved = cbv_howto_clean_steps( get_option( CBV_HOWTO_STEPS_OPTION, array() ) );
	return $saved ? $saved : cbv_howto_default_steps();
}

/** The disclosure: array( 'text', 'updated' Y-m-d ). */
function cbv_who_does_what() {
	$saved = get_option( CBV_WHO_OPTION, array() );
	if ( is_array( $saved ) && '' !== trim( (string) ( $saved['text'] ?? '' ) ) ) {
		return array( 'text' => (string) $saved['text'], 'updated' => (string) ( $saved['updated'] ?? '' ) );
	}
	return cbv_who_default();
}

/* ==========================================================================
   2. A trip's steps and heading.
   ========================================================================== */
/**
 * The trip's steps: its provider's (or the trip's own) How-to-book steps
 * REPLACE the site default; otherwise the site default. On a trip with no
 * CBGV Group Experience Fee to bill, the fee step is left out.
 */
function cbv_howto_steps( $trip_id ) {
	$steps = function_exists( 'cbv_lp_group' ) ? (array) cbv_lp_group( $trip_id, 'steps' ) : array();
	if ( $steps ) {
		$steps = array_map( function ( $s ) { return array( 'title' => $s['title'], 'body' => $s['body'], 'fee' => false ); }, $steps );
	} else {
		$steps = cbv_howto_site_steps();
	}
	$has_fee = function_exists( 'cb_trip_billable_fee' ) && null !== cb_trip_billable_fee( $trip_id );
	if ( ! $has_fee ) {
		$steps = array_values( array_filter( $steps, function ( $s ) { return empty( $s['fee'] ); } ) );
	}
	return $steps;
}

function cbv_howto_number_word( $n ) {
	$words = array( 1 => 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten' );
	return $words[ (int) $n ] ?? (string) (int) $n;
}

/** "Four steps to the gangway" (cruise) / "Four steps to your trip" (other types), counting the steps shown. */
function cbv_howto_heading( $trip_id, $count ) {
	$cruise = function_exists( 'cbv_lp_event_type' ) && 'cruise' === cbv_lp_event_type( $trip_id );
	return cbv_howto_number_word( $count ) . ( 1 === (int) $count ? ' step ' : ' steps ' ) . ( $cruise ? 'to the gangway' : 'to your trip' );
}

/* ==========================================================================
   3. Rendering.
   ========================================================================== */
/** The disclosure block. $variant: 'lp' (new design), 'page' (other trip pages), 'terms' (Terms of Service). */
function cbv_render_who_does_what( $variant = 'page' ) {
	$who     = cbv_who_does_what();
	$updated = '' !== $who['updated'] && strtotime( $who['updated'] ) ? date_i18n( 'F j, Y', strtotime( $who['updated'] ) ) : '';
	$tag     = 'terms' === $variant ? 'h3' : 'h2';
	ob_start();
	?>
	<aside class="cbv-who cbv-who--<?php echo esc_attr( $variant ); ?>" id="who-does-what" aria-labelledby="cbv-who-title-<?php echo esc_attr( $variant ); ?>">
		<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput -- fixed h2/h3 ?> class="cbv-who-title" id="cbv-who-title-<?php echo esc_attr( $variant ); ?>">Who does what</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
		<?php echo wpautop( esc_html( $who['text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped, then paragraphs ?>
		<?php if ( '' !== $updated ) : ?>
			<p class="cbv-who-updated">Last updated <?php echo esc_html( $updated ); ?></p>
		<?php endif; ?>
	</aside>
	<?php
	return ob_get_clean();
}

add_shortcode( 'cbv_who_does_what', function () {
	return cbv_render_who_does_what( 'terms' );
} );

/** New design: the How-to-book section (when on and there are steps). */
function cbv_lp_render_how( $trip_id ) {
	if ( ! function_exists( 'cbv_lp_section_enabled' ) || ! cbv_lp_section_enabled( $trip_id, 'how' ) ) {
		return '';
	}
	$steps = cbv_howto_steps( $trip_id );
	if ( ! $steps ) {
		return '';
	}
	$labels = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
	$gate   = function_exists( 'cbv_lp_gate_label' ) ? cbv_lp_gate_label( $labels['sec_how'] ?? 'Check-in' ) : '';
	$plural = $labels['accommodation_plural'] ?? 'cabins';
	$reg    = function_exists( 'cbv_lp_board_registration_url' ) ? cbv_lp_board_registration_url( $trip_id ) : home_url( '/join/' );
	ob_start();
	?>
	<section class="cbv-lp-how" id="book" aria-labelledby="cbv-lp-how-title">
		<div class="cbv-lp-inner cbv-lp-how-inner">
			<div class="cbv-lp-how-head">
				<p class="cbv-lp-gate"><?php echo esc_html( $gate ); ?></p>
				<h2 class="cbv-lp-h2" id="cbv-lp-how-title"><?php echo esc_html( cbv_howto_heading( $trip_id, count( $steps ) ) ); ?></h2>
			</div>
			<ol class="cbv-lp-how-steps">
				<?php foreach ( $steps as $i => $s ) : ?>
					<li class="cbv-lp-how-step">
						<span class="cbv-lp-how-num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
						<span class="cbv-lp-how-title"><?php echo esc_html( $s['title'] ); ?></span>
						<span class="cbv-lp-how-body"><?php echo esc_html( $s['body'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
			<div class="cbv-lp-how-actions">
				<a class="cbv-lp-how-btn" href="<?php echo esc_url( $reg ); ?>">Register now</a>
				<a class="cbv-lp-how-link" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Booking 5+ <?php echo esc_html( $plural ); ?>? Talk to us</a>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/** New design: the disclosure, always (even when How to book is off). */
function cbv_lp_render_who( $trip_id ) {
	return '<section class="cbv-lp-who" aria-label="Who does what"><div class="cbv-lp-inner">' . cbv_render_who_does_what( 'lp' ) . '</div></section>';
}

/**
 * Every other trip page (the old public page, the members' page, the
 * sign-in / no-access / agreement screens): the disclosure at the end.
 * After Gate 10's "Discuss this trip" link (priority 25). The new design
 * draws its own (above), so it is skipped there.
 */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'cb_trip' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( function_exists( 'cbv_lp_trip_view' ) && in_array( cbv_lp_trip_view( get_the_ID() ), array( 'new', 'new_preview' ), true ) ) {
		return $content;
	}
	return $content . cbv_render_who_does_what( 'page' );
}, 30 );

/* ==========================================================================
   4. Settings > How to Book & Disclosure.
   ========================================================================== */
add_action( 'admin_menu', function () {
	add_options_page( 'How to Book & Disclosure', 'How to Book & Disclosure', 'manage_options', 'cbv-how-to-book', 'cbv_render_howto_settings_page' );
} );

function cbv_render_howto_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['cbv_howto_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cbv_howto_nonce'] ) ), 'cbv_howto_save' ) ) {
		$steps = cbv_howto_clean_steps( wp_unslash( $_POST['cbv_howto_steps'] ?? array() ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned row by row
		update_option( CBV_HOWTO_STEPS_OPTION, $steps, false );
		$text = trim( sanitize_textarea_field( wp_unslash( $_POST['cbv_who_text'] ?? '' ) ) );
		$old  = cbv_who_does_what();
		if ( '' !== $text && $text !== trim( $old['text'] ) ) {
			update_option( CBV_WHO_OPTION, array( 'text' => $text, 'updated' => current_time( 'Y-m-d' ) ), false );
		}
		echo '<div class="notice notice-success"><p>Saved.' . ( $steps ? '' : ' No steps were filled in, so the original 4 steps are used.' ) . '</p></div>';
	}
	$steps = cbv_howto_site_steps();
	$who   = cbv_who_does_what();
	$rows  = array_merge( $steps, array_fill( 0, 2, array( 'title' => '', 'body' => '', 'fee' => false ) ) );
	?>
	<div class="wrap">
		<h1>How to Book &amp; Disclosure</h1>
		<form method="post">
			<?php wp_nonce_field( 'cbv_howto_save', 'cbv_howto_nonce' ); ?>
			<h2>How-to-book steps</h2>
			<p class="description">Shown in order on every trip page in the new design. A trip whose provider has its own How-to-book steps uses those instead. Tick "Fee step" on the step about the CBGV Group Experience Fee: it is left out on trips with no fee, and the heading's count adjusts. Never write "CBGV books your cabin": the travel advisor books the cabin.</p>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th style="width:40px">#</th><th>Title</th><th>Text</th><th style="width:80px">Fee step</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $i => $r ) : ?>
					<tr>
						<td><?php echo (int) $i + 1; ?></td>
						<td><input type="text" class="widefat" name="cbv_howto_steps[<?php echo (int) $i; ?>][title]" value="<?php echo esc_attr( $r['title'] ); ?>"></td>
						<td><textarea class="widefat" rows="2" name="cbv_howto_steps[<?php echo (int) $i; ?>][body]"><?php echo esc_textarea( $r['body'] ); ?></textarea></td>
						<td><input type="checkbox" name="cbv_howto_steps[<?php echo (int) $i; ?>][fee]" value="1" <?php checked( ! empty( $r['fee'] ) ); ?>></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p class="description">To remove a step, empty its title and text. Two empty rows are always offered for new steps.</p>
			<h2>"Who does what" disclosure</h2>
			<p class="description">Shown on every trip page and on the Terms of Service page. Last updated: <strong><?php echo esc_html( $who['updated'] ? date_i18n( 'F j, Y', strtotime( $who['updated'] ) ) : '—' ); ?></strong> (changes automatically when the text changes). Plain text; a blank line starts a new paragraph.</p>
			<textarea name="cbv_who_text" rows="10" style="width:100%;max-width:900px"><?php echo esc_textarea( $who['text'] ); ?></textarea>
			<p><button type="submit" class="button button-primary">Save</button></p>
		</form>
	</div>
	<?php
}
