<?php
/**
 * FAQ items: managed in wp-admin, rendered with [diamondistry_faq].
 *
 * Title = question, content = answer, "Order" (page attributes) = position.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function diamondistry_register_faq_post_type() {
	register_post_type(
		'ddp_faq',
		array(
			'labels'       => array(
				'name'          => 'FAQ',
				'singular_name' => 'Question',
				'add_new'       => 'Add question',
				'add_new_item'  => 'Add new question',
				'edit_item'     => 'Edit question',
				'all_items'     => 'All questions',
				'menu_name'     => 'FAQ',
				'not_found'     => 'No questions found',
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'show_in_rest' => true,
			'menu_icon'    => 'dashicons-editor-help',
			'supports'     => array( 'title', 'editor', 'page-attributes' ),
			'rewrite'      => false,
		)
	);
}

add_action( 'init', 'diamondistry_register_faq_post_type' );

/** Show questions in their display order in the admin list. */
add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( is_admin() && $query->is_main_query() && 'ddp_faq' === $query->get( 'post_type' ) && ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'menu_order' );
			$query->set( 'order', 'ASC' );
		}
	}
);

/** One-time import of the questions that used to live in the how-it-works template. */
function diamondistry_seed_faq() {
	if ( '1' === get_option( 'diamondistry_faq_seeded' ) ) {
		return;
	}

	$items = array(
		'Do I need any supplies?'           => 'No. Everything happens in your browser: no canvas, no beads, no tools. Just open a painting and start.',
		'Do I need an account?'             => 'You can try any painting as a guest. With an account your progress is saved to your account, you earn diamonds and you can keep favourites.',
		'What are diamonds?'                => 'Diamonds are the reward for finishing a painting. Each painting shows how many it is worth, and your balance is always visible at the top of the page.',
		'Can I play on my phone or tablet?' => 'Absolutely. Diamondistry works in any modern browser on phone, tablet and desktop. Use zoom for precise tapping on small screens.',
		'Can I start a painting over?'      => 'Yes. Use “Reset painting” below the colour list to clear the canvas and begin again.',
	);

	$order = 0;
	foreach ( $items as $question => $answer ) {
		wp_insert_post(
			array(
				'post_type'    => 'ddp_faq',
				'post_status'  => 'publish',
				'post_title'   => $question,
				'post_content' => "<!-- wp:paragraph -->\n<p>" . esc_html( $answer ) . "</p>\n<!-- /wp:paragraph -->",
				'menu_order'   => $order++,
			)
		);
	}

	update_option( 'diamondistry_faq_seeded', '1', false );
}

add_action( 'init', 'diamondistry_seed_faq', 30 );

function diamondistry_render_faq() {
	$items = get_posts(
		array(
			'post_type'      => 'ddp_faq',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'ASC',
			),
		)
	);

	if ( empty( $items ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="dd-faq" data-animate="stagger">
		<?php foreach ( $items as $index => $item ) : ?>
			<details name="faq" class="dd-faq__item"<?php echo 0 === $index ? ' open' : ''; ?>>
				<summary class="dd-faq__q"><?php echo esc_html( get_the_title( $item ) ); ?></summary>
				<div class="dd-faq__a"><div class="dd-faq__a-inner"><?php echo wp_kses_post( do_blocks( $item->post_content ) ); ?></div></div>
			</details>
		<?php endforeach; ?>
	</div>
	<?php
	// Compact so wpautop (wp:shortcode block) cannot inject <p>/<br> between tags.
	return diamondistry_compact_html( ob_get_clean() );
}

add_shortcode( 'diamondistry_faq', 'diamondistry_render_faq' );
