<?php
/**
 * Plugin Name: Diamondistry Core
 * Description: Core functionality for the Diamondistry digital diamond painting platform.
 * Version: 0.3.1
 * Author: Diamondistry
 * Text Domain: diamondistry-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIAMONDISTRY_CORE_VERSION', '0.3.1' );
define( 'DIAMONDISTRY_CORE_URL', plugin_dir_url( __FILE__ ) );


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
| Painting Meta Box
|--------------------------------------------------------------------------
*/

function diamondistry_add_painting_meta_box() {

	add_meta_box(
		'diamondistry-painting-data',
		'Painting Data',
		'diamondistry_render_painting_meta_box',
		'ddp_painting',
		'normal',
		'high'
	);
}

add_action(
	'add_meta_boxes',
	'diamondistry_add_painting_meta_box'
);


function diamondistry_render_painting_meta_box( $post ) {

	wp_nonce_field(
		'diamondistry_save_painting',
		'diamondistry_painting_nonce'
	);

	$width = get_post_meta(
		$post->ID,
		'_diamondistry_width',
		true
	);

	$height = get_post_meta(
		$post->ID,
		'_diamondistry_height',
		true
	);

	$reward = get_post_meta(
		$post->ID,
		'_diamondistry_reward',
		true
	);

	$palette = get_post_meta(
		$post->ID,
		'_diamondistry_palette',
		true
	);

	$pattern = get_post_meta(
		$post->ID,
		'_diamondistry_pattern',
		true
	);

	if ( ! $width ) {
		$width = 10;
	}

	if ( ! $height ) {
		$height = 10;
	}

	if ( '' === $reward ) {
		$reward = 50;
	}

	if ( ! $palette ) {
		$palette = wp_json_encode(
			array(
				array(
					'id'     => 1,
					'name'   => 'Rose',
					'hex'    => '#ef7c8e',
					'symbol' => '●',
				),
				array(
					'id'     => 2,
					'name'   => 'Sunshine',
					'hex'    => '#f5c451',
					'symbol' => '◆',
				),
				array(
					'id'     => 3,
					'name'   => 'Meadow',
					'hex'    => '#75b798',
					'symbol' => '▲',
				),
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
		);
	}

	?>

	<style>
		.diamondistry-admin-field {
			margin-bottom: 24px;
		}

		.diamondistry-admin-field label {
			display: block;
			margin-bottom: 6px;
			font-weight: 600;
		}

		.diamondistry-admin-row {
			display: grid;
			grid-template-columns: repeat(3, minmax(0, 1fr));
			gap: 20px;
		}

		.diamondistry-admin-field input[type="number"] {
			width: 100%;
			max-width: 180px;
		}

		.diamondistry-admin-field textarea {
			width: 100%;
			min-height: 220px;
			font-family: monospace;
		}

		.diamondistry-admin-help {
			margin-top: 6px;
			color: #646970;
		}

		@media (max-width: 782px) {
			.diamondistry-admin-row {
				grid-template-columns: 1fr;
			}
		}
	</style>


	<div class="diamondistry-admin-row">

		<div class="diamondistry-admin-field">
			<label for="diamondistry_width">
				Width
			</label>

			<input
				type="number"
				id="diamondistry_width"
				name="diamondistry_width"
				min="1"
				max="200"
				value="<?php echo esc_attr( $width ); ?>"
			>
		</div>


		<div class="diamondistry-admin-field">
			<label for="diamondistry_height">
				Height
			</label>

			<input
				type="number"
				id="diamondistry_height"
				name="diamondistry_height"
				min="1"
				max="200"
				value="<?php echo esc_attr( $height ); ?>"
			>
		</div>


		<div class="diamondistry-admin-field">
			<label for="diamondistry_reward">
				Reward
			</label>

			<input
				type="number"
				id="diamondistry_reward"
				name="diamondistry_reward"
				min="0"
				value="<?php echo esc_attr( $reward ); ?>"
			>

			<p class="diamondistry-admin-help">
				Diamonds earned after completion.
			</p>
		</div>

	</div>


	<div class="diamondistry-admin-field">

		<label for="diamondistry_palette">
			Palette JSON
		</label>

		<textarea
			id="diamondistry_palette"
			name="diamondistry_palette"
		><?php echo esc_textarea( $palette ); ?></textarea>

		<p class="diamondistry-admin-help">
			Each color needs an id, name, hex value and symbol.
		</p>

	</div>


	<div class="diamondistry-admin-field">

		<label for="diamondistry_pattern">
			Pattern
		</label>

		<textarea
			id="diamondistry_pattern"
			name="diamondistry_pattern"
			placeholder="1,1,1,2,2,3,3..."
		><?php echo esc_textarea( $pattern ); ?></textarea>

		<p class="diamondistry-admin-help">
			Enter one color ID per cell, separated by commas,
			spaces or line breaks.
			A 10 × 10 painting must contain exactly 100 values.
		</p>

	</div>

	<?php
}


/*
|--------------------------------------------------------------------------
| Save Painting
|--------------------------------------------------------------------------
*/

function diamondistry_save_painting( $post_id ) {

	if (
		! isset( $_POST['diamondistry_painting_nonce'] ) ||
		! wp_verify_nonce(
			sanitize_text_field(
				wp_unslash(
					$_POST['diamondistry_painting_nonce']
				)
			),
			'diamondistry_save_painting'
		)
	) {
		return;
	}

	if (
		defined( 'DOING_AUTOSAVE' ) &&
		DOING_AUTOSAVE
	) {
		return;
	}

	if (
		! current_user_can(
			'edit_post',
			$post_id
		)
	) {
		return;
	}


	$width = isset( $_POST['diamondistry_width'] )
		? absint( $_POST['diamondistry_width'] )
		: 10;

	$height = isset( $_POST['diamondistry_height'] )
		? absint( $_POST['diamondistry_height'] )
		: 10;

	$reward = isset( $_POST['diamondistry_reward'] )
		? absint( $_POST['diamondistry_reward'] )
		: 0;


	$width = max(
		1,
		min(
			200,
			$width
		)
	);

	$height = max(
		1,
		min(
			200,
			$height
		)
	);


	update_post_meta(
		$post_id,
		'_diamondistry_width',
		$width
	);

	update_post_meta(
		$post_id,
		'_diamondistry_height',
		$height
	);

	update_post_meta(
		$post_id,
		'_diamondistry_reward',
		$reward
	);


	if (
		isset(
			$_POST['diamondistry_palette']
		)
	) {

		$palette_raw =
			wp_unslash(
				$_POST['diamondistry_palette']
			);

		$palette_decoded =
			json_decode(
				$palette_raw,
				true
			);

		if (
			is_array(
				$palette_decoded
			)
		) {

			$clean_palette =
				array();

			foreach (
				$palette_decoded
				as
				$color
			) {

				if (
					! is_array(
						$color
					)
				) {
					continue;
				}

				$id =
					isset(
						$color['id']
					)
						? absint(
							$color['id']
						)
						: 0;

				$name =
					isset(
						$color['name']
					)
						? sanitize_text_field(
							$color['name']
						)
						: '';

				$hex =
					isset(
						$color['hex']
					)
						? sanitize_hex_color(
							$color['hex']
						)
						: '';

				$symbol =
					isset(
						$color['symbol']
					)
						? sanitize_text_field(
							$color['symbol']
						)
						: '';

				if (
					! $id ||
					! $name ||
					! $hex ||
					! $symbol
				) {
					continue;
				}

				$clean_palette[] =
					array(
						'id'     => $id,
						'name'   => $name,
						'hex'    => $hex,
						'symbol' => $symbol,
					);
			}

			if (
				! empty(
					$clean_palette
				)
			) {

				update_post_meta(
					$post_id,
					'_diamondistry_palette',
					wp_json_encode(
						$clean_palette,
						JSON_PRETTY_PRINT |
						JSON_UNESCAPED_UNICODE
					)
				);
			}
		}
	}


	if (
		isset(
			$_POST['diamondistry_pattern']
		)
	) {

		$pattern_raw =
			sanitize_textarea_field(
				wp_unslash(
					$_POST['diamondistry_pattern']
				)
			);

		$pattern_values =
			preg_split(
				'/[\s,]+/',
				$pattern_raw,
				-1,
				PREG_SPLIT_NO_EMPTY
			);

		$clean_pattern =
			array_map(
				'absint',
				$pattern_values
			);

		update_post_meta(
			$post_id,
			'_diamondistry_pattern',
			implode(
				',',
				$clean_pattern
			)
		);
	}
}

add_action(
	'save_post_ddp_painting',
	'diamondistry_save_painting'
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


	$width =
		absint(
			get_post_meta(
				$post->ID,
				'_diamondistry_width',
				true
			)
		);

	$height =
		absint(
			get_post_meta(
				$post->ID,
				'_diamondistry_height',
				true
			)
		);

	$reward =
		absint(
			get_post_meta(
				$post->ID,
				'_diamondistry_reward',
				true
			)
		);


	$palette_raw =
		get_post_meta(
			$post->ID,
			'_diamondistry_palette',
			true
		);

	$pattern_raw =
		get_post_meta(
			$post->ID,
			'_diamondistry_pattern',
			true
		);


	$palette =
		json_decode(
			$palette_raw,
			true
		);

	if (
		! is_array(
			$palette
		)
	) {
		$palette =
			array();
	}


	$pattern =
		preg_split(
			'/[\s,]+/',
			$pattern_raw,
			-1,
			PREG_SPLIT_NO_EMPTY
		);

	$pattern =
		is_array( $pattern )
			? array_map(
				'absint',
				$pattern
			)
			: array();


	return array(
		'id'      => $post->post_name,
		'postId'  => $post->ID,
		'title'   => get_the_title(
			$post
		),
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

	$slug =
		'';

	if (
		isset(
			$_GET['painting']
		)
	) {
		$slug =
			sanitize_title(
				wp_unslash(
					$_GET['painting']
				)
			);
	}


	if ( $slug ) {

		$painting =
			get_page_by_path(
				$slug,
				OBJECT,
				'ddp_painting'
			);

		if (
			$painting &&
			'publish' ===
				$painting->post_status
		) {
			return $painting;
		}
	}


	$paintings =
		get_posts(
			array(
				'post_type' =>
					'ddp_painting',

				'post_status' =>
					'publish',

				'posts_per_page' =>
					1,

				'orderby' =>
					'date',

				'order' =>
					'ASC',
			)
		);


	if (
		empty(
			$paintings
		)
	) {
		return null;
	}


	return $paintings[0];
}


/*
|--------------------------------------------------------------------------
| Play JavaScript
|--------------------------------------------------------------------------
*/

function diamondistry_enqueue_play_assets(
	$painting
) {

	wp_enqueue_script(
		'diamondistry-play',
		DIAMONDISTRY_CORE_URL .
			'assets/js/play.js',
		array(),
		DIAMONDISTRY_CORE_VERSION,
		true
	);


	$data =
		diamondistry_get_painting_data(
			$painting
		);

	wp_localize_script(
		'diamondistry-play',
		'DiamondistryPainting',
		$data
			? $data
			: array()
	);
}


/*
|--------------------------------------------------------------------------
| Play Shortcode
|--------------------------------------------------------------------------
*/

function diamondistry_render_playground() {

	$painting =
		diamondistry_get_requested_painting();


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

		<header
			class="diamondistry-game-header"
		>

			<div>

				<p
					class="diamondistry-eyebrow"
				>
					Diamond Painting
				</p>

				<h1
					id="diamondistry-title"
				>
					<?php
					echo esc_html(
						get_the_title(
							$painting
						)
					);
					?>
				</h1>

			</div>


			<div
				class="diamondistry-progress-text"
			>

				<span
					id="diamondistry-completed"
				>
					0
				</span>

				/

				<span
					id="diamondistry-total"
				>
					0
				</span>

				diamonds

			</div>

		</header>


		<div
			class="diamondistry-progress"
		>

			<div
				id="diamondistry-progress-bar"
				class="diamondistry-progress-bar"
			></div>

		</div>


		<div
			class="diamondistry-workspace"
		>

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


			<aside
				class="diamondistry-sidebar"
			>

				<h2>
					Kies een diamond
				</h2>


				<div
					id="diamondistry-palette"
					class="diamondistry-palette"
				></div>


				<p
					class="diamondistry-help"
				>
					Kies een kleur en plaats de
					diamonds op de vakjes met
					hetzelfde symbool.
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
				>Reset painting
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


	ob_start();

	?>

	<div
		class="diamondistry-gallery"
	>

		<?php

		foreach (
			$paintings
			as
			$painting
		) :

			$data =
				diamondistry_get_painting_data(
					$painting
				);

			$play_url =
				add_query_arg(
					'painting',
					$painting->post_name,
					home_url(
						'/play/'
					)
				);

			?>

			<a
				class="diamondistry-gallery-card"
				href="<?php
					echo esc_url(
						$play_url
					);
				?>"
			>

				<div
					class="diamondistry-gallery-preview"
				>

					<span
						aria-hidden="true"
					>
						💎
					</span>

				</div>


				<div
					class="diamondistry-gallery-content"
				>

					<h2>
						<?php
						echo esc_html(
							get_the_title(
								$painting
							)
						);
						?>
					</h2>


					<div
						class="diamondistry-gallery-meta"
					>

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


					<span
						class="diamondistry-gallery-action"
					>
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