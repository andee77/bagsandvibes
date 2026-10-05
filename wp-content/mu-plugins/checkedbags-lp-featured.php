<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Landing Redesign Featured Moment & Gallery
 * Description: Step 7 of the public trip landing redesign: the featured
 *              moment (one per trip: title, optional route day, text, note
 *              card, up to two portrait photos, a background colour) and the
 *              gallery (heading, intro line, up to 10 captioned photos as
 *              tilted prints), plus the two trip boxes that feed them. Only
 *              the new design calls any of this (preview with ?preview=new,
 *              or live per trip once switched on in checkedbags-lp-core.php);
 *              the current public design is untouched.
 *
 *              Photos: only pictures an admin picks from the Media Library
 *              are used (never the members' Gate 08 uploads). Alt text comes
 *              from each picture's Media Library entry.
 *
 *              Media rule: Virgin images are used UNALTERED (see
 *              docs/media-sources.md); the focus point keeps the subject in
 *              view and a frame never hides Virgin's logo or branding.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-featured.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CBV_LP_GALLERY_MAX', 10 );

/* ==========================================================================
   1. Meta. Presentation-only; nothing here is read by the current design.
   ========================================================================== */
add_action( 'init', function () {
	foreach ( array( 'cbv_lp_featured', 'cbv_lp_gallery' ) as $key ) {
		register_post_meta( 'cb_trip', $key, array(
			'type'          => 'array',
			'single'        => true,
			'default'       => array(),
			'show_in_rest'  => false,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}
} );

/* ==========================================================================
   2. Small pure helpers (no globals, no writes), tested directly.
   ========================================================================== */

/**
 * The featured band's background colours (key => label). All four pass WCAG
 * AA with the cream text and gold label (checked 2026-10-05); the teal is a
 * deeper shade (#174A41) of the brand Palm teal, which is too light behind
 * cream text. The hex values live in trip-landing.css.
 */
function cbv_lp_featured_colours() {
	return array(
		'horizon' => 'Horizon blue (default)',
		'ink'     => 'Ink',
		'teal'    => 'Deep palm teal',
		'red'     => 'Deep red (for red-themed events)',
	);
}

/** A capped plain-text field from a posted array. */
function cbv_lp_featured_text( $raw, $key, $max ) {
	$v = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? sanitize_text_field( (string) $raw[ $key ] ) : '';
	return function_exists( 'mb_substr' ) ? mb_substr( $v, 0, $max ) : substr( $v, 0, $max );
}

/** A focus key from a posted array ('' = centre). */
function cbv_lp_featured_focus( $raw, $key ) {
	$v = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? sanitize_key( (string) $raw[ $key ] ) : '';
	return function_exists( 'cbv_lp_focus_options' ) && isset( cbv_lp_focus_options()[ $v ] ) ? $v : '';
}

/** An image attachment id, or 0. */
function cbv_lp_featured_image( $value ) {
	return function_exists( 'cbv_lp_clean_attachment_id' ) ? cbv_lp_clean_attachment_id( $value, 'image' ) : 0;
}

/** Posted featured box values -> what gets stored. */
function cbv_lp_sanitize_featured( $raw ) {
	$raw    = is_array( $raw ) ? $raw : array();
	$body   = isset( $raw['text'] ) && is_scalar( $raw['text'] ) ? sanitize_textarea_field( (string) $raw['text'] ) : '';
	$body   = function_exists( 'mb_substr' ) ? mb_substr( $body, 0, 2000 ) : substr( $body, 0, 2000 );
	$day    = isset( $raw['day'] ) && is_scalar( $raw['day'] ) && ctype_digit( (string) $raw['day'] ) ? (int) $raw['day'] : 0;
	$colour = isset( $raw['colour'] ) && is_scalar( $raw['colour'] ) && isset( cbv_lp_featured_colours()[ (string) $raw['colour'] ] ) ? (string) $raw['colour'] : 'horizon';

	return array(
		'title'      => cbv_lp_featured_text( $raw, 'title', 80 ),
		'day'        => $day >= 1 && $day <= 366 ? $day : 0,
		'text'       => $body,
		'note_label' => cbv_lp_featured_text( $raw, 'note_label', 40 ),
		'note_text'  => cbv_lp_featured_text( $raw, 'note_text', 200 ),
		'photo1'     => cbv_lp_featured_image( $raw['photo1'] ?? 0 ),
		'focus1'     => cbv_lp_featured_focus( $raw, 'focus1' ),
		'photo2'     => cbv_lp_featured_image( $raw['photo2'] ?? 0 ),
		'focus2'     => cbv_lp_featured_focus( $raw, 'focus2' ),
		'colour'     => $colour,
	);
}

/**
 * Posted gallery box values -> what gets stored. Photos keep the order they
 * were posted in (the box's Up / Down buttons change that order); rows that
 * are not images are dropped; at most CBV_LP_GALLERY_MAX are kept.
 */
function cbv_lp_sanitize_gallery( $raw ) {
	$raw    = is_array( $raw ) ? $raw : array();
	$intro  = cbv_lp_featured_text( $raw, 'intro', 300 );
	$photos = array();
	foreach ( (array) ( $raw['photos'] ?? array() ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$id = cbv_lp_featured_image( $row['id'] ?? 0 );
		if ( ! $id ) {
			continue;
		}
		$photos[] = array(
			'id'      => $id,
			'caption' => cbv_lp_featured_text( $row, 'caption', 60 ),
			'focus'   => cbv_lp_featured_focus( $row, 'focus' ),
		);
		if ( count( $photos ) >= CBV_LP_GALLERY_MAX ) {
			break;
		}
	}
	return array(
		'heading' => cbv_lp_featured_text( $raw, 'heading', 80 ),
		'intro'   => $intro,
		'photos'  => $photos,
	);
}

/** The trip's route days as day number => "Day 03 · Wed Oct 27 · Puerto Plata" (for the Day dropdown and the label). */
function cbv_lp_featured_day_choices( $trip_id ) {
	if ( ! function_exists( 'cbv_lp_route_days' ) || ! function_exists( 'cb_trip_get_itinerary' ) ) {
		return array();
	}
	$out = array();
	foreach ( cbv_lp_route_days( cb_trip_get_itinerary( $trip_id ), get_post_meta( $trip_id, 'cb_start_date', true ) ) as $day ) {
		$ts                 = '' !== $day['date'] ? strtotime( $day['date'] . ' 12:00:00 UTC' ) : false;
		$out[ $day['n'] ] = 'Day ' . str_pad( (string) $day['n'], 2, '0', STR_PAD_LEFT ) . ( $ts ? ' · ' . gmdate( 'D M j', $ts ) : '' ) . ' · ' . $day['place'];
	}
	return $out;
}

/* ==========================================================================
   3. Featured moment.
   ========================================================================== */
function cbv_lp_featured_data( $trip_id ) {
	$trip_id = (int) $trip_id;
	if ( function_exists( 'cbv_lp_section_enabled' ) && ! cbv_lp_section_enabled( $trip_id, 'featured' ) ) {
		return null;
	}
	$f   = cbv_lp_sanitize_featured( get_post_meta( $trip_id, 'cbv_lp_featured', true ) );
	$tok = function ( $text ) use ( $trip_id ) {
		return trim( function_exists( 'cbv_lp_apply_tokens' ) ? cbv_lp_apply_tokens( (string) $text, $trip_id ) : (string) $text );
	};
	$title = $tok( $f['title'] );
	if ( '' === $title ) {
		return null;
	}
	// The Day link only shows while that day is still in the itinerary.
	$day = '';
	if ( $f['day'] && isset( cbv_lp_featured_day_choices( $trip_id )[ $f['day'] ] ) ) {
		$day = 'Day ' . str_pad( (string) $f['day'], 2, '0', STR_PAD_LEFT );
	}
	$photos = array();
	foreach ( array( 1, 2 ) as $i ) {
		if ( $f[ 'photo' . $i ] ) {
			$photos[] = array( 'id' => $f[ 'photo' . $i ], 'focus' => function_exists( 'cbv_lp_focus_position' ) ? cbv_lp_focus_position( $f[ 'focus' . $i ] ) : 'center center' );
		}
	}
	$labels = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
	$note   = $tok( $f['note_text'] );

	return array(
		'section'    => $labels['sec_featured'] ?? 'Featured',
		'day'        => $day,
		'title'      => $title,
		'text'       => '' !== trim( $f['text'] ) && function_exists( 'cbv_lp_blocks' ) ? cbv_lp_blocks( $f['text'], $trip_id ) : '',
		'note_label' => $tok( $f['note_label'] ),
		'note_text'  => '' !== $note && function_exists( 'cbv_lp_inline' ) ? cbv_lp_inline( $note ) : esc_html( $note ),
		'photos'     => $photos,
		'colour'     => $f['colour'],
	);
}

function cbv_lp_render_featured( $trip_id ) {
	$d = cbv_lp_featured_data( $trip_id );
	if ( ! $d ) {
		return '';
	}
	$gate  = function_exists( 'cbv_lp_next_gate' ) ? 'Gate ' . cbv_lp_next_gate() : '';
	$label = implode( ' · ', array_filter( array( $gate, $d['day'], $d['section'] ) ) );

	ob_start();
	?>
	<section class="cbv-lp-featured cbv-lp-featured--<?php echo esc_attr( $d['colour'] ); ?>" id="featured" aria-labelledby="cbv-lp-featured-title">
		<div class="cbv-lp-inner <?php echo $d['photos'] ? 'cbv-lp-featured-inner' : 'cbv-lp-featured-inner cbv-lp-featured-inner--solo'; ?>">
			<?php if ( $d['photos'] ) : ?>
				<div class="<?php echo 2 === count( $d['photos'] ) ? 'cbv-lp-featured-photos' : 'cbv-lp-featured-photos cbv-lp-featured-photos--one'; ?>">
					<?php
					foreach ( $d['photos'] as $p ) {
						echo wp_get_attachment_image( $p['id'], 'large', false, array( // phpcs:ignore WordPress.Security.EscapeOutput -- core image markup
							'class'   => 'cbv-lp-featured-img',
							'style'   => 'object-position:' . $p['focus'] . ';',
							'loading' => 'lazy',
						) );
					}
					?>
				</div>
			<?php endif; ?>
			<div class="cbv-lp-featured-copy">
				<p class="cbv-lp-gate cbv-lp-featured-label"><?php echo esc_html( $label ); ?></p>
				<h2 class="cbv-lp-h2 cbv-lp-featured-title" id="cbv-lp-featured-title"><?php echo esc_html( $d['title'] ); ?></h2>
				<?php if ( '' !== $d['text'] ) : ?>
					<div class="cbv-lp-featured-text"><?php echo $d['text']; // phpcs:ignore WordPress.Security.EscapeOutput -- cbv_lp_blocks escapes first ?></div>
				<?php endif; ?>
				<?php if ( '' !== $d['note_label'] || '' !== $d['note_text'] ) : ?>
					<div class="cbv-lp-note">
						<?php if ( '' !== $d['note_label'] ) : ?>
							<p class="cbv-lp-note-label"><?php echo esc_html( $d['note_label'] ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== $d['note_text'] ) : ?>
							<p class="cbv-lp-note-text"><?php echo $d['note_text']; // phpcs:ignore WordPress.Security.EscapeOutput -- cbv_lp_inline escapes first ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   4. Gallery.
   ========================================================================== */
function cbv_lp_gallery_data( $trip_id ) {
	$trip_id = (int) $trip_id;
	if ( function_exists( 'cbv_lp_section_enabled' ) && ! cbv_lp_section_enabled( $trip_id, 'gallery' ) ) {
		return null;
	}
	$g = cbv_lp_sanitize_gallery( get_post_meta( $trip_id, 'cbv_lp_gallery', true ) );
	if ( ! $g['photos'] ) {
		return null;
	}
	$tok = function ( $text ) use ( $trip_id ) {
		return trim( function_exists( 'cbv_lp_apply_tokens' ) ? cbv_lp_apply_tokens( (string) $text, $trip_id ) : (string) $text );
	};
	$labels  = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
	$section = $labels['sec_gallery'] ?? 'Gallery';
	$heading = $tok( $g['heading'] );
	$intro   = $tok( $g['intro'] );
	$photos  = array();
	foreach ( $g['photos'] as $p ) {
		$photos[] = array(
			'id'      => $p['id'],
			'caption' => $tok( $p['caption'] ),
			'focus'   => function_exists( 'cbv_lp_focus_position' ) ? cbv_lp_focus_position( $p['focus'] ) : 'center center',
		);
	}
	return array(
		'section' => $section,
		'heading' => '' !== $heading ? $heading : $section,
		'intro'   => '' !== $intro && function_exists( 'cbv_lp_inline' ) ? cbv_lp_inline( $intro ) : '',
		'photos'  => $photos,
	);
}

function cbv_lp_render_gallery( $trip_id ) {
	$d = cbv_lp_gallery_data( $trip_id );
	if ( ! $d ) {
		return '';
	}
	$gate = function_exists( 'cbv_lp_gate_label' ) ? cbv_lp_gate_label( $d['section'] ) : $d['section'];

	ob_start();
	?>
	<section class="cbv-lp-gallery" id="gallery" aria-labelledby="cbv-lp-gallery-title">
		<div class="cbv-lp-inner cbv-lp-gallery-inner">
			<div class="cbv-lp-gallery-head">
				<p class="cbv-lp-gate"><?php echo esc_html( $gate ); ?></p>
				<h2 class="cbv-lp-h2" id="cbv-lp-gallery-title"><?php echo esc_html( $d['heading'] ); ?></h2>
				<?php if ( '' !== $d['intro'] ) : ?>
					<p class="cbv-lp-gallery-intro"><?php echo $d['intro']; // phpcs:ignore WordPress.Security.EscapeOutput -- cbv_lp_inline escapes first ?></p>
				<?php endif; ?>
			</div>
			<ul class="cbv-lp-prints">
				<?php foreach ( $d['photos'] as $p ) : ?>
					<li class="cbv-lp-print-item">
						<figure class="cbv-lp-print">
							<?php
							echo wp_get_attachment_image( $p['id'], 'medium_large', false, array( // phpcs:ignore WordPress.Security.EscapeOutput -- core image markup
								'class'   => 'cbv-lp-print-img',
								'style'   => 'object-position:' . $p['focus'] . ';',
								'loading' => 'lazy',
							) );
							?>
							<?php if ( '' !== $p['caption'] ) : ?>
								<figcaption><?php echo esc_html( $p['caption'] ); ?></figcaption>
							<?php endif; ?>
						</figure>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   5. Trip edit screen: the "Featured moment" and "Gallery" boxes. The media
      chooser for the featured photos comes from checkedbags-lp-hero.php.
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cbv_lp_featured', 'Featured moment (new design)', 'cbv_lp_render_featured_box', 'cb_trip', 'normal', 'default' );
	add_meta_box( 'cbv_lp_gallery', 'Gallery (new design)', 'cbv_lp_render_gallery_box', 'cb_trip', 'normal', 'default' );
} );

/** One focus select (shared by both boxes). */
function cbv_lp_featured_focus_select( $name, $id, $value, $label ) {
	$options = function_exists( 'cbv_lp_focus_options' ) ? cbv_lp_focus_options() : array( '' => 'Centre (default)' );
	?>
	<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
	<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>">
		<?php foreach ( $options as $key => $text ) : ?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $text ); ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

function cbv_lp_render_featured_box( $post ) {
	wp_nonce_field( 'cbv_lp_featured_save', 'cbv_lp_featured_nonce' );
	$f       = cbv_lp_sanitize_featured( get_post_meta( $post->ID, 'cbv_lp_featured', true ) );
	$choices = cbv_lp_featured_day_choices( $post->ID );
	?>
	<p class="description">Used only by the new landing design (preview with <code>?preview=new</code>). One featured moment per trip, e.g. a signature night. No title = no featured section.</p>
	<p>
		<label for="cbv_lp_featured_title"><strong>Title</strong></label><br>
		<input type="text" name="cbv_lp_featured[title]" id="cbv_lp_featured_title" value="<?php echo esc_attr( $f['title'] ); ?>" maxlength="80" class="regular-text">
	</p>
	<p>
		<label for="cbv_lp_featured_day"><strong>Day</strong> <span class="description">(optional; adds "DAY 02" to the small label above the title)</span></label><br>
		<select name="cbv_lp_featured[day]" id="cbv_lp_featured_day">
			<option value="0">No day</option>
			<?php foreach ( $choices as $n => $text ) : ?>
				<option value="<?php echo (int) $n; ?>" <?php selected( $f['day'], $n ); ?>><?php echo esc_html( $text ); ?></option>
			<?php endforeach; ?>
			<?php if ( $f['day'] && ! isset( $choices[ $f['day'] ] ) ) : ?>
				<option value="<?php echo (int) $f['day']; ?>" selected="selected"><?php echo esc_html( 'Day ' . str_pad( (string) $f['day'], 2, '0', STR_PAD_LEFT ) . ' (not in the itinerary now, not shown)' ); ?></option>
			<?php endif; ?>
		</select>
	</p>
	<p>
		<label for="cbv_lp_featured_text"><strong>Text</strong></label><br>
		<textarea name="cbv_lp_featured[text]" id="cbv_lp_featured_text" rows="4" class="large-text"><?php echo esc_textarea( $f['text'] ); ?></textarea>
	</p>
	<p>
		<label for="cbv_lp_featured_note_label"><strong>Note card</strong> <span class="description">(optional: a small label and one line, e.g. "Dress code · All red")</span></label><br>
		<input type="text" name="cbv_lp_featured[note_label]" id="cbv_lp_featured_note_label" value="<?php echo esc_attr( $f['note_label'] ); ?>" maxlength="40" class="regular-text" aria-label="Note card label"><br>
		<input type="text" name="cbv_lp_featured[note_text]" id="cbv_lp_featured_note_text" value="<?php echo esc_attr( $f['note_text'] ); ?>" maxlength="200" class="large-text" aria-label="Note card text" style="margin-top:4px;">
	</p>
	<?php
	if ( function_exists( 'cbv_lp_media_field' ) ) {
		cbv_lp_media_field( 'cbv_lp_featured[photo1]', $f['photo1'], 'image', 'Photo 1 (optional)', 'Shown in a tall 2:3 frame. Virgin pictures from the First Mates toolkit only, unaltered. Give it Alt Text in the Media Library.' );
		echo '<p>';
		cbv_lp_featured_focus_select( 'cbv_lp_featured[focus1]', 'cbv_lp_featured_focus1', $f['focus1'], 'Photo 1 focus point' );
		echo '</p>';
		cbv_lp_media_field( 'cbv_lp_featured[photo2]', $f['photo2'], 'image', 'Photo 2 (optional)', 'Sits beside photo 1, set a little lower. Same rules.' );
		echo '<p>';
		cbv_lp_featured_focus_select( 'cbv_lp_featured[focus2]', 'cbv_lp_featured_focus2', $f['focus2'], 'Photo 2 focus point' );
		echo '</p>';
	}
	?>
	<p>
		<label for="cbv_lp_featured_colour"><strong>Background colour</strong></label><br>
		<select name="cbv_lp_featured[colour]" id="cbv_lp_featured_colour">
			<?php foreach ( cbv_lp_featured_colours() as $key => $text ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $f['colour'], $key ); ?>><?php echo esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p class="description">All four colours keep the text easy to read. A frame must never hide Virgin's logo or branding or change what a picture shows; use the focus points.</p>
	<?php
}

function cbv_lp_render_gallery_box( $post ) {
	wp_nonce_field( 'cbv_lp_gallery_save', 'cbv_lp_gallery_nonce' );
	$g = cbv_lp_sanitize_gallery( get_post_meta( $post->ID, 'cbv_lp_gallery', true ) );
	?>
	<p class="description">Used only by the new landing design (preview with <code>?preview=new</code>). Up to <?php echo (int) CBV_LP_GALLERY_MAX; ?> photos, shown as tilted prints. No photos = no gallery section. Only pictures you choose from the Media Library are used (never members' trip uploads).</p>
	<p>
		<label for="cbv_lp_gallery_heading"><strong>Heading</strong></label><br>
		<input type="text" name="cbv_lp_gallery[heading]" id="cbv_lp_gallery_heading" value="<?php echo esc_attr( $g['heading'] ); ?>" maxlength="80" class="regular-text">
		<span class="description">Blank = the section name.</span>
	</p>
	<p>
		<label for="cbv_lp_gallery_intro"><strong>Intro line</strong></label><br>
		<input type="text" name="cbv_lp_gallery[intro]" id="cbv_lp_gallery_intro" value="<?php echo esc_attr( $g['intro'] ); ?>" maxlength="300" class="large-text">
	</p>
	<div class="cbv-lp-gallery-admin" data-max="<?php echo (int) CBV_LP_GALLERY_MAX; ?>">
		<p><strong>Photos</strong> <span class="cbv-lp-gallery-count" aria-live="polite"><?php echo esc_html( count( $g['photos'] ) . ' of ' . CBV_LP_GALLERY_MAX ); ?></span></p>
		<ol class="cbv-lp-gallery-rows" style="margin-left:1.5em;">
			<?php foreach ( $g['photos'] as $i => $p ) : ?>
				<?php cbv_lp_gallery_admin_row( $i, $p ); ?>
			<?php endforeach; ?>
		</ol>
		<p>
			<button type="button" class="button cbv-lp-gallery-add">Add photos</button>
			<span class="description">Opens the Media Library; choose several at once. Each photo needs Alt Text in the Media Library.</span>
		</p>
		<template class="cbv-lp-gallery-template"><?php cbv_lp_gallery_admin_row( '__I__', array( 'id' => 0, 'caption' => '', 'focus' => '' ) ); ?></template>
	</div>
	<?php
}

/** One gallery row in the box: thumbnail, hidden id, caption, focus, Up / Down / Remove. */
function cbv_lp_gallery_admin_row( $i, $p ) {
	$base  = 'cbv_lp_gallery[photos][' . $i . ']';
	$id    = 'cbv_lp_gallery_' . $i;
	$thumb = $p['id'] ? wp_get_attachment_image( $p['id'], array( 60, 60 ), false, array( 'style' => 'width:60px;height:60px;object-fit:cover;vertical-align:middle;' ) ) : '';
	?>
	<li class="cbv-lp-gallery-row" style="margin:0 0 10px;padding:8px;border:1px solid #dcdcde;">
		<span class="cbv-lp-gallery-thumb"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput -- core image markup ?></span>
		<input type="hidden" name="<?php echo esc_attr( $base ); ?>[id]" value="<?php echo $p['id'] ? (int) $p['id'] : ''; ?>">
		<label for="<?php echo esc_attr( $id ); ?>_caption">Caption</label>
		<input type="text" name="<?php echo esc_attr( $base ); ?>[caption]" id="<?php echo esc_attr( $id ); ?>_caption" value="<?php echo esc_attr( $p['caption'] ); ?>" maxlength="60">
		<?php cbv_lp_featured_focus_select( $base . '[focus]', $id . '_focus', $p['focus'], 'Focus' ); ?>
		<button type="button" class="button-link cbv-lp-gallery-up">Up</button> ·
		<button type="button" class="button-link cbv-lp-gallery-down">Down</button> ·
		<button type="button" class="button-link cbv-lp-gallery-remove" style="color:#b32d2e;">Remove</button>
	</li>
	<?php
}

add_action( 'admin_footer', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'cb_trip' !== $screen->post_type || 'post' !== $screen->base ) {
		return;
	}
	?>
	<script>
	(function () {
		var wrap = document.querySelector('.cbv-lp-gallery-admin');
		if (!wrap) { return; }
		var list = wrap.querySelector('.cbv-lp-gallery-rows');
		var max = parseInt(wrap.getAttribute('data-max'), 10) || 10;
		var next = 0;
		function rows() { return list.querySelectorAll('.cbv-lp-gallery-row'); }
		function refresh() {
			var n = rows().length;
			wrap.querySelector('.cbv-lp-gallery-count').textContent = n + ' of ' + max;
			wrap.querySelector('.cbv-lp-gallery-add').disabled = n >= max;
			Array.prototype.forEach.call(rows(), function (row, i) {
				row.querySelector('.cbv-lp-gallery-up').setAttribute('aria-label', 'Move photo ' + (i + 1) + ' up');
				row.querySelector('.cbv-lp-gallery-down').setAttribute('aria-label', 'Move photo ' + (i + 1) + ' down');
				row.querySelector('.cbv-lp-gallery-remove').setAttribute('aria-label', 'Remove photo ' + (i + 1));
			});
		}
		list.addEventListener('click', function (e) {
			var row = e.target.closest('.cbv-lp-gallery-row');
			if (!row) { return; }
			if (e.target.closest('.cbv-lp-gallery-up') && row.previousElementSibling) {
				e.preventDefault(); list.insertBefore(row, row.previousElementSibling); e.target.focus();
			} else if (e.target.closest('.cbv-lp-gallery-down') && row.nextElementSibling) {
				e.preventDefault(); list.insertBefore(row.nextElementSibling, row); e.target.focus();
			} else if (e.target.closest('.cbv-lp-gallery-remove')) {
				e.preventDefault(); row.parentNode.removeChild(row); wrap.querySelector('.cbv-lp-gallery-add').focus();
			}
			refresh();
		});
		wrap.querySelector('.cbv-lp-gallery-add').addEventListener('click', function (e) {
			e.preventDefault();
			if (!window.wp || !wp.media) { return; }
			var frame = wp.media({ title: 'Choose gallery photos', library: { type: 'image' }, multiple: true, button: { text: 'Add to gallery' } });
			frame.on('select', function () {
				var room = max - rows().length;
				var chosen = frame.state().get('selection').toJSON();
				if (chosen.length > room) { window.alert('The gallery holds ' + max + ' photos. Only the first ' + room + ' were added.'); }
				chosen.slice(0, room).forEach(function (a) {
					var html = wrap.querySelector('.cbv-lp-gallery-template').innerHTML.replace(/__I__/g, 'n' + (next++));
					var tmp = document.createElement('ol');
					tmp.innerHTML = html;
					var row = tmp.firstElementChild;
					row.querySelector('input[type=hidden]').value = a.id;
					var url = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
					var img = document.createElement('img');
					img.src = url; img.alt = a.alt || ''; img.style.cssText = 'width:60px;height:60px;object-fit:cover;vertical-align:middle;';
					row.querySelector('.cbv-lp-gallery-thumb').appendChild(img);
					list.appendChild(row);
				});
				refresh();
			});
			frame.open();
		});
		refresh();
	})();
	</script>
	<?php
} );

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['cbv_lp_featured_nonce'] ) && wp_verify_nonce( $_POST['cbv_lp_featured_nonce'], 'cbv_lp_featured_save' ) ) {
		$raw = isset( $_POST['cbv_lp_featured'] ) && is_array( $_POST['cbv_lp_featured'] ) ? wp_unslash( $_POST['cbv_lp_featured'] ) : array();
		update_post_meta( $post_id, 'cbv_lp_featured', cbv_lp_sanitize_featured( $raw ) );
	}
	if ( isset( $_POST['cbv_lp_gallery_nonce'] ) && wp_verify_nonce( $_POST['cbv_lp_gallery_nonce'], 'cbv_lp_gallery_save' ) ) {
		$raw = isset( $_POST['cbv_lp_gallery'] ) && is_array( $_POST['cbv_lp_gallery'] ) ? wp_unslash( $_POST['cbv_lp_gallery'] ) : array();
		update_post_meta( $post_id, 'cbv_lp_gallery', cbv_lp_sanitize_gallery( $raw ) );
	}
} );
