<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Phase 9: Trip Roster Export
 * Description: Per-trip spreadsheet export -- one .xlsx per trip, one row
 *              per roster member, pulling together everything captured in
 *              Phase 8 (Member Profile, Trip Request, Per-Traveler Intake)
 *              plus Gate 09 payment status. Also flags any traveler whose
 *              passport expires within 6 months of the trip's end date,
 *              both in the export (highlighted cell) and as a notice on
 *              the trip's own edit screen -- never as an automatic email.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-roster-export.php
 *
 * No Composer/PhpSpreadsheet dependency -- .xlsx is just a zip of a few
 * small XML files, and the only formatting this needs is a bold header
 * row and one highlighted-cell style, so this writes that zip directly
 * with PHP's built-in ZipArchive rather than pull in a large external
 * library for a handful of features.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Minimal .xlsx writer -- single sheet, inline strings (no shared
      strings table needed), one bold header style and one highlight fill.
   ========================================================================== */
function cbv_xlsx_escape( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_XML1, 'UTF-8' );
}

function cbv_xlsx_col_letter( $index ) {
	$letter = '';
	$index++;
	while ( $index > 0 ) {
		$mod    = ( $index - 1 ) % 26;
		$letter = chr( 65 + $mod ) . $letter;
		$index  = intval( ( $index - $mod ) / 26 );
	}
	return $letter;
}

/**
 * @param string[]   $headers           Column headers, in order.
 * @param string[][] $rows              One array of cell strings per row, same order/count as $headers.
 * @param array[]    $highlighted_cells Array of [row_index, col_index] pairs (0-based, not counting the header row) to highlight.
 * @return string Raw .xlsx file bytes.
 */
function cbv_generate_xlsx( $headers, $rows, $highlighted_cells = array() ) {
	$highlight_set = array();
	foreach ( $highlighted_cells as $rc ) {
		$highlight_set[ $rc[0] . ':' . $rc[1] ] = true;
	}

	$sheet_xml  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
	$sheet_xml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

	$sheet_xml .= '<row r="1">';
	foreach ( $headers as $col_index => $header ) {
		$col_letter = cbv_xlsx_col_letter( $col_index );
		$sheet_xml .= '<c r="' . $col_letter . '1" t="inlineStr" s="1"><is><t xml:space="preserve">' . cbv_xlsx_escape( $header ) . '</t></is></c>';
	}
	$sheet_xml .= '</row>';

	foreach ( $rows as $row_index => $row ) {
		$excel_row  = $row_index + 2;
		$sheet_xml .= '<row r="' . $excel_row . '">';
		foreach ( $row as $col_index => $value ) {
			$col_letter = cbv_xlsx_col_letter( $col_index );
			$style      = isset( $highlight_set[ $row_index . ':' . $col_index ] ) ? '2' : '0';
			$sheet_xml .= '<c r="' . $col_letter . $excel_row . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . cbv_xlsx_escape( $value ) . '</t></is></c>';
		}
		$sheet_xml .= '</row>';
	}
	$sheet_xml .= '</sheetData></worksheet>';

	$content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
		. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
		. '<Default Extension="xml" ContentType="application/xml"/>'
		. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
		. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
		. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
		. '</Types>';

	$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
		. '</Relationships>';

	$workbook_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
		. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
		. '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
		. '</Relationships>';

	$workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
		. '<sheets><sheet name="Roster" sheetId="1" r:id="rId1"/></sheets></workbook>';

	$styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
		. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
		. '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
		. '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
		. '<fill><patternFill patternType="solid"><fgColor rgb="FFFFC000"/><bgColor indexed="64"/></patternFill></fill></fills>'
		. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
		. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
		. '<cellXfs count="3">'
		. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
		. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
		. '<xf numFmtId="0" fontId="0" fillId="2" borderId="0" xfId="0" applyFill="1"/>'
		. '</cellXfs></styleSheet>';

	$tmp_path = wp_tempnam( 'cbv-roster-export' );

	$zip = new ZipArchive();
	$zip->open( $tmp_path, ZipArchive::OVERWRITE );
	$zip->addFromString( '[Content_Types].xml', $content_types );
	$zip->addFromString( '_rels/.rels', $rels );
	$zip->addFromString( 'xl/workbook.xml', $workbook );
	$zip->addFromString( 'xl/_rels/workbook.xml.rels', $workbook_rels );
	$zip->addFromString( 'xl/styles.xml', $styles );
	$zip->addFromString( 'xl/worksheets/sheet1.xml', $sheet_xml );
	$zip->close();

	$bytes = file_get_contents( $tmp_path );
	@unlink( $tmp_path );

	return $bytes;
}

/* ==========================================================================
   2. Passport renewal check -- the standard "valid 6 months beyond return"
      rule most international destinations require.
   ========================================================================== */
function cbv_passport_needs_renewal( $passport_expiration, $trip_end_date ) {
	if ( ! $passport_expiration || ! $trip_end_date ) {
		return false;
	}
	$exp_ts = strtotime( $passport_expiration );
	$end_ts = strtotime( $trip_end_date );
	if ( ! $exp_ts || ! $end_ts ) {
		return false;
	}
	return $exp_ts < strtotime( '+6 months', $end_ts );
}

/* ==========================================================================
   3. Gather one export row per roster member.

   Some fields exist at both the trip-request level (Phase 8b, filled once
   by the organizer for the whole group) and the per-traveler level (Phase
   8c, filled individually). Seat preference and cabin class are the only
   two fields with both -- each traveler's own answer is preferred, and a
   trailing " *" marks any value that's actually the organizer's trip-wide
   answer standing in because that traveler never filled in their own, so
   it's never mistaken for a personal answer later.
   ========================================================================== */
function cbv_build_trip_roster_export_data( $trip_id ) {
	$trip = get_post( $trip_id );
	$roster = cb_trip_get_roster( $trip_id );
	$trip_end = get_post_meta( $trip_id, 'cb_end_date', true );

	$req = function ( $key ) use ( $trip_id ) {
		return function_exists( 'cbv_get_trip_request_field' ) ? cbv_get_trip_request_field( $trip_id, $key ) : '';
	};
	$req_list = function ( $key ) use ( $trip_id ) {
		return function_exists( 'cbv_get_trip_request_field' ) ? implode( ', ', (array) cbv_get_trip_request_field( $trip_id, $key, array() ) ) : '';
	};

	$trip_wide_seat_preference = $req_list( 'seat_preference' );

	// Prefers the traveler's own answer, falling back to the organizer's
	// trip-wide answer (marked with " *") when that traveler never filled
	// in their own -- same treatment as Seat Preference above and Cruise
	// Cabin Class below, for every field Per-Traveler Intake and Gate 12
	// both capture in the same domain (this excludes Flight Cabin Class,
	// which shares no option values with Gate 12's Cruise Cabin Class --
	// see the CBV_FLIGHT_CABIN_CLASSES comment in checkedbags-trip-invites.php).
	$merge = function ( $traveler_value, $trip_wide_value ) {
		if ( '' !== (string) $traveler_value ) {
			return $traveler_value;
		}
		return '' !== $trip_wide_value ? $trip_wide_value . ' *' : '';
	};
	$merge_list = function ( $traveler_list, $trip_wide_list ) {
		$traveler_str = implode( ', ', (array) $traveler_list );
		if ( '' !== $traveler_str ) {
			return $traveler_str;
		}
		return '' !== $trip_wide_list ? $trip_wide_list . ' *' : '';
	};

	$headers = array(
		// Roster / Contact
		'Name', 'First Name', 'Last Name', 'Date of Birth', 'Email', 'Phone',
		'Street Address', 'City', 'State', 'Zip Code',
		'Emergency Contact Name', 'Emergency Contact Phone',
		// Passport Status
		'Has Passport', 'Passport Country', 'Passport Expiration', 'Passport Renewal Needed',
		// Trip Role / Status
		'Role', 'Invited By', 'Membership Terms Version', 'Membership Terms Accepted Date',
		'Trip Agreement Version', 'Trip Agreement Accepted Date',
		// Financial
		'Budget Tier Requested', 'Trip Price', 'Payment Status', 'Amount Paid', 'Paid in Full',
		'Insurance Decision', 'Allianz Waiver Returned', 'Insurance Waiver Received',
		'CC Auth Completed', 'CC Auth Received',
		// Travel Logistics
		'Departure Airport', 'Airline Preference', 'Preferred Airline', 'Frequent Flyer Number',
		'Seat Preference', 'Flight Cabin Class',
		'Destinations of Interest', 'Travel Dates', 'Date Flexibility',
		'Additional Adults', 'Additional Children', "Children's Ages", 'Traveling Companions',
		// Accommodation & Trip-Type Preferences
		'Hotel Preferences', 'Hotel Room Type', 'Hotel Features',
		'Hotel Nights', 'Hotel Rooms/Arrangement', 'Hotel Concierge Notes',
		'Cruise Company', 'Cruise Program Number', 'Cruise Itinerary (Legacy)',
		'Cruise Start Date', 'Cruise End Date', 'Cruise Duration', 'Cruise Region',
		'Cruise Departure Port', 'Pre/Post Cruise Nights',
		'Cruise Cabin Class', 'Beverage Plan', 'Beverage Plan Type',
		'Car Preferences', 'Car Add-ons', 'Car Category',
		'Package Tour Countries', 'Package Tour Style', 'Package Activity Level',
		// Preferences
		'Dietary Restrictions/Allergies', 'Medical/Mobility Needs', 'Pacing Style',
		'Past Hotels/Cruiselines Enjoyed', 'Activity Interests',
	);

	$rows              = array();
	$highlighted_cells = array();

	$passport_exp_col = array_search( 'Passport Expiration', $headers, true );

	foreach ( $roster as $row_index => $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			continue;
		}

		$passport_exp = get_user_meta( $user_id, '_passport_expiration', true );
		$needs_renewal = cbv_passport_needs_renewal( $passport_exp, $trip_end );

		$inviter_id = get_user_meta( $user_id, '_invited_by_user_id', true );
		$inviter    = $inviter_id ? get_userdata( $inviter_id ) : false;

		$terms_version = get_user_meta( $user_id, '_accepted_terms_version', true );
		$terms_date    = get_user_meta( $user_id, '_accepted_terms_date', true );

		$agreement = get_user_meta( $user_id, '_trip_agreement_accepted_' . $trip_id, true );
		$agreement = is_array( $agreement ) ? $agreement : array();

		$intake = function_exists( 'cbv_get_traveler_intake' ) ? cbv_get_traveler_intake( $user_id, $trip_id ) : array();

		$price = (float) get_post_meta( $trip_id, 'cb_price', true );
		if ( function_exists( 'cb_trip_amount_paid' ) ) {
			$amount_paid  = cb_trip_amount_paid( $trip_id, $user_id );
			$balance_due  = cb_trip_balance_due( $trip_id, $user_id );
			$payment_status = ( $price > 0 && $balance_due <= 0 ) ? 'Paid in Full' : ( $amount_paid > 0 ? 'Partial' : 'Unpaid' );
		} else {
			$amount_paid    = 0;
			$payment_status = '';
		}

		$seat_preference = $intake['seat_preference'] ?? '';
		$seat_preference_marked = $seat_preference !== '' ? $seat_preference : ( $trip_wide_seat_preference !== '' ? $trip_wide_seat_preference . ' *' : '' );

		// Flight cabin class (per-traveler, Gate 07) and cruise cabin class
		// (trip-wide, Gate 12) share no option values -- see the CBV_SEAT_
		// POSITIONS/CBV_FLIGHT_CABIN_CLASSES comment in checkedbags-trip-
		// invites.php. No fallback between them; each gets its own column.
		$flight_cabin_class = $intake['flight_cabin_class'] ?? '';

		$hotel_preferences       = $merge( $intake['hotel_preferences'] ?? '', $req( 'hotel_preferences' ) );
		$hotel_room_type         = $merge_list( $intake['hotel_room_type'] ?? array(), $req_list( 'hotel_room_type' ) );
		$hotel_features          = $merge_list( $intake['hotel_features'] ?? array(), $req_list( 'hotel_features' ) );
		$hotel_nights            = $merge( $intake['hotel_nights'] ?? '', $req( 'hotel_nights' ) );
		$hotel_rooms_arrangement = $merge( $intake['hotel_rooms_arrangement'] ?? '', $req( 'hotel_rooms_arrangement' ) );
		$hotel_concierge_notes   = $merge( $intake['hotel_concierge_notes'] ?? '', $req( 'hotel_concierge_notes' ) );

		$cruise_company        = $merge( $intake['cruise_company'] ?? '', $req( 'cruise_company' ) );
		$cruise_program_number = $merge( $intake['cruise_program_number'] ?? '', $req( 'cruise_program_number' ) );

		// No longer a form input on either form (Duration/Region/Port
		// replace it) -- this only ever surfaces already-stored data now.
		$cruise_itinerary = $merge( $intake['cruise_itinerary'] ?? '', $req( 'cruise_itinerary' ) );

		$cruise_start_date     = $merge( $intake['cruise_start_date'] ?? '', $req( 'cruise_start_date' ) );
		$cruise_end_date       = $merge( $intake['cruise_end_date'] ?? '', $req( 'cruise_end_date' ) );
		$cruise_region         = $merge( $intake['cruise_region'] ?? '', $req( 'cruise_region' ) );
		$cruise_departure_port = $merge( $intake['cruise_departure_port'] ?? '', $req( 'cruise_departure_port' ) );

		// Cruise Duration replaces the old free-text Cruise Length going
		// forward (neither is a form input on either form anymore for
		// Cruise Length); if no Duration value exists at either level,
		// fall back to whatever old Cruise Length data is on file, clearly
		// marked as legacy so it's never mistaken for the new dropdown format.
		$cruise_duration = $merge( $intake['cruise_duration'] ?? '', $req( 'cruise_duration' ) );
		if ( '' === $cruise_duration ) {
			$legacy_cruise_length = $merge( $intake['cruise_length'] ?? '', $req( 'cruise_length' ) );
			if ( '' !== $legacy_cruise_length ) {
				$cruise_duration = $legacy_cruise_length . ' (legacy format)';
			}
		}

		$pre_post_cruise_nights = $merge( $intake['pre_post_cruise_nights'] ?? '', $req( 'pre_post_cruise_nights' ) );
		$cruise_cabin_class     = $merge( $intake['cruise_cabin_class'] ?? '', $req( 'cruise_cabin_class' ) );
		$beverage_plan          = $merge( $intake['beverage_plan'] ?? '', $req( 'beverage_plan' ) );
		$beverage_plan_type     = $merge( $intake['beverage_plan_type'] ?? '', $req( 'beverage_plan_type' ) );

		$car_preferences = $merge( $intake['car_preferences'] ?? '', $req( 'car_preferences' ) );
		$car_addons      = $merge( $intake['car_addons'] ?? '', $req( 'car_addons' ) );
		$car_category    = $merge_list( $intake['car_category'] ?? array(), $req_list( 'car_category' ) );

		$package_countries      = $merge( $intake['package_countries'] ?? '', $req( 'package_countries' ) );
		$package_style          = $merge_list( $intake['package_style'] ?? array(), $req_list( 'package_style' ) );
		$package_activity_level = $merge( $intake['package_activity_level'] ?? '', $req( 'package_activity_level' ) );

		$start = get_post_meta( $trip_id, 'cb_start_date', true );
		$dates = $start ? ( $start . ( $trip_end ? ' to ' . $trip_end : '' ) ) : get_post_meta( $trip_id, 'cb_when_notes', true );

		list( $first_name, $last_name ) = function_exists( 'cbv_get_member_name_parts' ) ? cbv_get_member_name_parts( $user_id ) : array( '', '' );
		list( $addr_street, $addr_city, $addr_state, $addr_zip ) = function_exists( 'cbv_get_member_address_parts' ) ? cbv_get_member_address_parts( $user_id ) : array( '', '', '', '' );

		$paid_in_full       = get_user_meta( $user_id, '_paid_in_full_' . $trip_id, true );
		$insurance_received = get_user_meta( $user_id, '_insurance_waiver_received_' . $trip_id, true );
		$cc_auth_received   = get_user_meta( $user_id, '_cc_auth_received_' . $trip_id, true );

		$row = array(
			$user->display_name,
			$first_name,
			$last_name,
			get_user_meta( $user_id, '_date_of_birth', true ),
			$user->user_email,
			get_user_meta( $user_id, '_phone', true ),
			$addr_street,
			$addr_city,
			$addr_state,
			$addr_zip,
			get_user_meta( $user_id, '_emergency_contact_name', true ),
			get_user_meta( $user_id, '_emergency_contact_phone', true ),

			get_user_meta( $user_id, '_has_passport', true ),
			get_user_meta( $user_id, '_passport_country', true ),
			$passport_exp,
			$needs_renewal ? 'Yes' : 'No',

			function_exists( 'cbv_user_is_full_member' ) && cbv_user_is_full_member( $user_id ) ? 'Full Member' : 'Trip Guest',
			$inviter ? $inviter->display_name : '',
			$terms_version,
			$terms_date,
			$agreement['version'] ?? '',
			$agreement['date'] ?? '',

			$req( 'budget_tier' ),
			$price > 0 ? number_format( $price, 2 ) : '',
			$payment_status,
			$amount_paid > 0 ? number_format( $amount_paid, 2 ) : '',
			'yes' === $paid_in_full ? 'Yes' : 'No',
			$intake['insurance_decision'] ?? '',
			! empty( $intake['allianz_waiver_returned'] ) ? 'Yes' : 'No',
			'yes' === $insurance_received ? 'Yes' : 'No',
			! empty( $intake['cc_auth_completed'] ) ? 'Yes' : 'No',
			'yes' === $cc_auth_received ? 'Yes' : 'No',

			$intake['departure_airport'] ?? '',
			$req( 'airline_preference' ),
			$intake['preferred_airline'] ?? '',
			$intake['frequent_flyer_number'] ?? '',
			$seat_preference_marked,
			$flight_cabin_class,
			$req( 'destination_pref' ),
			$dates,
			$req( 'date_flexibility' ),
			$intake['additional_adults'] ?? '',
			$intake['additional_children'] ?? '',
			$intake['children_ages'] ?? '',
			$intake['traveling_companions'] ?? '',

			$hotel_preferences,
			$hotel_room_type,
			$hotel_features,
			$hotel_nights,
			$hotel_rooms_arrangement,
			$hotel_concierge_notes,
			$cruise_company,
			$cruise_program_number,
			$cruise_itinerary,
			$cruise_start_date,
			$cruise_end_date,
			$cruise_duration,
			$cruise_region,
			$cruise_departure_port,
			$pre_post_cruise_nights,
			$cruise_cabin_class,
			$beverage_plan,
			$beverage_plan_type,
			$car_preferences,
			$car_addons,
			$car_category,
			$package_countries,
			$package_style,
			$package_activity_level,

			get_user_meta( $user_id, '_dietary_restrictions', true ),
			get_user_meta( $user_id, '_medical_mobility_needs', true ),
			$req( 'pace' ),
			get_user_meta( $user_id, '_travel_history', true ),
			implode( ', ', (array) get_user_meta( $user_id, '_activity_interests', true ) ),
		);

		$rows[] = $row;

		if ( $needs_renewal && false !== $passport_exp_col ) {
			$highlighted_cells[] = array( $row_index, $passport_exp_col );
		}
	}

	return array(
		'headers'            => $headers,
		'rows'               => $rows,
		'highlighted_cells'  => $highlighted_cells,
	);
}

/**
 * Roster members whose passport needs renewing before this trip -- used by
 * both the export highlight and the admin notice below.
 */
function cbv_trip_passport_renewal_alerts( $trip_id ) {
	$trip_end = get_post_meta( $trip_id, 'cb_end_date', true );
	$alerts   = array();

	foreach ( cb_trip_get_roster( $trip_id ) as $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			continue;
		}
		$passport_exp = get_user_meta( $user_id, '_passport_expiration', true );
		if ( cbv_passport_needs_renewal( $passport_exp, $trip_end ) ) {
			$alerts[] = array( 'name' => $user->display_name, 'expiration' => $passport_exp );
		}
	}

	return $alerts;
}

/* ==========================================================================
   4. Admin: "Export Roster" meta box + admin-post download handler.
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'cbv_roster_export', 'Export Roster', 'cbv_render_roster_export_meta_box', 'cb_trip', 'side', 'default' );
} );

function cbv_render_roster_export_meta_box( $post ) {
	$roster_count = cb_trip_get_valid_roster_count( $post->ID );

	if ( ! $roster_count ) {
		echo '<p><em>No roster members yet.</em></p>';
		return;
	}

	$alerts = cbv_trip_passport_renewal_alerts( $post->ID );
	if ( $alerts ) {
		echo '<p style="color:#b32d2e;"><strong>' . count( $alerts ) . ' traveler(s) need passport renewal before this trip.</strong> See notice above.</p>';
	}
	?>
	<p><?php echo (int) $roster_count; ?> roster member<?php echo 1 === $roster_count ? '' : 's'; ?>.</p>
	<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url(
		admin_url( 'admin-post.php?action=cbv_export_trip_roster&trip_id=' . $post->ID ),
		'cbv_export_trip_roster_' . $post->ID
	) ); ?>">Export Roster to Excel</a>
	<?php
}

add_action( 'admin_post_cbv_export_trip_roster', function () {
	$trip_id = isset( $_GET['trip_id'] ) ? (int) $_GET['trip_id'] : 0;

	if ( ! $trip_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'cbv_export_trip_roster_' . $trip_id ) ) {
		wp_die( 'Invalid request.' );
	}
	if ( ! current_user_can( 'edit_post', $trip_id ) ) {
		wp_die( 'Insufficient permissions.' );
	}

	$trip = get_post( $trip_id );
	if ( ! $trip || 'cb_trip' !== $trip->post_type ) {
		wp_die( 'Trip not found.' );
	}

	$data     = cbv_build_trip_roster_export_data( $trip_id );
	$xlsx     = cbv_generate_xlsx( $data['headers'], $data['rows'], $data['highlighted_cells'] );
	$trip_code = get_post_meta( $trip_id, 'cb_trip_code', true );
	$filename = sanitize_file_name( ( $trip_code ?: $trip->post_title ) . '-roster.xlsx' );

	nocache_headers();
	header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . strlen( $xlsx ) );
	echo $xlsx;
	exit;
} );

/* ==========================================================================
   5. Admin notice on the trip's own edit screen -- never an automatic
      email. Purely a visual flag for manual follow-up.
   ========================================================================== */
add_action( 'admin_notices', function () {
	global $pagenow, $post;

	if ( 'post.php' !== $pagenow || empty( $post ) || 'cb_trip' !== $post->post_type ) {
		return;
	}

	$alerts = cbv_trip_passport_renewal_alerts( $post->ID );
	if ( ! $alerts ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p><strong>Passport renewal needed before this trip:</strong></p>
		<ul style="margin-left:1.5em;list-style:disc;">
			<?php foreach ( $alerts as $alert ) : ?>
				<li><?php echo esc_html( $alert['name'] ); ?> — passport expires <?php echo esc_html( $alert['expiration'] ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
} );
