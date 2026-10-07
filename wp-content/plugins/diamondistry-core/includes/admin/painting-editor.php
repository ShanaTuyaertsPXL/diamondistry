<?php
/**
 * Diamondistry visual painting editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
|--------------------------------------------------------------------------
| Meta box
|--------------------------------------------------------------------------
*/

function diamondistry_add_painting_meta_box() {
	add_meta_box(
		'diamondistry-painting-editor',
		'Painting Editor',
		'diamondistry_render_painting_editor',
		'ddp_painting',
		'normal',
		'high'
	);
}

add_action(
	'add_meta_boxes',
	'diamondistry_add_painting_meta_box'
);

/*
|--------------------------------------------------------------------------
| Admin assets
|--------------------------------------------------------------------------
*/

function diamondistry_enqueue_painting_admin_assets( $hook ) {
	global $post_type;

	if ( 'ddp_painting' !== $post_type ) {
		return;
	}

	if (
		'post.php' !== $hook &&
		'post-new.php' !== $hook
	) {
		return;
	}

	wp_enqueue_style(
		'diamondistry-admin-painting',
		DIAMONDISTRY_CORE_URL . 'assets/css/admin-painting.css',
		array(),
		DIAMONDISTRY_CORE_VERSION
	);

	wp_enqueue_script(
		'diamondistry-admin-painting',
		DIAMONDISTRY_CORE_URL . 'assets/js/admin-painting.js',
		array(),
		DIAMONDISTRY_CORE_VERSION,
		true
	);
}

add_action(
	'admin_enqueue_scripts',
	'diamondistry_enqueue_painting_admin_assets'
);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function diamondistry_get_default_palette() {
	return array(
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
	);
}

function diamondistry_parse_pattern_string( $pattern_raw ) {
	if ( ! is_string( $pattern_raw ) ) {
		return array();
	}

	$values = preg_split(
		'/[\s,]+/',
		$pattern_raw,
		-1,
		PREG_SPLIT_NO_EMPTY
	);

	if ( ! is_array( $values ) ) {
		return array();
	}

	return array_map(
		'absint',
		$values
	);
}

function diamondistry_validate_pattern(
	$width,
	$height,
	$palette,
	$pattern
) {
	$expected =
		absint( $width ) *
		absint( $height );

	if ( $expected < 1 ) {
		return false;
	}

	if ( count( $pattern ) !== $expected ) {
		return false;
	}

	if ( empty( $palette ) ) {
		return false;
	}

	$valid_ids = array();

	foreach ( $palette as $color ) {
		if ( isset( $color['id'] ) ) {
			$valid_ids[] =
				absint(
					$color['id']
				);
		}
	}

	foreach ( $pattern as $color_id ) {
		$color_id =
			absint(
				$color_id
			);

		if ( 0 === $color_id ) {
			return false;
		}

		if (
			! in_array(
				$color_id,
				$valid_ids,
				true
			)
		) {
			return false;
		}
	}

	return true;
}

/*
|--------------------------------------------------------------------------
| Render editor
|--------------------------------------------------------------------------
*/

function diamondistry_render_painting_editor( $post ) {

	wp_nonce_field(
		'diamondistry_save_painting',
		'diamondistry_painting_nonce'
	);

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

	$reward = get_post_meta(
		$post->ID,
		'_diamondistry_reward',
		true
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

	if ( ! $width ) {
		$width = 10;
	}

	if ( ! $height ) {
		$height = 10;
	}

	if ( '' === $reward ) {
		$reward = 50;
	}

	$palette =
		json_decode(
			$palette_raw,
			true
		);

	if (
		! is_array( $palette ) ||
		empty( $palette )
	) {
		$palette =
			diamondistry_get_default_palette();
	}

	$pattern =
		diamondistry_parse_pattern_string(
			$pattern_raw
		);

	$expected_cells =
		$width *
		$height;

	if (
		count( $pattern ) !==
		$expected_cells
	) {
		$pattern =
			array_fill(
				0,
				$expected_cells,
				0
			);
	}

	?>

	<div
		id="diamondistry-admin-editor"
		class="diamondistry-admin-editor"
	>

		<div class="diamondistry-admin-settings">

			<div class="diamondistry-admin-field">

				<label for="diamondistry_width">
					Width
				</label>

				<input
					type="number"
					id="diamondistry_width"
					name="diamondistry_width"
					min="1"
					max="100"
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
					max="100"
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

			</div>

			<button
				type="button"
				id="diamondistry-resize-grid"
				class="button"
			>
				Update grid size
			</button>

		</div>

		<div class="diamondistry-admin-workspace">

			<!-- Main pattern editor -->

			<section class="diamondistry-admin-main">

				<div class="diamondistry-admin-panel">

					<div class="diamondistry-admin-panel-header">

						<div>
							<h3>Pattern</h3>

							<p>
								Teken het patroon. Alle vakjes moeten ingevuld zijn
								voor publicatie.
							</p>
						</div>

					</div>

					<div class="diamondistry-admin-toolbar">

						<div class="diamondistry-admin-toolbar-group">

							<button
								type="button"
								id="diamondistry-undo"
								class="button"
								disabled
							>
								↶ Undo
							</button>

							<button
								type="button"
								id="diamondistry-redo"
								class="button"
								disabled
							>
								↷ Redo
							</button>

						</div>

						<div class="diamondistry-admin-toolbar-group">

							<button
								type="button"
								id="diamondistry-paint-tool"
								class="button button-primary"
							>
								Paint
							</button>

							<button
								type="button"
								id="diamondistry-eraser"
								class="button"
							>
								Eraser
							</button>

							<button
								type="button"
								id="diamondistry-fill-row"
								class="button"
							>
								Fill row
							</button>

							<button
								type="button"
								id="diamondistry-fill-column"
								class="button"
							>
								Fill column
							</button>

							<button
								type="button"
								id="diamondistry-fill-all"
								class="button"
							>
								Fill all
							</button>

							<button
								type="button"
								id="diamondistry-clear-grid"
								class="button"
							>
								Clear
							</button>

						</div>

					</div>

					<div
						id="diamondistry-selected-color"
						class="diamondistry-selected-color"
					></div>

					<div
						id="diamondistry-admin-status"
						class="diamondistry-admin-status"
					></div>

					<div class="diamondistry-admin-grid-wrapper">

						<div
							id="diamondistry-admin-grid"
							class="diamondistry-admin-grid"
						></div>

					</div>

				</div>

			</section>

			<!-- Sidebar -->

			<aside class="diamondistry-admin-sidebar">

				<div class="diamondistry-admin-panel">

					<div class="diamondistry-admin-panel-header diamondistry-admin-palette-header">

						<div>
							<h3>Palette</h3>

							<p>
								Klik een kleur om ermee te tekenen.
							</p>
						</div>

						<button
							type="button"
							id="diamondistry-add-color"
							class="button"
						>
							+ Add
						</button>

					</div>

					<div
						id="diamondistry-admin-palette"
						class="diamondistry-admin-palette"
					></div>

				</div>

				<div class="diamondistry-admin-panel">

					<div class="diamondistry-admin-panel-header">

						<div>
							<h3>Live preview</h3>

							<p>
								Kleurweergave zonder symbolen.
							</p>
						</div>

					</div>

					<div class="diamondistry-admin-preview-frame">

						<div
							id="diamondistry-admin-preview"
							class="diamondistry-admin-preview"
						></div>

					</div>

					<div
						id="diamondistry-admin-preview-info"
						class="diamondistry-admin-preview-info"
					></div>

				</div>

			</aside>

		</div>

		<textarea
			id="diamondistry_palette"
			name="diamondistry_palette"
			hidden
		><?php echo esc_textarea( wp_json_encode( $palette ) ); ?></textarea>

		<textarea
			id="diamondistry_pattern"
			name="diamondistry_pattern"
			hidden
		><?php echo esc_textarea( implode( ',', $pattern ) ); ?></textarea>

	</div>

	<?php
}

/*
|--------------------------------------------------------------------------
| Save
|--------------------------------------------------------------------------
*/

function diamondistry_save_painting( $post_id ) {

	if (
		! isset(
			$_POST['diamondistry_painting_nonce']
		) ||
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

	$width =
		isset(
			$_POST['diamondistry_width']
		)
			? absint(
				$_POST['diamondistry_width']
			)
			: 10;

	$height =
		isset(
			$_POST['diamondistry_height']
		)
			? absint(
				$_POST['diamondistry_height']
			)
			: 10;

	$reward =
		isset(
			$_POST['diamondistry_reward']
		)
			? absint(
				$_POST['diamondistry_reward']
			)
			: 0;

	$width =
		max(
			1,
			min(
				100,
				$width
			)
		);

	$height =
		max(
			1,
			min(
				100,
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

	/*
	|--------------------------------------------------------------------------
	| Palette
	|--------------------------------------------------------------------------
	*/

	$clean_palette =
		array();

	if (
		isset(
			$_POST['diamondistry_palette']
		)
	) {
		$palette_raw =
			wp_unslash(
				$_POST['diamondistry_palette']
			);

		$palette =
			json_decode(
				$palette_raw,
				true
			);

		if ( is_array( $palette ) ) {

			$used_symbols =
				array();

			foreach (
				$palette
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

				if (
					in_array(
						$symbol,
						$used_symbols,
						true
					)
				) {
					continue;
				}

				$used_symbols[] =
					$symbol;

				$clean_palette[] =
					array(
						'id'     => $id,
						'name'   => $name,
						'hex'    => $hex,
						'symbol' => $symbol,
					);
			}
		}
	}

	if (
		empty(
			$clean_palette
		)
	) {
		$clean_palette =
			diamondistry_get_default_palette();
	}

	update_post_meta(
		$post_id,
		'_diamondistry_palette',
		wp_json_encode(
			$clean_palette,
			JSON_UNESCAPED_UNICODE
		)
	);

	/*
	|--------------------------------------------------------------------------
	| Pattern
	|--------------------------------------------------------------------------
	*/

	$values =
		array();

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

		$values =
			diamondistry_parse_pattern_string(
				$pattern_raw
			);
	}

	$expected =
		$width *
		$height;

	if (
		count( $values ) !==
		$expected
	) {
		$values =
			array_fill(
				0,
				$expected,
				0
			);
	}

	update_post_meta(
		$post_id,
		'_diamondistry_pattern',
		implode(
			',',
			$values
		)
	);

	$is_complete =
		diamondistry_validate_pattern(
			$width,
			$height,
			$clean_palette,
			$values
		);

	update_post_meta(
		$post_id,
		'_diamondistry_complete',
		$is_complete
			? '1'
			: '0'
	);
}

add_action(
	'save_post_ddp_painting',
	'diamondistry_save_painting'
);

/*
|--------------------------------------------------------------------------
| Prevent incomplete paintings from publishing
|--------------------------------------------------------------------------
*/

function diamondistry_prevent_incomplete_painting_publish(
	$data,
	$postarr
) {

	if (
		'ddp_painting' !==
		$data['post_type']
	) {
		return $data;
	}

	if (
		'publish' !==
		$data['post_status']
	) {
		return $data;
	}

	if (
		! isset(
			$_POST['diamondistry_palette'],
			$_POST['diamondistry_pattern'],
			$_POST['diamondistry_width'],
			$_POST['diamondistry_height']
		)
	) {
		return $data;
	}

	$width =
		max(
			1,
			absint(
				$_POST['diamondistry_width']
			)
		);

	$height =
		max(
			1,
			absint(
				$_POST['diamondistry_height']
			)
		);

	$palette =
		json_decode(
			wp_unslash(
				$_POST['diamondistry_palette']
			),
			true
		);

	$pattern =
		diamondistry_parse_pattern_string(
			sanitize_textarea_field(
				wp_unslash(
					$_POST['diamondistry_pattern']
				)
			)
		);

	if (
		! is_array(
			$palette
		) ||
		! diamondistry_validate_pattern(
			$width,
			$height,
			$palette,
			$pattern
		)
	) {
		$data['post_status'] =
			'draft';
	}

	return $data;
}

add_filter(
	'wp_insert_post_data',
	'diamondistry_prevent_incomplete_painting_publish',
	10,
	2
);