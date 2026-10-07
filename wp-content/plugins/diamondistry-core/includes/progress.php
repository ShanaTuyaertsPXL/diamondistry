<?php
/**
 * Diamondistry user progress and rewards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define(
	'DIAMONDISTRY_PROGRESS_META_KEY',
	'_diamondistry_progress'
);

define(
	'DIAMONDISTRY_BALANCE_META_KEY',
	'_diamondistry_diamond_balance'
);


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

	return is_array( $progress )
		? $progress
		: array();
}


function diamondistry_get_user_painting_progress(
	$user_id,
	$painting_id
) {

	$all_progress =
		diamondistry_get_all_user_progress(
			$user_id
		);

	$key =
		(string) absint(
			$painting_id
		);

	if (
		! isset(
			$all_progress[ $key ]
		) ||
		! is_array(
			$all_progress[ $key ]
		)
	) {
		return array(
			'completedCells' => array(),
			'selectedColor'  => null,
			'completed'      => false,
			'rewardClaimed'  => false,
		);
	}

	$progress =
		$all_progress[ $key ];

	return array(
		'completedCells' =>
			isset(
				$progress['completedCells']
			) &&
			is_array(
				$progress['completedCells']
			)
				? array_values(
					array_map(
						'absint',
						$progress['completedCells']
					)
				)
				: array(),

		'selectedColor' =>
			isset(
				$progress['selectedColor']
			)
				? absint(
					$progress['selectedColor']
				)
				: null,

		'completed' =>
			! empty(
				$progress['completed']
			),

		'rewardClaimed' =>
			! empty(
				$progress['rewardClaimed']
			),
	);
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


function diamondistry_add_user_diamonds(
	$user_id,
	$amount
) {

	$amount =
		max(
			0,
			absint(
				$amount
			)
		);

	$current =
		diamondistry_get_user_balance(
			$user_id
		);

	$new_balance =
		$current +
		$amount;

	update_user_meta(
		$user_id,
		DIAMONDISTRY_BALANCE_META_KEY,
		$new_balance
	);

	return $new_balance;
}


/*
|--------------------------------------------------------------------------
| AJAX: Save progress
|--------------------------------------------------------------------------
*/

function diamondistry_ajax_save_progress() {

	check_ajax_referer(
		'diamondistry_progress',
		'nonce'
	);

	if ( ! is_user_logged_in() ) {
		wp_send_json_error(
			array(
				'message' =>
					'You must be logged in.',
			),
			401
		);
	}


	$user_id =
		get_current_user_id();

	$painting_id =
		isset(
			$_POST['paintingId']
		)
			? absint(
				$_POST['paintingId']
			)
			: 0;


	if ( ! $painting_id ) {
		wp_send_json_error(
			array(
				'message' =>
					'Invalid painting.',
			),
			400
		);
	}


	$painting =
		get_post(
			$painting_id
		);


	if (
		! $painting ||
		'ddp_painting' !==
			$painting->post_type ||
		'publish' !==
			$painting->post_status
	) {
		wp_send_json_error(
			array(
				'message' =>
					'Painting not available.',
			),
			404
		);
	}


	$data =
		diamondistry_get_painting_data(
			$painting
		);


	if ( ! $data ) {
		wp_send_json_error(
			array(
				'message' =>
					'Painting data unavailable.',
			),
			400
		);
	}


	$total_cells =
		$data['width'] *
		$data['height'];


	$completed_cells =
		array();


	if (
		isset(
			$_POST['completedCells']
		)
	) {

		$raw =
			json_decode(
				wp_unslash(
					$_POST['completedCells']
				),
				true
			);


		if ( is_array( $raw ) ) {

			foreach ( $raw as $index ) {

				$index =
					absint(
						$index
					);

				if (
					$index >= 0 &&
					$index <
						$total_cells
				) {
					$completed_cells[] =
						$index;
				}
			}
		}
	}


	$completed_cells =
		array_values(
			array_unique(
				$completed_cells
			)
		);


	$selected_color =
		isset(
			$_POST['selectedColor']
		)
			? absint(
				$_POST['selectedColor']
			)
			: 0;


	$is_completed =
		count(
			$completed_cells
		) >=
		$total_cells;


	$all_progress =
		diamondistry_get_all_user_progress(
			$user_id
		);


	$key =
		(string) $painting_id;


	$old_progress =
		isset(
			$all_progress[ $key ]
		) &&
		is_array(
			$all_progress[ $key ]
		)
			? $all_progress[ $key ]
			: array();


	$reward_claimed =
		! empty(
			$old_progress['rewardClaimed']
		);


	$reward_granted =
		false;


	/*
	 * Reward is only granted once for this painting.
	 */
	if (
		$is_completed &&
		! $reward_claimed
	) {

		diamondistry_add_user_diamonds(
			$user_id,
			$data['reward']
		);

		$reward_claimed =
			true;

		$reward_granted =
			true;
	}


	$all_progress[ $key ] =
		array(
			'completedCells' =>
				$completed_cells,

			'selectedColor' =>
				$selected_color,

			'completed' =>
				$is_completed,

			'rewardClaimed' =>
				$reward_claimed,

			'updatedAt' =>
				current_time(
					'mysql',
					true
				),
		);


	update_user_meta(
		$user_id,
		DIAMONDISTRY_PROGRESS_META_KEY,
		$all_progress
	);


	wp_send_json_success(
		array(
			'completed' =>
				$is_completed,

			'rewardGranted' =>
				$reward_granted,

			'reward' =>
				$reward_granted
					? absint(
						$data['reward']
					)
					: 0,

			'balance' =>
				diamondistry_get_user_balance(
					$user_id
				),
		)
	);
}

add_action(
	'wp_ajax_diamondistry_save_progress',
	'diamondistry_ajax_save_progress'
);


/*
|--------------------------------------------------------------------------
| AJAX: Reset progress
|--------------------------------------------------------------------------
*/

function diamondistry_ajax_reset_progress() {

	check_ajax_referer(
		'diamondistry_progress',
		'nonce'
	);


	if ( ! is_user_logged_in() ) {
		wp_send_json_error(
			array(),
			401
		);
	}


	$user_id =
		get_current_user_id();


	$painting_id =
		isset(
			$_POST['paintingId']
		)
			? absint(
				$_POST['paintingId']
			)
			: 0;


	if ( ! $painting_id ) {
		wp_send_json_error(
			array(),
			400
		);
	}


	$all_progress =
		diamondistry_get_all_user_progress(
			$user_id
		);


	$key =
		(string) $painting_id;


	$reward_claimed =
		false;


	if (
		isset(
			$all_progress[ $key ]['rewardClaimed']
		)
	) {
		$reward_claimed =
			(bool)
			$all_progress[ $key ]['rewardClaimed'];
	}


	/*
	 * Reset the painting itself,
	 * but NEVER reset rewardClaimed.
	 */
	$all_progress[ $key ] =
		array(
			'completedCells' =>
				array(),

			'selectedColor' =>
				null,

			'completed' =>
				false,

			'rewardClaimed' =>
				$reward_claimed,

			'updatedAt' =>
				current_time(
					'mysql',
					true
				),
		);


	update_user_meta(
		$user_id,
		DIAMONDISTRY_PROGRESS_META_KEY,
		$all_progress
	);


	wp_send_json_success(
		array(
			'balance' =>
				diamondistry_get_user_balance(
					$user_id
				),
		)
	);
}

add_action(
	'wp_ajax_diamondistry_reset_progress',
	'diamondistry_ajax_reset_progress'
);