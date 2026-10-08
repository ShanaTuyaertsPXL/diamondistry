<?php
/**
 * Shared painting preview template.
 *
 * Available:
 * $painting
 * $data
 * $state
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$palette_map =
	array();

foreach (
	$data['palette'] as
	$color
) {

	if (
		! isset(
			$color['id'],
			$color['hex']
		)
	) {
		continue;
	}

	$hex =
		sanitize_hex_color(
			$color['hex']
		);

	if ( ! $hex ) {
		continue;
	}

	$palette_map[
		absint(
			$color['id']
		)
	] =
		$hex;
}

$completed_lookup =
	array_fill_keys(
		$state[
			'completedCells'
		],
		true
	);

?>

<div
	class="
		diamondistry-card-preview-grid
		is-<?php echo esc_attr( $state['status'] ); ?>
	"
	style="
		--painting-width: <?php echo esc_attr( $data['width'] ); ?>;
		--painting-height: <?php echo esc_attr( $data['height'] ); ?>;
	"
	aria-hidden="true"
>

	<?php foreach (
		$data['pattern'] as
		$index =>
		$color_id
	) : ?>

		<?php

		$color_id =
			absint(
				$color_id
			);

		$hex =
			isset(
				$palette_map[
					$color_id
				]
			)
				? $palette_map[
					$color_id
				]
				: '#eeeeee';

		$is_filled =
			isset(
				$completed_lookup[
					$index
				]
			);

		?>

		<span
			class="
				diamondistry-card-preview-cell
				<?php
				echo $is_filled
					? 'is-filled'
					: '';
				?>
			"
			data-index="<?php echo esc_attr( $index ); ?>"
			style="--cell-color: <?php echo esc_attr( $hex ); ?>;"
		></span>

	<?php endforeach; ?>

</div>