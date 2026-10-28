<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Invite Links
 * Description: Staff screen (Users > Invite Links) listing every personal
 *              trip invite link: trip, member who made it, date created,
 *              times used. "Turn off" stops a link working; "Make a new
 *              link" turns the old one off and gives that member a fresh
 *              one. Tokens themselves live in the cbv_invite_tokens option
 *              (checkedbags-trip-invites.php); a turned-off token stays in
 *              the list, marked 'disabled', so it keeps its history.
 * Author:      Built with Claude for JourneyWell Global LLC
 *
 * WHERE THIS FILE GOES:
 *   wp-content/mu-plugins/checkedbags-invite-links.php
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * One-time backfill: members who joined before "_invited_by_token" was
 * recorded get it from their inviter + trip, which matched exactly one
 * token each until links could be replaced.
 */
add_action( 'init', function () {
	if ( get_option( 'cbv_invite_token_backfill_done' ) ) {
		return;
	}

	$lookup = get_option( 'cbv_invite_tokens', array() );
	$users  = get_users( array(
		'meta_key' => '_invited_by_user_id',
		'fields'   => 'ID',
	) );
	foreach ( $users as $user_id ) {
		if ( get_user_meta( $user_id, '_invited_by_token', true ) ) {
			continue;
		}
		$inviter = (int) get_user_meta( $user_id, '_invited_by_user_id', true );
		$trip    = (int) get_user_meta( $user_id, '_invited_by_trip_id', true );
		$matches = array_keys( array_filter( $lookup, function ( $entry ) use ( $inviter, $trip ) {
			return (int) $entry['trip_id'] === $trip && (int) $entry['inviter_user_id'] === $inviter;
		} ) );
		if ( 1 === count( $matches ) ) {
			update_user_meta( $user_id, '_invited_by_token', $matches[0] );
		}
	}

	update_option( 'cbv_invite_token_backfill_done', true, false );
}, 40 );

add_action( 'admin_menu', function () {
	add_users_page( 'Invite Links', 'Invite Links', 'manage_options', 'cbv-invite-links', 'cbv_render_invite_links_page' );
} );

// Turn a link off, and clear it from its member so My Passport makes them a new one.
function cbv_disable_invite_token( $token ) {
	$lookup = get_option( 'cbv_invite_tokens', array() );
	if ( ! isset( $lookup[ $token ] ) ) {
		return false;
	}
	if ( empty( $lookup[ $token ]['disabled'] ) ) {
		$lookup[ $token ]['disabled'] = current_time( 'mysql' );
		update_option( 'cbv_invite_tokens', $lookup, false );
	}

	$entry    = $lookup[ $token ];
	$meta_key = 'cbv_invite_token_' . (int) $entry['trip_id'];
	if ( get_user_meta( (int) $entry['inviter_user_id'], $meta_key, true ) === $token ) {
		delete_user_meta( (int) $entry['inviter_user_id'], $meta_key );
	}
	return true;
}

add_action( 'admin_post_cbv_invite_link_action', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Not allowed.' );
	}
	$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
	$what  = sanitize_key( $_POST['what'] ?? '' );
	check_admin_referer( 'cbv_invite_link_' . $token );

	$lookup = get_option( 'cbv_invite_tokens', array() );
	$back   = array( 'page' => 'cbv-invite-links' );

	if ( ! isset( $lookup[ $token ] ) || ! in_array( $what, array( 'off', 'new' ), true ) ) {
		$back['cbv_msg'] = 'missing';
	} else {
		cbv_disable_invite_token( $token );
		$back['cbv_msg'] = 'off';

		if ( 'new' === $what ) {
			$new = cbv_get_or_create_invite_token( (int) $lookup[ $token ]['inviter_user_id'], (int) $lookup[ $token ]['trip_id'] );
			$back['cbv_msg'] = is_wp_error( $new ) ? 'new_failed' : 'new';
		}
	}

	wp_safe_redirect( add_query_arg( $back, admin_url( 'users.php' ) ) );
	exit;
} );

function cbv_render_invite_links_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$lookup = get_option( 'cbv_invite_tokens', array() );

	// token => number of members who registered through it.
	$used = array();
	foreach ( get_users( array( 'meta_key' => '_invited_by_token', 'fields' => 'ID' ) ) as $user_id ) {
		$token = get_user_meta( $user_id, '_invited_by_token', true );
		$used[ $token ] = ( $used[ $token ] ?? 0 ) + 1;
	}

	// Active links first, then newest first.
	uksort( $lookup, function ( $a, $b ) use ( $lookup ) {
		$a_off = ! empty( $lookup[ $a ]['disabled'] );
		$b_off = ! empty( $lookup[ $b ]['disabled'] );
		if ( $a_off !== $b_off ) {
			return $a_off ? 1 : -1;
		}
		return strcmp( $lookup[ $b ]['created'] ?? '', $lookup[ $a ]['created'] ?? '' );
	} );

	$messages = array(
		'off'        => array( 'success', 'Link turned off. Anyone opening it now sees "This invite link isn\'t valid or has expired." The member gets a new link next time they click Generate invite link on My Passport.' ),
		'new'        => array( 'success', 'Old link turned off and a new link made (at the top of the list). Send the new link to the member; their My Passport shows it too.' ),
		'new_failed' => array( 'warning', 'Old link turned off, but no new link was made: that member is no longer on the trip.' ),
		'missing'    => array( 'error', 'That link could not be found.' ),
	);
	$msg = sanitize_key( $_GET['cbv_msg'] ?? '' );
	?>
	<div class="wrap">
		<h1>Invite Links</h1>
		<?php if ( isset( $messages[ $msg ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $messages[ $msg ][0] ); ?>"><p><?php echo esc_html( $messages[ $msg ][1] ); ?></p></div>
		<?php endif; ?>
		<p class="description">Every personal invite link a member has made from My Passport. Someone who registers through a link becomes a Trip Guest on that trip. "Times used" counts those registrations. Links made before Oct 28, 2026 have no recorded creation date.</p>
		<table class="widefat striped">
			<thead>
				<tr><th>Trip</th><th>Made by</th><th>Created</th><th>Times used</th><th>Status</th><th>Link</th><th></th></tr>
			</thead>
			<tbody>
				<?php if ( ! $lookup ) : ?>
					<tr><td colspan="7">No invite links yet.</td></tr>
				<?php endif; ?>
				<?php foreach ( $lookup as $token => $entry ) :
					$trip_id  = (int) $entry['trip_id'];
					$inviter  = get_userdata( (int) $entry['inviter_user_id'] );
					$disabled = ! empty( $entry['disabled'] );
					$trip     = get_post( $trip_id );
					?>
					<tr>
						<td>
							<?php if ( $trip ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $trip_id ) ); ?>"><?php echo esc_html( get_the_title( $trip ) ); ?></a>
							<?php else : ?>
								<em>Deleted trip #<?php echo (int) $trip_id; ?></em>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $inviter ) : ?>
								<a href="<?php echo esc_url( get_edit_user_link( $inviter->ID ) ); ?>"><?php echo esc_html( $inviter->display_name ); ?></a>
							<?php else : ?>
								<em>Deleted member #<?php echo (int) $entry['inviter_user_id']; ?></em>
							<?php endif; ?>
						</td>
						<td><?php echo ! empty( $entry['created'] ) ? esc_html( date_i18n( 'M j, Y', strtotime( $entry['created'] ) ) ) : '<em>Not recorded</em>'; ?></td>
						<td><?php echo (int) ( $used[ $token ] ?? 0 ); ?></td>
						<td><?php echo $disabled ? esc_html( 'Turned off ' . date_i18n( 'M j, Y', strtotime( $entry['disabled'] ) ) ) : '<strong>Active</strong>'; ?></td>
						<td>
							<?php if ( ! $disabled ) : ?>
								<input type="text" readonly value="<?php echo esc_attr( add_query_arg( 'invite', $token, home_url( '/join/' ) ) ); ?>" onfocus="this.select();" style="width:260px;font-size:12px;">
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! $disabled && $trip && $inviter ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action" value="cbv_invite_link_action">
									<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
									<?php wp_nonce_field( 'cbv_invite_link_' . $token ); ?>
									<button type="submit" name="what" value="off" class="button button-small" onclick="return confirm('Turn off this link? Anyone who opens it after this sees that it is no longer valid.');">Turn off</button>
									<button type="submit" name="what" value="new" class="button button-small" onclick="return confirm('Turn off this link and make a new one for this member?');">Make a new link</button>
								</form>
							<?php elseif ( ! $disabled ) : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action" value="cbv_invite_link_action">
									<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
									<?php wp_nonce_field( 'cbv_invite_link_' . $token ); ?>
									<button type="submit" name="what" value="off" class="button button-small" onclick="return confirm('Turn off this link?');">Turn off</button>
								</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
