<?php
/**
 * Shared painting card template.
 *
 * Available:
 * $painting
 * $data
 * $state
 * $context
 * $progress
 * $play_url
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status =
	$state[
		'status'
	];

$percentage =
	$state[
		'percentage'
	];

if (
	'completed' ===
	$status
) {

	$action_label =
		'View painting →';

} elseif (
	'progress' ===
	$status
) {

	$action_label =
		'Continue painting →';

} else {

	$action_label =
		'Start painting →';
}

?>

<a
	class="
		diamondistry-painting-card
		diamondistry-painting-card--<?php echo esc_attr( $context ); ?>
		is-<?php echo esc_attr( $status ); ?>
	"
	href="<?php echo esc_url( $play_url ); ?>"
	data-painting-id="<?php echo esc_attr( $data['id'] ); ?>"
	data-painting-post-id="<?php echo esc_attr( $painting->ID ); ?>"
	data-total="<?php echo esc_attr( $state['total'] ); ?>"
>

	<div class="diamondistry-painting-card-preview">

		<?php
		echo diamondistry_render_painting_preview(
			$painting,
			$progress
		);
		?>

		<span
			class="diamondistry-painting-card-badge"
			<?php
			echo 'new' === $status
				? 'hidden'
				: '';
			?>
		>

			<?php

			if (
				'completed' ===
				$status
			) {

				echo '✓ Completed';

			} elseif (
				'progress' ===
				$status
			) {

				echo esc_html(
					$percentage .
					'%'
				);

			}

			?>

		</span>

	</div>


	<div class="diamondistry-painting-card-content">

		<h3>
			<?php
			echo esc_html(
				get_the_title(
					$painting
				)
			);
			?>
		</h3>


		<div class="diamondistry-painting-card-meta">

			<span>
				<?php
				echo esc_html(
					$data['width'] .
					' × ' .
					$data['height']
				);
				?>
			</span>

			<span>
				<?php
				echo esc_html(
					$data['reward']
				);
				?>
				💎
			</span>

		</div>


		<div
			class="diamondistry-painting-card-progress"
			<?php
			echo 'progress' === $status
				? ''
				: 'hidden';
			?>
		>

			<div class="diamondistry-painting-card-progress-track">

				<div
					class="diamondistry-painting-card-progress-bar"
					style="width: <?php echo esc_attr( $percentage ); ?>%;"
				></div>

			</div>

			<span class="diamondistry-painting-card-progress-text">
				<?php
				echo esc_html(
					$percentage .
					'% completed'
				);
				?>
			</span>

		</div>


		<?php if (
			'completed' ===
			$status
		) : ?>

			<div class="diamondistry-painting-card-completed">

				<?php if (
					$state[
						'rewardClaimed'
					]
				) : ?>

					✓ Reward claimed

				<?php else : ?>

					Reward available

				<?php endif; ?>

			</div>

		<?php endif; ?>


		<span class="diamondistry-painting-card-action">
			<?php
			echo esc_html(
				$action_label
			);
			?>
		</span>

	</div>

</a>