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

/**
 * Privacy: don't load emoji images from s.w.org (WordPress.org, US). Browsers
 * render emoji natively, so nothing changes visually.
 */
function diamondistry_theme_disable_emoji() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'diamondistry_theme_disable_emoji' );

function diamondistry_theme_enqueue_assets() {
	$theme = wp_get_theme();
	wp_enqueue_style(
		'diamondistry-fonts',
		'https://fonts.bunny.net/css?family=fraunces:500,600|nunito:500,600,700,800&display=swap',
		array(),
		null
	);
	// Compiled from assets/scss (npm run build); file time busts the cache after every build.
	wp_enqueue_style(
		'diamondistry-theme',
		get_theme_file_uri( 'assets/css/main.css' ),
		array( 'diamondistry-fonts' ),
		(string) filemtime( get_theme_file_path( 'assets/css/main.css' ) )
	);
	// Smooth wheel scrolling. Not on the play page: the canvas has its own scroll/zoom container.
	if ( ! is_page( 'play' ) ) {
		wp_enqueue_script(
			'lenis',
			get_theme_file_uri( 'assets/js/lenis.min.js' ),
			array(),
			'1.1.13',
			true
		);
	}
	wp_enqueue_script(
		'diamondistry-nav',
		get_theme_file_uri( 'assets/js/nav.js' ),
		array(),
		$theme->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'diamondistry_theme_enqueue_assets' );

/**
 * Hide [data-animate] elements before first paint so reveals do not flicker.
 * Falls back to visible if nav.js never takes over, and stays off for reduced motion.
 */
function diamondistry_theme_reveal_flag() {
	echo "<script>if(!matchMedia('(prefers-reduced-motion: reduce)').matches){document.documentElement.classList.add('dd-anim');setTimeout(function(){if(!window.ddAnimReady){document.documentElement.classList.remove('dd-anim')}},2500)}</script>\n";
}
add_action( 'wp_head', 'diamondistry_theme_reveal_flag', 1 );

function diamondistry_theme_body_class( $classes ) {
	if ( is_page( array( 'paintings', 'dashboard', 'play', 'account' ) ) ) {
		$classes[] = 'diamondistry-app-screen';
	}
	if ( is_page( array( 'login', 'register' ) ) ) {
		$classes[] = 'dd-auth-screen';
	}
	return $classes;
}
add_filter( 'body_class', 'diamondistry_theme_body_class' );

function diamondistry_theme_compact_html( $html ) {
	return trim( preg_replace( '/>\s+</', '><', $html ) );
}

function diamondistry_theme_nav_items( $include_home = false ) {
	$items = array(
		array(
			'key'   => 'paintings',
			'label' => 'Paintings',
			'url'   => home_url( '/paintings/' ),
		),
		array(
			'key'   => 'how',
			'label' => 'How it works',
			'url'   => home_url( '/how-it-works/' ),
		),
		array(
			'key'   => 'about',
			'label' => 'About',
			'url'   => home_url( '/about/' ),
		),
	);

	if ( is_user_logged_in() ) {
		$items[] = array(
			'key'   => 'dashboard',
			'label' => 'My paintings',
			'url'   => home_url( '/dashboard/' ),
		);
	}

	if ( $include_home ) {
		array_unshift(
			$items,
			array(
				'key'   => 'home',
				'label' => 'Home',
				'url'   => home_url( '/' ),
			)
		);
	}

	return $items;
}

function diamondistry_theme_nav_is_current( $key ) {
	$pages = array(
		'paintings' => array( 'paintings', 'play' ),
		'how'       => array( 'how-it-works' ),
		'about'     => array( 'about' ),
		'dashboard' => array( 'dashboard' ),
	);

	if ( 'home' === $key ) {
		return is_front_page();
	}

	return isset( $pages[ $key ] ) && is_page( $pages[ $key ] );
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
		'about'     => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 20 9l-8 12L4 9Z"/><path d="M4 9h16"/></svg>',
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
		<div class="diamondistry-mobile-panel" data-lenis-prevent role="dialog" aria-modal="true" aria-label="Menu">
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
					<a class="diamondistry-mobile-user" href="<?php echo esc_url( home_url( '/account/' ) ); ?>">
						<span class="diamondistry-header-avatar"><?php echo get_avatar( $user->ID, 64 ); ?></span>
						My account
						<span class="diamondistry-header-balance-value">· <?php echo esc_html( number_format_i18n( $balance ) ); ?> 💎</span>
					</a>
					<a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M10 7V5a2 2 0 0 1 2-2h7v18h-7a2 2 0 0 1-2-2v-2"/><path d="M15 12H3m0 0 3-3m-3 3 3 3"/></svg>
						Log out
					</a>
				<?php else : ?>
					<a class="dd-button dd-button-primary dd-button--block" href="<?php echo esc_url( wp_registration_url() ); ?>">Create account</a>
					<a class="dd-button dd-button-secondary dd-button--block" href="<?php echo esc_url( wp_login_url( $dashboard_url ) ); ?>">Log in</a>
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

/** [diamondistry_image file="hero-landscape.jpg" class="..."]: decorative image from the theme assets folder. */
function diamondistry_theme_render_image( $atts ) {
	$atts = shortcode_atts(
		array(
			'file'  => '',
			'class' => '',
		),
		$atts
	);

	$file = sanitize_file_name( $atts['file'] );
	if ( ! $file || ! file_exists( get_theme_file_path( 'assets/' . $file ) ) ) {
		return '';
	}

	return sprintf(
		'<img class="%1$s" src="%2$s" alt="" loading="lazy" decoding="async">',
		esc_attr( $atts['class'] ),
		esc_url( get_theme_file_uri( 'assets/' . $file ) )
	);
}
add_shortcode( 'diamondistry_image', 'diamondistry_theme_render_image' );

function diamondistry_theme_render_header_account() {
	$dashboard_url = home_url( '/dashboard/' );

	if ( ! is_user_logged_in() ) {
		return sprintf(
			'<div class="diamondistry-header-account"><a class="diamondistry-header-login" href="%1$s">Log in</a><a class="dd-button dd-button-primary dd-button--sm" href="%2$s">Sign up</a></div>',
			esc_url( wp_login_url( $dashboard_url ) ),
			esc_url( wp_registration_url() )
		);
	}

	$user    = wp_get_current_user();
	$balance = function_exists( 'diamondistry_get_user_balance' )
		? diamondistry_get_user_balance( $user->ID )
		: 0;

	ob_start();
	?>
	<div class="diamondistry-header-account">
		<a class="diamondistry-header-balance" href="<?php echo esc_url( $dashboard_url ); ?>" aria-label="<?php echo esc_attr( sprintf( '%s diamonds, view my paintings', number_format_i18n( $balance ) ) ); ?>">
			<svg viewBox="0 0 20 20" width="16" height="16" aria-hidden="true"><path fill="#6ea2ff" d="M10 1.2 17.2 7.4 10 18.8 2.8 7.4Z"/><path fill="#d7cbff" d="M10 1.2 13.4 7.4 10 18.8 6.6 7.4Z"/></svg>
			<span class="diamondistry-header-balance-value"><?php echo esc_html( number_format_i18n( $balance ) ); ?></span>
		</a>
		<a class="diamondistry-header-avatar<?php echo is_page( 'account' ) ? ' is-current' : ''; ?>" href="<?php echo esc_url( home_url( '/account/' ) ); ?>" aria-label="My account">
			<?php echo get_avatar( $user->ID, 72 ); ?>
		</a>
	</div>
	<?php
	return diamondistry_theme_compact_html( ob_get_clean() );
}
add_shortcode( 'diamondistry_header_account', 'diamondistry_theme_render_header_account' );

function diamondistry_theme_render_footer() {
	$explore = array(
		'Paintings'    => home_url( '/paintings/' ),
		'How it works' => home_url( '/how-it-works/' ),
		'About'        => home_url( '/about/' ),
	);

	$account = is_user_logged_in()
		? array(
			'My paintings' => home_url( '/dashboard/' ),
			'My account'   => home_url( '/account/' ),
			'Log out'      => wp_logout_url( home_url( '/' ) ),
		)
		: array(
			'Log in'         => wp_login_url( home_url( '/dashboard/' ) ),
			'Create account' => wp_registration_url(),
		);

	$columns = array(
		'Explore' => $explore,
		'Account' => $account,
	);

	ob_start();
	?>
	<div class="dd-footer">
		<div class="dd-footer__top">
			<div class="dd-footer__brand">
				<?php echo diamondistry_theme_render_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
				<p>Relax, create and build your personal collection, one diamond at a time.</p>
			</div>
			<?php foreach ( $columns as $heading => $links ) : ?>
				<nav class="dd-footer__col" aria-label="<?php echo esc_attr( $heading ); ?>">
					<h2 class="dd-footer__heading"><?php echo esc_html( $heading ); ?></h2>
					<ul>
						<?php foreach ( $links as $label => $url ) : ?>
							<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endforeach; ?>
		</div>
		<div class="dd-footer__bottom">
			<p>© <?php echo esc_html( gmdate( 'Y' ) . ' ' . get_bloginfo( 'name' ) ); ?></p>
			<p>Made with 💎 for slow, happy moments.</p>
		</div>
	</div>
	<?php
	return diamondistry_theme_compact_html( ob_get_clean() );
}
add_shortcode( 'diamondistry_footer', 'diamondistry_theme_render_footer' );

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
	<div class="diamondistry-home-paintings" data-animate="stagger">
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
	return diamondistry_theme_compact_html( ob_get_clean() );
}
add_shortcode( 'diamondistry_home_showcase', 'diamondistry_theme_render_home_showcase' );
