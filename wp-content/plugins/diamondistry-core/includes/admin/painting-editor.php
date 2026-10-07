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

	if (
		'ddp_painting' !== $post_type
	) {
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
| Editor rendering
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

	$palette = json_decode(
		$palette_raw,
		true
	);

	if (
		! is_array( $palette ) ||
		empty( $palette )
	) {
		$palette = array(
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

	$pattern = preg_split(
		'/[\s,]+/',
		$pattern_raw,
		-1,
		PREG_SPLIT_NO_EMPTY
	);

	if ( ! is_array( $pattern ) ) {
		$pattern = array();
	}

	$expected_cells =
		$width * $height;

	if (
		count( $pattern ) !==
		$expected_cells
	) {
		$default_id =
			isset( $palette[0]['id'] )
				? absint(
					$palette[0]['id']
				)
				: 1;

		$pattern =
			array_fill(
				0,
				$expected_cells,
				$default_id
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


		<div class="diamondistry-admin-section">

			<div class="diamondistry-admin-section-header">

				<div>
					<h3>
						Palette
					</h3>

					<p>
						Kies een kleur om ermee op het grid te tekenen.
					</p>
				</div>

				<button
					type="button"
					id="diamondistry-add-color"
					class="button"
				>
					+ Add color
				</button>

			</div>


			<div
				id="diamondistry-admin-palette"
				class="diamondistry-admin-palette"
			></div>

		</div>


		<div class="diamondistry-admin-section">

			<div class="diamondistry-admin-section-header">

				<div>
					<h3>
						Pattern
					</h3>

					<p>
						Selecteer een kleur hierboven en klik daarna op vakjes.
					</p>
				</div>

			</div>


			<div class="diamondistry-admin-grid-wrapper">

				<div
					id="diamondistry-admin-grid"
					class="diamondistry-admin-grid"
				></div>

			</div>

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
| Save editor
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

		if (
			is_array(
				$palette
			)
		) {

			$clean_palette =
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

		$values =
			preg_split(
				'/[\s,]+/',
				$pattern_raw,
				-1,
				PREG_SPLIT_NO_EMPTY
			);

		$values =
			is_array(
				$values
			)
				? array_map(
					'absint',
					$values
				)
				: array();

		update_post_meta(
			$post_id,
			'_diamondistry_pattern',
			implode(
				',',
				$values
			)
		);
	}
}

add_action(
	'save_post_ddp_painting',
	'diamondistry_save_painting'
);