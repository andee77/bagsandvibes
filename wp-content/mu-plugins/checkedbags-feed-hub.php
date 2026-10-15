<?php
/**
 * Plugin Name: Checked Bags & Good Vibes — Feed hub
 * Description: Step C of the member hub plan (owner, 2026-10-15). The Feed ([cb_feed], /member-feed/) becomes the
 *              CBGV hub, in this order: trip updates from admins (members of that trip only), The Good Vibe Check
 *              podcast (episodes from the RSS feed, played on the page, "Listen on" buttons), Trip Tips (cb_tip),
 *              the member's most active boards (the Lounge + their own trips' boards only, never other trips' and
 *              never walls), wall posts only while the member social features are on, and a small strip of
 *              upcoming open trips. Destinations are no longer shown (their data is kept).
 *
 * Settings: the podcast feed address and the four "Listen on" links live on Settings > CBGV Member Features
 * (option cbv_podcast), so they can be changed without code. Each episode's own RSS link (Spotify for Creators,
 * the admin site) is never shown publicly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ==========================================================================
   1. Podcast settings (Settings > CBGV Member Features).
   ========================================================================== */
function cbv_podcast_defaults() {
	return array(
		'rss'     => 'https://anchor.fm/s/117a25e28/podcast/rss',
		'apple'   => 'https://podcasts.apple.com/us/podcast/the-good-vibe-check/id6819837564',
		'spotify' => 'https://open.spotify.com/show/4QKnVZeCikRxZa5kIvV9J7',
		'amazon'  => 'https://music.amazon.com/podcasts/2aaf2a45-7d23-49ff-8bf5-7d27758eb4d0/the-good-vibe-check',
		'iheart'  => 'https://www.iheart.com/podcast/269-the-good-vibe-check-347148735',
	);
}

/** Platform key => button label, in display order. */
function cbv_podcast_platforms() {
	return array( 'apple' => 'Apple Podcasts', 'spotify' => 'Spotify', 'amazon' => 'Amazon Music', 'iheart' => 'iHeartRadio' );
}

function cbv_podcast_settings() {
	$saved = get_option( 'cbv_podcast', null );
	return is_array( $saved ) ? array_merge( array_fill_keys( array_keys( cbv_podcast_defaults() ), '' ), cbv_podcast_sanitize( $saved ) ) : cbv_podcast_defaults();
}

/** Every field is an http(s) address or blank. */
function cbv_podcast_sanitize( $raw ) {
	$out = array();
	foreach ( array_keys( cbv_podcast_defaults() ) as $k ) {
		$v         = is_array( $raw ) && isset( $raw[ $k ] ) && is_scalar( $raw[ $k ] ) ? trim( (string) $raw[ $k ] ) : '';
		$out[ $k ] = '' === $v ? '' : esc_url_raw( $v, array( 'http', 'https' ) );
	}
	return $out;
}

add_action( 'admin_init', function () {
	register_setting( 'cbv_member_features', 'cbv_podcast', array(
		'type'              => 'array',
		'sanitize_callback' => 'cbv_podcast_sanitize',
	) );
} );

add_action( 'cbv_member_features_fields', function () {
	$s = cbv_podcast_settings();
	?>
	<tr>
		<th scope="row">Podcast on the Feed<br><span style="font-weight:normal;">The Good Vibe Check</span></th>
		<td>
			<p><label>RSS feed (episodes)<br><input type="url" class="large-text" name="cbv_podcast[rss]" value="<?php echo esc_attr( $s['rss'] ); ?>"></label></p>
			<?php foreach ( cbv_podcast_platforms() as $k => $label ) : ?>
				<p><label>"Listen on <?php echo esc_html( $label ); ?>" link<br><input type="url" class="large-text" name="cbv_podcast[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $s[ $k ] ); ?>"></label></p>
			<?php endforeach; ?>
			<p class="description">Episodes come from the RSS feed (checked at most every 6 hours) and play on the Feed. A "Listen on" button shows only when its link is filled in. Never use the Spotify for Creators (admin) address here.</p>
		</td>
	</tr>
	<?php
} );

/* ==========================================================================
   2. Podcast episodes from the RSS feed (cached 6 hours; the last good copy
      is kept for when the feed is down; nothing shows if there's neither).
   ========================================================================== */
function cbv_podcast_parse_feed( $feed ) {
	$ns    = 'http://www.itunes.com/dtds/podcast-1.0.dtd';
	$img   = function ( $tags ) {
		return isset( $tags[0]['attribs']['']['href'] ) ? esc_url_raw( $tags[0]['attribs']['']['href'] ) : '';
	};
	$show  = $img( $feed->get_channel_tags( $ns, 'image' ) );
	$show  = '' !== $show ? $show : esc_url_raw( (string) $feed->get_image_url() );
	$items = array();
	foreach ( (array) $feed->get_items( 0, 8 ) as $item ) {
		$enc   = $item->get_enclosure();
		$audio = ( $enc && 0 === strpos( (string) $enc->get_type(), 'audio/' ) ) ? esc_url_raw( (string) $enc->get_link() ) : '';
		$desc  = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( (string) $item->get_description() ), ENT_QUOTES, 'UTF-8' ) ) );
		$dur   = $item->get_item_tags( $ns, 'duration' );
		$items[] = array(
			'title'    => trim( html_entity_decode( wp_strip_all_tags( (string) $item->get_title() ), ENT_QUOTES, 'UTF-8' ) ),
			'date'     => (int) $item->get_date( 'U' ),
			'desc'     => $desc,
			'audio'    => $audio,
			'image'    => $img( $item->get_item_tags( $ns, 'image' ) ),
			'duration' => isset( $dur[0]['data'] ) ? sanitize_text_field( $dur[0]['data'] ) : '',
		);
	}
	if ( ! $items ) {
		return null;
	}
	return array(
		'show'     => trim( html_entity_decode( wp_strip_all_tags( (string) $feed->get_title() ), ENT_QUOTES, 'UTF-8' ) ),
		'image'    => $show,
		'episodes' => $items,
	);
}

function cbv_podcast_data() {
	$s = cbv_podcast_settings();
	if ( '' === $s['rss'] ) {
		return null;
	}
	$key  = 'cbv_podcast_' . md5( $s['rss'] );
	$data = get_transient( $key );
	if ( false === $data ) {
		include_once ABSPATH . WPINC . '/feed.php';
		$life = function () { return 6 * HOUR_IN_SECONDS; };
		add_filter( 'wp_feed_cache_transient_lifetime', $life );
		$feed = fetch_feed( $s['rss'] );
		remove_filter( 'wp_feed_cache_transient_lifetime', $life );
		$data = is_wp_error( $feed ) ? null : cbv_podcast_parse_feed( $feed );
		if ( $data ) {
			set_transient( $key, $data, 6 * HOUR_IN_SECONDS );
			update_option( 'cbv_podcast_last_good', $data, false );
		} else {
			$data = get_option( 'cbv_podcast_last_good', null );
			set_transient( $key, $data ? $data : array(), HOUR_IN_SECONDS ); // try the feed again in an hour
		}
	}
	return ! empty( $data['episodes'] ) ? $data : null;
}

/** "Sep 27, 2026 · 9 min". */
function cbv_podcast_meta_line( $ep ) {
	$bits = array();
	if ( $ep['date'] ) {
		$bits[] = wp_date( 'M j, Y', $ep['date'] );
	}
	if ( '' !== $ep['duration'] ) {
		$p    = array_map( 'intval', explode( ':', $ep['duration'] ) );
		$secs = 3 === count( $p ) ? $p[0] * 3600 + $p[1] * 60 + $p[2] : ( 2 === count( $p ) ? $p[0] * 60 + $p[1] : $p[0] );
		if ( $secs > 0 ) {
			$bits[] = max( 1, (int) round( $secs / 60 ) ) . ' min';
		}
	}
	return implode( ' · ', $bits );
}

/* ==========================================================================
   3. Trip updates: admins post short notices on a trip's edit screen; the
      trip's members see them at the top of the Feed until the end date.
   ========================================================================== */
define( 'CBV_TRIP_UPDATES_MAX', 5 );

function cbv_trip_updates_sanitize( $raw ) {
	$out = array();
	foreach ( (array) $raw as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$text  = isset( $row['text'] ) && is_scalar( $row['text'] ) ? sanitize_textarea_field( (string) $row['text'] ) : '';
		$text  = function_exists( 'mb_substr' ) ? mb_substr( trim( $text ), 0, 300 ) : substr( trim( $text ), 0, 300 );
		$until = isset( $row['until'] ) && is_scalar( $row['until'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $row['until'] ) ? (string) $row['until'] : '';
		if ( '' === $text ) {
			continue;
		}
		$out[] = array( 'text' => $text, 'until' => $until );
		if ( count( $out ) >= CBV_TRIP_UPDATES_MAX ) {
			break;
		}
	}
	return $out;
}

/** The trip's updates still showing today (no end date, or ending today or later). */
function cbv_trip_updates_current( $trip_id ) {
	$today = wp_date( 'Y-m-d' );
	return array_values( array_filter( cbv_trip_updates_sanitize( get_post_meta( $trip_id, 'cbv_trip_updates', true ) ), function ( $u ) use ( $today ) {
		return '' === $u['until'] || $u['until'] >= $today;
	} ) );
}

add_action( 'add_meta_boxes_cb_trip', function () {
	add_meta_box( 'cbv_trip_updates', 'Trip updates (shown on members\' Feed)', 'cbv_render_trip_updates_box', 'cb_trip', 'side', 'default' );
} );

function cbv_render_trip_updates_box( $post ) {
	wp_nonce_field( 'cbv_trip_updates_save', 'cbv_trip_updates_nonce' );
	$rows = cbv_trip_updates_sanitize( get_post_meta( $post->ID, 'cbv_trip_updates', true ) );
	$rows = array_pad( $rows, CBV_TRIP_UPDATES_MAX, array( 'text' => '', 'until' => '' ) );
	echo '<p class="description">Short notices for this trip\'s members, shown at the top of their Feed (newest first). Leave the date blank to keep it up; it disappears after the date. Never name the host agency or ask for travel payments.</p>';
	foreach ( $rows as $i => $r ) {
		printf(
			'<p><label>Update %1$d<br><textarea name="cbv_trip_updates[%2$d][text]" rows="2" maxlength="300" style="width:100%%;">%3$s</textarea></label><label>Show until <input type="date" name="cbv_trip_updates[%2$d][until]" value="%4$s"></label></p>',
			(int) $i + 1,
			(int) $i,
			esc_textarea( $r['text'] ),
			esc_attr( $r['until'] )
		);
	}
}

add_action( 'save_post_cb_trip', function ( $post_id ) {
	if ( ! isset( $_POST['cbv_trip_updates_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cbv_trip_updates_nonce'] ) ), 'cbv_trip_updates_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, 'cbv_trip_updates', cbv_trip_updates_sanitize( wp_unslash( $_POST['cbv_trip_updates'] ?? array() ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- cleaned row by row
} );

/* ==========================================================================
   4. Who sees what: the member's trips, and their boards.
   ========================================================================== */
/** Trips the member is on (moderators: every trip). */
function cbv_hub_member_trip_ids( $user_id ) {
	$ids = get_posts( array( 'post_type' => 'cb_trip', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
	if ( user_can( $user_id, 'moderate' ) ) {
		return array_map( 'intval', $ids );
	}
	return array_values( array_filter( array_map( 'intval', $ids ), function ( $t ) use ( $user_id ) {
		return in_array( (int) $user_id, array_map( 'intval', cb_trip_get_roster( $t ) ), true );
	} ) );
}

/**
 * The member's boards: the Lounge and their own trips' boards, never another trip's and never a wall; each one
 * also has to pass the site's own read_forum check. Ordered by posts in the last 30 days, then last activity.
 */
function cbv_hub_boards( $user_id ) {
	$forum_trip = array();
	$lounge     = (int) get_option( 'cb_lounge_forum_id' );
	if ( $lounge ) {
		$forum_trip[ $lounge ] = 0;
	}
	foreach ( cbv_hub_member_trip_ids( $user_id ) as $t ) {
		$f = (int) get_post_meta( $t, 'cb_forum_id', true );
		if ( $f ) {
			$forum_trip[ $f ] = $t;
		}
	}
	$since = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );
	$out   = array();
	foreach ( $forum_trip as $f => $t ) {
		if ( 'forum' !== get_post_type( $f ) || ( function_exists( 'cb_wall_forum_owner_id' ) && cb_wall_forum_owner_id( $f ) ) || ! user_can( $user_id, 'read_forum', $f ) ) {
			continue;
		}
		$posts  = get_posts( array(
			'post_type'        => array( 'topic', 'reply' ),
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'meta_key'         => '_bbp_forum_id',
			'meta_value'       => $f,
			'suppress_filters' => true,
		) );
		$recent = 0;
		$latest = null;
		foreach ( $posts as $p ) {
			$gmt = get_post_field( 'post_date_gmt', $p );
			if ( $gmt >= $since ) {
				$recent++;
			}
			if ( ! $latest || $gmt > get_post_field( 'post_date_gmt', $latest ) ) {
				$latest = $p;
			}
		}
		$topic = $latest ? ( 'reply' === get_post_type( $latest ) ? (int) get_post_meta( $latest, '_bbp_topic_id', true ) : $latest ) : 0;
		$name  = $t ? get_the_title( $t ) : get_the_title( $f );
		$out[] = array(
			'forum'  => $f,
			'name'   => html_entity_decode( wp_strip_all_tags( $name ), ENT_QUOTES, 'UTF-8' ),
			'url'    => function_exists( 'bbp_get_forum_permalink' ) ? bbp_get_forum_permalink( $f ) : get_permalink( $f ),
			'count'  => count( $posts ),
			'recent' => $recent,
			'last'   => $latest ? strtotime( get_post_field( 'post_date_gmt', $latest ) . ' UTC' ) : 0,
			'topic'  => $topic ? html_entity_decode( wp_strip_all_tags( get_the_title( $topic ) ), ENT_QUOTES, 'UTF-8' ) : '',
			'turl'   => $topic && function_exists( 'bbp_get_topic_permalink' ) ? bbp_get_topic_permalink( $topic ) : '',
		);
	}
	usort( $out, function ( $a, $b ) {
		return array( $b['recent'], $b['last'], $a['name'] ) <=> array( $a['recent'], $a['last'], $b['name'] );
	} );
	return $out;
}

/* ==========================================================================
   5. The hub (rendered by [cb_feed] in checkedbags-feed.php).
   ========================================================================== */
function cbv_render_feed_hub( $user_id ) {
	$trips   = cbv_hub_member_trip_ids( $user_id );
	$notices = array();
	foreach ( $trips as $t ) {
		foreach ( array_reverse( cbv_trip_updates_current( $t ) ) as $u ) {
			$notices[] = array( 'trip' => get_the_title( $t ), 'url' => get_permalink( $t ), 'text' => $u['text'] );
		}
	}
	$pod    = cbv_podcast_data();
	$set    = cbv_podcast_settings();
	$tips   = get_posts( array( 'post_type' => 'cb_tip', 'numberposts' => 6, 'orderby' => 'date', 'order' => 'DESC' ) );
	$boards = cbv_hub_boards( $user_id );
	$social = ! function_exists( 'cbv_member_social_enabled' ) || cbv_member_social_enabled();
	$walls  = $social && function_exists( 'cb_feed_recent_topics' ) ? cb_feed_recent_topics()['wall_posts'] : array();
	$open   = function_exists( 'cb_feed_upcoming_trips' ) ? cb_feed_upcoming_trips() : array();

	ob_start();
	?>
	<div class="hub">
		<p class="cb-page-hint">Your CBGV hub: trip updates, the podcast, travel tips and the latest from your boards.</p>

		<?php if ( $notices ) : ?>
			<section class="hub-section hub-notices" aria-label="Trip updates">
				<?php foreach ( $notices as $n ) : ?>
					<div class="hub-notice">
						<span class="hub-notice-label">Gate notice · <a href="<?php echo esc_url( $n['url'] ); ?>"><?php echo esc_html( $n['trip'] ); ?></a></span>
						<p class="hub-notice-text"><?php echo nl2br( esc_html( $n['text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped first ?></p>
					</div>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<?php if ( $pod ) :
			$ep   = $pod['episodes'][0];
			$art  = '' !== $ep['image'] ? $ep['image'] : $pod['image'];
			$more = array_slice( $pod['episodes'], 1, 3 );
			?>
			<section class="hub-section" aria-labelledby="hub-pod-title">
				<h3 class="feed-section-title" id="hub-pod-title">The Good Vibe Check · Podcast</h3>
				<article class="hub-pod">
					<?php if ( '' !== $art ) : ?><img class="hub-pod-art" src="<?php echo esc_url( $art ); ?>" alt="" width="160" height="160" loading="lazy"><?php endif; ?>
					<div class="hub-pod-body">
						<span class="hub-pod-label">Latest episode</span>
						<h4 class="hub-pod-title"><?php echo esc_html( $ep['title'] ); ?></h4>
						<span class="hub-pod-meta"><?php echo esc_html( cbv_podcast_meta_line( $ep ) ); ?></span>
						<?php if ( '' !== $ep['desc'] ) : ?><p class="hub-pod-desc"><?php echo esc_html( wp_trim_words( $ep['desc'], 38, '…' ) ); ?></p><?php endif; ?>
						<?php if ( '' !== $ep['audio'] ) : ?>
							<audio class="hub-pod-audio" controls preload="none" src="<?php echo esc_url( $ep['audio'] ); ?>">Your browser can't play this episode here; use a "Listen on" link below.</audio>
						<?php endif; ?>
						<div class="hub-pod-links">
							<?php foreach ( cbv_podcast_platforms() as $k => $label ) : if ( '' === $set[ $k ] ) { continue; } ?>
								<a class="hub-pod-link" href="<?php echo esc_url( $set[ $k ] ); ?>" target="_blank" rel="noopener">Listen on <?php echo esc_html( $label ); ?></a>
							<?php endforeach; ?>
						</div>
					</div>
				</article>
				<?php if ( $more ) : ?>
					<ul class="hub-pod-more" aria-label="More episodes">
						<?php foreach ( $more as $m ) : ?>
							<li><span class="hub-pod-more-title"><?php echo esc_html( $m['title'] ); ?></span> <span class="hub-pod-more-meta"><?php echo esc_html( cbv_podcast_meta_line( $m ) ); ?></span><?php if ( '' !== $m['audio'] ) : ?><audio controls preload="none" src="<?php echo esc_url( $m['audio'] ); ?>"></audio><?php endif; ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $tips ) : ?>
			<section class="hub-section" aria-labelledby="hub-tips-title">
				<h3 class="feed-section-title" id="hub-tips-title">Trip Tips</h3>
				<div class="hub-tips">
					<?php foreach ( $tips as $i => $tip ) : $text = get_post_meta( $tip->ID, 'cb_tip_text', true ); ?>
						<div class="hub-tip">
							<span class="hub-tip-hole" aria-hidden="true"></span>
							<span class="hub-tip-label">Tip <?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
							<h4 class="hub-tip-title"><?php echo esc_html( get_the_title( $tip ) ); ?></h4>
							<?php if ( $text ) : ?><p class="hub-tip-text"><?php echo esc_html( $text ); ?></p><?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $boards ) : ?>
			<section class="hub-section" aria-labelledby="hub-boards-title">
				<h3 class="feed-section-title" id="hub-boards-title">Your boards</h3>
				<div class="hub-board" role="table" aria-label="Your boards, most active first">
					<div class="hub-board-head" role="row"><span role="columnheader">Board</span><span role="columnheader">Latest</span><span role="columnheader">Last activity</span><span role="columnheader">Posts</span><span role="columnheader"><span class="screen-reader-text">Open</span></span></div>
					<?php foreach ( $boards as $b ) : ?>
						<div class="hub-board-row" role="row">
							<span class="hub-board-name" role="cell"><?php echo esc_html( $b['name'] ); ?></span>
							<span class="hub-board-latest" role="cell"><?php if ( '' !== $b['topic'] && '' !== $b['turl'] ) : ?><a href="<?php echo esc_url( $b['turl'] ); ?>"><?php echo esc_html( $b['topic'] ); ?></a><?php else : ?>Be the first to post<?php endif; ?></span>
							<span class="hub-board-when" role="cell"><?php echo $b['last'] ? esc_html( human_time_diff( $b['last'] ) . ' ago' ) : '—'; ?></span>
							<span class="hub-board-count" role="cell"><?php echo (int) $b['count']; ?></span>
							<span class="hub-board-open" role="cell"><a class="hub-board-btn" href="<?php echo esc_url( $b['url'] ); ?>">Open <span aria-hidden="true">&rarr;</span></a></span>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $walls ) : ?>
			<section class="hub-section" aria-labelledby="hub-walls-title">
				<h3 class="feed-section-title" id="hub-walls-title">Members Post</h3>
				<div class="feed-topic-list">
					<?php foreach ( $walls as $topic ) : ?>
						<a href="<?php echo esc_url( function_exists( 'bbp_get_topic_permalink' ) ? bbp_get_topic_permalink( $topic->ID ) : get_permalink( $topic ) ); ?>" class="feed-topic-row"><span class="feed-topic-title"><?php echo esc_html( get_the_title( $topic ) ); ?></span></a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( $open ) : ?>
			<section class="hub-section" aria-labelledby="hub-open-title">
				<h3 class="feed-section-title" id="hub-open-title">Upcoming open trips</h3>
				<div class="hub-open">
					<?php foreach ( $open as $trip ) : $spots = function_exists( 'cb_trip_spots_remaining' ) ? cb_trip_spots_remaining( $trip->ID ) : null; ?>
						<a class="hub-open-chip" href="<?php echo esc_url( get_permalink( $trip ) ); ?>"><span class="hub-open-name"><?php echo esc_html( get_the_title( $trip ) ); ?></span><span class="hub-open-spots"><?php echo null === $spots ? 'Open' : esc_html( $spots . ' spot' . ( 1 === (int) $spots ? '' : 's' ) . ' left' ); ?></span></a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! $notices && ! $pod && ! $tips && ! $boards && ! $walls && ! $open ) : ?>
			<p class="cb-empty">Nothing to show yet — check back soon.</p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
