<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Landing Redesign Fields
 * Description: Trip edit-screen fields for the redesigned public landing
 *              page. Step 2 adds the "Landing Page Settings" box: Event
 *              Type, a per-section Default / On / Off control, and an
 *              optional accommodation noun. Later steps add the content
 *              boxes (hero media, fare board, gallery ...) here too.
 *
 *              Nothing here changes what any public visitor sees: the
 *              settings are only read by the new design, which is behind
 *              the master switch + per-trip box (checkedbags-lp-core.php)
 *              or the admin ?preview=new path.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-fields.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_post_meta( 'cb_trip', 'cbv_lp_event_type', array(
		'type'              => 'string',
		'single'            => true,
		'default'           => '',
		'show_in_rest'      => false,
		'sanitize_callback' => function ( $value ) {
			return isset( cbv_lp_event_types()[ $value ] ) ? $value : '';
		},
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'cb_trip', 'cbv_lp_provider_id', array(
		'type'          => 'integer',
		'single'        => true,
		'default'       => 0,
		'show_in_rest'  => false,
		'auth_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'cb_trip', 'cbv_lp_accommodation_noun', array(
		'type'              => 'string',
		'single'            => true,
		'default'           => '',
		'show_in_rest'      => false,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );
} );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cbv_lp_settings', 'Landing Page Settings (new design)', 'cbv_lp_render_settings_box', 'cb_trip', 'normal', 'default' );
} );

function cbv_lp_render_settings_box( $post ) {
	wp_nonce_field( 'cbv_lp_settings_save', 'cbv_lp_settings_nonce' );

	$types      = cbv_lp_event_types();
	$saved_type = get_post_meta( $post->ID, 'cbv_lp_event_type', true );
	$auto_type  = cbv_lp_event_type_from_trip_type( $post->ID );
	$effective  = cbv_lp_event_type( $post->ID );
	$overrides  = get_post_meta( $post->ID, 'cbv_lp_sections', true );
	$overrides  = is_array( $overrides ) ? $overrides : array();
	$noun       = get_post_meta( $post->ID, 'cbv_lp_accommodation_noun', true );
	$provider   = (int) get_post_meta( $post->ID, 'cbv_lp_provider_id', true );
	$providers  = get_posts( array(
		'post_type'      => 'cb_provider',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
	?>
	<p class="description">
		Used only by the new landing design (preview with <code>?preview=new</code>). The current public design ignores these settings.
	</p>

	<p>
		<label for="cbv_lp_event_type"><strong>Event type</strong></label><br>
		<select name="cbv_lp_event_type" id="cbv_lp_event_type">
			<option value="">Automatic &mdash; from Trip Type (currently <?php echo esc_html( $types[ $auto_type ]['label'] ); ?>)</option>
			<?php foreach ( $types as $key => $type ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $saved_type, $key ); ?>><?php echo esc_html( $type['label'] ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p class="description">Sets which sections are on by default and what the page calls things (cabin / room / villa, Travelers / Guests ...). Same template for every type.</p>

	<p>
		<label for="cbv_lp_provider_id"><strong>Provider</strong> <span class="description">(cruise line, resort brand ...)</span></label><br>
		<select name="cbv_lp_provider_id" id="cbv_lp_provider_id">
			<option value="0">None</option>
			<?php foreach ( $providers as $p ) : ?>
				<option value="<?php echo (int) $p->ID; ?>" <?php selected( $provider, (int) $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
			<?php endforeach; ?>
		</select>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=cb_provider' ) ); ?>" target="_blank" rel="noopener">Manage Provider Library</a>
	</p>
	<p class="description">The trip inherits that provider's price-column names, "what's included" cards, how-to-book steps, travel documents and key dates. Anything entered on the trip itself replaces the provider's version of that group.</p>

	<p>
		<label for="cbv_lp_accommodation_noun"><strong>Accommodation word</strong> <span class="description">(optional; overrides the event type's word, e.g. "suite" or "cottage")</span></label><br>
		<input type="text" name="cbv_lp_accommodation_noun" id="cbv_lp_accommodation_noun" value="<?php echo esc_attr( $noun ); ?>" maxlength="30" placeholder="<?php echo esc_attr( cbv_lp_base_labels_for_type( $effective )['accommodation'] ); ?>">
	</p>

	<p><strong>Sections</strong> <span class="description">&mdash; "Default" follows the event type. A section that is on still hides itself if it has no content.</span></p>
	<table class="widefat striped" style="max-width:640px;">
		<thead><tr><th>Section</th><th>Default for <?php echo esc_html( $types[ $effective ]['label'] ); ?></th><th>This trip</th></tr></thead>
		<tbody>
		<?php foreach ( cbv_lp_sections() as $key => $label ) :
			$current = $overrides[ $key ] ?? '';
			?>
			<tr>
				<td><?php echo esc_html( $label ); ?></td>
				<td><?php echo cbv_lp_section_default_on( $effective, $key ) ? 'On' : 'Off'; ?></td>
				<td>
					<select name="cbv_lp_sections[<?php echo esc_attr( $key ); ?>]">
						<option value="">Default</option>
						<option value="on" <?php selected( $current, 'on' ); ?>>On</option>
						<option value="off" <?php selected( $current, 'off' ); ?>>Off</option>
					</select>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<p class="description">The defaults shown follow the event type as last saved; save the trip to see them update after changing the type.</p>
	<?php
}

/** The accommodation word an event type uses with no per-trip override (admin placeholder). */
function cbv_lp_base_labels_for_type( $event_type ) {
	$types = cbv_lp_event_types();
	return array_merge( cbv_lp_base_labels(), (array) ( $types[ $event_type ]['labels'] ?? array() ) );
}

/**
 * Raw posted values -> the three values stored on the trip. Pure (no
 * globals, no writes) so it can be tested directly. Unknown keys / values
 * are dropped; a section left on "Default" is not stored at all, so
 * changing an event type later still moves those sections.
 */
function cbv_lp_sanitize_settings( $raw ) {
	$raw   = is_array( $raw ) ? $raw : array();
	$types = cbv_lp_event_types();

	$event_type = isset( $raw['event_type'] ) ? sanitize_key( wp_unslash( $raw['event_type'] ) ) : '';
	if ( ! isset( $types[ $event_type ] ) ) {
		$event_type = '';
	}

	$sections = array();
	foreach ( (array) ( $raw['sections'] ?? array() ) as $key => $value ) {
		$key   = sanitize_key( $key );
		$value = is_string( $value ) ? sanitize_key( $value ) : '';
		if ( isset( cbv_lp_sections()[ $key ] ) && in_array( $value, array( 'on', 'off' ), true ) ) {
			$sections[ $key ] = $value;
		}
	}

	// Only a real, published provider is kept; anything else (a stale id, a
	// trip or page id, junk) becomes "None".
	$provider_id = isset( $raw['provider_id'] ) && is_scalar( $raw['provider_id'] ) ? absint( $raw['provider_id'] ) : 0;
	if ( $provider_id ) {
		$provider_post = get_post( $provider_id );
		if ( ! $provider_post || 'cb_provider' !== $provider_post->post_type || 'publish' !== $provider_post->post_status ) {
			$provider_id = 0;
		}
	}

	$noun = isset( $raw['noun'] ) ? sanitize_text_field( wp_unslash( $raw['noun'] ) ) : '';
	$noun = function_exists( 'mb_substr' ) ? mb_substr( $noun, 0, 30 ) : substr( $noun, 0, 30 );

	return array(
		'event_type' => $event_type,
		'sections'   => $sections,
		'noun'       => $noun,
		'provider_id' => $provider_id,
	);
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_lp_settings_nonce'] ) || ! wp_verify_nonce( $_POST['cbv_lp_settings_nonce'], 'cbv_lp_settings_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$clean = cbv_lp_sanitize_settings( array(
		'event_type' => $_POST['cbv_lp_event_type'] ?? '',
		'sections'   => $_POST['cbv_lp_sections'] ?? array(),
		'noun'       => $_POST['cbv_lp_accommodation_noun'] ?? '',
		'provider_id' => $_POST['cbv_lp_provider_id'] ?? 0,
	) );

	update_post_meta( $post_id, 'cbv_lp_event_type', $clean['event_type'] );
	update_post_meta( $post_id, 'cbv_lp_sections', $clean['sections'] );
	update_post_meta( $post_id, 'cbv_lp_accommodation_noun', $clean['noun'] );
	update_post_meta( $post_id, 'cbv_lp_provider_id', $clean['provider_id'] );
} );
