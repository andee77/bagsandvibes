<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Exchange Rates
 * Description: Live currency exchange rate display for the Payment page
 *              (Section 6 of the trip-invite build's post-launch list) --
 *              a deliberate, approved exception to this build's usual
 *              "avoid third-party remote dependencies" pattern (unlike
 *              QR/PDF generation, live rate data genuinely can't be done
 *              locally). Frankfurter (ECB reference rates, no API key) is
 *              the primary source; ExchangeRate-API's open-access endpoint
 *              (also no key) is the fallback if Frankfurter is unreachable.
 *              Cached in a transient so this hits neither service more
 *              than roughly once a day regardless of site traffic, and
 *              degrades to the last successfully fetched rates (marked
 *              stale) rather than showing nothing if both are down.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-exchange-rates.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Currencies relevant to common cruise/travel ports (Caribbean, Mexico,
// Europe) -- adjust freely, this list only affects which columns display.
if ( ! defined( 'CBV_EXCHANGE_RATE_CURRENCIES' ) ) {
	define( 'CBV_EXCHANGE_RATE_CURRENCIES', array( 'CAD', 'EUR', 'GBP', 'MXN' ) );
}

/**
 * Frankfurter's v2 API (confirmed directly against the live endpoint, not
 * assumed from docs) returns a flat JSON array of independent {date, base,
 * quote, rate} records via ?quotes=, NOT a single object with a nested
 * rates dict the way the older v1/frankfurter.app API did -- each quote
 * currency can even carry its own most-recent-business-day date. Folded
 * into this function's own {base, rates, date, source} shape so the rest
 * of this file doesn't need to know which provider it came from.
 */
function cbv_fetch_exchange_rates_from_frankfurter() {
	$quotes   = implode( ',', CBV_EXCHANGE_RATE_CURRENCIES );
	$response = wp_remote_get( 'https://api.frankfurter.dev/v2/rates?base=USD&quotes=' . rawurlencode( $quotes ), array( 'timeout' => 8 ) );

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$records = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $records ) || ! is_array( $records ) ) {
		return null;
	}

	$rates    = array();
	$latest   = '';
	foreach ( $records as $record ) {
		if ( empty( $record['quote'] ) || ! isset( $record['rate'] ) ) {
			continue;
		}
		$rates[ $record['quote'] ] = (float) $record['rate'];
		if ( ! empty( $record['date'] ) && $record['date'] > $latest ) {
			$latest = $record['date'];
		}
	}
	if ( empty( $rates ) ) {
		return null;
	}

	return array(
		'base'   => 'USD',
		'rates'  => $rates,
		'date'   => $latest ?: current_time( 'Y-m-d' ),
		'source' => 'Frankfurter (European Central Bank reference rates)',
	);
}

function cbv_fetch_exchange_rates_from_open_er_api() {
	$response = wp_remote_get( 'https://open.er-api.com/v6/latest/USD', array( 'timeout' => 8 ) );

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( ( $body['result'] ?? '' ) !== 'success' || empty( $body['rates'] ) || ! is_array( $body['rates'] ) ) {
		return null;
	}

	$rates = array();
	foreach ( CBV_EXCHANGE_RATE_CURRENCIES as $code ) {
		if ( isset( $body['rates'][ $code ] ) ) {
			$rates[ $code ] = $body['rates'][ $code ];
		}
	}
	if ( empty( $rates ) ) {
		return null;
	}

	return array(
		'base'   => 'USD',
		'rates'  => $rates,
		'date'   => isset( $body['time_last_update_utc'] ) ? gmdate( 'Y-m-d', strtotime( $body['time_last_update_utc'] ) ) : current_time( 'Y-m-d' ),
		'source' => 'ExchangeRate-API (open access)',
	);
}

/**
 * Cached exchange-rate lookup. Checks a 24-hour transient first; on a miss,
 * tries Frankfurter then falls back to ExchangeRate-API; on success, caches
 * the result AND stores it in a plain option as a "last known good" value
 * (options survive far more aggressive cache-clearing than transients can,
 * e.g. an object-cache flush). If both providers fail and there's no
 * transient, serves that last-known-good value marked stale rather than
 * showing nothing -- graceful degradation over a broken section.
 */
function cbv_get_exchange_rates() {
	$cached = get_transient( 'cbv_exchange_rates' );
	if ( false !== $cached ) {
		return $cached;
	}

	$rates = cbv_fetch_exchange_rates_from_frankfurter();
	if ( ! $rates ) {
		$rates = cbv_fetch_exchange_rates_from_open_er_api();
	}

	if ( $rates ) {
		set_transient( 'cbv_exchange_rates', $rates, DAY_IN_SECONDS );
		update_option( 'cbv_exchange_rates_last_good', $rates, false );
		return $rates;
	}

	$last_good = get_option( 'cbv_exchange_rates_last_good' );
	if ( $last_good ) {
		$last_good['stale'] = true;
		return $last_good;
	}

	return null;
}

/**
 * Renders the Payment page's Exchange Rates section: explanatory copy plus
 * the live (or gracefully degraded) rate table. Draft copy -- review before
 * treating as final, this wasn't provided verbatim like the Payment
 * Disclaimer text was.
 */
function cbv_render_exchange_rates_section() {
	$data = cbv_get_exchange_rates();

	ob_start();
	?>
	<div class="cbv-exchange-rates-section">
		<h3>Exchange Rates</h3>
		<p class="cb-page-hint">
			Your CBGV Commitment Fee and your InteleTravel Travel Payment are both charged in US dollars.
			If your card or bank account is in a different currency, your card issuer or bank converts the charge
			to your home currency automatically, using their own exchange rate at the moment of the transaction —
			which may differ slightly from the reference rate shown below. Nothing extra is required from you;
			this section is just to help you know roughly what to expect when the charge posts.
		</p>
		<?php if ( ! $data ) : ?>
			<p class="cb-empty">Exchange rates are temporarily unavailable — please check back shortly.</p>
		<?php else : ?>
			<?php if ( ! empty( $data['stale'] ) ) : ?>
				<p class="cbv-exchange-rates-stale">Live rates are temporarily unavailable — showing the last known rates as of <?php echo esc_html( $data['date'] ); ?>.</p>
			<?php endif; ?>
			<table class="cbv-exchange-rates-table">
				<thead>
					<tr>
						<th>1 USD =</th>
						<?php foreach ( array_keys( $data['rates'] ) as $code ) : ?>
							<th><?php echo esc_html( $code ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>Rate</td>
						<?php foreach ( $data['rates'] as $rate ) : ?>
							<td><?php echo esc_html( number_format( (float) $rate, 4 ) ); ?></td>
						<?php endforeach; ?>
					</tr>
				</tbody>
			</table>
			<p class="cbv-exchange-rates-meta">As of <?php echo esc_html( $data['date'] ); ?> — source: <?php echo esc_html( $data['source'] ); ?>.</p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
