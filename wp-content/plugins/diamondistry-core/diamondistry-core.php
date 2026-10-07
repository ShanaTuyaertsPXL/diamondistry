<?php
/**
 * Plugin Name: Diamondistry Core
 * Description: Core functionality for the Diamondistry digital diamond painting platform.
 * Version: 0.12.0
 * Author: Diamondistry
 * Text Domain: diamondistry-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIAMONDISTRY_CORE_VERSION', '0.12.0' );
define( 'DIAMONDISTRY_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'DIAMONDISTRY_CORE_PATH', plugin_dir_path( __FILE__ ) );

/*
|--------------------------------------------------------------------------
| Admin files
|--------------------------------------------------------------------------
*/

require_once DIAMONDISTRY_CORE_PATH . 'includes/progress.php';

require_once DIAMONDISTRY_CORE_PATH . 'includes/painting-cards.php';

require_once DIAMONDISTRY_CORE_PATH . 'includes/dashboard.php';

require_once DIAMONDISTRY_CORE_PATH . 'includes/admin/painting-editor.php';

require_once DIAMONDISTRY_CORE_PATH . 'includes/admin/players.php';

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

	$user_progress =
	array(
		'loggedIn' =>
			is_user_logged_in(),

		'progress' =>
			null,

		'balance' =>
			0,

		'ajaxUrl' =>
			admin_url(
				'admin-ajax.php'
			),

		'nonce' =>
			wp_create_nonce(
				'diamondistry_progress'
			),
	);


if (
	is_user_logged_in()
) {

	$user_id =
		get_current_user_id();

	$user_progress['progress'] =
		diamondistry_get_user_painting_progress(
			$user_id,
			$painting->ID
		);

	$user_progress['balance'] =
		diamondistry_get_user_balance(
			$user_id
		);
}


wp_localize_script(
	'diamondistry-play',
	'DiamondistryUser',
	$user_progress
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

	$paintings =
		get_posts(
			array(
				'post_type' =>
					'ddp_painting',

				'post_status' =>
					'publish',

				'posts_per_page' =>
					-1,

				'orderby' =>
					'title',

				'order' =>
					'ASC',
			)
		);

	if (
		empty(
			$paintings
		)
	) {
		return sprintf(
			'<div class="diamondistry-empty">%s</div>',
			esc_html__(
				'Er zijn nog geen paintings.',
				'diamondistry-core'
			)
		);
	}


	diamondistry_enqueue_painting_card_assets();


	wp_enqueue_script(
		'diamondistry-gallery',
		DIAMONDISTRY_CORE_URL . 'assets/js/gallery.js',
		array(),
		DIAMONDISTRY_CORE_VERSION,
		true
	);


	$gallery_user =
		array(
			'loggedIn' =>
				is_user_logged_in(),

			'progress' =>
				array(),

			'balance' =>
				0,
		);


	if (
		is_user_logged_in()
	) {

		$user_id =
			get_current_user_id();

		$gallery_user[
			'progress'
		] =
			diamondistry_get_all_user_progress(
				$user_id
			);

		$gallery_user[
			'balance'
		] =
			diamondistry_get_user_balance(
				$user_id
			);
	}


	wp_localize_script(
		'diamondistry-gallery',
		'DiamondistryGalleryUser',
		$gallery_user
	);


	ob_start();

	?>

	<div class="diamondistry-gallery">

		<?php foreach (
			$paintings as
			$painting
		) : ?>

			<?php

			$progress =
				null;

			if (
				is_user_logged_in()
			) {

				$key =
					(string)
					$painting->ID;

				if (
					isset(
						$gallery_user[
							'progress'
						][
							$key
						]
					)
				) {
					$progress =
						$gallery_user[
							'progress'
						][
							$key
						];
				}
			}

			echo diamondistry_render_painting_card(
				$painting,
				array(
					'context' =>
						'gallery',

					'progress' =>
						$progress,
				)
			);

			?>

		<?php endforeach; ?>

	</div>

	<?php

	return ob_get_clean();
}

add_shortcode(
	'diamondistry_gallery',
	'diamondistry_render_gallery'
);