<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Phase 11: QR Code Generation
 * Description: Shared helper for generating scannable QR code images,
 *              entirely server-side (chillerlan/php-qrcode 6.x, installed
 *              via Composer -- see wp-content/composer.json). Deliberately
 *              not a third-party API: personal invite links identify both
 *              the trip and the inviting member, so routing them through an
 *              external QR-generation service would leak that pairing to a
 *              third party's logs. Verified against this server before
 *              adoption -- PHP 8.2, Composer available, GD present
 *              (Imagick is not), ~680KB vendor footprint, and the generated
 *              PNG was independently decoded back to its exact original
 *              string using a completely separate decoder (OpenCV,
 *              confirming genuine scannability, not just visual QR shape).
 *              Used by the trip edit screen (public join-code QR) and the
 *              Dashboard (personal invite-link QR).
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-qr.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WP_CONTENT_DIR . '/vendor/autoload.php';

/**
 * Returns a data URI (data:image/png;base64,...) for a QR code encoding
 * $data, ready to drop straight into an <img src="..."> -- no file written
 * to disk, nothing to clean up. Renders via GD (confirmed available on this
 * server; Imagick is not) through chillerlan/php-qrcode's QRGdImagePNG
 * output backend. Returns '' if the library somehow isn't loaded, so a
 * missing/broken vendor install fails quiet (no QR shown) rather than
 * fatal-erroring a whole admin screen or REST response.
 */
function cb_generate_qr_data_uri( $data ) {
	if ( ! class_exists( '\chillerlan\QRCode\QRCode' ) ) {
		return '';
	}

	$options = new \chillerlan\QRCode\QROptions( array(
		'outputInterface' => \chillerlan\QRCode\Output\QRGdImagePNG::class,
		'eccLevel'         => \chillerlan\QRCode\Common\EccLevel::M,
		'scale'            => 6,
	) );

	return ( new \chillerlan\QRCode\QRCode( $options ) )->render( (string) $data );
}
