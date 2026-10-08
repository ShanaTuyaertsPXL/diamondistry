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

	$gallery_url =
		home_url(
			'/paintings/'
		);

	$logout_url =
		wp_logout_url(
			home_url(
				'/'
			)
		);


	ob_start();

	?>

	<div class="diamondistry-dashboard">

		<header class="diamondistry-dashboard-header">

			<div>

				<p class="diamondistry-dashboard-eyebrow">
					My Diamondistry
				</p>

				<h1>
					Hi <?php echo esc_html( $user->display_name ); ?> 👋
				</h1>

				<p class="diamondistry-dashboard-intro">
					Continue your paintings, check your rewards and see what you've completed.
				</p>

			</div>


			<div class="diamondistry-dashboard-header-actions">

				<a
					href="<?php echo esc_url( $gallery_url ); ?>"
					class="diamondistry-dashboard-primary-button"
				>
					Browse paintings
				</a>

				<a
					href="<?php echo esc_url( $logout_url ); ?>"
					class="diamondistry-dashboard-secondary-link"
				>
					Log out
				</a>

			</div>

		</header>


		<section class="diamondistry-dashboard-stats">

			<div class="diamondistry-dashboard-stat diamondistry-dashboard-balance">

				<span class="diamondistry-dashboard-stat-icon">
					💎
				</span>

				<div>

					<strong>
						<?php echo esc_html( $balance ); ?>
					</strong>

					<span>
						Diamond balance
					</span>

				</div>

			</div>


			<div class="diamondistry-dashboard-stat">

				<strong>
					<?php echo esc_html( $total_completed ); ?>
				</strong>

				<span>
					Completed
				</span>

			</div>


			<div class="diamondistry-dashboard-stat">

				<strong>
					<?php echo esc_html( $total_in_progress ); ?>
				</strong>

				<span>
					In progress
				</span>

			</div>


			<div class="diamondistry-dashboard-stat">

				<strong>
					<?php echo esc_html( $total_paintings ); ?>
				</strong>

				<span>
					Available paintings
				</span>

			</div>

		</section>


		<section class="diamondistry-dashboard-section">

			<div class="diamondistry-dashboard-section-heading">

				<div>

					<p class="diamondistry-dashboard-section-eyebrow">
						Keep going
					</p>

					<h2>
						Continue painting
					</h2>

				</div>

				<?php if (
					$total_in_progress > 0
				) : ?>

					<span>
						<?php echo esc_html( $total_in_progress ); ?>
						in progress
					</span>

				<?php endif; ?>

			</div>


			<?php if (
				! empty(
					$in_progress
				)
			) : ?>

				<div class="diamondistry-dashboard-painting-grid">

					<?php foreach (
						$in_progress as
						$item
					) : ?>

						<?php
						echo diamondistry_render_painting_card(
							$item['painting'],
							array(
								'context' =>
									'dashboard',

								'progress' =>
									$item['progress'],
							)
						);
						?>

					<?php endforeach; ?>

				</div>

			<?php else : ?>

				<div class="diamondistry-dashboard-empty">

					<div>
						✨
					</div>

					<h3>
						No paintings in progress
					</h3>

					<p>
						Choose a painting from the gallery and start creating.
					</p>

					<a
						href="<?php echo esc_url( $gallery_url ); ?>"
					>
						Find a painting →
					</a>

				</div>

			<?php endif; ?>

		</section>


		<section class="diamondistry-dashboard-section">

			<div class="diamondistry-dashboard-section-heading">

				<div>

					<p class="diamondistry-dashboard-section-eyebrow">
						Your collection
					</p>

					<h2>
						Completed paintings
					</h2>

				</div>

				<?php if (
					$total_completed > 0
				) : ?>

					<span>
						<?php echo esc_html( $total_completed ); ?>
						completed
					</span>

				<?php endif; ?>

			</div>


			<?php if (
				! empty(
					$completed
				)
			) : ?>

				<div class="diamondistry-dashboard-painting-grid">

					<?php foreach (
						$completed as
						$item
					) : ?>

						<?php
						echo diamondistry_render_painting_card(
							$item['painting'],
							array(
								'context' =>
									'dashboard',

								'progress' =>
									$item['progress'],
							)
						);
						?>

					<?php endforeach; ?>

				</div>

			<?php else : ?>

				<div class="diamondistry-dashboard-empty">

					<div>
						💎
					</div>

					<h3>
						Your collection is waiting
					</h3>

					<p>
						Complete your first painting and it will appear here.
					</p>

				</div>

			<?php endif; ?>

		</section>

	</div>

	<?php

	return ob_get_clean();
}

add_shortcode(
	'diamondistry_dashboard',
	'diamondistry_render_dashboard'
);