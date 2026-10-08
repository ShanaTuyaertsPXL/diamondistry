<?php
/**
 * Diamondistry frontend user dashboard.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/*
|--------------------------------------------------------------------------
| Dashboard assets
|--------------------------------------------------------------------------
*/

function diamondistry_enqueue_dashboard_assets() {

	diamondistry_enqueue_painting_card_assets();

	wp_enqueue_style(
		'diamondistry-dashboard',
		DIAMONDISTRY_CORE_URL . 'assets/css/dashboard.css',
		array(
			'diamondistry-painting-cards',
		),
		DIAMONDISTRY_CORE_VERSION
	);

	wp_enqueue_script(
		'diamondistry-dashboard',
		DIAMONDISTRY_CORE_URL . 'assets/js/dashboard.js',
		array(),
		DIAMONDISTRY_CORE_VERSION,
		true
	);

	if ( function_exists( 'diamondistry_enqueue_gallery_script' ) ) {
		diamondistry_enqueue_gallery_script();
	}
}


/*
|--------------------------------------------------------------------------
| Dashboard shortcode
|--------------------------------------------------------------------------
*/

function diamondistry_render_dashboard() {

	diamondistry_enqueue_dashboard_assets();


	/*
	|--------------------------------------------------------------------------
	| Logged out
	|--------------------------------------------------------------------------
	*/

	if (
		! is_user_logged_in()
	) {

		$login_url =
			wp_login_url(
				home_url(
					'/dashboard/'
				)
			);

		ob_start();

		?>

		<div class="diamondistry-dashboard">

			<div class="diamondistry-dashboard-login">

				<div class="diamondistry-dashboard-login-icon">
					💎
				</div>

				<h1>
					Your Diamondistry dashboard
				</h1>

				<p>
					Log in to see your progress, completed paintings and diamond balance.
				</p>

				<a
					class="diamondistry-dashboard-primary-button"
					href="<?php echo esc_url( $login_url ); ?>"
				>
					Log in
				</a>

			</div>

		</div>

		<?php

		return ob_get_clean();
	}


	/*
	|--------------------------------------------------------------------------
	| User data
	|--------------------------------------------------------------------------
	*/

	$user_id =
		get_current_user_id();

	$user =
		wp_get_current_user();

	$balance =
		diamondistry_get_user_balance(
			$user_id
		);

	$all_progress =
		diamondistry_get_all_user_progress(
			$user_id
		);

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


	/*
	|--------------------------------------------------------------------------
	| Painting lookup
	|--------------------------------------------------------------------------
	*/

	$painting_lookup =
		array();

	foreach (
		$paintings as
		$painting
	) {

		$painting_lookup[
			(string)
			$painting->ID
		] =
			$painting;
	}


	/*
	|--------------------------------------------------------------------------
	| Split progress
	|--------------------------------------------------------------------------
	*/

	$in_progress =
		array();

	$completed =
		array();

	foreach (
		$all_progress as
		$painting_id =>
		$painting_progress
	) {

		$key =
			(string)
			$painting_id;

		if (
			! isset(
				$painting_lookup[
					$key
				]
			)
		) {
			continue;
		}

		if (
			! is_array(
				$painting_progress
			)
		) {
			continue;
		}

		$painting =
			$painting_lookup[
				$key
			];

		$state =
			diamondistry_get_painting_card_state(
				$painting,
				$painting_progress
			);

		$updated =
			diamondistry_get_progress_last_worked_at(
				$painting_progress
			);

		$updated =
			$updated
				? $updated
				: 0;

		if (
			'completed' ===
			$state['status']
		) {

			$completed[] =
				array(
					'painting' =>
						$painting,

					'progress' =>
						$painting_progress,

					'updated' =>
						$updated,
				);

			continue;
		}

		if (
			'progress' ===
			$state['status']
		) {

			$in_progress[] =
				array(
					'painting' =>
						$painting,

					'progress' =>
						$painting_progress,

					'updated' =>
						$updated,
				);
		}
	}


	/*
	|--------------------------------------------------------------------------
	| Most recent first
	|--------------------------------------------------------------------------
	*/

	usort(
		$in_progress,
		function (
			$a,
			$b
		) {

			return
				$b['updated'] <=>
				$a['updated'];
		}
	);

	usort(
		$completed,
		function (
			$a,
			$b
		) {

			return
				$b['updated'] <=>
				$a['updated'];
		}
	);


	$total_paintings =
		count(
			$paintings
		);

	$total_completed =
		count(
			$completed
		);

	$total_in_progress =
		count(
			$in_progress
		);

	$gallery_url = home_url( '/paintings/' );

	$favorite_ids = diamondistry_get_user_favorite_ids( $user_id );
	$favourites   = array();

	foreach ( $favorite_ids as $favorite_id ) {
		$key = (string) $favorite_id;
		if ( ! isset( $painting_lookup[ $key ] ) ) {
			continue;
		}
		$favourites[] = array(
			'painting' => $painting_lookup[ $key ],
			'progress' => isset( $all_progress[ $key ] ) ? $all_progress[ $key ] : null,
		);
	}

	$total_favourites = count( $favourites );
	$total_started    = $total_in_progress + $total_completed;
	$first_name       = $user->first_name ? $user->first_name : $user->display_name;


	ob_start();

	?>

	<div class="diamondistry-dashboard">

		<div class="diamondistry-dashboard-top">
			<div class="diamondistry-dashboard-welcome">
				<span class="diamondistry-dashboard-avatar"><?php echo get_avatar( $user->ID, 96 ); ?></span>
				<div>
					<h1>Welcome back, <?php echo esc_html( $first_name ); ?>!</h1>
					<p>Keep creating, you're doing great!</p>
				</div>
			</div>
			<div class="diamondistry-dashboard-balance">
				<span>Your balance</span>
				<strong>
					<svg viewBox="0 0 20 20" width="22" height="22" aria-hidden="true"><path fill="#fff" d="M10 1.2 17.2 7.4 10 18.8 2.8 7.4Z"/></svg>
					<?php echo esc_html( number_format_i18n( $balance ) ); ?>
				</strong>
			</div>
		</div>

		<div class="diamondistry-dashboard-layout">
			<div>
				<div class="diamondistry-dashboard-tabs" role="tablist">
					<button type="button" class="is-active" role="tab" aria-selected="true" data-dashboard-tab="progress">In Progress (<?php echo esc_html( $total_in_progress ); ?>)</button>
					<button type="button" role="tab" aria-selected="false" data-dashboard-tab="completed">Completed (<?php echo esc_html( $total_completed ); ?>)</button>
					<button type="button" role="tab" aria-selected="false" data-dashboard-tab="favourites">Favourites (<?php echo esc_html( $total_favourites ); ?>)</button>
				</div>

				<div class="diamondistry-dashboard-panel" data-dashboard-panel="progress">
					<?php if ( ! empty( $in_progress ) ) : ?>
						<div class="diamondistry-dashboard-painting-grid">
							<?php foreach ( $in_progress as $item ) : ?>
								<?php
								echo diamondistry_render_painting_card(
									$item['painting'],
									array(
										'context'  => 'dashboard',
										'progress' => $item['progress'],
									)
								);
								?>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="diamondistry-dashboard-empty">
							<h3>No paintings in progress</h3>
							<p>Choose a painting from the gallery and start creating.</p>
							<a href="<?php echo esc_url( $gallery_url ); ?>">Find a painting →</a>
						</div>
					<?php endif; ?>
				</div>

				<div class="diamondistry-dashboard-panel" data-dashboard-panel="completed" hidden>
					<?php if ( ! empty( $completed ) ) : ?>
						<div class="diamondistry-dashboard-painting-grid">
							<?php foreach ( $completed as $item ) : ?>
								<?php
								echo diamondistry_render_painting_card(
									$item['painting'],
									array(
										'context'  => 'dashboard',
										'progress' => $item['progress'],
									)
								);
								?>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="diamondistry-dashboard-empty">
							<h3>Your collection is waiting</h3>
							<p>Complete your first painting and it will appear here.</p>
						</div>
					<?php endif; ?>
				</div>

				<div class="diamondistry-dashboard-panel" data-dashboard-panel="favourites" hidden>
					<?php if ( ! empty( $favourites ) ) : ?>
						<div class="diamondistry-dashboard-painting-grid">
							<?php foreach ( $favourites as $item ) : ?>
								<?php
								echo diamondistry_render_painting_card(
									$item['painting'],
									array(
										'context'  => 'dashboard',
										'progress' => $item['progress'],
									)
								);
								?>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="diamondistry-dashboard-empty">
							<h3>No favourites yet</h3>
							<p>Tap the heart on a painting to keep it here.</p>
							<a href="<?php echo esc_url( $gallery_url ); ?>">Browse paintings →</a>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<aside class="diamondistry-dashboard-stats">
				<h2>Your Stats</h2>
				<ul>
					<li><span>Paintings started</span><strong><?php echo esc_html( $total_started ); ?></strong></li>
					<li><span>Paintings completed</span><strong><?php echo esc_html( $total_completed ); ?></strong></li>
					<li><span>Diamonds earned</span><strong><?php echo esc_html( number_format_i18n( $balance ) ); ?></strong></li>
					<li><span>Favourite paintings</span><strong><?php echo esc_html( $total_favourites ); ?></strong></li>
				</ul>
			</aside>
		</div>
	</div>

	<?php

	return ob_get_clean();
}

add_shortcode(
	'diamondistry_dashboard',
	'diamondistry_render_dashboard'
);