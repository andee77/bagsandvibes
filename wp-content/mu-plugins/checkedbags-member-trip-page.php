<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Members' Trip Page
 * Description: The redesigned trip page a roster member (or an admin) sees
 *              on a trip's own URL (owner, 2026-10-29), for every trip
 *              whichever public landing design it uses: hero, boarding pass
 *              with a days-until stub, "Your next step", the 6-part trip
 *              checklist, key dates, route, what's included, pack list,
 *              who's going, then the travel details card, rules, who does
 *              what and contact. Gate 07's single-trip filter calls
 *              cbv_render_member_trip_page(); every section reads the same
 *              data the new landing design and Gate 09 already use, and a
 *              section with no data is left out.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-member-trip-page.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CBV_MT_FORMS_EMAIL = 'travel@journeywellglobal.com';

/** Members' view of a trip: full-width page, no duplicate title ribbon (styles.css). */
add_filter( 'body_class', function ( $classes ) {
	if ( is_singular( 'cb_trip' ) && function_exists( 'cbv_lp_trip_view' ) && 'private' === cbv_lp_trip_view( get_queried_object_id() ) ) {
		$classes[] = 'cbv-member-trip';
	}
	return $classes;
} );

/* ==========================================================================
   1. Small helpers
   ========================================================================== */

/** "Andrea H": first name and last initial (falls back to the display name). */
function cbv_mt_short_name( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return '';
	}
	list( $first, $last ) = function_exists( 'cbv_get_member_name_parts' ) ? cbv_get_member_name_parts( $user_id ) : array( '', '' );
	$first = trim( (string) $first );
	$last  = trim( (string) $last );
	if ( '' === $first ) {
		return $user->display_name;
	}
	return $first . ( '' !== $last ? ' ' . mb_strtoupper( mb_substr( $last, 0, 1 ) ) : '' );
}

function cbv_mt_first_name( $user_id ) {
	list( $first ) = function_exists( 'cbv_get_member_name_parts' ) ? cbv_get_member_name_parts( $user_id ) : array( '' );
	$first = trim( (string) $first );
	if ( '' !== $first ) {
		return $first;
	}
	$user = get_userdata( $user_id );
	return $user ? strtok( $user->display_name, ' ' ) : '';
}

/** Profile photo URL when the member has uploaded one (Ultimate Member), else ''. */
function cbv_mt_photo_url( $user_id ) {
	return get_user_meta( $user_id, 'profile_photo', true ) ? (string) get_avatar_url( $user_id, array( 'size' => 120 ) ) : '';
}

/** The place name before the first comma ("Puerto Plata, Dominican Republic" -> "Puerto Plata"). */
function cbv_mt_place( $port ) {
	$port = trim( (string) $port );
	return '' === $port ? '' : trim( strtok( $port, ',' ) );
}

function cbv_mt_date( $ymd, $format = 'M j, Y' ) {
	$ts = $ymd ? strtotime( $ymd ) : false;
	return $ts ? date_i18n( $format, $ts ) : '';
}

/** Light markup used by the provider texts: **bold** only, everything else escaped. */
function cbv_mt_text( $text, $trip_id ) {
	$text = function_exists( 'cbv_lp_apply_tokens' ) ? cbv_lp_apply_tokens( (string) $text, $trip_id ) : (string) $text;
	return preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', esc_html( $text ) );
}

/* ==========================================================================
   2. The checklist (real data; owner, 2026-10-29)
      status: done | waiting (the member's signed forms) | todo
   ========================================================================== */
function cbv_mt_checklist( $trip_id, $user_id ) {
	$items     = array();
	$is_cruise = function_exists( 'cbv_inc_is_cruise' ) && cbv_inc_is_cruise( $trip_id );
	$gate09    = home_url( '/gate-09-payments/' );

	// 1. Trip rules.
	$needs_rules = function_exists( 'cbv_user_needs_trip_agreement_reaccept' ) && cbv_user_needs_trip_agreement_reaccept( $user_id, $trip_id );
	$accepted    = get_user_meta( $user_id, '_trip_agreement_accepted_' . $trip_id, true );
	$items[]     = $needs_rules
		? array( 'title' => 'Trip rules accepted', 'status' => 'todo', 'text' => 'Read and accept this trip’s rules to see the rest of your trip.', 'button' => array( 'Read the rules', '#cbv-trip-rules-prompt' ), 'next' => array( 'Accept the trip rules', 'Read and accept this trip’s rules to see the rest of your trip.' ) )
		: array( 'title' => 'Trip rules accepted', 'status' => 'done', 'text' => is_array( $accepted ) ? 'Version ' . (int) $accepted['version'] . ( ! empty( $accepted['date'] ) ? ' · accepted ' . cbv_mt_date( $accepted['date'] ) : '' ) : 'Accepted' );

	// 2. Travel details (Trip Registration).
	$intake  = function_exists( 'cbv_get_traveler_intake' ) ? cbv_get_traveler_intake( $user_id, $trip_id ) : array();
	$missing = ( $intake && function_exists( 'cbv_intake_missing_fields' ) ) ? cbv_intake_missing_fields( $trip_id, $intake ) : array();
	$saved   = function_exists( 'cbv_get_traveler_intake_saved_at' ) ? cbv_get_traveler_intake_saved_at( $user_id, $trip_id ) : '';
	if ( $intake && ! $missing ) {
		$items[] = array( 'title' => 'Travel details saved', 'status' => 'done', 'text' => ( $saved ? 'Last saved ' . cbv_mt_date( $saved ) . ' · ' : '' ), 'link' => array( 'View or edit', '#cbv-traveler-intake' ) );
	} elseif ( $intake ) {
		$items[] = array( 'title' => 'Travel details saved', 'status' => 'todo', 'text' => 'Still needed: ' . implode( ', ', $missing ), 'button' => array( 'Finish your travel details', '#cbv-traveler-intake' ), 'next' => array( 'Finish your travel details', 'Still needed: ' . implode( ', ', $missing ) . '.' ) );
	} else {
		$items[] = array( 'title' => 'Travel details saved', 'status' => 'todo', 'text' => 'Tell us your travel preferences and your insurance choice.', 'button' => array( 'Add your travel details', '#cbv-traveler-intake' ), 'next' => array( 'Add your travel details', 'Tell us your travel preferences and your insurance choice.' ) );
	}

	// 3. Travel insurance waiver.
	$waiver_url = content_url( 'uploads/checkedbags/documents/Allianz_Waiver_form.pdf' );
	$insurance  = $intake['insurance_decision'] ?? '';
	if ( 'accepted' === $insurance ) {
		$items[] = array( 'title' => 'Travel insurance waiver', 'status' => 'done', 'text' => 'Not needed: you chose travel insurance' );
	} elseif ( 'yes' === get_user_meta( $user_id, '_insurance_waiver_received_' . $trip_id, true ) ) {
		$items[] = array( 'title' => 'Travel insurance waiver', 'status' => 'done', 'text' => 'Received' );
	} else {
		$items[] = array( 'title' => 'Travel insurance waiver', 'status' => 'waiting', 'text' => 'We haven’t received your signed waiver yet.', 'button' => array( 'Download the waiver', $waiver_url, true ), 'next' => array( 'Send your travel insurance waiver', 'You declined travel insurance. Download the Allianz waiver, sign it, and email it to ' . CBV_MT_FORMS_EMAIL . '.' ) );
	}

	// 4. Card authorization form.
	$cc_url = content_url( 'uploads/checkedbags/documents/CC_authorization.pdf' );
	if ( 'yes' === get_user_meta( $user_id, '_cc_auth_received_' . $trip_id, true ) ) {
		$items[] = array( 'title' => 'Card authorization form', 'status' => 'done', 'text' => 'Received' );
	} else {
		$items[] = array( 'title' => 'Card authorization form', 'status' => 'waiting', 'text' => 'We haven’t received your signed form yet.', 'button' => array( 'Download the form', $cc_url, true ), 'next' => array( 'Send your card authorization form', 'Download the credit card authorization form, sign it, and email it to ' . CBV_MT_FORMS_EMAIL . '.' ) );
	}

	// 5. CBGV Group Experience Fee (Gate 09; the Pay button lives there).
	$fee_total = function_exists( 'cb_trip_cbgv_fee_total' ) ? cb_trip_cbgv_fee_total( $trip_id, $user_id ) : 0;
	$paid      = function_exists( 'cb_trip_amount_paid' ) ? cb_trip_amount_paid( $trip_id, $user_id ) : 0;
	$balance   = function_exists( 'cb_trip_balance_due' ) ? cb_trip_balance_due( $trip_id, $user_id ) : 0;
	if ( $fee_total <= 0 ) {
		$items[] = array( 'title' => 'CBGV Group Experience Fee', 'status' => 'done', 'text' => 'Nothing due' );
	} elseif ( $balance <= 0 || 'yes' === get_user_meta( $user_id, '_paid_in_full_' . $trip_id, true ) ) {
		$items[] = array( 'title' => 'CBGV Group Experience Fee', 'status' => 'done', 'text' => 'Paid in full' );
	} else {
		$due     = '$' . number_format( $balance, 2 ) . ' due · $' . number_format( $paid, 2 ) . ' paid';
		$items[] = array( 'title' => 'CBGV Group Experience Fee', 'status' => 'todo', 'text' => $due, 'button' => array( 'Pay now', $gate09 ), 'next' => array( 'Pay your CBGV Group Experience Fee', '$' . number_format( $balance, 2 ) . ' is due.' ) );
	}

	// 6. Book with your travel advisor (Schedule Appointment on Gate 09).
	$appt   = function_exists( 'cbv_get_latest_appointment_request' ) ? cbv_get_latest_appointment_request( $user_id, $trip_id ) : null;
	$advice = 'Set up your ' . ( $is_cruise ? 'cruise' : 'travel' ) . ' payment with your travel advisor.';
	if ( $appt && 'done' === ( $appt['status'] ?? '' ) ) {
		$items[] = array( 'title' => 'Book with your travel advisor', 'status' => 'done', 'text' => 'Complete' );
	} else {
		$status  = $appt && function_exists( 'cbv_appointment_status_label' ) ? 'Your request: ' . cbv_appointment_status_label( $appt['status'] ?? 'new' ) : $advice;
		$items[] = array( 'title' => 'Book with your travel advisor', 'status' => 'todo', 'text' => $status, 'button' => array( 'Schedule', $gate09 ), 'next' => array( 'Book with your travel advisor', $advice ) );
	}

	return $items;
}

/* ==========================================================================
   3. The page
   ========================================================================== */
function cbv_render_member_trip_page( $trip_id, $viewer_id, $on_roster, $content = '' ) {
	$trip_id   = (int) $trip_id;
	$labels    = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
	$is_cruise = function_exists( 'cbv_inc_is_cruise' ) && cbv_inc_is_cruise( $trip_id );
	$title     = get_the_title( $trip_id );
	$start     = (string) get_post_meta( $trip_id, 'cb_start_date', true );
	$end       = (string) get_post_meta( $trip_id, 'cb_end_date', true );
	$today     = current_time( 'Y-m-d' );
	$code      = (string) get_post_meta( $trip_id, 'cb_trip_code', true );
	$venue     = (string) get_post_meta( $trip_id, 'cbv_lp_venue_name', true );
	$provider  = function_exists( 'cbv_lp_provider_id' ) ? (int) cbv_lp_provider_id( $trip_id ) : 0;
	$provider  = $provider && 'publish' === get_post_status( $provider ) ? html_entity_decode( get_the_title( $provider ), ENT_QUOTES, 'UTF-8' ) : '';
	$types     = wp_get_post_terms( $trip_id, 'cb_trip_type', array( 'fields' => 'names' ) );
	$itinerary = function_exists( 'cb_trip_get_itinerary' ) ? cb_trip_get_itinerary( $trip_id ) : array();
	$ports     = array_values( array_filter( wp_list_pluck( $itinerary, 'port' ) ) );
	$codes     = array_values( array_filter( wp_list_pluck( $itinerary, 'stop_code' ) ) );
	$needs_rules = $on_roster && function_exists( 'cbv_user_needs_trip_agreement_reaccept' ) && cbv_user_needs_trip_agreement_reaccept( $viewer_id, $trip_id );

	// Hero picture: the new design's poster, else the cover photo.
	$poster_id = (int) get_post_meta( $trip_id, 'cbv_lp_hero_poster', true );
	$hero_url  = $poster_id ? (string) wp_get_attachment_image_url( $poster_id, 'full' ) : '';
	if ( '' === $hero_url && function_exists( 'cbv_get_trip_cover_photo_url' ) ) {
		$hero_url = (string) cbv_get_trip_cover_photo_url( $trip_id, 'full' );
	}

	// "Oct 25 – Oct 30, 2027 · Miami round trip · 5 nights"
	$sub = array();
	if ( $start ) {
		$sub[] = '<strong>' . esc_html( function_exists( 'cb_format_date_range' ) ? cb_format_date_range( $start, $end ) : cbv_mt_date( $start ) ) . '</strong>';
	}
	if ( $ports ) {
		$from  = cbv_mt_place( $ports[0] );
		$to    = cbv_mt_place( end( $ports ) );
		$sub[] = esc_html( $from === $to ? $from . ' round trip' : $from . ' to ' . $to );
	}
	if ( $start && $end && $end > $start ) {
		$nights = (int) ( ( strtotime( $end ) - strtotime( $start ) ) / DAY_IN_SECONDS );
		$sub[]  = esc_html( $nights . ' ' . ( 1 === $nights ? 'night' : 'nights' ) );
	}

	// Days-until stub: counting down, "Bon voyage" during the trip, gone after it.
	$stub = '';
	if ( $start && $today < $start ) {
		$days = (int) round( ( strtotime( $start ) - strtotime( $today ) ) / DAY_IN_SECONDS );
		$stub = '<span class="cbv-mt-stub-num">' . (int) $days . '</span><span class="cbv-mt-stub-label">' . esc_html( ( 1 === $days ? 'Day' : 'Days' ) . ' until we ' . ( $is_cruise ? 'sail' : 'go' ) ) . '</span>';
	} elseif ( $start && $today <= ( $end ? $end : $start ) ) {
		$stub = '<span class="cbv-mt-stub-num cbv-mt-stub-word">Bon voyage</span>';
	}

	$checklist = $on_roster ? cbv_mt_checklist( $trip_id, $viewer_id ) : array();
	$done      = count( array_filter( $checklist, function ( $i ) { return 'done' === $i['status']; } ) );
	$waiting   = count( array_filter( $checklist, function ( $i ) { return 'waiting' === $i['status']; } ) );
	$next_i    = null;
	foreach ( $checklist as $i => $item ) {
		if ( 'done' !== $item['status'] ) {
			$next_i = $i;
			break;
		}
	}
	$forum_id  = (int) get_post_meta( $trip_id, 'cb_forum_id', true );
	$forum_url = ( $forum_id && function_exists( 'bbp_get_forum_permalink' ) && get_post( $forum_id ) ) ? bbp_get_forum_permalink( $forum_id ) : '';

	$button = function ( $b, $class ) {
		if ( empty( $b ) ) {
			return '';
		}
		$external = ! empty( $b[2] ) ? ' target="_blank" rel="noopener"' : '';
		return '<a class="cbv-mt-btn ' . esc_attr( $class ) . '" href="' . esc_url( $b[1] ) . '"' . $external . '>' . esc_html( $b[0] ) . '</a>';
	};

	ob_start();
	?>
	<div class="cbv-mt">

	<header class="cbv-mt-hero"<?php echo $hero_url ? ' style="background-image:url(\'' . esc_url( $hero_url ) . '\');"' : ''; ?>>
		<div class="cbv-mt-wrap">
			<div class="cbv-mt-tags">
				<?php foreach ( (array) $types as $type ) : ?><span class="cbv-mt-tag"><?php echo esc_html( $type ); ?></span><?php endforeach; ?>
				<?php if ( $provider ) : ?><span class="cbv-mt-tag"><?php echo esc_html( $provider ); ?></span><?php endif; ?>
				<?php if ( $code ) : ?><span class="cbv-mt-tag"><?php echo esc_html( $code ); ?></span><?php endif; ?>
			</div>
			<h1 class="cbv-mt-title"><?php echo esc_html( $title ); ?></h1>
			<p class="cbv-mt-sub"><?php echo implode( ' · ', $sub ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>
				<?php if ( $on_roster ) : ?><br>Welcome aboard, <?php echo esc_html( cbv_mt_first_name( $viewer_id ) ); ?>.<?php endif; ?></p>
		</div>
	</header>

	<div class="cbv-mt-wrap">
		<div class="cbv-mt-pass-row<?php echo $on_roster ? '' : ' cbv-mt-pass-row--solo'; ?>">
			<div class="cbv-mt-pass<?php echo $stub ? '' : ' cbv-mt-pass--nostub'; ?>" aria-label="Boarding pass">
				<div class="cbv-mt-pass-main">
					<div class="cbv-mt-pass-head"><span>Boarding pass</span><?php if ( $venue ) : ?><span><?php echo esc_html( $venue ); ?></span><?php endif; ?></div>
					<?php if ( count( $codes ) >= 2 ) : ?>
						<div class="cbv-mt-pass-route"><span><?php echo esc_html( $codes[0] ); ?></span><span class="cbv-mt-pass-line" aria-hidden="true"></span><span><?php echo esc_html( end( $codes ) ); ?></span></div>
					<?php endif; ?>
					<dl class="cbv-mt-pass-grid">
						<div><dt>Passenger</dt><dd><?php echo esc_html( $on_roster ? cbv_mt_short_name( $viewer_id ) : 'Staff view' ); ?></dd></div>
						<?php if ( $provider ) : ?><div><dt><?php echo esc_html( $is_cruise ? 'Cruise line' : 'Provider' ); ?></dt><dd><?php echo esc_html( $provider ); ?></dd></div><?php endif; ?>
						<?php if ( $venue ) : ?><div><dt><?php echo esc_html( $labels['pass_place'] ?? 'Ship' ); ?></dt><dd><?php echo esc_html( $venue ); ?></dd></div><?php endif; ?>
						<?php if ( $start ) : ?><div><dt><?php echo esc_html( $labels['pass_start'] ?? 'Departs' ); ?></dt><dd><?php echo esc_html( cbv_mt_date( $start, 'D, M j, Y' ) ); ?></dd></div><?php endif; ?>
						<?php if ( $end ) : ?><div><dt><?php echo esc_html( $labels['pass_end'] ?? 'Returns' ); ?></dt><dd><?php echo esc_html( cbv_mt_date( $end, 'D, M j, Y' ) ); ?></dd></div><?php endif; ?>
						<?php if ( $code ) : ?><div><dt>Trip code</dt><dd><?php echo esc_html( $code ); ?></dd></div><?php endif; ?>
					</dl>
				</div>
				<?php if ( $stub ) : ?><div class="cbv-mt-stub"><?php echo $stub; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?></div><?php endif; ?>
			</div>

			<?php if ( $on_roster ) : ?>
			<aside class="cbv-mt-next" aria-label="Your next step">
				<?php if ( null === $next_i ) : ?>
					<p class="cbv-mt-eyebrow">Your next step</p>
					<h2 class="cbv-mt-next-title">You&#8217;re all set!</h2>
					<?php if ( $forum_url ) : ?><a class="cbv-mt-btn cbv-mt-btn--coral" href="<?php echo esc_url( $forum_url ); ?>">Go to the trip discussion</a><?php endif; ?>
				<?php else : $item = $checklist[ $next_i ]; ?>
					<p class="cbv-mt-eyebrow">Your next step · <?php echo (int) ( $next_i + 1 ); ?> of <?php echo count( $checklist ); ?></p>
					<h2 class="cbv-mt-next-title"><?php echo esc_html( $item['next'][0] ?? $item['title'] ); ?></h2>
					<p><?php echo esc_html( $item['next'][1] ?? $item['text'] ); ?></p>
					<?php echo $button( $item['button'] ?? array(), 'cbv-mt-btn--coral' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $button ?>
				<?php endif; ?>
			</aside>
			<?php endif; ?>
		</div>
	</div>

	<?php if ( '' !== trim( wp_strip_all_tags( $content ) ) ) : ?>
		<section class="cbv-mt-band cbv-mt-band--cream"><div class="cbv-mt-wrap cbv-mt-content"><?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- the_content output ?></div></section>
	<?php endif; ?>

	<?php if ( $on_roster ) : ?>
	<section class="cbv-mt-band cbv-mt-band--cream" aria-labelledby="cbv-mt-checklist-title">
		<div class="cbv-mt-wrap">
			<p class="cbv-mt-eyebrow">01 · Before you board</p>
			<h2 class="cbv-mt-h2" id="cbv-mt-checklist-title">Your trip checklist</h2>
			<div class="cbv-mt-progress" aria-hidden="true">
				<?php foreach ( $checklist as $item ) : ?><span class="is-<?php echo esc_attr( $item['status'] ); ?>"></span><?php endforeach; ?>
			</div>
			<p class="cbv-mt-progress-label"><?php echo (int) $done; ?> of <?php echo count( $checklist ); ?> done<?php echo $waiting ? ' · ' . (int) $waiting . ' waiting for your forms' : ''; ?></p>
			<div class="cbv-mt-checks">
				<?php foreach ( $checklist as $n => $item ) : ?>
					<article class="cbv-mt-check is-<?php echo esc_attr( $item['status'] ); ?>">
						<div class="cbv-mt-check-top"><span class="cbv-mt-check-num"><?php echo esc_html( sprintf( '%02d', $n + 1 ) ); ?></span><span class="cbv-mt-status is-<?php echo esc_attr( $item['status'] ); ?>"><?php echo esc_html( array( 'done' => 'DONE', 'waiting' => 'WAITING', 'todo' => 'TO DO' )[ $item['status'] ] ); ?></span></div>
						<h3><?php echo esc_html( $item['title'] ); ?></h3>
						<p><?php echo esc_html( $item['text'] ); ?><?php if ( ! empty( $item['link'] ) ) : ?><a href="<?php echo esc_url( $item['link'][1] ); ?>"><?php echo esc_html( $item['link'][0] ); ?></a><?php endif; ?></p>
						<?php echo $button( $item['button'] ?? array(), 'done' === $item['status'] ? '' : ( 'waiting' === $item['status'] ? 'cbv-mt-btn--outline' : 'cbv-mt-btn--ink' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $button ?>
					</article>
				<?php endforeach; ?>
			</div>
			<?php foreach ( function_exists( 'cbv_trip_updates_current' ) ? cbv_trip_updates_current( $trip_id ) : array() as $update ) : ?>
				<div class="cbv-mt-update"><span class="cbv-mt-update-icon" aria-hidden="true">!</span><div><p class="cbv-mt-eyebrow">Trip update</p><p><?php echo esc_html( $update['text'] ); ?></p></div></div>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $needs_rules ) : ?>
		<section class="cbv-mt-band cbv-mt-band--cream"><div class="cbv-mt-wrap" id="cbv-trip-rules-prompt">
			<?php
			// The itinerary PDF stays available before the rules are accepted (it helps someone still deciding).
			$pdf_url = function_exists( 'cbv_get_trip_itinerary_pdf_url' ) ? cbv_get_trip_itinerary_pdf_url( $trip_id ) : '';
			if ( $pdf_url ) :
				?>
				<p><a class="cbv-mt-btn cbv-mt-btn--outline" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener">Download Full Itinerary PDF</a></p>
			<?php endif; ?>
			<?php echo function_exists( 'cbv_render_trip_agreement_prompt' ) ? cbv_render_trip_agreement_prompt( $trip_id ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
		</div></section>
	<?php else : ?>
		<?php echo cbv_mt_sections( $trip_id, $viewer_id, $on_roster, $labels, $is_cruise, $ports, $forum_url ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
	<?php endif; ?>

	<section class="cbv-mt-band cbv-mt-band--cream cbv-mt-end">
		<div class="cbv-mt-wrap">
			<?php if ( $on_roster && function_exists( 'cbv_render_traveler_intake_form' ) ) : ?>
				<?php echo cbv_render_traveler_intake_form( $trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
			<?php endif; ?>
			<?php if ( ! $needs_rules ) : ?>
				<?php echo cbv_mt_end_cards( $trip_id, $viewer_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
			<?php elseif ( function_exists( 'cbv_render_who_does_what' ) ) : // the disclosure is on every trip page, the rules screen included ?>
				<div class="cbv-mt-who"><?php echo cbv_render_who_does_what( 'page' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></div>
			<?php endif; ?>
		</div>
	</section>

	</div>
	<?php echo cbv_mt_invite_script(); // phpcs:ignore WordPress.Security.EscapeOutput -- script ?>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   4. Sections 02-06 (shown once the trip rules are accepted)
   ========================================================================== */
function cbv_mt_sections( $trip_id, $viewer_id, $on_roster, $labels, $is_cruise, $ports, $forum_url ) {
	$on = function ( $key ) use ( $trip_id ) {
		return ! function_exists( 'cbv_lp_section_enabled' ) || cbv_lp_section_enabled( $trip_id, $key );
	};
	$start = (string) get_post_meta( $trip_id, 'cb_start_date', true );
	ob_start();

	// 02 · Key dates
	$rows = $on( 'timeline' ) && function_exists( 'cbv_kd_rows' ) ? cbv_kd_rows( $trip_id ) : array();
	if ( $rows ) :
		?>
		<section class="cbv-mt-band cbv-mt-band--navy" aria-labelledby="cbv-mt-dates-title">
			<div class="cbv-mt-wrap">
				<p class="cbv-mt-eyebrow">02 · Countdown to boarding</p>
				<h2 class="cbv-mt-h2" id="cbv-mt-dates-title">Key dates</h2>
				<ol class="cbv-mt-timeline">
					<?php foreach ( $rows as $row ) : ?>
						<li class="cbv-mt-tl<?php echo 'coral' === ( $row['dot'] ?? '' ) ? ' is-coral' : ''; ?><?php echo ! empty( $row['passed'] ) ? ' is-passed' : ''; ?>">
							<span class="cbv-mt-tl-dot" aria-hidden="true"></span>
							<span class="cbv-mt-tl-date"><?php echo esc_html( $row['when'] ); ?><?php echo ! empty( $row['passed'] ) ? ' · Passed' : ''; ?></span>
							<span class="cbv-mt-tl-title"><?php echo esc_html( $row['title'] ); ?></span>
							<?php if ( '' !== trim( (string) $row['description'] ) ) : ?><span class="cbv-mt-tl-desc"><?php echo cbv_mt_text( $row['description'], $trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in cbv_mt_text ?></span><?php endif; ?>
						</li>
					<?php endforeach; ?>
					<?php if ( $start ) : ?>
						<li class="cbv-mt-tl is-sail"><span class="cbv-mt-tl-dot" aria-hidden="true"></span><span class="cbv-mt-tl-date"><?php echo esc_html( cbv_mt_date( $start, 'F j, Y' ) ); ?></span><span class="cbv-mt-tl-title"><?php echo esc_html( $is_cruise && $ports ? 'We sail from ' . cbv_mt_place( $ports[0] ) : 'The trip begins' ); ?></span></li>
					<?php endif; ?>
				</ol>
			</div>
		</section>
		<?php
	endif;

	// 03 · The route
	$itinerary = function_exists( 'cb_trip_get_itinerary' ) ? cb_trip_get_itinerary( $trip_id ) : array();
	$days      = ( $itinerary && function_exists( 'cbv_lp_route_days' ) ) ? cbv_lp_route_days( $itinerary, $start ) : array();
	$pdf_url   = function_exists( 'cbv_get_trip_itinerary_pdf_url' ) ? cbv_get_trip_itinerary_pdf_url( $trip_id ) : '';
	if ( $days || $pdf_url ) :
		$places = array_values( array_unique( array_filter( array_map( 'cbv_mt_place', $ports ) ) ) );
		?>
		<section class="cbv-mt-band cbv-mt-band--cream" aria-labelledby="cbv-mt-route-title">
			<div class="cbv-mt-wrap">
				<p class="cbv-mt-eyebrow">03 · The route</p>
				<h2 class="cbv-mt-h2" id="cbv-mt-route-title"><?php echo esc_html( $places ? implode( ', ', $places ) : ( $labels['sec_route'] ?? 'The route' ) ); ?></h2>
				<?php if ( $pdf_url ) : ?><p><a class="cbv-mt-btn cbv-mt-btn--outline" href="<?php echo esc_url( $pdf_url ); ?>" target="_blank" rel="noopener">Download Full Itinerary PDF</a></p><?php endif; ?>
				<?php if ( $days ) : ?>
				<ol class="cbv-mt-route">
					<?php foreach ( $days as $day ) :
						$first = $day['rows'][0] ?? array();
						$last  = end( $day['rows'] );
						$times = array();
						foreach ( $day['rows'] as $r ) {
							if ( '' !== (string) ( $r['time'] ?? '' ) ) {
								$times[] = date_i18n( 'g:i A', strtotime( '2000-01-01 ' . $r['time'] ) );
							}
						}
						$meta = array();
						$desc = (string) ( $first['description'] ?? '' );
						if ( in_array( $desc, array( 'Embarkation', 'Disembarkation' ), true ) ) {
							$meta[] = $desc . ( $times ? ( 'Embarkation' === $desc ? ' · departs ' : ' · arrives ' ) . $times[0] : '' );
						} elseif ( $times ) {
							$meta[] = implode( ' – ', array_unique( array( $times[0], end( $times ) ) ) );
						}
						if ( ! empty( $last['tender_mode'] ) && empty( $day['at_sea'] ) && ! in_array( $desc, array( 'Embarkation', 'Disembarkation' ), true ) ) {
							$meta[] = $last['tender_mode'];
						}
						$stop_code = (string) ( $first['stop_code'] ?? '' );
						?>
						<li class="cbv-mt-stop<?php echo ! empty( $day['at_sea'] ) ? ' is-sea' : ''; ?>">
							<div class="cbv-mt-day"><span class="cbv-mt-day-n"><?php echo esc_html( sprintf( '%02d', (int) $day['n'] ) ); ?></span><?php if ( ! empty( $day['date'] ) ) : ?><span class="cbv-mt-day-d"><?php echo esc_html( cbv_mt_date( $day['date'], 'D M j' ) ); ?></span><?php endif; ?></div>
							<div class="cbv-mt-stop-card">
								<?php if ( $stop_code && empty( $day['at_sea'] ) ) : ?><span class="cbv-mt-code"><?php echo esc_html( $stop_code ); ?></span><?php endif; ?>
								<h3><?php echo esc_html( $day['place'] ); ?></h3>
								<?php if ( $meta ) : ?><p class="cbv-mt-stop-meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></p><?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
				<?php endif; ?>
			</div>
		</section>
		<?php
	endif;

	// 04 · What's included
	$allin   = $on( 'included' ) && function_exists( 'cbv_inc_allin_parts' ) ? cbv_inc_allin_parts( $trip_id ) : array();
	$program = $on( 'included' ) && function_exists( 'cbv_inc_program' ) ? cbv_inc_program( $trip_id ) : array( 'items' => array(), 'note' => '' );
	$line    = $on( 'included' ) && function_exists( 'cbv_inc_cruise_line' ) ? cbv_inc_cruise_line( $trip_id ) : array( 'cards' => array(), 'footnote' => '' );
	if ( $allin || $program['items'] || $line['cards'] ) :
		?>
		<section class="cbv-mt-band cbv-mt-band--paper" aria-labelledby="cbv-mt-included-title">
			<div class="cbv-mt-wrap">
				<p class="cbv-mt-eyebrow">04 · In your all-in price</p>
				<h2 class="cbv-mt-h2" id="cbv-mt-included-title">What&#8217;s included</h2>
				<?php if ( $allin ) : ?>
					<ul class="cbv-mt-tickets">
						<?php foreach ( $allin as $part ) : ?>
							<li class="cbv-mt-ticket"><span class="cbv-mt-ticket-body"><span class="cbv-mt-ticket-k">Included</span><span class="cbv-mt-ticket-v"><?php echo esc_html( $part ); ?></span></span><span class="cbv-mt-ticket-stub" aria-hidden="true">&#10003;</span></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $program['items'] ) : ?>
					<div class="cbv-mt-program">
						<div><h3 class="cbv-mt-h3">The CBGV group program</h3><?php if ( '' !== $program['note'] ) : ?><p class="cbv-mt-fine"><?php echo esc_html( $program['note'] ); ?></p><?php endif; ?></div>
						<ul><?php foreach ( $program['items'] as $item ) : ?><li><?php echo cbv_mt_text( $item, $trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in cbv_mt_text ?></li><?php endforeach; ?></ul>
					</div>
				<?php endif; ?>
				<?php if ( $line['cards'] && function_exists( 'cbv_inc_cards_html' ) ) : ?>
					<details class="cbv-mt-fares">
						<?php $prov_id = function_exists( 'cbv_lp_provider_id' ) ? (int) cbv_lp_provider_id( $trip_id ) : 0; ?>
						<summary><?php echo esc_html( 'What each ' . ( $prov_id ? html_entity_decode( get_the_title( $prov_id ), ENT_QUOTES, 'UTF-8' ) . ' ' : '' ) . 'fare includes' ); ?></summary>
						<div class="cbv-inc-member"><?php echo cbv_inc_cards_html( $line['cards'], $trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></div>
						<?php if ( '' !== $line['footnote'] ) : ?><p class="cbv-mt-fine"><?php echo esc_html( $line['footnote'] ); ?></p><?php endif; ?>
					</details>
				<?php endif; ?>
			</div>
		</section>
		<?php
	endif;

	// 05 · Pack list (+ old Packing Notes only when there's no pack list) + fine print
	$pack     = $on( 'pack' ) && function_exists( 'cbv_inc_pack' ) ? cbv_inc_pack( $trip_id ) : array( 'tags' => array(), 'leave' => array(), 'forecast' => '' );
	$notes    = ( ! $pack['tags'] ) ? trim( (string) get_post_meta( $trip_id, 'cb_packing_notes', true ) ) : '';
	$docs     = $on( 'docs' ) && function_exists( 'cbv_kd_docs' ) ? cbv_kd_docs( $trip_id ) : array();
	if ( $pack['tags'] || $pack['leave'] || '' !== $notes || $docs ) :
		?>
		<section class="cbv-mt-band cbv-mt-band--cream" aria-labelledby="cbv-mt-pack-title">
			<div class="cbv-mt-wrap">
				<p class="cbv-mt-eyebrow">05 · Pack like a pro</p>
				<h2 class="cbv-mt-h2" id="cbv-mt-pack-title">What to pack</h2>
				<?php if ( '' !== (string) $pack['forecast'] ) : ?><p class="cbv-mt-forecast"><?php echo esc_html( $pack['forecast'] ); ?></p><?php endif; ?>
				<?php if ( $pack['tags'] ) : ?>
					<div class="cbv-mt-packs">
						<?php foreach ( $pack['tags'] as $tag ) : ?>
							<div class="cbv-mt-pack"><p class="cbv-mt-pack-k"><?php echo esc_html( $tag['category'] ); ?></p><h3><?php echo esc_html( $tag['title'] ); ?></h3>
								<ul><?php foreach ( (array) $tag['items'] as $item ) : ?><li><?php echo cbv_mt_text( $item, $trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in cbv_mt_text ?></li><?php endforeach; ?></ul></div>
						<?php endforeach; ?>
					</div>
				<?php elseif ( '' !== $notes ) : ?>
					<div class="cbv-mt-card"><h3 class="cbv-mt-h3">Packing notes</h3><p><?php echo nl2br( esc_html( $notes ) ); ?></p></div>
				<?php endif; ?>
				<?php if ( $pack['leave'] ) : ?>
					<div class="cbv-mt-leave"><h3>Leave at home</h3>
						<ul><?php foreach ( $pack['leave'] as $item ) : ?><li><?php echo cbv_mt_text( $item, $trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in cbv_mt_text ?></li><?php endforeach; ?></ul>
						<?php if ( ! empty( $pack['link'] ) ) : ?><a href="<?php echo esc_url( $pack['link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $pack['link_label'] ?? 'Full prohibited items list' ); ?></a><?php endif; ?>
					</div>
				<?php endif; ?>
				<?php if ( $docs && function_exists( 'cbv_kd_docs_html' ) ) : ?>
					<details class="cbv-mt-fineprint">
						<summary>The fine print, folded neatly</summary>
						<?php if ( function_exists( 'cbv_kd_docs_lede' ) ) : ?><p class="cbv-mt-fine"><?php echo esc_html( cbv_kd_docs_lede( $trip_id ) ); ?></p><?php endif; ?>
						<div class="cbv-kd-member"><?php echo cbv_kd_docs_html( $trip_id, $docs ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></div>
					</details>
				<?php endif; ?>
			</div>
		</section>
		<?php
	endif;

	// 06 · Who's going (members only: photo or initials, first name + last initial)
	$roster = function_exists( 'cb_trip_get_roster' ) ? array_values( array_filter( cb_trip_get_roster( $trip_id ), 'get_userdata' ) ) : array();
	$spots  = function_exists( 'cb_trip_spots_remaining' ) ? cb_trip_spots_remaining( $trip_id ) : null;
	?>
	<section class="cbv-mt-band cbv-mt-band--coral" aria-labelledby="cbv-mt-crew-title">
		<div class="cbv-mt-wrap cbv-mt-crew">
			<div>
				<p class="cbv-mt-eyebrow">06 · Your crew</p>
				<h2 class="cbv-mt-h2" id="cbv-mt-crew-title">Who&#8217;s going</h2>
				<?php if ( $roster ) : ?>
					<ul class="cbv-mt-avatars">
						<?php foreach ( $roster as $uid ) :
							$name  = cbv_mt_short_name( $uid );
							$photo = cbv_mt_photo_url( $uid );
							?>
							<li class="cbv-mt-person">
								<?php if ( $photo ) : ?>
									<img class="cbv-mt-avatar" src="<?php echo esc_url( $photo ); ?>" alt="" width="58" height="58" loading="lazy">
								<?php else : ?>
									<span class="cbv-mt-avatar" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( $name, 0, 1 ) ) ); ?></span>
								<?php endif; ?>
								<span class="cbv-mt-person-name"><?php echo esc_html( $name ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<p class="cbv-mt-crew-count"><?php echo esc_html( count( $roster ) . ' ' . ( 1 === count( $roster ) ? 'traveler' : 'travelers' ) . ' so far' . ( null !== $spots ? ' · ' . $spots . ' ' . ( 1 === $spots ? 'spot' : 'spots' ) . ' open' : '' ) ); ?></p>
				<?php if ( function_exists( 'cb_trip_meets_minimum_group_size' ) && ! cb_trip_meets_minimum_group_size( $trip_id ) ) : ?>
					<p class="cbv-mt-crew-count">This trip needs a few more people before it&#8217;s confirmed.</p>
				<?php endif; ?>
			</div>
			<div class="cbv-mt-crew-actions">
				<?php if ( $forum_url ) : ?><a class="cbv-mt-btn cbv-mt-btn--ink" href="<?php echo esc_url( $forum_url ); ?>">Discuss this trip</a><?php endif; ?>
				<?php if ( $on_roster ) : ?>
					<button type="button" class="cbv-mt-btn cbv-mt-btn--outline cbv-mt-copy-invite" data-trip-id="<?php echo (int) $trip_id; ?>">Copy my invite link</button>
					<span class="cbv-mt-invite-result" role="status" aria-live="polite"></span>
					<input type="text" class="cbv-mt-invite-url" readonly hidden aria-label="Your invite link">
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   5. After the travel details card: request details, rules, who does what,
      questions.
   ========================================================================== */
function cbv_mt_end_cards( $trip_id, $viewer_id ) {
	ob_start();
	?>
	<div class="cbv-mt-end-grid">
		<?php if ( function_exists( 'cbv_render_trip_agreement_summary' ) ) : ?>
			<details class="cbv-mt-card cbv-mt-rules">
				<summary><span class="cbv-mt-h3">Rules for this trip</span></summary>
				<?php echo cbv_render_trip_agreement_summary( $trip_id, $viewer_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
			</details>
		<?php endif; ?>
		<?php
		$who = function_exists( 'cbv_render_who_does_what' ) ? cbv_render_who_does_what( 'page' ) : '';
		if ( '' !== trim( wp_strip_all_tags( $who ) ) ) :
			?>
			<div class="cbv-mt-who"><?php echo $who; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></div>
		<?php endif; ?>
	</div>
	<?php echo cbv_mt_request_details( $trip_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
	<div class="cbv-mt-card cbv-mt-questions">
		<div><h3 class="cbv-mt-h3">Questions?</h3><p>We&#8217;re here to help with anything about this trip.</p></div>
		<a class="cbv-mt-btn cbv-mt-btn--coral" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact us</a>
	</div>
	<?php
	return ob_get_clean();
}

/** "Your Request Details" for member-built trips (unchanged content, moved here). */
function cbv_mt_request_details( $trip_id ) {
	if ( 'member_built' !== get_post_meta( $trip_id, 'cb_source', true ) || ! function_exists( 'cbv_get_trip_request_field' ) ) {
		return '';
	}
	$f    = function ( $key ) use ( $trip_id ) { return cbv_get_trip_request_field( $trip_id, $key ); };
	$list = function ( $key ) use ( $trip_id ) { return implode( ', ', (array) cbv_get_trip_request_field( $trip_id, $key, array() ) ); };
	$rows = array_filter( array(
		'Group dynamic'          => $f( 'group_dynamic' ),
		'Rooming preference'     => $f( 'rooming' ),
		'Trip category'          => $list( 'trip_category' ),
		'Trip elements'          => $list( 'transport_modes' ),
		'Departure city'         => $f( 'origin_city' ),
		'Budget target'          => $f( 'budget_tier' ),
		'Accommodation'          => $f( 'accommodation_type' ),
		'Pace'                   => $f( 'pace' ),
		'Occasion'               => $f( 'occasion' ),
		'Must-haves'             => $f( 'must_haves' ),
		'Dietary'                => $f( 'dietary' ),
		'Mobility/access'        => $f( 'mobility' ),
		'Airline preference'     => $f( 'airline_preference' ),
		'Seat preference'        => $list( 'seat_preference' ),
		'Cruise preferences'     => $f( 'cruise_preferences' ),
		'Cruise itinerary'       => $f( 'cruise_itinerary' ),
		'Cruise length'          => $f( 'cruise_length' ),
		'Cruise cabin class'     => $f( 'cruise_cabin_class' ),
		'Beverage plan'          => $f( 'beverage_plan' ),
		'Hotel # of nights'      => $f( 'hotel_nights' ),
		'Hotel preferences'      => $f( 'hotel_preferences' ),
		'Hotel room type'        => $list( 'hotel_room_type' ),
		'Hotel features'         => $list( 'hotel_features' ),
		'Car preferences'        => $f( 'car_preferences' ),
		'Car category'           => $list( 'car_category' ),
		'Package tour countries' => $f( 'package_countries' ),
		'Package tour style'     => $list( 'package_style' ),
	) );
	if ( ! $rows ) {
		return '';
	}
	$out = '<details class="cbv-mt-card cbv-mt-request"><summary><span class="cbv-mt-h3">Your Request Details</span></summary><ul class="request-detail-list">';
	foreach ( $rows as $label => $value ) {
		$out .= '<li><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</li>';
	}
	return $out . '</ul></details>';
}

/** "Copy my invite link" in Who's going: the same personal link as My Passport. */
function cbv_mt_invite_script() {
	ob_start();
	?>
	<script>
	( function () {
		document.querySelectorAll( '.cbv-mt-copy-invite' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var wrap   = btn.parentNode;
				var result = wrap.querySelector( '.cbv-mt-invite-result' );
				var urlBox = wrap.querySelector( '.cbv-mt-invite-url' );
				btn.disabled = true;
				fetch( <?php echo wp_json_encode( esc_url_raw( rest_url( 'cb/v1/' ) ) ); ?> + 'trips/' + btn.getAttribute( 'data-trip-id' ) + '/invite-link', {
					method: 'POST',
					headers: { 'X-WP-Nonce': <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?> }
				} )
					.then( function ( res ) { return res.json(); } )
					.then( function ( data ) {
						btn.disabled = false;
						if ( ! data || ! data.url ) {
							result.textContent = ( data && data.message ) ? data.message : 'Could not get your invite link.';
							return;
						}
						urlBox.value = data.url;
						var shown = function () {
							urlBox.hidden = false;
							urlBox.focus();
							urlBox.select();
							result.textContent = 'Copy the link below.';
						};
						if ( navigator.clipboard && window.isSecureContext ) {
							navigator.clipboard.writeText( data.url ).then( function () {
								result.textContent = 'Link copied. Paste it into a text or email.';
							}, shown );
						} else {
							shown();
						}
					} )
					.catch( function () {
						btn.disabled = false;
						result.textContent = 'Something went wrong. Please try again.';
					} );
			} );
		} );
	} )();
	</script>
	<?php
	return ob_get_clean();
}
