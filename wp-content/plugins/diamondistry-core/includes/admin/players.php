<?php
/**
 * Diamondistry admin players page.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
|--------------------------------------------------------------------------
| Admin menu
|--------------------------------------------------------------------------
*/

function diamondistry_register_players_page() {

	add_users_page(
		'Diamondistry Players',
		'Diamondistry Players',
		'manage_options',
		'diamondistry-players',
		'diamondistry_render_players_page'
	);
}

add_action(
	'admin_menu',
	'diamondistry_register_players_page'
);


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function diamondistry_get_player_progress_summary( $user_id ) {

	$progress =
		diamondistry_get_all_user_progress(
			$user_id
		);

	$completed =
		0;

	$in_progress =
		0;

	$last_activity =
		null;

	foreach (
		$progress as
		$painting_progress
	) {

		if (
			! is_array(
				$painting_progress
			)
		) {
			continue;
		}

		if (
			! empty(
				$painting_progress['completed']
			)
		) {
			$completed++;
		} elseif (
			! empty(
				$painting_progress['completedCells']
			)
		) {
			$in_progress++;
		}

		if (
			! empty(
				$painting_progress['updatedAt']
			)
		) {

			$updated_at =
				strtotime(
					$painting_progress['updatedAt']
				);

			if (
				$updated_at &&
				(
					! $last_activity ||
					$updated_at >
						$last_activity
				)
			) {
				$last_activity =
					$updated_at;
			}
		}
	}

	return array(
		'completed' =>
			$completed,

		'in_progress' =>
			$in_progress,

		'last_activity' =>
			$last_activity,
	);
}


function diamondistry_get_painting_title_by_id(
	$painting_id
) {

	$post =
		get_post(
			$painting_id
		);

	if (
		! $post ||
		'ddp_painting' !==
			$post->post_type
	) {
		return sprintf(
			'Painting #%d',
			absint(
				$painting_id
			)
		);
	}

	return get_the_title(
		$post
	);
}


function diamondistry_get_painting_reward_by_id(
	$painting_id
) {

	$post =
		get_post(
			$painting_id
		);

	if (
		! $post ||
		'ddp_painting' !==
			$post->post_type
	) {
		return 0;
	}

	$data =
		diamondistry_get_painting_data(
			$post
		);

	if (
		! $data ||
		! isset(
			$data['reward']
		)
	) {
		return 0;
	}

	return absint(
		$data['reward']
	);
}


/*
|--------------------------------------------------------------------------
| Redirect helper
|--------------------------------------------------------------------------
*/

function diamondistry_players_redirect(
	$user_id,
	$message
) {

	$url =
		add_query_arg(
			array(
				'page' =>
					'diamondistry-players',

				'player' =>
					absint(
						$user_id
					),

				'diamondistry_message' =>
					sanitize_key(
						$message
					),
			),
			admin_url(
				'users.php'
			)
		);

	wp_safe_redirect(
		$url
	);

	exit;
}


/*
|--------------------------------------------------------------------------
| Admin actions
|--------------------------------------------------------------------------
*/

function diamondistry_handle_player_admin_actions() {

	if (
		! is_admin() ||
		! current_user_can(
			'manage_options'
		)
	) {
		return;
	}

	if (
		empty(
			$_POST['diamondistry_player_action']
		)
	) {
		return;
	}

	check_admin_referer(
		'diamondistry_player_admin_action',
		'diamondistry_player_nonce'
	);

	$action =
		sanitize_key(
			wp_unslash(
				$_POST['diamondistry_player_action']
			)
		);

	$user_id =
		isset(
			$_POST['user_id']
		)
			? absint(
				$_POST['user_id']
			)
			: 0;

	if (
		! $user_id ||
		! get_user_by(
			'id',
			$user_id
		)
	) {
		return;
	}


	/*
	|--------------------------------------------------------------------------
	| Reset one painting progress
	|--------------------------------------------------------------------------
	*/

	if (
		'reset_painting' ===
		$action
	) {

		$painting_id =
			isset(
				$_POST['painting_id']
			)
				? absint(
					$_POST['painting_id']
				)
				: 0;

		if (
			! $painting_id
		) {
			return;
		}

		$all_progress =
			diamondistry_get_all_user_progress(
				$user_id
			);

		$key =
			(string)
			$painting_id;

		$reward_claimed =
			false;

		if (
			isset(
				$all_progress[
					$key
				]['rewardClaimed']
			)
		) {
			$reward_claimed =
				(bool)
				$all_progress[
					$key
				]['rewardClaimed'];
		}

		$all_progress[
			$key
		] =
			array(
				'completedCells' =>
					array(),

				'selectedColor' =>
					null,

				'completed' =>
					false,

				'rewardClaimed' =>
					$reward_claimed,

				'updatedAt' =>
					current_time(
						'mysql',
						true
					),
			);

		update_user_meta(
			$user_id,
			DIAMONDISTRY_PROGRESS_META_KEY,
			$all_progress
		);

		diamondistry_players_redirect(
			$user_id,
			'painting-reset'
		);
	}


	/*
	|--------------------------------------------------------------------------
	| Reset one painting reward
	|--------------------------------------------------------------------------
	|
	| Keeps current balance unchanged.
	|
	*/

	if (
		'reset_painting_reward' ===
		$action
	) {

		$painting_id =
			isset(
				$_POST['painting_id']
			)
				? absint(
					$_POST['painting_id']
				)
				: 0;

		if (
			! $painting_id
		) {
			return;
		}

		$all_progress =
			diamondistry_get_all_user_progress(
				$user_id
			);

		$key =
			(string)
			$painting_id;

		if (
			isset(
				$all_progress[
					$key
				]
			) &&
			is_array(
				$all_progress[
					$key
				]
			)
		) {

			$all_progress[
				$key
			]['rewardClaimed'] =
				false;

			$all_progress[
				$key
			]['updatedAt'] =
				current_time(
					'mysql',
					true
				);

			update_user_meta(
				$user_id,
				DIAMONDISTRY_PROGRESS_META_KEY,
				$all_progress
			);
		}

		diamondistry_players_redirect(
			$user_id,
			'painting-reward-reset'
		);
	}


	/*
	|--------------------------------------------------------------------------
	| Revoke one painting reward
	|--------------------------------------------------------------------------
	|
	| Sets rewardClaimed to false AND subtracts the painting reward
	| from the user's current balance.
	|
	*/

	if (
		'revoke_painting_reward' ===
		$action
	) {

		$painting_id =
			isset(
				$_POST['painting_id']
			)
				? absint(
					$_POST['painting_id']
				)
				: 0;

		if (
			! $painting_id
		) {
			return;
		}

		$all_progress =
			diamondistry_get_all_user_progress(
				$user_id
			);

		$key =
			(string)
			$painting_id;

		$was_claimed =
			! empty(
				$all_progress[
					$key
				]['rewardClaimed']
			);

		/*
		 * Only subtract diamonds if this reward was
		 * actually marked as claimed.
		 */
		if (
			$was_claimed
		) {

			$reward =
				diamondistry_get_painting_reward_by_id(
					$painting_id
				);

			$current_balance =
				diamondistry_get_user_balance(
					$user_id
				);

			$new_balance =
				max(
					0,
					$current_balance -
					$reward
				);

			update_user_meta(
				$user_id,
				DIAMONDISTRY_BALANCE_META_KEY,
				$new_balance
			);
		}

		if (
			isset(
				$all_progress[
					$key
				]
			) &&
			is_array(
				$all_progress[
					$key
				]
			)
		) {

			$all_progress[
				$key
			]['rewardClaimed'] =
				false;

			$all_progress[
				$key
			]['updatedAt'] =
				current_time(
					'mysql',
					true
				);

			update_user_meta(
				$user_id,
				DIAMONDISTRY_PROGRESS_META_KEY,
				$all_progress
			);
		}

		diamondistry_players_redirect(
			$user_id,
			'painting-reward-revoked'
		);
	}


	/*
	|--------------------------------------------------------------------------
	| Reset one painting progress + reward
	|--------------------------------------------------------------------------
	|
	| Does NOT change current balance.
	|
	*/

	if (
		'reset_painting_all' ===
		$action
	) {

		$painting_id =
			isset(
				$_POST['painting_id']
			)
				? absint(
					$_POST['painting_id']
				)
				: 0;

		if (
			! $painting_id
		) {
			return;
		}

		$all_progress =
			diamondistry_get_all_user_progress(
				$user_id
			);

		$key =
			(string)
			$painting_id;

		$all_progress[
			$key
		] =
			array(
				'completedCells' =>
					array(),

				'selectedColor' =>
					null,

				'completed' =>
					false,

				'rewardClaimed' =>
					false,

				'updatedAt' =>
					current_time(
						'mysql',
						true
					),
			);

		update_user_meta(
			$user_id,
			DIAMONDISTRY_PROGRESS_META_KEY,
			$all_progress
		);

		diamondistry_players_redirect(
			$user_id,
			'painting-all-reset'
		);
	}


	/*
	|--------------------------------------------------------------------------
	| Reset all progress
	|--------------------------------------------------------------------------
	*/

	if (
		'reset_all_progress' ===
		$action
	) {

		$all_progress =
			diamondistry_get_all_user_progress(
				$user_id
			);

		foreach (
			$all_progress as
			$key =>
			$painting_progress
		) {

			$reward_claimed =
				! empty(
					$painting_progress[
						'rewardClaimed'
					]
				);

			$all_progress[
				$key
			] =
				array(
					'completedCells' =>
						array(),

					'selectedColor' =>
						null,

					'completed' =>
						false,

					'rewardClaimed' =>
						$reward_claimed,

					'updatedAt' =>
						current_time(
							'mysql',
							true
						),
				);
		}

		update_user_meta(
			$user_id,
			DIAMONDISTRY_PROGRESS_META_KEY,
			$all_progress
		);

		diamondistry_players_redirect(
			$user_id,
			'all-progress-reset'
		);
	}


	/*
	|--------------------------------------------------------------------------
	| Reset all rewards + balance
	|--------------------------------------------------------------------------
	*/

	if (
		'reset_all_rewards' ===
		$action
	) {

		$all_progress =
			diamondistry_get_all_user_progress(
				$user_id
			);

		foreach (
			$all_progress as
			$key =>
			$painting_progress
		) {

			if (
				! is_array(
					$painting_progress
				)
			) {
				continue;
			}

			$all_progress[
				$key
			]['rewardClaimed'] =
				false;

			$all_progress[
				$key
			]['updatedAt'] =
				current_time(
					'mysql',
					true
				);
		}

		update_user_meta(
			$user_id,
			DIAMONDISTRY_PROGRESS_META_KEY,
			$all_progress
		);

		update_user_meta(
			$user_id,
			DIAMONDISTRY_BALANCE_META_KEY,
			0
		);

		diamondistry_players_redirect(
			$user_id,
			'rewards-reset'
		);
	}


	/*
	|--------------------------------------------------------------------------
	| Change balance
	|--------------------------------------------------------------------------
	*/

	if (
		'update_balance' ===
		$action
	) {

		$balance =
			isset(
				$_POST['balance']
			)
				? max(
					0,
					absint(
						$_POST['balance']
					)
				)
				: 0;

		update_user_meta(
			$user_id,
			DIAMONDISTRY_BALANCE_META_KEY,
			$balance
		);

		diamondistry_players_redirect(
			$user_id,
			'balance-updated'
		);
	}
}

add_action(
	'admin_init',
	'diamondistry_handle_player_admin_actions'
);


/*
|--------------------------------------------------------------------------
| Notices
|--------------------------------------------------------------------------
*/

function diamondistry_render_players_notice() {

	if (
		empty(
			$_GET[
				'diamondistry_message'
			]
		)
	) {
		return;
	}

	$message_key =
		sanitize_key(
			wp_unslash(
				$_GET[
					'diamondistry_message'
				]
			)
		);

	$messages =
		array(
			'painting-reset' =>
				'Painting progress reset.',

			'painting-reward-reset' =>
				'Painting reward reset. Balance was not changed.',

			'painting-reward-revoked' =>
				'Painting reward revoked and removed from the diamond balance.',

			'painting-all-reset' =>
				'Painting progress and reward reset. Balance was not changed.',

			'all-progress-reset' =>
				'All painting progress reset.',

			'rewards-reset' =>
				'All rewards and diamond balance reset.',

			'balance-updated' =>
				'Diamond balance updated.',
		);

	if (
		! isset(
			$messages[
				$message_key
			]
		)
	) {
		return;
	}

	?>
	<div class="notice notice-success is-dismissible">
		<p>
			<?php
			echo esc_html(
				$messages[
					$message_key
				]
			);
			?>
		</p>
	</div>
	<?php
}


/*
|--------------------------------------------------------------------------
| Main admin page
|--------------------------------------------------------------------------
*/

function diamondistry_render_players_page() {

	if (
		! current_user_can(
			'manage_options'
		)
	) {
		return;
	}

	$selected_user_id =
		isset(
			$_GET['player']
		)
			? absint(
				$_GET['player']
			)
			: 0;

	?>
	<div class="wrap">

		<h1>
			Diamondistry Players
		</h1>

		<?php
		diamondistry_render_players_notice();
		?>

		<?php

		if (
			$selected_user_id
		) {

			diamondistry_render_player_detail(
				$selected_user_id
			);

		} else {

			diamondistry_render_players_table();

		}

		?>

	</div>
	<?php
}


/*
|--------------------------------------------------------------------------
| Players overview
|--------------------------------------------------------------------------
*/

function diamondistry_render_players_table() {

	$users =
		get_users(
			array(
				'orderby' =>
					'display_name',

				'order' =>
					'ASC',
			)
		);

	?>

	<table class="widefat striped">

		<thead>

			<tr>

				<th>
					Player
				</th>

				<th>
					Email
				</th>

				<th>
					💎 Balance
				</th>

				<th>
					Completed
				</th>

				<th>
					In progress
				</th>

				<th>
					Last activity
				</th>

				<th>
					Action
				</th>

			</tr>

		</thead>

		<tbody>

			<?php foreach (
				$users as
				$user
			) : ?>

				<?php

				$summary =
					diamondistry_get_player_progress_summary(
						$user->ID
					);

				$balance =
					diamondistry_get_user_balance(
						$user->ID
					);

				$view_url =
					add_query_arg(
						array(
							'page' =>
								'diamondistry-players',

							'player' =>
								$user->ID,
						),
						admin_url(
							'users.php'
						)
					);

				?>

				<tr>

					<td>
						<strong>
							<?php
							echo esc_html(
								$user->display_name
							);
							?>
						</strong>
					</td>

					<td>
						<?php
						echo esc_html(
							$user->user_email
						);
						?>
					</td>

					<td>
						<?php
						echo esc_html(
							$balance
						);
						?>
					</td>

					<td>
						<?php
						echo esc_html(
							$summary[
								'completed'
							]
						);
						?>
					</td>

					<td>
						<?php
						echo esc_html(
							$summary[
								'in_progress'
							]
						);
						?>
					</td>

					<td>

						<?php

						if (
							$summary[
								'last_activity'
							]
						) {

							echo esc_html(
								wp_date(
									'Y-m-d H:i',
									$summary[
										'last_activity'
									]
								)
							);

						} else {

							echo '—';

						}

						?>

					</td>

					<td>

						<a
							class="button button-small"
							href="<?php echo esc_url( $view_url ); ?>"
						>
							View
						</a>

					</td>

				</tr>

			<?php endforeach; ?>

		</tbody>

	</table>

	<?php
}


/*
|--------------------------------------------------------------------------
| Player detail
|--------------------------------------------------------------------------
*/

function diamondistry_render_player_detail(
	$user_id
) {

	$user =
		get_user_by(
			'id',
			$user_id
		);

	if (
		! $user
	) {
		echo '<p>Player not found.</p>';

		return;
	}

	$progress =
		diamondistry_get_all_user_progress(
			$user_id
		);

	$balance =
		diamondistry_get_user_balance(
			$user_id
		);

	$back_url =
		add_query_arg(
			array(
				'page' =>
					'diamondistry-players',
			),
			admin_url(
				'users.php'
			)
		);

	?>

	<p>
		<a
			href="<?php echo esc_url( $back_url ); ?>"
		>
			← Back to players
		</a>
	</p>

	<h2>
		<?php
		echo esc_html(
			$user->display_name
		);
		?>
	</h2>

	<p>
		<?php
		echo esc_html(
			$user->user_email
		);
		?>
	</p>

	<hr>

	<h2>
		Diamond balance
	</h2>

	<form method="post">

		<?php
		wp_nonce_field(
			'diamondistry_player_admin_action',
			'diamondistry_player_nonce'
		);
		?>

		<input
			type="hidden"
			name="diamondistry_player_action"
			value="update_balance"
		>

		<input
			type="hidden"
			name="user_id"
			value="<?php echo esc_attr( $user_id ); ?>"
		>

		<input
			type="number"
			name="balance"
			min="0"
			value="<?php echo esc_attr( $balance ); ?>"
		>

		<button
			type="submit"
			class="button button-primary"
		>
			Update balance
		</button>

	</form>

	<hr>

	<h2>
		Painting progress
	</h2>

	<?php if (
		empty(
			$progress
		)
	) : ?>

		<p>
			This player has no saved painting progress yet.
		</p>

	<?php else : ?>

		<table class="widefat striped">

			<thead>

				<tr>

					<th>
						Painting
					</th>

					<th>
						Status
					</th>

					<th>
						Progress
					</th>

					<th>
						Reward
					</th>

					<th>
						Reward claimed
					</th>

					<th>
						Last updated
					</th>

					<th>
						Actions
					</th>

				</tr>

			</thead>

			<tbody>

				<?php foreach (
					$progress as
					$painting_id =>
					$painting_progress
				) : ?>

					<?php

					$painting_id =
						absint(
							$painting_id
						);

					$painting =
						get_post(
							$painting_id
						);

					$total_cells =
						0;

					$reward =
						0;

					if (
						$painting &&
						'ddp_painting' ===
							$painting->post_type
					) {

						$data =
							diamondistry_get_painting_data(
								$painting
							);

						if (
							$data
						) {

							$total_cells =
								$data['width'] *
								$data['height'];

							$reward =
								absint(
									$data['reward']
								);
						}
					}

					$completed_cells =
						isset(
							$painting_progress[
								'completedCells'
							]
						) &&
						is_array(
							$painting_progress[
								'completedCells'
							]
						)
							? count(
								array_unique(
									array_map(
										'absint',
										$painting_progress[
											'completedCells'
										]
									)
								)
							)
							: 0;

					$percentage =
						$total_cells > 0
							? min(
								100,
								round(
									(
										$completed_cells /
										$total_cells
									) *
									100
								)
							)
							: 0;

					$reward_claimed =
						! empty(
							$painting_progress[
								'rewardClaimed'
							]
						);

					?>

					<tr>

						<td>
							<strong>
								<?php
								echo esc_html(
									diamondistry_get_painting_title_by_id(
										$painting_id
									)
								);
								?>
							</strong>
						</td>

						<td>

							<?php

							if (
								! empty(
									$painting_progress[
										'completed'
									]
								)
							) {

								echo 'Completed';

							} elseif (
								$completed_cells > 0
							) {

								echo 'In progress';

							} else {

								echo 'Not started';

							}

							?>

						</td>

						<td>
							<?php
							echo esc_html(
								$completed_cells .
								' / ' .
								$total_cells .
								' (' .
								$percentage .
								'%)'
							);
							?>
						</td>

						<td>
							<?php
							echo esc_html(
								$reward
							);
							?>
							💎
						</td>

						<td>
							<?php
							echo $reward_claimed
								? 'Yes'
								: 'No';
							?>
						</td>

						<td>

							<?php

							if (
								! empty(
									$painting_progress[
										'updatedAt'
									]
								)
							) {

								$timestamp =
									strtotime(
										$painting_progress[
											'updatedAt'
										]
									);

								echo $timestamp
									? esc_html(
										wp_date(
											'Y-m-d H:i',
											$timestamp
										)
									)
									: '—';

							} else {

								echo '—';

							}

							?>

						</td>

						<td>

							<div
								style="
									display:flex;
									flex-wrap:wrap;
									gap:6px;
								"
							>

								<form method="post">

									<?php
									wp_nonce_field(
										'diamondistry_player_admin_action',
										'diamondistry_player_nonce'
									);
									?>

									<input
										type="hidden"
										name="diamondistry_player_action"
										value="reset_painting"
									>

									<input
										type="hidden"
										name="user_id"
										value="<?php echo esc_attr( $user_id ); ?>"
									>

									<input
										type="hidden"
										name="painting_id"
										value="<?php echo esc_attr( $painting_id ); ?>"
									>

									<button
										type="submit"
										class="button button-small"
										onclick="return confirm('Reset progress for this painting? The reward stays protected.');"
									>
										Reset progress
									</button>

								</form>


								<form method="post">

									<?php
									wp_nonce_field(
										'diamondistry_player_admin_action',
										'diamondistry_player_nonce'
									);
									?>

									<input
										type="hidden"
										name="diamondistry_player_action"
										value="reset_painting_reward"
									>

									<input
										type="hidden"
										name="user_id"
										value="<?php echo esc_attr( $user_id ); ?>"
									>

									<input
										type="hidden"
										name="painting_id"
										value="<?php echo esc_attr( $painting_id ); ?>"
									>

									<button
										type="submit"
										class="button button-small"
										onclick="return confirm('Reset reward claim? Current diamond balance will NOT change.');"
									>
										Reset reward
									</button>

								</form>


								<?php if (
									$reward_claimed
								) : ?>

									<form method="post">

										<?php
										wp_nonce_field(
											'diamondistry_player_admin_action',
											'diamondistry_player_nonce'
										);
										?>

										<input
											type="hidden"
											name="diamondistry_player_action"
											value="revoke_painting_reward"
										>

										<input
											type="hidden"
											name="user_id"
											value="<?php echo esc_attr( $user_id ); ?>"
										>

										<input
											type="hidden"
											name="painting_id"
											value="<?php echo esc_attr( $painting_id ); ?>"
										>

										<button
											type="submit"
											class="button button-small"
											onclick="return confirm('Revoke this reward? <?php echo esc_js( $reward ); ?> diamonds will be removed from the current balance and the reward can be earned again.');"
										>
											Revoke reward (-<?php echo esc_html( $reward ); ?> 💎)
										</button>

									</form>

								<?php endif; ?>


								<form method="post">

									<?php
									wp_nonce_field(
										'diamondistry_player_admin_action',
										'diamondistry_player_nonce'
									);
									?>

									<input
										type="hidden"
										name="diamondistry_player_action"
										value="reset_painting_all"
									>

									<input
										type="hidden"
										name="user_id"
										value="<?php echo esc_attr( $user_id ); ?>"
									>

									<input
										type="hidden"
										name="painting_id"
										value="<?php echo esc_attr( $painting_id ); ?>"
									>

									<button
										type="submit"
										class="button button-small"
										onclick="return confirm('Reset progress AND reward claim? Current diamond balance will NOT change.');"
									>
										Reset both
									</button>

								</form>

							</div>

						</td>

					</tr>

				<?php endforeach; ?>

			</tbody>

		</table>

	<?php endif; ?>

	<hr>

	<h2>
		Danger zone
	</h2>

	<p>
		<strong>Reset all progress</strong>
		keeps claimed rewards protected.
		<strong>Reset all rewards + balance</strong>
		allows every reward to be earned again.
	</p>

	<form
		method="post"
		style="
			display:inline-block;
			margin-right:8px;
		"
	>

		<?php
		wp_nonce_field(
			'diamondistry_player_admin_action',
			'diamondistry_player_nonce'
		);
		?>

		<input
			type="hidden"
			name="diamondistry_player_action"
			value="reset_all_progress"
		>

		<input
			type="hidden"
			name="user_id"
			value="<?php echo esc_attr( $user_id ); ?>"
		>

		<button
			type="submit"
			class="button"
			onclick="return confirm('Reset ALL painting progress? Claimed rewards remain protected.');"
		>
			Reset all progress
		</button>

	</form>


	<form
		method="post"
		style="display:inline-block;"
	>

		<?php
		wp_nonce_field(
			'diamondistry_player_admin_action',
			'diamondistry_player_nonce'
		);
		?>

		<input
			type="hidden"
			name="diamondistry_player_action"
			value="reset_all_rewards"
		>

		<input
			type="hidden"
			name="user_id"
			value="<?php echo esc_attr( $user_id ); ?>"
		>

		<button
			type="submit"
			class="button"
			onclick="return confirm('Reset ALL reward claims and set the diamond balance to 0?');"
		>
			Reset all rewards + balance
		</button>

	</form>

	<?php
}