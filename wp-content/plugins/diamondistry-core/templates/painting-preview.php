<?php
/**
 * Shared painting preview template.
 *
 * Available:
 * $painting
 * $data
 * $state
 *
 * Called from diamondistry_render_painting_preview() in includes/painting-cards.php.
 * Payload fields: width, height, status, palette (id => hex), pattern (color ids), filled (cell indexes).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$palette_map = array();

foreach ( $data['palette'] as $color ) {
	if ( ! isset( $color['id'], $color['hex'] ) ) {
		continue;
	}

	$hex = sanitize_hex_color( $color['hex'] );

	if ( ! $hex ) {
		continue;
	}

	$palette_map[ absint( $color['id'] ) ] = $hex;
}

$filled = array();

if ( isset( $state['status'] ) && 'progress' === $state['status'] ) {
	$filled = array_values(
		array_map(
			'intval',
			isset( $state['completedCells'] ) ? $state['completedCells'] : array()
		)
	);
}

$preview_payload = array(
	'width'   => absint( $data['width'] ),
	'height'  => absint( $data['height'] ),
	'status'  => isset( $state['status'] ) ? $state['status'] : 'new',
	'shape'   => ( isset( $data['shape'] ) && 'round' === $data['shape'] ) ? 'round' : 'square',
	'palette' => $palette_map,
	'pattern' => array_map( 'absint', $data['pattern'] ),
	'filled'  => $filled,
);

?>

<div
	class="diamondistry-card-preview-grid is-<?php echo esc_attr( $state['status'] ); ?>"
	style="--painting-width: <?php echo esc_attr( $data['width'] ); ?>; --painting-height: <?php echo esc_attr( $data['height'] ); ?>;"
	aria-hidden="true"
>
	<canvas class="diamondistry-preview-canvas" data-preview="<?php echo esc_attr( wp_json_encode( $preview_payload ) ); ?>"></canvas>
</div>
