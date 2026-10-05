<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Landing Redesign Status Board & Intro
 * Description: Step 5 of the public trip landing redesign: the status board
 *              (the "25 sailors needed to depart" strip under the hero, with
 *              "NN OF 25" tiles and a status chip) and the intro section
 *              (GATE 14 · THE TRIP: heading, text, fact chips, photo), plus
 *              the trip edit-screen box that feeds them and the GATE label
 *              counter later sections share. Only the new design calls any
 *              of this (preview with ?preview=new, or live per trip once
 *              switched on in checkedbags-lp-core.php); the current public
 *              design is untouched.
 *
 *              Status board, by stage, from the number typed in "Travelers
 *              booked (paid deposits)" (blank = no tiles, first stage):
 *                below the minimum: "NN OF {min}", "{min} travelers needed
 *                  to depart", chip Boarding;
 *                minimum reached: "NN OF {capacity}", "Departure confirmed
 *                  · N spots left", chip On time (no capacity set: "NN OF
 *                  {min}", "Departure confirmed");
 *                full: "NN OF {capacity}", chip "Fully booked · Ask about
 *                  the waitlist".
 *              Non-cruise event types use their own words (guests, to
 *              confirm, Open / Confirmed, Group confirmed).
 *
 *              Intro: chips start with the number of nights (from the
 *              trip's dates) and the vessel / venue name, then the typed
 *              ones. No heading and no text means no intro section.
 *
 *              Media rule: Virgin images are used UNALTERED (see
 *              docs/media-sources.md); the focus point keeps the subject in
 *              view and a frame never hides Virgin's logo or branding.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-intro.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Meta. All presentation-only; nothing here is read by the current design.
      The booked number is a string so "blank" (no tiles) and "0" differ.
   ========================================================================== */
add_action( 'init', function () {
	$auth = function () {
		return current_user_can( 'edit_posts' );
	};
	foreach ( array( 'cbv_lp_travelers_booked', 'cbv_lp_intro_heading', 'cbv_lp_intro_photo_focus', 'cbv_lp_intro_caption' ) as $key ) {
		register_post_meta( 'cb_trip', $key, array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => false,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => $auth,
		) );
	}
	register_post_meta( 'cb_trip', 'cbv_lp_intro_body', array(
		'type'              => 'string',
		'single'            => true,
		'default'           => '',
		'show_in_rest'      => false,
		'sanitize_callback' => 'sanitize_textarea_field',
		'auth_callback'     => $auth,
	) );
	register_post_meta( 'cb_trip', 'cbv_lp_intro_photo', array(
		'type'          => 'integer',
		'single'        => true,
		'default'       => 0,
		'show_in_rest'  => false,
		'auth_callback' => $auth,
	) );
	register_post_meta( 'cb_trip', 'cbv_lp_intro_chips', array(
		'type'          => 'array',
		'single'        => true,
		'default'       => array(),
		'show_in_rest'  => false,
		'auth_callback' => $auth,
	) );
} );

/* ==========================================================================
   2. GATE labels. Sections number themselves from GATE 14 in the order they
      actually render, so a hidden section never leaves a gap. The counter is
      reset at the start of each page render (checkedbags-lp-core.php).
   ========================================================================== */
function cbv_lp_next_gate( $reset = false ) {
	static $n = 13;
	if ( $reset ) {
		$n = 13;
		return 0;
	}
	return ++$n;
}

/** "GATE 14 · THE TRIP" style label (upper-cased by CSS, so screen readers read words). */
function cbv_lp_gate_label( $section_name ) {
	$name = trim( (string) $section_name );
	return 'Gate ' . cbv_lp_next_gate() . ( '' !== $name ? ' · ' . $name : '' );
}

/* ==========================================================================
   3. Small pure helpers (no globals, no writes), tested directly.
   ========================================================================== */

/** The typed booked number: '' (blank, no tiles) or digits only, at most 4. */
function cbv_lp_clean_booked( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	$value = trim( (string) $value );
	if ( '' === $value || ! preg_match( '/^\d{1,4}$/', $value ) ) {
		return '';
	}
	return (string) (int) $value;
}

/** Whole nights between the start and end dates (0 when either is missing or the order is wrong). */
function cbv_lp_nights( $start, $end ) {
	$s = $start ? strtotime( (string) $start . ' 00:00:00 UTC' ) : false;
	$e = $end ? strtotime( (string) $end . ' 00:00:00 UTC' ) : false;
	if ( ! $s || ! $e || $e <= $s ) {
		return 0;
	}
	return (int) round( ( $e - $s ) / DAY_IN_SECONDS );
}

/**
 * The chip state for the status board.
 *   'full'   -- capacity set and the booked number has reached it
 *   'after'  -- booked number at or above the minimum
 *   'before' -- below the minimum, or no booked number typed
 */
function cbv_lp_status_state( $booked, $min, $capacity ) {
	if ( '' === $booked || null === $booked ) {
		return 'before';
	}
	$booked = (int) $booked;
	if ( (int) $capacity > 0 && $booked >= (int) $capacity ) {
		return 'full';
	}
	return $booked >= (int) $min ? 'after' : 'before';
}

/** Posted typed chips (one per line) -> at most 4 short plain-text chips. */
function cbv_lp_clean_chips( $value ) {
	$lines = is_array( $value ) ? $value : ( is_scalar( $value ) ? preg_split( '/\R/', (string) $value ) : array() );
	$out   = array();
	foreach ( $lines as $line ) {
		if ( ! is_scalar( $line ) ) {
			continue;
		}
		$line = sanitize_text_field( (string) $line );
		$line = function_exists( 'mb_substr' ) ? mb_substr( $line, 0, 40 ) : substr( $line, 0, 40 );
		if ( '' !== trim( $line ) ) {
			$out[] = trim( $line );
		}
		if ( count( $out ) >= 4 ) {
			break;
		}
	}
	return $out;
}

/** Posted box values -> what gets stored. */
function cbv_lp_sanitize_intro( $raw ) {
	$raw   = is_array( $raw ) ? $raw : array();
	$text  = function ( $key, $max ) use ( $raw ) {
		$v = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? sanitize_text_field( (string) $raw[ $key ] ) : '';
		return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
	};
	$focus = isset( $raw['focus'] ) && is_scalar( $raw['focus'] ) ? sanitize_key( (string) $raw['focus'] ) : '';
	if ( ! function_exists( 'cbv_lp_focus_options' ) || ! isset( cbv_lp_focus_options()[ $focus ] ) ) {
		$focus = '';
	}
	$body = isset( $raw['body'] ) && is_scalar( $raw['body'] ) ? sanitize_textarea_field( (string) $raw['body'] ) : '';
	$body = function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 3000 ) : substr( $body, 0, 3000 );

	return array(
		'booked'  => cbv_lp_clean_booked( $raw['booked'] ?? '' ),
		'heading' => $text( 'heading', 120 ),
		'body'    => $body,
		'chips'   => cbv_lp_clean_chips( $raw['chips'] ?? '' ),
		'photo'   => function_exists( 'cbv_lp_clean_attachment_id' ) ? cbv_lp_clean_attachment_id( $raw['photo'] ?? 0, 'image' ) : 0,
		'focus'   => $focus,
		'caption' => $text( 'caption', 100 ),
	);
}

/* ==========================================================================
   4. Status board.
   ========================================================================== */
/** What the status board shows for a trip, or null when it is hidden. */
function cbv_lp_status_data( $trip_id ) {
	$trip_id = (int) $trip_id;
	if ( function_exists( 'cbv_lp_section_enabled' ) && ! cbv_lp_section_enabled( $trip_id, 'status' ) ) {
		return null;
	}
	if ( in_array( (string) get_post_meta( $trip_id, 'cb_status', true ), array( 'completed', 'declined' ), true ) ) {
		return null;
	}
	$min = (int) get_post_meta( $trip_id, 'cb_min_group_size', true );
	if ( $min <= 0 ) {
		return null;
	}

	$labels   = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
	$booked   = cbv_lp_clean_booked( get_post_meta( $trip_id, 'cbv_lp_travelers_booked', true ) );
	$capacity = (int) get_post_meta( $trip_id, 'cb_capacity', true );
	$state    = cbv_lp_status_state( $booked, $min, $capacity );
	$people   = strtolower( 1 === $min ? ( $labels['party_one'] ?? 'Guest' ) : ( $labels['party'] ?? 'Guests' ) );

	$chips = array(
		'before' => $labels['status_before'] ?? 'Open',
		'after'  => $labels['status_after'] ?? 'Confirmed',
		'full'   => 'Fully booked · Ask about the waitlist',
	);

	// The cruise wording ("to depart") is about a departure; other event types get neutral words.
	$suffix    = $labels['status_suffix'] ?? 'to confirm';
	$departs   = 'to depart' === $suffix;
	$kicker    = $labels['status_kicker'] ?? ( $departs ? 'Departure status' : 'Group status' );
	$confirmed = $labels['status_confirmed'] ?? ( $departs ? 'Departure confirmed' : 'Group confirmed' );

	// By stage:
	//   below the minimum (or no number typed): "NN OF {min}", "{min} travelers needed to depart"
	//   minimum reached: "NN OF {capacity}", "Departure confirmed · N spots left"
	//                    (no capacity set: "NN OF {min}", "Departure confirmed")
	//   full:            "NN OF {capacity}", "Departure confirmed"
	if ( 'before' === $state ) {
		$line  = $min . ' ' . $people . ' needed ' . $suffix;
		$total = $min;
	} elseif ( 'after' === $state && $capacity > 0 ) {
		$left  = $capacity - (int) $booked;
		$line  = $confirmed . ' · ' . $left . ( 1 === $left ? ' spot left' : ' spots left' );
		$total = $capacity;
	} else {
		$line  = $confirmed;
		$total = 'full' === $state ? $capacity : $min;
	}

	$tiles = null;
	if ( '' !== $booked ) {
		$width = max( strlen( (string) $total ), strlen( $booked ) );
		$tiles = array(
			'booked' => str_split( str_pad( $booked, $width, '0', STR_PAD_LEFT ) ),
			'total'  => str_split( str_pad( (string) $total, $width, '0', STR_PAD_LEFT ) ),
		);
	}

	return array(
		'kicker' => $kicker,
		'line'   => $line,
		'tiles'  => $tiles,
		'spoken' => '' !== $booked ? sprintf( '%1$s of %2$d %3$s booked', $booked, $total, strtolower( $labels['party'] ?? 'guests' ) ) : '',
		'state'  => $state,
		'chip'   => $chips[ $state ],
	);
}

function cbv_lp_render_status( $trip_id ) {
	$d = cbv_lp_status_data( $trip_id );
	if ( ! $d ) {
		return '';
	}
	ob_start();
	?>
	<section class="cbv-lp-status" aria-label="Group status">
		<div class="cbv-lp-inner cbv-lp-status-inner">
			<div class="cbv-lp-status-copy">
				<span class="cbv-lp-status-kicker"><?php echo esc_html( $d['kicker'] ); ?></span>
				<span class="cbv-lp-status-line"><?php echo esc_html( $d['line'] ); ?></span>
			</div>
			<?php if ( $d['tiles'] ) : ?>
				<p class="cbv-lp-sr"><?php echo esc_html( $d['spoken'] ); ?></p>
				<div class="cbv-lp-status-tiles" aria-hidden="true">
					<?php foreach ( $d['tiles']['booked'] as $digit ) : ?>
						<span class="cbv-lp-tile"><?php echo esc_html( $digit ); ?></span>
					<?php endforeach; ?>
					<span class="cbv-lp-status-of">of</span>
					<?php foreach ( $d['tiles']['total'] as $digit ) : ?>
						<span class="cbv-lp-tile"><?php echo esc_html( $digit ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<span class="cbv-lp-status-chip cbv-lp-status-chip--<?php echo esc_attr( $d['state'] ); ?>"><?php echo esc_html( $d['chip'] ); ?></span>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   5. Intro.
   ========================================================================== */
/** What the intro shows for a trip, or null when it is hidden. */
function cbv_lp_intro_data( $trip_id ) {
	$trip_id = (int) $trip_id;
	if ( function_exists( 'cbv_lp_section_enabled' ) && ! cbv_lp_section_enabled( $trip_id, 'intro' ) ) {
		return null;
	}
	$tok     = function ( $text ) use ( $trip_id ) {
		return trim( function_exists( 'cbv_lp_apply_tokens' ) ? cbv_lp_apply_tokens( (string) $text, $trip_id ) : (string) $text );
	};
	$heading = $tok( get_post_meta( $trip_id, 'cbv_lp_intro_heading', true ) );
	$raw     = (string) get_post_meta( $trip_id, 'cbv_lp_intro_body', true );
	$body    = '' !== trim( $raw ) ? ( function_exists( 'cbv_lp_blocks' ) ? cbv_lp_blocks( $raw, $trip_id ) : '<p>' . esc_html( $raw ) . '</p>' ) : '';
	if ( '' === $heading && '' === trim( wp_strip_all_tags( $body ) ) ) {
		return null;
	}

	$chips  = array();
	$nights = cbv_lp_nights( get_post_meta( $trip_id, 'cb_start_date', true ), get_post_meta( $trip_id, 'cb_end_date', true ) );
	if ( $nights > 0 ) {
		$chips[] = $nights . ( 1 === $nights ? ' night' : ' nights' );
	}
	$vessel = trim( (string) get_post_meta( $trip_id, 'cbv_lp_venue_name', true ) );
	if ( '' !== $vessel ) {
		$chips[] = $vessel;
	}
	foreach ( cbv_lp_clean_chips( get_post_meta( $trip_id, 'cbv_lp_intro_chips', true ) ) as $chip ) {
		$chip = $tok( $chip );
		if ( '' !== $chip ) {
			$chips[] = $chip;
		}
	}

	$photo_id = function_exists( 'cbv_lp_clean_attachment_id' ) ? cbv_lp_clean_attachment_id( get_post_meta( $trip_id, 'cbv_lp_intro_photo', true ), 'image' ) : 0;
	$labels   = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();

	return array(
		'section'  => $labels['sec_intro'] ?? 'The trip',
		'heading'  => $heading,
		'body'     => $body,
		'chips'    => $chips,
		'photo_id' => $photo_id,
		'focus'    => function_exists( 'cbv_lp_focus_position' ) ? cbv_lp_focus_position( (string) get_post_meta( $trip_id, 'cbv_lp_intro_photo_focus', true ) ) : 'center center',
		'caption'  => $photo_id ? $tok( get_post_meta( $trip_id, 'cbv_lp_intro_caption', true ) ) : '',
	);
}

function cbv_lp_render_intro( $trip_id ) {
	$d = cbv_lp_intro_data( $trip_id );
	if ( ! $d ) {
		return '';
	}
	$photo = $d['photo_id'] ? wp_get_attachment_image( $d['photo_id'], 'large', false, array(
		'class'   => 'cbv-lp-intro-img',
		'style'   => 'object-position:' . $d['focus'] . ';',
		'loading' => 'lazy',
	) ) : '';

	ob_start();
	?>
	<section class="cbv-lp-intro" id="intro" aria-labelledby="cbv-lp-intro-title">
		<div class="cbv-lp-inner <?php echo '' !== $photo ? 'cbv-lp-intro-inner' : 'cbv-lp-intro-inner cbv-lp-intro-inner--solo'; ?>">
			<div class="cbv-lp-intro-copy">
				<p class="cbv-lp-gate"><?php echo esc_html( cbv_lp_gate_label( $d['section'] ) ); ?></p>
				<h2 class="cbv-lp-h2" id="cbv-lp-intro-title"><?php echo esc_html( '' !== $d['heading'] ? $d['heading'] : $d['section'] ); ?></h2>
				<?php if ( '' !== $d['body'] ) : ?>
					<div class="cbv-lp-intro-body"><?php echo $d['body']; // phpcs:ignore WordPress.Security.EscapeOutput -- cbv_lp_blocks escapes first ?></div>
				<?php endif; ?>
				<?php if ( $d['chips'] ) : ?>
					<ul class="cbv-lp-chips">
						<?php foreach ( $d['chips'] as $chip ) : ?>
							<li><?php echo esc_html( $chip ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $photo ) : ?>
				<figure class="cbv-lp-polaroid">
					<?php echo $photo; // phpcs:ignore WordPress.Security.EscapeOutput -- core image markup ?>
					<?php if ( '' !== $d['caption'] ) : ?>
						<figcaption><?php echo esc_html( $d['caption'] ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endif; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   6. Trip edit screen: the "Status board & intro" box. The media chooser and
      its script come from checkedbags-lp-hero.php (cbv_lp_media_field).
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cbv_lp_intro', 'Status board & intro (new design)', 'cbv_lp_render_intro_box', 'cb_trip', 'normal', 'default' );
} );

function cbv_lp_render_intro_box( $post ) {
	wp_nonce_field( 'cbv_lp_intro_save', 'cbv_lp_intro_nonce' );
	$booked  = (string) get_post_meta( $post->ID, 'cbv_lp_travelers_booked', true );
	$heading = (string) get_post_meta( $post->ID, 'cbv_lp_intro_heading', true );
	$body    = (string) get_post_meta( $post->ID, 'cbv_lp_intro_body', true );
	$chips   = cbv_lp_clean_chips( get_post_meta( $post->ID, 'cbv_lp_intro_chips', true ) );
	$focus   = (string) get_post_meta( $post->ID, 'cbv_lp_intro_photo_focus', true );
	$caption = (string) get_post_meta( $post->ID, 'cbv_lp_intro_caption', true );
	$min     = (int) get_post_meta( $post->ID, 'cb_min_group_size', true );
	?>
	<p class="description">Used only by the new landing design (preview with <code>?preview=new</code>).</p>

	<h4 style="margin:16px 0 6px;">Status board</h4>
	<p>
		<label for="cbv_lp_travelers_booked"><strong>Travelers booked (paid deposits)</strong></label><br>
		<input type="number" name="cbv_lp_travelers_booked" id="cbv_lp_travelers_booked" value="<?php echo esc_attr( $booked ); ?>" min="0" max="9999" step="1" class="small-text">
	</p>
	<p class="description">How many travelers have paid their deposit. Below the Minimum group size the board shows "NN OF <?php echo (int) $min; ?>"; once the minimum is reached it switches to "NN OF" the trip's Capacity with the spots left; at Capacity the chip reads "Fully booked · Ask about the waitlist". Leave blank to show only the "needed" line and the status chip.</p>

	<h4 style="margin:16px 0 6px;">Intro</h4>
	<p>
		<label for="cbv_lp_intro_heading"><strong>Heading</strong></label><br>
		<input type="text" name="cbv_lp_intro_heading" id="cbv_lp_intro_heading" value="<?php echo esc_attr( $heading ); ?>" maxlength="120" class="large-text">
	</p>
	<p>
		<label for="cbv_lp_intro_body"><strong>Text</strong></label><br>
		<textarea name="cbv_lp_intro_body" id="cbv_lp_intro_body" rows="6" class="large-text"><?php echo esc_textarea( $body ); ?></textarea>
	</p>
	<p class="description">Blank line = new paragraph. **bold**, *italic* and [link text](https://...) work, and so do tokens such as <code>{vessel}</code>. No heading and no text = no intro section.</p>
	<p>
		<label for="cbv_lp_intro_chips"><strong>Fact chips</strong> <span class="description">(one per line, up to 4)</span></label><br>
		<textarea name="cbv_lp_intro_chips" id="cbv_lp_intro_chips" rows="4" class="large-text"><?php echo esc_textarea( implode( "\n", $chips ) ); ?></textarea>
	</p>
	<p class="description">The number of nights (from the trip's dates) and the Vessel / venue name are added first automatically; type only the extras, e.g. "Adults only · 18+".</p>

	<?php
	if ( function_exists( 'cbv_lp_media_field' ) ) {
		cbv_lp_media_field( 'cbv_lp_intro_photo', get_post_meta( $post->ID, 'cbv_lp_intro_photo', true ), 'image', 'Intro photo', 'Shown beside the text (under it on phones). Virgin pictures must come from the First Mates toolkit and be used unaltered. Set its Alt Text in the Media Library. No photo = the text runs full width.' );
	}
	?>
	<p>
		<label for="cbv_lp_intro_photo_focus"><strong>Photo focus point</strong></label><br>
		<select name="cbv_lp_intro_photo_focus" id="cbv_lp_intro_photo_focus">
			<?php foreach ( ( function_exists( 'cbv_lp_focus_options' ) ? cbv_lp_focus_options() : array( '' => 'Centre (default)' ) ) as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $focus, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="cbv_lp_intro_caption"><strong>Photo caption</strong></label><br>
		<input type="text" name="cbv_lp_intro_caption" id="cbv_lp_intro_caption" value="<?php echo esc_attr( $caption ); ?>" maxlength="100" class="regular-text">
	</p>
	<p class="description">The photo is shown in a 4:3 frame. Choose where the subject is so it stays in view; a frame must never hide Virgin's logo or branding or change what the picture shows.</p>
	<?php
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_lp_intro_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_lp_intro_nonce'], 'cbv_lp_intro_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$clean = cbv_lp_sanitize_intro( array(
		'booked'  => wp_unslash( $_POST['cbv_lp_travelers_booked'] ?? '' ),
		'heading' => wp_unslash( $_POST['cbv_lp_intro_heading'] ?? '' ),
		'body'    => wp_unslash( $_POST['cbv_lp_intro_body'] ?? '' ),
		'chips'   => wp_unslash( $_POST['cbv_lp_intro_chips'] ?? '' ),
		'photo'   => wp_unslash( $_POST['cbv_lp_intro_photo'] ?? 0 ),
		'focus'   => wp_unslash( $_POST['cbv_lp_intro_photo_focus'] ?? '' ),
		'caption' => wp_unslash( $_POST['cbv_lp_intro_caption'] ?? '' ),
	) );

	update_post_meta( $post_id, 'cbv_lp_travelers_booked', $clean['booked'] );
	update_post_meta( $post_id, 'cbv_lp_intro_heading', $clean['heading'] );
	update_post_meta( $post_id, 'cbv_lp_intro_body', $clean['body'] );
	update_post_meta( $post_id, 'cbv_lp_intro_chips', $clean['chips'] );
	update_post_meta( $post_id, 'cbv_lp_intro_photo', $clean['photo'] );
	update_post_meta( $post_id, 'cbv_lp_intro_photo_focus', $clean['focus'] );
	update_post_meta( $post_id, 'cbv_lp_intro_caption', $clean['caption'] );
} );
