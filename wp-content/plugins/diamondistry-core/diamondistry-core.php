<?php
/**
 * Plugin Name: Diamondistry Core
 * Description: Core functionality for the Diamondistry digital diamond painting platform.
 * Version: 0.7.0
 * Author: Diamondistry
 * Text Domain: diamondistry-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIAMONDISTRY_CORE_VERSION', '0.7.0' );
define( 'DIAMONDISTRY_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'DIAMONDISTRY_CORE_PATH', plugin_dir_path( __FILE__ ) );

/*
|--------------------------------------------------------------------------
| Admin files
|--------------------------------------------------------------------------
*/

require_once DIAMONDISTRY_CORE_PATH . 'includes/admin/painting-editor.php';

/*
|--------------------------------------------------------------------------
| Frontend styles
|--------------------------------------------------------------------------
*/

function diamondistry_enqueue_styles() {
	wp_enqueue_style(
		'diamondistry-play',
		DIAMONDISTRY_CORE_URL . 'assets/css/play.css',
		array(),
		DIAMONDISTRY_CORE_VERSION
	);
}

add_action(
	'wp_enqueue_scripts',
	'diamondistry_enqueue_styles'
);

/*
|--------------------------------------------------------------------------
| Painting Custom Post Type
|--------------------------------------------------------------------------
*/

function diamondistry_register_painting_post_type() {

	$labels = array(
		'name'               => 'Paintings',
		'singular_name'      => 'Painting',
		'add_new'            => 'Add Painting',
		'add_new_item'       => 'Add New Painting',
		'edit_item'          => 'Edit Painting',
		'new_item'           => 'New Painting',
		'view_item'          => 'View Painting',
		'search_items'       => 'Search Paintings',
		'not_found'          => 'No paintings found',
		'not_found_in_trash' => 'No paintings found in Trash',
		'menu_name'          => 'Paintings',
	);

	register_post_type(
		'ddp_painting',
		array(
			'labels' => $labels,

			'public' => false,

			'show_ui' => true,

			'show_in_menu' => true,

			'show_in_rest' => true,

			'menu_icon' => 'dashicons-art',

			'supports' => array(
				'title',
			),

			'has_archive' => false,

			'rewrite' => false,
		)
	);
}

add_action(
	'init',
	'diamondistry_register_painting_post_type'
);

/*
|--------------------------------------------------------------------------
| Painting Data Helper
|--------------------------------------------------------------------------
*/

function diamondistry_get_painting_data( $post ) {

	if (
		! $post ||
		'ddp_painting' !== $post->post_type
	) {
		return null;
	}

	$width = absint(
		get_post_meta(
			$post->ID,
			'_diamondistry_width',
			true
		)
	);

	$height = absint(
		get_post_meta(
			$post->ID,
			'_diamondistry_height',
			true
		)
	);

	$reward = absint(
		get_post_meta(
			$post->ID,
			'_diamondistry_reward',
			true
		)
	);

	$palette_raw = get_post_meta(
		$post->ID,
		'_diamondistry_palette',
		true
	);

	$pattern_raw = get_post_meta(
		$post->ID,
		'_diamondistry_pattern',
		true
	);

	$palette = json_decode(
		$palette_raw,
		true
	);

	if ( ! is_array( $palette ) ) {
		$palette = array();
	}

	$pattern = preg_split(
		'/[\s,]+/',
		$pattern_raw,
		-1,
		PREG_SPLIT_NO_EMPTY
	);

	$pattern = is_array( $pattern )
		? array_map(
			'absint',
			$pattern
		)
		: array();

	return array(
		'id'      => $post->post_name,
		'postId'  => $post->ID,
		'title'   => get_the_title( $post ),
		'width'   => $width,
		'height'  => $height,
		'reward'  => $reward,
		'palette' => $palette,
		'pattern' => $pattern,
	);
}

/*
|--------------------------------------------------------------------------
| Get Requested Painting
|--------------------------------------------------------------------------
*/

function diamondistry_get_requested_painting() {

	$slug = '';

	if ( isset( $_GET['painting'] ) ) {
		$slug = sanitize_title(
			wp_unslash(
				$_GET['painting']
			)
		);
	}

	if ( $slug ) {

		$painting = get_page_by_path(
			$slug,
			OBJECT,
			'ddp_painting'
		);

		if (
			$painting &&
			'publish' === $painting->post_status
		) {
			return $painting;
		}
	}

	$paintings = get_posts(
		array(
			'post_type'      => 'ddp_painting',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'ASC',
		)
	);

	if ( empty( $paintings ) ) {
		return null;
	}

	return $paintings[0];
}

/*
|--------------------------------------------------------------------------
| Play assets
|--------------------------------------------------------------------------
*/

function diamondistry_enqueue_play_assets( $painting ) {

	wp_enqueue_script(
		'diamondistry-play',
		DIAMONDISTRY_CORE_URL . 'assets/js/play.js',
		array(),
		DIAMONDISTRY_CORE_VERSION,
		true
	);

	$data = diamondistry_get_painting_data(
		$painting
	);

	wp_localize_script(
		'diamondistry-play',
		'DiamondistryPainting',
		$data ? $data : array()
	);
}

/*
|--------------------------------------------------------------------------
| Play Shortcode
|--------------------------------------------------------------------------
*/

function diamondistry_render_playground() {

	$painting = diamondistry_get_requested_painting();

	if ( ! $painting ) {
		return sprintf(
			'<div class="diamondistry-empty">%s</div>',
			esc_html__(
				'Er zijn nog geen paintings beschikbaar.',
				'diamondistry-core'
			)
		);
	}

	diamondistry_enqueue_play_assets(
		$painting
	);

	ob_start();
	?>

	<div class="diamondistry-app">

		<header class="diamondistry-game-header">

			<div>

				<p class="diamondistry-eyebrow">
					Diamond Painting
				</p>

				<h1 id="diamondistry-title">
					<?php
					echo esc_html(
						get_the_title(
							$painting
						)
					);
					?>
				</h1>

			</div>

			<div class="diamondistry-progress-text">
				<span id="diamondistry-completed">0</span>
				/
				<span id="diamondistry-total">0</span>
				diamonds
			</div>

		</header>

		<div class="diamondistry-progress">

			<div
				id="diamondistry-progress-bar"
				class="diamondistry-progress-bar"
			></div>

		</div>

		<div class="diamondistry-workspace">

			<div
				id="diamondistry-grid"
				class="diamondistry-grid"
				aria-label="<?php
					esc_attr_e(
						'Diamond painting canvas',
						'diamondistry-core'
					);
				?>"
			></div>

			<aside class="diamondistry-sidebar">

				<h2>
					Kies een diamond
				</h2>

				<div
					id="diamondistry-palette"
					class="diamondistry-palette"
				></div>

				<p class="diamondistry-help">
					Kies een kleur en plaats de diamonds op de vakjes met hetzelfde symbool.
				</p>

				<div
					id="diamondistry-message"
					class="diamondistry-message"
					aria-live="polite"
				></div>

				<button
					type="button"
					id="diamondistry-reset"
					class="diamondistry-reset"
				>
					Reset painting
				</button>

			</aside>

		</div>

	</div>

	<?php

	return ob_get_clean();
}

add_shortcode(
	'diamondistry_play',
	'diamondistry_render_playground'
);

/*
|--------------------------------------------------------------------------
| Gallery Shortcode
|--------------------------------------------------------------------------
*/

function diamondistry_render_gallery() {

	$paintings = get_posts(
		array(
			'post_type'      => 'ddp_painting',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	if ( empty( $paintings ) ) {
		return sprintf(
			'<div class="diamondistry-empty">%s</div>',
			esc_html__(
				'Er zijn nog geen paintings.',
				'diamondistry-core'
			)
		);
	}

	ob_start();
	?>

	<div class="diamondistry-gallery">

		<?php foreach ( $paintings as $painting ) : ?>

			<?php

			$data = diamondistry_get_painting_data(
				$painting
			);

			$play_url = add_query_arg(
				'painting',
				$painting->post_name,
				home_url( '/play/' )
			);

			?>

			<a
				class="diamondistry-gallery-card"
				href="<?php echo esc_url( $play_url ); ?>"
			>

				<div class="diamondistry-gallery-preview">
					<span aria-hidden="true">
						💎
					</span>
				</div>

				<div class="diamondistry-gallery-content">

					<h2>
						<?php
						echo esc_html(
							get_the_title(
								$painting
							)
						);
						?>
					</h2>

					<div class="diamondistry-gallery-meta">

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

					<span class="diamondistry-gallery-action">
						Start painting →
					</span>

				</div>

			</a>

		<?php endforeach; ?>

	</div>

	<?php

	return ob_get_clean();
}

add_shortcode(
	'diamondistry_gallery',
	'diamondistry_render_gallery'
);