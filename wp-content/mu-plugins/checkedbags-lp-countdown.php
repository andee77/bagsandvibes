<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Countdown (key dates) + Travel documents
 * Description: Draws the merged key dates (provider + trip, from
 *              checkedbags-lp-providers.php) as the "Countdown" section and the
 *              provider's (+ trip's) travel documents as fold-open panels:
 *              on the new trip landing design (after How to book) and on the
 *              members' trip page (after "Rules for this trip", only once the
 *              trip agreement is accepted -- gate07 only draws trip details
 *              then). Owner decisions 2026-10-14: past dates dimmed with a
 *              "Passed" tag; heading "The fine print, folded neatly"; lede
 *              "Key {provider} policies, in short. The cruise line's own
 *              terms always apply."; "Last reviewed" dates admin-only.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-lp-countdown.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Data.
   ========================================================================== */
/** Today (site time) as Y-m-d; filterable so tests can move "today". */
function cbv_kd_today() {
	return (string) apply_filters( 'cbv_kd_today', current_time( 'Y-m-d' ) );
}

/** The trip's countdown rows (oldest first), each with 'passed' when its date is before today. */
function cbv_kd_rows( $trip_id ) {
	if ( ! function_exists( 'cbv_lp_timeline_rows' ) ) {
		return array();
	}
	$today = cbv_kd_today();
	return array_map( function ( $r ) use ( $today ) {
		$r['passed'] = '' !== $r['sort'] && $r['sort'] < $today;
		return $r;
	}, cbv_lp_timeline_rows( $trip_id ) );
}

/** The trip's travel documents: the provider's first, then the trip's own. */
function cbv_kd_docs( $trip_id ) {
	return function_exists( 'cbv_lp_group' ) ? (array) cbv_lp_group( $trip_id, 'docs' ) : array();
}

/** "Key Virgin Voyages policies, in short. The cruise line's own terms always apply." / "Key policies, in short." */
function cbv_kd_docs_lede( $trip_id ) {
	$name = function_exists( 'cbv_lp_provider_name' ) ? cbv_lp_provider_name( $trip_id ) : '';
	return '' !== $name ? 'Key ' . $name . ' policies, in short. The cruise line\'s own terms always apply.' : 'Key policies, in short.';
}

/* ==========================================================================
   2. Shared markup (both pages; the page's own CSS styles it).
   ========================================================================== */
function cbv_kd_rows_html( $rows ) {
	ob_start();
	?>
	<ol class="cbv-kd-list">
		<?php foreach ( $rows as $r ) : ?>
			<li class="cbv-kd-row<?php echo $r['passed'] ? ' is-passed' : ''; ?>">
				<span class="cbv-kd-dot cbv-kd-dot--<?php echo esc_attr( $r['dot'] ? $r['dot'] : 'gold' ); ?>" aria-hidden="true"></span>
				<span class="cbv-kd-when"><?php echo esc_html( $r['when'] ); ?><?php if ( $r['passed'] ) : ?> <span class="cbv-kd-passed">Passed</span><?php endif; ?></span>
				<span class="cbv-kd-title"><?php echo esc_html( $r['title'] ); ?></span>
				<?php if ( '' !== trim( (string) $r['description'] ) ) : ?>
					<span class="cbv-kd-desc"><?php echo esc_html( $r['description'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php
	return ob_get_clean();
}

function cbv_kd_docs_html( $trip_id, $docs ) {
	ob_start();
	?>
	<div class="cbv-kd-docs">
		<?php foreach ( $docs as $d ) : ?>
			<details class="cbv-kd-doc">
				<summary><span class="cbv-kd-doc-title"><?php echo esc_html( $d['title'] ); ?></span><span class="cbv-kd-chev" aria-hidden="true"></span></summary>
				<div class="cbv-kd-doc-body"><?php echo function_exists( 'cbv_lp_blocks' ) ? cbv_lp_blocks( $d['body'], $trip_id ) : wpautop( esc_html( $d['body'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- cbv_lp_blocks escapes ?></div>
			</details>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   3. New design sections.
   ========================================================================== */
function cbv_lp_render_countdown( $trip_id ) {
	if ( ! function_exists( 'cbv_lp_section_enabled' ) || ! cbv_lp_section_enabled( $trip_id, 'timeline' ) ) {
		return '';
	}
	$rows = cbv_kd_rows( $trip_id );
	if ( ! $rows ) {
		return '';
	}
	$labels = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
	$gate   = function_exists( 'cbv_lp_gate_label' ) ? cbv_lp_gate_label( $labels['sec_timeline'] ?? 'Countdown' ) : '';
	ob_start();
	?>
	<section class="cbv-lp-countdown" id="countdown" aria-labelledby="cbv-lp-countdown-title">
		<div class="cbv-lp-inner cbv-lp-countdown-inner">
			<div class="cbv-lp-countdown-head">
				<p class="cbv-lp-gate"><?php echo esc_html( $gate ); ?></p>
				<h2 class="cbv-lp-h2" id="cbv-lp-countdown-title"><?php echo esc_html( $labels['timeline_heading'] ?? 'Countdown to boarding' ); ?></h2>
			</div>
			<?php echo cbv_kd_rows_html( $rows ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

function cbv_lp_render_docs( $trip_id ) {
	if ( ! function_exists( 'cbv_lp_section_enabled' ) || ! cbv_lp_section_enabled( $trip_id, 'docs' ) ) {
		return '';
	}
	$docs = cbv_kd_docs( $trip_id );
	if ( ! $docs ) {
		return '';
	}
	$labels = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
	$gate   = function_exists( 'cbv_lp_gate_label' ) ? cbv_lp_gate_label( $labels['sec_docs'] ?? 'Travel documents' ) : '';
	ob_start();
	?>
	<section class="cbv-lp-docs" id="docs" aria-labelledby="cbv-lp-docs-title">
		<div class="cbv-lp-inner cbv-lp-docs-inner">
			<div class="cbv-lp-docs-head">
				<p class="cbv-lp-gate"><?php echo esc_html( $gate ); ?></p>
				<h2 class="cbv-lp-h2" id="cbv-lp-docs-title">The fine print, folded neatly</h2>
				<p class="cbv-lp-docs-lede"><?php echo esc_html( cbv_kd_docs_lede( $trip_id ) ); ?></p>
			</div>
			<?php echo cbv_kd_docs_html( $trip_id, $docs ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
   4. Members' trip page (gate07 calls this inside the accepted-agreement
      trip details, after "Rules for this trip"). Same switches as the new
      design: a section switched off there is off here too.
   ========================================================================== */
function cbv_render_member_countdown_docs( $trip_id ) {
	$out  = '';
	$rows = function_exists( 'cbv_lp_section_enabled' ) && cbv_lp_section_enabled( $trip_id, 'timeline' ) ? cbv_kd_rows( $trip_id ) : array();
	if ( $rows ) {
		$labels = function_exists( 'cbv_lp_labels' ) ? cbv_lp_labels( $trip_id ) : array();
		$out   .= '<div class="trip-detail-section cbv-kd-member cbv-kd-member--countdown"><h3>' . esc_html( $labels['timeline_heading'] ?? 'Key dates' ) . '</h3>' . cbv_kd_rows_html( $rows ) . '</div>';
	}
	$docs = function_exists( 'cbv_lp_section_enabled' ) && cbv_lp_section_enabled( $trip_id, 'docs' ) ? cbv_kd_docs( $trip_id ) : array();
	if ( $docs ) {
		$out .= '<div class="trip-detail-section cbv-kd-member cbv-kd-member--docs"><h3>The fine print, folded neatly</h3><p class="cbv-kd-lede">' . esc_html( cbv_kd_docs_lede( $trip_id ) ) . '</p>' . cbv_kd_docs_html( $trip_id, $docs ) . '</div>';
	}
	return $out;
}
