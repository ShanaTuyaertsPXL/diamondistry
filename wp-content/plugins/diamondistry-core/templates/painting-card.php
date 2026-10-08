<?php
/**
 * Shared painting card template.
 *
 * Available:
 * $painting, $data, $state, $context, $progress, $play_url,
 * $category_label, $category_slugs, $cover_url, $is_favorite, $show_action
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status     = $state['status'];
$percentage = $state['percentage'];

if ( 'completed' === $status ) {
	$action_label = 'View painting →';
} elseif ( 'progress' === $status ) {
	$action_label = 'Continue Painting →';
} else {
	$action_label = 'Start painting →';
}
?>

<article
	class="diamondistry-painting-card diamondistry-painting-card--<?php echo esc_attr( $context ); ?> is-<?php echo esc_attr( $status ); ?>"
	data-painting-id="<?php echo esc_attr( $data['id'] ); ?>"
	data-painting-post-id="<?php echo esc_attr( $painting->ID ); ?>"
	data-total="<?php echo esc_attr( $state['total'] ); ?>"
	data-title="<?php echo esc_attr( get_the_title( $painting ) ); ?>"
	data-categories="<?php echo esc_attr( $category_slugs ); ?>"
>
	<div class="diamondistry-painting-card-preview<?php echo $cover_url ? ' has-cover' : ''; ?>">
		<a class="diamondistry-painting-card-media" href="<?php echo esc_url( $play_url ); ?>">
			<?php if ( $cover_url ) : ?>
				<img class="diamondistry-painting-card-cover" src="<?php echo esc_url( $cover_url ); ?>" alt="">
			<?php else : ?>
				<?php echo diamondistry_render_painting_preview( $painting, $progress ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</a>

		<button
			type="button"
			class="diamondistry-favorite<?php echo $is_favorite ? ' is-favorite' : ''; ?>"
			aria-pressed="<?php echo $is_favorite ? 'true' : 'false'; ?>"
			aria-label="<?php echo $is_favorite ? 'Remove from favourites' : 'Save to favourites'; ?>"
		>
			<span class="diamondistry-favorite-burst" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
			<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
				<path d="M12 20s-7-4.4-7-9a4 4 0 0 1 7-2 4 4 0 0 1 7 2c0 4.6-7 9-7 9Z" fill="currentColor" stroke="currentColor" stroke-width="1.4"/>
			</svg>
		</button>

		<span class="diamondistry-painting-card-badge" <?php echo 'completed' === $status ? '' : 'hidden'; ?>>
			<?php echo 'completed' === $status ? 'Completed' : ''; ?>
		</span>
	</div>

	<div class="diamondistry-painting-card-content">
		<h3>
			<a href="<?php echo esc_url( $play_url ); ?>"><?php echo esc_html( get_the_title( $painting ) ); ?></a>
		</h3>

		<?php if ( $category_label ) : ?>
			<p class="diamondistry-painting-card-category"><?php echo esc_html( $category_label ); ?></p>
		<?php endif; ?>

		<div class="diamondistry-painting-card-progress">
			<div class="diamondistry-painting-card-progress-track">
				<div class="diamondistry-painting-card-progress-bar" style="width: <?php echo esc_attr( $percentage ); ?>%;"></div>
			</div>
			<span class="diamondistry-painting-card-progress-text"><?php echo esc_html( $percentage ); ?>%</span>
		</div>

		<?php if ( 'completed' === $status ) : ?>
			<div class="diamondistry-painting-card-completed">
				<?php echo $state['rewardClaimed'] ? 'Reward claimed' : 'Reward available'; ?>
			</div>
		<?php endif; ?>

		<?php if ( $show_action ) : ?>
			<a class="diamondistry-painting-card-action" href="<?php echo esc_url( $play_url ); ?>"><?php echo esc_html( $action_label ); ?></a>
		<?php endif; ?>
	</div>
</article>
