<?php
/**
 * Shared Diamondistry painting card helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/*
|--------------------------------------------------------------------------
| Shared card assets
|--------------------------------------------------------------------------
*/

function diamondistry_enqueue_painting_card_assets() {

	wp_enqueue_style(
		'diamondistry-painting-cards',
		DIAMONDISTRY_CORE_URL . 'assets/css/painting-cards.css',
		array(),
		DIAMONDISTRY_CORE_VERSION
	);
}


/*
|--------------------------------------------------------------------------
| Normalize progress
|--------------------------------------------------------------------------
*/

function diamondistry_normalize_painting_progress(
	$progress
) {

	if (
		! is_array(
			$progress
		)
	) {
		return array(
			'completedCells' =>
				array(),

			'completed' =>
				false,

			'rewardClaimed' =>
				false,

			'selectedColor' =>
				null,

			'updatedAt' =>
				null,

			'lastWorkedAt' =>
				null,
		);
	}

	$completed_cells =
		array();

	if (
		isset( $progress['completedCells'] ) &&
		is_array( $progress['completedCells'] )
	) {
		foreach ( $progress['completedCells'] as $index ) {
			if (
				is_int( $index ) ||
				( is_string( $index ) && preg_match( '/^\d+$/', $index ) )
			) {
				$completed_cells[] = (int) $index;
			}
		}

		$completed_cells =
			array_values(
				array_unique( $completed_cells )
			);
	}

	return array(
		'completedCells' =>
			$completed_cells,

		'completed' =>
			! empty(
				$progress[
					'completed'
				]
			),

		'rewardClaimed' =>
			! empty(
				$progress[
					'rewardClaimed'
				]
			),

		'selectedColor' =>
			isset(
				$progress[
					'selectedColor'
				]
			)
				? absint(
					$progress[
						'selectedColor'
					]
				)
				: null,

		'updatedAt' =>
			isset(
				$progress[
					'updatedAt'
				]
			)
				? $progress[
					'updatedAt'
				]
				: null,

		'lastWorkedAt' =>
			diamondistry_get_progress_last_worked_at(
				$progress
			),
	);
}


/*
|--------------------------------------------------------------------------
| Progress state
|--------------------------------------------------------------------------
*/

function diamondistry_get_painting_card_state(
	$painting,
	$progress = null
) {

	$data =
		diamondistry_get_painting_data(
			$painting
		);

	if ( ! $data ) {
		return array(
			'status' =>
				'new',

			'percentage' =>
				0,

			'completedCells' =>
				array(),

			'completedCount' =>
				0,

			'total' =>
				0,

			'rewardClaimed' =>
				false,
		);
	}

	$total =
		absint(
			$data['width']
		) *
		absint(
			$data['height']
		);

	$progress =
		diamondistry_normalize_painting_progress(
			$progress
		);

	$completed_cells =
		array_values(
			array_filter(
				$progress[
					'completedCells'
				],
				function (
					$index
				) use (
					$total
				) {

					return
						$index >= 0 &&
						$index < $total;
				}
			)
		);

	$completed_count =
		count(
			$completed_cells
		);

	$is_completed =
		$progress[
			'completed'
		] ||
		(
			$total > 0 &&
			$completed_count >=
				$total
		);

	if (
		$is_completed
	) {

		$status =
			'completed';

		$percentage =
			100;

	} elseif (
		$completed_count > 0
	) {

		$status =
			'progress';

		$percentage =
			$total > 0
				? min(
					100,
					round(
						(
							$completed_count /
							$total
						) *
						100
					)
				)
				: 0;

	} else {

		$status =
			'new';

		$percentage =
			0;
	}

	return array(
		'status' =>
			$status,

		'percentage' =>
			$percentage,

		'completedCells' =>
			$completed_cells,

		'completedCount' =>
			$completed_count,

		'total' =>
			$total,

		'rewardClaimed' =>
			$progress[
				'rewardClaimed'
			],
	);
}


/*
|--------------------------------------------------------------------------
| Render preview
|--------------------------------------------------------------------------
*/

function diamondistry_render_painting_preview(
	$painting,
	$progress = null
) {

	$data =
		diamondistry_get_painting_data(
			$painting
		);

	if ( ! $data ) {
		return '';
	}

	$state =
		diamondistry_get_painting_card_state(
			$painting,
			$progress
		);

	ob_start();

	include
		DIAMONDISTRY_CORE_PATH .
		'templates/painting-preview.php';

	return ob_get_clean();
}


/*
|--------------------------------------------------------------------------
| Render card
|--------------------------------------------------------------------------
*/

function diamondistry_render_painting_card(
	$painting,
	$args = array()
) {

	$data =
		diamondistry_get_painting_data(
			$painting
		);

	if ( ! $data ) {
		return '';
	}

	$defaults =
		array(
			'context' =>
				'gallery',

			'progress' =>
				null,
		);

	$args =
		wp_parse_args(
			$args,
			$defaults
		);

	$context =
		sanitize_key(
			$args[
				'context'
			]
		);

	$progress =
		$args[
			'progress'
		];

	$state =
		diamondistry_get_painting_card_state(
			$painting,
			$progress
		);

	$play_url =
		add_query_arg(
			'painting',
			$painting->post_name,
			home_url(
				'/play/'
			)
		);

	ob_start();

	include
		DIAMONDISTRY_CORE_PATH .
		'templates/painting-card.php';

	return ob_get_clean();
}