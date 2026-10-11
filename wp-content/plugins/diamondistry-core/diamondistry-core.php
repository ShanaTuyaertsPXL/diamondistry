<?php
/**
 * Plugin Name: Diamondistry Core
 * Description: Core functionality for the Diamondistry digital diamond painting platform.
 * Version: 0.21.4
 * Author: Diamondistry
 * Text Domain: diamondistry-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIAMONDISTRY_CORE_VERSION', '0.21.4' );
define( 'DIAMONDISTRY_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'DIAMONDISTRY_CORE_PATH', plugin_dir_path( __FILE__ ) );

/*
|--------------------------------------------------------------------------
| Admin files
|--------------------------------------------------------------------------
*/

require_once DIAMONDISTRY_CORE_PATH . 'includes/progress.php';

require_once DIAMONDISTRY_CORE_PATH . 'includes/favorites.php';

require_once DIAMONDISTRY_CORE_PATH . 'includes/painting-cards.php';

require_once DIAMONDISTRY_CORE_PATH . 'includes/dashboard.php';

require_once DIAMONDISTRY_CORE_PATH . 'includes/account.php';

require_once DIAMONDISTRY_CORE_PATH . 'includes/faq.php';

require_once DIAMONDISTRY_CORE_PATH . 'includes/unlocks.php';

require_once DIAMONDISTRY_CORE_PATH . 'includes/challenge.php';

require_once DIAMONDISTRY_CORE_PATH . 'includes/privacy.php';

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
				'thumbnail',
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

function diamondistry_register_painting_taxonomy() {
	register_taxonomy(
		'painting_category',
		array( 'ddp_painting' ),
		array(
			'labels'            => array(
				'name'          => 'Categories',
				'singular_name' => 'Category',
				'menu_name'     => 'Categories',
				'all_items'     => 'All categories',
				'edit_item'     => 'Edit category',
				'add_new_item'  => 'Add category',
			),
			'public'            => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'hierarchical'      => true,
			'meta_box_cb'       => 'post_categories_meta_box',
			'rewrite'           => false,
		)
	);
}

add_action( 'init', 'diamondistry_register_painting_taxonomy' );

function diamondistry_seed_painting_categories() {
	if ( get_option( 'diamondistry_categories_seeded' ) ) {
		return;
	}

	$names = array( 'Animals', 'Nature', 'Flowers', 'Places', 'Food', 'Seasonal' );

	foreach ( $names as $name ) {
		if ( ! term_exists( $name, 'painting_category' ) ) {
			wp_insert_term( $name, 'painting_category' );
		}
	}

	update_option( 'diamondistry_categories_seeded', '1', false );
}

add_action( 'init', 'diamondistry_seed_painting_categories', 20 );

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

	$shape = get_post_meta(
		$post->ID,
		'_diamondistry_shape',
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
		'shape'   => 'round' === $shape ? 'round' : 'square',
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

/** Brand diamond icon (styled by the theme's .dd-gem), in place of the 💎 emoji. */
function diamondistry_gem() {
	return '<span class="dd-gem" role="img" aria-label="diamonds"></span>';
}

/** Logo for the download poster: the Customizer logo, else the theme's logo.png. Same origin, so the canvas stays exportable. */
function diamondistry_get_brand_logo_url() {
	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $logo_id ) {
		return (string) wp_get_attachment_image_url( $logo_id, 'full' );
	}

	return file_exists( get_theme_file_path( 'assets/logo.png' ) ) ? get_theme_file_uri( 'assets/logo.png' ) : '';
}

function diamondistry_enqueue_play_assets( $painting ) {

	wp_enqueue_script(
		'diamondistry-grid',
		DIAMONDISTRY_CORE_URL . 'assets/js/grid-canvas.js',
		array(),
		DIAMONDISTRY_CORE_VERSION,
		true
	);

	wp_enqueue_script(
		'diamondistry-download',
		DIAMONDISTRY_CORE_URL . 'assets/js/download.js',
		array( 'diamondistry-grid' ),
		DIAMONDISTRY_CORE_VERSION,
		true
	);

	wp_localize_script(
		'diamondistry-download',
		'DiamondistryDownloadConfig',
		array(
			'logo'     => diamondistry_get_brand_logo_url(),
			'siteName' => get_bloginfo( 'name' ),
		)
	);

	wp_enqueue_script(
		'diamondistry-play',
		DIAMONDISTRY_CORE_URL . 'assets/js/play.js',
		array( 'diamondistry-grid', 'diamondistry-download' ),
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
				'No paintings are available yet.',
				'diamondistry-core'
			)
		);
	}

	if ( function_exists( 'diamondistry_enqueue_painting_card_assets' ) ) {
		diamondistry_enqueue_painting_card_assets();
	}

	if ( ! diamondistry_user_can_play( get_current_user_id(), $painting ) ) {
		return diamondistry_compact_html( diamondistry_render_locked_painting( $painting ) );
	}

	diamondistry_enqueue_play_assets( $painting );

	$category_label = function_exists( 'diamondistry_get_painting_category_label' )
		? diamondistry_get_painting_category_label( $painting->ID )
		: '';

	$data = diamondistry_get_painting_data( $painting );

	ob_start();
	?>

	<div class="diamondistry-app">

		<nav class="diamondistry-breadcrumb" aria-label="Breadcrumb">
			<a href="<?php echo esc_url( home_url( '/paintings/' ) ); ?>">Paintings</a>
			<?php if ( $category_label ) : ?>
				<span aria-hidden="true">/</span><span><?php echo esc_html( $category_label ); ?></span>
			<?php endif; ?>
		</nav>

		<header class="diamondistry-game-header">

			<div>
				<h1 id="diamondistry-title">
					<?php echo esc_html( get_the_title( $painting ) ); ?>
				</h1>
				<?php if ( $data ) : ?>
					<ul class="diamondistry-meta">
						<li><?php echo esc_html( $data['width'] . ' × ' . $data['height'] ); ?> diamonds</li>
						<li><?php echo esc_html( count( $data['palette'] ) ); ?> colours</li>
						<li class="is-reward"><?php echo esc_html( number_format_i18n( $data['reward'] ) ); ?> <?php echo diamondistry_gem(); ?> reward</li>
						<?php if ( function_exists( 'diamondistry_is_current_challenge' ) && diamondistry_is_current_challenge( $painting->ID ) ) : ?>
							<li class="is-challenge"><a href="<?php echo esc_url( home_url( '/challenge/' ) ); ?>">🏆 Weekly challenge</a></li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
			</div>

			<div class="diamondistry-progress-row">
				<span id="diamondistry-progress-label">0%</span>
				<div class="diamondistry-progress">
					<div id="diamondistry-progress-bar" class="diamondistry-progress-bar"></div>
				</div>
			</div>

		</header>

		<?php if ( ! is_user_logged_in() ) : ?>
			<p class="diamondistry-guest-note">Playing as a guest. <a href="<?php echo esc_url( wp_login_url( add_query_arg( 'painting', $painting->post_name, home_url( '/play/' ) ) ) ); ?>">Log in</a> or <a href="<?php echo esc_url( wp_registration_url() ); ?>">create an account</a> to save your progress and earn diamonds.</p>
		<?php endif; ?>

		<div class="diamondistry-workspace">

			<aside class="diamondistry-colors">
				<h2>Colors</h2>
				<div id="diamondistry-palette" class="diamondistry-palette"></div>
				<div id="diamondistry-message" class="diamondistry-message" aria-live="polite"></div>
				<button type="button" id="diamondistry-reset" class="diamondistry-reset">Reset painting</button>
				<button type="button" id="diamondistry-download" class="dd-button dd-button-primary dd-button--block diamondistry-download" hidden><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14"/></svg>Download painting</button>
			</aside>

			<div class="diamondistry-board">
				<div class="diamondistry-stage">
					<div
						id="diamondistry-grid"
						class="diamondistry-grid"
						aria-label="<?php esc_attr_e( 'Diamond painting canvas', 'diamondistry-core' ); ?>"
					></div>
				</div>

				<aside class="diamondistry-play-tools">
					<div class="diamondistry-reference">
						<?php
						$cover_url = get_the_post_thumbnail_url( $painting, 'medium' );
						if ( $cover_url ) :
							?>
							<img src="<?php echo esc_url( $cover_url ); ?>" alt="">
						<?php else : ?>
							<?php echo diamondistry_render_painting_preview( $painting, null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</div>
					<div class="diamondistry-zoom">
						<button type="button" id="diamondistry-zoom-in" aria-label="Zoom in">+</button>
						<button type="button" id="diamondistry-zoom-out" aria-label="Zoom out">−</button>
						<button type="button" id="diamondistry-loupe" aria-label="Magnifier" aria-keyshortcuts="M" aria-pressed="false" title="Magnifier (M)"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="5.2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M14.5 14.5 19.2 19.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>
						<button type="button" id="diamondistry-fullscreen" aria-label="Fullscreen" aria-pressed="false"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 4H4v4M16 4h4v4M8 20H4v-4M16 20h4v-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>
					</div>
				</aside>
			</div>

		</div>

	</div>

	<?php

	// Compact so wpautop (wp:shortcode block) cannot inject <br>/<p> between tags.
	return diamondistry_compact_html( ob_get_clean() );
}

add_shortcode(
	'diamondistry_play',
	'diamondistry_render_playground'
);

/** Play page: the tab title is the painting, also for locked paintings and when JS is off. */
add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( is_page( 'play' ) ) {
			$painting = diamondistry_get_requested_painting();
			if ( $painting ) {
				$parts['title'] = get_the_title( $painting );
			}
		}
		return $parts;
	}
);

/*
|--------------------------------------------------------------------------
| Gallery Shortcode
|--------------------------------------------------------------------------
*/

function diamondistry_gallery_user_payload() {
	$payload = array(
		'loggedIn'  => is_user_logged_in(),
		'progress'  => array(),
		'balance'   => 0,
		'favorites' => array(),
		'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		'nonce'     => wp_create_nonce( 'diamondistry_favorite' ),
	);

	if ( is_user_logged_in() ) {
		$user_id                = get_current_user_id();
		$payload['progress']    = diamondistry_get_all_user_progress( $user_id );
		$payload['balance']     = diamondistry_get_user_balance( $user_id );
		$payload['favorites']   = diamondistry_get_user_favorite_ids( $user_id );
	}

	return $payload;
}

function diamondistry_enqueue_gallery_script() {
	diamondistry_enqueue_painting_card_assets();

	wp_enqueue_script(
		'diamondistry-gallery',
		DIAMONDISTRY_CORE_URL . 'assets/js/gallery.js',
		array( 'diamondistry-grid' ),
		DIAMONDISTRY_CORE_VERSION,
		true
	);

	wp_localize_script(
		'diamondistry-gallery',
		'DiamondistryGalleryUser',
		diamondistry_gallery_user_payload()
	);
}

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
				'No paintings yet.',
				'diamondistry-core'
			)
		);
	}


	$gallery_user = diamondistry_gallery_user_payload();
	diamondistry_enqueue_gallery_script();

	$categories = function_exists( 'diamondistry_get_painting_categories' )
		? diamondistry_get_painting_categories()
		: array();

	ob_start();
	?>

	<div class="diamondistry-gallery-page">
		<header class="diamondistry-gallery-intro">
			<h1>Paintings</h1>
			<p>Discover and complete beautiful diamond paintings. Earn diamonds and grow your collection.</p>
		</header>

		<div class="diamondistry-gallery-toolbar">
			<label class="diamondistry-search">
				<span class="screen-reader-text">Search paintings</span>
				<input id="diamondistry-gallery-search" type="search" placeholder="Search paintings..." autocomplete="off">
			</label>
			<div class="diamondistry-filters" role="tablist" aria-label="Categories">
				<button type="button" class="is-active" data-filter="all" role="tab" aria-selected="true">All</button>
				<?php foreach ( $categories as $category ) : ?>
					<button type="button" data-filter="<?php echo esc_attr( $category->slug ); ?>" role="tab" aria-selected="false">
						<?php echo esc_html( $category->name ); ?>
					</button>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="diamondistry-gallery-results">
			<p class="diamondistry-gallery-empty">No paintings match your search.</p>
			<div class="diamondistry-gallery">
			<?php foreach ( $paintings as $painting ) : ?>
				<?php
				$key      = (string) $painting->ID;
				$progress = isset( $gallery_user['progress'][ $key ] ) ? $gallery_user['progress'][ $key ] : null;

				echo diamondistry_render_painting_card(
					$painting,
					array(
						'context'  => 'gallery',
						'progress' => $progress,
					)
				);
				?>
			<?php endforeach; ?>
			</div>
		</div>
	</div>

	<?php

	return ob_get_clean();
}

add_shortcode(
	'diamondistry_gallery',
	'diamondistry_render_gallery'
);