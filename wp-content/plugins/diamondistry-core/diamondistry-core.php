<?php
/**
 * Plugin Name: Diamondistry Core
 * Description: Core functionality for the Diamondistry digital diamond painting platform.
 * Version: 0.1.0
 * Author: Diamondistry
 * Text Domain: diamondistry-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIAMONDISTRY_CORE_VERSION', '0.1.0' );
define( 'DIAMONDISTRY_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Render the Diamondistry painting prototype.
 */
function diamondistry_render_playground() {

	wp_enqueue_style(
		'diamondistry-play',
		DIAMONDISTRY_CORE_URL . 'assets/css/play.css',
		array(),
		DIAMONDISTRY_CORE_VERSION
	);

	wp_enqueue_script(
		'diamondistry-play',
		DIAMONDISTRY_CORE_URL . 'assets/js/play.js',
		array(),
		DIAMONDISTRY_CORE_VERSION,
		true
	);

	ob_start();
	?>

	<div class="diamondistry-app">

		<header class="diamondistry-game-header">
			<div>
				<p class="diamondistry-eyebrow">First painting</p>
				<h1>Diamond Meadow</h1>
			</div>

			<div class="diamondistry-progress-text">
				<span id="diamondistry-completed">0</span>
				/
				<span id="diamondistry-total">100</span>
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
				aria-label="Diamond painting canvas"
			></div>

			<aside class="diamondistry-sidebar">

				<h2>Kies een diamond</h2>

				<div
					id="diamondistry-palette"
					class="diamondistry-palette"
				></div>

				<p class="diamondistry-help">
					Kies een kleur en klik daarna op de vakjes met hetzelfde symbool.
				</p>

				<div
					id="diamondistry-message"
					class="diamondistry-message"
					aria-live="polite"
				></div>

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