<?php
/**
 * Weekly challenge: one featured painting per week, ranked by finishing time.
 *
 * Painting post meta:
 * - `_diamondistry_challenge_start`  Y-m-d, the challenge runs 7 days from local midnight.
 * - `_diamondistry_ch_{Ymd}_{userId}` finishing time in seconds, one row per player
 *   (separate rows so players finishing at the same moment never overwrite each other).
 * User meta `_diamondistry_leaderboard_hidden` = 1 shows the player as "Anonymous player".
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIAMONDISTRY_CHALLENGE_START_META_KEY', '_diamondistry_challenge_start' );
define( 'DIAMONDISTRY_LEADERBOARD_HIDDEN_META_KEY', '_diamondistry_leaderboard_hidden' );

/*
 * Anti-cheat. Progress comes from the browser, so a finish only earns its reward
 * and a leaderboard spot when it looks human. Players can drag across a row to
 * fill it, so bursts of 10-30 diamonds a second are normal play: a save may add
 * up to MAX_CELLS_PER_SECOND per second since the previous save (+10 slack), and
 * a finish must take at least MIN_SECONDS_PER_CELL per diamond. This still stops
 * a request that sends the whole painting at once.
 */
define( 'DIAMONDISTRY_CHALLENGE_MAX_CELLS_PER_SECOND', 40 );
define( 'DIAMONDISTRY_CHALLENGE_MIN_SECONDS_PER_CELL', 0.02 );

/** Whether one save added a humanly possible number of diamonds. */
function diamondistry_challenge_save_is_plausible( $added_cells, $seconds_since_last_save ) {
	return $added_cells <= 10 + max( 0, $seconds_since_last_save ) * DIAMONDISTRY_CHALLENGE_MAX_CELLS_PER_SECOND;
}

/** Diamonds for places 1, 2 and 3 when a challenge week ends. */
function diamondistry_challenge_prizes() {
	return array( 100, 50, 25 );
}

function diamondistry_challenge_window( $painting_id ) {
	$date = (string) get_post_meta( $painting_id, DIAMONDISTRY_CHALLENGE_START_META_KEY, true );
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		return null;
	}

	$start = new DateTimeImmutable( $date . ' 00:00:00', wp_timezone() );

	return array(
		'key'   => $start->format( 'Ymd' ),
		'start' => $start->getTimestamp(),
		'end'   => $start->modify( '+7 days' )->getTimestamp(),
	);
}

/** The painting whose challenge week is running now, or null. */
function diamondistry_get_current_challenge() {
	static $current = false;
	if ( false !== $current ) {
		return $current;
	}

	$today    = wp_date( 'Y-m-d' );
	$current  = null;
	$featured = get_posts(
		array(
			'post_type'      => 'ddp_painting',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => DIAMONDISTRY_CHALLENGE_START_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery -- tiny set.
			'meta_value'     => $today, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_compare'   => '<=',
			'orderby'        => 'meta_value',
			'order'          => 'DESC',
		)
	);

	if ( $featured ) {
		$window = diamondistry_challenge_window( $featured[0]->ID );
		if ( $window && time() < $window['end'] ) {
			$current = array( 'painting' => $featured[0] ) + $window;
		}
	}

	return $current;
}

function diamondistry_is_current_challenge( $painting_id ) {
	$current = diamondistry_get_current_challenge();
	return $current && (int) $current['painting']->ID === (int) $painting_id;
}

function diamondistry_format_duration( $seconds ) {
	$seconds = max( 0, (int) $seconds );
	$hours   = intdiv( $seconds, 3600 );
	$minutes = intdiv( $seconds % 3600, 60 );
	$secs    = $seconds % 60;

	return $hours ? sprintf( '%d:%02d:%02d', $hours, $minutes, $secs ) : sprintf( '%d:%02d', $minutes, $secs );
}

/*
|--------------------------------------------------------------------------
| Results
|--------------------------------------------------------------------------
*/

function diamondistry_challenge_result_prefix( $week_key ) {
	return '_diamondistry_ch_' . $week_key . '_';
}

/**
 * Called from the progress save (inside the user row lock) on a fresh completion.
 * Returns ['seconds' => int, 'rank' => int] when the time counts, else null.
 */
function diamondistry_record_challenge_result( $user_id, $painting_id, $started_at, $completed_at, $total_cells = 0, $plausible = true ) {
	if ( ! diamondistry_is_current_challenge( $painting_id ) ) {
		return null;
	}

	$current = diamondistry_get_current_challenge();

	// Only attempts started during the challenge week count; the first completion sticks.
	if ( ! $started_at || $started_at < $current['start'] || $completed_at >= $current['end'] ) {
		return null;
	}

	$seconds = max( 1, $completed_at - $started_at );

	// Too fast for a human, or diamonds arrived in impossible bursts: no leaderboard entry.
	if ( ! $plausible || $seconds < $total_cells * DIAMONDISTRY_CHALLENGE_MIN_SECONDS_PER_CELL ) {
		return null;
	}

	$meta_key = diamondistry_challenge_result_prefix( $current['key'] ) . absint( $user_id );

	add_post_meta( $painting_id, $meta_key, $seconds, true );

	return array(
		'seconds' => (int) get_post_meta( $painting_id, $meta_key, true ),
		'rank'    => diamondistry_challenge_rank( $painting_id, $current['key'], $user_id ),
	);
}

/** All results for one challenge week, fastest first: [ [user_id, seconds], ... ]. */
function diamondistry_get_challenge_results( $painting_id, $week_key ) {
	global $wpdb;

	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- per-week leaderboard, keys are dynamic.
		$wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s ORDER BY CAST(meta_value AS UNSIGNED) ASC, meta_id ASC",
			$painting_id,
			$wpdb->esc_like( diamondistry_challenge_result_prefix( $week_key ) ) . '%'
		)
	);

	$prefix  = diamondistry_challenge_result_prefix( $week_key );
	$results = array();
	foreach ( $rows as $row ) {
		$user_id = absint( substr( $row->meta_key, strlen( $prefix ) ) );
		if ( $user_id && get_user_by( 'id', $user_id ) ) {
			$results[] = array( $user_id, (int) $row->meta_value );
		}
	}

	return $results;
}

function diamondistry_challenge_rank( $painting_id, $week_key, $user_id ) {
	foreach ( diamondistry_get_challenge_results( $painting_id, $week_key ) as $index => $result ) {
		if ( (int) $result[0] === (int) $user_id ) {
			return $index + 1;
		}
	}
	return 0;
}

/*
|--------------------------------------------------------------------------
| Prizes for finished weeks (checked at most once an hour)
|--------------------------------------------------------------------------
*/

function diamondistry_award_finished_challenges() {
	global $wpdb;

	if ( get_transient( 'diamondistry_challenge_award_check' ) ) {
		return;
	}
	set_transient( 'diamondistry_challenge_award_check', 1, HOUR_IN_SECONDS );

	$paintings = get_posts(
		array(
			'post_type'      => 'ddp_painting',
			'post_status'    => array( 'publish', 'private', 'draft', 'trash' ),
			'posts_per_page' => -1,
			'meta_key'       => DIAMONDISTRY_CHALLENGE_START_META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery -- tiny set.
			'fields'         => 'ids',
		)
	);

	foreach ( $paintings as $painting_id ) {
		$window = diamondistry_challenge_window( $painting_id );
		if ( ! $window || time() < $window['end'] ) {
			continue;
		}

		// INSERT IGNORE on the unique option name: only one request can claim the payout.
		$claimed = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				'diamondistry_challenge_awarded_' . $painting_id . '_' . $window['key'],
				(string) time()
			)
		);
		if ( 1 !== (int) $claimed ) {
			continue;
		}

		$prizes = diamondistry_challenge_prizes();
		foreach ( array_slice( diamondistry_get_challenge_results( $painting_id, $window['key'] ), 0, count( $prizes ) ) as $place => $result ) {
			// Same per-user lock as saves and unlocks, so a payout never races a balance change.
			$locked = diamondistry_begin_progress_write( $result[0] );
			diamondistry_add_user_diamonds( $result[0], $prizes[ $place ] );
			diamondistry_commit_progress_write( $locked );
		}
	}
}

add_action( 'init', 'diamondistry_award_finished_challenges', 40 );

/*
|--------------------------------------------------------------------------
| [diamondistry_challenge] — full page; compact="1" = home banner
|--------------------------------------------------------------------------
*/

function diamondistry_leaderboard_name( $user_id ) {
	if ( get_user_meta( $user_id, DIAMONDISTRY_LEADERBOARD_HIDDEN_META_KEY, true ) ) {
		return 'Anonymous player';
	}
	$user = get_user_by( 'id', $user_id );
	return $user ? $user->display_name : 'Player';
}

function diamondistry_time_left_label( $end ) {
	$left  = max( 0, $end - time() );
	$days  = intdiv( $left, DAY_IN_SECONDS );
	$hours = intdiv( $left % DAY_IN_SECONDS, HOUR_IN_SECONDS );

	if ( $days ) {
		return sprintf( '%d day%s %d h left', $days, 1 === $days ? '' : 's', $hours );
	}
	return sprintf( '%d h %d min left', $hours, intdiv( $left % HOUR_IN_SECONDS, MINUTE_IN_SECONDS ) );
}

function diamondistry_render_challenge( $atts ) {
	$atts    = shortcode_atts( array( 'compact' => '' ), $atts );
	$current = diamondistry_get_current_challenge();

	if ( $atts['compact'] ) {
		if ( ! $current ) {
			return '';
		}
		ob_start();
		?>
		<a class="dd-challenge-banner" href="<?php echo esc_url( home_url( '/challenge/' ) ); ?>">
			<span class="dd-challenge-banner__icon" aria-hidden="true">🏆</span>
			<span class="dd-challenge-banner__text"><strong>This week's challenge:</strong> <?php echo esc_html( get_the_title( $current['painting'] ) ); ?> · <?php echo esc_html( diamondistry_time_left_label( $current['end'] ) ); ?></span>
			<span class="dd-challenge-banner__cta">Leaderboard <span aria-hidden="true">→</span></span>
		</a>
		<?php
		return diamondistry_compact_html( ob_get_clean() );
	}

	if ( ! $current ) {
		return '<div class="dd-challenge-empty"><h2>No challenge running right now</h2><p>A new weekly challenge starts soon. Meanwhile, pick any painting and practise your speed.</p><a class="dd-button dd-button-primary" href="' . esc_url( home_url( '/paintings/' ) ) . '">Explore paintings <span aria-hidden="true">→</span></a></div>';
	}

	if ( function_exists( 'diamondistry_enqueue_painting_card_assets' ) ) {
		diamondistry_enqueue_painting_card_assets();
	}

	$painting = $current['painting'];
	$data     = diamondistry_get_painting_data( $painting );
	$results  = diamondistry_get_challenge_results( $painting->ID, $current['key'] );
	$me       = get_current_user_id();
	$my_rank  = 0;
	foreach ( $results as $index => $result ) {
		if ( $me && (int) $result[0] === $me ) {
			$my_rank = $index + 1;
		}
	}
	$prizes   = diamondistry_challenge_prizes();
	$medals   = array( '🥇', '🥈', '🥉' );
	$play_url = add_query_arg( 'painting', $painting->post_name, home_url( '/play/' ) );
	$cover    = get_the_post_thumbnail_url( $painting, 'large' );

	ob_start();
	?>
	<div class="dd-challenge">
		<article class="dd-challenge__painting">
			<div class="dd-challenge__art">
				<?php if ( $cover ) : ?>
					<img src="<?php echo esc_url( $cover ); ?>" alt="">
				<?php else : ?>
					<?php echo diamondistry_render_painting_preview( $painting, null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</div>
			<div class="dd-challenge__body">
				<p class="dd-eyebrow">This week's painting</p>
				<h2 class="dd-challenge__title"><?php echo esc_html( get_the_title( $painting ) ); ?></h2>
				<ul class="dd-challenge__meta">
					<li><?php echo esc_html( diamondistry_time_left_label( $current['end'] ) ); ?></li>
					<?php if ( $data ) : ?>
						<li><?php echo esc_html( $data['width'] . ' × ' . $data['height'] ); ?> diamonds</li>
					<?php endif; ?>
					<li>Free to play this week</li>
				</ul>
				<p class="dd-challenge__prizes">
					<?php foreach ( $prizes as $place => $prize ) : ?>
						<span><?php echo esc_html( $medals[ $place ] . ' ' . $prize ); ?> 💎</span>
					<?php endforeach; ?>
				</p>
				<a class="dd-button dd-button-primary" href="<?php echo esc_url( $play_url ); ?>"><?php echo $my_rank ? 'View painting' : 'Play the challenge'; ?> <span aria-hidden="true">→</span></a>
				<p class="dd-challenge__rules">Your time runs from your first diamond to your last. Only attempts started this week count, and your first finish is the one that counts. Times faster than humanly possible are not ranked. Prizes are added to your balance after the week ends.</p>
			</div>
		</article>

		<section class="dd-challenge__board" aria-labelledby="dd-leaderboard-title">
			<h2 class="dd-panel__title" id="dd-leaderboard-title">Leaderboard</h2>
			<?php if ( empty( $results ) ) : ?>
				<p class="dd-challenge__none">No finishers yet. Be the first!</p>
			<?php else : ?>
				<ol class="dd-leaderboard">
					<?php foreach ( array_slice( $results, 0, 10 ) as $index => $result ) : ?>
						<li class="dd-leaderboard__row<?php echo (int) $result[0] === $me ? ' is-me' : ''; ?>">
							<span class="dd-leaderboard__rank"><?php echo esc_html( isset( $medals[ $index ] ) ? $medals[ $index ] : $index + 1 ); ?></span>
							<span class="dd-leaderboard__avatar"><?php echo get_user_meta( $result[0], DIAMONDISTRY_LEADERBOARD_HIDDEN_META_KEY, true ) ? get_avatar( 0, 64 ) : get_avatar( $result[0], 64 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="dd-leaderboard__name"><?php echo esc_html( diamondistry_leaderboard_name( $result[0] ) ); ?></span>
							<span class="dd-leaderboard__time"><?php echo esc_html( diamondistry_format_duration( $result[1] ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
				<?php if ( $my_rank > 10 ) : ?>
					<p class="dd-challenge__mine">Your time: <strong><?php echo esc_html( diamondistry_format_duration( $results[ $my_rank - 1 ][1] ) ); ?></strong> · #<?php echo esc_html( $my_rank ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( ! $me ) : ?>
				<p class="dd-challenge__none"><a class="dd-link" href="<?php echo esc_url( wp_login_url( home_url( '/challenge/' ) ) ); ?>">Log in</a> to get on the leaderboard.</p>
			<?php endif; ?>
		</section>
	</div>
	<?php
	return diamondistry_compact_html( ob_get_clean() );
}

add_shortcode( 'diamondistry_challenge', 'diamondistry_render_challenge' );

/*
|--------------------------------------------------------------------------
| Privacy: remove a player's times when their account is deleted or erased
|--------------------------------------------------------------------------
*/

function diamondistry_delete_challenge_results( $user_id ) {
	global $wpdb;

	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_diamondistry_ch_' ) . '%' . $wpdb->esc_like( '_' . absint( $user_id ) )
		)
	);
}

add_action( 'delete_user', 'diamondistry_delete_challenge_results' );
