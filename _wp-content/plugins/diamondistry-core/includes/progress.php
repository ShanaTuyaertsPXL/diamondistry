<?php
/**
 * Diamondistry user progress and rewards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIAMONDISTRY_PROGRESS_META_KEY', '_diamondistry_progress' );
define( 'DIAMONDISTRY_BALANCE_META_KEY', '_diamondistry_diamond_balance' );

/*
|--------------------------------------------------------------------------
| User progress helpers
|--------------------------------------------------------------------------
*/

function diamondistry_get_all_user_progress( $user_id ) {
	$progress = get_user_meta(
		$user_id,
		DIAMONDISTRY_PROGRESS_META_KEY,
		true
	);

	return is_array( $progress ) ? $progress : array();
}

function diamondistry_get_progress_last_worked_at( $progress ) {
	if ( ! is_array( $progress ) ) {
		return null;
	}

	if (
		isset( $progress['lastWorkedAt'] ) &&
		is_numeric( $progress['lastWorkedAt'] ) &&
		absint( $progress['lastWorkedAt'] ) > 0
	) {
		return absint( $progress['lastWorkedAt'] );
	}

	/* Legacy fallback for records created before lastWorkedAt existed. */
	if (
		! empty( $progress['completedCells'] ) &&
		! empty( $progress['updatedAt'] )
	) {
		$timestamp = strtotime( $progress['updatedAt'] . ' UTC' );
		return $timestamp ? $timestamp : null;
	}

	return null;
}

function diamondistry_get_user_painting_progress( $user_id, $painting_id ) {
	$all_progress = diamondistry_get_all_user_progress( $user_id );
	$key          = (string) absint( $painting_id );

	if ( ! isset( $all_progress[ $key ] ) || ! is_array( $all_progress[ $key ] ) ) {
		return array(
			'completedCells' => array(),
			'selectedColor'  => null,
			'completed'      => false,
			'rewardClaimed'  => false,
			'lastWorkedAt'   => null,
		);
	}

	$progress = $all_progress[ $key ];

	$completed_cells = array();
	if ( isset( $progress['completedCells'] ) && is_array( $progress['completedCells'] ) ) {
		foreach ( $progress['completedCells'] as $index ) {
			if ( is_int( $index ) || ( is_string( $index ) && preg_match( '/^\d+$/', $index ) ) ) {
				$completed_cells[] = (int) $index;
			}
		}
		$completed_cells = array_values( array_unique( $completed_cells ) );
	}

	return array(
		'completedCells' => $completed_cells,
		'selectedColor'  => isset( $progress['selectedColor'] ) ? absint( $progress['selectedColor'] ) : null,
		'completed'      => ! empty( $progress['completed'] ),
		'rewardClaimed'  => ! empty( $progress['rewardClaimed'] ),
		'lastWorkedAt'   => diamondistry_get_progress_last_worked_at( $progress ),
	);
}

/*
|--------------------------------------------------------------------------
| Painting validation used by progress saves
|--------------------------------------------------------------------------
*/

function diamondistry_get_valid_playable_painting_data( $painting ) {
	$data = diamondistry_get_painting_data( $painting );

	if ( ! $data ) {
		return null;
	}

	$width       = absint( $data['width'] );
	$height      = absint( $data['height'] );
	$total_cells = $width * $height;

	if ( $width < 1 || $height < 1 || $total_cells < 1 ) {
		return null;
	}

	if ( ! is_array( $data['palette'] ) || empty( $data['palette'] ) ) {
		return null;
	}

	$valid_color_ids = array();

	foreach ( $data['palette'] as $color ) {
		if ( ! is_array( $color ) || empty( $color['id'] ) ) {
			continue;
		}

		$color_id = absint( $color['id'] );
		if ( $color_id > 0 ) {
			$valid_color_ids[] = $color_id;
		}
	}

	$valid_color_ids = array_values( array_unique( $valid_color_ids ) );

	if ( empty( $valid_color_ids ) ) {
		return null;
	}

	if ( ! is_array( $data['pattern'] ) || count( $data['pattern'] ) !== $total_cells ) {
		return null;
	}

	foreach ( $data['pattern'] as $color_id ) {
		$color_id = absint( $color_id );

		if ( 0 === $color_id || ! in_array( $color_id, $valid_color_ids, true ) ) {
			return null;
		}
	}

	$data['totalCells']    = $total_cells;
	$data['validColorIds'] = $valid_color_ids;

	return $data;
}

function diamondistry_sanitize_completed_cell_indexes( $raw, $total_cells ) {
	if ( ! is_array( $raw ) || $total_cells < 1 ) {
		return array();
	}

	$clean = array();

	foreach ( $raw as $index ) {
		if ( is_int( $index ) ) {
			$index = $index;
		} elseif ( is_string( $index ) && preg_match( '/^\d+$/', $index ) ) {
			$index = (int) $index;
		} else {
			continue;
		}

		if ( $index >= 0 && $index < $total_cells ) {
			$clean[] = $index;
		}
	}

	$clean = array_values( array_unique( $clean ) );
	sort( $clean, SORT_NUMERIC );

	return $clean;
}

/*
|--------------------------------------------------------------------------
| Balance
|--------------------------------------------------------------------------
*/

function diamondistry_get_user_balance( $user_id ) {
	return absint(
		get_user_meta(
			$user_id,
			DIAMONDISTRY_BALANCE_META_KEY,
			true
		)
	);
}

function diamondistry_add_user_diamonds( $user_id, $amount ) {
	$amount  = max( 0, absint( $amount ) );
	$current = diamondistry_get_user_balance( $user_id );
	$new     = $current + $amount;

	update_user_meta( $user_id, DIAMONDISTRY_BALANCE_META_KEY, $new );

	return $new;
}

/*
|--------------------------------------------------------------------------
| AJAX: Save progress
|--------------------------------------------------------------------------
*/

function diamondistry_ajax_save_progress() {
	check_ajax_referer( 'diamondistry_progress', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'You must be logged in.' ), 401 );
	}

	$user_id = get_current_user_id();
	$painting_id = isset( $_POST['paintingId'] ) ? absint( $_POST['paintingId'] ) : 0;

	if ( ! $painting_id ) {
		wp_send_json_error( array( 'message' => 'Invalid painting.' ), 400 );
	}

	$painting = get_post( $painting_id );

	if (
		! $painting ||
		'ddp_painting' !== $painting->post_type ||
		'publish' !== $painting->post_status
	) {
		wp_send_json_error( array( 'message' => 'Painting not available.' ), 404 );
	}

	$data = diamondistry_get_valid_playable_painting_data( $painting );

	if ( ! $data ) {
		wp_send_json_error( array( 'message' => 'Painting data is incomplete or invalid.' ), 400 );
	}

	$raw_completed = array();
	if ( isset( $_POST['completedCells'] ) ) {
		$decoded = json_decode( wp_unslash( $_POST['completedCells'] ), true );
		if ( is_array( $decoded ) ) {
			$raw_completed = $decoded;
		}
	}

	$completed_cells = diamondistry_sanitize_completed_cell_indexes(
		$raw_completed,
		$data['totalCells']
	);

	$selected_color = isset( $_POST['selectedColor'] ) ? absint( $_POST['selectedColor'] ) : 0;
	if ( $selected_color && ! in_array( $selected_color, $data['validColorIds'], true ) ) {
		$selected_color = 0;
	}

	$is_completed = count( $completed_cells ) === $data['totalCells'];

	$all_progress = diamondistry_get_all_user_progress( $user_id );
	$key          = (string) $painting_id;
	$old_progress = isset( $all_progress[ $key ] ) && is_array( $all_progress[ $key ] )
		? $all_progress[ $key ]
		: array();

	$old_completed_cells = diamondistry_sanitize_completed_cell_indexes(
		isset( $old_progress['completedCells'] ) && is_array( $old_progress['completedCells'] )
			? $old_progress['completedCells']
			: array(),
		$data['totalCells']
	);

	$progress_changed = $old_completed_cells !== $completed_cells;
	$last_worked_at   = diamondistry_get_progress_last_worked_at( $old_progress );

	if ( $progress_changed ) {
		$last_worked_at = current_time( 'timestamp', true );
	}

	$reward_claimed = ! empty( $old_progress['rewardClaimed'] );
	$was_completed  = ! empty( $old_progress['completed'] );
	$reward_granted = false;

	/*
	 * Grant only on an incomplete -> complete transition. This prevents an
	 * admin "Reset reward" from being immediately reclaimed while progress
	 * remains at 100%. Resetting progress first allows the reward to be earned
	 * again on a later genuine completion.
	 */
	if ( $is_completed && ! $was_completed && ! $reward_claimed ) {
		diamondistry_add_user_diamonds( $user_id, $data['reward'] );
		$reward_claimed = true;
		$reward_granted = true;
	}

	$all_progress[ $key ] = array(
		'completedCells' => $completed_cells,
		'selectedColor'  => $selected_color ?: null,
		'completed'      => $is_completed,
		'rewardClaimed'  => $reward_claimed,
		'lastWorkedAt'   => $last_worked_at,
		'updatedAt'      => current_time( 'mysql', true ),
	);

	update_user_meta( $user_id, DIAMONDISTRY_PROGRESS_META_KEY, $all_progress );

	wp_send_json_success(
		array(
			'completed'     => $is_completed,
			'rewardGranted' => $reward_granted,
			'reward'        => $reward_granted ? absint( $data['reward'] ) : 0,
			'balance'       => diamondistry_get_user_balance( $user_id ),
		)
	);
}

add_action( 'wp_ajax_diamondistry_save_progress', 'diamondistry_ajax_save_progress' );

/*
|--------------------------------------------------------------------------
| AJAX: Reset progress
|--------------------------------------------------------------------------
*/

function diamondistry_ajax_reset_progress() {
	check_ajax_referer( 'diamondistry_progress', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array(), 401 );
	}

	$user_id     = get_current_user_id();
	$painting_id = isset( $_POST['paintingId'] ) ? absint( $_POST['paintingId'] ) : 0;

	if ( ! $painting_id ) {
		wp_send_json_error( array(), 400 );
	}

	$painting = get_post( $painting_id );
	if ( ! $painting || 'ddp_painting' !== $painting->post_type ) {
		wp_send_json_error( array(), 404 );
	}

	$all_progress  = diamondistry_get_all_user_progress( $user_id );
	$key           = (string) $painting_id;
	$reward_claimed = ! empty( $all_progress[ $key ]['rewardClaimed'] );

	$all_progress[ $key ] = array(
		'completedCells' => array(),
		'selectedColor'  => null,
		'completed'      => false,
		'rewardClaimed'  => $reward_claimed,
		'lastWorkedAt'   => null,
		'updatedAt'      => current_time( 'mysql', true ),
	);

	update_user_meta( $user_id, DIAMONDISTRY_PROGRESS_META_KEY, $all_progress );

	wp_send_json_success(
		array(
			'balance' => diamondistry_get_user_balance( $user_id ),
		)
	);
}

add_action( 'wp_ajax_diamondistry_reset_progress', 'diamondistry_ajax_reset_progress' );
