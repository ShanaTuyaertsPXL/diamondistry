<?php
/**
 * Diamondistry theme functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function diamondistry_theme_setup() {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'title-tag' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 260,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
}
add_action( 'after_setup_theme', 'diamondistry_theme_setup' );

function diamondistry_theme_enqueue_assets() {
	$theme = wp_get_theme();
	wp_enqueue_style(
		'diamondistry-fonts',
		'https://fonts.bunny.net/css?family=fraunces:500,600|nunito:500,600,700,800&display=swap',
		array(),
		null
	);
	wp_enqueue_style(
		'diamondistry-theme',
		get_stylesheet_uri(),
		array( 'diamondistry-fonts' ),
		$theme->get( 'Version' )
	);
	wp_enqueue_script(
		'diamondistry-nav',
		get_theme_file_uri( 'assets/js/nav.js' ),
		array(),
		$theme->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'diamondistry_theme_enqueue_assets' );

function diamondistry_theme_body_class( $classes ) {
	if ( is_page( array( 'paintings', 'dashboard', 'play' ) ) ) {
		$classes[] = 'diamondistry-app-screen';
	}
	return $classes;
}
add_filter( 'body_class', 'diamondistry_theme_body_class' );

function diamondistry_theme_compact_html( $html ) {
	return trim( preg_replace( '/>\s+</', '><', $html ) );
}

function diamondistry_theme_nav_items( $include_home = false ) {
	$home = array(
		'key'   => 'home',
		'label' => 'Home',
		'url'   => home_url( '/' ),
	);
	$paintings = array(
		'key'   => 'paintings',
		'label' => 'Paintings',
		'url'   => home_url( '/paintings/' ),
	);
	$how = array(
		'key'   => 'how',
		'label' => 'How it works',
		'url'   => home_url( '/#how-it-works' ),
	);
	$dashboard = array(
		'key'   => 'dashboard',
		'label' => 'Dashboard',
		'url'   => home_url( '/dashboard/' ),
	);

	if ( $include_home ) {
		return array( $home, $paintings, $dashboard, $how );
	}

	return array( $paintings, $how, $dashboard );
}

function diamondistry_theme_nav_is_current( $key ) {
	if ( 'home' === $key ) {
		return is_front_page();
	}
	if ( 'paintings' === $key ) {
		return is_page( array( 'paintings', 'play' ) );
	}
	if ( 'dashboard' === $key ) {
		return is_page( 'dashboard' );
	}
	return false;
}

function diamondistry_theme_render_site_nav() {
	$links = array();

	foreach ( diamondistry_theme_nav_items() as $item ) {
		$links[] = sprintf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( $item['url'] ),
			diamondistry_theme_nav_is_current( $item['key'] ) ? ' class="is-current" aria-current="page"' : '',
			esc_html( $item['label'] )
		);
	}

	return '<nav class="diamondistry-site-nav" aria-label="Main navigation">' . implode( '', $links ) . '</nav>';
}
add_shortcode( 'diamondistry_site_nav', 'diamondistry_theme_render_site_nav' );

function diamondistry_theme_nav_icon( $key ) {
	$icons = array(
		'home'      => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1v-9.5Z"/></svg>',
		'paintings' => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="5" width="16" height="14" rx="2"/><path d="m8 15 2.5-3 2 2.2L15 11l3 4"/></svg>',
		'dashboard' => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="4" rx="1.5"/><rect x="13" y="10" width="7" height="10" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/></svg>',
		'how'       => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="8"/><path d="M12 11v5"/><path d="M12 8h.01"/></svg>',
	);
	return isset( $icons[ $key ] ) ? $icons[ $key ] : '';
}

function diamondistry_theme_logo_img() {
	$logo_id = (int) get_theme_mod( 'custom_logo' );

	if ( $logo_id ) {
		return wp_get_attachment_image(
			$logo_id,
			'full',
			false,
			array(
				'class' => 'diamondistry-logo custom-logo',
				'alt'   => get_bloginfo( 'name', 'display' ),
			)
		);
	}

	return '<img class="diamondistry-logo" src="' . esc_url( get_theme_file_uri( 'assets/logo.png' ) ) . '" alt="' . esc_attr( get_bloginfo( 'name', 'display' ) ) . '">';
}

function diamondistry_theme_render_logo() {
	return '<div class="diamondistry-brand-slot"><a class="diamondistry-brand" href="' . esc_url( home_url( '/' ) ) . '">' . diamondistry_theme_logo_img() . '</a></div>';
}
add_shortcode( 'diamondistry_logo', 'diamondistry_theme_render_logo' );

function diamondistry_theme_render_mobile_menu() {
	$dashboard_url = home_url( '/dashboard/' );
	$logged_in     = is_user_logged_in();
	$user          = wp_get_current_user();
	$balance       = ( $logged_in && function_exists( 'diamondistry_get_user_balance' ) )
		? diamondistry_get_user_balance( $user->ID )
		: 0;

	ob_start();
	?>
	<div id="diamondistry-mobile-menu" class="diamondistry-mobile-menu" hidden>
		<div class="diamondistry-mobile-panel" role="dialog" aria-modal="true" aria-label="Menu">
			<div class="diamondistry-mobile-panel-head">
				<?php echo diamondistry_theme_render_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
				<button type="button" class="diamondistry-menu-close" aria-label="Close menu">×</button>
			</div>
			<nav class="diamondistry-mobile-links" aria-label="Mobile">
				<?php foreach ( diamondistry_theme_nav_items( true ) as $item ) : ?>
					<a
						href="<?php echo esc_url( $item['url'] ); ?>"
						<?php echo diamondistry_theme_nav_is_current( $item['key'] ) ? 'class="is-current" aria-current="page"' : ''; ?>
					>
						<?php echo diamondistry_theme_nav_icon( $item['key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						<?php echo esc_html( $item['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="diamondistry-mobile-account">
				<?php if ( $logged_in ) : ?>
					<a class="diamondistry-header-balance" href="<?php echo esc_url( $dashboard_url ); ?>">
						<svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true"><path fill="#5b8def" d="M10 1.2 17.2 7.4 10 18.8 2.8 7.4Z"/><path fill="#c9b6ff" d="M10 1.2 13.4 7.4 10 18.8 6.6 7.4Z"/></svg>
						<span class="diamondistry-header-balance-value"><?php echo esc_html( number_format_i18n( $balance ) ); ?></span>
					</a>
					<a class="diamondistry-mobile-user" href="<?php echo esc_url( $dashboard_url ); ?>">
						<span class="diamondistry-header-avatar"><?php echo get_avatar( $user->ID, 64 ); ?></span>
						My Account
					</a>
					<a class="diamondistry-header-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M10 7V5a2 2 0 0 1 2-2h7v18h-7a2 2 0 0 1-2-2v-2"/><path d="M15 12H3m0 0 3-3m-3 3 3 3"/></svg>
						Log out
					</a>
				<?php else : ?>
					<a class="diamondistry-header-login" href="<?php echo esc_url( wp_login_url( $dashboard_url ) ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M10 7V5a2 2 0 0 1 2-2h7v18h-7a2 2 0 0 1-2-2v-2"/><path d="M15 12H3m0 0 3-3m-3 3 3 3"/></svg>
						Log in
					</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	return diamondistry_theme_compact_html( ob_get_clean() );
}
add_shortcode( 'diamondistry_mobile_menu', 'diamondistry_theme_render_mobile_menu' );

function diamondistry_theme_render_hero_photo() {
	$src = get_theme_file_uri( 'assets/hero-design.jpg' );
	return '<img class="diamondistry-home-hero-photo" src="' . esc_url( $src ) . '?v=2" alt="">';
}
add_shortcode( 'diamondistry_hero_photo', 'diamondistry_theme_render_hero_photo' );

function diamondistry_theme_render_header_account() {
	$dashboard_url = home_url( '/dashboard/' );

	if ( ! is_user_logged_in() ) {
		$login_url = wp_login_url( $dashboard_url );
		return sprintf(
			'<div class="diamondistry-header-account"><a class="diamondistry-header-login" href="%s">Log in</a></div>',
			esc_url( $login_url )
		);
	}

	$user = wp_get_current_user();
	$balance = function_exists( 'diamondistry_get_user_balance' )
		? diamondistry_get_user_balance( $user->ID )
		: 0;
	$logout_url = wp_logout_url( home_url( '/' ) );

	ob_start();
	?>
	<div class="diamondistry-header-account">
		<a class="diamondistry-header-balance" href="<?php echo esc_url( $dashboard_url ); ?>">
			<svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="#6ea2ff" d="M10 1.2 17.2 7.4 10 18.8 2.8 7.4Z"/><path fill="#d7cbff" d="M10 1.2 13.4 7.4 10 18.8 6.6 7.4Z"/></svg>
			<span class="diamondistry-header-balance-value"><?php echo esc_html( number_format_i18n( $balance ) ); ?></span>
		</a>
		<a class="diamondistry-header-avatar" href="<?php echo esc_url( $dashboard_url ); ?>" aria-label="Dashboard">
			<?php echo get_avatar( $user->ID, 72 ); ?>
		</a>
		<a class="diamondistry-header-logout" href="<?php echo esc_url( $logout_url ); ?>">Log out</a>
	</div>
	<?php
	return diamondistry_theme_compact_html( ob_get_clean() );
}
add_shortcode( 'diamondistry_header_account', 'diamondistry_theme_render_header_account' );

function diamondistry_theme_render_hero_art() {
	if (
		! function_exists( 'diamondistry_render_painting_preview' ) ||
		! function_exists( 'diamondistry_get_painting_data' )
	) {
		return '';
	}

	$paintings = get_posts(
		array(
			'post_type'      => 'ddp_painting',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	if ( empty( $paintings ) ) {
		return '';
	}

	$painting = $paintings[0];
	$data = diamondistry_get_painting_data( $painting );
	if ( ! $data ) {
		return '';
	}

	if ( function_exists( 'diamondistry_enqueue_painting_card_assets' ) ) {
		diamondistry_enqueue_painting_card_assets();
	}

	$play_url = add_query_arg( 'painting', $painting->post_name, home_url( '/play/' ) );

	ob_start();
	?>
	<a class="diamondistry-hero-art" href="<?php echo esc_url( $play_url ); ?>" style="text-decoration:none;">
		<div class="diamondistry-hero-art-inner">
			<div class="diamondistry-hero-art-preview">
				<?php echo diamondistry_render_painting_preview( $painting, null ); ?>
			</div>
			<div class="diamondistry-hero-art-meta">
				<div>
					<h3><?php echo esc_html( get_the_title( $painting ) ); ?></h3>
					<span>Featured painting</span>
				</div>
				<strong><?php echo esc_html( $data['reward'] ); ?> 💎</strong>
			</div>
		</div>
	</a>
	<?php
	return ob_get_clean();
}
add_shortcode( 'diamondistry_hero_art', 'diamondistry_theme_render_hero_art' );

function diamondistry_theme_render_home_showcase() {
	if ( ! function_exists( 'diamondistry_render_painting_card' ) ) {
		return '';
	}

	$paintings = get_posts(
		array(
			'post_type'      => 'ddp_painting',
			'post_status'    => 'publish',
			'posts_per_page' => 3,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);

	if ( empty( $paintings ) ) {
		return '';
	}

	if ( function_exists( 'diamondistry_enqueue_gallery_script' ) ) {
		diamondistry_enqueue_gallery_script();
	} elseif ( function_exists( 'diamondistry_enqueue_painting_card_assets' ) ) {
		diamondistry_enqueue_painting_card_assets();
	}

	$all_progress = array();
	if ( is_user_logged_in() && function_exists( 'diamondistry_get_all_user_progress' ) ) {
		$all_progress = diamondistry_get_all_user_progress( get_current_user_id() );
	}

	ob_start();
	?>
	<div class="diamondistry-home-paintings">
		<?php foreach ( $paintings as $painting ) : ?>
			<?php
			$key = (string) $painting->ID;
			$progress = isset( $all_progress[ $key ] ) ? $all_progress[ $key ] : null;
			echo diamondistry_render_painting_card(
				$painting,
				array(
					'context'  => 'home',
					'progress' => $progress,
				)
			);
			?>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'diamondistry_home_showcase', 'diamondistry_theme_render_home_showcase' );
